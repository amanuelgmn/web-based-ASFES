<?php
require_once __DIR__ . '/config.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = authenticate_user($email, $password);

    if ($user) {
        $_SESSION['user'] = $user;
        flash_set('success', 'Welcome back, ' . $user['name'] . '.');
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid email or password. Use the seeded accounts from the README.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | <?= h(APP_BRAND) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <main class="login-wrap">
    <section class="login-panel">
      <div class="login-brand">
        <div class="login-brand__mark">
          <div class="sidebar__logo" aria-hidden="true">
            <span class="material-symbols-outlined">school</span>
          </div>
          <div>
            <h1 class="login-brand__name brand">ASTU SFES</h1>
          </div>
        </div>
        <p class="login-brand__sub"><?= h(APP_SUBTITLE) ?></p>
      </div>

      <div class="login-card">
        <div style="margin-bottom: 1.2rem;">
          <h2 style="font-size: 1.8rem;">Login</h2>
          <p class="muted" style="margin-top: 0.35rem; line-height: 1.65;">Enter your institutional credentials to access the role-based dashboard.</p>
        </div>

        <?php if ($error): ?>
          <div class="flash flash--error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
          <div class="field">
            <label for="email">Email Address</label>
            <div class="input-with-icon">
              <span class="material-symbols-outlined input-icon">mail</span>
              <input id="email" name="email" type="email" placeholder="name@institution.edu" required>
            </div>
          </div>

          <div class="field">
            <div class="row" style="justify-content: space-between;">
              <label for="password">Password</label>
              <a href="#" class="muted" style="color: var(--primary);">Forgot password?</a>
            </div>
            <div class="input-with-icon">
              <span class="material-symbols-outlined input-icon">lock</span>
              <input id="password" name="password" type="password" placeholder="password" required>
              <button class="password-toggle" type="button" data-password-toggle="#password" aria-label="Toggle password visibility">
                <span class="material-symbols-outlined" data-icon>visibility</span>
              </button>
            </div>
          </div>

          <label class="toggle">
            <input type="checkbox" name="remember" value="1">
            <span>Remember this device</span>
          </label>

          <button class="btn btn--primary btn--full" type="submit">
            <span class="row" style="justify-content: center; gap: 0.55rem;">
              <span>Login</span>
              <span class="material-symbols-outlined">arrow_forward</span>
            </span>
          </button>
        </form>

        <div style="border-top: 1px solid rgba(227, 226, 226, 0.9); margin: 1.4rem 0; padding-top: 1.3rem; text-align: center;">
          <p class="muted">New to ASTU SFES?</p>
          <button class="btn btn--outline btn--full" type="button" style="margin-top: 0.9rem;">Request Institution Access</button>
        </div>
      </div>

      <div class="footer">
        <div>© 2024 ASTU SFES. All rights reserved.</div>
        <div class="login-meta">
          <a href="#">Privacy Policy</a>
          <span>•</span>
          <a href="#">Terms of Service</a>
        </div>
      </div>
    </section>
  </main>

  <aside class="feature-badge">
    <div class="feature-badge__art">
      <span class="material-symbols-outlined" style="color: var(--primary);">groups_2</span>
    </div>
    <div>
      <div class="muted" style="font-size: 0.9rem;">Trusted by over</div>
      <div style="color: var(--primary); font-family: Lexend, system-ui, sans-serif; font-size: 1.05rem;">ASTU Community</div>
    </div>
  </aside>

  <script src="assets/app.js"></script>
</body>
</html>
