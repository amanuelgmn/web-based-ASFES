<?php
require_once __DIR__ . '/config.php';

$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);
$feedbackRows = all_feedback() ?? [];

$accessible = $user['role'] === 'admin'
    ? $feedbackRows
    : accessible_feedback($user, $feedbackRows);

$flash = flash_get();

$query = trim(request_string('q'));
$filterStatus = request_string('status', 'all');
$filterCategory = request_string('category', 'all');
$filterTarget = request_string('target', 'all');
$fromDate = request_string('from');
$toDate = request_string('to');

$page = max(1, request_int('page', 1));
$perPage = 6;

$filtered = array_values(array_filter($accessible, function (array $row) use (
    $query,
    $filterStatus,
    $filterCategory,
    $filterTarget,
    $fromDate,
    $toDate
): bool {

    // Search
    if (
        $query !== '' &&
        stripos($row['subject'], $query) === false &&
        stripos($row['message'], $query) === false &&
        stripos((string) ($row['course_code'] ?? ''), $query) === false
    ) {
        return false;
    }

    // Filters
    if ($filterStatus !== 'all' && $row['status'] !== $filterStatus) return false;
    if ($filterCategory !== 'all' && $row['category'] !== $filterCategory) return false;
    if ($filterTarget !== 'all' && $row['target_role'] !== $filterTarget) return false;

    // Date filtering (safer handling)
    $created = strtotime((string) ($row['created_at'] ?? ''));
    if ($created === false) return false;

    if ($fromDate !== '' && $created < strtotime($fromDate . ' 00:00:00')) return false;
    if ($toDate !== '' && $created > strtotime($toDate . ' 23:59:59')) return false;

    return true;
}));

$summary = feedback_summary($filtered);
$byStatus = count_by($filtered, 'status');
$byCategory = count_by($filtered, 'category');

// Top courses
$topCourses = [];
foreach ($filtered as $row) {
    $key = $row['course_code'] ?? 'General';
    $topCourses[$key] = ($topCourses[$key] ?? 0) + 1;
}
arsort($topCourses);
$topCourses = array_slice($topCourses, 0, 6, true);

// Pagination
$pagination = pagination_meta(count($filtered), $page, $perPage);
$offset = ($pagination['page'] - 1) * $pagination['per_page'];
$visibleRows = array_slice($filtered, $offset, $pagination['per_page']);

// Response timing
$responseSamples = array_values(array_filter(
    array_map(fn(array $row) => response_time_seconds($row), $filtered),
    fn($v) => $v !== null
));

$averageResponseSeconds = $responseSamples
    ? (int) round(array_sum($responseSamples) / count($responseSamples))
    : null;

// Overdue
$overdueCount = count(array_filter(
    $filtered,
    fn(array $row) => (sla_state($row)['state'] ?? '') === 'Overdue'
));

// CSV export
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="asfes-report.csv"');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Subject', 'Category', 'Target', 'Status', 'Submitted', 'Course', 'SLA Due']);

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