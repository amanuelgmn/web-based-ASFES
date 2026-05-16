<?php
// Load application configuration (constants, DB connection, helper functions, etc.)
require_once __DIR__ . '/config.php';

/**
 * Ensure only students can access this page.
 * require_role() checks the session and redirects unauthorized users automatically.
 */
$user = require_role('student');

/**
 * Load flash messages (success/error alerts) stored from the previous request.
 * These are consumed here and cleared from the session so they only show once.
 */
$flash = flash_get();

/**
 * Load data needed to render the page:
 * - $courses     : courses the student is enrolled in (used to populate the course dropdown)
 * - $allFeedback : every feedback entry the student is permitted to read
 * - $myHistory   : the six most recent entries shown in the "Recent Submissions" panel
 */
$courses = user_courses($user);
$allFeedback = accessible_feedback($user, all_feedback());
$myHistory = array_slice($allFeedback, 0, 6);

// Restore any previously saved form values from the session (set on validation failure)
// so the user does not lose their input after a redirect
$form = $_SESSION['feedback_form'] ?? [];
unset($_SESSION['feedback_form']); // Clear immediately after reading to avoid stale data

/**
 * Handle feedback form submission (POST only).
 * All GET requests simply render the page; no processing happens here.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token to prevent cross-site request forgery attacks
    verify_csrf();

    // --- Collect and sanitise form inputs ---

    $category  = (string) ($_POST['category']  ?? 'course');
    // course_id is optional — a blank string means "general department issue"
    $courseId  = isset($_POST['course_id']) && $_POST['course_id'] !== '' ? (int) $_POST['course_id'] : null;
    $subject   = trim((string) ($_POST['subject']  ?? ''));
    $message   = trim((string) ($_POST['message']  ?? ''));
    $severity  = (string) ($_POST['severity']  ?? 'Medium');
    // Checkbox: present = 1 (anonymous), absent = 0 (named)
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;

    // Persist form values to session so they can be restored if validation fails
    $_SESSION['feedback_form'] = [
        'category'  => $category,
        'course_id' => $courseId,
        'subject'   => $subject,
        'message'   => $message,
        'severity'  => $severity,
        'anonymous' => $anonymous,
    ];

    // Determine which staff role should receive this feedback based on its category
    // e.g. 'harassment' routes to the dean, 'course' routes to the instructor, etc.
    $targetRole = route_for_category($category);

    /**
     * Basic validation: both subject and message are required fields.
     * On failure, store an error flash message and redirect back to the form.
     */
    if ($subject === '' || $message === '') {
        flash_set('error', 'Please complete the subject and message fields.');
        header('Location: feedback.php');
        exit;
    }

    /**
     * Category-specific validation: course-related categories must have a course selected.
     * 'department' and 'harassment' do not require a course and are excluded from this check.
     */
    if (in_array($category, ['course', 'instructor', 'assessment'], true) && $courseId === null) {
        flash_set('error', 'Please choose a related course for this category.');
        header('Location: feedback.php');
        exit;
    }

    /**
     * Persist the validated feedback entry to the database.
     * Returns the newly created record (including its auto-generated ID).
     */
    $inserted = insert_feedback([
        'student_id'   => $user['id'],
        'course_id'    => $courseId,
        'category'     => $category,
        'target_role'  => $targetRole,
        'subject'      => $subject,
        'message'      => $message,
        'is_anonymous' => $anonymous,
        'severity'     => $severity,
    ]);

    /**
     * Process file attachments, if any were uploaded.
     * The input uses the array syntax (attachments[]) so multiple files can be sent
     * in a single request; PHP stores them as parallel arrays under $_FILES.
     */
    if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
        foreach ($_FILES['attachments']['name'] as $index => $name) {

            // Re-assemble each file's metadata from the parallel $_FILES arrays
            // into a single associative array that save_feedback_attachment() expects
            $file = [
                'name'     => $name,
                'type'     => $_FILES['attachments']['type'][$index]     ?? '',
                'tmp_name' => $_FILES['attachments']['tmp_name'][$index] ?? '',
                'error'    => $_FILES['attachments']['error'][$index]    ?? UPLOAD_ERR_NO_FILE,
                'size'     => $_FILES['attachments']['size'][$index]     ?? 0,
            ];

            // Store the file on disk and link the attachment record to the feedback entry
            save_feedback_attachment((int) $inserted['id'], (int) $user['id'], $file);
        }
    }

    /**
     * All processing succeeded — inform the student and send them back to the dashboard.
     * The session form data is cleared since the submission was successful.
     */
    flash_set('success', 'Your feedback was submitted and routed to the ' . role_label($targetRole) . '.');
    unset($_SESSION['feedback_form']);
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">

  <!-- Responsive layout — ensures the page scales correctly on mobile devices -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Page title — APP_BRAND is defined in config.php -->
  <title>Submit Feedback | <?= h(APP_BRAND) ?></title>

  <!-- Google Fonts: Lexend (headings/brand) and Inter (body text) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Material Symbols icon font (outlined style with variable weight/fill axes) -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Main application stylesheet -->
  <link rel="stylesheet" href="assets/style.css">
</head>

<body class="app-shell">

  <!-- ================================================================
       SIDEBAR NAVIGATION
       Fixed left-hand panel; .is-active highlights the current page link
       ================================================================ -->
  <aside class="sidebar">

    <!-- Brand / logo area -->
    <div class="sidebar__brand">
      <div class="sidebar__logo">
        <span class="material-symbols-outlined">school</span>
      </div>
      <div>
        <div class="sidebar__title brand">ASTU SFES</div>
        <div class="sidebar__subtitle">Academic Management</div>
      </div>
    </div>

    <!-- Primary navigation links -->
    <nav class="sidebar__nav">
      <a class="sidebar__link" href="dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span>Dashboard</span>
      </a>

      <!-- Current page — marked active so the sidebar highlights it -->
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

    <!-- Sidebar footer: quick shortcut back to the dashboard -->
    <div class="sidebar__footer">
      <a class="btn btn--primary btn--full row" style="justify-content: center;" href="dashboard.php">
        <span class="material-symbols-outlined">arrow_back</span>
        <span>Back to Dashboard</span>
      </a>
    </div>
  </aside>

  <!-- ================================================================
       TOP BAR
       Contains the mobile menu toggle, page title, and user profile chip
       ================================================================ -->
  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <!-- Hamburger button — toggles the sidebar on mobile viewports -->
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>

      <div class="topbar__title brand">Submit Feedback</div>
    </div>

    <!-- Right-hand profile chip: shows the logged-in student's name, role, and avatar initial -->
    <div class="topbar__actions">
      <div class="profile">
        <div class="profile__meta">
          <div class="profile__name"><?= h($user['name']) ?></div>
          <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
        </div>

        <!-- Avatar circle — displays the first letter of the user's name -->
        <div class="avatar">
          <?= h(strtoupper(mb_substr($user['name'], 0, 1))) ?>
        </div>
      </div>
    </div>
  </header>

  <!-- ================================================================
       MAIN CONTENT
       Two-column layout: feedback form card (left) + history card (right)
       ================================================================ -->
  <main class="main">
    <div class="page">

      <!-- FLASH MESSAGE
           Conditionally rendered alert banner for success/error notifications
           from the previous POST request (e.g. "Feedback submitted successfully") -->
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>">
          <?= h($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- HERO SECTION
           Introductory copy that orients the student before they fill in the form -->
      <section class="hero">
        <div class="hero__eyebrow">Student submission flow</div>
        <h1 class="hero__title">Submit secure academic feedback</h1>
        <p class="hero__lead">
          Choose issue type, target, and anonymity preference before submission.
        </p>
      </section>

      <!-- FEEDBACK FORM + HISTORY
           .feedback-shell is a two-column CSS grid defined in style.css -->
      <section class="section feedback-shell" style="margin-top: 1rem;">

        <!-- ============================================================
             FORM CARD
             ============================================================ -->
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">New Feedback</h2>
              <p class="section__sub">Stored securely and routed automatically.</p>
            </div>
            <!-- Badge indicates that anonymous submission is supported -->
            <span class="badge badge-indigo">Anonymous ready</span>
          </div>

          <!-- FEEDBACK FORM
               enctype="multipart/form-data" is required for file attachment uploads -->
          <form method="post" class="form-grid" enctype="multipart/form-data">

            <!-- CSRF hidden token — must be present on every POST form -->
            <?= csrf_input() ?>

            <!-- ROW 1: Course selector + Category selector -->
            <div class="split-grid">
              <div class="field">
                <label for="course_id">Related course</label>
                <select id="course_id" name="course_id">
                  <!-- Default option represents a general issue not tied to any course -->
                  <option value="">General department issue</option>
                  <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>" <?= (string) ($form['course_id'] ?? '') === (string) $course['id'] ? 'selected' : '' ?>>
                      <?= h($course['code'] . ' - ' . $course['title']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label for="category">Feedback category</label>
                <select id="category" name="category">
                  <!-- Each option's selected state is restored from $form on validation failure -->
                  <option value="course"      <?= ($form['category'] ?? 'course') === 'course'      ? 'selected' : '' ?>>Course quality</option>
                  <option value="instructor"  <?= ($form['category'] ?? '') === 'instructor'        ? 'selected' : '' ?>>Instructor performance</option>
                  <option value="assessment"  <?= ($form['category'] ?? '') === 'assessment'        ? 'selected' : '' ?>>Assessment fairness</option>
                  <option value="department"  <?= ($form['category'] ?? '') === 'department'        ? 'selected' : '' ?>>Department issue</option>
                  <option value="harassment"  <?= ($form['category'] ?? '') === 'harassment'        ? 'selected' : '' ?>>Sensitive / harassment case</option>
                </select>
              </div>
            </div>

            <!-- ROW 2: Subject input + Target authority display (read-only, driven by JS) -->
            <div class="split-grid">
              <div class="field">
                <label for="subject">Subject</label>
                <input id="subject" name="subject" type="text" value="<?= h((string) ($form['subject'] ?? '')) ?>" required>
              </div>

              <div class="field">
                <label>Target authority</label>
                <!-- Dynamically updated by assets/app.js when the category changes -->
                <div class="toggle">
                  <span class="material-symbols-outlined">send</span>
                  <strong id="target-role">Instructor</strong>
                </div>
              </div>
            </div>

            <!-- ROW 3: Severity selector + Anonymous checkbox toggle -->
            <div class="split-grid">
              <div class="field">
                <label for="severity">Severity</label>
                <select id="severity" name="severity">
                  <!-- Selected state restored from $form; falls back to "Medium" -->
                  <option <?= ($form['severity'] ?? 'Medium') === 'Low'      ? 'selected' : '' ?>>Low</option>
                  <option <?= ($form['severity'] ?? 'Medium') === 'Medium'   ? 'selected' : '' ?>>Medium</option>
                  <option <?= ($form['severity'] ?? 'Medium') === 'High'     ? 'selected' : '' ?>>High</option>
                  <option <?= ($form['severity'] ?? 'Medium') === 'Critical' ? 'selected' : '' ?>>Critical</option>
                </select>
              </div>

              <div class="field">
                <!-- Checkbox defaults to checked (anonymous) unless the student opts out -->
                <label class="toggle">
                  <input type="checkbox" name="anonymous" value="1" <?= ($form['anonymous'] ?? 1) ? 'checked' : '' ?>>
                  <span>Anonymous submission</span>
                </label>
              </div>
            </div>

            <!-- ROW 4: Full-width message textarea -->
            <div class="field">
              <label for="message">Feedback message</label>
              <textarea id="message" name="message" required><?= h((string) ($form['message'] ?? '')) ?></textarea>
            </div>

            <!-- ROW 5: Optional file attachments (multiple files allowed) -->
            <div class="field">
              <label for="attachments">Attachments</label>
              <input id="attachments" name="attachments[]" type="file" multiple>
            </div>

            <!-- SUBMIT BUTTON — aligned to the right -->
            <div class="row" style="justify-content: flex-end;">
              <button class="btn btn--primary" type="submit">Submit Feedback</button>
            </div>

          </form>
        </div><!-- /.card (form) -->

        <!-- ============================================================
             HISTORY CARD
             Shows the student's six most recent submissions at a glance
             ============================================================ -->
        <div class="card">
          <div class="section__head">
            <h2 class="section__title">Recent Submissions</h2>
          </div>

          <div class="activity-list">
            <?php foreach ($myHistory as $item): ?>
              <div class="feedback-item">
                <!-- Subject line as the primary identifier -->
                <strong><?= h($item['subject']) ?></strong>
                <!-- Human-readable category label (e.g. "Course quality") -->
                <div class="muted"><?= h(category_label($item['category'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div><!-- /.card (history) -->

      </section><!-- /.feedback-shell -->

      <!-- Page footer note — reminds the student of the confidentiality policy -->
      <div class="footer">
        Feedback remains confidential under university policy.
      </div>

    </div>
  </main>

  <!-- Main application JS: handles sidebar toggle, target-role label updates, etc. -->
  <script src="assets/app.js"></script>

  <!-- ================================================================
       INLINE ENHANCEMENT: Client-side draft auto-save
       Saves subject, message, and category to localStorage so the student
       does not lose their input if they accidentally navigate away.
       Runs in an IIFE to avoid polluting the global scope.
       NOTE: No structural or server-side logic changes — purely additive UX.
       ================================================================ -->
  <script>
/* Auto-save draft (local only, prevents data loss) */
(function () {
  const subject  = document.getElementById('subject');
  const message  = document.getElementById('message');
  const category = document.getElementById('category');

  // Bail out gracefully if any expected element is missing from the DOM
  if (!subject || !message || !category) return;

  // Restore previously saved draft values (only if the server has not pre-filled them)
  subject.value  = localStorage.getItem('fb_subject')  || subject.value;
  message.value  = localStorage.getItem('fb_message')  || message.value;
  category.value = localStorage.getItem('fb_category') || category.value;

  // Persist each field to localStorage whenever the user changes it
  subject.addEventListener('input',  () => localStorage.setItem('fb_subject',  subject.value));
  message.addEventListener('input',  () => localStorage.setItem('fb_message',  message.value));
  category.addEventListener('change',() => localStorage.setItem('fb_category', category.value));
})();
</script>

</body>
</html>