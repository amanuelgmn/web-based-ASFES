<?php
require_once __DIR__ . '/config.php';

// Require user login before accessing notifications page
$user = require_login();

// Load flash messages (success / error alerts)
$flash = flash_get();

// Handle POST actions (mark as read or dismiss)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF protection
    verify_csrf();

    // Get notification ID and action type from form
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'read');

    // Only proceed if valid notification ID is provided
    if ($notificationId > 0) {

        // If user chooses to dismiss notification
        if ($action === 'dismiss') {
            dismiss_notification($notificationId, (int) $user['id']);

            // Log dismissal action for audit tracking
            log_audit((int) $user['id'], 'notification.dismissed', 'notification', $notificationId);

        } else {
            // Otherwise mark notification as read
            mark_notification_read($notificationId, (int) $user['id']);

            // Log read action for audit tracking
            log_audit((int) $user['id'], 'notification.read', 'notification', $notificationId);
        }
    }

    // Redirect back to notifications page after action
    header('Location: notifications.php');
    exit;
}

// Get search input from query string
$search = request_string('q');

// Get filter status (all, read, unread, dismissed)
$status = request_string('status', 'all');

// Fetch all notifications for current user (including dismissed)
$notifications = notifications_for_user((int) $user['id'], true);

// Apply filtering (search + status filter)
$notifications = array_values(array_filter($notifications, function (array $note) use ($search, $status): bool {

    // Search in message or related feedback subject
    if (
        $search !== '' &&
        stripos($note['message'], $search) === false &&
        stripos((string) ($note['feedback_subject'] ?? ''), $search) === false
    ) {
        return false;
    }

    // Show only unread notifications
    if ($status === 'unread' && (int) $note['is_read'] === 1) {
        return false;
    }

    // Show only dismissed notifications
    if ($status === 'dismissed' && (int) $note['is_dismissed'] === 0) {
        return false;
    }

    // Show only read notifications
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

  <!-- Page title -->
  <title>Notifications | <?= h(APP_BRAND) ?></title>

  <!-- Fonts and icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Google fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Material icons -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Main stylesheet -->
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">

  <!-- Sidebar navigation -->
  <aside class="sidebar">

    <!-- App branding -->
    <div class="sidebar__brand">
      <div class="sidebar__logo">
        <span class="material-symbols-outlined">school</span>
      </div>
      <div>
        <div class="sidebar__title brand">ASTU SFES</div>
        <div class="sidebar__subtitle">Academic Management</div>
      </div>
    </div>

    <!-- Navigation links -->
    <nav class="sidebar__nav">
      <a class="sidebar__link" href="dashboard.php">
        <span class="material-symbols-outlined">dashboard</span><span>Dashboard</span>
      </a>
      <a class="sidebar__link" href="feedback.php">
        <span class="material-symbols-outlined">rate_review</span><span>Submit Feedback</span>
      </a>
      <a class="sidebar__link is-active" href="notifications.php">
        <span class="material-symbols-outlined">notifications</span><span>Notifications</span>
      </a>
      <a class="sidebar__link" href="profile.php">
        <span class="material-symbols-outlined">account_circle</span><span>Profile</span>
      </a>
      <a class="sidebar__link" href="logout.php">
        <span class="material-symbols-outlined">logout</span><span>Logout</span>
      </a>
    </nav>
  </aside>

  <!-- Top bar -->
  <header class="topbar">

    <div class="row" style="gap: 0.85rem;">

      <!-- Mobile menu toggle -->
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>

      <!-- Page title -->
      <div class="topbar__title brand">Notifications</div>
    </div>

    <!-- User profile summary -->
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

      <!-- Flash messages -->
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>">
          <?= h($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- Page header -->
      <section class="hero">
        <div class="hero__eyebrow">Account activity</div>
        <h1 class="hero__title">Persistent notifications</h1>
        <p class="hero__lead">Track feedback updates, response events, and account alerts from the same place.</p>
      </section>

      <!-- Notification list section -->
      <section class="section card" style="margin-top: 1rem;">

        <!-- Filter form -->
        <form method="get" class="toolbar" style="margin-bottom: 1rem;">

          <!-- Search input -->
          <div class="field" style="min-width: 16rem; flex: 1;">
            <label for="notif-search">Search</label>
            <input type="text" id="notif-search" name="q" value="<?= h($search) ?>" placeholder="Search notifications, feedback titles, or subjects">
          </div>

          <!-- Status filter -->
          <div class="field" style="min-width: 12rem;">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
              <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Unread</option>
              <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Read</option>
              <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            </select>
          </div>

          <!-- Submit filter -->
          <div style="align-self: end;">
            <button class="btn btn--primary" type="submit">Filter</button>
          </div>
        </form>

        <!-- Notification items -->
        <div class="stat-list">

          <?php foreach ($notifications as $note): ?>
            <div class="mini-stat">

              <!-- Notification content -->
              <div style="flex: 1;">

                <div class="row" style="flex-wrap: wrap; align-items: flex-start;">

                  <!-- Message -->
                  <strong><?= h($note['message']) ?></strong>

                  <!-- Related feedback subject -->
                  <?php if (!empty($note['feedback_subject'])): ?>
                    <span class="badge badge-indigo"><?= h($note['feedback_subject']) ?></span>
                  <?php endif; ?>

                  <!-- Read/unread badge -->
                  <span class="<?= (int) $note['is_read'] === 1 ? 'badge badge-green' : 'badge badge-amber' ?>">
                    <?= (int) $note['is_read'] === 1 ? 'Read' : 'Unread' ?>
                  </span>

                  <!-- Dismissed badge -->
                  <?php if ((int) $note['is_dismissed'] === 1): ?>
                    <span class="badge badge-slate">Dismissed</span>
                  <?php endif; ?>

                </div>

                <!-- Timestamp and type -->
                <div class="muted" style="margin-top: 0.35rem;">
                  <?= h(format_time($note['created_at'])) ?> · <?= h($note['type']) ?>
                </div>
              </div>

              <!-- Actions -->
              <form method="post" class="row" style="gap: 0.5rem; flex-wrap: wrap;">
                <?= csrf_input() ?>
                <input type="hidden" name="notification_id" value="<?= (int) $note['id'] ?>">

                <!-- Mark as read -->
                <button class="btn btn--secondary btn--sm" name="action" value="read" type="submit">
                  Mark read
                </button>

                <!-- Dismiss notification -->
                <button class="btn btn--ghost btn--sm" name="action" value="dismiss" type="submit">
                  Dismiss
                </button>
              </form>

            </div>
          <?php endforeach; ?>

          <!-- Empty state -->
          <?php if (!$notifications): ?>
            <p class="muted">No notifications match the current filters.</p>
          <?php endif; ?>

        </div>
      </section>

      <!-- Footer note -->
      <div class="footer">Dismissed notifications stay available in your activity history.</div>
    </div>
  </main>
</body>
</html>