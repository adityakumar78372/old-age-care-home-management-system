<?php
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Unauthorized access.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

// Require POST + CSRF to prevent accidental/malicious GET triggers
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid request. Use the approval form.');
    header("Location: approvals.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id > 0) {
    try {
        // Rejected free request becomes paid — use a neutral default
        $stmt = $conn->prepare("UPDATE residents SET approval_status = 'rejected' WHERE id = ? AND resident_type = 'free'");
        if ($stmt->execute([$id])) {
            set_flash_message('warning', 'Resident free request rejected. They remain in the system but are not admitted.');
        } else {
            set_flash_message('error', 'Failed to reject resident.');
        }
    } catch (PDOException $e) {
        set_flash_message('error', 'Database error: ' . $e->getMessage());
    }
}

header("Location: approvals.php");
exit;
?>
