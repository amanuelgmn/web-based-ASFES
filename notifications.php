<?php
require_once __DIR__ . '/config.php';

$user = require_login();
$flash = flash_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'read');

    if ($notificationId > 0) {
        if ($action === 'dismiss') {
            dismiss_notification($notificationId, (int) $user['id']);
            log_audit((int) $user['id'], 'notification.dismissed', 'notification', $notificationId);
        } else {
            mark_notification_read($notificationId, (int) $user['id']);
            log_audit((int) $user['id'], 'notification.read', 'notification', $notificationId);
        }
    }

    header('Location: notifications.php');
    exit;
}

$search = request_string('q');
$status = request_string('status', 'all');
$notifications = notifications_for_user((int) $user['id'], true);

$notifications = array_values(array_filter($notifications, function (array $note) use ($search, $status): bool {
    if ($search !== '' && stripos($note['message'], $search) === false && stripos((string) ($note['feedback_subject'] ?? ''), $search) === false) {
        return false;
    }

    if ($status === 'unread' && (int) $note['is_read'] === 1) {
        return false;
    }

    if ($status === 'dismissed' && (int) $note['is_dismissed'] === 0) {
        return false;
    }

    if ($status === 'read' && (int) $note['is_read'] === 0) {
        return false;
    }

    return true;
}));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications | <?= h(APP_BRAND) ?></title>
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
      <a class="sidebar__link is-active" href="notifications.php"><span class="material-symbols-outlined">notifications</span><span>Notifications</span></a>
      <a class="sidebar__link" href="profile.php"><span class="material-symbols-outlined">account_circle</span><span>Profile</span></a>
      <a class="sidebar__link" href="logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </nav>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">Notifications</div>
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
        <div class="hero__eyebrow">Account activity</div>
        <h1 class="hero__title">Persistent notifications</h1>
        <p class="hero__lead">Track feedback updates, response events, and account alerts from the same place.</p>
      </section>

      <section class="section card" style="margin-top: 1rem;">
        <form method="get" class="toolbar" style="margin-bottom: 1rem;">
          <div class="field" style="min-width: 16rem; flex: 1;">
            <label for="notif-search">Search</label>
            <input type="text" id="notif-search" name="q" value="<?= h($search) ?>" placeholder="Search notifications, feedback titles, or subjects">
          </div>
          <div class="field" style="min-width: 12rem;">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
              <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread</option>
              <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read</option>
              <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            </select>
          </div>
          <div style="align-self: end;">
            <button class="btn btn--primary" type="submit">Filter</button>
          </div>
        </form>

        <div class="stat-list">
          <?php foreach ($notifications as $note): ?>
            <div class="mini-stat">
              <div style="flex: 1;">
                <div class="row" style="flex-wrap: wrap; align-items: flex-start;">
                  <strong><?= h($note['message']) ?></strong>
                  <?php if (!empty($note['feedback_subject'])): ?>
                    <span class="badge badge-indigo"><?= h($note['feedback_subject']) ?></span>
                  <?php endif; ?>
                  <span class="<?= (int) $note['is_read'] === 1 ? 'badge badge-green' : 'badge badge-amber' ?>"><?= (int) $note['is_read'] === 1 ? 'Read' : 'Unread' ?></span>
                  <?php if ((int) $note['is_dismissed'] === 1): ?>
                    <span class="badge badge-slate">Dismissed</span>
                  <?php endif; ?>
                </div>
                <div class="muted" style="margin-top: 0.35rem;">
                  <?= h(format_time($note['created_at'])) ?> · <?= h($note['type']) ?>
                </div>
              </div>
              <form method="post" class="row" style="gap: 0.5rem; flex-wrap: wrap;">
                <?= csrf_input() ?>
                <input type="hidden" name="notification_id" value="<?= (int) $note['id'] ?>">
                <button class="btn btn--secondary btn--sm" name="action" value="read" type="submit">Mark read</button>
                <button class="btn btn--ghost btn--sm" name="action" value="dismiss" type="submit">Dismiss</button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (!$notifications): ?>
            <p class="muted">No notifications match the current filters.</p>
          <?php endif; ?>
        </div>
      </section>

      <div class="footer">Dismissed notifications stay available in your activity history.</div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
