<?php ob_start(); ?>
<?php
require_once __DIR__ . '/config.php';
require_login();

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
  <title>FAQ | <?= h(APP_BRAND) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body class="app-shell">
  <main class="main">
    <div class="page">
      <h1 style="display:flex;align-items:center;gap:0.5rem;">
        <span class="material-symbols-outlined" style="font-size:2rem;color:#2563eb;">help</span>
        Frequently Asked Questions
      </h1>

      <input type="text" id="faq-search" class="input" placeholder="Search questions..." aria-label="Search FAQ questions" style="margin-bottom:1.2rem;width:100%;max-width:400px;">

      <p id="faq-empty" class="muted" hidden>No questions match your search.</p>

      <div class="faq-list" id="faq-list">
        <?php foreach ($faqs as $i => $faq): ?>
          <div class="faq-item" data-question="<?= h(strtolower($faq['q'])) ?>">
            <button class="faq-question" type="button" aria-expanded="false" aria-controls="faq-answer-<?= $i ?>">
              <span class="material-symbols-outlined" style="vertical-align:middle;">expand_more</span>
              <strong>Q: <?= h($faq['q']) ?></strong>
            </button>
            <div class="faq-answer" id="faq-answer-<?= $i ?>" style="display:none;">
              <p style="margin:0.7rem 0 0.5rem 2.2rem;">A: <?= h($faq['a']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <a href="dashboard.php" class="btn btn--outline" style="margin-top:1.5rem;">Back to Dashboard</a>

      <script>
      const faqSearch = document.getElementById('faq-search');
      const emptyState = document.getElementById('faq-empty');
      const items = Array.from(document.querySelectorAll('.faq-item'));

      const closeAll = () => {
        document.querySelectorAll('.faq-answer').forEach((answer) => {
          answer.style.display = 'none';
        });
        document.querySelectorAll('.faq-question').forEach((button) => {
          button.setAttribute('aria-expanded', 'false');
        });
      };

      document.querySelectorAll('.faq-question').forEach((btn) => {
        btn.addEventListener('click', function () {
          const answer = this.parentElement.querySelector('.faq-answer');
          const expanded = this.getAttribute('aria-expanded') === 'true';
          closeAll();
          if (!expanded && answer) {
            answer.style.display = 'block';
            this.setAttribute('aria-expanded', 'true');
          }
        });
      });

      const applySearch = () => {
        const val = faqSearch.value.trim().toLowerCase();
        let visibleCount = 0;

        items.forEach((item) => {
          const visible = item.dataset.question.includes(val);
          item.hidden = !visible;
          if (visible) visibleCount += 1;
        });

        emptyState.hidden = visibleCount > 0;
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

      faqSearch.addEventListener('input', applySearch);
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
