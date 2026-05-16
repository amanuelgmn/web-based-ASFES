<?php

// Load application configuration (session setup, helper functions, constants, etc.)
require_once __DIR__ . '/config.php';

// Prevent the browser from caching authenticated pages after logout.
// no-store  : do not save the response anywhere (memory or disk)
// no-cache  : always revalidate before serving from cache
// must-revalidate : stale cached copies must not be used under any circumstance
// max-age=0 : treat the response as immediately expired
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
// Pragma: no-cache is the HTTP/1.0 equivalent — included for older proxy/browser compatibility
header('Pragma: no-cache');

// Start logout process: clear all session data.
// Overwriting $_SESSION with an empty array wipes all stored values (user, CSRF token, flash, etc.)
// without destroying the underlying session file yet — that happens below via session_destroy()
$_SESSION = [];

// Delete the session cookie from the browser if cookies are being used to track the session.
// Simply calling session_destroy() on the server is not enough — the browser would keep sending
// the old (now invalid) session ID on subsequent requests.
if (ini_get('session.use_cookies')) {
    // Retrieve the exact cookie parameters that were set when the session started
    // (path, domain, secure, httponly) so the replacement cookie matches precisely
    $params = session_get_cookie_params();

    // Overwrite the session cookie with an empty value and an expiry in the past
    // (time() - 42000) to instruct the browser to delete it immediately
    setcookie(
        session_name(),  // The cookie name (typically 'PHPSESSID')
        '',              // Empty value — effectively blanks the cookie
        time() - 42000, // Expiry timestamp in the past — triggers browser deletion
        $params['path'],
        $params['domain'],
        $params['secure'],   // Honour the original Secure flag
        $params['httponly']  // Honour the original HttpOnly flag
    );
}

// Destroy the server-side session file/data now that the cookie has been invalidated
session_destroy();

// Redirect to the login page — the user is fully logged out at this point
header('Location: index.php');
exit;