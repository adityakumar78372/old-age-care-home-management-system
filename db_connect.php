<?php
/**
 * db_connect.php - Global Initialization & Database Connection
 * Standardized for OAHMS Institutional Core
 */
if (!ob_get_level()) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helpers.php';
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch(PDOException $e) {
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        die("Database not found! Please run setup.php first to initialize the database: <a href='" . APP_URL . "/setup.php'>Click here to setup</a>");
    } else {
        // Production error handling
        if (defined('APP_ENV') && APP_ENV === 'production') {
            error_log("Database Connection error: " . $e->getMessage());
            die("Database Connection failed. Please try again later or contact administrator.");
        } else {
            die("Database Connection failed: " . $e->getMessage() . "<br>Please ensure XAMPP MySQL is running.");
        }
    }
}
