<?php
// Load application configuration (constants, DB connection, helper functions, etc.)
require_once __DIR__ . '/config.php';

// Require user login before accessing notifications page
// Redirects to index.php if no active session is found
$user = require_login();

// Load flash messages (success / error alerts)
// Consumed here and cleared from the session so they only appear once
$flash = flash_get();

// Get search input and status filter up front so redirects can preserve them
// request_string() trims whitespace and falls back to '' / 'all' when absent
$search = request_string('q');
$status = request_string('status', 'all');

// Handle POST actions (mark as read or dismiss)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF protection — halts with HTTP 419 if token is missing or mismatched
    verify_csrf();

    // Get notification ID and action type from form
    // Defaults to 'read' if no action is provided in the POST body
    $notificationId = (int) ($_POST['notification_id'] ?? 0);
    $action         = (string) ($_POST['action']          ?? 'read');

    // Only proceed if valid notification ID is provided
    // ID of 0 or below means no specific notification was targeted (e.g. mark_all_read)
    if ($notificationId > 0) {

        // If user chooses to dismiss notification
        // Dismissing hides the notification from the default view but keeps it in history
        if ($action === 'dismiss') {
            dismiss_notification($notificationId, (int) $user['id']);

            // Log dismissal action for audit tracking
            log_audit((int) $user['id'], 'notification.dismissed', 'notification', $notificationId);

        } else {
            // Otherwise mark notification as read (sets is_read = 1 in the database)
            mark_notification_read($notificationId, (int) $user['id']);

            // Log read action for audit tracking
            log_audit((int) $user['id'], 'notification.read', 'notification', $notificationId);
        }

    } elseif ($action === 'mark_all_read') {
        // Bulk action — marks every unread notification for this user as read in one call
        // Returns the count of rows updated so we only log when something actually changed
        $count = mark_all_notifications_read((int) $user['id']);
        if ($count > 0) {
            // Pass the count as extra context in the audit log for reporting purposes
            log_audit((int) $user['id'], 'notification.mark_all_read', 'notification', null, ['count' => $count]);
        }
    }

    // Redirect back to the current filtered view after action
    // query_string() rebuilds the GET params (q + status) so filters are not lost
    header('Location: notifications.php?' . query_string(['q' => $search, 'status' => $status]));
    exit;
}

// Fetch all notifications for current user (including dismissed)
// The second argument (true) instructs the function to include dismissed entries
// so the 'dismissed' status filter tab has data to display
$notifications = notifications_for_user((int) $user['id'], true);

// Apply filtering (search + status filter)
// array_values() re-indexes the result to a dense 0-based array after filtering
$notifications = array_values(array_filter($notifications, function (array $note) use ($search, $status): bool {

    // Search in message or related feedback subject
    // stripos() is used for case-insensitive substring matching
    if (
        $search !== '' &&
        stripos($note['message'], $search) === false &&
        stripos((string) ($note['feedback_subject'] ?? ''), $search) === false
    ) {
        return false; // Neither message nor feedback subject matches — exclude this item
    }

    // Show only unread notifications
    // is_read is stored as an integer (0 = unread, 1 = read) in the database
    if ($status === 'unread' && (int) $note['is_read'] === 1) {
        return false;
    }

    // Show only dismissed notifications
    // is_dismissed is stored as an integer (0 = active, 1 = dismissed)
    if ($status === 'dismissed' && (int) $note['is_dismissed'] === 0) {
        return false;
    }

    // Show only read notifications
    if ($status === 'read' && (int) $note['is_read'] === 0) {
        return false;
    }

    // Notification passed all active filters — include it
    return true;
}));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <!-- Responsive layout — ensures the page scales correctly on mobile devices -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Page title — APP_BRAND is defined in config.php -->
  <title>Notifications | <?= h(APP_BRAND) ?></title>

  <!-- Fonts and icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Google fonts: Lexend (brand/headings) and Inter (body text) -->
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Material Symbols icon font (outlined style, variable weight/fill axes) -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Main application stylesheet -->
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">

  <!-- ================================================================
       SIDEBAR NAVIGATION
       Fixed left-hand panel; .is-active highlights the current page link
       ================================================================ -->
  <aside class="sidebar">

    <!-- App branding — logo icon, system name, and subtitle -->
    <div class="sidebar__brand">
      <div class="sidebar__logo">
        <span class="material-symbols-outlined">school</span>
      </div>
      <div>
        <div class="sidebar__title brand">ASTU SFES</div>
        <div class="sidebar__subtitle">Academic Management</div>
      </div>
    </div>

    <!-- Navigation links — .is-active on the Notifications link marks the current page -->
    <nav class="sidebar__nav">
      <a class="sidebar__link" href="dashboard.php">
        <span class="material-symbols-outlined">dashboard</span><span>Dashboard</span>
      </a>
      <a class="sidebar__link" href="feedback.php">
        <span class="material-symbols-outlined">rate_review</span><span>Submit Feedback</span>
      </a>
      <!-- Current page -->
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

  <!-- ================================================================
       TOP BAR
       Contains the mobile menu toggle, page title, and user profile chip
       ================================================================ -->
  <header class="topbar">

    <div class="row" style="gap: 0.85rem;">

      <!-- Hamburger button — toggles the sidebar on mobile viewports via app.js -->
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>

      <!-- Page heading shown in the top bar -->
      <div class="topbar__title brand">Notifications</div>
    </div>

    <!-- Right-hand profile chip: logged-in user's name, role label, and avatar initial -->
    <div class="topbar__actions">
      <div class="profile">
        <div class="profile__meta">
          <div class="profile__name"><?= h($user['name']) ?></div>
          <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
        </div>
        <!-- Avatar circle — first letter of the user's name, generated by user_initial() -->
        <div class="avatar"><?= h(user_initial($user)) ?></div>
      </div>
    </div>
  </header>

  <!-- ================================================================
       MAIN CONTENT
       ================================================================ -->
  <main class="main">
    <div class="page">

      <!-- FLASH MESSAGE
           Conditionally rendered alert banner for success/error notifications
           from the previous POST action (e.g. after marking as read) -->
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>">
          <?= h($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- PAGE HEADER
           Hero section that orients the user before the notification list -->
      <section class="hero">
        <div class="hero__eyebrow">Account activity</div>
        <h1 class="hero__title">Persistent notifications</h1>
        <p class="hero__lead">Track feedback updates, response events, and account alerts from the same place.</p>
      </section>

      <!-- NOTIFICATION LIST SECTION -->
      <section class="section card" style="margin-top: 1rem;">

        <!-- FILTER FORM (GET)
             Submits via GET so the filtered URL is bookmarkable and shareable.
             The Reset link appears only when an active filter is in effect. -->
        <form method="get" class="toolbar" style="margin-bottom: 1rem;">

          <!-- Free-text search — matches against notification message and feedback subject -->
          <div class="field" style="min-width: 16rem; flex: 1;">
            <label for="notif-search">Search</label>
            <input type="text" id="notif-search" name="q" value="<?= h($search) ?>" placeholder="Search notifications, feedback titles, or subjects">
          </div>

          <!-- Status filter dropdown — maps to the $status variable used in array_filter() above -->
          <div class="field" style="min-width: 12rem;">
            <label for="status">Status</label>
            <select id="status" name="status">
              <!-- selected state is driven by the current $status value -->
              <option value="all"       <?= $status === 'all'       ? 'selected' : '' ?>>All</option>
              <option value="unread"    <?= $status === 'unread'    ? 'selected' : '' ?>>Unread</option>
              <option value="read"      <?= $status === 'read'      ? 'selected' : '' ?>>Read</option>
              <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            </select>
          </div>

          <!-- Submit the filter form -->
          <div style="align-self: end;">
            <button class="btn btn--primary" type="submit">Filter</button>
          </div>

          <!-- Reset link — only rendered when at least one filter is active -->
          <?php if ($search !== '' || $status !== 'all'): ?>
            <div style="align-self: end;">
              <!-- Navigating to the bare URL clears all GET params, resetting the filters -->
              <a class="btn btn--outline" href="notifications.php">Reset</a>
            </div>
          <?php endif; ?>
        </form>

        <!-- MARK ALL READ FORM (POST)
             Separate form from the filter — uses POST because it mutates server state.
             action="mark_all_read" is handled by the bulk branch in the POST handler above. -->
        <form method="post" class="row" style="justify-content: flex-end; margin-bottom: 1rem;">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="mark_all_read">
          <button class="btn btn--secondary btn--sm" type="submit">Mark all read</button>
        </form>

        <!-- NOTIFICATION ITEMS
             Each .mini-stat row renders one notification with its badges and action buttons -->
        <div class="stat-list">

          <?php foreach ($notifications as $note): ?>
            <div class="mini-stat">

              <!-- NOTIFICATION CONTENT (left side, flex: 1 fills available width) -->
              <div style="flex: 1;">

                <div class="row" style="flex-wrap: wrap; align-items: flex-start;">

                  <!-- Primary notification message -->
                  <strong><?= h($note['message']) ?></strong>

                  <!-- Optional feedback subject badge — shown only when linked to a feedback entry -->
                  <?php if (!empty($note['feedback_subject'])): ?>
                    <span class="badge badge-indigo"><?= h($note['feedback_subject']) ?></span>
                  <?php endif; ?>

                  <!-- Read/unread status badge — green for read, amber for unread -->
                  <span class="<?= (int) $note['is_read'] === 1 ? 'badge badge-green' : 'badge badge-amber' ?>">
                    <?= (int) $note['is_read'] === 1 ? 'Read' : 'Unread' ?>
                  </span>

                  <!-- Dismissed badge — only rendered when is_dismissed = 1 -->
                  <?php if ((int) $note['is_dismissed'] === 1): ?>
                    <span class="badge badge-slate">Dismissed</span>
                  <?php endif; ?>

                </div>

                <!-- Timestamp (human-readable date) and notification type identifier -->
                <div class="muted" style="margin-top: 0.35rem;">
                  <?= h(format_time($note['created_at'])) ?> · <?= h($note['type']) ?>
                </div>
              </div>

              <!-- NOTIFICATION ACTIONS (right side)
                   One POST form per notification row; notification_id is passed as a hidden field.
                   The two submit buttons share the same form — the clicked button's name/value
                   determines which action ('read' or 'dismiss') is sent to the POST handler. -->
              <form method="post" class="row" style="gap: 0.5rem; flex-wrap: wrap;">
                <?= csrf_input() ?>
                <!-- Pass the specific notification ID so the handler knows which record to update -->
                <input type="hidden" name="notification_id" value="<?= (int) $note['id'] ?>">

                <!-- Mark as read — sets is_read = 1 for this notification -->
                <button class="btn btn--secondary btn--sm" name="action" value="read" type="submit">
                  Mark read
                </button>

                <!-- Dismiss — sets is_dismissed = 1; notification stays in history but is hidden by default -->
                <button class="btn btn--ghost btn--sm" name="action" value="dismiss" type="submit">
                  Dismiss
                </button>
              </form>

            </div>
          <?php endforeach; ?>

          <!-- EMPTY STATE
               Shown when $notifications is empty after filtering
               (either no notifications exist or all were filtered out) -->
          <?php if (!$notifications): ?>
            <div class="notice">
              <strong>No notifications match the current filters.</strong>
              <p class="muted" style="margin-bottom: 0;">Try clearing the search or switching back to All.</p>
            </div>
          <?php endif; ?>

        </div><!-- /.stat-list -->
      </section>

      <!-- Footer note — reassures users that dismissed items are not permanently deleted -->
      <div class="footer">Dismissed notifications stay available in your activity history.</div>
    </div>
  </main>
</body>
</html>