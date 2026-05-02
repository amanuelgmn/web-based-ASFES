<?php

require_once __DIR__ . '/config.php';

// 🔒 AUTH CHECK
$user = require_login();

// 📢 STATIC NOTIFICATIONS (demo data)
$notifications = [
    [
        'message' => 'System maintenance scheduled for May 5th.',
        'date'    => '2026-04-29',
        'type'    => 'info'
    ],
    [
        'message' => 'New feedback form released!',
        'date'    => '2026-04-28',
        'type'    => 'success'
    ],
    [
        'message' => 'Your last feedback was responded to.',
        'date'    => '2026-04-27',
        'type'    => 'success'
    ],
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">

  <title>Notifications | <?= h(APP_BRAND) ?></title>

  <link rel="stylesheet" href="assets/style.css">
</head>

<body class="app-shell">

<main class="main">
  <div class="page">

    <!-- TITLE -->
    <h1 style="display:flex;align-items:center;gap:0.5rem;">
      <span class="material-symbols-outlined" style="font-size:2rem;color:#2563eb;">
        notifications
      </span>
      Notifications
    </h1>

    <!-- SEARCH -->
    <input
      type="text"
      id="notif-search"
      class="input"
      placeholder="Search notifications..."
      style="margin-bottom:1.2rem;width:100%;max-width:400px;"
    >

    <!-- LIST -->
    <ul class="notification-list" id="notif-list">

      <?php foreach ($notifications as $note): ?>

        <li
          class="notification notification--<?= h($note['type']) ?>"
          data-message="<?= h(strtolower($note['message'])) ?>"
        >

          <span><?= h($note['message']) ?></span>

          <span class="notification__date">
            <?= h($note['date']) ?>
          </span>

          <button
            class="notif-dismiss"
            title="Dismiss"
            aria-label="Dismiss notification"
          >
            &times;
          </button>

        </li>

      <?php endforeach; ?>

    </ul>

    <!-- BACK -->
    <a href="dashboard.php" class="btn btn--outline" style="margin-top:1.5rem;">
      Back to Dashboard
    </a>

    <!-- SCRIPT -->
    <script>

      // 🔍 SEARCH FILTER
      const searchInput = document.getElementById('notif-search');

      searchInput.addEventListener('input', function () {
        const val = this.value.trim().toLowerCase();

        document.querySelectorAll('.notification-list .notification')
          .forEach(item => {
            item.style.display = item.dataset.message.includes(val) ? '' : 'none';
          });
      });

      // ❌ DISMISS (client-side only)
      document.querySelectorAll('.notif-dismiss')
        .forEach(btn => {
          btn.addEventListener('click', function () {
            this.parentElement.style.display = 'none';
          });
        });

    </script>

  </div>
</main>

</body>
</html>