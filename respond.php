<?php
require_once __DIR__ . '/config.php';

$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);
$allRows = accessible_feedback($user, all_feedback());
$flash = flash_get();

$query = request_string('q');
$filterStatus = request_string('status', 'all');
$filterCategory = request_string('category', 'all');
$page = max(1, request_int('page', 1));
$perPage = 6;

$rows = array_values(array_filter($allRows, function (array $row) use ($query, $filterStatus, $filterCategory): bool {
    if ($query !== '' && stripos($row['subject'], $query) === false && stripos($row['message'], $query) === false && stripos((string) ($row['course_code'] ?? ''), $query) === false && stripos((string) ($row['student_name'] ?? ''), $query) === false) {
        return false;
    }

    if ($filterStatus !== 'all' && $row['status'] !== $filterStatus) {
        return false;
    }

    if ($filterCategory !== 'all' && $row['category'] !== $filterCategory) {
        return false;
    }

    return true;
}));

$pagination = pagination_meta(count($rows), $page, $perPage);
$selectedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($rows[0]['id'] ?? 0);
$selected = null;
foreach ($rows as $row) {
    if ((int) $row['id'] === $selectedId) {
        $selected = $row;
        break;
    }
}

if (!$selected && $rows) {
    $selected = $rows[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
    $status = normalize_status((string) ($_POST['status'] ?? 'Seen'));
    $message = trim((string) ($_POST['response'] ?? ''));

    $target = null;
    foreach ($allRows as $row) {
        if ((int) $row['id'] === $feedbackId) {
            $target = $row;
            break;
        }
    }

    if (!$target) {
        flash_set('error', 'You do not have access to that feedback item.');
        header('Location: respond.php');
        exit;
    }

    update_feedback_status($feedbackId, $status);

    if ($message !== '') {
        insert_response($feedbackId, (int) $user['id'], $message, $status);
    }

    flash_set('success', 'Feedback updated successfully.');
    header('Location: respond.php?id=' . $feedbackId);
    exit;
}

$responses = $selected ? feedback_responses((int) $selected['id']) : [];
$attachments = $selected ? feedback_attachments((int) $selected['id']) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inbox | <?= h(APP_BRAND) ?></title>
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
      <a class="sidebar__link is-active" href="respond.php"><span class="material-symbols-outlined">forum</span><span>Review Inbox</span></a>
      <a class="sidebar__link" href="report.php"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
      <a class="sidebar__link" href="logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </nav>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">Review Inbox</div>
    </div>
    <div class="topbar__actions">
      <div class="profile">
        <div class="profile__meta">
          <div class="profile__name"><?= h($user['name']) ?></div>
          <div class="profile__role"><?= h(role_label($user['role'])) ?></div>
        </div>
        <div class="avatar"><?= h(user_initial($user)) ?></div>
      </div>
    </div>
  </header>

  <main class="main">
    <div class="page">
      <?php if ($flash): ?>
        <div class="flash flash--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endif; ?>

      <section class="hero">
        <div class="hero__eyebrow"><?= h(route_label($user['role'])) ?></div>
        <h1 class="hero__title">Structured feedback handling and response workspace</h1>
        <p class="hero__lead">Review the queue, open a case, add a response, and update the status to keep the feedback lifecycle visible.</p>
      </section>

      <section class="section feedback-shell">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Queue</h2>
              <p class="section__sub"><?= count($rows) ?> matching feedback items</p>
            </div>
          </div>

          <form method="get" class="toolbar" style="margin-bottom: 1rem;">
            <div class="field" style="flex: 1; min-width: 14rem;">
              <label for="q">Search</label>
              <input id="q" name="q" type="text" value="<?= h($query) ?>" placeholder="Search subject, message, course, or student">
            </div>
            <div class="field" style="min-width: 12rem;">
              <label for="status">Status</label>
              <select id="status" name="status">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All</option>
                <?php foreach (status_options() as $option): ?>
                  <option value="<?= h($option) ?>" <?= $filterStatus === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field" style="min-width: 14rem;">
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
            <div style="align-self: end;">
              <button class="btn btn--primary" type="submit">Filter</button>
            </div>
          </form>

          <div class="feedback-list">
            <?php foreach (array_slice($rows, ($pagination['page'] - 1) * $pagination['per_page'], $pagination['per_page']) as $row): ?>
              <a class="feedback-item <?= $selected && (int) $selected['id'] === (int) $row['id'] ? 'is-active' : '' ?>" href="respond.php?id=<?= (int) $row['id'] ?>">
                <div class="row" style="justify-content: space-between; align-items: flex-start;">
                  <strong><?= h($row['subject']) ?></strong>
                  <span class="<?= h(status_badge_class($row['status'])) ?>"><?= h($row['status']) ?></span>
                </div>
                <div class="muted" style="margin-top: 0.35rem;">
                  <?= h(category_label($row['category'])) ?> · <?= h($row['course_code'] ?? 'General') ?> · <?= h(relative_time($row['created_at'])) ?>
                </div>
                <div class="muted" style="margin-top: 0.35rem;">
                  <?= h($row['is_anonymous'] ? 'Anonymous student' : $row['student_name']) ?>
                </div>
                <div class="muted" style="margin-top: 0.35rem;">
                  SLA: <?= h(sla_state($row)['state']) ?> · Due <?= h(format_time($row['sla_due_at'] ?? null)) ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="row" style="justify-content: space-between; margin-top: 1rem; flex-wrap: wrap;">
            <span class="muted">Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['pages'] ?></span>
            <div class="row">
              <a class="btn btn--outline btn--sm" href="<?= $pagination['has_prev'] ? 'respond.php?page=' . (int) $pagination['prev'] . '&q=' . urlencode($query) . '&status=' . urlencode($filterStatus) . '&category=' . urlencode($filterCategory) : '#' ?>">Previous</a>
              <a class="btn btn--outline btn--sm" href="<?= $pagination['has_next'] ? 'respond.php?page=' . (int) $pagination['next'] . '&q=' . urlencode($query) . '&status=' . urlencode($filterStatus) . '&category=' . urlencode($filterCategory) : '#' ?>">Next</a>
            </div>
          </div>
        </div>

        <div class="card">
          <?php if ($selected): ?>
            <div class="detail">
              <div class="section__head">
                <div>
                  <h2 class="section__title"><?= h($selected['subject']) ?></h2>
                  <p class="section__sub"><?= h(feedback_subject($selected)) ?></p>
                </div>
                <span class="<?= h(severity_badge_class($selected['severity'])) ?>"><?= h($selected['severity']) ?></span>
              </div>

              <div class="row" style="flex-wrap: wrap;">
                <span class="badge badge-indigo"><?= h(category_label($selected['category'])) ?></span>
                <span class="badge badge-blue"><?= h(route_label($selected['target_role'])) ?></span>
                <span class="<?= h(status_badge_class($selected['status'])) ?>"><?= h($selected['status']) ?></span>
                <span class="badge badge-slate"><?= h($selected['is_anonymous'] ? 'Anonymous' : 'Named') ?></span>
                <span class="<?= h(sla_state($selected)['class']) ?>"><?= h(sla_state($selected)['state']) ?></span>
              </div>

              <div class="notes">
                <div class="muted" style="font-size: 0.85rem;">Submitted by</div>
                <strong><?= h($selected['is_anonymous'] ? 'Anonymous student' : $selected['student_name']) ?></strong>
                <div class="muted" style="margin-top: 0.25rem;"><?= h(format_time($selected['created_at'])) ?></div>
                <div class="muted" style="margin-top: 0.25rem;">SLA due <?= h(format_time($selected['sla_due_at'] ?? null)) ?></div>
              </div>

              <div class="detail__message"><?= h($selected['message']) ?></div>

              <?php if ($attachments): ?>
                <div class="card card--tight">
                  <div class="section__head">
                    <div>
                      <h3 class="section__title" style="font-size: 1.15rem;">Attachments</h3>
                    </div>
                  </div>
                  <div class="stat-list">
                    <?php foreach ($attachments as $attachment): ?>
                      <div class="mini-stat">
                        <div>
                          <strong><?= h($attachment['original_name']) ?></strong>
                          <div class="muted"><?= h($attachment['mime_type']) ?> · <?= h(format_duration_seconds((int) $attachment['file_size'])) ?></div>
                        </div>
                        <a class="btn btn--outline btn--sm" href="storage/attachments/<?= h($attachment['stored_name']) ?>" target="_blank" rel="noopener">Open</a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <form method="post" class="form-grid">
                <?= csrf_input() ?>
                <input type="hidden" name="feedback_id" value="<?= (int) $selected['id'] ?>">
                <div class="split-grid">
                  <div class="field">
                    <label for="status">Update status</label>
                    <select id="status" name="status">
                      <option <?= $selected['status'] === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                      <option <?= $selected['status'] === 'Seen' ? 'selected' : '' ?>>Seen</option>
                      <option <?= $selected['status'] === 'Responded' ? 'selected' : '' ?>>Responded</option>
                      <option <?= $selected['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                  </div>
                  <div class="field">
                    <label>Routing</label>
                    <div class="toggle">
                      <span class="material-symbols-outlined">route</span>
                      <strong><?= h(route_label($selected['target_role'])) ?></strong>
                    </div>
                  </div>
                </div>

                <div class="field">
                  <label for="response">Response note</label>
                  <textarea id="response" name="response" placeholder="Write a reply, warning, or resolution note that will be stored in the response trail."></textarea>
                </div>

                <div class="row" style="justify-content: flex-end; gap: 0.8rem; flex-wrap: wrap;">
                  <a class="btn btn--ghost" href="dashboard.php">Back</a>
                  <button class="btn btn--primary" type="submit">Save Response</button>
                </div>
              </form>

              <div>
                <h3 class="headline" style="font-size: 1.15rem; margin-bottom: 0.6rem;">History</h3>
                <div class="activity-list">
                  <?php foreach ($responses as $response): ?>
                    <div class="activity" style="padding-block: 0.6rem;">
                      <div class="avatar" style="width: 2.1rem; height: 2.1rem; font-size: 0.75rem;"><?= h(strtoupper(mb_substr($response['responder_name'], 0, 1))) ?></div>
                      <div>
                        <strong><?= h($response['responder_name']) ?></strong>
                        <div class="muted"><?= h($response['status']) ?> · <?= h(relative_time($response['created_at'])) ?></div>
                        <div style="margin-top: 0.25rem; color: #334155;"><?= h($response['message']) ?></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <p class="muted">No accessible feedback items found.</p>
            <?php endif; ?>
        </div>
      </section>

      <div class="footer">All access is filtered by role and visibility rules.</div>
    </div>
  </main>

  <script src="assets/app.js"></script>
</body>
</html>
