<?php
require_once __DIR__ . '/config.php';

$user = require_login();
$feedbackRows = all_feedback();
$myFeedback = accessible_feedback($user, $feedbackRows);
$metrics = dashboard_metrics($user, $feedbackRows);
$courses = user_courses($user);
$flash = flash_get();
$recentFeedback = array_slice($myFeedback, 0, 6);
$totalResponses = response_total();
$openCases = count(array_filter($myFeedback, fn($row) => $row['status'] !== 'Closed'));
$completionRate = $myFeedback ? round((count(array_filter($myFeedback, fn($row) => $row['status'] === 'Closed')) / count($myFeedback)) * 100) : 0;
$avgRating = match ($user['role']) {
    'student' => '4.8',
    'instructor' => '4.6',
    'department' => '4.4',
    'student_affairs' => '4.7',
    'admin' => '4.82',
    default => '4.5',
};


// Enhanced navigation with new features
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
      <a class="btn btn--primary btn--full row" style="justify-content: center;" href="feedback.php">
        <span class="material-symbols-outlined">add</span>
        <span>New Evaluation</span>
      </a>
    </div>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">ASTU SFES</div>
    </div>

    <div class="search">
      <span class="material-symbols-outlined">search</span>
      <input type="text" placeholder="Search courses, reports, or feedback...">
    </div>

    <div class="topbar__actions">
      <a class="icon-btn" href="notifications.php" title="Notifications"><span class="material-symbols-outlined">notifications</span></a>
      <a class="icon-btn" href="faq.php" title="FAQ"><span class="material-symbols-outlined">help</span></a>
      <a class="profile" href="profile.php" title="Profile">
        <div class="profile__meta">
          <div class="profile__name"><?= h($user['name']) ?></div>
          <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
        </div>
        <div class="avatar"><?= h(strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
      </a>
    </div>
  </header>

  <main class="main">
    <div class="page">
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endif; ?>

      <section class="hero">
        <div class="hero__eyebrow">🎉 <?= h(route_label($user['role'])) ?> | <a href="notifications.php" style="color:#2563eb;">View Notifications</a></div>
        <h1 class="hero__title">
          <?php if ($user['role'] === 'student'): ?>
            Welcome back, <?= h($user['name']) ?>! Ready to make your voice heard?
          <?php elseif ($user['role'] === 'instructor'): ?>
            Instructor workspace for <?= h($user['name']) ?>. Inspire, review, and connect!
          <?php elseif ($user['role'] === 'department'): ?>
            Department oversight and academic review. Shape the future!
          <?php elseif ($user['role'] === 'student_affairs'): ?>
            Confidential case handling for Student Affairs. Support every student.
          <?php else: ?>
            Administrative control center for ASFES. Lead with insight!
          <?php endif; ?>
        </h1>
        <p class="hero__lead">
          <?php if ($user['role'] === 'student'): ?>
            Submit academic feedback, track responses, and keep your concerns organized in one secure place. <a href="faq.php">Need help?</a>
          <?php elseif ($user['role'] === 'instructor'): ?>
            Review structured course feedback, reply to students, and keep feedback status updated without losing privacy. <a href="faq.php">See FAQ</a>
          <?php elseif ($user['role'] === 'department'): ?>
            Monitor course quality, handle complaints, and manage anonymous issue routing for department-level follow up. <a href="faq.php">See FAQ</a>
          <?php elseif ($user['role'] === 'student_affairs'): ?>
            Review sensitive reports with confidentiality, capture follow-up notes, and protect student privacy. <a href="faq.php">See FAQ</a>
          <?php else: ?>
            Monitor campus-wide trends, manage users, and generate reports that support academic quality improvement. <a href="faq.php">See FAQ</a>
          <?php endif; ?>
        </p>
        <div class="hero__actions">
          <a class="btn btn--primary" href="<?= h($primaryAction[0]) ?>"><?= h($primaryAction[1]) ?></a>
          <a class="btn btn--outline" href="<?= h($secondaryAction[0]) ?>"><?= h($secondaryAction[1]) ?></a>
        </div>
      </section>

      <section class="section" id="courses">
        <div class="grid-stats grid-stats--4">
          <div class="card stat">
            <div class="stat__top">
              <div class="stat__chip" style="background: rgba(37, 99, 235, 0.1); color: #2563eb;"><span class="material-symbols-outlined">insights</span></div>
              <div class="stat__label">Visible</div>
            </div>
            <div>
              <div class="stat__value"><?= count($myFeedback) ?></div>
              <div class="stat__meta"><?= h($user['role'] === 'student' ? 'My submissions' : 'Assigned cases') ?></div>
            </div>
          </div>
          <div class="card stat">
            <div class="stat__top">
              <div class="stat__chip" style="background: rgba(34, 197, 94, 0.12); color: #15803d;"><span class="material-symbols-outlined">verified</span></div>
              <div class="stat__label">Responded</div>
            </div>
            <div>
              <div class="stat__value"><?= $metrics['responded'] ?></div>
              <div class="stat__meta">Feedback with replies</div>
            </div>
          </div>
          <div class="card stat">
            <div class="stat__top">
              <div class="stat__chip" style="background: rgba(245, 158, 11, 0.14); color: #b45309;"><span class="material-symbols-outlined">schedule</span></div>
              <div class="stat__label">Pending</div>
            </div>
            <div>
              <div class="stat__value"><?= $metrics['pending'] ?></div>
              <div class="stat__meta">Submitted or seen</div>
            </div>
          </div>
          <div class="card stat">
            <div class="stat__top">
              <div class="stat__chip" style="background: rgba(168, 85, 247, 0.12); color: #7c3aed;"><span class="material-symbols-outlined">star</span></div>
              <div class="stat__label">Average</div>
            </div>
            <div>
              <div class="stat__value"><?= h($avgRating) ?></div>
              <div class="stat__meta"><?= $completionRate ?>% completion rate</div>
            </div>
          </div>
        </div>
      </section>

      <section class="section split">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Recent Feedback</h2>
              <p class="section__sub">A live look at current submissions and responses.</p>
            </div>
            <div class="toolbar">
              <span class="pill"><?= h(role_label($user['role'])) ?></span>
            </div>
          </div>

          <div class="activity-list">
            <?php if (!$recentFeedback): ?>
              <div class="muted">No visible feedback yet.</div>
            <?php endif; ?>
            <?php foreach ($recentFeedback as $item): ?>
              <div class="activity">
                <div class="avatar" style="width: 2.2rem; height: 2.2rem; font-size: 0.8rem;"><?= h($item['is_anonymous'] ? 'A' : mb_substr($item['student_name'], 0, 1)) ?></div>
                <div style="flex: 1;">
                  <div class="row" style="justify-content: space-between; align-items: flex-start;">
                    <strong><?= h($item['subject']) ?></strong>
                    <span class="<?= h(status_badge_class($item['status'])) ?>"><?= h($item['status']) ?></span>
                  </div>
                  <div class="muted" style="margin-top: 0.25rem;">
                    <?= h(category_label($item['category'])) ?> · <?= h($item['course_code'] ?? 'General') ?> · <?= h(relative_time($item['created_at'])) ?>
                  </div>
                  <div style="margin-top: 0.35rem; color: #334155;">
                    <?= h(text_excerpt($item['message'], 135)) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Availability</h2>
              <p class="section__sub">Tracking the latest handling windows.</p>
            </div>
          </div>

          <div class="stat-list">
            <div class="mini-stat">
              <div>
                <div class="muted">Open cases</div>
                <strong><?= $openCases ?></strong>
              </div>
              <span class="badge badge-blue">Active</span>
            </div>
            <div class="mini-stat">
              <div>
                <div class="muted">Total responses</div>
                <strong><?= $totalResponses ?></strong>
              </div>
              <span class="badge badge-green">Logged</span>
            </div>
            <div class="mini-stat">
              <div style="flex: 1;">
                <div class="muted">Completion progress</div>
                <div class="progress" style="margin-top: 0.4rem;"><div class="progress__bar" style="width: <?= $completionRate ?>%;"></div></div>
              </div>
              <span style="min-width: 3rem; text-align: right; font-weight: 700;"><?= $completionRate ?>%</span>
            </div>
          </div>
        </div>
      </section>

      <section class="section">
        <div class="section__head">
          <div>
            <h2 class="section__title"><?= $user['role'] === 'student' ? 'My Courses' : 'Assigned Courses' ?></h2>
            <p class="section__sub">The academic context that drives feedback routing.</p>
          </div>
          <div class="toolbar">
            <span class="pill"><?= count($courses) ?> courses</span>
          </div>
        </div>

        <div class="course-grid">
          <?php foreach ($courses as $course): ?>
            <article class="card course-card">
              <div class="course-card__art"></div>
              <div>
                <div class="row" style="justify-content: space-between; align-items: flex-start;">
                  <span class="badge badge-indigo"><?= h($course['code']) ?></span>
                  <span class="badge badge-slate"><?= h($course['semester']) ?></span>
                </div>
                <h3 class="course-card__title" style="margin-top: 0.8rem;"><?= h($course['title']) ?></h3>
                <div class="course-card__meta" style="margin-top: 0.45rem;">
                  <?= h($course['instructor_name']) ?> · <?= h($course['department']) ?>
                </div>
                <div class="row" style="margin-top: 0.9rem; justify-content: space-between;">
                  <span class="badge badge-green"><?= h($course['status']) ?></span>
                  <a class="btn btn--secondary btn--sm" href="feedback.php">Give Feedback</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <?php if ($user['role'] !== 'student'): ?>
      <section class="section split">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Recent Activity</h2>
              <p class="section__sub">A compact audit trail for current work.</p>
            </div>
          </div>
          <div class="activity-list">
            <?php foreach (array_slice($myFeedback, 0, 4) as $item): ?>
              <div class="activity">
                <div class="<?= h(role_badge_class($user['role'])) ?>"><?= h(strtoupper(substr($user['role'], 0, 1))) ?></div>
                <div>
                  <strong><?= h(latest_response($item)) ?></strong>
                  <div class="muted" style="margin-top: 0.25rem;"><?= h(relative_time($item['updated_at'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Quick Controls</h2>
              <p class="section__sub">Shortcuts aligned to your current role.</p>
            </div>
          </div>
          <div class="form-grid">
            <a class="btn btn--primary btn--full" href="respond.php">Open Inbox</a>
            <a class="btn btn--outline btn--full" href="report.php">Open Reports</a>
            <a class="btn btn--secondary btn--full" href="logout.php">Sign Out</a>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <div class="footer">
        Powered by ASTU SFES · Privacy Policy · Terms of Service
      </div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
