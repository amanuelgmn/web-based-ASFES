<?php
require_once __DIR__ . '/config.php';

// Ensure user has permission to access inbox
$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);

// Load feedback accessible to this user
$allRows = accessible_feedback($user, all_feedback() ?? []);

// Flash message (success / error)
$flash = flash_get();

// Get search and filter inputs
$query = trim(request_string('q')); // keyword search
$filterStatus = request_string('status', 'all'); // status filter
$filterCategory = request_string('category', 'all'); // category filter

// Pagination setup
$page = max(1, request_int('page', 1));
$perPage = 6;

// Apply filtering logic
$rows = array_values(array_filter($allRows, function (array $row) use ($query, $filterStatus, $filterCategory): bool {

    // Search across multiple fields
    if (
        $query !== '' &&
        stripos($row['subject'], $query) === false &&
        stripos($row['message'], $query) === false &&
        stripos((string) ($row['course_code'] ?? ''), $query) === false &&
        stripos((string) ($row['student_name'] ?? ''), $query) === false
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

    return true;
}));

// Pagination calculation
$pagination = pagination_meta(count($rows), $page, $perPage);
$offset = ($pagination['page'] - 1) * $pagination['per_page'];

// Slice rows for current page
$visibleRows = array_slice($rows, $offset, $pagination['per_page']);

// Determine selected feedback item
$selectedId = (int) ($_GET['id'] ?? ($rows[0]['id'] ?? 0));
$selected = null;

// Find selected row
foreach ($rows as $row) {
    if ((int) $row['id'] === $selectedId) {
        $selected = $row;
        break;
    }
}

// Fallback to first row if selected not found
if (!$selected && $rows) {
    $selected = $rows[0];
}

// Handle form submission (update status / add response)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
    $status = normalize_status((string) ($_POST['status'] ?? 'Seen'));
    $message = trim((string) ($_POST['response'] ?? ''));

    // Verify user still has access to this feedback
    $target = null;
    foreach ($allRows as $row) {
        if ((int) $row['id'] === $feedbackId) {
            $target = $row;
            break;
        }
    }

    // Block unauthorized access
    if (!$target) {
        flash_set('error', 'You do not have access to that feedback item.');
        header('Location: respond.php');
        exit;
    }

    // Update feedback status
    update_feedback_status($feedbackId, $status);

    // Insert response if message is provided
    if ($message !== '') {
        insert_response($feedbackId, (int) $user['id'], $message, $status);
    }

    // Success feedback and redirect
    flash_set('success', 'Feedback updated successfully.');
    header('Location: respond.php?id=' . $feedbackId);
    exit;
}

// Load related data for selected feedback
$responses = $selected ? feedback_responses((int) $selected['id']) ?? [] : [];
$attachments = $selected ? feedback_attachments((int) $selected['id']) ?? [] : [];

?>