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
    header("Location: " . APP_URL . "/modules/rooms/index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash_message('error', 'Invalid security token. Please try again.');
    header("Location: " . APP_URL . "/modules/rooms/index.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    try {
        // Check if any active residents are in this room
        $check = $conn->prepare("SELECT COUNT(*) FROM residents WHERE room_id = ? AND status='active'");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            set_flash_message('error', "Cannot delete room — active residents are assigned to it.");
        } else {
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$id]);
            set_flash_message('success', "Room deleted successfully.");
        }
    } catch (PDOException $e) {
        set_flash_message('error', "Cannot delete room. Please ensure no residents are assigned.");
    }
}
header("Location: " . APP_URL . "/modules/rooms/index.php");
exit;
?>
