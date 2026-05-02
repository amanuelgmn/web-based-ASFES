<?php
require_once __DIR__ . '/config.php';

$user = require_role(['instructor', 'department', 'student_affairs', 'admin']);

$allRows = accessible_feedback($user, all_feedback() ?? []);
$flash = flash_get();

$query = trim(request_string('q'));
$filterStatus = request_string('status', 'all');
$filterCategory = request_string('category', 'all');

$page = max(1, request_int('page', 1));
$perPage = 6;

$rows = array_values(array_filter($allRows, function (array $row) use ($query, $filterStatus, $filterCategory): bool {

    if (
        $query !== '' &&
        stripos($row['subject'], $query) === false &&
        stripos($row['message'], $query) === false &&
        stripos((string) ($row['course_code'] ?? ''), $query) === false &&
        stripos((string) ($row['student_name'] ?? ''), $query) === false
    ) {
        return false;
    }

    if ($filterStatus !== 'all' && $row['status'] !== $filterStatus) return false;
    if ($filterCategory !== 'all' && $row['category'] !== $filterCategory) return false;

    return true;
}));

$pagination = pagination_meta(count($rows), $page, $perPage);
$offset = ($pagination['page'] - 1) * $pagination['per_page'];
$visibleRows = array_slice($rows, $offset, $pagination['per_page']);

// Selected item
$selectedId = (int) ($_GET['id'] ?? ($rows[0]['id'] ?? 0));
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

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $feedbackId = (int) ($_POST['feedback_id'] ?? 0);
    $status = normalize_status((string) ($_POST['status'] ?? 'Seen'));
    $message = trim((string) ($_POST['response'] ?? ''));

    // Find target safely
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

//  Data loading
$responses = $selected ? feedback_responses((int) $selected['id']) ?? [] : [];
$attachments = $selected ? feedback_attachments((int) $selected['id']) ?? [] : [];

?>