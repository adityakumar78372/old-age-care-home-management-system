<?php
require_once __DIR__ . '/config.php'; // Load FIRST so APP_URL is available
session_start();
$_SESSION = [];
session_destroy();
header("Location: " . APP_URL . "/index.php");
exit;
?>
