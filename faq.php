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

      <input type="text" id="faq-search" class="input" placeholder="Search questions..." style="margin-bottom:1.2rem;width:100%;max-width:400px;">

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
      // FAQ accordion (ORIGINAL - unchanged)
      document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', function() {
          const answer = this.parentElement.querySelector('.faq-answer');
          const expanded = this.getAttribute('aria-expanded') === 'true';
          document.querySelectorAll('.faq-answer').forEach(a => a.style.display = 'none');
          document.querySelectorAll('.faq-question').forEach(b => b.setAttribute('aria-expanded', 'false'));
          if (!expanded) {
            answer.style.display = 'block';
            this.setAttribute('aria-expanded', 'true');
          }
        });
      });

      // FAQ search (ORIGINAL - unchanged)
      document.getElementById('faq-search').addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        document.querySelectorAll('.faq-item').forEach(item => {
          item.style.display = item.dataset.question.includes(val) ? '' : 'none';
        });
      });
      // Auto-expand first visible FAQ after search
      const faqSearch = document.getElementById('faq-search');
      if (faqSearch) {
        faqSearch.addEventListener('input', function () {
          const firstVisible = document.querySelector('.faq-item:not([style*="display: none"]) .faq-question');
          if (firstVisible) {
            firstVisible.click();
          }
        });
      }

      // Allow Enter key to toggle FAQ (accessibility)
      document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('keypress', function (e) {
          if (e.key === 'Enter') {
            this.click();
          }
        });
      });
      </script>
    </div>
  </main>
</body>
</html>