<?php
$page_title = 'Edit Staff';
require_once '../../db_connect.php';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Access denied.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    header("Location: " . APP_URL . "/modules/staff/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_staff'])) {
    $name    = trim($_POST['name'] ?? '');
    $role    = $_POST['role'] ?? '';
    $contact = trim($_POST['contact'] ?? '');
    $shift   = $_POST['shift'] ?? '';

    if (empty($name) || empty($role) || empty($shift)) {
        $error = "Name, Role and Shift are required.";
    } elseif (!empty($contact) && !preg_match('/^\d{10}$/', $contact)) {
        $error = "Contact must be exactly 10 digits.";
    } else {
        try {
            $update = $conn->prepare("UPDATE staff SET name=?, role=?, contact=?, shift=? WHERE id=?");
            $update->execute([$name, $role, $contact, $shift, $id]);
            set_flash_message('success', "Staff member updated successfully!");
            header("Location: " . APP_URL . "/modules/staff/index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Staff update error: " . $e->getMessage());
            $error = "Error updating staff. Please try again.";
        }
    }
}
?>

<div class="row justify-content-center g-4">
    <div class="col-lg-6">
        <div class="card-widget">
            <h5 class="fw-bold mb-4">
                <a href="index.php" class="text-decoration-none text-secondary me-2"><i class="fas fa-arrow-left"></i></a>
                Edit Staff Member
            </h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($staff['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="Nurse" <?php echo ($staff['role']=='Nurse')?'selected':''; ?>>Nurse</option>
                        <option value="Doctor" <?php echo ($staff['role']=='Doctor')?'selected':''; ?>>Doctor</option>
                        <option value="Caretaker" <?php echo ($staff['role']=='Caretaker')?'selected':''; ?>>Caretaker</option>
                        <option value="Cleaner" <?php echo ($staff['role']=='Cleaner')?'selected':''; ?>>Cleaner</option>
                        <option value="Cook" <?php echo ($staff['role']=='Cook')?'selected':''; ?>>Cook</option>
                        <option value="Manager" <?php echo ($staff['role']=='Manager')?'selected':''; ?>>Manager</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact</label>
                    <input type="tel" name="contact" class="form-control" value="<?php echo htmlspecialchars($staff['contact']); ?>" required placeholder="e.g. 1234567890" pattern="[0-9]{10}" maxlength="10" title="Please enter exactly 10 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                </div>
                <div class="mb-4">
                    <label class="form-label">Shift</label>
                    <select name="shift" class="form-select" required>
                        <option value="Morning" <?php echo ($staff['shift']=='Morning')?'selected':''; ?>>Morning (6 AM - 2 PM)</option>
                        <option value="Evening" <?php echo ($staff['shift']=='Evening')?'selected':''; ?>>Evening (2 PM - 10 PM)</option>
                        <option value="Night" <?php echo ($staff['shift']=='Night')?'selected':''; ?>>Night (10 PM - 6 AM)</option>
                    </select>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_staff" class="btn btn-primary-custom">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
