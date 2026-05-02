<?php
require_once __DIR__ . '/config.php';

$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);
$feedbackRows = all_feedback();
$accessible = $user['role'] === 'admin' ? $feedbackRows : accessible_feedback($user, $feedbackRows);
$flash = flash_get();

$query = request_string('q');
$filterStatus = request_string('status', 'all');
$filterCategory = request_string('category', 'all');
$filterTarget = request_string('target', 'all');
$fromDate = request_string('from');
$toDate = request_string('to');
$page = max(1, request_int('page', 1));
$perPage = 6;

$filtered = array_values(array_filter($accessible, function (array $row) use ($query, $filterStatus, $filterCategory, $filterTarget, $fromDate, $toDate): bool {
    if ($query !== '' && stripos($row['subject'], $query) === false && stripos($row['message'], $query) === false && stripos((string) ($row['course_code'] ?? ''), $query) === false) {
        return false;
    }

    if ($filterStatus !== 'all' && $row['status'] !== $filterStatus) {
        return false;
    }

    if ($filterCategory !== 'all' && $row['category'] !== $filterCategory) {
        return false;
    }

    if ($filterTarget !== 'all' && $row['target_role'] !== $filterTarget) {
        return false;
    }

    $created = strtotime((string) $row['created_at']);
    if ($fromDate !== '' && $created < strtotime($fromDate . ' 00:00:00')) {
        return false;
    }
    if ($toDate !== '' && $created > strtotime($toDate . ' 23:59:59')) {
        return false;
    }

    return true;
}));

$summary = feedback_summary($filtered);
$byStatus = count_by($filtered, 'status');
$byCategory = count_by($filtered, 'category');
$topCourses = [];
foreach ($filtered as $row) {
    $key = $row['course_code'] ?? 'General';
    $topCourses[$key] = ($topCourses[$key] ?? 0) + 1;
}
arsort($topCourses);
$topCourses = array_slice($topCourses, 0, 6, true);

$pagination = pagination_meta(count($filtered), $page, $perPage);
$visibleRows = array_slice($filtered, ($pagination['page'] - 1) * $pagination['per_page'], $pagination['per_page']);

$averageResponseSeconds = null;
$responseSamples = array_values(array_filter(array_map(fn(array $row) => response_time_seconds($row), $filtered), fn($value) => $value !== null));
if ($responseSamples) {
    $averageResponseSeconds = (int) round(array_sum($responseSamples) / count($responseSamples));
}

$overdueCount = count(array_filter($filtered, fn(array $row) => sla_state($row)['state'] === 'Overdue'));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asfes-report.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Subject', 'Category', 'Target', 'Status', 'Submitted', 'Course', 'SLA Due']);
    foreach ($filtered as $row) {
        fputcsv($output, [
            $row['subject'],
            category_label($row['category']),
            route_label($row['target_role']),
            $row['status'],
            format_time($row['created_at']),
            $row['course_code'] ?? 'General issue',
            format_time($row['sla_due_at'] ?? null),
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
          <a class="btn btn--primary" href="report.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>">Export Summary</a>
          <a class="btn btn--outline" href="feedback.php">Create Evaluation</a>
        </div>
      </section>

      <section class="section grid-stats grid-stats--4">
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
        <div class="card stat">
          <div class="stat__top">
            <div class="stat__label">Overdue</div>
            <div class="stat__chip" style="background: rgba(244, 63, 94, 0.14); color: #be123c;"><span class="material-symbols-outlined">warning</span></div>
          </div>
          <div>
            <div class="stat__value"><?= (int) $overdueCount ?></div>
            <div class="stat__meta">Past SLA due date</div>
          </div>
        </div>
      </section>

      <section class="section card">
        <form method="get" class="toolbar">
          <div class="field" style="flex: 1; min-width: 14rem;">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="<?= h($query) ?>" placeholder="Search subject, message, or course">
          </div>
          <div class="field" style="min-width: 10rem;">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All</option>
              <?php foreach (status_options() as $option): ?>
                <option value="<?= h($option) ?>" <?= $filterStatus === $option ? 'selected' : '' ?>><?= h($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field" style="min-width: 12rem;">
            <label for="category">Category</label>
            <select id="category" name="category">
              <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>All</option>
              <option value="course" <?= $filterCategory === 'course' ? 'selected' : '' ?>>Course quality</option>
              <option value="instructor" <?= $filterCategory === 'instructor' ? 'selected' : '' ?>>Instructor performance</option>
              <option value="assessment" <?= $filterCategory === 'assessment' ? 'selected' : '' ?>>Assessment fairness</option>
              <option value="department" <?= $filterCategory === 'department' ? 'selected' : '' ?>>Department issue</option>
              <option value="harassment" <?= $filterCategory === 'harassment' ? 'selected' : '' ?>>Sensitive / harassment case</option>
            </select>
          </div>
          <div class="field" style="min-width: 12rem;">
            <label for="target">Target</label>
            <select id="target" name="target">
              <option value="all" <?= $filterTarget === 'all' ? 'selected' : '' ?>>All</option>
              <option value="instructor" <?= $filterTarget === 'instructor' ? 'selected' : '' ?>>Instructor</option>
              <option value="department" <?= $filterTarget === 'department' ? 'selected' : '' ?>>Department</option>
              <option value="student_affairs" <?= $filterTarget === 'student_affairs' ? 'selected' : '' ?>>Student Affairs</option>
            </select>
          </div>
          <div class="field" style="min-width: 11rem;">
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="<?= h($fromDate) ?>">
          </div>
          <div class="field" style="min-width: 11rem;">
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="<?= h($toDate) ?>">
          </div>
          <div style="align-self: end;">
            <button class="btn btn--primary" type="submit">Apply</button>
          </div>
        </form>
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
                  <strong><?= (int) count(array_filter($filtered, fn($row) => $row['target_role'] === $key)) ?></strong>
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
              <h2 class="section__title">Response timing</h2>
              <p class="section__sub">Average time from submission to latest response.</p>
            </div>
          </div>
          <div class="stat-list">
            <div class="mini-stat">
              <div>
                <div class="muted">Average response time</div>
                <strong><?= $averageResponseSeconds !== null ? format_duration_seconds($averageResponseSeconds) : 'No responses yet' ?></strong>
              </div>
            </div>
            <div class="mini-stat">
              <div>
                <div class="muted">Items with responses</div>
                <strong><?= (int) count($responseSamples) ?></strong>
              </div>
            </div>
            <div class="mini-stat">
              <div>
                <div class="muted">Category mix</div>
                <strong><?= count($byCategory) ?> active categories</strong>
              </div>
            </div>
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
            <th>SLA</th>
          </tr>
        </thead>
        <tbody>
            <?php foreach ($visibleRows as $row): ?>
              <tr>
                <td>
                  <strong><?= h($row['subject']) ?></strong>
                  <div class="muted"><?= h($row['course_code'] ?? 'General issue') ?></div>
                </td>
                <td><?= h(category_label($row['category'])) ?></td>
                <td><?= h(route_label($row['target_role'])) ?></td>
                <td><span class="<?= h(status_badge_class($row['status'])) ?>"><?= h($row['status']) ?></span></td>
                <td><?= h(format_time($row['created_at'])) ?></td>
                <td><span class="<?= h(sla_state($row)['class']) ?>"><?= h(sla_state($row)['state']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$visibleRows): ?>
              <tr>
                <td colspan="6" class="muted">No rows match the current report filters.</td>
              </tr>
            <?php endif; ?>
        </tbody>
      </table>

      <div class="row" style="justify-content: space-between; margin-top: 1rem; flex-wrap: wrap;">
        <span class="muted">Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['pages'] ?></span>
        <div class="row">
          <a class="btn btn--outline btn--sm" href="<?= $pagination['has_prev'] ? 'report.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['prev']])) : '#' ?>">Previous</a>
          <a class="btn btn--outline btn--sm" href="<?= $pagination['has_next'] ? 'report.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['next']])) : '#' ?>">Next</a>
        </div>
      </div>
      </section>

      <div class="footer">Reports are generated from the MySQL-backed PHP store and filtered by role.</div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
