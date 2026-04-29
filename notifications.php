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
      <h1>Notifications</h1>
      <ul class="notification-list">
        <?php foreach ($notifications as $note): ?>
          <li class="notification notification--<?= h($note['type']) ?>">
            <span><?= h($note['message']) ?></span>
            <span class="notification__date"><?= h($note['date']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <a href="dashboard.php" class="btn btn--outline">Back to Dashboard</a>
    </div>
  </main>
</body>
</html>