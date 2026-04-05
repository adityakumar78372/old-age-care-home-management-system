<?php
$page_title = 'Resident Management';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

// Sort variables must be computed BEFORE HTML so thead links work
$sort       = $_GET['sort']  ?? 'id';
$order      = $_GET['order'] ?? 'DESC';
$next_order = ($order === 'ASC') ? 'DESC' : 'ASC';

require_once '../../includes/header.php';
?>

<div class="card-hydro mb-4 border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h5 class="fw-bold mb-0 text-primary-custom"><i class="fas fa-users-viewfinder me-2"></i>Resident Directory</h5>
        <div class="d-flex gap-2">
            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
            <a href="create.php" class="btn btn-primary-custom px-4 rounded-pill shadow-sm"><i class="fas fa-user-plus me-1"></i> New Admission</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Filters -->
    <div class="mb-4 d-flex gap-2 flex-wrap pb-3 border-bottom">
        <a href="index.php" class="btn btn-sm <?php echo !isset($_GET['status']) ? 'btn-primary' : 'btn-outline-secondary'; ?> rounded-pill px-3">All</a>
        <a href="index.php?status=active" class="btn btn-sm <?php echo (($_GET['status']??'')=='active') ? 'btn-success' : 'btn-outline-success'; ?> rounded-pill px-3">Active</a>
        <a href="index.php?status=discharged" class="btn btn-sm <?php echo (($_GET['status']??'')=='discharged') ? 'btn-info' : 'btn-outline-info'; ?> rounded-pill px-3">Discharged</a>
        <a href="index.php?status=deceased" class="btn btn-sm <?php echo (($_GET['status']??'')=='deceased') ? 'btn-dark' : 'btn-outline-dark'; ?> rounded-pill px-3">Deceased</a>
    </div>

    <div class="table-responsive">
        <table class="table table-custom align-middle" id="residentsTable">
            <thead>
                <tr>
                    <th><a href="?sort=name&order=<?php echo $next_order; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" class="text-decoration-none text-muted">Resident <i class="fas fa-sort small ms-1"></i></a></th>
                    <th>Age/Gender</th>
                    <th><a href="?sort=room&order=<?php echo $next_order; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" class="text-decoration-none text-muted">Room Info <i class="fas fa-sort small ms-1"></i></a></th>
                    <th>Stay (Months)</th>
                    <th><a href="?sort=status&order=<?php echo $next_order; ?><?php echo isset($_GET['status']) ? '&status='.$_GET['status'] : ''; ?>" class="text-decoration-none text-muted">Status <i class="fas fa-sort small ms-1"></i></a></th>
                    <th>Due Amount</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Use already-calculated sort vars from top of file
                    $allowed_sorts = ['name' => 'r.name', 'room' => 'rm.room_number', 'date' => 'r.admit_date', 'status' => 'r.status', 'id' => 'r.id'];
                    $sort_col = $allowed_sorts[$sort] ?? 'r.id';

                    $where  = "";
                    $params = [];
                    if (isset($_GET['status']) && !empty($_GET['status'])) {
                        $where    = " WHERE r.status = ? ";
                        $params[] = $_GET['status'];
                    }

                    $query = "
                        SELECT r.*, rm.room_number, rm.room_type,
                        (SELECT SUM(due_amount) FROM monthly_ledger WHERE resident_id = r.id) as total_due
                        FROM residents r
                        LEFT JOIN rooms rm ON r.room_id = rm.id
                        $where
                        ORDER BY $sort_col $order
                    ";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rows as $row) {
                        $badge = 'badge-soft-success';
                        if ($row['status'] === 'inactive')   $badge = 'badge-soft-warning';
                        if ($row['status'] === 'deceased')   $badge = 'badge-soft-dark';
                        if ($row['status'] === 'discharged') $badge = 'badge-soft-info';

                        $age = $row['dob'] ? date_diff(date_create($row['dob']), date_create('today'))->y : 'N/A';
                        $admitDate = new DateTime($row['admit_date']);
                        $now = new DateTime();
                        $months_stayed = $admitDate->diff($now)->m + ($admitDate->diff($now)->y * 12);
                        
                        $name  = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                        $room  = $row['room_number'] ? "Room " . htmlspecialchars($row['room_number']) : 'No Room';
                        $rtype = $row['room_type'] ? htmlspecialchars($row['room_type']) : 'N/A';
                        $sts   = htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8');
                        $due   = (float)($row['total_due'] ?? 0);
                        $photo = !empty($row['profile_photo']) ? APP_URL . '/' . $row['profile_photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($row['name']) . '&background=4f46e5&color=fff&size=40';

                        echo "<tr>
                                <td>
                                    <div class='d-flex align-items-center gap-3'>
                                        <a href='view.php?id={$row['id']}' class='text-decoration-none shadow-sm rounded-circle'>
                                            <img src='{$photo}' class='rounded-circle border' width='40' height='40' style='object-fit:cover;'>
                                        </a>
                                        <div>
                                             <a href='view.php?id={$row['id']}' class='fw-bold text-decoration-none hover-primary'>{$name}</a>
                                            <div class='small text-muted'>ID: #{$row['id']}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class='fw-bold'>{$age} yrs</div>
                                    <div class='small text-muted'>{$row['gender']}</div>
                                </td>
                                <td>
                                    <div class='fw-bold text-primary'>{$room}</div>
                                    <div class='badge badge-soft-secondary small'>{$rtype}</div>
                                </td>
                                <td>
                                    <div class='fw-bold'>{$months_stayed} Mos</div>
                                    <div class='small text-muted'>Since " . formatDate($row['admit_date']) . "</div>
                                </td>
                                <td><span class='badge {$badge} px-3 rounded-pill'>{$sts}</span></td>
                                <td>
                                    <div class='fw-bold " . ($due > 0 ? 'text-danger' : 'text-success') . "'>₹" . number_format($due, 2) . "</div>
                                    <div class='small text-muted'>" . ($due > 0 ? 'Pending Dues' : 'Clear') . "</div>
                                </td>
                                <td class='text-end'>
                                    <div class='dropdown'>
                                        <button class='btn btn-sm btn-light border rounded-pill py-1' type='button' data-bs-toggle='dropdown'>
                                            <i class='fas fa-ellipsis-h'></i>
                                        </button>
                                        <ul class='dropdown-menu dropdown-menu-end shadow border-0'>
                                            <li><a class='dropdown-item' href='view.php?id={$row['id']}'><i class='fas fa-id-badge me-2 text-primary'></i>Master Profile</a></li>";
                        if (in_array($_SESSION['role'], ['admin', 'manager'])) {
                            echo "<li><a class='dropdown-item' href='edit.php?id={$row['id']}'><i class='fas fa-edit me-2 text-success'></i>Modify Info</a></li>
                                  <li><hr class='dropdown-divider'></li>
                                  <li>
                                    <form method='post' action='delete.php' class='delete-form'>
                                      <input type='hidden' name='id' value='{$row['id']}'>
                                      <button type='button' class='dropdown-item text-danger delete-btn'><i class='fas fa-trash-alt me-2'></i>Delete Record</button>
                                    </form>
                                  </li>";
                        }
                        echo "          </ul>
                                    </div>
                                </td>
                              </tr>";
                    }
                } catch (PDOException $e) {
                    echo "<tr><td colspan='7' class='text-danger text-center py-4'>Error: " . $e->getMessage() . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<script>
// Confirm delete via SweetAlert — submits hidden POST form (CSRF-safe)
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = this.closest('form.delete-form');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Resident?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) form.submit();
            });
        } else {
            if (confirm('Are you sure you want to delete this resident?')) form.submit();
        }
    });
});
</script>
<?php require_once '../../includes/footer.php'; ?>
