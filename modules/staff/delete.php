<?php
session_start();
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// SECURITY FIX: POST-only to prevent CSRF via GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . APP_URL . "/modules/staff/index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header("Location: " . APP_URL . "/modules/staff/index.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    try {
        // Find staff's corresponding user_id to delete their login too
        $stmt_user = $conn->prepare("SELECT user_id FROM staff WHERE id = ?");
        $stmt_user->execute([$id]);
        $staff = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // Delete staff profile
        $stmt = $conn->prepare("DELETE FROM staff WHERE id = ?");
        $stmt->execute([$id]);

        // Delete associated user account if it exists
        if ($staff && !empty($staff['user_id'])) {
            $del_user = $conn->prepare("DELETE FROM users WHERE id = ?");
            $del_user->execute([$staff['user_id']]);
        }

        set_flash_message('success', "Staff member deleted successfully.");
    } catch (PDOException $e) {
        set_flash_message('error', "Cannot delete staff member. Please try again.");
    }
}
header("Location: " . APP_URL . "/modules/staff/index.php");
exit;
?>
