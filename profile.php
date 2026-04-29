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
      <h1 style="display:flex;align-items:center;gap:0.5rem;"><span class="material-symbols-outlined" style="font-size:2rem;color:#2563eb;">account_circle</span> My Profile</h1>
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>"> <?= h($flash['message']) ?> </div>
      <?php endif; ?>
      <div class="card" style="max-width:500px;margin-bottom:2rem;">
        <div style="display:flex;align-items:center;gap:1.2rem;">
          <div class="avatar" style="width:3.2rem;height:3.2rem;font-size:1.5rem;"> <?= h(strtoupper(mb_substr($user['name'], 0, 1))) ?> </div>
          <div>
            <div style="font-size:1.2rem;font-weight:600;"> <?= h($user['name']) ?> </div>
            <div class="muted"> <?= h($user['email']) ?> </div>
            <div class="muted">Role: <?= h(role_label($user['role'])) ?> </div>
            <?php if (!empty($user['department'])): ?>
              <div class="muted">Department: <?= h($user['department']) ?></div>
            <?php endif; ?>
            <?php if (!empty($user['student_code'])): ?>
              <div class="muted">Student Code: <?= h($user['student_code']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <form method="post" class="form-grid" style="max-width: 400px;">
        <label>Name <input type="text" name="name" value="<?= h($user['name']) ?>" required></label>
        <label>Email <input type="email" name="email" value="<?= h($user['email']) ?>" required></label>
        <label>Change Password <input type="password" name="password" placeholder="New password (simulated)"></label>
        <button class="btn btn--primary" type="submit">Update Profile</button>
      </form>
      <a href="dashboard.php" class="btn btn--outline" style="margin-top:1.5rem;">Back to Dashboard</a>
      <script>
      // Optionally, add password visibility toggle here if desired
      </script>
    </div>
  </main>
</body>
</html>