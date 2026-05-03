<?php
require_once __DIR__ . '/config.php';

// Get logged-in user from session
$user = require_login();

// Get flash messages (success/error messages from previous request)
$flash = flash_get();

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF protection check
    verify_csrf();

    // Collect and sanitize input values
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    // Basic validation: required fields
    if ($name === '' || $email === '') {
        flash_set('error', 'Name and email are required.');
        header('Location: profile.php');
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid email address.');
        header('Location: profile.php');
        exit;
    }

    try {
        // Update user profile in database
        $updated = update_user_profile_record(
            (int) $user['id'],
            $name,
            strtolower($email), // normalize email to lowercase
            $password !== '' ? $password : null // only update password if provided
        );

        // If update succeeded
        if ($updated) {

            // Refresh session data with updated user info
            $_SESSION['user'] = array_merge($user, $updated);

            // Success message
            flash_set('success', 'Profile updated successfully.');

            // Log profile update action for audit tracking
            log_audit(
                (int) $user['id'],
                'profile.updated',
                'user',
                (int) $user['id'],
                ['email' => $email]
            );

        } else {
            // No database changes were made
            flash_set('error', 'No changes were made.');
        }

    } catch (Throwable $exception) {
        // Handle errors (e.g. duplicate email)
        flash_set('error', 'That email may already be in use.');
    }

    // Redirect back to profile page after processing
    header('Location: profile.php');
    exit;
}

// Load latest 3 notifications for dashboard display
$latestNotifications = array_slice(
    notifications_for_user((int) $user['id']) ?? [],
    0,
    3
);
?>