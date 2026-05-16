<?php
require_once __DIR__ . '/../config.php';

$user = require_role('admin');
$page = max(1, request_int('page', 1));
$perPage = 12;
$search = request_string('q');
$audit = audit_logs_for_page(1, 500);
$rows = $audit['rows'];

if ($search !== '') {
    $rows = array_values(array_filter($rows, fn(array $row) => stripos($row['action'], $search) !== false || stripos((string) ($row['actor_name'] ?? ''), $search) !== false || stripos((string) ($row['entity_type'] ?? ''), $search) !== false));
}

$pagination = pagination_meta(count($rows), $page, $perPage);
$rows = array_slice($rows, ($pagination['page'] - 1) * $pagination['per_page'], $pagination['per_page']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activity Log | <?= h(APP_BRAND) ?></title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="app-shell">
  <main class="main">
    <div class="page">
      <section class="hero">
        <div class="hero__eyebrow">Audit trail</div>
        <h1 class="hero__title">User activity log</h1>
        <p class="hero__lead">Every admin action and major workflow event is recorded for accountability.</p>
      </section>

      <section class="section card" style="margin-top: 1rem;">
        <form method="get" class="toolbar">
          <div class="field" style="flex: 1; min-width: 14rem;">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="<?= h($search) ?>" placeholder="Search actions, users, or entities">
          </div>
          <div style="align-self: end;">
            <button class="btn btn--primary" type="submit">Filter</button>
          </div>
          <?php if ($search !== ''): ?>
            <div style="align-self: end;">
              <a class="btn btn--outline" href="activity_log.php">Reset</a>
            </div>
          <?php endif; ?>
        </form>

        <div class="activity-list">
          <?php foreach ($rows as $entry): ?>
            <div class="activity">
              <div class="avatar" style="width: 2.1rem; height: 2.1rem; font-size: 0.75rem;">L</div>
              <div>
                <strong><?= h($entry['action']) ?></strong>
                <div class="muted"><?= h($entry['actor_name'] ?? 'System') ?> · <?= h($entry['entity_type']) ?><?= !empty($entry['entity_id']) ? ' #' . (int) $entry['entity_id'] : '' ?></div>
                <div class="muted"><?= h(format_time($entry['created_at'])) ?></div>
                <?php if (!empty($entry['metadata'])): ?>
                  <div class="muted"><?= h((string) $entry['metadata']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <div class="notice">
              <strong>No activity records match the filters.</strong>
              <p class="muted" style="margin-bottom: 0;">Try a broader search or clear the filter.</p>
            </div>
          <?php endif; ?>
        </div>

        <div class="row" style="justify-content: space-between; margin-top: 1rem; flex-wrap: wrap;">
          <span class="muted">Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['pages'] ?></span>
          <div class="row">
            <a class="btn btn--outline btn--sm" href="<?= $pagination['has_prev'] ? 'activity_log.php?' . h(query_string(['q' => $search, 'page' => $pagination['prev']])) : '#' ?>">Previous</a>
            <a class="btn btn--outline btn--sm" href="<?= $pagination['has_next'] ? 'activity_log.php?' . h(query_string(['q' => $search, 'page' => $pagination['next']])) : '#' ?>">Next</a>
          </div>
        </div>

        <a href="users.php" class="btn btn--outline" style="margin-top:1.5rem;">Back to Admin Console</a>
      </section>
    </div>
  </main>
  <!-- ADDED: Safe UX enhancements (non-breaking, no logic changes) -->
<script>
(function () {

  // 🔍 Auto-focus search input for faster filtering
  const searchInput = document.getElementById('q');
  if (searchInput && !searchInput.value) {
    searchInput.focus();
  }

  // ⬆️ Scroll to top on pagination click (better UX)
  document.querySelectorAll('a.btn').forEach(link => {
    if (link.textContent.includes('Next') || link.textContent.includes('Previous')) {
      link.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }
  });

  // 📋 Click to copy activity text (admin convenience)
  document.querySelectorAll('.activity').forEach(item => {
    item.addEventListener('click', function () {
      const text = this.innerText.trim();

      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).catch(() => {});
      }
    });
  });

})();
</script>
</body>
</html>
