<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (PHP_SAPI !== 'cli') {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
        session_start();
    }
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();

    if (!$user) {
        header('Location: index.php');
        exit;
    }

    return $user;
}

function require_role(array|string $roles): array
{
    $user = require_login();
    $allowed = is_array($roles) ? $roles : [$roles];

    if (!in_array($user['role'], $allowed, true)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!$token || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419);
        echo 'Invalid security token.';
        exit;
    }
}

function request_int(string $key, int $default = 0): int
{
    return isset($_REQUEST[$key]) ? (int) $_REQUEST[$key] : $default;
}

function request_string(string $key, string $default = ''): string
{
    return trim((string) ($_REQUEST[$key] ?? $default));
}

function query_string(array $params, array $exclude = []): string
{
    foreach ($exclude as $key) {
        unset($params[$key]);
    }

    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');

    return http_build_query($params);
}

function pagination_bounds(int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset];
}

function pagination_meta(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $pages,
        'prev' => max(1, $page - 1),
        'next' => min($pages, $page + 1),
    ];
}

function status_options(): array
{
    return ['Submitted', 'Seen', 'Responded', 'Closed'];
}

function severity_options(): array
{
    return ['Low', 'Medium', 'High', 'Critical'];
}

function normalize_status(string $status): string
{
    return in_array($status, status_options(), true) ? $status : 'Seen';
}

function normalize_severity(string $severity): string
{
    return in_array($severity, severity_options(), true) ? $severity : 'Medium';
}

function sla_hours_for_feedback(string $severity, string $category = ''): int
{
    return match (true) {
        $category === 'harassment' => 12,
        $severity === 'Critical' => 24,
        $severity === 'High' => 72,
        $severity === 'Medium' => 120,
        default => 240,
    };
}

function sla_due_at_for_feedback(string $severity, string $category, ?string $createdAt = null): string
{
    $base = $createdAt ? strtotime($createdAt) : time();
    return date('Y-m-d H:i:s', $base + sla_hours_for_feedback($severity, $category) * 3600);
}

function sla_state(array $feedback): array
{
    $due = $feedback['sla_due_at'] ?? null;
    if (!$due) {
        return ['state' => 'Not set', 'class' => 'badge badge-slate'];
    }

    $remaining = strtotime($due) - time();
    if ($remaining < 0) {
        return ['state' => 'Overdue', 'class' => 'badge badge-rose'];
    }

    if ($remaining <= 86400) {
        return ['state' => 'Due soon', 'class' => 'badge badge-amber'];
    }

    return ['state' => 'On track', 'class' => 'badge badge-green'];
}

function format_duration_seconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);

    if ($days > 0) {
        return $days . 'd ' . $hours . 'h';
    }
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}

function user_initial(array $user): string
{
    return strtoupper(mb_substr((string) ($user['name'] ?? ''), 0, 1));
}

function legacy_init_database(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            department TEXT NOT NULL,
            student_code TEXT,
            created_at TEXT NOT NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL,
            title TEXT NOT NULL,
            instructor_id INTEGER NOT NULL,
            department TEXT NOT NULL,
            semester TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "Active",
            FOREIGN KEY (instructor_id) REFERENCES users (id) ON DELETE CASCADE
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            course_id INTEGER,
            category TEXT NOT NULL,
            target_role TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            is_anonymous INTEGER NOT NULL DEFAULT 1,
            severity TEXT NOT NULL DEFAULT "Medium",
            status TEXT NOT NULL DEFAULT "Submitted",
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE SET NULL
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS responses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            feedback_id INTEGER NOT NULL,
            responder_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (feedback_id) REFERENCES feedback (id) ON DELETE CASCADE,
            FOREIGN KEY (responder_id) REFERENCES users (id) ON DELETE CASCADE
        )
    ');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_target_role ON feedback (target_role)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_student ON feedback (student_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_status ON feedback (status)');

    $users = [
        ['Amanuel Getachew', 'student@astu.edu', 'student', 'Computer Science', 'STU-48291', 'password'],
        ['Dr. Abebech Alemu', 'instructor@astu.edu', 'instructor', 'Computer Science', null, 'password'],
        ['Dr. Tesfaye Belachew', 'department@astu.edu', 'department', 'Computer Science', null, 'password'],
        ['Ms. Muna Mohammed', 'studentaffairs@astu.edu', 'student_affairs', 'Student Affairs', null, 'password'],
        ['Mr. Dawit Solomon', 'admin@astu.edu', 'admin', 'Academic Directorate', null, 'password'],
    ];

    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, department, student_code, created_at)
        VALUES (:name, :email, :password_hash, :role, :department, :student_code, :created_at)
    ');

    foreach ($users as $user) {
        $stmt->execute([
            ':name' => $user[0],
            ':email' => $user[1],
            ':password_hash' => password_hash($user[5], PASSWORD_DEFAULT),
            ':role' => $user[2],
            ':department' => $user[3],
            ':student_code' => $user[4],
            ':created_at' => date('c'),
        ]);
    }

    $instructorId = (int) $pdo->query("SELECT id FROM users WHERE role = 'instructor' LIMIT 1")->fetchColumn();
    $studentId = (int) $pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();
    $departmentId = (int) $pdo->query("SELECT id FROM users WHERE role = 'department' LIMIT 1")->fetchColumn();
    $studentAffairsId = (int) $pdo->query("SELECT id FROM users WHERE role = 'student_affairs' LIMIT 1")->fetchColumn();

    $courses = [
        ['CS101', 'Introduction to Computer Science', $instructorId, 'Computer Science', 'Fall 2024', 'Active'],
        ['CS204', 'Database Systems', $instructorId, 'Computer Science', 'Fall 2024', 'Active'],
        ['CS305', 'Software Engineering Capstone', $instructorId, 'Computer Science', 'Spring 2025', 'Active'],
        ['CS312', 'Human Computer Interaction', $instructorId, 'Computer Science', 'Spring 2025', 'Active'],
    ];

    $courseStmt = $pdo->prepare('
        INSERT INTO courses (code, title, instructor_id, department, semester, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    foreach ($courses as $course) {
        $courseStmt->execute($course);
    }

    $courseId = (int) $pdo->query("SELECT id FROM courses WHERE code = 'CS101' LIMIT 1")->fetchColumn();
    $courseId2 = (int) $pdo->query("SELECT id FROM courses WHERE code = 'CS204' LIMIT 1")->fetchColumn();

    $feedback = [
        [
            $studentId,
            $courseId,
            'instructor',
            'instructor',
            'Lecture pacing and explanation clarity',
            'The explanations are strong, but the pace in the second half of the lecture sometimes moves too quickly for revision notes.',
            0,
            'Medium',
            'Seen',
        ],
        [
            $studentId,
            $courseId2,
            'assessment',
            'department',
            'Assessment fairness and workload',
            'The assignment deadlines for the course are clustered too tightly around the midterm period.',
            0,
            'High',
            'Responded',
        ],
        [
            $studentId,
            null,
            'harassment',
            'student_affairs',
            'Confidential student support request',
            'I want to report a sensitive issue and request a private follow-up from Student Affairs only.',
            1,
            'High',
            'Submitted',
        ],
        [
            $studentId,
            $courseId,
            'course',
            'instructor',
            'Course materials and examples',
            'The tutorials are helpful, especially the worked examples before quizzes.',
            1,
            'Low',
            'Closed',
        ],
    ];

    $feedbackStmt = $pdo->prepare('
        INSERT INTO feedback (
            student_id, course_id, category, target_role, subject, message, is_anonymous, severity, status, created_at, updated_at
        ) VALUES (
            :student_id, :course_id, :category, :target_role, :subject, :message, :is_anonymous, :severity, :status, :created_at, :updated_at
        )
    ');

    foreach ($feedback as $entry) {
        $feedbackStmt->execute([
            ':student_id' => $entry[0],
            ':course_id' => $entry[1],
            ':category' => $entry[2],
            ':target_role' => $entry[3],
            ':subject' => $entry[4],
            ':message' => $entry[5],
            ':is_anonymous' => $entry[6],
            ':severity' => $entry[7],
            ':status' => $entry[8],
            ':created_at' => date('c', strtotime('-' . random_int(2, 14) . ' days')),
            ':updated_at' => date('c'),
        ]);
    }

    $responseStmt = $pdo->prepare('
        INSERT INTO responses (feedback_id, responder_id, message, status, created_at)
        VALUES (?, ?, ?, ?, ?)
    ');

    $responseStmt->execute([
        2,
        $departmentId,
        'The department has reviewed the assessment schedule and will rebalance the assignment windows for the next cycle.',
        'Responded',
        date('c', strtotime('-1 day')),
    ]);

    $responseStmt->execute([
        4,
        $instructorId,
        'Thank you for the encouraging feedback. We will keep the worked examples in future lecture notes.',
        'Closed',
        date('c', strtotime('-3 days')),
    ]);
}

function legacy_authenticate_user(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);
    return $user;
}

function role_label(string $role): string
{
    return [
        'student' => 'Student',
        'instructor' => 'Instructor',
        'department' => 'Department',
        'student_affairs' => 'Student Affairs',
        'admin' => 'Administrator',
    ][$role] ?? ucfirst($role);
}

function role_badge_class(string $role): string
{
    return [
        'student' => 'badge badge-blue',
        'instructor' => 'badge badge-indigo',
        'department' => 'badge badge-amber',
        'student_affairs' => 'badge badge-rose',
        'admin' => 'badge badge-slate',
    ][$role] ?? 'badge';
}

function category_label(string $category): string
{
    return [
        'course' => 'Course quality',
        'instructor' => 'Instructor performance',
        'assessment' => 'Assessment fairness',
        'harassment' => 'Sensitive / harassment case',
        'department' => 'Department issue',
    ][$category] ?? ucfirst($category);
}

function route_label(string $role): string
{
    return [
        'instructor' => 'Instructor inbox',
        'department' => 'Department review',
        'student_affairs' => 'Student Affairs',
        'admin' => 'Administrative oversight',
        'student' => 'Student feedback',
    ][$role] ?? ucfirst($role);
}

function route_for_category(string $category): string
{
    return [
        'course' => 'instructor',
        'instructor' => 'instructor',
        'assessment' => 'department',
        'department' => 'department',
        'harassment' => 'student_affairs',
    ][$category] ?? 'department';
}

function severity_badge_class(string $severity): string
{
    return [
        'Low' => 'badge badge-green',
        'Medium' => 'badge badge-blue',
        'High' => 'badge badge-amber',
        'Critical' => 'badge badge-rose',
    ][$severity] ?? 'badge badge-slate';
}

function status_badge_class(string $status): string
{
    return [
        'Submitted' => 'badge badge-blue',
        'Seen' => 'badge badge-slate',
        'Responded' => 'badge badge-green',
        'Closed' => 'badge badge-amber',
    ][$status] ?? 'badge badge-slate';
}

function format_time(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    if ($time === false) {
        return '';
    }

    return date('M j, Y', $time);
}

function relative_time(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $minutes = max(1, (int) floor($diff / 60));
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = max(1, (int) floor($diff / 3600));
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }

    $days = max(1, (int) floor($diff / 86400));
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}

function legacy_all_feedback(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT
            f.*,
            u.name AS student_name,
            u.department AS student_department,
            u.student_code,
            c.code AS course_code,
            c.title AS course_title,
            c.semester AS course_semester,
            c.instructor_id,
            i.name AS instructor_name,
            r.name AS responder_name,
            r.role AS responder_role
        FROM feedback f
        JOIN users u ON u.id = f.student_id
        LEFT JOIN courses c ON c.id = f.course_id
        LEFT JOIN users i ON i.id = c.instructor_id
        LEFT JOIN (
            SELECT feedback_id, MAX(id) AS latest_response_id
            FROM responses
            GROUP BY feedback_id
        ) latest ON latest.feedback_id = f.id
        LEFT JOIN responses rs ON rs.id = latest.latest_response_id
        LEFT JOIN users r ON r.id = rs.responder_id
        ORDER BY f.created_at DESC, f.id DESC
    ');

    return $stmt->fetchAll();
}

function legacy_feedback_responses(PDO $pdo, int $feedbackId): array
{
    $stmt = $pdo->prepare('
        SELECT r.*, u.name AS responder_name, u.role AS responder_role
        FROM responses r
        JOIN users u ON u.id = r.responder_id
        WHERE r.feedback_id = :id
        ORDER BY r.created_at ASC
    ');
    $stmt->execute([':id' => $feedbackId]);

    return $stmt->fetchAll();
}

function can_access_feedback(array $user, array $feedback): bool
{
    return match ($user['role']) {
        'admin' => true,
        'student' => (int) $feedback['student_id'] === (int) $user['id'],
        'instructor' => $feedback['target_role'] === 'instructor' && (int) $feedback['instructor_id'] === (int) $user['id'],
        'department' => $feedback['target_role'] === 'department' && $feedback['category'] !== 'harassment',
        'student_affairs' => $feedback['target_role'] === 'student_affairs',
        default => false,
    };
}

function accessible_feedback(array $user, array $rows): array
{
    return array_values(array_filter($rows, fn(array $feedback) => can_access_feedback($user, $feedback)));
}

function count_by(array $rows, string $key): array
{
    $result = [];
    foreach ($rows as $row) {
        $value = $row[$key] ?? null;
        if ($value === null) {
            continue;
        }
        $result[$value] = ($result[$value] ?? 0) + 1;
    }
    return $result;
}

function dashboard_metrics(array $user, array $feedbackRows): array
{
    $myRows = accessible_feedback($user, $feedbackRows);
    $submitted = count($myRows);
    $pending = count(array_filter($myRows, fn($row) => $row['status'] === 'Submitted' || $row['status'] === 'Seen'));
    $closed = count(array_filter($myRows, fn($row) => $row['status'] === 'Closed'));
    $responded = count(array_filter($myRows, fn($row) => $row['status'] === 'Responded'));

    return compact('submitted', 'pending', 'closed', 'responded');
}

function legacy_user_courses(PDO $pdo, array $user): array
{
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare('
            SELECT c.*, u.name AS instructor_name
            FROM courses c
            JOIN users u ON u.id = c.instructor_id
            WHERE c.department = :department
            ORDER BY c.code ASC
        ');
        $stmt->execute([':department' => $user['department']]);

        return $stmt->fetchAll();
    }

    if ($user['role'] === 'instructor') {
        $stmt = $pdo->prepare('
            SELECT c.*, u.name AS instructor_name
            FROM courses c
            JOIN users u ON u.id = c.instructor_id
            WHERE c.instructor_id = :id
            ORDER BY c.code ASC
        ');
        $stmt->execute([':id' => $user['id']]);

        return $stmt->fetchAll();
    }

    return $pdo->query('
        SELECT c.*, u.name AS instructor_name
        FROM courses c
        JOIN users u ON u.id = c.instructor_id
        ORDER BY c.code ASC
    ')->fetchAll();
}

function feedback_summary(array $rows): array
{
    return [
        'total' => count($rows),
        'submitted' => count(array_filter($rows, fn($row) => $row['status'] === 'Submitted')),
        'seen' => count(array_filter($rows, fn($row) => $row['status'] === 'Seen')),
        'responded' => count(array_filter($rows, fn($row) => $row['status'] === 'Responded')),
        'closed' => count(array_filter($rows, fn($row) => $row['status'] === 'Closed')),
    ];
}

function latest_response(array $feedback): string
{
    if (empty($feedback['responder_name'])) {
        return 'Awaiting response';
    }

    return $feedback['responder_name'] . ' · ' . $feedback['status'];
}

function text_excerpt(string $text, int $limit = 120): string
{
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit - 1) . '…';
}

function feedback_subject(array $feedback): string
{
    $course = $feedback['course_code'] ? ($feedback['course_code'] . ' · ' . $feedback['course_title']) : 'General academic issue';
    return $course . ' · ' . $feedback['subject'];
}
