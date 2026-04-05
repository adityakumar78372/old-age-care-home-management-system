<?php
$page_title = 'Edit Room';
require_once '../../db_connect.php';
require_once '../../includes/header.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Access denied.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    header("Location: " . APP_URL . "/modules/rooms/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_room'])) {
    $room_number = trim($_POST['room_number'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 1);
    $room_type   = $_POST['room_type'] ?? 'Non-AC';
    $status      = $_POST['status'] ?? 'available';

    if (empty($room_number)) {
        $error = "Room number is required.";
    } elseif ($capacity < 1 || $capacity > 20) {
        $error = "Capacity must be between 1 and 20.";
    } else {
        try {
            $update = $conn->prepare("UPDATE rooms SET room_number = ?, capacity = ?, room_type = ?, status = ? WHERE id = ?");
            $update->execute([$room_number, $capacity, $room_type, $status, $id]);
            set_flash_message('success', "Room updated successfully!");
            header("Location: " . APP_URL . "/modules/rooms/index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Room update error: " . $e->getMessage());
            $error = "Error updating room. Please try again.";
        }
    }
}
?>

<div class="row g-4 justify-content-center">
    <div class="col-lg-6">
        <div class="card-widget">
            <h5 class="fw-bold mb-4">
                <a href="index.php" class="text-decoration-none text-secondary me-2"><i class="fas fa-arrow-left"></i></a>
                Edit Room
            </h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Room Number</label>
                    <input type="text" name="room_number" class="form-control" value="<?php echo htmlspecialchars($room['room_number']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Room Type</label>
                    <select name="room_type" class="form-select">
                        <option value="Non-AC" <?php echo ($room['room_type'] == 'Non-AC') ? 'selected' : ''; ?>>Non-AC (₹5,000/mo)</option>
                        <option value="AC" <?php echo ($room['room_type'] == 'AC') ? 'selected' : ''; ?>>AC (₹7,000/mo)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="available" <?php echo ($room['status'] == 'available') ? 'selected' : ''; ?>>Available</option>
                        <option value="maintenance" <?php echo ($room['status'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="occupied" <?php echo ($room['status'] == 'occupied') ? 'selected' : ''; ?>>Occupied</option>
                    </select>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_room" class="btn btn-primary-custom">Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
