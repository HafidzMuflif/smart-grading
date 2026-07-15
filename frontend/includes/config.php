<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_grading');
define('DB_USER', 'postgres');
define('DB_PASS', '12345');

// API configuration
define('API_BASE_URL', 'http://localhost:8000');
define('API_TIMEOUT', 300); // 5 minutes

// Application configuration
define('APP_NAME', 'Smart Grading');
define('APP_VERSION', '1.0.0');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'txt', 'csv']);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// BASE_URL: path web ke folder frontend/ (dihitung otomatis, bukan hardcode)
// Supaya link navbar & asset tetap benar walau dipanggil dari classes/, students/, exams/, dll.
$frontendFsPath = str_replace('\\', '/', dirname(__DIR__));
$docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
if ($docRoot && strpos($frontendFsPath, $docRoot) === 0) {
    define('BASE_URL', substr($frontendFsPath, strlen($docRoot)));
} else {
    // Fallback kalau DOCUMENT_ROOT tidak terbaca
    define('BASE_URL', '/smart-grading/frontend');
}
?>