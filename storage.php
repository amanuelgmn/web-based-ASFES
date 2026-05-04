<?php
declare(strict_types=1);

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('ASFES_DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('ASFES_DB_PORT') ?: '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('ASFES_DB_NAME') ?: 'asfes');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('ASFES_DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('ASFES_DB_PASS') ?: '');
}

function mysql_pdo(?string $database = DB_NAME): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT;
    if ($database !== null && $database !== '') {
        $dsn .= ';dbname=' . $database;
    }
    $dsn .= ';charset=utf8mb4';

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = mysql_pdo(DB_NAME);
    } catch (PDOException $exception) {
        $server = mysql_pdo(null);
        $safeName = str_replace('`', '``', DB_NAME);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = mysql_pdo(DB_NAME);
    }

    init_database($pdo);
    return $pdo;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ');
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!table_has_column($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }
}

function table_has_index(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
          AND INDEX_NAME = :index_name
    ');
    $stmt->execute([
        ':table_name' => $table,
        ':index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function ensure_index(PDO $pdo, string $table, string $indexName, string $definition): void
{
    if (!table_has_index($pdo, $table, $indexName)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$definition})");
    }
}

function init_database(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL,
            department VARCHAR(150) NOT NULL,
            student_code VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS courses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL,
            title VARCHAR(200) NOT NULL,
            instructor_id INT UNSIGNED NOT NULL,
            department VARCHAR(150) NOT NULL,
            semester VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT "Active",
            CONSTRAINT fk_courses_instructor
                FOREIGN KEY (instructor_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED DEFAULT NULL,
            category VARCHAR(50) NOT NULL,
            target_role VARCHAR(50) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_anonymous TINYINT(1) NOT NULL DEFAULT 1,
            severity VARCHAR(20) NOT NULL DEFAULT "Medium",
            status VARCHAR(20) NOT NULL DEFAULT "Submitted",
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT fk_feedback_student
                FOREIGN KEY (student_id) REFERENCES users (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_feedback_course
                FOREIGN KEY (course_id) REFERENCES courses (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS responses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            responder_id INT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_responses_feedback
                FOREIGN KEY (feedback_id) REFERENCES feedback (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_responses_responder
                FOREIGN KEY (responder_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS feedback_attachments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT UNSIGNED NOT NULL,
            uploader_id INT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_feedback_attachments_feedback
                FOREIGN KEY (feedback_id) REFERENCES feedback (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_feedback_attachments_uploader
                FOREIGN KEY (uploader_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            feedback_id INT UNSIGNED DEFAULT NULL,
            type VARCHAR(30) NOT NULL DEFAULT "info",
            message VARCHAR(255) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            read_at DATETIME DEFAULT NULL,
            dismissed_at DATETIME DEFAULT NULL,
            CONSTRAINT fk_notifications_user
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE,
            CONSTRAINT fk_notifications_feedback
                FOREIGN KEY (feedback_id) REFERENCES feedback (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id INT UNSIGNED DEFAULT NULL,
            metadata JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_audit_logs_actor
                FOREIGN KEY (actor_id) REFERENCES users (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    ensure_column($pdo, 'feedback', 'sla_due_at', 'sla_due_at DATETIME DEFAULT NULL AFTER updated_at');
    ensure_column($pdo, 'feedback', 'priority', 'priority VARCHAR(20) NOT NULL DEFAULT "Medium" AFTER severity');
    ensure_column($pdo, 'feedback', 'assigned_to', 'assigned_to INT UNSIGNED DEFAULT NULL AFTER priority');

    ensure_index($pdo, 'feedback', 'idx_feedback_created_at', '`created_at`');
    ensure_index($pdo, 'feedback', 'idx_feedback_status', '`status`');
    ensure_index($pdo, 'feedback', 'idx_feedback_target_role', '`target_role`');
    ensure_index($pdo, 'responses', 'idx_responses_feedback_id', '`feedback_id`');
    ensure_index($pdo, 'responses', 'idx_responses_created_at', '`created_at`');
    ensure_index($pdo, 'notifications', 'idx_notifications_user_id', '`user_id`');
    ensure_index($pdo, 'notifications', 'idx_notifications_status', '`user_id`, `is_read`, `is_dismissed`');
    ensure_index($pdo, 'audit_logs', 'idx_audit_logs_created_at', '`created_at`');
    ensure_index($pdo, 'feedback_attachments', 'idx_feedback_attachments_feedback_id', '`feedback_id`');

    $hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($hasUsers === 0) {
        seed_database($pdo);
    }
}

function seed_database(PDO $pdo): void
{
    $now = date('Y-m-d H:i:s');
    $password = password_hash('password', PASSWORD_DEFAULT);

    $users = [
        ['Amanuel Getachew', 'student@astu.edu', 'student', 'Computer Science', 'STU-48291'],
        ['Dr. Abebech Alemu', 'instructor@astu.edu', 'instructor', 'Computer Science', null],
        ['Dr. Tesfaye Belachew', 'department@astu.edu', 'department', 'Computer Science', null],
        ['Ms. Muna Mohammed', 'studentaffairs@astu.edu', 'student_affairs', 'Student Affairs', null],
        ['Mr. Dawit Solomon', 'admin@astu.edu', 'admin', 'Academic Directorate', null],
    ];

    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, department, student_code, created_at)
        VALUES (:name, :email, :password_hash, :role, :department, :student_code, :created_at)
    ');

    foreach ($users as $user) {
        $stmt->execute([
            ':name' => $user[0],
            ':email' => $user[1],
            ':password_hash' => $password,
            ':role' => $user[2],
            ':department' => $user[3],
            ':student_code' => $user[4],
            ':created_at' => $now,
        ]);
    }

    $instructorId = (int) $pdo->query("SELECT id FROM users WHERE role = 'instructor' LIMIT 1")->fetchColumn();
    $studentId = (int) $pdo->query("SELECT id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn();
    $departmentId = (int) $pdo->query("SELECT id FROM users WHERE role = 'department' LIMIT 1")->fetchColumn();

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
            'student_id' => $studentId,
            'course_id' => $courseId,
            'category' => 'instructor',
            'target_role' => 'instructor',
            'subject' => 'Lecture pacing and explanation clarity',
            'message' => 'The explanations are strong, but the pace in the second half of the lecture sometimes moves too quickly for revision notes.',
            'is_anonymous' => 0,
            'severity' => 'Medium',
            'status' => 'Seen',
        ],
        [
            'student_id' => $studentId,
            'course_id' => $courseId2,
            'category' => 'assessment',
            'target_role' => 'department',
            'subject' => 'Assessment fairness and workload',
            'message' => 'The assignment deadlines for the course are clustered too tightly around the midterm period.',
            'is_anonymous' => 0,
            'severity' => 'High',
            'status' => 'Responded',
        ],
        [
            'student_id' => $studentId,
            'course_id' => null,
            'category' => 'harassment',
            'target_role' => 'student_affairs',
            'subject' => 'Confidential student support request',
            'message' => 'I want to report a sensitive issue and request a private follow-up from Student Affairs only.',
            'is_anonymous' => 1,
            'severity' => 'High',
            'status' => 'Submitted',
        ],
        [
            'student_id' => $studentId,
            'course_id' => $courseId,
            'category' => 'course',
            'target_role' => 'instructor',
            'subject' => 'Course materials and examples',
            'message' => 'The tutorials are helpful, especially the worked examples before quizzes.',
            'is_anonymous' => 1,
            'severity' => 'Low',
            'status' => 'Closed',
        ],
    ];

    $feedbackStmt = $pdo->prepare('
        INSERT INTO feedback (
            student_id, course_id, category, target_role, subject, message, is_anonymous, severity, priority, status, created_at, updated_at, sla_due_at
        ) VALUES (
            :student_id, :course_id, :category, :target_role, :subject, :message, :is_anonymous, :severity, :priority, :status, :created_at, :updated_at, :sla_due_at
        )
    ');

    foreach ($feedback as $entry) {
        $createdAt = date('Y-m-d H:i:s', strtotime('-' . random_int(2, 14) . ' days'));
        $feedbackStmt->execute([
            ':student_id' => $entry['student_id'],
            ':course_id' => $entry['course_id'],
            ':category' => $entry['category'],
            ':target_role' => $entry['target_role'],
            ':subject' => $entry['subject'],
            ':message' => $entry['message'],
            ':is_anonymous' => $entry['is_anonymous'],
            ':severity' => $entry['severity'],
            ':priority' => $entry['severity'],
            ':status' => $entry['status'],
            ':created_at' => $createdAt,
            ':updated_at' => $now,
            ':sla_due_at' => sla_due_at_for_feedback($entry['severity'], $entry['category'], $createdAt),
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
        date('Y-m-d H:i:s', strtotime('-1 day')),
    ]);

    $responseStmt->execute([
        4,
        $instructorId,
        'Thank you for the encouraging feedback. We will keep the worked examples in future lecture notes.',
        'Closed',
        date('Y-m-d H:i:s', strtotime('-3 days')),
    ]);
}

function authenticate_user(string $email, string $password): ?array
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

function user_courses(array $user): array
{
    if ($user['role'] === 'student') {
        $stmt = db()->prepare('
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
        $stmt = db()->prepare('
            SELECT c.*, u.name AS instructor_name
            FROM courses c
            JOIN users u ON u.id = c.instructor_id
            WHERE c.instructor_id = :id
            ORDER BY c.code ASC
        ');
        $stmt->execute([':id' => $user['id']]);

        return $stmt->fetchAll();
    }

    return db()->query('
        SELECT c.*, u.name AS instructor_name
        FROM courses c
        JOIN users u ON u.id = c.instructor_id
        ORDER BY c.code ASC
    ')->fetchAll();
}

function response_total(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM responses')->fetchColumn();
}

function latest_response_for_feedback(int $feedbackId): ?array
{
    $stmt = db()->prepare('
        SELECT r.*
        FROM responses r
        WHERE r.feedback_id = :feedback_id
        ORDER BY r.id DESC
        LIMIT 1
    ');
    $stmt->execute([':feedback_id' => $feedbackId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function all_feedback(): array
{
    $stmt = db()->query('
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

function feedback_responses(int $feedbackId): array
{
    $stmt = db()->prepare('
        SELECT r.*, u.name AS responder_name, u.role AS responder_role
        FROM responses r
        JOIN users u ON u.id = r.responder_id
        WHERE r.feedback_id = :feedback_id
        ORDER BY r.created_at ASC, r.id ASC
    ');
    $stmt->execute([':feedback_id' => $feedbackId]);

    return $stmt->fetchAll();
}

function insert_feedback(array $data): array
{
    $pdo = db();
    $stmt = $pdo->prepare('
        INSERT INTO feedback (
            student_id, course_id, category, target_role, subject, message, is_anonymous, severity, status, created_at, updated_at, sla_due_at, priority
        ) VALUES (
            :student_id, :course_id, :category, :target_role, :subject, :message, :is_anonymous, :severity, :status, :created_at, :updated_at, :sla_due_at, :priority
        )
    ');

    $severity = normalize_severity((string) ($data['severity'] ?? 'Medium'));
    $createdAt = date('Y-m-d H:i:s');
    $slaDueAt = sla_due_at_for_feedback($severity, (string) $data['category'], $createdAt);

    $stmt->execute([
        ':student_id' => (int) $data['student_id'],
        ':course_id' => $data['course_id'] !== null ? (int) $data['course_id'] : null,
        ':category' => $data['category'],
        ':target_role' => $data['target_role'],
        ':subject' => $data['subject'],
        ':message' => $data['message'],
        ':is_anonymous' => !empty($data['is_anonymous']) ? 1 : 0,
        ':severity' => $severity,
        ':status' => 'Submitted',
        ':created_at' => $createdAt,
        ':updated_at' => $createdAt,
        ':sla_due_at' => $slaDueAt,
        ':priority' => $severity,
    ]);

    $id = (int) $pdo->lastInsertId();
    $row = $pdo->prepare('SELECT * FROM feedback WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);
    $feedback = $row->fetch() ?: [];

    if ($feedback) {
        dispatch_feedback_notifications($feedback, 'submitted');
        log_audit((int) $data['student_id'], 'feedback.submitted', 'feedback', $id, [
            'target_role' => $feedback['target_role'],
            'category' => $feedback['category'],
        ]);
    }

    return $feedback;
}

function update_feedback_status(int $feedbackId, string $status): ?array
{
    $pdo = db();
    $status = normalize_status($status);
    $stmt = $pdo->prepare('UPDATE feedback SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':status' => $status,
        ':updated_at' => date('Y-m-d H:i:s'),
        ':id' => $feedbackId,
    ]);

    $row = $pdo->prepare('SELECT * FROM feedback WHERE id = :id LIMIT 1');
    $row->execute([':id' => $feedbackId]);

    $feedback = $row->fetch();
    if ($feedback) {
        dispatch_feedback_notifications($feedback, 'status_changed');
        log_audit(null, 'feedback.status_changed', 'feedback', $feedbackId, ['status' => $status]);
    }
    return $feedback ?: null;
}

function insert_response(int $feedbackId, int $responderId, string $message, string $status): array
{
    $pdo = db();
    $status = normalize_status($status);
    $stmt = $pdo->prepare('
        INSERT INTO responses (feedback_id, responder_id, message, status, created_at)
        VALUES (:feedback_id, :responder_id, :message, :status, :created_at)
    ');
    $stmt->execute([
        ':feedback_id' => $feedbackId,
        ':responder_id' => $responderId,
        ':message' => $message,
        ':status' => $status,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    $id = (int) $pdo->lastInsertId();
    $row = $pdo->prepare('SELECT * FROM responses WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);
    $response = $row->fetch() ?: [];

    $feedback = db()->prepare('SELECT * FROM feedback WHERE id = :id LIMIT 1');
    $feedback->execute([':id' => $feedbackId]);
    $feedbackRow = $feedback->fetch();
    if ($feedbackRow) {
        dispatch_feedback_notifications($feedbackRow, 'responded');
        log_audit($responderId, 'feedback.responded', 'feedback', $feedbackId, ['status' => $status]);
    }

    return $response;
}

function users_for_role(string $role): array
{
    $stmt = db()->prepare('SELECT id, name, email, role, department FROM users WHERE role = :role ORDER BY name ASC');
    $stmt->execute([':role' => $role]);

    return $stmt->fetchAll();
}

function log_audit(?int $actorId, string $action, string $entityType, ?int $entityId = null, array $metadata = []): void
{
    $stmt = db()->prepare('
        INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, metadata, ip_address, created_at)
        VALUES (:actor_id, :action, :entity_type, :entity_id, :metadata, :ip_address, :created_at)
    ');
    $stmt->execute([
        ':actor_id' => $actorId,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);
}

function create_notification(int $userId, string $message, string $type = 'info', ?int $feedbackId = null): array
{
    $stmt = db()->prepare('
        INSERT INTO notifications (user_id, feedback_id, type, message, created_at)
        VALUES (:user_id, :feedback_id, :type, :message, :created_at)
    ');
    $stmt->execute([
        ':user_id' => $userId,
        ':feedback_id' => $feedbackId,
        ':type' => $type,
        ':message' => $message,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    $id = (int) db()->lastInsertId();
    $row = db()->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);

    return $row->fetch() ?: [];
}

function dispatch_feedback_notifications(array $feedback, string $event = 'submitted'): void
{
    $recipients = [];

    if (!empty($feedback['student_id'])) {
        $recipients[] = (int) $feedback['student_id'];
    }

    foreach (users_for_role((string) $feedback['target_role']) as $recipient) {
        $recipients[] = (int) $recipient['id'];
    }

    $recipients = array_values(array_unique($recipients));
    $subject = (string) ($feedback['subject'] ?? 'Feedback update');

    foreach ($recipients as $recipientId) {
        $isStudent = (int) $feedback['student_id'] === $recipientId;
        $message = match ($event) {
            'submitted' => $isStudent
                ? 'Your feedback "' . $subject . '" has been submitted.'
                : 'New feedback has been routed to your inbox: ' . $subject,
            'status_changed' => $isStudent
                ? 'Status changed for "' . $subject . '" to ' . ($feedback['status'] ?? 'Updated')
                : 'Feedback "' . $subject . '" changed status to ' . ($feedback['status'] ?? 'Updated'),
            'responded' => $isStudent
                ? 'A new response was added to "' . $subject . '".'
                : 'A response was added to "' . $subject . '".',
            default => 'Feedback activity recorded for "' . $subject . '".',
        };

        create_notification($recipientId, $message, $isStudent ? 'success' : 'info', (int) $feedback['id']);
    }
}

function notifications_for_user(int $userId, bool $includeDismissed = false): array
{
    $sql = '
        SELECT n.*, f.subject AS feedback_subject, f.status AS feedback_status, f.category AS feedback_category
        FROM notifications n
        LEFT JOIN feedback f ON f.id = n.feedback_id
        WHERE n.user_id = :user_id
    ';
    if (!$includeDismissed) {
        $sql .= ' AND n.is_dismissed = 0';
    }
    $sql .= ' ORDER BY n.created_at DESC, n.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute([':user_id' => $userId]);

    return $stmt->fetchAll();
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0 AND is_dismissed = 0');
    $stmt->execute([':user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

function mark_all_notifications_read(int $userId): int
{
    $stmt = db()->prepare('
        UPDATE notifications
        SET is_read = 1,
            read_at = COALESCE(read_at, :read_at)
        WHERE user_id = :user_id
          AND is_read = 0
          AND is_dismissed = 0
    ');
    $stmt->execute([
        ':read_at' => date('Y-m-d H:i:s'),
        ':user_id' => $userId,
    ]);

    return $stmt->rowCount();
}

function mark_notification_read(int $notificationId, int $userId): ?array
{
    $stmt = db()->prepare('
        UPDATE notifications
        SET is_read = 1, read_at = COALESCE(read_at, :read_at)
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([
        ':read_at' => date('Y-m-d H:i:s'),
        ':id' => $notificationId,
        ':user_id' => $userId,
    ]);

    $row = db()->prepare('SELECT * FROM notifications WHERE id = :id AND user_id = :user_id LIMIT 1');
    $row->execute([':id' => $notificationId, ':user_id' => $userId]);
    $notification = $row->fetch();

    return $notification ?: null;
}

function dismiss_notification(int $notificationId, int $userId): ?array
{
    $stmt = db()->prepare('
        UPDATE notifications
        SET is_dismissed = 1, dismissed_at = COALESCE(dismissed_at, :dismissed_at)
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([
        ':dismissed_at' => date('Y-m-d H:i:s'),
        ':id' => $notificationId,
        ':user_id' => $userId,
    ]);

    $row = db()->prepare('SELECT * FROM notifications WHERE id = :id AND user_id = :user_id LIMIT 1');
    $row->execute([':id' => $notificationId, ':user_id' => $userId]);
    $notification = $row->fetch();

    return $notification ?: null;
}

function feedback_attachments(int $feedbackId): array
{
    $stmt = db()->prepare('
        SELECT a.*, u.name AS uploader_name
        FROM feedback_attachments a
        JOIN users u ON u.id = a.uploader_id
        WHERE a.feedback_id = :feedback_id
        ORDER BY a.created_at ASC, a.id ASC
    ');
    $stmt->execute([':feedback_id' => $feedbackId]);

    return $stmt->fetchAll();
}

function attachment_storage_dir(): string
{
    return __DIR__ . '/storage/attachments';
}

function save_feedback_attachment(int $feedbackId, int $uploaderId, array $file): ?array
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $original = basename((string) ($file['name'] ?? 'attachment'));
    $extension = pathinfo($original, PATHINFO_EXTENSION);
    $safeName = bin2hex(random_bytes(16)) . ($extension ? '.' . strtolower($extension) : '');
    $directory = attachment_storage_dir();

    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $destination = $directory . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }

    $mime = (string) ($file['type'] ?? 'application/octet-stream');
    $size = (int) ($file['size'] ?? 0);

    $stmt = db()->prepare('
        INSERT INTO feedback_attachments (
            feedback_id, uploader_id, original_name, stored_name, mime_type, file_size, created_at
        ) VALUES (
            :feedback_id, :uploader_id, :original_name, :stored_name, :mime_type, :file_size, :created_at
        )
    ');
    $stmt->execute([
        ':feedback_id' => $feedbackId,
        ':uploader_id' => $uploaderId,
        ':original_name' => $original,
        ':stored_name' => $safeName,
        ':mime_type' => $mime,
        ':file_size' => $size,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    $id = (int) db()->lastInsertId();
    $row = db()->prepare('SELECT * FROM feedback_attachments WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);

    return $row->fetch() ?: null;
}

function all_users(string $search = '', int $page = 1, int $perPage = 10, ?string $role = null): array
{
    [$page, $perPage, $offset] = pagination_bounds($page, $perPage);
    $search = trim($search);
    $where = '';
    $params = [];
    $clauses = [];
    if ($search !== '') {
        $clauses[] = '(name LIKE :search OR email LIKE :search OR department LIKE :search OR role LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if ($role !== null && $role !== 'all') {
        $clauses[] = 'role = :role';
        $params[':role'] = $role;
    }
    if ($clauses) {
        $where = 'WHERE ' . implode(' AND ', $clauses);
    }

    $count = db()->prepare('SELECT COUNT(*) FROM users ' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = db()->prepare('
        SELECT *
        FROM users
        ' . $where . '
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'pagination' => pagination_meta($total, $page, $perPage),
    ];
}

function update_user_profile_record(int $userId, string $name, string $email, ?string $password = null): ?array
{
    $fields = ['name = :name', 'email = :email'];
    $params = [
        ':id' => $userId,
        ':name' => $name,
        ':email' => $email,
    ];

    if ($password !== null && $password !== '') {
        $fields[] = 'password_hash = :password_hash';
        $params[':password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $row = db()->prepare('SELECT id, name, email, role, department, student_code, created_at FROM users WHERE id = :id LIMIT 1');
    $row->execute([':id' => $userId]);

    return $row->fetch() ?: null;
}

function update_user_role_record(int $userId, string $role, string $department): ?array
{
    $stmt = db()->prepare('UPDATE users SET role = :role, department = :department WHERE id = :id');
    $stmt->execute([
        ':role' => $role,
        ':department' => $department,
        ':id' => $userId,
    ]);

    $row = db()->prepare('SELECT id, name, email, role, department, student_code, created_at FROM users WHERE id = :id LIMIT 1');
    $row->execute([':id' => $userId]);

    return $row->fetch() ?: null;
}

function reset_user_password_record(int $userId, string $password): ?array
{
    $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    $stmt->execute([
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':id' => $userId,
    ]);

    $row = db()->prepare('SELECT id, name, email, role, department, student_code, created_at FROM users WHERE id = :id LIMIT 1');
    $row->execute([':id' => $userId]);

    return $row->fetch() ?: null;
}

function create_user_record(array $data): ?array
{
    $stmt = db()->prepare('
        INSERT INTO users (name, email, password_hash, role, department, student_code, created_at)
        VALUES (:name, :email, :password_hash, :role, :department, :student_code, :created_at)
    ');
    $stmt->execute([
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
        ':role' => $data['role'],
        ':department' => $data['department'],
        ':student_code' => $data['student_code'] ?: null,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    $row = db()->prepare('SELECT id, name, email, role, department, student_code, created_at FROM users WHERE id = :id LIMIT 1');
    $row->execute([':id' => (int) db()->lastInsertId()]);

    return $row->fetch() ?: null;
}

function audit_logs_for_page(int $page = 1, int $perPage = 10): array
{
    [$page, $perPage, $offset] = pagination_bounds($page, $perPage);
    $count = (int) db()->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();

    $stmt = db()->prepare('
        SELECT a.*, u.name AS actor_name
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.actor_id
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT :limit OFFSET :offset
    ');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'rows' => $stmt->fetchAll(),
        'total' => $count,
        'pagination' => pagination_meta($count, $page, $perPage),
    ];
}

function feedback_stats_by_category(array $rows): array
{
    $stats = [];
    foreach ($rows as $row) {
        $stats[$row['category']] = ($stats[$row['category']] ?? 0) + 1;
    }
    return $stats;
}

function response_time_seconds(array $feedback): ?int
{
    $latest = latest_response_for_feedback((int) $feedback['id']);
    if (!$latest) {
        return null;
    }

    return max(0, strtotime((string) $latest['created_at']) - strtotime((string) $feedback['created_at']));
}
