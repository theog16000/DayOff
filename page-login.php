<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page de connexion</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f8f9fa;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
    }

    .login-card {
      background: white;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
    }

    h1 {
      font-weight: 800;
      font-size: 2rem;
      margin-bottom: 1.5rem;
      text-align: center;
    }

    label {
      font-weight: 700;
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .btn-primary {
      width: 100%;
      padding: 0.75rem;
      font-weight: 700;
      margin-top: 1rem;
      border-radius: 8px;
    }

    .form-control {
      padding: 0.6rem;
      border-radius: 6px;
    }
  </style>
</head>

<body>

  <div class="login-card">
    <h1>DayOff - Connexion</h1>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <input type="hidden" name="action" value="gcp_login">
      <div class="mb-3">
        <label for="emailInput" class="form-label">Adresse Email</label>
        <input type="email" class="form-control" id="emailInput" placeholder="theo.genty@dayoff.com" required name="log">
      </div>

      <div class="mb-4">
        <label for="passwordInput" class="form-label">Mot de Passe</label>
        <input type="password" name="pwd" class="form-control" id="passwordInput" placeholder="••••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary">Se connecter</button>

    <?php if(isset($_GET['login']) && $_GET['login'] == 'failed'): ?>
      <p style="color: red; text-align:center; margin-top: 10px;">Identifiants incorrectes</p>
      <?php endif; ?>
  </div>

</body>

</html>
