<?php
/**
 * Plugin Name: DayOff - Gestion de Congés Pro
 * Description: Interface Front-end complète pour la gestion des congés.
 * Version: 1.0
 * Author: T. GENTY
 */

if (!defined('ABSPATH')) exit;


add_action('admin_post_nopriv_gcp_login', 'gcp_handle_login');
add_action('admin_post_gcp_login', 'gcp_handle_login' );

function gcp_handle_login() {
  //Récupération des données du formulaire

  $creds = array(
    'user_login' => $_POST['log'],
    'user_password' => $_POST['pwd'],
    'remember' => true
  );

  $user = wp_signon($creds, false);

  if(is_wp_error($user)) {
    wp_redirect(home_url('/connexion?login=failed'));
    exit;
  }
  else {
    wp_redirect(home_url('/dashboard-conges'));
    exit;
  }
}

//Afficher le template perso pour la connexion

add_filter('template_include', 'gcp_login_template');

function gcp_login_template($template) {
  if(is_page('connexion')) {
    $new_template = plugin_dir_path(__FILE__) . 'page-login.php';
    if(file_exists($new_template)) {
      return $new_template;
    }
  }
  return $template;
}

add_filter('template_include', function($template) {
  if(is_page('dashboard-conges')) {
    return plugin_dir_path(__FILE__) . 'page-dashboard.php';
  }
  return $template;
});

// Gestion de l'envoi du formulaire via AJAX
add_action('wp_ajax_submit_conge', 'gcp_handle_conge_submission');

function gcp_handle_conge_submission() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'conges_demandes';

    // AJOUTE LE POINT D'EXCLAMATION ICI :
    if (!is_user_logged_in()) {
        wp_send_json_error('Erreur : Vous n\'êtes pas connecté selon WordPress.');
    }

    $result = $wpdb->insert($table_name, array(
        'user_id'    => get_current_user_id(),
        'date_debut' => $_POST['date_debut'],
        'date_fin'   => $_POST['date_fin'],
        'type_conge' => $_POST['type_conge'],
        'motif'      => $_POST['motif'],
        'statut'     => 'En attente'
    ));

    if ($result) {
        wp_send_json_success('Demande enregistrée !');
    } else {
        wp_send_json_error('Erreur SQL : ' . $wpdb->last_error);
    }
}

//ACTION AJAX POUR LA SUPPRESSION
add_action('wp_ajax_delete_conge','gcp_handle_delete_conge');

function gcp_handle_delete_conge() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'conges_demandes';

  //Sécurité pour vérifier la connexion
if(!is_user_logged_in()) {
  wp_send_json_error('Session expirée');
}

$demande_id = intval($_POST['demande_id']);
$user_id = get_current_user_id();

//Supprime la demande seulement si elle appartient à l'utilisateur

$result = $wpdb->delete($table_name, array(
  'id' => $demande_id,
  'user_id'  => $user_id
));

if($result) {
  wp_send_json_success('Demande supprimée');
}
else {
  wp_send_json_error('Erreur lors de la suppression');
}
}

//Action AJAX pour traiter une demande de congé
add_action('wp_ajax_traiter_demande_admin', 'gcp_handle_traiter_demande');

function gcp_handle_traiter_demande() {
  global $wpdb;
  $table_name = $wpdb->prefix . 'conges_demandes';

  //Verification si utilisateur = admin
if(!current_user_can('administrator')) {
  wp_send_json_error('Accès refusé');
}

$demande_id = intval($_POST['demande_id']);
$decision = sanitize_text_field($_POST['decision']);
$reponse = sanitize_text_field($_POST['commentaire_admin']);

$result = $wpdb->update(
  $table_name,
  array('statut' => $decision),
  array('id' => $demande_id),
  array('%s'),
  array('%d')
);

if($result !== false) {
  wp_send_json_success("Demande $decision avec succès");
} else {
  wp_send_json_error('Erreur lors de la mise a jour');
}
}