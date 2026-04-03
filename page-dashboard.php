<?php

/**
 * Template Name: DayOff Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/connexion'));
    exit;
}

$settings = gcp_get_settings();
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
global $wpdb;
// Définition des noms de tables
$table_conges = $wpdb->prefix . 'conges_demandes';
$table_modifs = $wpdb->prefix . 'conges_modifications'; // C'est cette ligne qui manque !
$solde_cp = get_user_meta($user_id, 'gcp_solde_cp', true) ?: '0';
$solde_rtt = get_user_meta($user_id, 'gcp_solde_rtt', true) ?: '0';
$solde_recup = get_user_meta($user_id, 'gcp_solde_recup', true) ?: '0';
$solde_maladie = get_user_meta($user_id, 'gcp_solde_maladie', true) ?: '0';

global $wpdb;
$table_conges = $wpdb->prefix . 'conges_demandes';

// --- 1. INITIALISATION DU TABLEAU D'ÉVÉNEMENTS ---
$events = array();

// --- 2. RÉCUPÉRATION DES CONGÉS DE TOUTE L'ÉQUIPE ---
$toutes_les_demandes = $wpdb->get_results("SELECT * FROM $table_conges ORDER BY date_debut ASC");

foreach ($toutes_les_demandes as $demande) {
    $user_info = get_userdata($demande->user_id);
    $nom_complet = $user_info ? $user_info->display_name : "Inconnu";

    // Couleur selon le statut
    $color = '#3f81ea'; // Bleu (En attente)
    if ($demande->statut === 'Validé')
        $color = '#10b981'; // Vert
    if ($demande->statut === 'Refusé')
        $color = '#ef4444'; // Rouge

    $events[] = array(
        'title' => $nom_complet . ' - ' . $demande->type_conge,
        'start' => $demande->date_debut,
        'end' => date('Y-m-d', strtotime($demande->date_fin . ' +1 day')),
        'backgroundColor' => $color,
        'borderColor' => $color,
        'allDay' => true
    );
}

// --- 3. AJOUT DES JOURS FÉRIÉS ---
$anneeCourante = date('Y');
// Note : Assure-toi que la fonction gcp_get_jours_feries() est bien dans dayoff.php
if (function_exists('gcp_get_jours_feries')) {
    $feries = gcp_get_jours_feries($anneeCourante);
    foreach ($feries as $date => $nom) {
        $events[] = [
            'title' => " " . $nom,
            'start' => $date,
            'allDay' => true,
            'backgroundColor' => '#e5e7eb', // Gris très clair
            'borderColor' => '#d1d5db',
            'textColor' => '#374151',
            'display' => 'block', // 'block' pour voir le titre ou 'background' pour colorer la case
        ];
    }
}

// --- 4. RÉCUPÉRATION DES INFOS DASHBOARD ---
$aujourdhui = date('Y-m-d');
$prochaines_absences = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_conges WHERE user_id = %d AND date_debut >= %s 
    ORDER BY date_debut ASC LIMIT 3",
    $user_id,
    $aujourdhui
));

$mes_demandes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_conges WHERE user_id = %d ORDER BY created_at DESC",
    $user_id
));

$mon_historique_modifs = $wpdb->get_results($wpdb->prepare(
    "SELECT m.*, d.type_conge, d.date_debut, d.date_fin 
                         FROM {$wpdb->prefix}conges_modifications m
                         JOIN {$wpdb->prefix}conges_demandes d ON m.demande_id = d.id
                         WHERE m.user_id = %d
                         ORDER BY m.created_at DESC",
    $user_id
));

$avatar_url = get_avatar_url($user_id);
$is_admin = in_array('administrator', (array) $current_user->roles);

$total_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}conges_demandes WHERE statut = 'En attente'");
$count_pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}conges_modifications WHERE statut = 'En attente'");


?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>DayOff - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'assets/css/style.css'; ?>">
</head>

<body>

    <aside class="menu">
        <header>
            <h1>DayOff</h1>
            <button class="button" id="btn-ouvrir-demande">Déposer une demande</button>
        </header>
        <a href="#" class="menu-item active" data-target="dashboard"><i data-lucide="layout-dashboard"></i><span>Tableau
                de Bord</span></a>
        <a href="#" class="menu-item" data-target="calendrier"><i data-lucide="calendar"></i><span>Calendrier</span></a>
        <a href="#" class="menu-item" data-target="mes_demandes"><i data-lucide="tree-palm"></i><span>Mes
                Demandes</span></a>
        <?php if ($is_admin): ?>
            <hr>
            <a href="#" class="menu-item" data-target="validation_conges">
                <i data-lucide="badge-check"></i>
                <span>Gestion de congés</span>
                <?php if ($total_pending > 0): ?>
                    <span style="
            background:#ef4444;color:white;
            border-radius:50%;min-width:18px;height:18px;
            font-size:10px;font-weight:700;
            display:inline-flex;align-items:center;justify-content:center;
            margin-left:auto;padding:0 4px;
            line-height:1;
        ">
                        <?php echo $total_pending > 99 ? '99+' : $total_pending; ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="#" class="menu-item" data-target="modifications">
                <i data-lucide="refresh-ccw"></i>
                <span>Modifications</span>
                <?php if ($count_pending > 0): ?>
                    <span style="
            background:#ef4444;color:white;
            border-radius:50%;min-width:18px;height:18px;
            font-size:10px;font-weight:700;
            display:inline-flex;align-items:center;justify-content:center;
            margin-left:auto;padding:0 4px;
            line-height:1;
        ">
                        <?php echo $count_pending > 99 ? '99+' : $count_pending; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="#" class="menu-item" data-target="gestion_users"><i data-lucide="shield-user"></i><span>Gestion
                    Utilisateurs</span></a>
            <a href="#" class="menu-item" data-target="config"><i data-lucide="bolt"></i><span>Configuration</span></a>
        <?php endif; ?>
        <hr>
        <a href="#" class="menu-item" data-target="parametres"><i data-lucide="settings"></i><span>Paramètres</span></a>
        <a href="<?php echo wp_logout_url(home_url('/connexion')); ?>" class="menu-item">
            <i data-lucide="log-out"></i>
            <span>Déconnexion</span>
        </a>
    </aside>

    <!-- ============================================================
     OVERLAY + MODALE DEMANDÉE
     ============================================================ -->
    <div id="overlay"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999;">
    </div>

    <div class="demande-container" id="modale-demande"
        style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); z-index:1000; background:#fff; border-radius:16px; padding:30px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.2);">

        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-weight:700;">Déposer</h3>
            <i data-lucide="x" class="close-icon" id="btn-close-modal"
                style="cursor:pointer; width:22px; height:22px;"></i>
        </div>

        <form id="form-conge" enctype="multipart/form-data">
            <div class="form-body">
                <div class="form-group full-width mb-3">
                    <label>Type de congés</label>
                    <select name="type_conge" id="type_conge" class="form-control">
                        <?php if (!empty($settings['types_enabled'])):
                            foreach ($settings['types_enabled'] as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                            <?php endforeach;
                        endif; ?>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="form-group col-md-6">
                        <label id="label-date-debut">Date de début</label>
                        <input type="date" name="date_debut" id="date_debut" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6" id="container-date-fin">
                        <label>Date de fin</label>
                        <input type="date" name="date_fin" id="date_fin" class="form-control" required>
                    </div>
                </div>

                <?php if (!empty($settings['allow_half_days'])): ?>
                    <div class="form-group full-width mb-3">
                        <label>Moment de la journée</label>
                        <select name="moment_journee" id="moment_journee" class="form-control">
                            <option value="full">Journée(s) entière(s)</option>
                            <option value="matin">Matin uniquement (0.5)</option>
                            <option value="apres-midi">Après-midi uniquement (0.5)</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group full-width mb-3">
                    <label>Justificatif (PDF, JPG, PNG)</label>
                    <input type="file" name="justificatif" class="form-control">
                </div>

                <div class="form-group full-width mb-3">
                    <label>Commentaire (optionnel)</label>
                    <textarea name="motif" rows="2" class="form-control"></textarea>
                </div>
            </div>

            <div class="form-footer mt-3" style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn-cancel">Annuler</button>
                <button type="submit" class="btn-submit">Envoyer la demande</button>
            </div>
        </form>
    </div>

    <main class="container">

        <!-- -- -- -- -- -- TABLEAU DE BORD -- -- -- -- --  -->
        <section id="dashboard" class="tab-content active">
            <div class="global">
                <h1>Tableau De Bord</h1>
                <div class="profile">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Profile">
                    <span><?php echo esc_html($current_user->display_name); ?></span>
                </div>
            </div>
            <div class="dashboard-content">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="card-header"><span>CP restants</span><i data-lucide="briefcase"></i></div>
                        <div class="card-body"><strong><?php echo $solde_cp; ?></strong> <small>Jours</small></div>
                    </div>
                    <div class="stat-card">
                        <div class="card-header"><span>RTT restants</span><i data-lucide="navigation"></i></div>
                        <div class="card-body"><strong><?php echo $solde_rtt; ?></strong> <small>Jours</small></div>
                    </div>
                    <div class="stat-card">
                        <div class="card-header"><span>Récupérations</span><i data-lucide="plus-circle"></i></div>
                        <div class="card-body"><strong><?php echo $solde_recup; ?></strong> <small>Jours</small></div>
                    </div>
                    <div class="stat-card">
                        <div class="card-header"><span>Arrêt maladie</span><i data-lucide="syringe"></i></div>
                        <div class="card-body"><strong><?php echo $solde_maladie; ?></strong> <small>Jours</small></div>
                    </div>
                </div>
                <div class="proabsence">
                    <div class="proabsence-header">
                        <h6>Mes prochaines absences</h6>
                        <i data-lucide="tickets-plane"></i>
                    </div>
                    <ul>
                        <?php if (!empty($prochaines_absences)):
                            foreach ($prochaines_absences as $abs): ?>
                                <li>
                                    <i data-lucide="calendar"></i>
                                    <strong><?php echo esc_html($abs->type_conge); ?></strong> -
                                    <?php echo date('d/m/Y', strtotime($abs->date_debut)); ?> au
                                    <?php echo date('d/m/Y', strtotime($abs->date_fin)); ?>
                                </li>
                            <?php endforeach;
                        else: ?>
                            <li class="text-muted">Aucune absence prévu.</li>
                        <?php endif; ?>
                    </ul>
                    <button class="details"
                        onclick="document.querySelector('[data-target=\'mes_demandes\']').click();">Voir plus de détails
                        ...</button>
                </div>
            </div>
            <div class="apropos">
                <h6>A propos</h6>
                <p>Bienvenue sur votre espace DayOff. Ici vous pouvez gérer vos demandes de congés et suivre vos soldes
                    en temps réels.</p>
            </div>
        </section>

        <!-- -- -- -- -- -- CALENDRIER -- -- -- -- --  -->
        <section id="calendrier" class="tab-content">
            <div class="global">
                <div>
                    <h1>Mon calendrier</h1>
                    <p class="text-muted">Visualiez vos congés et celles de vos équipes.</p>
                </div>
                <div class="profile">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Profile">
                    <span><?php echo esc_html($current_user->display_name); ?></span>
                </div>
            </div>
            <div id="calendar-container"
                style="background:white; padding:20px; border-radius:12px; border:1px solid var(--bordure);">
                <div id="calendar"></div>
            </div>
            <div class="mt-4 d-flex gap-4">
                <div class="d-flex align-items-center gap-2"><span
                        style="width:12px;height:12px;background:#3f81ea;border-radius:3px;display:inline-block;"></span><small>En
                        attente</small></div>
                <div class="d-flex align-items-center gap-2"><span
                        style="width:12px;height:12px;background:#10b981;border-radius:3px;display:inline-block;"></span><small>Validé</small>
                </div>
                <div class="d-flex align-items-center gap-2"><span
                        style="width:12px;height:12px;background:#ef4444;border-radius:3px;display:inline-block;"></span><small>Refusé</small>
                </div>
            </div>
        </section>

        <!-- -- -- -- -- -- MES DEMANDES -- -- -- -- --  -->
        <section id="mes_demandes" class="tab-content">
            <div class="global d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 fw-bold">Mes demandes</h1>
                <div class="profile d-flex align-items-center bg-light p-2 rounded-pill">
                    <img src="<?php echo esc_url($avatar_url); ?>" alt="Profile">
                    <span class="fw-semibold me-2"><?php echo esc_html($current_user->display_name); ?></span>
                </div>
            </div>

            <!-- ===== TABS NAVIGATION ===== -->
            <div style="display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid #f3f4f6;padding-bottom:0;">
                <button id="tab-btn-demandes" onclick="switchMesDemandesTab('demandes')" style="
                padding:10px 20px;border:none;background:none;cursor:pointer;
                font-size:14px;font-weight:600;color:#6b7280;
                border-bottom:2px solid transparent;margin-bottom:-2px;
                transition:all .2s;
            ">
                    <i data-lucide="list" style="width:15px;margin-right:6px;vertical-align:middle;"></i>
                    Mes demandes
                    <span
                        style="background:#f3f4f6;color:#374151;border-radius:20px;padding:2px 8px;font-size:11px;margin-left:6px;">
                        <?php echo count($mes_demandes); ?>
                    </span>
                </button>
                <button id="tab-btn-historique" onclick="switchMesDemandesTab('historique')" style="
                padding:10px 20px;border:none;background:none;cursor:pointer;
                font-size:14px;font-weight:600;color:#6b7280;
                border-bottom:2px solid transparent;margin-bottom:-2px;
                transition:all .2s;
            ">
                    <i data-lucide="history" style="width:15px;margin-right:6px;vertical-align:middle;"></i>
                    Historique des modifications
                    <span
                        style="background:#f3f4f6;color:#374151;border-radius:20px;padding:2px 8px;font-size:11px;margin-left:6px;">
                        <?php echo count($mon_historique_modifs); ?>
                    </span>
                </button>
            </div>

            <!-- ===== PANEL 1 : MES DEMANDES ===== -->
            <div id="panel-demandes">

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:15%;">Type</th>
                                <th style="width:30%;">Période</th>
                                <th style="width:20%;">Statut</th>
                                <th style="width:25%;">Commentaire</th>
                                <th style="width:10%;">Actions</th>
                            </tr>
                            <tr class="bg-white">
                                <th>
                                    <select id="filterType" class="form-select form-select-sm">
                                        <option value="">Tous</option>
                                        <option value="CP">CP</option>
                                        <option value="RTT">RTT</option>
                                        <option value="Maladie">Maladie</option>
                                    </select>
                                </th>
                                <th style="min-width:220px;">
                                    <div class="d-flex gap-1 align-items-center">
                                        <input type="date" id="filterDateDebut" class="form-control form-control-sm">
                                        <span class="small text-muted">au</span>
                                        <input type="date" id="filterDateFin" class="form-control form-control-sm">
                                    </div>
                                </th>
                                <th>
                                    <select id="filterStatut" class="form-select form-select-sm">
                                        <option value="">Tous</option>
                                        <option value="Validé">Validé</option>
                                        <option value="En attente">En attente</option>
                                        <option value="Refusé">Refusé</option>
                                    </select>
                                </th>
                                <th></th>
                                <th class="text-end">
                                    <button id="btn-reset-filters" class="btn-submit"
                                        style="padding:5px 10px;font-size:12px;background:var(--texte-noir);width:auto;">
                                        <i data-lucide="rotate-ccw" style="width:14px;margin-right:5px;"></i> Reset
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php
                            $mes_modifs_en_attente = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}conges_modifications 
                         WHERE user_id = %d AND statut = 'En attente'
                         ORDER BY created_at DESC",
                                $user_id
                            ));
                            $modifs_index = [];
                            foreach ($mes_modifs_en_attente as $mod) {
                                $modifs_index[$mod->demande_id] = $mod;
                            }

                            $mes_modifs_traitees = $wpdb->get_results($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}conges_modifications 
                         WHERE user_id = %d AND statut != 'En attente'
                         ORDER BY created_at DESC",
                                $user_id
                            ));
                            $modifs_traitees_index = [];
                            foreach ($mes_modifs_traitees as $mod) {
                                if (!isset($modifs_traitees_index[$mod->demande_id])) {
                                    $modifs_traitees_index[$mod->demande_id] = $mod;
                                }
                            }



                            if (!empty($mes_demandes)):
                                foreach ($mes_demandes as $demande):
                                    $d_deb = date('d/m/Y', strtotime($demande->date_debut));
                                    $d_fin = date('d/m/Y', strtotime($demande->date_fin));
                                    $statut_colors = [
                                        'Validé' => ['bg' => '#d1fae5', 'color' => '#065f46', 'border' => '#10b981'],
                                        'En attente' => ['bg' => '#fef3c7', 'color' => '#92400e', 'border' => '#f59e0b'],
                                        'Refusé' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'border' => '#ef4444'],
                                    ];
                                    $s = $statut_colors[$demande->statut] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'border' => '#9ca3af'];
                                    $modif_en_cours = $modifs_index[$demande->id] ?? null;
                                    ?>
                                    <tr data-type="<?php echo esc_attr($demande->type_conge); ?>"
                                        data-statut="<?php echo esc_attr($demande->statut); ?>"
                                        data-debut="<?php echo esc_attr($demande->date_debut); ?>"
                                        data-fin="<?php echo esc_attr($demande->date_fin); ?>">

                                        <td><span style="font-weight:600;"><?php echo esc_html($demande->type_conge); ?></span>
                                        </td>

                                        <td>
                                            <?php $moment = $demande->moment_journee ?? 'full'; ?>
                                            <div style="font-weight:500;">
                                                <?php echo $moment !== 'full' ? "Le $d_deb" : "Du $d_deb au $d_fin"; ?>
                                            </div>
                                            <div style="margin-top:5px;">
                                                <?php if ($moment === 'matin'): ?>
                                                    <span
                                                        style="background:#e3f2fd;color:#1976d2;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:bold;border:1px solid #1976d2;">MATIN</span>
                                                <?php elseif ($moment === 'apres-midi'): ?>
                                                    <span
                                                        style="background:#fff3e0;color:#ef6c00;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:bold;border:1px solid #ef6c00;">APRÈS-MIDI</span>
                                                <?php else: ?>
                                                    <span
                                                        style="background:#f5f5f5;color:#616161;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:bold;border:1px solid #bdbdbd;">
                                                        <?php echo gcp_get_duree_formattee($demande); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <span
                                                    style="background:<?php echo $s['bg']; ?>;color:<?php echo $s['color']; ?>;border:1px solid <?php echo $s['border']; ?>;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;display:inline-block;width:fit-content;">
                                                    <?php echo esc_html($demande->statut); ?>
                                                </span>
                                                <?php if ($modif_en_cours): ?>
                                                    <span
                                                        style="font-size:10px;color:#92400e;background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:2px 8px;display:inline-block;width:fit-content;">
                                                        Modif/Annulation en attente
                                                    </span>
                                                <?php elseif (isset($modifs_traitees_index[$demande->id])): ?>
                                                    <?php
                                                    $mt = $modifs_traitees_index[$demande->id];
                                                    $label_action = $mt->type_action === 'Suppression' ? 'Suppression' : 'Modification';
                                                    ?>
                                                    <?php if ($mt->statut === 'Validé'): ?>
                                                        <span
                                                            style="font-size:10px;color:#065f46;background:#d1fae5;border:1px solid #10b981;border-radius:12px;padding:2px 8px;display:inline-block;width:fit-content;">
                                                            ✓ <?php echo $label_action; ?> acceptée
                                                        </span>
                                                    <?php elseif ($mt->statut === 'Refusé'): ?>
                                                        <span
                                                            style="font-size:10px;color:#991b1b;background:#fee2e2;border:1px solid #ef4444;border-radius:12px;padding:2px 8px;display:inline-block;width:fit-content;">
                                                            ✕ <?php echo $label_action; ?> refusée
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <span style="font-size:13px;color:#6b7280;">
                                                <?php echo !empty($demande->commentaire_admin) ? esc_html($demande->commentaire_admin) : '<em>—</em>'; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($modif_en_cours): ?>
                                                <button class="btn-action btn-voir-modif"
                                                    style="font-size:10px;padding:4px 8px;background:#fef3c7;color:#92400e;border:1px solid #f59e0b;white-space:nowrap;"
                                                    data-modif='<?php echo esc_attr(json_encode([
                                                        "type_action" => $modif_en_cours->type_action,
                                                        "created_at" => date("d/m/Y à H:i", strtotime($modif_en_cours->created_at)),
                                                        "raison" => $modif_en_cours->raison,
                                                        "type_conge" => $demande->type_conge,
                                                        "date_debut" => $d_deb,
                                                        "date_fin" => $d_fin,
                                                        "nouveau_type" => $modif_en_cours->nouveau_type ?? "",
                                                        "nouvelle_date_debut" => !empty($modif_en_cours->nouvelle_date_debut) ? date("d/m/Y", strtotime($modif_en_cours->nouvelle_date_debut)) : "",
                                                        "nouvelle_date_fin" => !empty($modif_en_cours->nouvelle_date_fin) ? date("d/m/Y", strtotime($modif_en_cours->nouvelle_date_fin)) : "",
                                                    ])); ?>'>
                                                    <i data-lucide="clock"
                                                        style="width:12px;margin-right:3px;vertical-align:middle;"></i>
                                                    Modif en attente...
                                                </button>
                                            <?php elseif ($demande->statut === 'En attente'): ?>
                                                <button class="action-icon-btn delete" title="Supprimer"
                                                    onclick="annulerMaDemande(<?php echo $demande->id; ?>)">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            <?php elseif ($demande->statut === 'Validé'): ?>
                                                <button class="btn-action" style="font-size:10px;padding:4px 8px;"
                                                    onclick="demanderModification(<?php echo $demande->id; ?>)">
                                                    <i data-lucide="refresh-cw" style="width:12px;margin-right:4px;"></i>
                                                    Modif/Annuler
                                                </button>
                                            <?php elseif ($demande->statut === 'Refusé'): ?>
                                                <button class="btn-action btn-refaire-demande"
                                                    style="font-size:10px;padding:4px 8px;background:#eff6ff;color:#1d4ed8;border:1px solid #3b82f6;white-space:nowrap;"
                                                    data-type="<?php echo esc_attr($demande->type_conge); ?>"
                                                    data-debut="<?php echo esc_attr($demande->date_debut); ?>"
                                                    data-fin="<?php echo esc_attr($demande->date_fin); ?>">
                                                    <i data-lucide="rotate-ccw"
                                                        style="width:12px;margin-right:3px;vertical-align:middle;"></i>
                                                    Refaire
                                                </button>
                                            <?php else: ?>
                                                <span style="color:#d1d5db;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach;
                            else: ?>
                                <tr>
                                    <td colspan="5" class="text-center" style="padding:40px;color:#9ca3af;">
                                        Aucune demande enregistrée pour le moment ...
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>



            </div><!-- fin panel-demandes -->

            <!-- ===== PANEL 2 : HISTORIQUE DES MODIFICATIONS ===== -->
            <div id="panel-historique" style="display:none;">

                <!-- Filtres -->
                <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
                    <select id="filtre-hm-action" onchange="filtrerHistoriqueModifs()"
                        style="height:36px;padding:0 12px;border:1px solid #e5e7eb;border-radius:8px;background:white;font-size:13px;font-family:inherit;color:#374151;outline:none;">
                        <option value="">Toutes les actions</option>
                        <option value="Modification">Modifications</option>
                        <option value="Suppression">Suppressions</option>
                    </select>

                    <select id="filtre-hm-statut" onchange="filtrerHistoriqueModifs()"
                        style="height:36px;padding:0 12px;border:1px solid #e5e7eb;border-radius:8px;background:white;font-size:13px;font-family:inherit;color:#374151;outline:none;">
                        <option value="">Tous les statuts</option>
                        <option value="En attente">En attente</option>
                        <option value="Validé">Validé</option>
                        <option value="Refusé">Refusé</option>
                    </select>

                    <button onclick="filtrerHistoriqueModifs('reset')"
                        style="height:36px;padding:0 12px;border:1px solid #e5e7eb;border-radius:8px;background:white;color:#374151;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px;"></i>
                        Reset
                    </button>

                    <span id="filtre-hm-count" style="font-size:12px;color:#9ca3af;margin-left:auto;">
                                    <?php echo count($mon_historique_modifs); ?>
                        entrée<?php echo count($mon_historique_modifs) > 1 ? 's' : ''; ?>
                    </span>
                </div>

                <?php if (!empty($mon_historique_modifs)): ?>

                        <div style="display:flex;flex-direction:column;gap:12px;" id="liste-historique-modifs">
                            <?php foreach ($mon_historique_modifs as $hm):
                                $is_suppr = ($hm->type_action === 'Suppression');
                                $is_valide = ($hm->statut === 'Validé');
                                $is_attente = ($hm->statut === 'En attente');

                                if ($is_attente) {
                                    $sbg = '#fef3c7';
                                    $scol = '#92400e';
                                    $sborder = '#f59e0b';
                                } elseif ($is_valide) {
                                    $sbg = '#d1fae5';
                                    $scol = '#065f46';
                                    $sborder = '#10b981';
                                } else {
                                    $sbg = '#fee2e2';
                                    $scol = '#991b1b';
                                    $sborder = '#ef4444';
                                }
                                ?>
                                    <div class="hm-card" data-action="<?php echo esc_attr($hm->type_action); ?>"
                                        data-statut="<?php echo esc_attr($hm->statut); ?>"
                                        style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;display:flex;gap:20px;align-items:flex-start;">

                                        <!-- Icône action -->
                                        <div
                                            style="width:36px;height:36px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
                    <?php echo $is_suppr ? 'background:#fee2e2;color:#b91c1c;' : 'background:#fef3c7;color:#92400e;'; ?>">
                                            <?php if ($is_suppr): ?>
                                                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                                            <?php else: ?>
                                                    <i data-lucide="edit-3" style="width:16px;height:16px;"></i>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Contenu -->
                                        <div style="flex:1;">
                                            <div
                                                style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                                                <div>
                                                    <span style="font-size:13px;font-weight:700;color:#1f2937;">
                                                        <?php echo $is_suppr ? 'Demande d\'annulation' : 'Demande de modification'; ?>
                                                    </span>
                                                    <div style="font-size:11px;color:#9ca3af;margin-top:2px;">
                                                        Soumis le <?php echo date('d/m/Y à H:i', strtotime($hm->created_at)); ?>
                                                    </div>
                                                </div>
                                                <span
                                                    style="font-size:11px;font-weight:600;padding:3px 12px;border-radius:20px;background:<?php echo $sbg; ?>;color:<?php echo $scol; ?>;border:1px solid <?php echo $sborder; ?>;">
                                                    <?php echo esc_html($hm->statut); ?>
                                                </span>
                                            </div>

                                            <!-- Avant / Après -->
                                            <div
                                                style="display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;background:#f9fafb;border-radius:8px;padding:10px 14px;margin-bottom:10px;">
                                                <div>
                                                    <div
                                                        style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">
                                                        Congé concerné</div>
                                                    <div
                                                        style="font-size:12px;color:#dc2626;text-decoration:line-through;font-weight:500;">
                                                        <?php echo esc_html($hm->type_conge); ?> ·
                                                        <?php echo date('d/m', strtotime($hm->date_debut)); ?> →
                                                        <?php echo date('d/m', strtotime($hm->date_fin)); ?>
                                                    </div>
                                                </div>
                                                <div style="font-size:18px;color:#d1d5db;">→</div>
                                                <div>
                                                    <div
                                                        style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;margin-bottom:3px;">
                                                        <?php echo $is_suppr ? 'Résultat' : 'Nouvelles dates'; ?>
                                                    </div>
                                                    <div style="font-size:12px;color:#059669;font-weight:500;">
                                                        <?php if ($is_suppr): ?>
                                                                Suppression du congé
                                                        <?php else: ?>
                                                                <?php echo !empty($hm->nouveau_type) ? esc_html($hm->nouveau_type) : esc_html($hm->type_conge); ?>
                                                                ·
                                                                <?php echo !empty($hm->nouvelle_date_debut) ? date('d/m', strtotime($hm->nouvelle_date_debut)) : '—'; ?>
                                                                →
                                                                <?php echo !empty($hm->nouvelle_date_fin) ? date('d/m', strtotime($hm->nouvelle_date_fin)) : '—'; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Motif -->
                                            <?php if (!empty($hm->raison)): ?>
                                                    <div
                                                        style="font-style:italic;font-size:12px;color:#6b7280;border-left:3px solid #e5e7eb;padding-left:10px;">
                                                        "<?php echo esc_html($hm->raison); ?>"
                                                    </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                            <?php endforeach; ?>
                        </div>

                <?php else: ?>
                        <div style="text-align:center;padding:60px 20px;color:#9ca3af;">
                            <i data-lucide="inbox"
                                style="width:40px;height:40px;margin-bottom:12px;display:block;margin-inline:auto;"></i>
                            <p style="font-size:14px;">Aucune demande de modification pour le moment.</p>
                        </div>
                <?php endif; ?>

            </div><!-- fin panel-historique -->

        </section>


        <!-- ===== MODALE TRAÇABILITÉ MODIFICATION ===== -->
        <div id="modal-trace-modif" class="modal-overlay" style="display:none;">
            <div class="modal-confirm-content" style="max-width:480px;text-align:left;">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="fw-bold mb-0" id="trace-title">Demande en cours</h3>
                    <i data-lucide="x" class="close-icon"
                        onclick="document.getElementById('modal-trace-modif').style.display='none'"
                        style="cursor:pointer;"></i>
                </div>

                <!-- Badge statut + label -->
                <div id="trace-badge-zone" class="mb-3 d-flex align-items-center gap-2"></div>

                <!-- Bloc avant / après -->
                <div
                    style="display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:center;background:#f9fafb;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                    <div>
                        <div
                            style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">
                            Congé actuel</div>
                        <div id="trace-avant"
                            style="font-size:13px;color:#dc2626;text-decoration:line-through;font-weight:500;"></div>
                    </div>
                    <div style="font-size:20px;color:#d1d5db;line-height:1;">→</div>
                    <div>
                        <div id="trace-apres-label"
                            style="font-size:10px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">
                        </div>
                        <div id="trace-apres" style="font-size:13px;font-weight:500;"></div>
                    </div>
                </div>

                <!-- Motif -->
                <div
                    style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:14px;">
                    <div
                        style="font-size:10px;font-weight:600;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                        Motif invoqué
                    </div>
                    <div id="trace-raison" style="font-style:italic;font-size:13px;color:#78350f;line-height:1.5;">
                    </div>
                </div>

                <!-- Date soumission -->
                <div style="font-size:12px;color:#9ca3af;text-align:right;margin-bottom:20px;">
                    Soumis le <span id="trace-date" style="font-weight:500;color:#6b7280;"></span>
                </div>

                <div class="text-center">
                    <button class="btn-cancel"
                        onclick="document.getElementById('modal-trace-modif').style.display='none'">
                        Fermer
                    </button>
                </div>
            </div>
        </div>

        <!-- -- -- -- -- -- GESTION DES UTILISATEURS -- -- -- -- --  -->

        <?php if (current_user_can('manage_options')): ?>
                <section id="gestion_users" class="tab-content">
                    <div class="admin-grid">
                        <div class="admin-main">
                            <div class="header-flex d-flex justify-content-between align-items-center mb-3">
                                <h1>Gestion de l'Équipe</h1>
                                <div class="d-flex gap-2">
                                    <button class="btn-submit" id="btn-open-add-user"
                                        style="width:auto;padding:8px 20px;font-size:13px;"><i data-lucide="user-plus"></i>
                                        Nouveau Collaborateur</button>
                                </div>
                            </div>
                            <div class="search-box mb-3">
                                <input type="text" id="user-search" class="form-control"
                                    placeholder="Rechercher un collaborateur ... ">
                            </div>
                            <div class="user-list-container">
                                <?php
                                $users = get_users(array('orderby' => 'display_name'));
                                if (!empty($users)):
                                    foreach ($users as $user):
                                        $cp = get_user_meta($user->ID, 'gcp_solde_cp', true) ?: 0;
                                        $rtt = get_user_meta($user->ID, 'gcp_solde_rtt', true) ?: 0;
                                        ?>
                                                <div class="user-row-item" data-id="<?php echo $user->ID; ?>"
                                                    data-name="<?php echo strtolower(esc_attr($user->display_name)); ?>">
                                                    <div class="user-main-info">
                                                        <div class="user-avatar-circle">
                                                            <?php echo strtoupper(substr($user->display_name, 0, 1)); ?>
                                                        </div>
                                                        <div class="user-text-details">
                                                            <span class="user-name-label"><?php echo esc_html($user->display_name); ?></span>
                                                            <span class="user-email-sub"><?php echo esc_html($user->user_email); ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="user-stats-mini">
                                                        <span class="mini-badge-cp"><?php echo $cp; ?> CP</span>
                                                        <span class="mini-badge-rtt"><?php echo $rtt; ?> RTT</span>
                                                    </div>
                                                    <div class="user-row-actions">
                                                        <button class="action-icon-btn edit btn-edit-user" title="Modifier"
                                                            data-user='<?php echo json_encode(["id" => $user->ID, "name" => $user->display_name, "email" => $user->user_email, "cp" => $cp, "rtt" => $rtt]); ?>'>
                                                            <i data-lucide="edit-2"></i>
                                                        </button>
                                                        <button class="action-icon-btn delete" title="Supprimer"
                                                            onclick="supprimerCollaborateur(<?php echo $user->ID; ?>, '<?php echo esc_js($user->display_name); ?>')">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                        <?php endforeach;
                                else: ?>
                                        <p class="p-4 text-center">Aucun collaborateur trouvé.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="admin-sidebar" id="edit-panel">
                            <div class="sidebar-placeholder"><i data-lucide="user-cog"></i>
                                <p>Sélectionnez un collaborateur ou créez en un nouveau</p>
                            </div>
                            <div class="sidebar-content" style="display:none;">
                                <div class="sidebar-header-flex"
                                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                                    <h2 id="panel-title" style="margin:0; font-weight:700; font-size: 1.5rem;">Modifier</h2>
                                    <i data-lucide="x" class="close-icon" id="btn-close-edit-panel"
                                        style="cursor:pointer; width:22px; height:22px;"></i>
                                </div>

                                <form id="form-admin-user-global">
                                    <input type="hidden" name="target_user_id" id="edit-user-id">
                                    <input type="hidden" id="form-mode" value="update">
                                    <div class="form-group mb-2"><label>Nom d'affichage</label><input type="text"
                                            name="display_name" id="edit-display-name" required></div>
                                    <div class="form-group mb-2"><label>Email (Identifiant)</label><input type="email"
                                            name="user_email" id="edit-email" required></div>
                                    <div id="password-field-container" class="form-group mb-2" style="display:none;"><label>Mot
                                            de passe provisoire</label><input type="password" name="password"
                                            id="edit-password"></div>
                                    <div class="row-inputs d-flex gap-2">
                                        <div class="form-group flex-grow-1"><label>Solde CP</label><input type="number"
                                                step="0.5" name="new_cp" id="edit-cp" value="0"></div>
                                        <div class="form-group flex-grow-1"><label>Solde RTT</label><input type="number"
                                                step="0.5" name="new_rtt" id="edit-rtt" value="0"></div>
                                    </div>
                                    <button type="submit" class="btn-submit mt-3">Enregistrer</button>
                                    <button type="button" id="btn-export-single-user" class="btn-action mt-2 w-100"
                                        style="height: 40px; justify-content: center; gap: 8px; border: 1px solid var(--bordure); ">
                                        <i data-lucide="file-text" style="width: 16px;"></i>
                                        <span>Exporter ce collaborateur (CSV)</span>
                                    </button>
                                </form>
                                <hr class="my-4">
                                <div class="user-history-section">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h3 class="h6 fw-bold mb-0"><i data-lucide="history" class="me-2"></i>Historique</h3>
                                        <button type="button" id="btn-reset-history"
                                            class="text-danger border-0 bg-transparent p-0 small fw-bold"
                                            style="font-size: 11px; cursor: pointer; display: none;">
                                            <i data-lucide="trash-2" style="width: 12px; vertical-align: middle;"></i>
                                            Réinitialiser
                                        </button>
                                    </div>

                                    <div id="history-loader" style="display:none;" class="text-center p-3">
                                        <div class="spinner-border spinner-border-sm text-primary"></div>
                                    </div>
                                    <ul id="user-leave-list" class="list-group list-group-flush small"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
        <?php endif; ?>

        <!-- -- -- -- -- -- CONFIGURATION DU PLUGIN -- -- -- -- --  -->

        <section id="config" class="tab-content">
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div>
                    <h1 class="fw-bold mb-1">Configuration du plugin</h1>
                    <p class="text-muted small mb-0">Paramètres globaux de l'instance DayOff.</p>
                </div>
                <button type="submit" form="form-config-global" class="btn-submit"
                    style="width:auto;padding:10px 25px;">Enregistrer les modifications</button>
            </div>
            <form id="form-config-global">
                <div class="config-section mb-5">
                    <h3 class="config-title">Informations sur l'entreprise</h3>
                    <div class="config-card p-4">
                        <div class="row g-4">
                            <div class="col-md-6"><label
                                    class="small fw-bold text-uppercase text-muted d-block mb-2">Nom de
                                    l'organisation</label><input type="text" name="company_name"
                                    class="form-input-clean"
                                    value="<?php echo esc_attr($settings['company_name'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label
                                    class="small fw-bold text-uppercase text-muted d-block mb-2">Email de notification
                                    RH</label><input type="email" name="admin_email_notify" class="form-input-clean"
                                    value="<?php echo esc_attr($settings['admin_email_notify'] ?? get_option('admin_email')); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="config-section mb-5">
                    <h3 class="config-title">Paramètres de temps & saisie</h3>
                    <div class="config-card">
                        <div class="setting-row">
                            <div class="setting-info"><span class="setting-label">Heures travaillées / jours</span><span
                                    class="setting-desc">Utilisé pour convertir les heures en jours (Défaut: 7h).</span>
                            </div>
                            <div class="setting-action"><input type="number" name="work_hours_per_day" step="0.5"
                                    class="form-input-clean text-end" style="width:80px;"
                                    value="<?php echo $settings['work_hours_per_day'] ?? 7; ?>"></div>
                        </div>
                        <div class="setting-row">
                            <div class="setting-info"><span class="setting-label">Gestion des demi-journées</span><span
                                    class="setting-desc">Permettre aux collaborateurs de poser des matinées ou des
                                    après-midi.</span></div>
                            <div class="setting-action">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="allow_half_days" value="0">
                                    <input class="form-check-input" type="checkbox" name="allow_half_days" value="1"
                                        <?php checked($settings['allow_half_days'] ?? 0, 1); ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="config-section mb-5">
                    <h3 class="config-title">Acquisition & Soldes</h3>
                    <div class="config-card p-4">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-4"><label
                                    class="small fw-bold text-uppercase text-muted d-block mb-2">Méthode de
                                    calculs</label>
                                <select name="acquisition_mode" class="form-select-clean">
                                    <option value="monthly" <?php selected($settings['acquisition_mode'], 'monthly'); ?>>Mensuel (Pro-Rata)</option>
                                    <option value="yearly" <?php selected($settings['acquisition_mode'], 'yearly'); ?>>
                                        Annuel (Crédit fixe)</option>
                                </select>
                            </div>
                            <div class="col-md-4"><label
                                    class="small fw-bold text-uppercase text-muted d-block mb-2">Crédit CP
                                    /mois</label><input type="number" name="cp_rate" step="0.01"
                                    class="form-input-clean" value="<?php echo $settings['cp_rate']; ?>"></div>
                            <div class="col-md-4"><label
                                    class="small fw-bold text-uppercase text-muted d-block mb-2">Crédit RTT
                                    /mois</label><input type="number" name="rtt_rate" step="0.01"
                                    class="form-input-clean" value="<?php echo $settings['rtt_rate'] ?? 0; ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="config-section">
                    <h3 class="config-title">Motifs d'absence autorisés</h3>
                    <div class="config-card p-4">
                        <div class="d-flex flex-wrap gap-2">
                            <?php $available_types = ['CP' => 'Congés Payés', 'RTT' => 'RTT', 'Maladie' => 'Maladie', 'Sans Solde' => 'Sans Solde', 'Evenement' => 'Évènements'];
                            foreach ($available_types as $key => $label): ?>
                                    <label class="chip-select">
                                        <input type="checkbox" name="types_enabled[]" value="<?php echo $key; ?>" <?php checked(in_array($key, $settings['types_enabled']), true); ?>>
                                        <span class="chip-label"><?php echo $label; ?></span>
                                    </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <!-- -- -- -- -- -- Paramètres -- -- -- -- -- -->

        <section id="parametres" class="tab-content">
            <div class="global">
                <div>
                    <h1>Paramètres</h1>
                    <p class="text-muted">Gérez vos informations personnelles et votre sécurité</p>
                </div>
            </div>
            <div class="settings-container" style="display:grid;grid-template-columns:1fr 1fr;gap:30px;">
                <div class="rules-container">
                    <div class="rules-header"><i data-lucide="user"></i>
                        <h2>Mon Profil</h2>
                    </div>
                    <form id="form-update-profile">
                        <div class="form-group mb-3"><label>Nom d'affichage</label><input type="text"
                                name="display_name" value="<?php echo esc_attr($current_user->display_name); ?>"
                                class="form-control"></div>
                        <div class="form-group mb-3"><label>Email</label><input type="email" name="user_email"
                                value="<?php echo esc_attr($current_user->user_email); ?>" class="form-control"></div>
                        <button type="submit" class="btn-submit" style="width:auto;padding:10px 25px;">Mettre à jour le
                            profil</button>
                    </form>
                </div>
                <div class="rules-container">
                    <div class="rules-header"><i data-lucide="lock"></i>
                        <h2>Sécurité</h2>
                    </div>
                    <form id="form-update-password">
                        <div class="form-group mb-3"><label>Nouveau mot de passe</label><input type="password"
                                name="new_password" id="new_password" class="form-control"
                                placeholder="Laissez vide pour ne pas changer"></div>
                        <div class="form-group mb-3"><label>Confirmer le nouveau mot de passe</label><input
                                type="password" name="conf_password" id="conf_password" class="form-control"></div>
                        <button type="submit" class="btn-submit"
                            style="width:auto;padding:10px 25px;background:var(--texte-noir);">Modifier ce mot de
                            passe</button>
                    </form>
                </div>
            </div>
        </section>

        <!-- -- -- -- -- -- VALIDATIONS -- -- -- -- --  -->

        <section id="validation_conges" class="tab-content">
            <?php
            // Récupération des compteurs simples
            $total_pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_conges WHERE statut = 'En attente'");
            $all_pending = $wpdb->get_results("SELECT d.*, u.display_name FROM $table_conges d JOIN {$wpdb->users} u ON d.user_id = u.ID WHERE d.statut = 'En attente' ORDER BY d.created_at ASC");
            ?>

            <div class="global">
                <h1>Centre de validations</h1>
                <div class="d-flex gap-2">
                    <button id="btn-export-all" class="btn-action"
                        style="padding: 0 15px; height: 40px; display: flex; align-items: center; gap: 8px; border: 1px solid var(--bordure);">
                        <i data-lucide="download" style="width: 16px;"></i>
                        <span>Exporter CSV</span>
                    </button>
                    <button id="btn-refresh-validations" class="btn-refresh-small"><i
                            data-lucide="refresh-cw"></i></button>
                </div>
            </div>

            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="card-header"><span>Demandes en attente</span><i data-lucide="clock"></i></div>
                    <div class="card-body"><strong><?php echo $total_pending; ?></strong> <small>Dossiers</small></div>
                </div>
                <div class="rules-container mb-4" style="padding: 15px;">
                    <div class="d-flex flex-wrap gap-3 align-items-end">
                        <div style="flex: 2; min-width: 200px;">
                            <label class="small fw-bold text-muted mb-1 d-block text-uppercase"
                                style="font-size: 10px;">Rechercher un collaborateur</label>
                            <div class="search-container">
                                <i data-lucide="search" class="search-icon"></i>
                                <input type="text" id="adminFilterName" class="search-input"
                                    placeholder="Nom ou prénom...">
                            </div>
                        </div>

                        <div style="flex: 1; min-width: 120px;">
                            <label class="small fw-bold text-muted mb-1 d-block text-uppercase"
                                style="font-size: 10px;">Type</label>
                            <select id="adminFilterType" class="form-select form-select-sm">
                                <option value="">Tous</option>
                                <option value="CP">CP</option>
                                <option value="RTT">RTT</option>
                                <option value="Maladie">Maladie</option>
                            </select>
                        </div>

                        <div style="flex: 1.5; min-width: 200px;">
                            <label class="small fw-bold text-muted mb-1 d-block text-uppercase"
                                style="font-size: 10px;">Période (Début)</label>
                            <input type="date" id="adminFilterDate" class="form-control form-control-sm"
                                style="height: 40px; border-radius: 8px;">
                        </div>

                        <button id="btn-reset-admin-filters" class="btn-cancel" style="height: 40px; padding: 0 15px;">
                            <i data-lucide="rotate-ccw" style="width: 16px;"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="user-list-container">
                <?php if (!empty($all_pending)):
                    foreach ($all_pending as $val): ?>
                                <div class="user-row-item" data-id="<?php echo $val->id; ?>"
                                    data-debut="<?php echo $val->date_debut; ?>" style="grid-template-columns: 1.2fr 1fr 1.5fr 100px;">

                                    <div class="user-main-info">
                                        <div class="user-avatar-circle" style="background:#f3f4f6; color:#374151;">
                                            <?php echo strtoupper(substr($val->display_name, 0, 1)); ?>
                                        </div>
                                        <div class="user-text-details">
                                            <span class="user-name-label"><?php echo esc_html($val->display_name); ?></span>
                                            <span class="user-email-sub"><?php echo esc_html($val->type_conge); ?></span>

                                            <?php if (!empty($val->justificatif)): ?>
                                                    <a href="<?php echo esc_url($val->justificatif); ?>" target="_blank"
                                                        class="badge bg-info text-white mt-1" style="text-decoration:none;">
                                                        <i data-lucide="paperclip" style="width:12px;"></i> Voir la pièce jointe
                                                    </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="user-stats-mini">
                                        <div style="font-size: 13px; font-weight: 600;">
                                            Du <?php echo date('d/m/Y', strtotime($val->date_debut)); ?><br>
                                            au <?php echo date('d/m/Y', strtotime($val->date_fin)); ?>
                                        </div>
                                    </div>

                                    <div style="padding: 0 10px;">
                                        <?php if (!empty($val->motif)): ?>
                                                <div style="font-size: 11px; color: var(--texte-gris); margin-bottom: 4px;">Motif :
                                                    <?php echo esc_html($val->motif); ?>
                                                </div>
                                        <?php endif; ?>
                                        <input type="text" class="comm-admin" placeholder="Réponse (optionnel)..."
                                            style="width:100%; border:1px solid var(--bordure); border-radius:6px; padding:6px; font-size:12px;">
                                    </div>

                                    <div class="user-row-actions">
                                        <button class="action-icon-btn edit" onclick="validerDemande(<?php echo $val->id; ?>, 'Validé')"
                                            title="Valider">
                                            <i data-lucide="check" style="color:var(--success);"></i>
                                        </button>
                                        <button class="action-icon-btn delete"
                                            onclick="validerDemande(<?php echo $val->id; ?>, 'Refusé')" title="Refuser">
                                            <i data-lucide="x" style="color:var(--danger);"></i>
                                        </button>
                                    </div>

                                </div>
                        <?php endforeach;
                else: ?>
                        <p class="p-5 text-center text-muted small">Aucune demande d'absence en attente ...</p>
                <?php endif; ?>
            </div>
        </section>

        <section id="modifications" class="tab-content">
            <div class="global d-flex justify-content-between align-items-center mb-4">
                <h1>Demandes de modifications</h1>
                <button id="btn-refresh-modifications" class="btn-refresh-small">
                    <i data-lucide="refresh-cw"></i>
                </button>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">

                <!-- ===== BLOC GAUCHE : EN ATTENTE ===== -->
                <div class="bg-white rounded-4 border shadow-sm overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <span class="fw-bold" style="font-size:14px;">En attente de traitement</span>
                        <?php
                        $count_pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_modifs WHERE statut = 'En attente'");
                        ?>
                        <span class="badge bg-warning-subtle text-warning border"
                            style="border-color:#f59e0b !important;">
                            <?php echo $count_pending; ?> dossier<?php echo $count_pending > 1 ? 's' : ''; ?>
                        </span>
                    </div>

                    <!-- Filtres -->
                    <div class="p-3 border-bottom" style="background:#fafafa;">
                        <div class="row g-2 align-items-end">
                            <div class="col-7">
                                <div style="position:relative;">
                                    <i data-lucide="search"
                                        style="position:absolute;left:10px;top:11px;width:14px;color:#9ca3af;"></i>
                                    <input type="text" id="filterModifName" class="form-control form-control-sm"
                                        placeholder="Nom du collaborateur..." style="padding-left:30px;">
                                </div>
                            </div>
                            <div class="col-4">
                                <select id="filterModifAction" class="form-select form-select-sm">
                                    <option value="">Toutes</option>
                                    <option value="Suppression">Annulations</option>
                                    <option value="Modification">Modifications</option>
                                </select>
                            </div>
                            <div class="col-1">
                                <button id="btn-reset-modif-filters" class="btn btn-light btn-sm w-100 px-1">
                                    <i data-lucide="rotate-ccw" style="width:13px;"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="container-modifs-list">
                        <?php
                        $modifs = $wpdb->get_results("SELECT m.*, u.display_name, d.type_conge, d.date_debut, d.date_fin 
                    FROM $table_modifs m 
                    JOIN {$wpdb->users} u ON m.user_id = u.ID 
                    JOIN {$wpdb->prefix}conges_demandes d ON m.demande_id = d.id 
                    WHERE m.statut = 'En attente'
                    ORDER BY m.created_at DESC");

                        if (!empty($modifs)):
                            foreach ($modifs as $m): ?>
                                        <div class="modif-row p-3 border-bottom"
                                            data-user="<?php echo strtolower(esc_attr($m->display_name)); ?>"
                                            data-action="<?php echo esc_attr($m->type_action); ?>">

                                            <!-- Ligne du haut : avatar + nom + badge + date -->
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <div class="user-avatar-circle"
                                                    style="background:#fef3c7;color:#92400e;width:34px;height:34px;font-size:13px;flex-shrink:0;">
                                                    <?php echo strtoupper(substr($m->display_name, 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-bold"
                                                        style="font-size:13px;"><?php echo esc_html($m->display_name); ?></span>
                                                    <span
                                                        class="badge ms-1 <?php echo ($m->type_action === 'Suppression' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning'); ?>"
                                                        style="font-size:10px; border:1px solid currentColor;">
                                                        <?php echo ($m->type_action === 'Suppression' ? 'ANNULATION' : 'MODIFICATION'); ?>
                                                    </span>
                                                    <div style="font-size:10px;color:#9ca3af;">
                                                        Demandé le <?php echo date('d/m/Y', strtotime($m->created_at)); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Bloc avant / après -->
                                            <div
                                                style="display:grid;grid-template-columns:1fr auto 1fr;gap:8px;align-items:center;background:#f9fafb;border-radius:8px;padding:8px 10px;margin-bottom:8px;">
                                                <div>
                                                    <div
                                                        style="font-size:10px;font-weight:500;color:#9ca3af;text-transform:uppercase;margin-bottom:2px;">
                                                        Congé actuel</div>
                                                    <div style="font-size:12px;color:#dc2626;text-decoration:line-through;">
                                                        <?php echo $m->type_conge; ?> ·
                                                        <?php echo date('d/m', strtotime($m->date_debut)); ?> →
                                                        <?php echo date('d/m', strtotime($m->date_fin)); ?>
                                                    </div>
                                                </div>
                                                <div style="font-size:14px;color:#9ca3af;">→</div>
                                                <div>
                                                    <?php if ($m->type_action === 'Suppression'): ?>
                                                            <div
                                                                style="font-size:10px;font-weight:500;color:#9ca3af;text-transform:uppercase;margin-bottom:2px;">
                                                                Après annulation</div>
                                                            <div style="font-size:12px;color:#059669;">Supprimé</div>
                                                    <?php else: ?>
                                                            <div
                                                                style="font-size:10px;font-weight:500;color:#9ca3af;text-transform:uppercase;margin-bottom:2px;">
                                                                Nouvelles dates</div>
                                                            <div style="font-size:12px;color:#059669;">
                                                                <?php echo (!empty($m->nouveau_type) ? $m->nouveau_type : $m->type_conge); ?>
                                                                ·
                                                                <?php echo (!empty($m->nouvelle_date_debut) ? date('d/m', strtotime($m->nouvelle_date_debut)) : '—'); ?>
                                                                →
                                                                <?php echo (!empty($m->nouvelle_date_fin) ? date('d/m', strtotime($m->nouvelle_date_fin)) : '—'); ?>
                                                            </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Raison -->
                                            <div style="font-style:italic;font-size:11px;color:#6b7280;margin-bottom:10px;">
                                                "<?php echo esc_html($m->raison); ?>"
                                            </div>

                                            <!-- Boutons -->
                                            <div class="d-flex gap-2 justify-content-end">
                                                <button class="btn btn-sm btn-success"
                                                    onclick="traiterModif(<?php echo $m->id; ?>, 'Validé')">
                                                    <i data-lucide="check" style="width:13px;"></i> Valider
                                                </button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="traiterModif(<?php echo $m->id; ?>, 'Refusé')">
                                                    <i data-lucide="x" style="width:13px;"></i> Refuser
                                                </button>
                                            </div>
                                        </div>
                                <?php endforeach;
                        else: ?>
                                <p class="p-5 text-center text-muted small">Aucune demande en attente.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== BLOC DROIT : HISTORIQUE ===== -->
                <div class="bg-white rounded-4 border shadow-sm overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <span class="fw-bold" style="font-size:14px;">Historique des décisions</span>
                        <?php
                        $count_hist = $wpdb->get_var("SELECT COUNT(*) FROM $table_modifs WHERE statut != 'En attente'");
                        ?>
                        <span style="font-size:11px;color:#9ca3af;"><?php echo $count_hist; ?>
                            entrée<?php echo $count_hist > 1 ? 's' : ''; ?></span>
                    </div>

                    <?php
                    $historique = $wpdb->get_results("SELECT m.*, u.display_name, d.type_conge, d.date_debut, d.date_fin 
                FROM $table_modifs m 
                JOIN {$wpdb->users} u ON m.user_id = u.ID 
                JOIN {$wpdb->prefix}conges_demandes d ON m.demande_id = d.id 
                WHERE m.statut != 'En attente'
                ORDER BY m.created_at DESC
                LIMIT 50");

                    if (!empty($historique)):
                        foreach ($historique as $h):
                            $is_valide = ($h->statut === 'Validé');
                            $badge_class = $is_valide ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                            $badge_border = $is_valide ? '#10b981' : '#ef4444';
                            ?>
                                    <div class="p-3 border-bottom">
                                        <!-- Haut : avatar + nom + date + statut -->
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar-circle"
                                                    style="background:#f3f4f6;color:#374151;width:30px;height:30px;font-size:11px;flex-shrink:0;">
                                                    <?php echo strtoupper(substr($h->display_name, 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold" style="font-size:13px;">
                                                        <?php echo esc_html($h->display_name); ?>
                                                    </div>
                                                    <div style="font-size:10px;color:#9ca3af;">Traité le
                                                        <?php echo date('d/m/Y', strtotime($h->created_at)); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="badge <?php echo $badge_class; ?>"
                                                style="font-size:11px;border:1px solid <?php echo $badge_border; ?>;">
                                                <?php echo esc_html($h->statut); ?>
                                            </span>
                                        </div>

                                        <!-- Bloc avant / après -->
                                        <div
                                            style="display:grid;grid-template-columns:1fr auto 1fr;gap:8px;align-items:center;background:#f9fafb;border-radius:8px;padding:8px 10px;margin-bottom:6px;">
                                            <div>
                                                <div
                                                    style="font-size:10px;font-weight:500;color:#9ca3af;text-transform:uppercase;margin-bottom:2px;">
                                                    Avant</div>
                                                <div style="font-size:11px;color:#dc2626;text-decoration:line-through;">
                                                    <?php echo $h->type_conge; ?> ·
                                                    <?php echo date('d/m', strtotime($h->date_debut)); ?> →
                                                    <?php echo date('d/m', strtotime($h->date_fin)); ?>
                                                </div>
                                            </div>
                                            <div style="font-size:13px;color:#9ca3af;">→</div>
                                            <div>
                                                <?php if ($h->type_action === 'Suppression'): ?>
                                                        <div
                                                            style="font-size:10px;font-weight:500;color:#9ca3af;text-transform:uppercase;margin-bottom:2px;">
                                                            Après</div>
                                                        <div style="font-size:11px;color:<?php echo $is_valide ? '#059669' : '#9ca3af'; ?>;">
                                                            <?php echo $is_valide ? 'Supprimé' : 'Non traité'; ?>
                                                        </div>
                                                <?php else: ?>
                                                        <div
                                                            style="font-size:10px;font-weight:500;color:#9ca3af;text-transform:uppercase;margin-bottom:2px;">
                                                            Demande modif.</div>
                                                        <div style="font-size:11px;color:<?php echo $is_valide ? '#059669' : '#9ca3af'; ?>;">
                                                            <?php echo (!empty($h->nouveau_type) ? $h->nouveau_type : $h->type_conge); ?>
                                                            ·
                                                            <?php echo (!empty($h->nouvelle_date_debut) ? date('d/m', strtotime($h->nouvelle_date_debut)) : '—'); ?>
                                                            →
                                                            <?php echo (!empty($h->nouvelle_date_fin) ? date('d/m', strtotime($h->nouvelle_date_fin)) : '—'); ?>
                                                        </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Raison -->
                                        <div style="font-style:italic;font-size:11px;color:#9ca3af;">
                                            "<?php echo esc_html($h->raison); ?>"
                                        </div>
                                    </div>
                            <?php endforeach;
                    else: ?>
                            <p class="p-5 text-center text-muted small">Aucune décision dans l'historique.</p>
                    <?php endif; ?>
                </div>

            </div>
        </section>

        <div id="modal-choix-modif" class="modal-overlay" style="display:none;">
            <div class="modal-confirm-content" style="max-width:500px; text-align:left;">
                <div class="form-header d-flex justify-content-between">
                    <h3 class="fw-bold">Que souhaitez-vous faire ?</h3>
                    <i data-lucide="x" class="close-icon" onclick="fermerModaleModif()" style="cursor:pointer;"></i>
                </div>

                <p class="text-muted small">Sélectionnez l'action à entreprendre pour ce congé.</p>

                <div class="d-flex gap-3 mb-4">
                    <button class="btn-cancel w-100" onclick="afficherFormModif('Suppression')"
                        style="background:#fee2e2; color:#b91c1c;">
                        <i data-lucide="trash-2" class="me-2"></i>Demander l'annulation
                    </button>
                    <button class="btn-submit w-100" onclick="afficherFormModif('Modification')">
                        <i data-lucide="edit-3" class="me-2"></i>Modifier les dates
                    </button>
                </div>

                <form id="form-request-change" style="display:none; border-top: 1px solid #eee; pt-3">
                    <input type="hidden" name="demande_id" id="request-demande-id">
                    <input type="hidden" name="type_action" id="request-type-action">

                    <div id="zone-modif-dates" style="display:none;" class="mt-3">
                        <div class="form-group mb-3">
                            <label class="small fw-bold">Nouveau Type de congé</label>
                            <select name="nouveau_type" class="form-control">
                                <option value="">Conserver le type actuel</option>
                                <option value="CP">Congés Payés (CP)</option>
                                <option value="RTT">RTT</option>
                                <option value="Maladie">Maladie</option>
                                <option value="Sans Solde">Sans Solde</option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="small fw-bold">Nouveau Début</label>
                                <input type="date" name="nouveau_debut" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold">Nouvelle Fin</label>
                                <input type="date" name="nouvelle_fin" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="small fw-bold">Raison de la demande</label>
                        <textarea name="raison" class="form-control" rows="3"
                            placeholder="Expliquez pourquoi..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit w-100">Envoyer la demande</button>
                </form>
            </div>
        </div>

    </main>

    <!-- -- -- -- -- -- MODALES SYSTEMES -- -- -- -- --  -->

    <div id="modal-export-custom" class="modal-overlay" style="display:none;">
        <div class="modal-confirm-content" style="max-width:500px;">
            <div class="modal-confirm-icon"><i data-lucide="download-cloud"></i></div>
            <h2>Paramètres d'export</h2>
            <p class="text-muted small">Sélectionnez les collaborateurs à inclure dans le CSV</p>

            <div class="search-container mb-3" style="position:relative;">
                <i data-lucide="search" style="position:absolute;left:10px;top:10px;width:16px;color:#9ca3af;"></i>
                <input type="text" id="search-export-user" class="form-control" placeholder="Rechercher un nom..."
                    style="padding-left:35px;height:38px;font-size:13px;border-radius:8px;">
            </div>

            <!-- ZONE SÉLECTIONNÉS — visible seulement si au moins 1 coché -->
            <div id="export-selected-zone" style="display:none;margin-bottom:10px;">
                <div
                    style="font-size:10px;font-weight:600;color:#374151;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                    <i data-lucide="check-circle" style="width:12px;color:#10b981;"></i>
                    Sélectionnés
                    <span id="export-selected-count"
                        style="background:#d1fae5;color:#065f46;border:1px solid #10b981;border-radius:10px;padding:1px 8px;font-size:10px;">0</span>
                </div>
                <div id="export-selected-chips" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
                <div style="border-bottom:1px solid #e5e7eb;margin-top:10px;"></div>
            </div>

            <div
                style="text-align:left;max-height:250px;overflow-y:auto;border:1px solid var(--bordure);border-radius:10px;padding:10px;">
                <label
                    style="display:flex;align-items:center;gap:10px;padding:8px;border-bottom:1px solid var(--bordure);font-weight:700;cursor:pointer;">
                    <input type="checkbox" id="export-select-all" checked> Tout sélectionner
                </label>
                <div id="export-users-list"></div>
            </div>

            <div class="modal-confirm-actions mt-3" style="display:flex;gap:10px;justify-content:flex-end;">
                <button onclick="document.getElementById('modal-export-custom').style.display='none'"
                    class="btn-cancel">Annuler</button>
                <button id="btn-confirm-export-csv" class="btn-action" style="border:1px ;background:#fff hover:blue;">
                    <i data-lucide="file-spreadsheet" style="width:14px;margin-right:5px;"></i>
                    CSV
                </button>
                <button id="btn-confirm-export-pdf" class="btn-submit">
                    <i data-lucide="file-text" style="width:14px;margin-right:5px;"></i>
                    PDF
                </button>
            </div>
        </div>
    </div>

    <div id="custom-confirm-modal" class="modal-overlay" style="display:none;">
        <div class="modal-confirm-content">
            <div class="modal-confirm-icon" id="modal-confirm-icon"><i data-lucide="help-circle"></i></div>
            <h2 id="modal-confirm-title">Confirmation</h2>
            <p id="modal-confirm-text">...</p>
            <div class="modal-confirm-actions">
                <button id="modal-confirm-cancel" class="btn-cancel">Annuler</button>
                <button id="modal-confirm-proceed" class="btn-submit">Confirmer</button>
            </div>
        </div>
    </div>

    <div id="modal-trace-modif" class="modal-overlay" style="display:none;">
        <div class="modal-confirm-content" style="max-width:480px; text-align:left;">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold mb-0" id="trace-title">Demande en cours</h3>
                <i data-lucide="x" class="close-icon"
                    onclick="document.getElementById('modal-trace-modif').style.display='none'"
                    style="cursor:pointer;"></i>
            </div>

            <div id="trace-badge-zone" class="mb-3"></div>

            <!-- Bloc avant / après -->
            <div
                style="display:grid; grid-template-columns:1fr auto 1fr; gap:10px; align-items:center; background:#f9fafb; border-radius:10px; padding:12px 14px; margin-bottom:16px;">
                <div>
                    <div
                        style="font-size:10px; font-weight:500; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">
                        Congé actuel</div>
                    <div id="trace-avant" style="font-size:13px; color:#dc2626; text-decoration:line-through;"></div>
                </div>
                <div style="font-size:18px; color:#9ca3af;">→</div>
                <div>
                    <div id="trace-apres-label"
                        style="font-size:10px; font-weight:500; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">
                    </div>
                    <div id="trace-apres" style="font-size:13px; color:#059669;"></div>
                </div>
            </div>

            <!-- Raison -->
            <div
                style="background:#fefce8; border:1px solid #fde68a; border-radius:8px; padding:12px 14px; margin-bottom:16px;">
                <div
                    style="font-size:10px; font-weight:500; color:#92400e; text-transform:uppercase; margin-bottom:4px;">
                    <i data-lucide="message-square" style="width:11px; vertical-align:middle;"></i> Motif invoqué
                </div>
                <div id="trace-raison" style="font-style:italic; font-size:13px; color:#78350f;"></div>
            </div>

            <!-- Date de la demande -->
            <div style="font-size:12px; color:#9ca3af; text-align:right;">
                <i data-lucide="calendar" style="width:12px; vertical-align:middle;"></i>
                Soumis le <span id="trace-date"></span>
            </div>

            <div class="mt-4 text-center">
                <button class="btn-cancel"
                    onclick="document.getElementById('modal-trace-modif').style.display='none'">Fermer</button>
            </div>
        </div>
    </div>

    <!-- ============================================================
     SCRIPTS — chargés EN FIN DE BODY pour que le DOM soit prêt
     ============================================================ -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script>
        var dayoff_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
        var congesEvents = <?php echo json_encode($events); ?>;
    </script>
    <script src="<?php echo plugin_dir_url(__FILE__) . 'assets/js/index.js'; ?>"></script>
    <script>
        // On attend que tout soit chargé avant d'initialiser les icônes
        document.addEventListener("DOMContentLoaded", function () {
            lucide.createIcons();
            // Re-créer les icônes après un délai pour les sections cachées
            setTimeout(() => lucide.createIcons(), 300);
        });
    </script>

</body>

</html>