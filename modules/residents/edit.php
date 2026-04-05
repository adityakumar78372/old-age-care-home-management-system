<?php
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Only admins/managers can access edit page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    set_flash_message('error', 'Access denied. Authorized staff only.');
    header("Location: " . APP_URL . "/modules/residents/index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM residents WHERE id = ?");
$stmt->execute([$id]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    header("Location: " . APP_URL . "/modules/residents/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_resident'])) {
    $name        = trim($_POST['name'] ?? '');
    $dob         = $_POST['dob'] ?? '';
    $gender      = $_POST['gender'] ?? '';
    $contact     = trim($_POST['contact'] ?? '');
    $emergency   = trim($_POST['emergency_contact'] ?? '');
    $room        = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $history     = trim($_POST['medical_history'] ?? '');
    $status      = $_POST['status'] ?? 'active';
    $monthly_fee = !empty($_POST['monthly_fee']) ? (float)$_POST['monthly_fee'] : (float)($resident['monthly_fee'] ?? 5000.00);
    $address     = trim($_POST['address'] ?? '');
    $family_contact = trim($_POST['family_contact'] ?? '');
    $photo_path  = $resident['profile_photo'];

    // Handle File Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/residents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = uniqid('resident_', true) . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_file_name)) {
                // Delete old photo if it exists
                if ($resident['profile_photo'] && file_exists('../../' . $resident['profile_photo'])) {
                    @unlink('../../' . $resident['profile_photo']);
                }
                $photo_path = 'assets/uploads/residents/' . $new_file_name;
            }
        }
    }

    if (empty($name) || empty($dob) || empty($emergency) || empty($address)) {
        $error = "Name, Date of Birth, Emergency Contact, and Address are required.";
    } elseif (!preg_match('/^\d{10}$/', $emergency)) {
        $error = "Emergency contact must be exactly 10 digits.";
    } elseif (!empty($contact) && !preg_match('/^\d{10}$/', $contact)) {
        $error = "Primary contact must be exactly 10 digits.";
    } else {
        try {
            $conn->beginTransaction();

            // Detect Changes for History Logging
            if ($room != $resident['room_id']) {
                $h1 = $conn->prepare("INSERT INTO resident_room_history (resident_id, room_id, start_date) VALUES (?, ?, CURRENT_DATE)");
                $h1->execute([$id, $room, date('Y-m-d')]);
            }
            if ($status != $resident['status']) {
                $h2 = $conn->prepare("INSERT INTO resident_status_history (resident_id, status, change_date) VALUES (?, ?, CURRENT_DATE)");
                $h2->execute([$id, $status, date('Y-m-d')]);
            }

            $update = $conn->prepare("UPDATE residents SET name=?, dob=?, gender=?, contact=?, emergency_contact=?, room_id=?, status=?, medical_history=?, profile_photo=?, monthly_fee=?, address=?, family_contact=? WHERE id=?");
            $update->execute([$name, $dob, $gender, $contact, $emergency, $room, $status, $history, $photo_path, $monthly_fee, $address, $family_contact, $id]);
            
            // Sync Ledger (in case fee changed or catch-up needed)
            syncResidentLedger($id, $conn);

            $conn->commit();
            set_flash_message('success', "Resident updated successfully!");
            header("Location: " . APP_URL . "/modules/residents/view.php?id=" . $id);
            exit;
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $error = "Error saving changes. Please try again.";
            error_log("Resident update error: " . $e->getMessage());
        }
    }
}

$page_title = 'Edit Resident';
require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card-widget">
            <h5 class="fw-bold mb-4"><a href="index.php" class="text-decoration-none text-secondary"><i class="fas fa-arrow-left"></i> Back</a> | Edit Resident Profile</h5>
            
            <?php if(isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-2 mb-3 text-center">
                        <?php 
                        $photo = !empty($resident['profile_photo']) ? APP_URL . '/' . $resident['profile_photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($resident['name']) . '&size=128';
                        ?>
                        <img src="<?php echo $photo; ?>" alt="Profile" class="img-thumbnail rounded-circle mb-2" style="width:100px; height:100px; object-fit:cover;">
                        <input type="file" name="profile_photo" class="form-control form-control-sm" accept="image/*">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($resident['name']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($resident['dob']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="Male" <?php echo ($resident['gender']=='Male')?'selected':'';?>>Male</option>
                            <option value="Female" <?php echo ($resident['gender']=='Female')?'selected':'';?>>Female</option>
                            <option value="Other" <?php echo ($resident['gender']=='Other')?'selected':'';?>>Other</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" name="contact" class="form-control" value="<?php echo htmlspecialchars($resident['contact']); ?>" placeholder="e.g. 1234567890" pattern="[0-9]{10}" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Emergency Contact *</label>
                        <input type="tel" name="emergency_contact" class="form-control phone-prefix" value="<?php echo htmlspecialchars($resident['emergency_contact']); ?>" placeholder="e.g. 1234567890" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Family Contact Number</label>
                        <input type="tel" name="family_contact" class="form-control phone-prefix" value="<?php echo htmlspecialchars($resident['family_contact'] ?? ''); ?>" placeholder="10-digit number" maxlength="15">
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Permanent Address <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($resident['address']); ?></textarea>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Assign Room</label>
                        <select name="room_id" id="room_select" class="form-select">
                            <option value="" data-type="Non-AC">-- No Room Assigned --</option>
                            <?php
                            $roomStmt = $conn->prepare("
                                SELECT r.id, r.room_number, r.capacity, r.room_type,
                                (SELECT COUNT(*) FROM residents WHERE room_id = r.id AND status='active' AND id != ?) AS occ
                                FROM rooms r
                                WHERE r.status != 'maintenance'
                            ");
                            $roomStmt->execute([$id]);
                            while ($rm = $roomStmt->fetch(PDO::FETCH_ASSOC)) {
                                if ($rm['occ'] < $rm['capacity'] || $rm['id'] == $resident['room_id']) {
                                    $sel = ($rm['id'] == $resident['room_id']) ? 'selected' : '';
                                    $rn  = htmlspecialchars($rm['room_number'], ENT_QUOTES, 'UTF-8');
                                    $rt  = htmlspecialchars($rm['room_type'] ?? 'Non-AC', ENT_QUOTES, 'UTF-8');
                                    echo "<option value='{$rm['id']}' {$sel} data-type='{$rt}'>Room {$rn} [{$rt}] ({$rm['capacity']} bed max)</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Monthly Fee (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="monthly_fee" id="monthly_fee" class="form-control" value="<?php echo (float)($resident['monthly_fee'] ?? 5000); ?>" required>
                        </div>
                        <small class="text-muted">Suggested: Non-AC (5000), AC (7000)</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active" <?php echo ($resident['status']=='active')?'selected':'';?>>Active</option>
                            <option value="inactive" <?php echo ($resident['status']=='inactive')?'selected':'';?>>Inactive</option>
                            <option value="deceased" <?php echo ($resident['status']=='deceased')?'selected':'';?>>Deceased</option>
                            <option value="discharged" <?php echo ($resident['status']=='discharged')?'selected':'';?>>Discharged</option>
                        </select>
                    </div>

                    <div class="col-12 mb-4">
                        <label class="form-label">Medical History / Allergies</label>
                        <textarea name="medical_history" class="form-control" rows="4"><?php echo htmlspecialchars($resident['medical_history']); ?></textarea>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" name="update_resident" class="btn btn-primary-custom btn-lg"><i class="fas fa-save me-2"></i>Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomSelect = document.getElementById('room_select');
    const feeInput = document.getElementById('monthly_fee');

    // Phone Number auto-prefix +91
    document.querySelectorAll('.phone-prefix').forEach(input => {
        input.addEventListener('blur', function() {
            let val = this.value.replace(/\D/g, '');
            if (val.length === 10) {
                this.value = '+91' + val;
            } else if (val.length === 12 && val.startsWith('91')) {
                this.value = '+' + val;
            }
        });
    });

    if (roomSelect && feeInput) {
        roomSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const roomType = selectedOption.getAttribute('data-type');
            
            if (roomType === 'AC') {
                feeInput.value = '7000';
            } else if (roomType === 'Non-AC') {
                feeInput.value = '5000';
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
