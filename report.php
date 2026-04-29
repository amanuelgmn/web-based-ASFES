<?php
require_once __DIR__ . '/config.php';

$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);
$feedbackRows = all_feedback();
$accessible = $user['role'] === 'admin' ? $feedbackRows : accessible_feedback($user, $feedbackRows);
$flash = flash_get();

$summary = feedback_summary($accessible);
$byStatus = count_by($accessible, 'status');
$byCategory = count_by($accessible, 'category');
$topCourses = [];
foreach ($accessible as $row) {
    $key = $row['course_code'] ?? 'General';
    $topCourses[$key] = ($topCourses[$key] ?? 0) + 1;
}
arsort($topCourses);
$topCourses = array_slice($topCourses, 0, 6, true);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asfes-report.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Subject', 'Category', 'Target', 'Status', 'Submitted', 'Course']);
    foreach ($accessible as $row) {
        fputcsv($output, [
            $row['subject'],
            category_label($row['category']),
            route_label($row['target_role']),
            $row['status'],
            format_time($row['created_at']),
            $row['course_code'] ?? 'General issue',
        ]);
    }
    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | <?= h(APP_BRAND) ?></title>
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
      <a class="sidebar__link" href="respond.php"><span class="material-symbols-outlined">forum</span><span>Review Inbox</span></a>
      <a class="sidebar__link is-active" href="report.php"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
      <a class="sidebar__link" href="logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </nav>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">Reports &amp; Analytics</div>
    </div>
    <div class="topbar__actions">
      <div class="profile">
        <div class="profile__meta">
          <div class="profile__name"><?= h($user['name']) ?></div>
          <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
        </div>
        <div class="avatar"><?= h(strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
      </div>
    </div>
  </header>

  <main class="main">
    <div class="page">
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endif; ?>

      <section class="hero">
        <div class="hero__eyebrow">Institutional oversight</div>
        <h1 class="hero__title">Reports &amp; analytics</h1>
        <p class="hero__lead">Use aggregated feedback to spot patterns, monitor completion, and support academic quality improvement.</p>
        <div class="hero__actions">
          <a class="btn btn--primary" href="report.php?export=csv">Export Summary</a>
          <a class="btn btn--outline" href="feedback.php">Create Evaluation</a>
        </div>
      </section>

      <section class="section grid-stats grid-stats--3">
        <div class="card stat">
          <div class="stat__top">
            <div class="stat__label">Total Evaluations</div>
            <div class="stat__chip" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;"><span class="material-symbols-outlined">groups</span></div>
          </div>
          <div>
            <div class="stat__value"><?= $summary['total'] ?></div>
            <div class="stat__meta">Visible across your role</div>
          </div>
        </div>
        <div class="card stat">
          <div class="stat__top">
            <div class="stat__label">Completion Rate</div>
            <div class="stat__chip" style="background: rgba(34, 197, 94, 0.12); color: #15803d;"><span class="material-symbols-outlined">verified</span></div>
          </div>
          <div>
            <div class="stat__value"><?= $summary['total'] ? round(($summary['closed'] / $summary['total']) * 100) : 0 ?>%</div>
            <div class="stat__meta">Closed feedback ratio</div>
          </div>
        </div>
        <div class="card stat">
          <div class="stat__top">
            <div class="stat__label">Open Cases</div>
            <div class="stat__chip" style="background: rgba(245, 158, 11, 0.14); color: #b45309;"><span class="material-symbols-outlined">schedule</span></div>
          </div>
          <div>
            <div class="stat__value"><?= $summary['submitted'] + $summary['seen'] ?></div>
            <div class="stat__meta">Awaiting final resolution</div>
          </div>
        </div>
      </section>

      <section class="section split">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Status overview</h2>
              <p class="section__sub">A simple visual breakdown of feedback handling.</p>
            </div>
          </div>
          <div class="chart">
            <?php foreach (['Submitted', 'Seen', 'Responded', 'Closed'] as $status): ?>
              <div class="chart__bar">
                <div class="chart__label"><?= h($status) ?></div>
                <div class="chart__track">
                  <div class="chart__fill" style="width: <?= $summary['total'] ? round((($byStatus[$status] ?? 0) / $summary['total']) * 100) : 0 ?>%;"></div>
                </div>
                <strong style="width: 3rem; text-align: right;"><?= (int) ($byStatus[$status] ?? 0) ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Target routing</h2>
              <p class="section__sub">The same system logic that keeps Student Affairs private.</p>
            </div>
          </div>
          <div class="stat-list">
            <?php foreach (['instructor' => 'Instructor', 'department' => 'Department', 'student_affairs' => 'Student Affairs'] as $key => $label): ?>
              <div class="mini-stat">
                <div>
                  <div class="muted"><?= h($label) ?></div>
                  <strong><?= (int) count(array_filter($accessible, fn($row) => $row['target_role'] === $key)) ?></strong>
                </div>
                <span class="badge badge-indigo"><?= h(route_label($key)) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="section split">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Top courses</h2>
              <p class="section__sub">Which courses appear most often in the visible queue.</p>
            </div>
          </div>
          <div class="activity-list">
            <?php foreach ($topCourses as $course => $count): ?>
              <div class="mini-stat">
                <div>
                  <strong><?= h($course) ?></strong>
                  <div class="muted">Feedback entries</div>
                </div>
                <span class="badge badge-blue"><?= (int) $count ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Sentiment tags</h2>
              <p class="section__sub">A compact keyword strip for presentation slides.</p>
            </div>
          </div>
          <div class="keyword-list">
            <span class="keyword"><span class="material-symbols-outlined">thumb_up</span>Engaging Lectures</span>
            <span class="keyword"><span class="material-symbols-outlined">schedule</span>Timely Response</span>
            <span class="keyword"><span class="material-symbols-outlined">warning</span>Heavy Workload</span>
            <span class="keyword"><span class="material-symbols-outlined">book</span>Clear Objectives</span>
            <span class="keyword"><span class="material-symbols-outlined">shield</span>Confidential Review</span>
            <span class="keyword"><span class="material-symbols-outlined">event_note</span>Assessment Fairness</span>
          </div>
        </div>
      </section>

      <section class="section card">
        <div class="section__head">
          <div>
            <h2 class="section__title">Course evaluation details</h2>
            <p class="section__sub">A lightweight report table that supports the same layout language as the reference.</p>
          </div>
        </div>
        <table class="table">
          <thead>
            <tr>
              <th>Subject</th>
              <th>Category</th>
              <th>Target</th>
              <th>Status</th>
              <th>Submitted</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($accessible, 0, 6) as $row): ?>
              <tr>
                <td>
                  <strong><?= h($row['subject']) ?></strong>
                  <div class="muted"><?= h($row['course_code'] ?? 'General issue') ?></div>
                </td>
                <td><?= h(category_label($row['category'])) ?></td>
                <td><?= h(route_label($row['target_role'])) ?></td>
                <td><span class="<?= h(status_badge_class($row['status'])) ?>"><?= h($row['status']) ?></span></td>
                <td><?= h(format_time($row['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <div class="footer">Reports are generated from the MySQL-backed PHP store and filtered by role.</div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
