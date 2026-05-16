<?php
require_once __DIR__ . '/config.php';

$user = require_login();
$feedbackRows = all_feedback();
$myFeedback = accessible_feedback($user, $feedbackRows);
$search = request_string('q');
if ($search !== '') {
  $myFeedback = array_values(array_filter($myFeedback, function (array $row) use ($search): bool {
    return stripos($row['subject'], $search) !== false
      || stripos($row['message'], $search) !== false
      || stripos((string) ($row['course_code'] ?? ''), $search) !== false
      || stripos((string) ($row['status'] ?? ''), $search) !== false;
  }));
}
$metrics = dashboard_metrics($user, $feedbackRows);
$courses = user_courses($user);
$flash = flash_get();
$recentFeedback = array_slice($myFeedback, 0, 6);
$totalResponses = response_total();
$openCases = count(array_filter($myFeedback, fn($row) => $row['status'] !== 'Closed'));
$completionRate = $myFeedback ? round((count(array_filter($myFeedback, fn($row) => $row['status'] === 'Closed')) / count($myFeedback)) * 100) : 0;
$unreadNotifications = unread_notification_count((int) $user['id']);

$avgRating = match ($user['role']) {
    'student' => '4.8',
    'instructor' => '4.6',
    'department' => '4.4',
    'student_affairs' => '4.7',
    'admin' => '4.82',
    default => '4.5',
};

// Navigation
$navigation = [
  ['dashboard.php', 'dashboard', 'Dashboard', 'dashboard'],
  ['feedback.php', 'rate_review', 'Submit Feedback', 'feedback'],
  ['notifications.php', 'notifications', 'Notifications', 'notifications'],
  ['profile.php', 'account_circle', 'Profile', 'profile'],
  ['faq.php', 'help', 'FAQ', 'faq'],
];

if ($user['role'] !== 'student') {
  $navigation[] = ['respond.php', 'forum', 'Review Inbox', 'inbox'];
  $navigation[] = ['report.php', 'analytics', 'Reports', 'reports'];
}
if ($user['role'] === 'admin') {
  $navigation[] = ['users/users.php', 'manage_accounts', 'Admin Console', 'users'];
}
$navigation[] = ['logout.php', 'logout', 'Logout', 'logout'];

$primaryAction = $user['role'] === 'student' ? ['feedback.php', 'Open Submission Flow'] : ['respond.php', 'Open Inbox'];
$secondaryAction = $user['role'] === 'student' ? ['dashboard.php#courses', 'My Courses'] : ['report.php', 'View Reports'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | <?= h(APP_BRAND) ?></title>

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
    <?php foreach ($navigation as [$href, $icon, $label, $key]): ?>
      <a class="sidebar__link <?= basename($_SERVER['PHP_SELF']) === $href ? 'is-active' : '' ?>" href="<?= h($href) ?>">
        <span class="material-symbols-outlined"><?= h($icon) ?></span>
        <span><?= h($label) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar__footer">
    <a class="btn btn--primary btn--full row" style="justify-content:center;" href="feedback.php">
      <span class="material-symbols-outlined">add</span>
      <span>New Evaluation</span>
    </a>
  </div>
</aside>

<header class="topbar">
  <div class="row" style="gap:0.85rem;">
    <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle>
      <span class="material-symbols-outlined">menu</span>
    </button>
    <div class="topbar__title brand">ASTU SFES</div>
  </div>

  <form class="search" method="get">
    <span class="material-symbols-outlined">search</span>
    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search courses, reports, or feedback...">
  </form>

  <div class="topbar__actions">
    <a class="icon-btn" href="notifications.php" aria-label="Notifications"><span class="material-symbols-outlined">notifications</span></a>
    <?php if ($unreadNotifications > 0): ?>
      <span class="badge badge-rose" style="position: relative; left: -1.2rem; top: -0.8rem; min-width: 1.6rem; height: 1.6rem; padding: 0 0.35rem;"><?= (int) $unreadNotifications ?></span>
    <?php endif; ?>
    <a class="icon-btn" href="faq.php"><span class="material-symbols-outlined">help</span></a>
    <a class="profile" href="profile.php">
      <div class="profile__meta">
        <div class="profile__name"><?= h($user['name']) ?></div>
        <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
      </div>
      <div class="avatar"><?= h(user_initial($user)) ?></div>
    </a>
  </div>
</header>

<main class="main">
<div class="page">

<?php if ($flash): ?>
  <div class="flash flash--<?= h($flash['type']) ?>">
    <?= h($flash['message']) ?>
  </div>
<?php endif; ?>

<!-- HERO -->
<section class="hero">
  <h1 class="hero__title">Welcome back, <?= h($user['name']) ?>!</h1>
  <p class="hero__lead">Your dashboard is showing <?= (int) count($myFeedback) ?> accessible feedback items and <?= (int) $unreadNotifications ?> unread notifications.</p>
  <div class="hero__actions">
    <a class="btn btn--primary" href="<?= h($primaryAction[0]) ?>"><?= h($primaryAction[1]) ?></a>
    <a class="btn btn--outline" href="<?= h($secondaryAction[0]) ?>"><?= h($secondaryAction[1]) ?></a>
  </div>
</section>

<!-- STATS -->
<section class="section">
  <div class="grid-stats grid-stats--4">

    <div class="card stat">
      <div class="stat__value"><?= count($myFeedback) ?></div>
      <div class="stat__meta">Submissions</div>
    </div>

    <div class="card stat">
      <div class="stat__value"><?= $metrics['responded'] ?></div>
      <div class="stat__meta">Responded</div>
    </div>

    <div class="card stat">
      <div class="stat__value"><?= $metrics['pending'] ?></div>
      <div class="stat__meta">Pending</div>
    </div>

    <div class="card stat">
      <div class="stat__value"><?= h($avgRating) ?></div>
      <div class="stat__meta"><?= $completionRate ?>% complete</div>
    </div>

  </div>
</section>

<!-- RECENT -->
  <section class="section">
    <div class="card">
      <h2>Recent Feedback</h2>
      <?php foreach ($recentFeedback as $item): ?>
        <div class="activity">
          <strong><?= h($item['subject']) ?></strong>
          <div class="muted"><?= h($item['message']) ?></div>
          <div class="muted">SLA: <?= h(sla_state($item)['state']) ?> · <?= h(format_time($item['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentFeedback): ?>
        <div class="notice">
          <strong>No feedback matches your search yet.</strong>
          <p class="muted" style="margin-bottom: 0;">Try a broader search or clear the query to see the latest submissions.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="section">
    <div class="card">
      <div class="section__head">
        <div>
        <h2 class="section__title">SLA snapshot</h2>
        <p class="section__sub">How close your current queue is to resolution targets.</p>
        </div>
      </div>

      <div class="stat-list">
        <div class="mini-stat">
          <div>
            <div class="muted">Open cases</div>
            <strong><?= (int) $openCases ?></strong>
          </div>
          <span class="badge badge-amber"><?= h($completionRate) ?>% closed</span>
        </div>
        <div class="mini-stat">
          <div>
            <div class="muted">Responses logged</div>
            <strong><?= (int) $totalResponses ?></strong>
          </div>
        </div>
        <div class="mini-stat">
          <div>
            <div class="muted">Average completion</div>
            <strong><?= h($avgRating) ?></strong>
          </div>
        </div>
      </div>
  </div>
</section>

<!-- COURSES -->
<section class="section">
  <div class="card">
    <h2>Courses</h2>
    <?php if ($courses): ?>
      <?php foreach ($courses as $course): ?>
        <div class="course">
          <strong><?= h($course['code']) ?> - <?= h($course['title']) ?></strong>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="notice">
        <strong>No courses are assigned yet.</strong>
        <p class="muted" style="margin-bottom: 0;">This area will populate once your role is linked to courses.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

      <div class="footer">
  Powered by ASTU SFES · <?= (int) $unreadNotifications ?> unread notifications
  </div>

</div>
</main>

<script src="assets/app.js"></script>
</body>
</html>
