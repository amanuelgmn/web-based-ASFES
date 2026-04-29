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
            student_id, course_id, category, target_role, subject, message, is_anonymous, severity, status, created_at, updated_at
        ) VALUES (
            :student_id, :course_id, :category, :target_role, :subject, :message, :is_anonymous, :severity, :status, :created_at, :updated_at
        )
    ');

    foreach ($feedback as $entry) {
        $feedbackStmt->execute([
            ':student_id' => $entry['student_id'],
            ':course_id' => $entry['course_id'],
            ':category' => $entry['category'],
            ':target_role' => $entry['target_role'],
            ':subject' => $entry['subject'],
            ':message' => $entry['message'],
            ':is_anonymous' => $entry['is_anonymous'],
            ':severity' => $entry['severity'],
            ':status' => $entry['status'],
            ':created_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(2, 14) . ' days')),
            ':updated_at' => $now,
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
            student_id, course_id, category, target_role, subject, message, is_anonymous, severity, status, created_at, updated_at
        ) VALUES (
            :student_id, :course_id, :category, :target_role, :subject, :message, :is_anonymous, :severity, :status, :created_at, :updated_at
        )
    ');

    $stmt->execute([
        ':student_id' => (int) $data['student_id'],
        ':course_id' => $data['course_id'] !== null ? (int) $data['course_id'] : null,
        ':category' => $data['category'],
        ':target_role' => $data['target_role'],
        ':subject' => $data['subject'],
        ':message' => $data['message'],
        ':is_anonymous' => !empty($data['is_anonymous']) ? 1 : 0,
        ':severity' => $data['severity'] ?? 'Medium',
        ':status' => 'Submitted',
        ':created_at' => date('Y-m-d H:i:s'),
        ':updated_at' => date('Y-m-d H:i:s'),
    ]);

    $id = (int) $pdo->lastInsertId();
    $row = $pdo->prepare('SELECT * FROM feedback WHERE id = :id LIMIT 1');
    $row->execute([':id' => $id]);

    return $row->fetch() ?: [];
}

function update_feedback_status(int $feedbackId, string $status): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE feedback SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':status' => $status,
        ':updated_at' => date('Y-m-d H:i:s'),
        ':id' => $feedbackId,
    ]);

    $row = $pdo->prepare('SELECT * FROM feedback WHERE id = :id LIMIT 1');
    $row->execute([':id' => $feedbackId]);

    $feedback = $row->fetch();
    return $feedback ?: null;
}

function insert_response(int $feedbackId, int $responderId, string $message, string $status): array
{
    $pdo = db();
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

    return $row->fetch() ?: [];
}

