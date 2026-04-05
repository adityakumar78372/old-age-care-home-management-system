<?php
$page_title = 'Staff Management';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Access denied. Admin only.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

// Handle Add Staff
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_staff'])) {
    $name       = trim($_POST['name'] ?? '');
    $role       = $_POST['role'] ?? '';
    $contact    = trim($_POST['contact'] ?? '');
    $shift      = $_POST['shift'] ?? '';
    $join_date  = $_POST['joining_date'] ?? date('Y-m-d');
    $create_login = isset($_POST['create_login']) ? 1 : 0;

    // Server-side validation
    if (empty($name) || empty($role) || empty($shift)) {
        $error = "Name, Role, and Shift are required.";
    } elseif (!empty($contact) && !preg_match('/^\d{10}$/', $contact)) {
        $error = "Contact must be exactly 10 digits.";
    } else {
        try {
            $conn->beginTransaction();

            $user_id = null;
            $successMsg = "Staff member added successfully!";

            if ($create_login) {
                // Map job title to system login role
                $role_to_system = [
                    'Nurse'     => 'nurse',
                    'Doctor'    => 'doctor',
                    'Cook'      => 'cook',
                    'Manager'   => 'manager',
                    'Caretaker' => 'staff',
                    'Cleaner'   => 'staff',
                    'Security'  => 'staff',
                ];
                $system_role = $role_to_system[$role] ?? 'staff';
                $username = strtolower(preg_replace('/\s+/', '', $name)) . rand(10, 99);
                $password = password_hash('staff123', PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $password, $system_role]);
                $user_id = $conn->lastInsertId();
                $successMsg = "Staff & login added! Username: {$username} | Temp Password: staff123 | System Role: {$system_role}";
            }

            $stmt = $conn->prepare("INSERT INTO staff (name, user_id, role, contact, shift, joining_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $user_id, $role, $contact, $shift, $join_date]);

            $conn->commit();
            set_flash_message('success', $successMsg);
            header("Location: " . APP_URL . "/modules/staff/index.php");
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Add staff error: " . $e->getMessage());
            $error = "Error saving staff member. Please try again.";
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-widget">
            <h5 class="fw-bold mb-4"><i class="fas fa-user-plus me-2 text-primary-custom"></i>Add Staff Member</h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" id="addStaffForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" required>
                        <option value="">-- Select Role --</option>
                        <option value="Nurse">Nurse</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Caretaker">Caretaker</option>
                        <option value="Cleaner">Cleaner</option>
                        <option value="Cook">Cook</option>
                        <option value="Manager">Manager</option>
                        <option value="Security">Security</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Contact Number</label>
                    <input type="tel" name="contact" class="form-control" placeholder="10-digit number" pattern="[0-9]{10}" maxlength="10" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Shift <span class="text-danger">*</span></label>
                    <select name="shift" class="form-select" required>
                        <option value="">-- Select Shift --</option>
                        <option value="Morning">Morning (6 AM – 2 PM)</option>
                        <option value="Evening">Evening (2 PM – 10 PM)</option>
                        <option value="Night">Night (10 PM – 6 AM)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Joining Date</label>
                    <input type="date" name="joining_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="mb-4 p-3 rounded-3" style="background:var(--sidebar-active); border:1px solid var(--border-color)">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="createLogin" name="create_login" value="1" checked>
                        <label class="form-check-label fw-semibold" for="createLogin">Create System Login</label>
                    </div>
                    <small class="text-muted d-block mt-1">Default password: <code>staff123</code> — advise staff to change it.</small>
                </div>
                <button type="submit" name="add_staff" class="btn btn-primary-custom w-100">
                    <i class="fas fa-save me-2"></i>Save Staff Profile
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-widget">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold mb-0"><i class="fas fa-users me-2 text-primary-custom"></i>Staff Directory</h5>
                <div>
                    <input type="text" id="tableSearch" class="form-control" placeholder="Search staff..." style="width:220px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom filterable-table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Shift</th>
                            <th>Contact</th>
                            <th>Login Access</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("
                                SELECT s.*, u.username
                                FROM staff s
                                LEFT JOIN users u ON s.user_id = u.id
                                ORDER BY s.id DESC
                            ");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (empty($rows)) {
                                echo "<tr><td colspan='6' class='text-center text-muted py-4'>No staff members added yet.</td></tr>";
                            }
                            foreach ($rows as $row) {
                                $loginBadge = $row['username']
                                    ? "<span class='badge bg-success'><i class='fas fa-key me-1'></i>" . htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') . "</span>"
                                    : "<span class='badge bg-secondary'>No Access</span>";
                                $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                                $role = htmlspecialchars($row['role'], ENT_QUOTES, 'UTF-8');
                                $shift = htmlspecialchars($row['shift'], ENT_QUOTES, 'UTF-8');
                                $contact = htmlspecialchars($row['contact'] ?? '—', ENT_QUOTES, 'UTF-8');

                                echo "<tr>
                                        <td><strong>{$name}</strong></td>
                                        <td>{$role}</td>
                                        <td><span class='badge bg-info text-dark'>{$shift}</span></td>
                                        <td>{$contact}</td>
                                        <td>{$loginBadge}</td>
                                        <td>
                                            <a href='edit.php?id={$row['id']}' class='btn btn-sm btn-outline-primary me-1' title='Edit'><i class='fas fa-edit'></i></a>
                                            <form method='post' action='delete.php' class='delete-staff-form d-inline'>
                                              <input type='hidden' name='id' value='{$row['id']}'>
                                              <input type='hidden' name='csrf_token' value='" . generate_csrf_token() . "'>
                                              <button type='button' class='btn btn-sm btn-outline-danger delete-staff-btn' title='Delete'><i class='fas fa-trash'></i></button>
                                            </form>
                                        </td>
                                      </tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='6' class='text-danger'>Error loading staff.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.delete-staff-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = this.closest('form.delete-staff-form');
        Swal.fire({
            title: 'Delete Staff Member?',
            text: 'Their login account will also be removed.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
