<?php
$page_title = 'System Reports';
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

// Filters
$finance_filter  = $_GET['finance'] ?? 'monthly'; 
$resident_status = $_GET['status'] ?? 'active';

$current_month = date('F');
$current_year  = date('Y');

// Fetch Finance Stats from LEDGER (Source of truth)
try {
    $where = "";
    $f_params = [];
    if ($finance_filter === 'monthly') {
        $where = " WHERE month = ? AND year = ?";
        $f_params = [$current_month, $current_year];
    } else {
        $where = " WHERE year = ?";
        $f_params = [$current_year];
    }

    $finance_stmt = $conn->prepare("SELECT SUM(paid_amount) as paid, SUM(due_amount) as pending FROM monthly_ledger $where");
    $finance_stmt->execute($f_params);
    $f_stats = $finance_stmt->fetch();
    
    $total_paid    = (float)($f_stats['paid'] ?? 0);
    $total_pending = (float)($f_stats['pending'] ?? 0);
    
    // Overall Stats
    $count_staff   = (int)($conn->query("SELECT COUNT(*) FROM staff")->fetchColumn());
    $count_res     = (int)($conn->query("SELECT COUNT(*) FROM residents WHERE status='active'")->fetchColumn());
} catch (PDOException $e) {
    $total_paid = $total_pending = 0;
}

require_once '../../includes/header.php';
?>

<div class="row g-4 mb-4 d-print-none">
    <div class="col-lg-12">
        <div class="card-hydro border-0 shadow-sm">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="fw-bold mb-0 text-primary-custom"><i class="fas fa-chart-pie me-2"></i>Institutional Analytics</h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary px-4 rounded-pill" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Generate PDF / Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card-hydro printable-area border-0 shadow-sm">
    <!-- Print Header -->
    <div class="text-center mb-5 d-none d-print-block">
        <h2 class="fw-bold text-primary-custom">OAHMS CARE</h2>
        <h5 class="text-uppercase letter-spacing-1">Executive Management Report</h5>
        <p class="text-muted small">Generated On: <?php echo date('d M Y, h:i A'); ?> &bull; Data Source: Monthly Ledger V2</p>
        <hr>
    </div>

    <!-- Business Overview -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h6 class="fw-bold mb-0 text-uppercase tracking-wider">Financial Summary (<?php echo ucfirst($finance_filter); ?>)</h6>
        <div class="d-flex gap-2 d-print-none">
            <a href="?finance=monthly&status=<?php echo $resident_status; ?>" class="btn btn-sm <?php echo $finance_filter==='monthly'?'btn-primary':'btn-outline-secondary'; ?> rounded-pill px-3">This Month</a>
            <a href="?finance=yearly&status=<?php echo $resident_status; ?>" class="btn btn-sm <?php echo $finance_filter==='yearly'?'btn-primary':'btn-outline-secondary'; ?> rounded-pill px-3">Yearly Report</a>
        </div>
    </div>
    
    <div class="row g-4 mb-5 text-center">
        <div class="col-6 col-sm-3 border-end">
            <h6 class="text-secondary small text-uppercase fw-bold opacity-75">Active Residents</h6>
            <h3 class="fw-bold text-dark" style="font-weight:800;"><?php echo $count_res; ?></h3>
        </div>
        <div class="col-6 col-sm-3 border-end">
            <h6 class="text-secondary small text-uppercase fw-bold opacity-75">Total Staff</h6>
            <h3 class="fw-bold text-dark" style="font-weight:800;"><?php echo $count_staff; ?></h3>
        </div>
        <div class="col-6 col-sm-3 border-end">
            <h6 class="text-secondary small text-uppercase fw-bold opacity-75">Revenue (Ledger)</h6>
            <h3 class="fw-800 text-success">₹<?php echo number_format($total_paid, 2); ?></h3>
        </div>
        <div class="col-6 col-sm-3">
            <h6 class="text-secondary small text-uppercase fw-bold opacity-75">Total Outstanding</h6>
            <h3 class="fw-800 text-danger">₹<?php echo number_format($total_pending, 2); ?></h3>
        </div>
    </div>

    <!-- Resident Roster -->
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 mt-5">
        <h6 class="fw-bold mb-0 text-uppercase tracking-wider">Resident Roster (<?php echo ucfirst($resident_status); ?>)</h6>
        <div class="d-print-none">
            <select class="form-select form-select-sm rounded-pill shadow-sm" style="width: 160px;" onchange="location.href='?finance=<?php echo $finance_filter; ?>&status='+this.value">
                <option value="active" <?php echo $resident_status==='active'?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo $resident_status==='inactive'?'selected':''; ?>>Inactive</option>
                <option value="discharged" <?php echo $resident_status==='discharged'?'selected':''; ?>>Discharged</option>
                <option value="deceased" <?php echo $resident_status==='deceased'?'selected':''; ?>>Deceased</option>
            </select>
        </div>
    </div>
    
    <div class="table-responsive mb-5">
        <table class="table table-custom table-sm align-middle">
            <thead class="bg-light">
                <tr>
                    <th>Resident</th>
                    <th>Age</th>
                    <th>Room No</th>
                    <th>Primary Address</th>
                    <th>Emergency Contact</th>
                    <th>Admission</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $conn->prepare("SELECT r.*, rm.room_number, rm.room_type FROM residents r LEFT JOIN rooms rm ON r.room_id = rm.id WHERE r.status = ? ORDER BY r.name ASC");
                $stmt->execute([$resident_status]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($rows)) echo "<tr><td colspan='6' class='text-center py-4 text-muted'>No entries found.</td></tr>";
                
                foreach ($rows as $row) {
                    $age  = $row['dob'] ? date_diff(date_create($row['dob']), date_create('today'))->y : 'N/A';
                    $name = htmlspecialchars($row['name']);
                    $room = htmlspecialchars($row['room_number'] ?? '—');
                    $addr = mb_strimwidth(htmlspecialchars($row['address'] ?? '—'), 0, 50, "...");
                    
                    echo "<tr>
                            <td><strong>{$name}</strong> <br><small class='text-muted'>ID: #{$row['id']}</small></td>
                            <td>{$age} yrs</td>
                             <td>
                                <div class='fw-bold text-primary'>Room {$room}</div>
                                <div class='badge badge-soft-secondary small'>{$row['room_type']}</div>
                             </td>
                            <td class='small'>{$addr}</td>
                            <td class='fw-bold text-danger'>{$row['emergency_contact']}</td>
                            <td class='small'>" . formatDate($row['admit_date']) . "</td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Outstanding Dues (Grouped) -->
    <h6 class="fw-bold mb-3 border-bottom pb-2 mt-5 text-uppercase tracking-wider" style="page-break-before: always;">Outstanding Account Balances</h6>
    <div class="table-responsive">
        <table class="table table-custom table-sm align-middle">
            <thead class="bg-light">
                <tr>
                    <th>Resident Name</th>
                    <th>Months Pending</th>
                    <th>Total Outstanding</th>
                    <th>Emergency Followup</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $due_stmt = $conn->query("
                    SELECT r.name, r.emergency_contact, COUNT(l.id) as pending_count, SUM(l.due_amount) as total_due
                    FROM monthly_ledger l
                    JOIN residents r ON l.resident_id = r.id
                    WHERE l.due_amount > 0
                    GROUP BY r.id
                    ORDER BY total_due DESC
                ");
                $due_rows = $due_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($due_rows)) echo "<tr><td colspan='4' class='text-center text-success py-4'><i class='fas fa-check-circle me-2'></i>All accounts are settled!</td></tr>";
                
                foreach ($due_rows as $row) {
                    echo "<tr>
                            <td><strong>" . htmlspecialchars($row['name']) . "</strong></td>
                            <td><span class='badge badge-soft-warning'>" . $row['pending_count'] . " Months</span></td>
                            <td class='text-danger fw-800'>₹" . number_format($row['total_due'], 2) . "</td>
                            <td><span class='fw-bold'>" . htmlspecialchars($row['emergency_contact']) . "</span></td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Reports page — dark mode fixes */
[data-theme="dark"] h3.text-dark,
[data-theme="dark"] strong.text-dark,
[data-theme="dark"] .text-dark {
    color: var(--text-primary) !important;
}

/* thead bg-light fix for dark mode */
[data-theme="dark"] thead.bg-light th {
    background-color: var(--sidebar-active) !important;
    color: var(--text-secondary) !important;
    border-color: var(--border-color) !important;
}

/* fw-800 utility (Bootstrap doesn't have this) */
.fw-800 { font-weight: 800 !important; }

@media print {
    body * { visibility: hidden; }
    .printable-area, .printable-area * { visibility: visible; }
    .printable-area { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: none; padding: 0;}
    .card-widget { box-shadow: none !important; border: none !important; }
    .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
