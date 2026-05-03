<?php

require_once __DIR__ . '/config.php';

// Start logout process: clear all session data
$_SESSION = [];

// If the session uses cookies, remove the session cookie from the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000, // set expiry in the past to delete cookie
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session on the server
session_destroy();

// Redirect user back to homepage after logout
header('Location: index.php');
exit;