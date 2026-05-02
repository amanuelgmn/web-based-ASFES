<?php
require_once __DIR__ . '/config.php';

// Ensure user has one of the allowed roles
$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);

// Fetch all feedback (fallback to empty array if null)
$feedbackRows = all_feedback() ?? [];

// Admin sees all, others see only accessible feedback
$accessible = $user['role'] === 'admin'
    ? $feedbackRows
    : accessible_feedback($user, $feedbackRows);

// Flash message (success/error)
$flash = flash_get();

// Get filter/search inputs
$query = trim(request_string('q')); // search keyword
$filterStatus = request_string('status', 'all'); // status filter
$filterCategory = request_string('category', 'all'); // category filter
$filterTarget = request_string('target', 'all'); // routing target filter
$fromDate = request_string('from'); // start date
$toDate = request_string('to'); // end date

// Pagination setup
$page = max(1, request_int('page', 1));
$perPage = 6;

// Apply filtering logic
$filtered = array_values(array_filter($accessible, function (array $row) use (
    $query,
    $filterStatus,
    $filterCategory,
    $filterTarget,
    $fromDate,
    $toDate
): bool {

    // Search: match subject, message, or course code
    if (
        $query !== '' &&
        stripos($row['subject'], $query) === false &&
        stripos($row['message'], $query) === false &&
        stripos((string) ($row['course_code'] ?? ''), $query) === false
    ) {
        return false;
    }

    // Status filter
    if ($filterStatus !== 'all' && $row['status'] !== $filterStatus) {
        return false;
    }

    // Category filter
    if ($filterCategory !== 'all' && $row['category'] !== $filterCategory) {
        return false;
    }

    // Target (routing) filter
    if ($filterTarget !== 'all' && $row['target_role'] !== $filterTarget) {
        return false;
    }

    // Convert created_at to timestamp (safe check)
    $created = strtotime((string) ($row['created_at'] ?? ''));
    if ($created === false) {
        return false; // skip invalid dates
    }

    // From date filter (start of day)
    if ($fromDate !== '' && $created < strtotime($fromDate . ' 00:00:00')) {
        return false;
    }

    // To date filter (end of day)
    if ($toDate !== '' && $created > strtotime($toDate . ' 23:59:59')) {
        return false;
    }

    return true;
}));

// Summary stats (total, closed, etc.)
$summary = feedback_summary($filtered);

// Count grouped values
$byStatus = count_by($filtered, 'status');
$byCategory = count_by($filtered, 'category');

// Build top courses list (most frequent)
$topCourses = [];
foreach ($filtered as $row) {
    $key = $row['course_code'] ?? 'General';
    $topCourses[$key] = ($topCourses[$key] ?? 0) + 1;
}
arsort($topCourses); // sort descending
$topCourses = array_slice($topCourses, 0, 6, true); // keep top 6

// Pagination calculation
$pagination = pagination_meta(count($filtered), $page, $perPage);
$offset = ($pagination['page'] - 1) * $pagination['per_page'];

// Only rows for current page
$visibleRows = array_slice($filtered, $offset, $pagination['per_page']);

// Calculate response times (in seconds)
$responseSamples = array_values(array_filter(
    array_map(fn(array $row) => response_time_seconds($row), $filtered),
    fn($v) => $v !== null
));

// Average response time
$averageResponseSeconds = $responseSamples
    ? (int) round(array_sum($responseSamples) / count($responseSamples))
    : null;

// Count overdue items (based on SLA)
$overdueCount = count(array_filter(
    $filtered,
    fn(array $row) => (sla_state($row)['state'] ?? '') === 'Overdue'
));

// CSV export handling
if (($_GET['export'] ?? '') === 'csv') {

    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asfes-report.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // CSV column headers
    fputcsv($output, ['Subject', 'Category', 'Target', 'Status', 'Submitted', 'Course', 'SLA Due']);

    // Write each row
    foreach ($filtered as $row) {
        fputcsv($output, [
            $row['subject'],
            category_label($row['category']),
            route_label($row['target_role']),
            $row['status'],
            format_time($row['created_at']),
            $row['course_code'] ?? 'General issue',
            format_time($row['sla_due_at'] ?? null),
        ]);
    }

    fclose($output);
    exit;
}
?>