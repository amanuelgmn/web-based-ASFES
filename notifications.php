<?php
require_once __DIR__ . '/config.php';
$user = require_login();
$notifications = [
    ['message' => 'System maintenance scheduled for May 5th.', 'date' => '2026-04-29', 'type' => 'info'],
    ['message' => 'New feedback form released!', 'date' => '2026-04-28', 'type' => 'success'],
    ['message' => 'Your last feedback was responded to.', 'date' => '2026-04-27', 'type' => 'success'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications | <?= h(APP_BRAND) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">
  <main class="main">
    <div class="page">
      <h1 style="display:flex;align-items:center;gap:0.5rem;"><span class="material-symbols-outlined" style="font-size:2rem;color:#2563eb;">notifications</span> Notifications</h1>
      <input type="text" id="notif-search" class="input" placeholder="Search notifications..." style="margin-bottom:1.2rem;width:100%;max-width:400px;">
      <ul class="notification-list" id="notif-list">
        <?php foreach ($notifications as $i => $note): ?>
          <li class="notification notification--<?= h($note['type']) ?>" data-message="<?= h(strtolower($note['message'])) ?>">
            <span><?= h($note['message']) ?></span>
            <span class="notification__date"><?= h($note['date']) ?></span>
            <button class="notif-dismiss" title="Dismiss" style="margin-left:1rem;background:none;border:none;color:#888;cursor:pointer;font-size:1.1rem;" aria-label="Dismiss notification">&times;</button>
          </li>
        <?php endforeach; ?>
      </ul>
      <a href="dashboard.php" class="btn btn--outline" style="margin-top:1.5rem;">Back to Dashboard</a>
      <script>
      // Notification search
      document.getElementById('notif-search').addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        document.querySelectorAll('.notification-list .notification').forEach(item => {
          item.style.display = item.dataset.message.includes(val) ? '' : 'none';
        });
      });
      // Dismiss notification (client-side only)
      document.querySelectorAll('.notif-dismiss').forEach(btn => {
        btn.addEventListener('click', function() {
          this.parentElement.style.display = 'none';
        });
      });
      </script>
    </div>
  </main>
<!-- ADDED SAFE JS ENHANCEMENT (NO STRUCTURE CHANGE) -->
<script>
/* Persist dismissed notifications (no backend change) */
(function () {
  const dismissed = JSON.parse(localStorage.getItem('dismissed_notifs') || '[]');

  document.querySelectorAll('.notification').forEach((el, i) => {
    if (dismissed.includes(i)) {
      el.style.display = 'none';
    }
  });

  document.querySelectorAll('.notif-dismiss').forEach((btn, i) => {
    btn.addEventListener('click', function () {
      const item = this.parentElement;
      item.style.display = 'none';

      const list = JSON.parse(localStorage.getItem('dismissed_notifs') || '[]');
      list.push(i);
      localStorage.setItem('dismissed_notifs', JSON.stringify(list));
    });
  });
})();
</script>

</body>
</html>