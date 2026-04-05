<?php
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'doctor', 'nurse'])) {
    die("Access denied.");
}

$resident_id = isset($_GET['resident_id']) ? (int)$_GET['resident_id'] : 0;

try {
    if ($resident_id > 0) {
        $stmt = $conn->prepare("SELECT h.*, r.name FROM health_records h JOIN residents r ON h.resident_id = r.id WHERE h.resident_id = ? ORDER BY h.checkup_date DESC");
        $stmt->execute([$resident_id]);
    } else {
        $stmt = $conn->query("SELECT h.*, r.name FROM health_records h JOIN residents r ON h.resident_id = r.id ORDER BY h.checkup_date DESC");
    }

    $filename = "Health_Records_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Resident Name', 'Date', 'Temperature', 'Blood Pressure', 'Medicines', 'Doctor Notes']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['checkup_date'],
            $row['temp'],
            $row['blood_pressure'],
            $row['medicines'],
            $row['doctor_visit_notes']
        ]);
    }
    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Error exporting data.");
}
