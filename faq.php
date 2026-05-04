<?php ob_start(); ?>
<?php
// Load application configuration (constants, DB connection, helpers, etc.)
require_once __DIR__ . '/config.php';

// Redirect unauthenticated users to the login page
require_login();

// Static FAQ data — each entry has a question ('q') and answer ('a')
// In a real app this could be pulled from a database table
$faqs = [
    ['q' => 'How do I submit feedback?', 'a' => 'Go to the Submit Feedback page and fill out the form.'],
    ['q' => 'Is my feedback anonymous?', 'a' => 'You can choose to submit feedback anonymously or with your name.'],
    ['q' => 'Who can see my feedback?', 'a' => 'Only authorized staff and instructors can view your feedback.'],
    ['q' => 'How do I update my profile?', 'a' => 'Visit the Profile page from the navigation menu.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <!-- Page title — APP_BRAND is defined in config.php -->
  <title>FAQ | <?= h(APP_BRAND) ?></title>
  <!-- Shared application stylesheet -->
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">
  <main class="main">
    <div class="page">

      <!-- Page heading with a Material Symbols icon -->
      <h1 style="display:flex;align-items:center;gap:0.5rem;">
        <span class="material-symbols-outlined" style="font-size:2rem;color:#2563eb;">help</span>
        Frequently Asked Questions
      </h1>

      <!-- Live search input — filters FAQ items as the user types -->
      <input type="text" id="faq-search" class="input" placeholder="Search questions..." aria-label="Search FAQ questions" style="margin-bottom:1.2rem;width:100%;max-width:400px;">

      <!-- Empty-state message shown when no FAQ items match the search query -->
      <p id="faq-empty" class="muted" hidden>No questions match your search.</p>

      <!-- Container for all FAQ accordion items -->
      <div class="faq-list" id="faq-list">

        <?php foreach ($faqs as $i => $faq): ?>
          <!--
            Each .faq-item wraps one question/answer pair.
            data-question holds a lowercase version of the question text
            so the JS search filter can do a simple string-includes check.
          -->
          <div class="faq-item" data-question="<?= h(strtolower($faq['q'])) ?>">

            <!-- Accordion toggle button — aria-expanded tracks open/closed state -->
            <button class="faq-question" type="button" aria-expanded="false" aria-controls="faq-answer-<?= $i ?>">
              <!-- Chevron icon rotates visually via CSS when the answer is open -->
              <span class="material-symbols-outlined" style="vertical-align:middle;">expand_more</span>
              <strong>Q: <?= h($faq['q']) ?></strong>
            </button>

            <!-- Answer panel — hidden by default, revealed on button click -->
            <div class="faq-answer" id="faq-answer-<?= $i ?>" style="display:none;">
              <p style="margin:0.7rem 0 0.5rem 2.2rem;">A: <?= h($faq['a']) ?></p>
            </div>

          </div>
        <?php endforeach; ?>

      </div><!-- /.faq-list -->

      <!-- Navigation link back to the main dashboard -->
      <a href="dashboard.php" class="btn btn--outline" style="margin-top:1.5rem;">Back to Dashboard</a>

      <script>
      // --- Element references ---
      const faqSearch  = document.getElementById('faq-search');
      const emptyState = document.getElementById('faq-empty');
      const items      = Array.from(document.querySelectorAll('.faq-item'));

      /**
       * closeAll — collapse every open answer panel and reset all
       * aria-expanded attributes back to "false".
       * Called before opening a new panel so only one is open at a time
       * (accordion / exclusive-expand behaviour).
       */
      const closeAll = () => {
        document.querySelectorAll('.faq-answer').forEach((answer) => {
          answer.style.display = 'none';
        });
        document.querySelectorAll('.faq-question').forEach((button) => {
          button.setAttribute('aria-expanded', 'false');
        });
      };

      // Attach click handlers to every accordion toggle button
      document.querySelectorAll('.faq-question').forEach((btn) => {
        btn.addEventListener('click', function () {
          const answer   = this.parentElement.querySelector('.faq-answer');
          // Capture current state before closeAll() resets it
          const expanded = this.getAttribute('aria-expanded') === 'true';

          // Collapse everything first (handles the case where another panel is open)
          closeAll();

          // If this panel was closed, open it; if it was already open, leave it closed
          if (!expanded && answer) {
            answer.style.display = 'block';
            this.setAttribute('aria-expanded', 'true');
          }
        });
      });

      /**
       * applySearch — filter visible FAQ items based on the current search input.
       * Hides items whose question text does not contain the query string,
       * shows the empty-state message when nothing matches, and auto-opens
       * the first visible item for quick keyboard-friendly browsing.
       */
      const applySearch = () => {
        const val = faqSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        items.forEach((item) => {
          // data-question already stores the lowercase question text (set in PHP)
          const visible = item.dataset.question.includes(val);
          item.hidden = !visible;
          if (visible) visibleCount += 1;
        });

        // Toggle the "no results" message
        emptyState.hidden = visibleCount > 0;

        // Auto-open the first matching item so users immediately see a result
        if (visibleCount > 0) {
          const firstVisible = document.querySelector('.faq-item:not([hidden]) .faq-question');
          if (firstVisible) {
            closeAll();
            firstVisible.setAttribute('aria-expanded', 'true');
            const answer = firstVisible.parentElement.querySelector('.faq-answer');
            if (answer) {
              answer.style.display = 'block';
            }
          }
        }
      };

      // Re-run the filter on every keystroke
      faqSearch.addEventListener('input', applySearch);

      // Allow users to clear the search box quickly by pressing Escape
      faqSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          faqSearch.value = '';
          applySearch();
        }
      });
      </script>
    </div>
  </main>
</body>
</html>