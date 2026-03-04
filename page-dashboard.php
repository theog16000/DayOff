<?php
/**
 * Template Name: DayOff Dashboard
 */

if (!is_user_logged_in()) {
    wp_redirect(home_url('/connexion'));
    exit;
}

//récupère l'utilisateur
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

//  gère les metas
// Force les valeurs pour le test 
update_user_meta($user_id, 'gcp_solde_cp', 2);
update_user_meta($user_id, 'gcp_solde_rtt', 10);

$solde_cp      = get_user_meta($user_id, 'gcp_solde_cp', true) ?: '0';
$solde_rtt     = get_user_meta($user_id, 'gcp_solde_rtt', true) ?: '0';
$solde_recup   = get_user_meta($user_id, 'gcp_solde_recup', true) ?: '0';
$solde_maladie = get_user_meta($user_id, 'gcp_solde_maladie', true) ?: '0';


global $wpdb;
$table_conges = $wpdb->prefix . 'conges_demandes';

// Récup de TOUTES les demandes (déjà présent)
$mes_demandes = $wpdb->get_results($wpdb->prepare(
  "SELECT * FROM $table_conges WHERE user_id = %d ORDER BY created_at DESC",
  $user_id
));

//Préparation des évènements pour le calendrier

$events = array();
foreach($mes_demandes as $demande) {
  $color = '#3f81ea';
  if($demande->statut === 'Validé') $color = '#10b981';
  if($demande->statut === 'Refusé') $color = '#ef4444';

 $events[] = array(
        'title' => $demande->type_conge . ' (' . $demande->statut . ')',
        'start' => $demande->date_debut,
        'end'   => date('Y-m-d', strtotime($demande->date_fin . ' +1 day')), 
        'backgroundColor' => $color,
        'borderColor' => $color
    );
}

// NOUVEAU : Récup des 3 prochaines absences (futures uniquement)
// NOUVEAU : Récup des 3 prochaines absences (Toutes : En attente OU Validé)
$aujourdhui = date('Y-m-d');
$prochaines_absences = $wpdb->get_results($wpdb->prepare(
  "SELECT * FROM $table_conges 
   WHERE user_id = %d 
   AND date_debut >= %s 
   ORDER BY date_debut ASC 
   LIMIT 3",
  $user_id,
  $aujourdhui
));

// récupère l'URL de l'image de profil
$avatar_url = get_avatar_url($user_id);

$is_admin = in_array('administrator', (array) $current_user->roles);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DayOff - Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'assets/css/style.css'; ?>">
    <script>
    var dayoff_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
    // On injecte les événements ici pour qu'ils soient dispos pour index.js
    var congesEvents = <?php echo json_encode($events); ?>; 
</script>
<script src="<?php echo plugin_dir_url(__FILE__) . 'assets/js/index.js'; ?>" defer></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>


</head>

<body>

  <aside class="menu">
    <header>
      <h1>DayOff</h1>
      <button class="button">Déposer une demande</button>
    </header>

    <a href="#" class="menu-item active" data-target="dashboard">
      <i data-lucide="layout-dashboard"></i><span>Tableau de Bord</span>
    </a>
    <a href="#" class="menu-item" data-target="calendrier">
      <i data-lucide="calendar"></i><span>Calendrier</span>
    </a>
    <a href="#" class="menu-item" data-target="mes_demandes">
      <i data-lucide="tree-palm"></i><span>Mes Demandes</span>
    </a>

    <?php if ($is_admin) : ?>
    <hr>
    <a href="#" class="menu-item" data-target="validation_conges">
      <i data-lucide="badge-check"></i><span>Validations</span>
    </a>
    <a href="#" class="menu-item" data-target="gestion_users">
      <i data-lucide="shield-user"></i><span>Gestion Utilisateurs</span>
    </a>
    <a href="#" class="menu-item" data-target="config">
      <i data-lucide="bolt"></i><span>Configuration</span>
    </a>
    <?php endif; ?>

    <hr>
    <a href="#" class="menu-item" data-target="parametres">
      <i data-lucide="settings"></i><span>Paramètres</span>
    </a>

    <a href="<?php echo wp_logout_url(home_url('/connexion')); ?>" class="menu-item">
      <i data-lucide="log-out"></i><span>Déconnexion</span>
    </a>
  </aside>
<div class="demande-container">
    <form id="form-conge">
        <div class="form-body">
            <div class="form-group full-width">
                <label>Type</label>
                <select name="type_conge" id="type_conge">
                    <option value="CP">Congé payé (CP)</option>
                    <option value="RTT">RTT</option>
                    <option value="Maladie">Arrêt maladie</option>
                </select>
            </div>

            <div class="row">
                <div class="form-group">
                    <label>Date de début</label>
                    <input type="date" name="date_debut" id="date_debut" required>
                </div>
                <div class="form-group">
                    <label>Date de fin</label>
                    <input type="date" name="date_fin" id="date_fin" required>
                </div>
            </div>

            <div class="form-group full-width">
                <label>Commentaire (optionnel)</label>
                <textarea name="motif" id="motif" rows="2"></textarea>
            </div>
        </div>

        <div class="form-footer">
            <button type="button" class="btn-cancel">Annuler</button>
            <button type="submit" class="btn-submit">Envoyer la demande</button>
        </div>
    </form>
</div>
  <main class="container">


  

<!-- --- ONGLET DASHBOARD --- -->
      
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
    <h6>Mes Prochaines Absences</h6>
    <i data-lucide="tickets-plane"></i>
  </div>
  <ul>
    <?php if ( !empty($prochaines_absences) ) : ?>
        <?php foreach ( $prochaines_absences as $abs ) : ?>
          <li>
            <i data-lucide="calendar"></i> 
            <strong><?php echo esc_html($abs->type_conge); ?></strong> - 
            <?php echo date('d/m/Y', strtotime($abs->date_debut)); ?> au 
            <?php echo date('d/m/Y', strtotime($abs->date_fin)); ?>
          </li>
        <?php endforeach; ?>
    <?php else : ?>
        <li class="text-muted">Aucune absence prévue.</li>
    <?php endif; ?>
  </ul>
  <button class="details" onclick="document.querySelector('[data-target=\'mes_demandes\']').click();">
    Voir plus de détails ...
  </button>
</div>
      </div>

      <div class="apropos">
        <h6>A propos</h6>
        <p>Bienvenue sur votre espace DayOff. Ici vous pouvez gérer vos demandes de congés et suivre vos soldes en temps réel.</p>
      </div>
    </section>


<!-- --- ONGLET CALENDRIER ---  -->

<section id="calendrier" class="tab-content">
    <div class="global">
        <div>
            <h1>Mon Calendrier</h1>
            <p class="text-muted">Visualisez vos congés et les absences de l'équipe</p>
        </div>
        <div class="profile">
            <img src="<?php echo esc_url($avatar_url); ?>" alt="Profile">
            <span><?php echo esc_html($current_user->display_name); ?></span>
        </div>
    </div>

    <div id="calendar-container" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--bordure);">
        <div id="calendar"></div>
    </div>

    <div class="mt-4 d-flex gap-4">
        <div class="d-flex align-items-center gap-2">
            <span style="width: 12px; height: 12px; background: #3f81ea; border-radius: 3px; display: inline-block;"></span>
            <small>En attente</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span style="width: 12px; height: 12px; background: #10b981; border-radius: 3px; display: inline-block;"></span>
            <small>Validé</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span style="width: 12px; height: 12px; background: #ef4444; border-radius: 3px; display: inline-block;"></span>
            <small>Refusé</small>
        </div>
    </div>
</section>






    <!-- --- ONGLET MES DEMANDES ---  -->

    <section id="mes_demandes" class="tab-content">
      <div class="global d-flex justify-content-between align-items-center mb-4">
        <div class="title-area">
          <h1 class="h2 fw-bold">Mes Demandes</h1>
        </div>
        <div class="profile d-flex align-items-center bg-light p-2 rounded-pill">
          <img src="<?php echo esc_url($avatar_url); ?>" alt="Profile">
          <span class="fw-semibold me-2"><?php echo esc_html($current_user->display_name); ?></span>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle" style="width: 100%; border-collapse: collapse;">
          <thead class="table-light">
            <tr>
              <th scope="col" style="width: 20%;">Nom</th>
              <th scope="col" style="width: 10%;">Type</th>
              <th scope="col" style="width: 25%;">Période</th>
              <th scope="col" style="width: 15%;">Statut</th>
              <th scope="col" style="width: 30%;">Commentaire</th>
              <th scope="col">Supprimer </th>
            </tr>
            <tr class="bg-white">
              <th>
                <input type="text" id="filterNom" placeholder="Nom..." class="form-control form-control-sm">
              </th>
              <th>
                <select id="filterType" class="form-select form-select-sm">
                  <option value="">Tous</option>
                  <option value="CP">CP</option>
                  <option value="RTT">RTT</option>
                  <option value="CSS">CSS</option>
                </select>
              </th>
              <th>
                <div class="d-flex gap-1">
                  <input type="date" id="filterDateDebut" class="form-control form-control-sm">
                  <input type="date" id="filterDateFin" class="form-control form-control-sm">
                </div>
              </th>
              <th></th>
              <th class="text-end">
                <button onclick="resetFilter()" class="btn btn-sm btn-link text-decoration-none">Réinitialiser
                  ...</button>
              </th>
            </tr>
          </thead>
         <tbody id="tableBody">
    <?php if ( !empty($mes_demandes) ) : ?>
        <?php foreach ( $mes_demandes as $demande ) : ?>
            <tr>
                <td><?php echo esc_html($current_user->display_name); ?></td>
                <td><span class="badge badge-<?php echo strtolower($demande->type_conge); ?>"><?php echo esc_html($demande->type_conge); ?></span></td>
                <td class="fw-medium">
                    Du <?php echo date('d/m/Y', strtotime($demande->date_debut)); ?> 
                    au <?php echo date('d/m/Y', strtotime($demande->date_fin)); ?>
                </td>
                <td>
                    <?php 
                        // Style dynamique en fonction du statut
                        $status_class = ($demande->statut == 'Validé') ? 'actif' : 'inactif';
                        echo '<span class="badge ' . $status_class . '">' . esc_html($demande->statut) . '</span>';
                    ?>
                </td>
                <td class="text-muted small"><em><?php echo esc_html($demande->motif); ?></em></td>
                <td class="text-center">
                    <button class="btn-icon delete" data-id="<?php echo $demande->id; ?>">
                        <i data-lucide="trash-2"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else : ?>
        <tr>
            <td colspan="6" class="text-center">Aucune demande enregistrée pour le moment.</td>
        </tr>
    <?php endif; ?>
</tbody>
        </table>
      </div>
    </section>

   <!-- --- SECTION VALIDATION CONGES --- -->
<section id="validation_conges" class="tab-content">
  <tbody id="valTableBody">
    <?php
    // On récupère TOUTES les demandes "En attente" de TOUS les utilisateurs
    $all_pending = $wpdb->get_results("SELECT d.*, u.display_name 
                                       FROM $table_conges d 
                                       JOIN {$wpdb->users} u ON d.user_id = u.ID 
                                       WHERE d.statut = 'En attente' 
                                       ORDER BY d.created_at ASC");

    if (!empty($all_pending)) :
        foreach ($all_pending as $val) : ?>
          <tr data-id="<?php echo $val->id; ?>">
            <td><strong><?php echo esc_html($val->display_name); ?></strong></td>
            <td><span class="badge badge-cp"><?php echo esc_html($val->type_conge); ?></span></td>
            <td>Du <?php echo date('d/m/Y', strtotime($val->date_debut)); ?><br>au <?php echo date('d/m/Y', strtotime($val->date_fin)); ?></td>
            <td><span class="text-muted"><?php echo esc_html($val->motif ?: 'Aucun'); ?></span></td>
            <td><input type="text" class="form-control-sm w-100 comm-admin" placeholder="Note..."></td>
            <td>
              <div style="display: flex; gap: 8px; justify-content: center;">
                <button class="btn-submit" onclick="validerDemande(<?php echo $val->id; ?>, 'Validé')" style="background: #28a745;">Approuver</button>
                <button class="btn-cancel" onclick="validerDemande(<?php echo $val->id; ?>, 'Refusé')" style="background: #dc3545;">Refuser</button>
              </div>
            </td>
          </tr>
        <?php endforeach;
    else : ?>
        <tr><td colspan="6" class="text-center">Aucune demande en attente de validation.</td></tr>
    <?php endif; ?>
  </tbody>
</section>





  </main>

  <script>
    lucide.createIcons();

</script>
</body>
</html>