<?php

require_once __DIR__ . '/config.php';

// 🧹 CLEAR SESSION ARRAY
$_SESSION = [];

// 🍪 REMOVE SESSION COOKIE (if cookies enabled)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 🧨 DESTROY SESSION
session_destroy();

// 🔁 REDIRECT TO HOME
header('Location: index.php');
exit;