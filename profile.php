<?php
require_once __DIR__ . '/config.php';

$user = require_login();
$flash = flash_get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    // Basic validation
    if ($name === '' || $email === '') {
        flash_set('error', 'Name and email are required.');
        header('Location: profile.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid email address.');
        header('Location: profile.php');
        exit;
    }

    try {
        $updated = update_user_profile_record(
            (int) $user['id'],
            $name,
            strtolower($email), // normalize email
            $password !== '' ? $password : null
        );

        if ($updated) {
            // Refresh session user safely
            $_SESSION['user'] = array_merge($user, $updated);

            flash_set('success', 'Profile updated successfully.');

            log_audit(
                (int) $user['id'],
                'profile.updated',
                'user',
                (int) $user['id'],
                ['email' => $email]
            );
        } else {
            flash_set('error', 'No changes were made.');
        }
    } catch (Throwable $exception) {
        // Avoid exposing internal errors
        flash_set('error', 'That email may already be in use.');
    }

    header('Location: profile.php');
    exit;
}

// Limit notifications more safely
$latestNotifications = array_slice(
    notifications_for_user((int) $user['id']) ?? [],
    0,
    3
);
?>