<?php
/**
 * Template Name: Page Login Custom
 */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DayOff - Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'assets/css/style.css'; ?>">
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">

    <div class="login-card p-4 shadow-sm bg-white" style="width: 100%; max-width: 400px; border-radius: 15px;">

        <div class="text-center mb-4">
            <h1 class="fw-bold" style="color: #3f81ea;">DayOff</h1>
        </div>

        <?php
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $password_status = isset($_GET['password']) ? $_GET['password'] : '';

        // --- CAS 1 : L'UTILISATEUR VIENT DE CHANGER SON MOT DE PASSE ---
        if ($password_status == 'changed'): ?>
            <div class="alert alert-success small">Mot de passe modifié ! Connectez vous dès maintenant ! </div>
        <?php endif; ?>

        <?php
        // --- CAS 2 : FORMULAIRE DE NOUVEAU MOT DE PASSE (Via Email) ---
        if ($action == 'rp'): ?>
            <h2 class="h5 fw-bold mb-3">Définir votre nouveau mot de passe unique</h2>
            <form name="resetpassform" id="resetpassform"
                action="<?php echo esc_url(network_site_url('wp-login.php?action=resetpass', 'login_post')); ?>"
                method="post" autocomplete="off">
                <input type="hidden" id="user_login" name="rp_login" value="<?php echo esc_attr($_GET['login']); ?>"
                    autocomplete="off" />
                <input type="hidden" name="rp_key" value="<?php echo esc_attr($_GET['key']); ?>" />

                <div class="mb-3">
                    <label class="form-label small">Nouveau mot de passe</label>
                    <input type="password" name="pass1" id="pass1" class="form-control" required
                        autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label small">Confirmez le mot de passe</label>
                    <input type="password" name="pass2" id="pass2" class="form-control" required
                        autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary w-100" style="background: #3f81ea;">Enregistrer</button>
            </form>

            <?php
            // --- CAS 3 : FORMULAIRE DE CONNEXION NORMAL ---
        else: ?>
            <h2 class="h5 fw-bold mb-3">Connexion</h2>

            <?php if (isset($_GET['login']) && $_GET['login'] == 'failed'): ?>
                <div class="alert alert-danger small">Identifiants incorrects.</div>
            <?php endif; ?>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="gcp_login">

                <div class="mb-3">
                    <label class="form-label small">Email</label>
                    <input type="text" name="log" class="form-control" placeholder="nom@entreprise.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Mot de passe</label>
                    <input type="password" name="pwd" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3"
                    style="background: #3f81ea; border: none; padding: 12px;">
                    Se connecter
                </button>
            </form>
        <?php endif; ?>

    </div>

</body>

</html>