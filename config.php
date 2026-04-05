<?php
// config.php - Database Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'oahmsdb_backup2');

define('APP_NAME', 'Old Age Home Management System');
if (php_sapi_name() !== 'cli') {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root_path = str_replace('\\', '/', realpath(__DIR__));
    $doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base_dir = str_replace($doc_root, '', $root_path);
    define('APP_URL', $protocol . '://' . $host . '/' . ltrim($base_dir, '/'));
} else {
    define('APP_URL', 'http://localhost/projectbackup2'); // Fallback for CLI
}
define('APP_ENV', 'development'); // Set to 'production' on live server to hide errors
