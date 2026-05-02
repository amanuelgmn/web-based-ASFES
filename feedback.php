<?php
require_once __DIR__ . '/config.php';

/**
 * Ensure only students can access this page
 */
$user = require_role('student');

/**
 * Load flash messages (success/error alerts)
 */
$flash = flash_get();

/**
 * Load data needed for the page:
 * - student courses
 * - all accessible feedback
 * - recent history (latest 6 items)
 */
$courses = user_courses($user);
$allFeedback = accessible_feedback($user, all_feedback());
$myHistory = array_slice($allFeedback, 0, 6);

/**
 * Handle feedback form submission
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Collect form inputs
    $category = (string) ($_POST['category'] ?? 'course');
    $courseId = isset($_POST['course_id']) && $_POST['course_id'] !== '' ? (int) $_POST['course_id'] : null;
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $severity = (string) ($_POST['severity'] ?? 'Medium');
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;

    // Determine routing target based on category
    $targetRole = route_for_category($category);

    /**
     * Basic validation: subject and message are required
     */
    if ($subject === '' || $message === '') {
        flash_set('error', 'Please complete the subject and message fields.');
        header('Location: feedback.php');
        exit;
    }

    /**
     * Some categories require course selection
     */
    if (in_array($category, ['course', 'instructor', 'assessment'], true) && $courseId === null) {
        flash_set('error', 'Please choose a related course for this category.');
        header('Location: feedback.php');
        exit;
    }

    /**
     * Insert feedback into database
     */
    $inserted = insert_feedback([
        'student_id' => $user['id'],
        'course_id' => $courseId,
        'category' => $category,
        'target_role' => $targetRole,
        'subject' => $subject,
        'message' => $message,
        'is_anonymous' => $anonymous,
        'severity' => $severity,
    ]);

    /**
     * Handle file attachments if provided
     */
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        foreach ($_FILES['attachments']['name'] as $index => $name) {

            // Normalize each uploaded file entry
            $file = [
                'name' => $name,
                'type' => $_FILES['attachments']['type'][$index] ?? '',
                'tmp_name' => $_FILES['attachments']['tmp_name'][$index] ?? '',
                'error' => $_FILES['attachments']['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['attachments']['size'][$index] ?? 0,
            ];

            // Save attachment linked to feedback
            save_feedback_attachment((int) $inserted['id'], (int) $user['id'], $file);
        }
    }

    /**
     * Success message and redirect
     */
    flash_set('success', 'Your feedback was submitted and routed to the ' . role_label($targetRole) . '.');
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">

  <!-- Responsive layout -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Page title -->
  <title>Submit Feedback | <?= h(APP_BRAND) ?></title>

  <!-- Fonts and icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Main stylesheet -->
  <link rel="stylesheet" href="assets/style.css">
</head>

<body class="app-shell">

  <!-- SIDEBAR NAVIGATION -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="sidebar__logo">
        <span class="material-symbols-outlined">school</span>
      </div>
      <div>
        <div class="sidebar__title brand">ASTU SFES</div>
        <div class="sidebar__subtitle">Academic Management</div>
      </div>
    </div>

    <!-- Navigation links -->
    <nav class="sidebar__nav">
      <a class="sidebar__link" href="dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span>Dashboard</span>
      </a>

      <a class="sidebar__link is-active" href="feedback.php">
        <span class="material-symbols-outlined">rate_review</span>
        <span>Submit Feedback</span>
      </a>

      <a class="sidebar__link" href="respond.php">
        <span class="material-symbols-outlined">forum</span>
        <span>Responses</span>
      </a>

      <a class="sidebar__link" href="report.php">
        <span class="material-symbols-outlined">analytics</span>
        <span>Reports</span>
      </a>

      <a class="sidebar__link" href="logout.php">
        <span class="material-symbols-outlined">logout</span>
        <span>Logout</span>
      </a>
    </nav>

    <!-- Back button -->
    <div class="sidebar__footer">
      <a class="btn btn--primary btn--full row" style="justify-content: center;" href="dashboard.php">
        <span class="material-symbols-outlined">arrow_back</span>
        <span>Back to Dashboard</span>
      </a>
    </div>
  </aside>

  <!-- TOP BAR -->
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

        <div class="avatar">
          <?= h(strtoupper(mb_substr($user['name'], 0, 1))) ?>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN CONTENT -->
  <main class="main">
    <div class="page">

      <!-- FLASH MESSAGE -->
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>">
          <?= h($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- HERO SECTION -->
      <section class="hero">
        <div class="hero__eyebrow">Student submission flow</div>
        <h1 class="hero__title">Submit secure academic feedback</h1>
        <p class="hero__lead">
          Choose issue type, target, and anonymity preference before submission.
        </p>
      </section>

      <!-- FEEDBACK FORM + HISTORY -->
      <section class="section feedback-shell" style="margin-top: 1rem;">

        <!-- FORM CARD -->
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">New Feedback</h2>
              <p class="section__sub">Stored securely and routed automatically.</p>
            </div>
            <span class="badge badge-indigo">Anonymous ready</span>
          </div>

          <!-- FEEDBACK FORM -->
          <form method="post" class="form-grid" enctype="multipart/form-data">
            <?= csrf_input() ?>

            <!-- COURSE + CATEGORY -->
            <div class="split-grid">
              <div class="field">
                <label for="course_id">Related course</label>
                <select id="course_id" name="course_id">
                  <option value="">General department issue</option>
                  <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>">
                      <?= h($course['code'] . ' - ' . $course['title']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label for="category">Feedback category</label>
                <select id="category" name="category">
                  <option value="course">Course quality</option>
                  <option value="instructor">Instructor performance</option>
                  <option value="assessment">Assessment fairness</option>
                  <option value="department">Department issue</option>
                  <option value="harassment">Sensitive / harassment case</option>
                </select>
              </div>
            </div>

            <!-- SUBJECT + TARGET -->
            <div class="split-grid">
              <div class="field">
                <label for="subject">Subject</label>
                <input id="subject" name="subject" type="text" required>
              </div>

              <div class="field">
                <label>Target authority</label>
                <div class="toggle">
                  <span class="material-symbols-outlined">send</span>
                  <strong id="target-role">Instructor</strong>
                </div>
              </div>
            </div>

            <!-- SEVERITY + ANONYMITY -->
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
                <label class="toggle">
                  <input type="checkbox" name="anonymous" value="1" checked>
                  <span>Anonymous submission</span>
                </label>
              </div>
            </div>

            <!-- MESSAGE -->
            <div class="field">
              <label for="message">Feedback message</label>
              <textarea id="message" name="message" required></textarea>
            </div>

            <!-- ATTACHMENTS -->
            <div class="field">
              <label for="attachments">Attachments</label>
              <input id="attachments" name="attachments[]" type="file" multiple>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="row" style="justify-content: flex-end;">
              <button class="btn btn--primary" type="submit">Submit Feedback</button>
            </div>
          </form>
        </div>

        <!-- HISTORY CARD -->
        <div class="card">
          <div class="section__head">
            <h2 class="section__title">Recent Submissions</h2>
          </div>

          <div class="activity-list">
            <?php foreach ($myHistory as $item): ?>
              <div class="feedback-item">
                <strong><?= h($item['subject']) ?></strong>
                <div class="muted"><?= h(category_label($item['category'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </section>

      <div class="footer">
        Feedback remains confidential under university policy.
      </div>

    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>