<?php
require_once __DIR__ . '/config.php';
require_login();
$faqs = [
    ['q' => 'How do I submit feedback?', 'a' => 'Go to the Submit Feedback page and fill out the form.'],
    ['q' => 'Is my feedback anonymous?', 'a' => 'You can choose to submit feedback anonymously or with your name.'],
    ['q' => 'Who can see my feedback?', 'a' => 'Only authorized staff and instructors can view your feedback.'],
    ['q' => 'How do I update my profile?', 'a' => 'Visit the Profile page from the navigation menu.'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>FAQ | <?= h(APP_BRAND) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">
  <main class="main">
    <div class="page">
      <h1>Frequently Asked Questions</h1>
      <div class="faq-list">
        <?php foreach ($faqs as $faq): ?>
          <div class="faq-item">
            <strong>Q: <?= h($faq['q']) ?></strong>
            <p>A: <?= h($faq['a']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <a href="dashboard.php" class="btn btn--outline">Back to Dashboard</a>
    </div>
  </main>
</body>
</html>