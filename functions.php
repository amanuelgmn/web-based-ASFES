<?php
// Enforce strict types for all functions in this file — prevents silent type coercion bugs
declare(strict_types=1);

/**
 * start_session — Start a PHP session if one is not already active.
 * Sets secure cookie parameters (HttpOnly, SameSite=Lax, Secure over HTTPS)
 * before calling session_start() so they take effect on the first response.
 * Skips cookie configuration entirely when running from the CLI (e.g. seeding scripts).
 */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (PHP_SAPI !== 'cli') {
            session_set_cookie_params([
                'httponly' => true,       // Prevents JavaScript from reading the session cookie
                'samesite' => 'Lax',      // Blocks the cookie from being sent on cross-site POST requests
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // HTTPS-only flag
            ]);
        }
        session_start();
    }
}

/**
 * h — HTML-escape a string for safe output inside HTML contexts.
 * Converts special characters (<, >, ", ', &) to their HTML entities.
 * Accepts null and treats it as an empty string to avoid warnings.
 *
 * @param  string|null $value  Raw string to escape (or null)
 * @return string              HTML-safe string ready for direct echo
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * flash_set — Store a one-time notification in the session.
 * The message survives exactly one redirect and is consumed by flash_get().
 *
 * @param string $type    Alert style key, e.g. 'success' or 'error'
 * @param string $message Human-readable notification text
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * flash_get — Read and immediately remove the stored flash message.
 * Call once per page load (typically at the top of the view) to render the alert.
 * Returns null when no flash message is pending.
 *
 * @return array{type: string, message: string}|null
 */
function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    // Copy the value before unsetting so we can return it
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']); // Consume the message — it must not appear on subsequent requests

    return $flash;
}

/**
 * current_user — Return the authenticated user array stored in the session.
 * Returns null when no user is logged in (i.e. session['user'] is absent).
 *
 * @return array|null  User row (without password_hash) or null if guest
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * require_login — Ensure a user is logged in before continuing.
 * Redirects to the login page and exits if the session has no user.
 * Call at the top of any page that requires authentication.
 *
 * @return array  The authenticated user array
 */
function require_login(): array
{
    $user = current_user();

    if (!$user) {
        // No active session — send the visitor to the login page
        header('Location: index.php');
        exit;
    }

    return $user;
}

/**
 * require_role — Ensure the logged-in user holds one of the permitted roles.
 * Calls require_login() first, so unauthenticated visitors are redirected too.
 * Responds with HTTP 403 and halts if the role check fails.
 *
 * @param array|string $roles  A single role string or an array of allowed roles
 * @return array               The authenticated user array
 */
function require_role(array|string $roles): array
{
    $user    = require_login();
    // Normalise to an array so we can always use in_array()
    $allowed = is_array($roles) ? $roles : [$roles];

    if (!in_array($user['role'], $allowed, true)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    return $user;
}

/**
 * csrf_token — Return (or lazily generate) the CSRF token for the current session.
 * A new 64-character hex token is created via cryptographically secure random bytes
 * and stored in the session on first call; subsequent calls return the same token.
 *
 * @return string  The CSRF token for this session
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        // 32 bytes = 64 hex characters — sufficient entropy against brute-force
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

/**
 * csrf_input — Render a hidden HTML input carrying the CSRF token.
 * Drop <?= csrf_input() ?> inside every POST form to enable verify_csrf().
 *
 * @return string  A complete <input type="hidden"> HTML element
 */
function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/**
 * verify_csrf — Validate the CSRF token submitted with a POST request.
 * Uses hash_equals() for constant-time comparison to prevent timing attacks.
 * Responds with HTTP 419 and halts if the token is missing or does not match.
 * Call at the very start of any POST handler before touching user data.
 */
function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    // hash_equals() avoids leaking token length or content via timing differences
    if (!$token || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(419); // 419 = Authentication Timeout (Laravel convention, widely understood)
        echo 'Invalid security token.';
        exit;
    }
}

/**
 * request_int — Read an integer value from $_REQUEST with a fallback default.
 * Useful for safely reading page numbers, IDs, and other numeric query params.
 *
 * @param string $key     Parameter name to look up in $_REQUEST
 * @param int    $default Value to return when the key is absent
 * @return int
 */
function request_int(string $key, int $default = 0): int
{
    return isset($_REQUEST[$key]) ? (int) $_REQUEST[$key] : $default;
}

/**
 * request_string — Read a trimmed string from $_REQUEST with a fallback default.
 * Whitespace is stripped so callers do not need to trim separately.
 *
 * @param string $key     Parameter name to look up in $_REQUEST
 * @param string $default Value to return when the key is absent
 * @return string
 */
function request_string(string $key, string $default = ''): string
{
    return trim((string) ($_REQUEST[$key] ?? $default));
}

/**
 * query_string — Build a URL query string from an array of parameters.
 * Keys listed in $exclude are removed first.
 * Null and empty-string values are filtered out so the result stays clean.
 *
 * @param array $params   Associative array of query parameters
 * @param array $exclude  Keys to strip before building the query string
 * @return string         URL-encoded query string (no leading '?')
 */
function query_string(array $params, array $exclude = []): string
{
    // Remove explicitly excluded keys (e.g. 'page' when resetting pagination)
    foreach ($exclude as $key) {
        unset($params[$key]);
    }

    // Drop null and empty-string values to keep URLs tidy
    $params = array_filter($params, static fn($value) => $value !== null && $value !== '');

    return http_build_query($params);
}

/**
 * pagination_bounds — Calculate the validated page number, page size, and SQL OFFSET.
 * Ensures page and perPage are at least 1 to prevent negative offsets.
 *
 * @param int $page     Requested page number (1-based)
 * @param int $perPage  Number of rows per page
 * @return array        [$page, $perPage, $offset]  — safe values ready for a SQL query
 */
function pagination_bounds(int $page, int $perPage): array
{
    $page    = max(1, $page);
    $perPage = max(1, $perPage);
    $offset  = ($page - 1) * $perPage;

    return [$page, $perPage, $offset];
}

/**
 * pagination_meta — Build a complete pagination metadata array from totals.
 * Clamps $page to the valid range [1, $pages] so the UI never references
 * a non-existent page.
 *
 * @param int $total    Total number of rows in the result set
 * @param int $page     Current page number (1-based)
 * @param int $perPage  Number of rows per page
 * @return array{
 *   page: int, per_page: int, total: int, pages: int,
 *   has_prev: bool, has_next: bool, prev: int, next: int
 * }
 */
function pagination_meta(int $total, int $page, int $perPage): array
{
    // ceil(total / perPage) gives the last valid page; guard against zero perPage
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    // Clamp the requested page to the valid range
    $page  = min(max(1, $page), $pages);

    return [
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'pages'    => $pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $pages,
        'prev'     => max(1, $page - 1),
        'next'     => min($pages, $page + 1),
    ];
}

/**
 * status_options — Return the canonical ordered list of feedback lifecycle statuses.
 * Used to populate dropdowns and validate incoming status values.
 *
 * @return string[]
 */
function status_options(): array
{
    return ['Submitted', 'Seen', 'Responded', 'Closed'];
}

/**
 * severity_options — Return the canonical ordered list of severity levels (low → critical).
 * Used to populate dropdowns and validate incoming severity values.
 *
 * @return string[]
 */
function severity_options(): array
{
    return ['Low', 'Medium', 'High', 'Critical'];
}

/**
 * normalize_status — Return a valid status string, falling back to 'Seen' for unknown values.
 * Prevents arbitrary strings from being stored in the database status column.
 *
 * @param string $status  Raw status string from user input
 * @return string         A value that exists in status_options()
 */
function normalize_status(string $status): string
{
    return in_array($status, status_options(), true) ? $status : 'Seen';
}

/**
 * normalize_severity — Return a valid severity string, falling back to 'Medium' for unknowns.
 * Mirrors normalize_status() for the severity column.
 *
 * @param string $severity  Raw severity string from user input
 * @return string           A value that exists in severity_options()
 */
function normalize_severity(string $severity): string
{
    return in_array($severity, severity_options(), true) ? $severity : 'Medium';
}

/**
 * sla_hours_for_feedback — Determine the SLA response window (in hours) for a feedback entry.
 * Harassment cases are always treated as the most urgent regardless of their severity.
 * Other cases are ranked by severity: Critical > High > Medium > Low.
 *
 * @param string $severity  Feedback severity level
 * @param string $category  Feedback category (pass 'harassment' to force the 12-hour window)
 * @return int              Number of hours the assigned staff has to respond
 */
function sla_hours_for_feedback(string $severity, string $category = ''): int
{
    return match (true) {
        $category === 'harassment' => 12,   // Highest urgency — 12 hours regardless of severity
        $severity === 'Critical'   => 24,   // Critical non-harassment — 24 hours
        $severity === 'High'       => 72,   // High — 3 days
        $severity === 'Medium'     => 120,  // Medium — 5 days
        default                    => 240,  // Low — 10 days
    };
}

/**
 * sla_due_at_for_feedback — Calculate the absolute SLA deadline timestamp for a feedback entry.
 * Adds the severity/category-derived SLA hours to either the feedback's created_at time
 * or the current time when no creation timestamp is available.
 *
 * @param string      $severity   Feedback severity level
 * @param string      $category   Feedback category
 * @param string|null $createdAt  ISO-8601 creation timestamp (or null to use now)
 * @return string                 MySQL-compatible datetime string (Y-m-d H:i:s)
 */
function sla_due_at_for_feedback(string $severity, string $category, ?string $createdAt = null): string
{
    // Fall back to the current Unix timestamp when no creation time is provided
    $base = $createdAt ? strtotime($createdAt) : time();
    return date('Y-m-d H:i:s', $base + sla_hours_for_feedback($severity, $category) * 3600);
}

/**
 * sla_state — Return a human-readable SLA status label and its CSS badge class.
 * Three possible states:
 *   - 'Not set'  — no SLA due date is stored on the record
 *   - 'Overdue'  — the deadline has already passed (remaining < 0)
 *   - 'Due soon' — fewer than 24 hours remain
 *   - 'On track' — more than 24 hours remain
 *
 * @param array $feedback  Feedback row; must contain 'sla_due_at' key
 * @return array{state: string, class: string}
 */
function sla_state(array $feedback): array
{
    $due = $feedback['sla_due_at'] ?? null;
    if (!$due) {
        return ['state' => 'Not set', 'class' => 'badge badge-slate'];
    }

    // Seconds remaining until the SLA deadline (negative = already overdue)
    $remaining = strtotime($due) - time();
    if ($remaining < 0) {
        return ['state' => 'Overdue',  'class' => 'badge badge-rose'];
    }

    // 86400 seconds = 24 hours
    if ($remaining <= 86400) {
        return ['state' => 'Due soon', 'class' => 'badge badge-amber'];
    }

    return ['state' => 'On track', 'class' => 'badge badge-green'];
}

/**
 * format_duration_seconds — Convert a raw second count into a compact human-readable string.
 * Output format depends on magnitude:
 *   - Days present  : "2d 3h"
 *   - Hours present : "5h 20m"
 *   - Minutes only  : "45m"
 * Negative values are clamped to zero.
 *
 * @param int $seconds  Duration in seconds
 * @return string       Formatted duration string
 */
function format_duration_seconds(int $seconds): string
{
    $seconds = max(0, $seconds); // Guard against negative durations

    // Break the total into day / hour / minute components
    $days     = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours    = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes  = intdiv($seconds, 60);

    if ($days > 0) {
        return $days . 'd ' . $hours . 'h';
    }
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }

    return $minutes . 'm';
}

/**
 * user_initial — Return the uppercase first character of a user's name.
 * Used to render avatar circles throughout the UI.
 * Falls back to an empty string when the name is absent.
 *
 * @param array $user  User row containing at least a 'name' key
 * @return string      Single uppercase letter, or '' if name is empty
 */
function user_initial(array $user): string
{
    return strtoupper(mb_substr((string) ($user['name'] ?? ''), 0, 1));
}

// =============================================================================
// DATABASE SCHEMA + SEED DATA
// =============================================================================

/**
 * legacy_init_database — Create all required tables and insert demo seed data.
 * Prefixed "legacy_" because config.php wraps this in a thin abstraction layer;
 * call via the db() helper rather than directly.
 *
 * Tables created (all use IF NOT EXISTS so re-running is safe):
 *   - users          : accounts for all roles (student, instructor, department, student_affairs, admin)
 *   - courses        : academic courses linked to an instructor
 *   - feedback       : student feedback entries with routing metadata
 *   - responses      : staff replies linked to a feedback entry
 *
 * Indexes created on frequently filtered columns (target_role, student_id, status).
 *
 * After schema creation, five demo users, four courses, four feedback entries,
 * and two responses are inserted for local development and testing.
 *
 * @param PDO $pdo  Active PDO connection to the SQLite database
 */
function legacy_init_database(PDO $pdo): void
{
    // --- TABLE: users ---
    // Stores all system users across every role.
    // student_code is NULL for non-student accounts.
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

    // --- TABLE: courses ---
    // Each course belongs to one instructor (FK to users).
    // Cascades on instructor deletion to keep referential integrity.
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

    // --- TABLE: feedback ---
    // Core entity of the system.
    // target_role determines which staff group receives and manages the entry.
    // course_id is nullable — general/department issues are not tied to a course.
    // ON DELETE SET NULL preserves feedback history even if the course is removed.
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

    // --- TABLE: responses ---
    // Staff replies attached to a feedback entry.
    // Each response records its own status snapshot at the time it was written.
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

    // --- INDEXES ---
    // Speeds up the most common WHERE/JOIN conditions in feedback list queries
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_target_role ON feedback (target_role)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_student    ON feedback (student_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_feedback_status     ON feedback (status)');

    // --- SEED: users ---
    // One account per role, all with the password 'password' (bcrypt-hashed at insert time).
    // student_code is null for non-student roles.
    $users = [
        ['Amanuel Getachew',   'student@astu.edu',       'student',        'Computer Science', 'STU-48291', 'password'],
        ['Dr. Abebech Alemu',  'instructor@astu.edu',    'instructor',     'Computer Science', null,        'password'],
        ['Dr. Tesfaye Belachew','department@astu.edu',   'department',     'Computer Science', null,        'password'],
        ['Ms. Muna Mohammed',  'studentaffairs@astu.edu','student_affairs','Student Affairs',  null,        'password'],
        ['Mr. Dawit Solomon',  'admin@astu.edu',         'admin',          'Academic Directorate', null,    'password'],
    ];

    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, department, student_code, created_at)
        VALUES (:name, :email, :password_hash, :role, :department, :student_code, :created_at)
    ');

    foreach ($users as $user) {
        $stmt->execute([
            ':name'          => $user[0],
            ':email'         => $user[1],
            ':password_hash' => password_hash($user[5], PASSWORD_DEFAULT), // Hash plain-text password
            ':role'          => $user[2],
            ':department'    => $user[3],
            ':student_code'  => $user[4],
            ':created_at'    => date('c'), // ISO 8601 timestamp
        ]);
    }

    // Resolve the auto-generated IDs of the seeded accounts for use as foreign keys below
    $instructorId      = (int) $pdo->query("SELECT id FROM users WHERE role = 'instructor'      LIMIT 1")->fetchColumn();
    $studentId         = (int) $pdo->query("SELECT id FROM users WHERE role = 'student'         LIMIT 1")->fetchColumn();
    $departmentId      = (int) $pdo->query("SELECT id FROM users WHERE role = 'department'      LIMIT 1")->fetchColumn();
    $studentAffairsId  = (int) $pdo->query("SELECT id FROM users WHERE role = 'student_affairs' LIMIT 1")->fetchColumn();

    // --- SEED: courses ---
    // Four Computer Science courses all taught by the seeded instructor
    $courses = [
        ['CS101', 'Introduction to Computer Science',  $instructorId, 'Computer Science', 'Fall 2024',   'Active'],
        ['CS204', 'Database Systems',                  $instructorId, 'Computer Science', 'Fall 2024',   'Active'],
        ['CS305', 'Software Engineering Capstone',     $instructorId, 'Computer Science', 'Spring 2025', 'Active'],
        ['CS312', 'Human Computer Interaction',        $instructorId, 'Computer Science', 'Spring 2025', 'Active'],
    ];

    $courseStmt = $pdo->prepare('
        INSERT INTO courses (code, title, instructor_id, department, semester, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    foreach ($courses as $course) {
        $courseStmt->execute($course);
    }

    // Resolve course IDs needed as foreign keys in the feedback seed data
    $courseId  = (int) $pdo->query("SELECT id FROM courses WHERE code = 'CS101' LIMIT 1")->fetchColumn();
    $courseId2 = (int) $pdo->query("SELECT id FROM courses WHERE code = 'CS204' LIMIT 1")->fetchColumn();

    // --- SEED: feedback ---
    // Four representative entries covering different categories, statuses, and anonymity settings
    // Column order: student_id, course_id, category, target_role, subject, message, is_anonymous, severity, status
    $feedback = [
        [
            $studentId,
            $courseId,
            'instructor',
            'instructor',
            'Lecture pacing and explanation clarity',
            'The explanations are strong, but the pace in the second half of the lecture sometimes moves too quickly for revision notes.',
            0,          // Not anonymous
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
            0,          // Not anonymous
            'High',
            'Responded',
        ],
        [
            $studentId,
            null,       // General issue — not tied to a specific course
            'harassment',
            'student_affairs',
            'Confidential student support request',
            'I want to report a sensitive issue and request a private follow-up from Student Affairs only.',
            1,          // Anonymous
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
            1,          // Anonymous
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
            ':student_id'   => $entry[0],
            ':course_id'    => $entry[1],
            ':category'     => $entry[2],
            ':target_role'  => $entry[3],
            ':subject'      => $entry[4],
            ':message'      => $entry[5],
            ':is_anonymous' => $entry[6],
            ':severity'     => $entry[7],
            ':status'       => $entry[8],
            // Scatter creation dates over the past 2–14 days for realistic demo data
            ':created_at'   => date('c', strtotime('-' . random_int(2, 14) . ' days')),
            ':updated_at'   => date('c'),
        ]);
    }

    // --- SEED: responses ---
    // Two staff responses linked to feedback IDs 2 (Responded) and 4 (Closed)
    $responseStmt = $pdo->prepare('
        INSERT INTO responses (feedback_id, responder_id, message, status, created_at)
        VALUES (?, ?, ?, ?, ?)
    ');

    // Department response to the assessment fairness entry (feedback #2)
    $responseStmt->execute([
        2,
        $departmentId,
        'The department has reviewed the assessment schedule and will rebalance the assignment windows for the next cycle.',
        'Responded',
        date('c', strtotime('-1 day')),
    ]);

    // Instructor acknowledgment of the positive course materials entry (feedback #4)
    $responseStmt->execute([
        4,
        $instructorId,
        'Thank you for the encouraging feedback. We will keep the worked examples in future lecture notes.',
        'Closed',
        date('c', strtotime('-3 days')),
    ]);
}

// =============================================================================
// AUTHENTICATION
// =============================================================================

/**
 * legacy_authenticate_user — Verify credentials and return the matching user row.
 * Looks up the user by email first, then verifies the bcrypt password hash.
 * Returns null on any failure (user not found OR wrong password) to avoid
 * leaking which part of the check failed (timing-safe via password_verify).
 * The password_hash column is stripped from the returned array for safety.
 *
 * @param string $email     Plain-text email address
 * @param string $password  Plain-text password (compared against bcrypt hash)
 * @return array|null       User row without password_hash, or null on failure
 */
function legacy_authenticate_user(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Return null for both "user not found" and "wrong password" — same response
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Never expose the password hash outside this function
    unset($user['password_hash']);
    return $user;
}

// =============================================================================
// LABEL / BADGE HELPERS
// =============================================================================

/**
 * role_label — Return a human-readable display name for a role key.
 * Falls back to ucfirst($role) for any unrecognised value.
 *
 * @param string $role  Internal role identifier (e.g. 'student_affairs')
 * @return string       Display label (e.g. 'Student Affairs')
 */
function role_label(string $role): string
{
    return [
        'student'        => 'Student',
        'instructor'     => 'Instructor',
        'department'     => 'Department',
        'student_affairs'=> 'Student Affairs',
        'admin'          => 'Administrator',
    ][$role] ?? ucfirst($role);
}

/**
 * role_badge_class — Return the CSS badge class for a role key.
 * Used to colour-code role chips consistently across all views.
 *
 * @param string $role  Internal role identifier
 * @return string       CSS class string (e.g. 'badge badge-indigo')
 */
function role_badge_class(string $role): string
{
    return [
        'student'        => 'badge badge-blue',
        'instructor'     => 'badge badge-indigo',
        'department'     => 'badge badge-amber',
        'student_affairs'=> 'badge badge-rose',
        'admin'          => 'badge badge-slate',
    ][$role] ?? 'badge';
}

/**
 * category_label — Return a human-readable label for a feedback category key.
 * Falls back to ucfirst($category) for unknown categories.
 *
 * @param string $category  Internal category identifier (e.g. 'harassment')
 * @return string           Display label (e.g. 'Sensitive / harassment case')
 */
function category_label(string $category): string
{
    return [
        'course'      => 'Course quality',
        'instructor'  => 'Instructor performance',
        'assessment'  => 'Assessment fairness',
        'harassment'  => 'Sensitive / harassment case',
        'department'  => 'Department issue',
    ][$category] ?? ucfirst($category);
}

/**
 * route_label — Return a human-readable label for a routing target role.
 * Used in audit trails and confirmation messages to show where feedback was sent.
 *
 * @param string $role  Target role identifier
 * @return string       Descriptive routing label
 */
function route_label(string $role): string
{
    return [
        'instructor'     => 'Instructor inbox',
        'department'     => 'Department review',
        'student_affairs'=> 'Student Affairs',
        'admin'          => 'Administrative oversight',
        'student'        => 'Student feedback',
    ][$role] ?? ucfirst($role);
}

/**
 * route_for_category — Map a feedback category to its designated target role.
 * This is the single source of truth for the routing logic.
 * Defaults to 'department' for any unrecognised category.
 *
 * @param string $category  Feedback category key
 * @return string           Target role that should receive the feedback
 */
function route_for_category(string $category): string
{
    return [
        'course'      => 'instructor',     // Course issues go to the course instructor
        'instructor'  => 'instructor',     // Instructor performance also goes to the instructor
        'assessment'  => 'department',     // Assessment concerns go to the department
        'department'  => 'department',     // General department issues stay at department level
        'harassment'  => 'student_affairs',// Sensitive cases are escalated to Student Affairs
    ][$category] ?? 'department';
}

/**
 * severity_badge_class — Return the CSS badge class for a severity level.
 * Green = low risk, Rose = critical — provides a quick visual risk indicator.
 *
 * @param string $severity  Severity value (Low / Medium / High / Critical)
 * @return string           CSS class string
 */
function severity_badge_class(string $severity): string
{
    return [
        'Low'      => 'badge badge-green',
        'Medium'   => 'badge badge-blue',
        'High'     => 'badge badge-amber',
        'Critical' => 'badge badge-rose',
    ][$severity] ?? 'badge badge-slate';
}

/**
 * status_badge_class — Return the CSS badge class for a feedback lifecycle status.
 * Blue = newly submitted, Amber = closed, Green = has a response.
 *
 * @param string $status  Status value (Submitted / Seen / Responded / Closed)
 * @return string         CSS class string
 */
function status_badge_class(string $status): string
{
    return [
        'Submitted' => 'badge badge-blue',
        'Seen'      => 'badge badge-slate',
        'Responded' => 'badge badge-green',
        'Closed'    => 'badge badge-amber',
    ][$status] ?? 'badge badge-slate';
}

// =============================================================================
// DATE / TIME HELPERS
// =============================================================================

/**
 * format_time — Format an ISO-8601 or MySQL datetime string as "Mon D, YYYY".
 * Returns an empty string for null input or strings that cannot be parsed.
 *
 * @param string|null $value  Datetime string to format
 * @return string             Formatted date (e.g. "Jan 3, 2025") or ''
 */
function format_time(?string $value): string
{
    if (!$value) {
        return '';
    }

    $time = strtotime($value);
    if ($time === false) {
        return ''; // Unparseable date — fail silently
    }

    return date('M j, Y', $time);
}

/**
 * relative_time — Return a fuzzy human-readable time difference from now.
 * Thresholds:
 *   < 60 s      → "just now"
 *   < 1 hour    → "N minute(s) ago"
 *   < 1 day     → "N hour(s) ago"
 *   ≥ 1 day     → "N day(s) ago"
 *
 * Returns an empty string for null or unparseable input.
 *
 * @param string|null $value  Datetime string to compare against now
 * @return string             Relative time string or ''
 */
function relative_time(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return ''; // Unparseable date — fail silently
    }

    // Elapsed seconds since the event
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

// =============================================================================
// DATA ACCESS HELPERS
// =============================================================================

/**
 * legacy_all_feedback — Fetch every feedback row with all joined display data.
 * Joins: student info, course details, instructor name, and the most recent response.
 * The subquery "latest" finds the highest response ID per feedback entry so only
 * the newest response fields are included in each row.
 * Results are ordered newest-first (created_at DESC, then id DESC for ties).
 *
 * @param PDO $pdo  Active database connection
 * @return array    Flat array of feedback rows with joined columns
 */
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
            -- Subquery: find the most recent response ID for each feedback entry
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

/**
 * legacy_feedback_responses — Fetch all responses for a single feedback entry.
 * Joins the responder's name and role for display in the conversation thread.
 * Ordered chronologically (oldest first) so the thread reads top-to-bottom.
 *
 * @param PDO $pdo         Active database connection
 * @param int $feedbackId  ID of the feedback entry whose responses to retrieve
 * @return array           Ordered array of response rows with responder info
 */
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

// =============================================================================
// ACCESS CONTROL
// =============================================================================

/**
 * can_access_feedback — Determine whether a user is permitted to view a feedback row.
 * Access rules per role:
 *   admin          — can see everything
 *   student        — can only see their own submissions
 *   instructor     — can see feedback routed to 'instructor' for their own courses only
 *   department     — can see department-routed feedback, but NOT harassment cases
 *   student_affairs— can see all student_affairs-routed feedback
 *
 * @param array $user      Authenticated user array (must have 'role' and 'id' keys)
 * @param array $feedback  Feedback row (must have 'target_role', 'student_id', 'instructor_id', 'category')
 * @return bool            True if the user may read this feedback entry
 */
function can_access_feedback(array $user, array $feedback): bool
{
    return match ($user['role']) {
        'admin'          => true,
        'student'        => (int) $feedback['student_id']   === (int) $user['id'],
        'instructor'     => $feedback['target_role'] === 'instructor'
                            && (int) $feedback['instructor_id'] === (int) $user['id'],
        // Departments cannot see harassment cases — those belong to student_affairs
        'department'     => $feedback['target_role'] === 'department'
                            && $feedback['category'] !== 'harassment',
        'student_affairs'=> $feedback['target_role'] === 'student_affairs',
        default          => false,
    };
}

/**
 * accessible_feedback — Filter a flat array of feedback rows to those the user may view.
 * Wraps can_access_feedback() for use on bulk result sets.
 * array_values() re-indexes the result so callers get a dense 0-based array.
 *
 * @param array $user  Authenticated user array
 * @param array $rows  All feedback rows to filter
 * @return array       Only the rows the user is permitted to see
 */
function accessible_feedback(array $user, array $rows): array
{
    return array_values(array_filter($rows, fn(array $feedback) => can_access_feedback($user, $feedback)));
}

// =============================================================================
// AGGREGATION / METRICS HELPERS
// =============================================================================

/**
 * count_by — Count how many rows in $rows share each unique value of $key.
 * Returns an associative array of [value => count] pairs.
 * Rows where $key is absent or null are silently skipped.
 *
 * @param array  $rows  Flat array of associative arrays
 * @param string $key   Column/key to group and count by
 * @return array        e.g. ['Submitted' => 3, 'Closed' => 1]
 */
function count_by(array $rows, string $key): array
{
    $result = [];
    foreach ($rows as $row) {
        $value = $row[$key] ?? null;
        if ($value === null) {
            continue; // Skip rows with a missing or null key
        }
        $result[$value] = ($result[$value] ?? 0) + 1;
    }
    return $result;
}

/**
 * dashboard_metrics — Calculate the four headline KPI counts shown on role dashboards.
 * Filters $feedbackRows through accessible_feedback() first so each role only
 * sees their own data, then counts by lifecycle stage.
 *
 * @param array $user          Authenticated user array
 * @param array $feedbackRows  All feedback rows (will be filtered internally)
 * @return array{submitted: int, pending: int, closed: int, responded: int}
 */
function dashboard_metrics(array $user, array $feedbackRows): array
{
    $myRows    = accessible_feedback($user, $feedbackRows);
    $submitted = count($myRows);
    // "Pending" = not yet actioned (Submitted or Seen, but not Responded or Closed)
    $pending   = count(array_filter($myRows, fn($row) => $row['status'] === 'Submitted' || $row['status'] === 'Seen'));
    $closed    = count(array_filter($myRows, fn($row) => $row['status'] === 'Closed'));
    $responded = count(array_filter($myRows, fn($row) => $row['status'] === 'Responded'));

    return compact('submitted', 'pending', 'closed', 'responded');
}

/**
 * legacy_user_courses — Fetch the courses relevant to a given user based on their role.
 * - Students  : all courses in their department (they are enrolled across the department)
 * - Instructors: only courses they personally teach
 * - All others : every course in the system (admin/department-level access)
 *
 * @param PDO   $pdo   Active database connection
 * @param array $user  Authenticated user array
 * @return array       Course rows with an added 'instructor_name' column
 */
function legacy_user_courses(PDO $pdo, array $user): array
{
    if ($user['role'] === 'student') {
        // Students see all courses offered in their own department
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
        // Instructors only see courses they are assigned to teach
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

    // Admins, department staff, and student_affairs see all courses
    return $pdo->query('
        SELECT c.*, u.name AS instructor_name
        FROM courses c
        JOIN users u ON u.id = c.instructor_id
        ORDER BY c.code ASC
    ')->fetchAll();
}

/**
 * feedback_summary — Return a breakdown of a set of feedback rows by status.
 * Produces the five-key array used by analytics and report views.
 *
 * @param array $rows  Feedback rows (pre-filtered for the current user)
 * @return array{total: int, submitted: int, seen: int, responded: int, closed: int}
 */
function feedback_summary(array $rows): array
{
    return [
        'total'     => count($rows),
        'submitted' => count(array_filter($rows, fn($row) => $row['status'] === 'Submitted')),
        'seen'      => count(array_filter($rows, fn($row) => $row['status'] === 'Seen')),
        'responded' => count(array_filter($rows, fn($row) => $row['status'] === 'Responded')),
        'closed'    => count(array_filter($rows, fn($row) => $row['status'] === 'Closed')),
    ];
}

/**
 * latest_response — Return a one-line summary of the most recent staff response.
 * Shows "Awaiting response" when no responder has replied yet.
 * The joined responder_name and status columns come from legacy_all_feedback().
 *
 * @param array $feedback  Feedback row with 'responder_name' and 'status' columns
 * @return string          E.g. "Dr. Alemu · Responded" or "Awaiting response"
 */
function latest_response(array $feedback): string
{
    if (empty($feedback['responder_name'])) {
        return 'Awaiting response';
    }

    return $feedback['responder_name'] . ' · ' . $feedback['status'];
}

/**
 * text_excerpt — Truncate a string to $limit characters, appending an ellipsis if cut.
 * Uses mb_strlen/mb_substr for safe multibyte (UTF-8) handling.
 *
 * @param string $text   Full text to potentially truncate
 * @param int    $limit  Maximum character count before truncation (default 120)
 * @return string        Original text if short enough, otherwise truncated + '…'
 */
function text_excerpt(string $text, int $limit = 120): string
{
    if (mb_strlen($text) <= $limit) {
        return $text; // No truncation needed
    }

    // -1 to leave room for the ellipsis character without exceeding $limit
    return mb_substr($text, 0, $limit - 1) . '…';
}

/**
 * feedback_subject — Build a full display subject line for a feedback entry.
 * Combines the course code + title (or a generic fallback) with the feedback subject.
 * Used in list views and notification messages where context-rich labels are helpful.
 *
 * @param array $feedback  Feedback row containing 'course_code', 'course_title', and 'subject'
 * @return string          E.g. "CS101 · Intro to CS · Lecture pacing" or "General academic issue · ..."
 */
function feedback_subject(array $feedback): string
{
    // Build the course prefix; fall back gracefully when no course is linked
    $course = $feedback['course_code']
        ? ($feedback['course_code'] . ' · ' . $feedback['course_title'])
        : 'General academic issue';

    return $course . ' · ' . $feedback['subject'];
}