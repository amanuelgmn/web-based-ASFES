<?php
require_once __DIR__ . '/config.php';

$user = require_role('student');
$flash = flash_get();
$courses = user_courses($user);
$allFeedback = accessible_feedback($user, all_feedback());
$myHistory = array_slice($allFeedback, 0, 6);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = (string) ($_POST['category'] ?? 'course');
    $courseId = $_POST['course_id'] !== '' ? (int) $_POST['course_id'] : null;
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $severity = (string) ($_POST['severity'] ?? 'Medium');
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    $targetRole = route_for_category($category);

    if ($subject === '' || $message === '') {
        flash_set('error', 'Please complete the subject and message fields.');
        header('Location: feedback.php');
        exit;
    }

    insert_feedback([
        'student_id' => $user['id'],
        'course_id' => $courseId,
        'category' => $category,
        'target_role' => $targetRole,
        'subject' => $subject,
        'message' => $message,
        'is_anonymous' => $anonymous,
        'severity' => $severity,
    ]);

    flash_set('success', 'Your feedback was submitted and routed to the ' . role_label($targetRole) . '.');
    header('Location: dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Feedback | <?= h(APP_BRAND) ?></title>
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
      <a class="sidebar__link is-active" href="feedback.php"><span class="material-symbols-outlined">rate_review</span><span>Submit Feedback</span></a>
      <a class="sidebar__link" href="respond.php"><span class="material-symbols-outlined">forum</span><span>Responses</span></a>
      <a class="sidebar__link" href="report.php"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
      <a class="sidebar__link" href="logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </nav>
    <div class="sidebar__footer">
      <a class="btn btn--primary btn--full row" style="justify-content: center;" href="dashboard.php">
        <span class="material-symbols-outlined">arrow_back</span>
        <span>Back to Dashboard</span>
      </a>
    </div>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">Submit Feedback</div>
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
        <div class="hero__eyebrow">Student submission flow</div>
        <h1 class="hero__title">Submit secure academic feedback</h1>
        <p class="hero__lead">Choose the issue type, set the routing target, and decide whether your name should remain hidden from the receiving office.</p>
      </section>

      <section class="section feedback-shell" style="margin-top: 1rem;">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">New Feedback</h2>
              <p class="section__sub">All fields are stored in the PHP backend and routed automatically.</p>
            </div>
            <span class="badge badge-indigo">Anonymous ready</span>
          </div>

          <form method="post" class="form-grid">
            <div class="split-grid">
              <div class="field">
                <label for="course_id">Related course</label>
                <select id="course_id" name="course_id">
                  <option value="">General department issue</option>
                  <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= h($course['code'] . ' - ' . $course['title']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label for="category">Feedback category</label>
                <select id="category" name="category" data-feedback-category data-feedback-target="#target-role">
                  <option value="course">Course quality</option>
                  <option value="instructor">Instructor performance</option>
                  <option value="assessment">Assessment fairness</option>
                  <option value="department">Department issue</option>
                  <option value="harassment">Sensitive / harassment case</option>
                </select>
              </div>
            </div>

            <div class="split-grid">
              <div class="field">
                <label for="subject">Subject</label>
                <input id="subject" name="subject" type="text" placeholder="Short summary of the issue" required>
              </div>

              <div class="field">
                <label>Target authority</label>
                <div class="toggle">
                  <span class="material-symbols-outlined">send</span>
                  <strong id="target-role">Instructor</strong>
                </div>
              </div>
            </div>

            <div class="split-grid">
              <div class="field">
                <label for="severity">Severity</label>
                <select id="severity" name="severity">
                  <option>Low</option>
                  <option selected>Medium</option>
                  <option>High</option>
                  <option>Critical</option>
                </select>
              </div>

              <div class="field">
                <label>&nbsp;</label>
                <label class="toggle">
                  <input type="checkbox" name="anonymous" value="1" checked>
                  <span>Submit anonymously to receivers</span>
                </label>
              </div>
            </div>

            <div class="field">
              <label for="message">Feedback message</label>
              <textarea id="message" name="message" placeholder="Explain the issue, course context, and any details that would help the receiving office respond..." required></textarea>
              <div class="field__hint">Harassment-related feedback is routed only to Student Affairs.</div>
            </div>

            <div class="row" style="justify-content: flex-end; gap: 0.8rem; flex-wrap: wrap;">
              <a class="btn btn--ghost" href="dashboard.php">Cancel</a>
              <button class="btn btn--primary" type="submit">Submit Feedback</button>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Recent Submissions</h2>
              <p class="section__sub">Your latest entries and current status.</p>
            </div>
          </div>
          <div class="activity-list">
            <?php foreach ($myHistory as $item): ?>
              <div class="feedback-item">
                <div class="row" style="justify-content: space-between;">
                  <strong><?= h($item['subject']) ?></strong>
                  <span class="<?= h(status_badge_class($item['status'])) ?>"><?= h($item['status']) ?></span>
                </div>
                <div class="muted" style="margin-top: 0.35rem;">
                  <?= h(category_label($item['category'])) ?> · <?= h(route_label($item['target_role'])) ?>
                </div>
                <div class="muted" style="margin-top: 0.35rem;"><?= h(relative_time($item['created_at'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <div class="footer">Feedback remains subject to university privacy and ethics rules.</div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
