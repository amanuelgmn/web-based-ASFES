<?php

require_once __DIR__ . '/config.php';
// Loads configuration and likely starts the session (often session_start() is in config.php).

// Start logout process: clear all session data
$_SESSION = [];
// Empties the $_SESSION array so all session variables are removed.

// If the session uses cookies, remove the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',                // Set value to empty string
        time() - 42000,    // Set cookie expiration to a time in the past (deletes cookie)
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
    // This removes the PHP session cookie from the user's browser if sessions use cookies.
}

// Destroy the session on the server
session_destroy();
// Physically deletes the session data from the server.

// Redirect user back to homepage after logout
header('Location: index.php');
exit;
// After cleaning up, the user is redirected to the home (index.php). 'exit' ensures no further code runs.
