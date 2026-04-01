<?php
/**
 * Plugin Name: DayOff - Gestion de Congés Pro
 * Version: 1.0
 */

if (!defined('ABSPATH'))
    exit;

/** 1. RÉGLAGES **/
function gcp_get_settings()
{
    $defaults = array(
        'acquisition_mode' => 'monthly',
        'cp_rate' => 2.08,
        'types_enabled' => array('CP', 'RTT', 'Maladie')
    );
    $saved = get_option('dayoff_settings');
    return wp_parse_args($saved, $defaults);
}

add_action('wp_enqueue_scripts', 'gcp_enqueue_scripts');
function gcp_enqueue_scripts()
{
    if (is_page('dashboard-conges')) {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'dayoff-js',
            plugin_dir_url(__FILE__) . 'assets/js/index.js',
            array('jquery'),
            '1.1',
            true
        );
        wp_localize_script('dayoff-js', 'dayoff_ajax_url', admin_url('admin-ajax.php'));
    }
}

function gcp_get_duree_formattee($demande)
{
    $moment = isset($demande->moment_journee) ? $demande->moment_journee : 'full';
    if ($moment === 'matin' || $moment === 'apres-midi')
        return '0.5 jour (' . ucfirst($moment) . ')';
    try {
        $debut = new DateTime($demande->date_debut);
        $fin = new DateTime($demande->date_fin);
        $interval = $debut->diff($fin);
        return ($interval->days + 1) . ' jour(s)';
    } catch (Exception $e) {
        return 'Erreur date';
    }
}

/** UTILITAIRE — EMAIL HTML **/
function gcp_send_email($to, $subject, $title, $intro, $details = [], $cta_text = '', $cta_url = '')
{
    $details_html = '';
    foreach ($details as $label => $value) {
        $details_html .= "
        <tr>
            <td style='padding:8px 16px;font-size:13px;color:#6b7280;width:40%;border-bottom:1px solid #f3f4f6;'>{$label}</td>
            <td style='padding:8px 16px;font-size:13px;color:#1f2937;font-weight:600;border-bottom:1px solid #f3f4f6;'>{$value}</td>
        </tr>";
    }

    $cta_html = '';
    if ($cta_text && $cta_url) {
        $cta_html = "
        <div style='text-align:center;margin:30px 0 10px;'>
            <a href='{$cta_url}' style='background:#1f2937;color:white;padding:12px 28px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;display:inline-block;'>
                {$cta_text}
            </a>
        </div>";
    }

    $table_html = $details_html ? "
        <table style='width:100%;border-collapse:collapse;background:#f9fafb;border-radius:8px;overflow:hidden;margin-bottom:24px;'>
            {$details_html}
        </table>" : "";

    $html = "
    <!DOCTYPE html>
    <html lang='fr'>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;'>
        <div style='max-width:560px;margin:40px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);'>
            <div style='background:#1f2937;padding:28px 32px;'>
                <h1 style='margin:0;color:white;font-size:22px;font-weight:700;letter-spacing:-0.5px;'>DayOff</h1>
                <p style='margin:6px 0 0;color:rgba(255,255,255,0.6);font-size:13px;'>Gestion de Congés</p>
            </div>
            <div style='padding:32px;'>
                <h2 style='margin:0 0 8px;font-size:18px;color:#1f2937;font-weight:700;'>{$title}</h2>
                <p style='margin:0 0 24px;font-size:14px;color:#6b7280;line-height:1.6;'>{$intro}</p>
                {$table_html}
                {$cta_html}
            </div>
            <div style='padding:20px 32px;border-top:1px solid #f3f4f6;text-align:center;'>
                <p style='margin:0;font-size:11px;color:#9ca3af;'>DayOff — Gestion de Congés Pro · Cet email est automatique, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($to, $subject, $html, $headers);
}

/** 2. SOUMISSION D'UNE DEMANDE **/
add_action('wp_ajax_submit_conge', 'gcp_handle_conge_submission');
function gcp_handle_conge_submission()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'conges_demandes';
    $user_id = get_current_user_id();

    if (!is_user_logged_in())
        wp_send_json_error('Session expirée');

    // 1. Récupération des données
    $type_conge = sanitize_text_field($_POST['type_conge']);
    $moment = isset($_POST['moment_journee']) ? sanitize_text_field($_POST['moment_journee']) : 'full';
    $date_deb = sanitize_text_field($_POST['date_debut']);
    $date_fin = ($moment !== 'full') ? $date_deb : sanitize_text_field($_POST['date_fin']);
    $motif = sanitize_textarea_field($_POST['motif']);

    // 2. Calcul du nombre de jours demandés
    if ($moment !== 'full') {
        $jours_demandes = 0.5;
    } else {
        $ts_debut = strtotime($date_deb);
        $ts_fin = strtotime($date_fin);
        if ($ts_fin < $ts_debut) {
            wp_send_json_error('La date de fin ne peut pas être avant la date de début.');
        }
        $jours_demandes = (($ts_fin - $ts_debut) / 86400) + 1;
    }

    // 3. Vérification du solde (uniquement pour CP et RTT)
    if ($type_conge === 'CP' || $type_conge === 'RTT') {
        $meta_key = ($type_conge === 'CP') ? 'gcp_solde_cp' : 'gcp_solde_rtt';
        $solde_actuel = floatval(get_user_meta($user_id, $meta_key, true) ?: 0);

        // --- CONDITION DE BLOCAGE ---
        if ($solde_actuel < $jours_demandes) {
            wp_send_json_error("Solde insuffisant. Vous demandez $jours_demandes jour(s) mais votre solde est de $solde_actuel.");
        }
    }

    // 4. Gestion du fichier justificatif
    $justificatif_url = '';
    if (!empty($_FILES['justificatif']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded_file = wp_handle_upload($_FILES['justificatif'], array('test_form' => false));
        if (isset($uploaded_file['url']))
            $justificatif_url = $uploaded_file['url'];
    }

    // 5. Insertion en base de données
    $result = $wpdb->insert($table_name, array(
        'user_id' => $user_id,
        'date_debut' => $date_deb,
        'date_fin' => $date_fin,
        'type_conge' => $type_conge,
        'moment_journee' => $moment,
        'motif' => $motif,
        'statut' => 'En attente',
        'justificatif' => $justificatif_url
    ));

    if ($result) {
        $settings = gcp_get_settings();
        $admin_email = $settings['admin_email_notify'] ?? get_option('admin_email');
        $user_info = get_userdata($user_id);
        $user_name = $user_info->display_name;
        $periode = ($moment !== 'full')
            ? 'Le ' . date('d/m/Y', strtotime($date_deb)) . ' (' . ($moment === 'matin' ? 'Matin' : 'Après-midi') . ')'
            : 'Du ' . date('d/m/Y', strtotime($date_deb)) . ' au ' . date('d/m/Y', strtotime($date_fin));

        // EMAIL ADMIN
        gcp_send_email(
            $admin_email,
            "🔔 Nouvelle demande de congé — {$user_name}",
            "Nouvelle demande de congé",
            "Une nouvelle demande vient d'être déposée et attend votre validation.",
            [
                'Collaborateur' => $user_name,
                'Type' => $type_conge,
                'Période' => $periode,
                'Motif' => $motif ?: '—',
                'Statut' => '⏳ En attente',
            ],
            'Voir les demandes en attente',
            home_url('/dashboard-conges?tab=validation_conges')
        );

        // EMAIL USER
        gcp_send_email(
            $user_info->user_email,
            "Votre demande de congé a bien été transmise",
            "Demande reçue !",
            "Nous confirmons la bonne réception de votre demande. Elle sera traitée dans les meilleurs délais.",
            [
                'Type' => $type_conge,
                'Période' => $periode,
                'Statut' => '⏳ En attente de validation',
            ]
        );

        wp_send_json_success('Demande envoyée avec succès !');
    } else {
        wp_send_json_error('Erreur BDD');
    }
}

/** 3. VALIDATION ADMIN **/
add_action('wp_ajax_traiter_demande_admin', 'gcp_handle_traiter_demande');
function gcp_handle_traiter_demande()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'conges_demandes';
    if (!current_user_can('manage_options'))
        wp_send_json_error('Accès refusé');

    $demande_id = intval($_POST['demande_id']);
    $decision = sanitize_text_field($_POST['decision']);
    $commentaire = sanitize_textarea_field($_POST['commentaire_admin']);

    $demande = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $demande_id));
    if (!$demande)
        wp_send_json_error('Demande introuvable');

    if ($decision === 'Validé' && $demande->statut !== 'Validé') {
        $jours = ($demande->moment_journee !== 'full') ? 0.5 : ((strtotime($demande->date_fin) - strtotime($demande->date_debut)) / 86400 + 1);
        if ($demande->type_conge === 'Maladie') {
            $cumul = floatval(get_user_meta($demande->user_id, 'gcp_solde_maladie', true) ?: 0);
            update_user_meta($demande->user_id, 'gcp_solde_maladie', $cumul + $jours);
        } else {
            $meta = ($demande->type_conge === 'CP') ? 'gcp_solde_cp' : (($demande->type_conge === 'RTT') ? 'gcp_solde_rtt' : '');
            if ($meta) {
                $solde = floatval(get_user_meta($demande->user_id, $meta, true) ?: 0);
                update_user_meta($demande->user_id, $meta, $solde - $jours);
            }
        }
    }

    $result = $wpdb->update($table_name, array('statut' => $decision, 'commentaire_admin' => $commentaire), array('id' => $demande_id));

    if ($result !== false) {
        $user_info = get_userdata($demande->user_id);
        $periode = 'Du ' . date('d/m/Y', strtotime($demande->date_debut)) . ' au ' . date('d/m/Y', strtotime($demande->date_fin));
        $is_valide = ($decision === 'Validé');
        $emoji = $is_valide ? '✅' : '❌';

        $details = [
            'Type' => $demande->type_conge,
            'Période' => $periode,
            'Décision' => $emoji . ' ' . $decision,
        ];
        if ($commentaire)
            $details['Commentaire'] = $commentaire;

        gcp_send_email(
            $user_info->user_email,
            "{$emoji} Votre demande de congé — {$decision}",
            $is_valide ? 'Votre congé a été validé !' : 'Votre congé a été refusé',
            $is_valide
            ? "Bonne nouvelle ! Votre demande de congé a été acceptée par l'administration."
            : "Votre demande de congé n'a pas pu être acceptée cette fois-ci.",
            $details,
            'Voir mes demandes',
            home_url('/dashboard-conges?tab=mes_demandes')
        );

        wp_send_json_success("Demande mise à jour.");
    } else {
        wp_send_json_error('Erreur BDD');
    }
}

/** 4. AUTHENTIFICATION & ROUTING **/
add_action('admin_post_nopriv_gcp_login', 'gcp_handle_login');
add_action('admin_post_gcp_login', 'gcp_handle_login');
add_action('wp_ajax_nopriv_gcp_login', 'gcp_handle_login');
add_action('wp_ajax_gcp_login', 'gcp_handle_login');
function gcp_handle_login()
{
    $creds = array('user_login' => $_POST['log'], 'user_password' => $_POST['pwd'], 'remember' => true);
    $user = wp_signon($creds, false);
    if (is_wp_error($user)) {
        wp_redirect(home_url('/connexion?login=failed'));
    } else {
        $url = in_array('administrator', (array) $user->roles)
            ? home_url('/dashboard-conges?tab=validation_conges')
            : home_url('/dashboard-conges');
        wp_redirect($url);
    }
    exit;
}

add_filter('template_include', function ($template) {
    if (is_page('connexion'))
        return plugin_dir_path(__FILE__) . 'page-login.php';
    if (is_page('dashboard-conges'))
        return plugin_dir_path(__FILE__) . 'page-dashboard.php';
    return $template;
});

/** 5. DEMANDE DE MODIFICATION / ANNULATION **/
add_action('wp_ajax_request_change_conge', 'gcp_handle_request_change');
add_action('wp_ajax_nopriv_request_change_conge', 'gcp_handle_request_change');
function gcp_handle_request_change()
{
    global $wpdb;
    $table_requests = $wpdb->prefix . 'conges_modifications';
    $table_conges = $wpdb->prefix . 'conges_demandes';
    if (!is_user_logged_in())
        wp_send_json_error('Session expirée');

    $demande_id = intval($_POST['demande_id']);
    $type_action = sanitize_text_field($_POST['type_action']);
    $raison = sanitize_textarea_field($_POST['raison']);

    $data = array(
        'demande_id' => $demande_id,
        'user_id' => get_current_user_id(),
        'type_action' => $type_action,
        'raison' => $raison,
        'statut' => 'En attente',
    );

    if ($type_action === 'Modification') {
        $data['nouveau_type'] = sanitize_text_field($_POST['nouveau_type']);
        $data['nouvelle_date_debut'] = sanitize_text_field($_POST['nouveau_debut']);
        $data['nouvelle_date_fin'] = sanitize_text_field($_POST['nouvelle_fin']);
    }

    $result = $wpdb->insert($table_requests, $data);

    if ($result) {
        $settings = gcp_get_settings();
        $admin_email = $settings['admin_email_notify'] ?? get_option('admin_email');
        $user_info = get_userdata(get_current_user_id());
        $user_name = $user_info->display_name;
        $conge = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_conges WHERE id = %d", $demande_id));
        $label_action = $type_action === 'Suppression' ? 'Annulation' : 'Modification';

        $periode_actuelle = $conge
            ? 'Du ' . date('d/m/Y', strtotime($conge->date_debut)) . ' au ' . date('d/m/Y', strtotime($conge->date_fin))
            : '—';

        $details_admin = [
            'Collaborateur' => $user_name,
            'Action demandée' => $label_action,
            'Congé concerné' => ($conge ? $conge->type_conge : '—') . ' · ' . $periode_actuelle,
            'Motif' => $raison,
        ];

        if ($type_action === 'Modification') {
            $nouveau_type = sanitize_text_field($_POST['nouveau_type']) ?: ($conge->type_conge ?? '—');
            $nouveau_debut = date('d/m/Y', strtotime(sanitize_text_field($_POST['nouveau_debut'])));
            $nouveau_fin = date('d/m/Y', strtotime(sanitize_text_field($_POST['nouvelle_fin'])));
            $details_admin['Nouvelles dates'] = "{$nouveau_type} · Du {$nouveau_debut} au {$nouveau_fin}";
        }

        // EMAIL ADMIN
        gcp_send_email(
            $admin_email,
            "⚠️ Demande de {$label_action} — {$user_name}",
            "Demande de {$label_action} reçue",
            "{$user_name} sollicite une {$label_action} pour un congé déjà validé.",
            $details_admin,
            'Traiter la demande',
            home_url('/dashboard-conges?tab=modifications')
        );

        // EMAIL USER
        gcp_send_email(
            $user_info->user_email,
            "Votre demande de {$label_action} a bien été transmise",
            "Demande de {$label_action} enregistrée",
            "Votre demande a bien été transmise à l'administration. Vous serez notifié dès qu'une décision sera prise.",
            [
                'Action' => $label_action,
                'Congé concerné' => ($conge ? $conge->type_conge : '—') . ' · ' . $periode_actuelle,
                'Motif' => $raison,
                'Statut' => '⏳ En attente de validation',
            ]
        );

        wp_send_json_success('Demande de modification transmise !');
    } else {
        wp_send_json_error('Erreur BDD');
    }
}

/** 6. TRAITEMENT MODIF ADMIN **/
add_action('wp_ajax_traiter_modification_admin', 'gcp_handle_traiter_modification');
function gcp_handle_traiter_modification()
{
    global $wpdb;
    if (!current_user_can('manage_options'))
        wp_send_json_error('Accès refusé');

    $modif_id = intval($_POST['modif_id']);
    $decision = sanitize_text_field($_POST['decision']);
    $table_modifs = $wpdb->prefix . 'conges_modifications';
    $table_conges = $wpdb->prefix . 'conges_demandes';

    $modif = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_modifs WHERE id = %d", $modif_id));
    $conge_original = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_conges WHERE id = %d", $modif->demande_id));

    if ($decision === 'Validé' && $conge_original) {
        $jours_anc = (strtotime($conge_original->date_fin) - strtotime($conge_original->date_debut)) / 86400 + 1;
        $meta_anc = ($conge_original->type_conge === 'CP') ? 'gcp_solde_cp' : 'gcp_solde_rtt';
        update_user_meta($conge_original->user_id, $meta_anc, floatval(get_user_meta($conge_original->user_id, $meta_anc, true)) + $jours_anc);

        if ($modif->type_action === 'Suppression') {
            $wpdb->delete($table_conges, array('id' => $modif->demande_id));
        } else {
            $upd = [];
            if (!empty($modif->nouveau_type))
                $upd['type_conge'] = $modif->nouveau_type;
            if (!empty($modif->nouvelle_date_debut))
                $upd['date_debut'] = $modif->nouvelle_date_debut;
            if (!empty($modif->nouvelle_date_fin))
                $upd['date_fin'] = $modif->nouvelle_date_fin;
            $wpdb->update($table_conges, $upd, array('id' => $modif->demande_id));

            $new = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_conges WHERE id = %d", $modif->demande_id));
            $jours_new = (strtotime($new->date_fin) - strtotime($new->date_debut)) / 86400 + 1;
            $meta_new = ($new->type_conge === 'CP') ? 'gcp_solde_cp' : 'gcp_solde_rtt';
            update_user_meta($conge_original->user_id, $meta_new, floatval(get_user_meta($conge_original->user_id, $meta_new, true)) - $jours_new);
        }
    }

    $wpdb->update($table_modifs, array('statut' => $decision), array('id' => $modif_id));

    $user_info = get_userdata($modif->user_id);
    $is_valide = ($decision === 'Validé');
    $label_action = $modif->type_action === 'Suppression' ? 'Annulation' : 'Modification';
    $emoji = $is_valide ? '✅' : '❌';

    $periode_avant = $conge_original
        ? 'Du ' . date('d/m/Y', strtotime($conge_original->date_debut)) . ' au ' . date('d/m/Y', strtotime($conge_original->date_fin))
        : '—';

    $details = [
        'Type de demande' => $label_action,
        'Congé concerné' => ($conge_original ? $conge_original->type_conge : '—') . ' · ' . $periode_avant,
        'Décision' => $emoji . ' ' . $decision,
    ];

    if ($modif->type_action === 'Modification' && $is_valide) {
        $type_apres = $modif->nouveau_type ?: ($conge_original->type_conge ?? '—');
        $debut_apres = !empty($modif->nouvelle_date_debut) ? date('d/m/Y', strtotime($modif->nouvelle_date_debut)) : '—';
        $fin_apres = !empty($modif->nouvelle_date_fin) ? date('d/m/Y', strtotime($modif->nouvelle_date_fin)) : '—';
        $details['Nouvelles dates appliquées'] = "{$type_apres} · Du {$debut_apres} au {$fin_apres}";
    }

    if ($modif->type_action === 'Suppression' && $is_valide) {
        $details['Résultat'] = 'Le congé a été supprimé de votre planning.';
    }

    gcp_send_email(
        $user_info->user_email,
        "{$emoji} Votre demande de {$label_action} — {$decision}",
        "Demande de {$label_action} " . ($is_valide ? 'acceptée' : 'refusée'),
        $is_valide
        ? "Votre demande de {$label_action} a été acceptée par l'administration."
        : "Votre demande de {$label_action} n'a pas pu être acceptée cette fois-ci.",
        $details,
        'Voir mes demandes',
        home_url('/dashboard-conges?tab=mes_demandes')
    );

    wp_send_json_success('Action enregistrée !');
}

/** 7. EXPORT CSV **/
add_action('wp_ajax_export_users_csv', 'gcp_handle_export_users_csv');
function gcp_handle_export_users_csv()
{
    global $wpdb;
    if (!current_user_can('manage_options'))
        wp_die('Accès refusé');
    $user_id = intval($_POST['group_ids']);
    $user = get_userdata($user_id);
    if (!$user)
        wp_die('Inconnu');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=export-' . $user->user_login . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, array('Nom', 'Email', 'CP', 'RTT', 'Maladie'), ';');
    fputcsv($output, array(
        $user->display_name,
        $user->user_email,
        get_user_meta($user_id, 'gcp_solde_cp', true) ?: 0,
        get_user_meta($user_id, 'gcp_solde_rtt', true) ?: 0,
        get_user_meta($user_id, 'gcp_solde_maladie', true) ?: 0,
    ), ';');
    fclose($output);
    exit;
}

/** 8. AUTO-INSTALLATION BDD **/
function gcp_install_plugin()
{
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$wpdb->prefix}conges_demandes (
        id INT NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        date_debut DATE NOT NULL,
        date_fin DATE NOT NULL,
        type_conge VARCHAR(20) NOT NULL,
        moment_journee VARCHAR(20) DEFAULT 'full',
        motif TEXT,
        commentaire_admin TEXT,
        statut VARCHAR(20) DEFAULT 'En attente',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;");

    dbDelta("CREATE TABLE {$wpdb->prefix}conges_modifications (
        id INT NOT NULL AUTO_INCREMENT,
        demande_id INT NOT NULL,
        user_id INT NOT NULL,
        type_action VARCHAR(50) NOT NULL,
        nouveau_type VARCHAR(20),
        nouvelle_date_debut DATE,
        nouvelle_date_fin DATE,
        raison TEXT,
        statut VARCHAR(20) DEFAULT 'En attente',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;");



    function gcp_create_plugin_pages()
    {
        $pages = [
            'connexion' => 'Connexion',
            'dashboard-conges' => 'Dashboard Congés'
        ];

        foreach ($pages as $slug => $title) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title' => $title,
                    'post_content' => '', // Pas besoin de contenu car template_include gère l'affichage
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ]);
            }
        }
    }
    register_activation_hook(__FILE__, 'gcp_create_plugin_pages');




}
add_action('init', 'gcp_install_plugin');
// Déclenche la création des tables à l'activation
register_activation_hook(__FILE__, 'gcp_install_plugin');

/** 9. GESTION COMPTES **/
add_action('wp_ajax_add_new_user_admin', 'gcp_add_new_user_admin');
function gcp_add_new_user_admin()
{
    if (!current_user_can('manage_options'))
        wp_send_json_error('Refusé');
    $email = sanitize_email($_POST['user_email']);
    $user_id = wp_create_user($email, wp_generate_password(), $email);
    if (is_wp_error($user_id))
        wp_send_json_error($user_id->get_error_message());
    wp_update_user(['ID' => $user_id, 'display_name' => sanitize_text_field($_POST['display_name'])]);
    update_user_meta($user_id, 'gcp_solde_cp', floatval($_POST['new_cp']));
    update_user_meta($user_id, 'gcp_solde_rtt', floatval($_POST['new_rtt']));
    wp_send_json_success('Collaborateur créé !');
}

add_action('wp_ajax_update_user_soldes', 'gcp_update_user_soldes');
function gcp_update_user_soldes()
{
    if (!current_user_can('manage_options'))
        wp_send_json_error('Refusé');
    $tid = intval($_POST['target_user_id']);
    wp_update_user(['ID' => $tid, 'display_name' => $_POST['display_name'], 'user_email' => $_POST['user_email']]);
    update_user_meta($tid, 'gcp_solde_cp', floatval($_POST['new_cp']));
    update_user_meta($tid, 'gcp_solde_rtt', floatval($_POST['new_rtt']));
    wp_send_json_success('OK');
}

add_action('wp_ajax_delete_user_admin', 'gcp_delete_user_admin');
function gcp_delete_user_admin()
{
    if (!current_user_can('manage_options'))
        wp_send_json_error('Refusé');
    require_once(ABSPATH . 'wp-admin/includes/user.php');
    wp_delete_user(intval($_POST['target_user_id']));
    wp_send_json_success('OK');
}

add_action('wp_ajax_delete_conge_user', 'gcp_handle_delete_conge_user');
function gcp_handle_delete_conge_user()
{
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'conges_demandes', ['id' => intval($_POST['demande_id']), 'user_id' => get_current_user_id()]);
    wp_send_json_success('Annulé');
}

function gcp_get_jours_feries($annee)
{
    $easter = easter_date($annee);
    return [
        "$annee-01-01" => "An",
        "$annee-05-01" => "Travail",
        "$annee-05-08" => "1945",
        "$annee-07-14" => "National",
        "$annee-08-15" => "Assomption",
        "$annee-11-01" => "Toussaint",
        "$annee-11-11" => "Armistice",
        "$annee-12-25" => "Noël",
        date('Y-m-d', strtotime("+1 day", $easter)) => "Lundi Pâques",
        date('Y-m-d', strtotime("+39 days", $easter)) => "Ascension",
        date('Y-m-d', strtotime("+50 days", $easter)) => "Lundi Pentecôte",
    ];
}

/** MOT DE PASSE **/
add_action('wp_ajax_update_user_password', 'gcp_handle_update_password');
function gcp_handle_update_password()
{
    if (!is_user_logged_in())
        wp_send_json_error('Session expirée');
    $user_id = get_current_user_id();
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['conf_password'];

    if (empty($new_pass))
        wp_send_json_error('Le mot de passe ne peut pas être vide.');
    if ($new_pass !== $conf_pass)
        wp_send_json_error('Les mots de passe ne correspondent pas.');
    if (strlen($new_pass) < 6)
        wp_send_json_error('Le mot de passe doit faire au moins 6 caractères.');

    wp_set_password($new_pass, $user_id);
    wp_send_json_success('Mot de passe mis à jour avec succès !');
}

add_filter('wp_mail_from_name', function ($name) {
    return 'DayOff Gestion';
});
add_filter('wp_mail_from', function ($email) {
    return get_option('admin_email');
});

/** CONFIGURATION GLOBALE **/
add_action('wp_ajax_save_global_config', 'dayoff_handle_save_config');
function dayoff_handle_save_config()
{
    if (!current_user_can('manage_options'))
        wp_send_json_error('Accès refusé : droits insuffisants.');
    $settings = array(
        'acquisition_mode' => sanitize_text_field($_POST['acquisition_mode']),
        'cp_rate' => floatval($_POST['cp_rate']),
        'types_enabled' => isset($_POST['types_enabled']) ? array_map('sanitize_text_field', $_POST['types_enabled']) : array(),
    );
    update_option('dayoff_settings', $settings);
    wp_send_json_success('Configuration enregistrée !');
}

/** EXPORT PDF **/
add_action('init', 'gcp_handle_export_pdf_init');
function gcp_handle_export_pdf_init()
{
    if (!isset($_GET['dayoff_export_pdf']))
        return;
    if (!is_user_logged_in() || !current_user_can('manage_options'))
        wp_die('Accès refusé');

    global $wpdb;
    $ids = isset($_GET['ids']) ? array_map('intval', explode(',', sanitize_text_field($_GET['ids']))) : [];
    if (empty($ids))
        wp_die('Aucun utilisateur sélectionné');

    $users_data = [];
    foreach ($ids as $uid) {
        $user = get_userdata($uid);
        if (!$user)
            continue;
        $demandes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}conges_demandes WHERE user_id = %d ORDER BY date_debut DESC",
            $uid
        ));
        $users_data[] = [
            'user' => $user,
            'cp' => get_user_meta($uid, 'gcp_solde_cp', true) ?: 0,
            'rtt' => get_user_meta($uid, 'gcp_solde_rtt', true) ?: 0,
            'maladie' => get_user_meta($uid, 'gcp_solde_maladie', true) ?: 0,
            'demandes' => $demandes,
        ];
    }

    $settings = gcp_get_settings();
    $company_name = $settings['company_name'] ?? get_option('blogname');
    $date_export = date('d/m/Y à H:i');

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <title>Export DayOff</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #1f2937;
            }

            .page-header {
                background: #1f2937;
                color: white;
                padding: 24px 30px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
            }

            .page-header h1 {
                font-size: 20px;
                font-weight: 700;
            }

            .page-header .meta {
                font-size: 11px;
                opacity: .7;
                margin-top: 4px;
            }

            .page-header .badge {
                background: rgba(255, 255, 255, .15);
                border: 1px solid rgba(255, 255, 255, .3);
                border-radius: 20px;
                padding: 6px 14px;
                font-size: 11px;
            }

            .user-block {
                margin: 0 30px 30px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                overflow: hidden;
                page-break-inside: avoid;
            }

            .user-header {
                background: #f9fafb;
                padding: 14px 18px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #e5e7eb;
            }

            .left {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .avatar {
                width: 38px;
                height: 38px;
                background: #1f2937;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 15px;
                font-weight: 700;
                flex-shrink: 0;
            }

            .user-name {
                font-size: 14px;
                font-weight: 700;
            }

            .user-email {
                font-size: 11px;
                color: #6b7280;
                margin-top: 2px;
            }

            .soldes {
                display: flex;
                gap: 10px;
            }

            .solde-badge {
                border-radius: 8px;
                padding: 5px 12px;
                font-size: 11px;
                font-weight: 600;
            }

            .solde-cp {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .solde-rtt {
                background: #d1fae5;
                color: #065f46;
            }

            .solde-maladie {
                background: #fee2e2;
                color: #991b1b;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th {
                background: #f3f4f6;
                padding: 8px 14px;
                text-align: left;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: #6b7280;
                border-bottom: 1px solid #e5e7eb;
            }

            td {
                padding: 9px 14px;
                border-bottom: 1px solid #f3f4f6;
                font-size: 11px;
                vertical-align: middle;
            }

            tr:last-child td {
                border-bottom: none;
            }

            .statut {
                display: inline-block;
                padding: 2px 10px;
                border-radius: 20px;
                font-size: 10px;
                font-weight: 600;
            }

            .statut-valide {
                background: #d1fae5;
                color: #065f46;
                border: 1px solid #10b981;
            }

            .statut-attente {
                background: #fef3c7;
                color: #92400e;
                border: 1px solid #f59e0b;
            }

            .statut-refuse {
                background: #fee2e2;
                color: #991b1b;
                border: 1px solid #ef4444;
            }

            .empty-msg {
                padding: 20px;
                text-align: center;
                color: #9ca3af;
                font-style: italic;
                font-size: 11px;
            }

            .page-footer {
                margin-top: 40px;
                padding: 16px 30px;
                border-top: 1px solid #e5e7eb;
                display: flex;
                justify-content: space-between;
                font-size: 10px;
                color: #9ca3af;
            }

            @media print {
                .no-print {
                    display: none;
                }

                .user-block {
                    page-break-inside: avoid;
                }
            }
        </style>
    </head>

    <body>
        <div class="no-print"
            style="background:#f9fafb;padding:12px 30px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:10px;">
            <button onclick="window.print()"
                style="background:#1f2937;color:white;border:none;padding:8px 20px;border-radius:8px;font-size:13px;cursor:pointer;">Imprimer
                / Enregistrer en PDF</button>
            <button onclick="window.close()"
                style="background:white;color:#374151;border:1px solid #e5e7eb;padding:8px 20px;border-radius:8px;font-size:13px;cursor:pointer;">Fermer</button>
        </div>

        <div class="page-header">
            <div>
                <h1>DayOff — Rapport d'export</h1>
                <div class="meta"><?php echo esc_html($company_name); ?> · Généré le <?php echo $date_export; ?></div>
            </div>
            <div class="badge"><?php echo count($users_data); ?>
                collaborateur<?php echo count($users_data) > 1 ? 's' : ''; ?></div>
        </div>

        <?php foreach ($users_data as $ud):
            $user = $ud['user'];
            $initial = strtoupper(substr($user->display_name, 0, 1));
            ?>
            <div class="user-block">
                <div class="user-header">
                    <div class="left">
                        <div class="avatar"><?php echo $initial; ?></div>
                        <div>
                            <div class="user-name"><?php echo esc_html($user->display_name); ?></div>
                            <div class="user-email"><?php echo esc_html($user->user_email); ?></div>
                        </div>
                    </div>
                    <div class="soldes">
                        <div class="solde-badge solde-cp"><?php echo $ud['cp']; ?> CP</div>
                        <div class="solde-badge solde-rtt"><?php echo $ud['rtt']; ?> RTT</div>
                        <div class="solde-badge solde-maladie"><?php echo $ud['maladie']; ?> Maladie</div>
                    </div>
                </div>
                <?php if (!empty($ud['demandes'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Début</th>
                                <th>Fin</th>
                                <th>Durée</th>
                                <th>Statut</th>
                                <th>Commentaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ud['demandes'] as $d):
                                $debut = date('d/m/Y', strtotime($d->date_debut));
                                $fin = date('d/m/Y', strtotime($d->date_fin));
                                $moment = $d->moment_journee ?? 'full';
                                $duree = $moment !== 'full' ? '0.5 jour' : ((strtotime($d->date_fin) - strtotime($d->date_debut)) / 86400 + 1) . ' j';
                                if ($d->statut === 'Validé')
                                    $sc = 'statut-valide';
                                elseif ($d->statut === 'En attente')
                                    $sc = 'statut-attente';
                                else
                                    $sc = 'statut-refuse';
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($d->type_conge); ?></strong></td>
                                    <td><?php echo $debut; ?></td>
                                    <td><?php echo $fin; ?></td>
                                    <td><?php echo $duree; ?></td>
                                    <td><span class="statut <?php echo $sc; ?>"><?php echo esc_html($d->statut); ?></span></td>
                                    <td><?php echo !empty($d->commentaire_admin) ? esc_html($d->commentaire_admin) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-msg">Aucune demande enregistrée</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="page-footer">
            <span>DayOff — Gestion de Congés Pro</span>
            <span>Export généré le <?php echo $date_export; ?></span>
        </div>
    </body>

    </html>
    <?php
    exit;
}

/** 10. RÉCUPÉRATION DE L'HISTORIQUE POUR GESTION USER **/
add_action('wp_ajax_get_user_leave_history', 'gcp_ajax_get_user_leave_history');
function gcp_ajax_get_user_leave_history()
{
    global $wpdb;

    if (!current_user_can('manage_options'))
        wp_send_json_error('Accès refusé');

    $target_user_id = intval($_POST['target_user_id']);
    $table_name = $wpdb->prefix . 'conges_demandes';

    // On récupère les 15 dernières demandes de cet utilisateur
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT type_conge, date_debut, date_fin, statut, moment_journee 
         FROM $table_name 
         WHERE user_id = %d 
         ORDER BY created_at DESC 
         LIMIT 15",
        $target_user_id
    ));

    if ($results) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error('Aucun historique trouvé.');
    }
}

/** 11. RÉINITIALISER L'HISTORIQUE D'UN USER **/
add_action('wp_ajax_reset_user_history', 'gcp_ajax_reset_user_history');
function gcp_ajax_reset_user_history()
{
    global $wpdb;
    if (!current_user_can('manage_options'))
        wp_send_json_error('Accès refusé');

    $target_user_id = intval($_POST['target_user_id']);
    $wpdb->delete($wpdb->prefix . 'conges_demandes', array('user_id' => $target_user_id));
    $wpdb->delete($wpdb->prefix . 'conges_modifications', array('user_id' => $target_user_id));

    wp_send_json_success('Historique entièrement supprimé.');
}