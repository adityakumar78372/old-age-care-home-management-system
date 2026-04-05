<?php
session_start();
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// Role check — only admins can delete residents
if ($_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Access denied. Admin only.');
    header("Location: " . APP_URL . "/modules/residents/index.php");
    exit;
}

// SECURITY FIX: Only accept POST to prevent CSRF via GET (link prefetch, image tags, etc.)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . APP_URL . "/modules/residents/index.php");
    exit;
}

// CSRF token verification
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header("Location: " . APP_URL . "/modules/residents/index.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM residents WHERE id = ?");
        $stmt->execute([$id]);
        set_flash_message('success', "Resident deleted successfully.");
    } catch (PDOException $e) {
        set_flash_message('error', "Cannot delete this resident — they may have linked health or payment records.");
    }
}

header("Location: " . APP_URL . "/modules/residents/index.php");
exit;
?>
