<?php

// Load application configuration (constants, DB connection, helper functions, etc.)
require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| LOGIN PAGE
|--------------------------------------------------------------------------
| This page handles:
| 1. Blocking access for already authenticated users
| 2. Processing login requests (POST)
| 3. Authenticating user credentials
| 4. Starting a secure session
| 5. Redirecting to dashboard on success
| 6. Showing login errors on failure
*/

// 🔒 REDIRECT IF USER IS ALREADY LOGGED IN
// Prevents logged-in users from seeing login page again
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

// $error holds any authentication failure message shown to the user
// $email is repopulated into the input on failure so the user doesn't retype it
$error = null;
$email = '';

// 📥 HANDLE LOGIN REQUEST (FORM SUBMISSION)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limit login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

$_SESSION['login_attempts']++;

if ($_SESSION['login_attempts'] > 5) {
    die("Too many login attempts. Please try again later.");
}

    // CSRF protection to prevent cross-site request attacks
    verify_csrf();

    // Get and sanitize user input
    // trim() removes accidental leading/trailing whitespace from the email
    
  $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$password = trim($_POST['password'] ?? '');

    // 🔐 Authenticate user against database
    // Returns the user array on success, or null if credentials are invalid
    $user = authenticate_user($email, $password);

    if ($user) {

        // 🧠 Regenerate session ID to prevent session fixation attacks
        // true = delete the old session file, not just change the ID
        session_regenerate_id(true);

        // Store the authenticated user data in the session for use across all pages
        $_SESSION['user'] = $user;

        // 🧾 Log login activity for audit tracking
        // Arguments: acting user ID, event name, target entity type, target entity ID
        log_audit((int) $user['id'], 'auth.login', 'user', (int) $user['id']);

        // 💬 Flash success message displayed on the dashboard after redirect
        flash_set('success', 'Welcome back, ' . $user['name'] . '.');

        // Redirect to dashboard after successful login
        header('Location: dashboard.php');
        exit;
    }

    // ❌ Authentication failed — set the inline error message
    // The hint about seeded accounts is useful during development/demo use
    $error = 'Invalid email or password. Use the seeded accounts from the README.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <!-- Responsive layout — ensures the page scales correctly on mobile devices -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Page title — APP_BRAND is defined in config.php -->
  <title>Login | <?= h(APP_BRAND) ?></title>

  <!-- Google Fonts: Lexend (brand/headings) and Inter (body text) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Material Symbols icon font (outlined style, variable weight/fill axes) -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Main application stylesheet -->
  <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<!-- .login-wrap centres the panel vertically and horizontally on the page -->
<main class="login-wrap">
  <section class="login-panel">

    <!-- BRAND HEADER
         Displays the application logo, name, and subtitle above the form card -->
    <div class="login-brand">
      <div class="login-brand__mark">
        <!-- aria-hidden="true" hides the decorative icon from screen readers -->
        <div class="sidebar__logo" aria-hidden="true">
          <span class="material-symbols-outlined">school</span>
        </div>

        <div>
          <h1 class="login-brand__name brand">ASTU SFES</h1>
        </div>
      </div>

      <!-- APP_SUBTITLE is a constant defined in config.php -->
      <p class="login-brand__sub"><?= h(APP_SUBTITLE) ?></p>
    </div>

    <!-- LOGIN FORM CARD
         The white card that contains the heading, error alert, and form fields -->
    <div class="login-card">

      <!-- Card heading and supporting copy -->
      <div style="margin-bottom: 1.2rem;">
        <h2 style="font-size: 1.8rem;">Login</h2>
        <p class="muted" style="margin-top: 0.35rem; line-height: 1.65;">
          Enter your institutional credentials to access the role-based dashboard.
        </p>
      </div>

      <!-- ERROR MESSAGE
           Conditionally rendered only when $error is set (i.e. after a failed POST) -->
      <?php if ($error): ?>
        <div class="flash flash--error"><?= h($error) ?></div>
      <?php endif; ?>

      <!-- LOGIN FORM
           Standard POST form — no enctype needed (no file uploads) -->
      <form method="post" class="form-grid">

        <!-- CSRF TOKEN — must be present on every POST form to satisfy verify_csrf() -->
        <?= csrf_input() ?>

        <!-- EMAIL FIELD
             value is repopulated from $email so the user doesn't retype after a failure
             autofocus places the cursor here automatically on page load -->
        <div class="field">
          <label for="email">Email Address</label>
          <div class="input-with-icon">
            <!-- Decorative mail icon rendered inside the input wrapper via CSS -->
            <span class="material-symbols-outlined input-icon">mail</span>
            <input
              id="email"
              name="email"
              type="email"
              value="<?= h($email) ?>"
              placeholder="name@institution.edu"
              autocomplete="email"
              autofocus
              required
            >
          </div>
        </div>

        <!-- PASSWORD FIELD
             autocomplete="current-password" hints to browsers/password managers -->
        <div class="field">
          <div class="row" style="justify-content: space-between;">
            <label for="password">Password</label>
            <!-- Inline help text — directs users to their admin instead of a reset link -->
            <span class="muted" style="font-size: 0.9rem;">
              Contact your department administrator for account help.
            </span>
          </div>

          <div class="input-with-icon">
            <!-- Decorative lock icon -->
            <span class="material-symbols-outlined input-icon">lock</span>

            <input
              id="password"
              name="password"
              type="password"
              placeholder="password"
              autocomplete="current-password"
              required
            >

            <!-- PASSWORD TOGGLE BUTTON
                 data-password-toggle="#password" tells app.js which input to toggle.
                 data-icon on the <span> is updated between 'visibility' and
                 'visibility_off' by the JS handler. -->
            <button
              class="password-toggle"
              type="button"
              data-password-toggle="#password"
              aria-label="Toggle password visibility"
            >
              <span class="material-symbols-outlined" data-icon>visibility</span>
            </button>
          </div>
        </div>

        <!-- SUBMIT BUTTON — full-width primary style -->
        <button class="btn btn--primary btn--full" type="submit">
          <span class="row" style="justify-content: center; gap: 0.55rem;">
            <span>Login</span>
            <span class="material-symbols-outlined">arrow_forward</span>
          </span>
        </button>

      </form>

      <!-- INFO SECTION
           Horizontal rule separator followed by a note about account provisioning -->
      <div style="border-top: 1px solid rgba(227, 226, 226, 0.9); margin: 1.4rem 0; padding-top: 1.3rem; text-align: center;">
        <p class="muted">New account access is issued by the university registry.</p>
      </div>

    </div><!-- /.login-card -->

    <!-- FOOTER
         Copyright line and placeholder policy links at the bottom of the panel -->
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

<!-- FEATURE BADGE
     Floating decorative chip in the corner of the page (positioned via CSS).
     Reinforces the "secure institutional access" brand message. -->
<aside class="feature-badge">
  <div class="feature-badge__art">
    <!-- --primary CSS variable is set in style.css -->
    <span class="material-symbols-outlined" style="color: var(--primary);">groups_2</span>
  </div>

  <div>
    <div class="muted" style="font-size: 0.9rem;">Secure institutional access</div>
    <div style="color: var(--primary); font-family: Lexend, system-ui, sans-serif; font-size: 1.05rem;">
      ASTU Community
    </div>
  </div>
</aside>

<!-- Main application JS: handles password-toggle, sidebar, and other shared behaviours -->
<script src="assets/app.js"></script>

</body>
</html>
