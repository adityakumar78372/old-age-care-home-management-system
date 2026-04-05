<?php
$page_title = 'Room Management';
require_once '../../db_connect.php';

// Access control: admin and manager only
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php"); exit;
}
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    set_flash_message('error', 'Access denied. Admin/Manager only.');
    header("Location: " . APP_URL . "/dashboard.php"); exit;
}

require_once '../../includes/header.php';

// Handle Add Room
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_room'])) {
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
            $stmt = $conn->prepare("INSERT INTO rooms (room_number, capacity, room_type, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$room_number, $capacity, $room_type, $status]);
            set_flash_message('success', "Room {$room_number} added successfully!");
            header("Location: " . APP_URL . "/modules/rooms/index.php");
            exit;
        } catch (PDOException $e) {
            $error = "Error adding room: " . $e->getMessage();
        }
    }
}
?>

<div class="row g-4">
    <!-- Add Room Form -->
    <div class="col-lg-4">
        <div class="card-widget sticky-top" style="top: 20px;">
            <h5 class="fw-bold mb-4"><i class="fas fa-plus-circle me-2 text-primary"></i>Add New Room</h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Room Number / Name</label>
                    <input type="text" name="room_number" class="form-control" placeholder="e.g. 101, A-1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Capacity (Beds)</label>
                    <input type="number" name="capacity" class="form-control" value="1" min="1" max="20" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Room Type</label>
                    <select name="room_type" class="form-select">
                        <option value="Non-AC" selected>Non-AC (₹5,000/mo)</option>
                        <option value="AC">AC (₹7,000/mo)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="maintenance">Under Maintenance</option>
                    </select>
                </div>
                <button type="submit" name="add_room" class="btn btn-primary-custom w-100 py-2 fw-bold shadow-sm">
                    Create Room Record
                </button>
            </form>
        </div>
    </div>

    <!-- Room List -->
    <div class="col-lg-8">
        <div class="card-widget">
            <h5 class="fw-bold mb-4">Total Facilities & Occupancy</h5>
            <div class="table-responsive">
                <table class="table table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Room No</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Availability</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("
                                SELECT r.*, 
                                (SELECT COUNT(*) FROM residents WHERE room_id = r.id AND status = 'active') as occupied_beds
                                FROM rooms r
                                ORDER BY CAST(r.room_number AS UNSIGNED) ASC, r.room_number ASC
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $isFull = $row['occupied_beds'] >= $row['capacity'];
                                $isMaintenance = $row['status'] === 'maintenance';

                                if ($isMaintenance) {
                                    $badge = 'bg-warning text-dark';
                                    $statusText = 'Maintenance';
                                } elseif ($isFull) {
                                    $badge = 'bg-danger';
                                    $statusText = 'Full';
                                } else {
                                    $badge = 'bg-success';
                                    $statusText = 'Available';
                                }

                                $availPercent = $row['capacity'] > 0 ? round(($row['occupied_beds'] / $row['capacity']) * 100) : 0;
                                $progressColor = $availPercent >= 100 ? 'bg-danger' : ($availPercent >= 70 ? 'bg-warning' : 'bg-success');
                                $rn = htmlspecialchars($row['room_number'], ENT_QUOTES, 'UTF-8');

                                echo "<tr>
                                        <td><strong>{$rn}</strong></td>
                                        <td><span class='badge badge-soft-primary px-2'>" . ($row['room_type'] ?: 'Non-AC') . "</span></td>
                                        <td>{$row['capacity']} Beds</td>
                                        <td>{$row['occupied_beds']} / {$row['capacity']}</td>
                                        <td style='min-width:100px;'>
                                            <div class='progress' style='height:6px;' title='{$availPercent}% full'>
                                                <div class='progress-bar {$progressColor}' style='width:{$availPercent}%'></div>
                                            </div>
                                        </td>
                                        <td><span class='badge {$badge}'>{$statusText}</span></td>
                                        <td>
                                            <a href='edit.php?id={$row['id']}' class='btn btn-sm btn-outline-primary me-1' title='Edit'><i class='fas fa-edit'></i></a>
                                            <form method='post' action='delete.php' class='delete-room-form d-inline'>
                                              <input type='hidden' name='id' value='{$row['id']}'>
                                              <input type='hidden' name='csrf_token' value='" . generate_csrf_token() . "'>
                                              <button type='button' class='btn btn-sm btn-outline-danger delete-room-btn' title='Delete'><i class='fas fa-trash'></i></button>
                                            </form>
                                        </td>
                                      </tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7' class='text-danger text-center'>Error: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Confirm Delete for Rooms
document.querySelectorAll('.delete-room-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = this.closest('form.delete-room-form');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Room?',
                text: 'All residents assigned to this room will become unassigned.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Delete'
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        } else {
            if (confirm('Delete this room record?')) form.submit();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
