<?php
declare(strict_types=1);

// 🌍 Set the application's default timezone
date_default_timezone_set('Africa/Addis_Ababa');

// 🧠 Application-wide constants
const APP_NAME = 'ASFES';
const APP_BRAND = 'ASTU SFES';
const APP_SUBTITLE = 'Academic Student Feedback and Evaluation System';

// 🧪 Application environment: 'development' or 'production'
const APP_ENV = 'development'; // Switch to 'production' when deploying

// 🔐 Error reporting configuration based on environment
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Harden session cookies before the session starts.
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_trans_sid', '0');

// 📦 Ensure all required core files are present before continuing
$requiredFiles = [
    __DIR__ . '/functions.php',
    __DIR__ . '/storage.php'
];

// Validate the existence of required files, halt if missing
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        die("Critical file missing: " . basename($file));
    }
}

// Load essential application modules
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/storage.php';

// Initialize user session safely
start_session();

// 🧱 OUTPUT BUFFERING (safe, prevents header issues globally)
if (!headers_sent()) {
    ob_start();
}
