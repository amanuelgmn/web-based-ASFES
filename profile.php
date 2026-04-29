<?php
require_once __DIR__ . '/config.php';
$user = require_login();
$flash = flash_get();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulate profile update
    $user['name'] = $_POST['name'] ?? $user['name'];
    $user['email'] = $_POST['email'] ?? $user['email'];
    flash_set('Profile updated!', 'success');
    header('Location: profile.php');
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Profile | <?= h(APP_BRAND) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">
  <main class="main">
    <div class="page">
      <h1>My Profile</h1>
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>"> <?= h($flash['message']) ?> </div>
      <?php endif; ?>
      <form method="post" class="form-grid" style="max-width: 400px;">
        <label>Name <input type="text" name="name" value="<?= h($user['name']) ?>" required></label>
        <label>Email <input type="email" name="email" value="<?= h($user['email']) ?>" required></label>
        <button class="btn btn--primary" type="submit">Update Profile</button>
      </form>
      <a href="dashboard.php" class="btn btn--outline">Back to Dashboard</a>
    </div>
  </main>
</body>
</html>