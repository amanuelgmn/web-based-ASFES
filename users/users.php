<?php
require_once __DIR__ . '/../config.php';

$user = require_role('admin');
$flash = flash_get();
$search = request_string('q');
$roleFilter = request_string('role', 'all');
$page = max(1, request_int('page', 1));
$perPage = 8;
$returnQuery = query_string([
    'q' => $search,
    'role' => $roleFilter,
    'page' => $page,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_user') {
            $created = create_user_record([
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'password' => trim((string) ($_POST['password'] ?? '')),
                'role' => (string) ($_POST['role'] ?? 'student'),
                'department' => trim((string) ($_POST['department'] ?? '')),
                'student_code' => trim((string) ($_POST['student_code'] ?? '')),
            ]);

            if ($created) {
                log_audit((int) $user['id'], 'user.created', 'user', (int) $created['id'], ['email' => $created['email'], 'role' => $created['role']]);
                flash_set('success', 'New user created successfully.');
            }
        } elseif ($action === 'update_role') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'student');
            $department = trim((string) ($_POST['department'] ?? ''));
            $updated = update_user_role_record($targetId, $role, $department);
            if ($updated) {
                log_audit((int) $user['id'], 'user.role_updated', 'user', $targetId, ['role' => $role]);
                flash_set('success', 'User role updated.');
            }
        } elseif ($action === 'reset_password') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $password = trim((string) ($_POST['password'] ?? ''));
            if ($password === '') {
                throw new RuntimeException('Password cannot be empty.');
            }
            $updated = reset_user_password_record($targetId, $password);
            if ($updated) {
                log_audit((int) $user['id'], 'user.password_reset', 'user', $targetId);
                flash_set('success', 'Password reset successfully.');
            }
        }
    } catch (Throwable $exception) {
        flash_set('error', $exception->getMessage() ?: 'Unable to complete admin action.');
    }

    header('Location: users.php?' . $returnQuery);
    exit;
}

$users = all_users($search, $page, $perPage, $roleFilter);
$rows = $users['rows'];
$pagination = $users['pagination'];

$audit = audit_logs_for_page(1, 6);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Console | <?= h(APP_BRAND) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="app-shell">
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="sidebar__logo"><span class="material-symbols-outlined">school</span></div>
      <div>
        <div class="sidebar__title brand">ASTU SFES</div>
        <div class="sidebar__subtitle">Admin Console</div>
      </div>
    </div>
    <nav class="sidebar__nav">
      <a class="sidebar__link" href="../dashboard.php"><span class="material-symbols-outlined">dashboard</span><span>Dashboard</span></a>
      <a class="sidebar__link is-active" href="users.php"><span class="material-symbols-outlined">manage_accounts</span><span>Users</span></a>
      <a class="sidebar__link" href="activity_log.php"><span class="material-symbols-outlined">history</span><span>Activity Log</span></a>
      <a class="sidebar__link" href="../report.php"><span class="material-symbols-outlined">analytics</span><span>Reports</span></a>
      <a class="sidebar__link" href="../logout.php"><span class="material-symbols-outlined">logout</span><span>Logout</span></a>
    </nav>
  </aside>

  <header class="topbar">
    <div class="row" style="gap: 0.85rem;">
      <button class="icon-btn mobile-toggle" type="button" data-sidebar-toggle aria-label="Open navigation">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="topbar__title brand">User Administration</div>
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
        <div class="hero__eyebrow">Administrative tools</div>
        <h1 class="hero__title">Manage users, roles, and access</h1>
        <p class="hero__lead">Create accounts, update roles, and reset credentials while keeping every change in the audit log.</p>
      </section>

      <section class="section split" style="margin-top: 1rem;">
        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Create user</h2>
              <p class="section__sub">Create new institutional accounts directly in the database.</p>
            </div>
          </div>

          <form method="post" class="form-grid" action="users.php?<?= h($returnQuery) ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="create_user">
            <div class="split-grid">
              <label class="field"><span>Name</span><input type="text" name="name" required></label>
              <label class="field"><span>Email</span><input type="email" name="email" required></label>
            </div>
            <div class="split-grid">
              <label class="field"><span>Password</span><input type="password" name="password" required></label>
              <label class="field">
                <span>Role</span>
                <select name="role">
                  <option value="student">Student</option>
                  <option value="instructor">Instructor</option>
                  <option value="department">Department</option>
                  <option value="student_affairs">Student Affairs</option>
                  <option value="admin">Admin</option>
                </select>
              </label>
            </div>
            <div class="split-grid">
              <label class="field"><span>Department</span><input type="text" name="department" required></label>
              <label class="field"><span>Student code</span><input type="text" name="student_code" placeholder="Optional for staff"></label>
            </div>
            <div class="row" style="justify-content: flex-end;">
              <button class="btn btn--primary" type="submit">Create User</button>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="section__head">
            <div>
              <h2 class="section__title">Search users</h2>
              <p class="section__sub">Filter the account list without leaving the page.</p>
            </div>
          </div>

          <form method="get" class="toolbar">
            <div class="field" style="flex: 1; min-width: 14rem;">
              <label for="q">Search</label>
              <input id="q" name="q" type="text" value="<?= h($search) ?>" placeholder="Search by name, email, or department">
            </div>
            <div class="field" style="min-width: 12rem;">
              <label for="role">Role</label>
              <select id="role" name="role">
                <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All</option>
                <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="instructor" <?= $roleFilter === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                <option value="department" <?= $roleFilter === 'department' ? 'selected' : '' ?>>Department</option>
                <option value="student_affairs" <?= $roleFilter === 'student_affairs' ? 'selected' : '' ?>>Student Affairs</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
              </select>
            </div>
            <div style="align-self: end;">
              <button class="btn btn--primary" type="submit">Apply</button>
            </div>
            <?php if ($search !== '' || $roleFilter !== 'all'): ?>
              <div style="align-self: end;">
                <a class="btn btn--outline" href="users.php">Reset</a>
              </div>
            <?php endif; ?>
          </form>
        </div>
      </section>

      <section class="section card">
        <div class="section__head">
          <div>
            <h2 class="section__title">Accounts</h2>
            <p class="section__sub"><?= (int) $users['total'] ?> users in the system</p>
          </div>
        </div>
        <div class="stat-list">
          <?php foreach ($rows as $account): ?>
            <div class="mini-stat" style="align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
              <div style="flex: 1; min-width: 16rem;">
                <div class="row" style="flex-wrap: wrap;">
                  <strong><?= h($account['name']) ?></strong>
                  <span class="badge badge-indigo"><?= h(role_label($account['role'])) ?></span>
                  <span class="badge badge-slate"><?= h($account['department']) ?></span>
                </div>
                <div class="muted" style="margin-top: 0.35rem;"><?= h($account['email']) ?></div>
                <div class="muted" style="margin-top: 0.25rem;">Created <?= h(format_time($account['created_at'])) ?></div>
                <?php if (!empty($account['student_code'])): ?>
                  <div class="muted" style="margin-top: 0.25rem;">Student code <?= h($account['student_code']) ?></div>
                <?php endif; ?>
              </div>
              <form method="post" class="form-grid" style="min-width: 18rem; flex: 0 0 18rem;" action="users.php?<?= h($returnQuery) ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                <select name="role">
                  <option value="student" <?= $account['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                  <option value="instructor" <?= $account['role'] === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                  <option value="department" <?= $account['role'] === 'department' ? 'selected' : '' ?>>Department</option>
                  <option value="student_affairs" <?= $account['role'] === 'student_affairs' ? 'selected' : '' ?>>Student Affairs</option>
                  <option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <input type="text" name="department" value="<?= h($account['department']) ?>" placeholder="Department">
                <button class="btn btn--secondary btn--sm" type="submit">Update Role</button>
              </form>
              <form method="post" class="form-grid" style="min-width: 18rem; flex: 0 0 18rem;" action="users.php?<?= h($returnQuery) ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                <input type="password" name="password" placeholder="Temporary password" required>
                <button class="btn btn--outline btn--sm" type="submit">Reset Password</button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <div class="notice">
              <strong>No users match the current filters.</strong>
              <p class="muted" style="margin-bottom: 0;">Try clearing the search or switching back to All roles.</p>
            </div>
          <?php endif; ?>
        </div>

        <div class="row" style="justify-content: space-between; margin-top: 1rem; flex-wrap: wrap;">
          <span class="muted">Page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['pages'] ?></span>
          <div class="row">
            <a class="btn btn--outline btn--sm" href="<?= $pagination['has_prev'] ? 'users.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['prev']])) : '#' ?>">Previous</a>
            <a class="btn btn--outline btn--sm" href="<?= $pagination['has_next'] ? 'users.php?' . http_build_query(array_merge($_GET, ['page' => $pagination['next']])) : '#' ?>">Next</a>
          </div>
        </div>
      </section>

      <section class="section card">
        <div class="section__head">
          <div>
            <h2 class="section__title">Recent audit log</h2>
            <p class="section__sub">Every create/update/reset action is tracked here.</p>
          </div>
        </div>
        <div class="activity-list">
          <?php foreach ($audit['rows'] as $entry): ?>
            <div class="activity">
              <div class="avatar" style="width: 2.1rem; height: 2.1rem; font-size: 0.75rem;">A</div>
              <div>
                <strong><?= h($entry['action']) ?></strong>
                <div class="muted"><?= h($entry['actor_name'] ?? 'System') ?> · <?= h(format_time($entry['created_at'])) ?></div>
                <?php if (!empty($entry['metadata'])): ?>
                  <div class="muted"><?= h((string) $entry['metadata']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <div class="footer">Admin changes are persisted and recorded for accountability.</div>
    </div>
  </main>

  <script src="../assets/app.js"></script>
</body>
</html>
