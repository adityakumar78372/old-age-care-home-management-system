<?php
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Only admins can access approval page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Access denied. Administrator only.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

$page_title = 'Resident Approvals';
require_once '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card-widget mb-4">
            <h5 class="fw-bold mb-4 border-bottom pb-3"><i class="fas fa-user-check me-2 text-primary"></i> Pending Free Resident Requests</h5>
            
            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age/Gender</th>
                            <th>Reason for Free</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("SELECT id, name, dob, gender, reason_for_free, created_at FROM residents WHERE resident_type='free' AND approval_status='pending' ORDER BY id DESC");
                        $stmt->execute();
                        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($requests)) {
                            echo "<tr><td colspan='5' class='text-center text-muted py-5'>No pending requests found.</td></tr>";
                        } else {
                            foreach ($requests as $row) {
                                $age = date_diff(date_create($row['dob']), date_create('today'))->y;
                                $name = sanitize_html($row['name']);
                                $reason = sanitize_html($row['reason_for_free']);
                                $date = formatDate($row['created_at']);
                                echo "<tr>
                                    <td><strong>{$name}</strong></td>
                                    <td>{$age} yrs / {$row['gender']}</td>
                                    <td><small>{$reason}</small></td>
                                    <td>{$date}</td>
                                    <td>
                                        <div class='d-flex gap-2'>
                                            <form method='post' action='approve.php' class='d-inline' onsubmit='return confirm(\"Approve this resident for FREE stay?\")'>
                                                <input type='hidden' name='id' value='{$row['id']}'>
                                                <input type='hidden' name='csrf_token' value='" . generate_csrf_token() . "'>
                                                <button type='submit' class='btn btn-sm btn-success px-3 rounded-pill'><i class='fas fa-check-circle me-1'></i> Approve</button>
                                            </form>
                                            <form method='post' action='reject.php' class='d-inline' onsubmit='return confirm(\"Reject this free request?\")'>
                                                <input type='hidden' name='id' value='{$row['id']}'>
                                                <input type='hidden' name='csrf_token' value='" . generate_csrf_token() . "'>
                                                <button type='submit' class='btn btn-sm btn-outline-danger px-3 rounded-pill'><i class='fas fa-times-circle me-1'></i> Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
