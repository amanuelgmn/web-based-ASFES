<?php
require_once __DIR__ . '/config.php';

$user = require_login();
$flash = flash_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    if ($name === '' || $email === '') {
        flash_set('error', 'Name and email are required.');
        header('Location: profile.php');
        exit;
    }

    try {
        $updated = update_user_profile_record(
            (int) $user['id'],
            $name,
            $email,
            $password !== '' ? $password : null
        );

        if ($updated) {
            $_SESSION['user'] = array_merge($user, $updated);
            flash_set('success', 'Profile updated successfully.');
            log_audit((int) $user['id'], 'profile.updated', 'user', (int) $user['id'], ['email' => $email]);
        } else {
            flash_set('error', 'Could not update your profile.');
        }
    } catch (Throwable $exception) {
        flash_set('error', 'That email may already be in use.');
    }

    header('Location: profile.php');
    exit;
}

$latestNotifications = array_slice(notifications_for_user((int) $user['id']), 0, 3);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile | <?= h(APP_BRAND) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="sidebar__logo"><span class="material-symbols-outlined">school</span></div>
      <div>
        <div class="sidebar__title brand">ASTU SFES</div>
        <div class="sidebar__subtitle">Academic Management</div>
      </div>
    </div>
    <nav class="sidebar__nav">
      <a class="sidebar__link" href="dashboard.php"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
      <a class="sidebar__link" href="feedback.php"><span class="material-symbols-outlined">rate_review</span><span>Submit Feedback</span></a>
      <a class="sidebar__link" href="notifications.php"><span class="material-symbols-outlined">notifications</span><span>Notifications</span></a>
      <?php if ($user['role'] !== 'student'): ?>
        <a class="sidebar__link" href="respond.php"><span class="material-symbols-outlined">forum</span><span>Review Inbox</span></a>
        <a class="sidebar__link" href="report.php"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
      <?php endif; ?>
      <?php if ($user['role'] === 'admin'): ?>
        <a class="sidebar__link" href="users/users.php"><span class="material-symbols-outlined">manage_accounts</span><span>Admin Console</span></a>
      <?php endif; ?>
      <a class="sidebar__link" href="logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </nav>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">Profile</div>
    </div>
    <div class="topbar__actions">
      <div class="profile">
        <div class="profile__meta">
          <div class="profile__name"><?= h($user['name']) ?></div>
          <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
        </div>
        <div class="avatar"><?= h(user_initial($user)) ?></div>
      </div>
    </div>
  </header>

  <main class="main">
    <div class="page">
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endif; ?>

      <section class="hero">
        <div class="hero__eyebrow">Account settings</div>
        <h1 class="hero__title">Manage your profile and credentials</h1>
        <p class="hero__lead">Update your contact details, set a new password, and review the latest notifications tied to your account.</p>
      </section>

      <section class="section split" style="margin-top: 1rem;">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Profile details</h2>
              <p class="section__sub">Changes persist to the database immediately.</p>
            </div>
          </div>

          <form method="post" class="form-grid" style="max-width: 44rem;">
            <?= csrf_input() ?>

            <div class="split-grid">
              <label class="field">
                <span>Name</span>
                <input type="text" name="name" value="<?= h($user['name']) ?>" required>
              </label>
              <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= h($user['email']) ?>" required>
              </label>
            </div>

            <label class="field">
              <span>New password</span>
              <input type="password" name="password" placeholder="Leave blank to keep your current password">
            </label>

            <div class="row" style="justify-content: flex-end;">
              <button class="btn btn--primary" type="submit">Save Changes</button>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Account summary</h2>
              <p class="section__sub">Quick view of your current identity and alerts.</p>
            </div>
          </div>

          <div class="stat-list">
            <div class="mini-stat">
              <div>
                <div class="muted">Role</div>
                <strong><?= h(role_label($user['role'])) ?></strong>
              </div>
              <span class="badge badge-indigo"><?= h($user['role']) ?></span>
            </div>
            <div class="mini-stat">
              <div>
                <div class="muted">Department</div>
                <strong><?= h($user['department']) ?></strong>
              </div>
            </div>
            <?php if (!empty($user['student_code'])): ?>
              <div class="mini-stat">
                <div>
                  <div class="muted">Student Code</div>
                  <strong><?= h($user['student_code']) ?></strong>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div style="margin-top: 1rem;">
            <h3 class="headline" style="font-size: 1.1rem; margin-bottom: 0.6rem;">Recent notifications</h3>
            <div class="activity-list">
              <?php foreach ($latestNotifications as $note): ?>
                <div class="activity">
                  <div class="avatar" style="width: 2.1rem; height: 2.1rem; font-size: 0.75rem;">N</div>
                  <div>
                    <strong><?= h($note['message']) ?></strong>
                    <div class="muted"><?= h(format_time($note['created_at'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (!$latestNotifications): ?>
                <p class="muted">No notifications yet.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <div class="footer">Keep your profile up to date for accurate routing and notifications.</div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
