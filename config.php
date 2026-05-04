<?php
declare(strict_types=1);

// 🌍 TIMEZONE
date_default_timezone_set('Africa/Addis_Ababa');

// 🧠 APP CONSTANTS
const APP_NAME = 'ASFES';
const APP_BRAND = 'ASTU SFES';
const APP_SUBTITLE = 'Academic Student Feedback and Evaluation System';

// 🧪 ENVIRONMENT MODE (safe addition)
const APP_ENV = 'development'; // change to 'production' later

// 🔐 ERROR HANDLING (dev vs production)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// 📦 REQUIRED FILES CHECK (prevents fatal crashes)
$requiredFiles = [
    __DIR__ . '/functions.php',
    __DIR__ . '/storage.php'
];

// validate required files exist before loading
foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        die("Critical file missing: " . basename($file));
    }
}

// load core modules
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/storage.php';

// start session safely
start_session();

// 🧱 OUTPUT BUFFERING (safe, prevents header issues globally)
if (!headers_sent()) {
    ob_start();
}