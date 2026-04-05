<?php
require_once '../../config.php';
session_start();

// Ensure only admins can backup
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access. Administrator privileges required.");
}

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$name = DB_NAME;

// Native PHP MySQL Dump 
$conn = new mysqli($host, $user, $pass, $name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "";
$sqlScript .= "-- Database Backup for $name \n";
$sqlScript .= "-- Generated at: " . date('Y-m-d H:i:s') . " \n\n";

foreach ($tables as $table) {
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    $sqlScript .= "\n\n-- Structure for table `$table`\n";
    $sqlScript .= $row[1] . ";\n\n";
    
    $result = $conn->query("SELECT * FROM `$table`");
    $columnCount = $result->field_count;
    
    $sqlScript .= "-- Data for table `$table`\n";
    while ($row = $result->fetch_row()) {
        $sqlScript .= "INSERT INTO `$table` VALUES(";
        for ($j = 0; $j < $columnCount; $j++) {
            if (isset($row[$j])) {
                $escaped = $conn->real_escape_string($row[$j]);
                $sqlScript .= "'" . $escaped . "'";
            } else {
                $sqlScript .= "NULL";
            }
            if ($j < ($columnCount - 1)) {
                $sqlScript .= ",";
            }
        }
        $sqlScript .= ");\n";
    }
    $sqlScript .= "\n"; 
}

$backup_file_name = $name . '_backup_' . date('Ymd_His') . '.sql';

// Forces Download
header('Content-Type: application/x-sql');
header('Content-Transfer-Encoding: Binary');
header('Content-disposition: attachment; filename="' . $backup_file_name . '"');
header('Pragma: no-cache');
header('Expires: 0');
echo $sqlScript;
exit;
?>
