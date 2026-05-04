<?php ob_start(); ?>
<?php
require_once __DIR__ . '/config.php';

// Retrieve the currently logged-in user from the session.
// If no user is logged in, the function will redirect to the login page.
$user = require_login();

// Fetch any flash messages (success or error messages) from the previous request.
$flash = flash_get();

// If the profile update form was submitted:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF token to protect against cross-site request forgery.
    verify_csrf();

    // Collect and sanitize form input.
    $name = trim((string) ($_POST['name'] ?? ''));        // User's full name (required)
    $email = trim((string) ($_POST['email'] ?? ''));      // User's email address (required)
    $password = trim((string) ($_POST['password'] ?? ''));// New password (optional)

    // Validate required fields.
    if ($name === '' || $email === '') {
        flash_set('error', 'Name and email are required.'); // Set error if missing input
        header('Location: profile.php');
        exit;
    }

    // Validate the format of the provided email.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid email address.');
        header('Location: profile.php');
        exit;
    }

    try {
        // Attempt to update the user's data in the database.
        // Only update the password if a new one is provided.
        $updated = update_user_profile_record(
            (int) $user['id'],
            $name,
            strtolower($email), // Always store email in lowercase
            $password !== '' ? $password : null // Update password only if given
        );

        // If the profile was successfully updated (database changes occurred)
        if ($updated) {

            // Update the session with the new user information.
            $_SESSION['user'] = array_merge($user, $updated);

            // Set a success flash message for the user.
            flash_set('success', 'Profile updated successfully.');

            // Log this profile update for auditing and tracking purposes.
            log_audit(
                (int) $user['id'],       // Who made the change
                'profile.updated',       // What action (profile updated)
                'user',                  // Resource type
                (int) $user['id'],       // Resource ID
                ['email' => $email]      // Extra contextual info
            );

        } else {
            // No changes: input matched existing data.
            flash_set('error', 'No changes were made.');
        }

    } catch (Throwable $exception) {
        // Handle potential errors, such as attempting to use a duplicate email.
        flash_set('error', 'That email may already be in use.');
    }

    // After processing the form, redirect to this page so browser refresh won't resubmit the form.
    header('Location: profile.php');
    exit;
}

// For dashboard/notification widget: get the user's latest (3) notifications
$latestNotifications = array_slice(
    notifications_for_user((int) $user['id']) ?? [],
    0,
    3
);

?>
