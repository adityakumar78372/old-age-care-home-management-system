<?php
/**
 * view.php - Resident Master Profile (Production Grade)
 */
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch Resident Master Info
$stmt = $conn->prepare("
    SELECT r.*, rm.room_number, rm.room_type 
    FROM residents r 
    LEFT JOIN rooms rm ON r.room_id = rm.id 
    WHERE r.id = ?
");
$stmt->execute([$id]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    set_flash_message('error', 'Resident not found.');
    header("Location: index.php");
    exit;
}

// Calculate Stay Duration
$admitDate = new DateTime($res['admit_date']);
$endDate = ($res['status'] === 'discharged' || $res['status'] === 'deceased') && $res['discharge_date'] 
           ? new DateTime($res['discharge_date']) 
           : new DateTime();
$interval = $admitDate->diff($endDate);
$stay_str = $interval->y . "y " . $interval->m . "m " . $interval->d . "d";

// Fetch Ledger Data
$ledger_stmt = $conn->prepare("SELECT * FROM monthly_ledger WHERE resident_id = ? ORDER BY year DESC, FIELD(month, 'December','November','October','September','August','July','June','May','April','March','February','January') DESC");
$ledger_stmt->execute([$id]);
$ledgers = $ledger_stmt->fetchAll();

// Fetch Payment Totals
$pay_stats = $conn->prepare("SELECT SUM(paid_amount) as total_paid, SUM(due_amount) as total_due FROM monthly_ledger WHERE resident_id = ?");
$pay_stats->execute([$id]);
$stats = $pay_stats->fetch();

$page_title = 'Resident Master Profile';
require_once '../../includes/header.php';
?>

<div class="row g-4">
    <!-- Left Sidebar: Quick Summary -->
    <div class="col-lg-4">
        <div class="card-hydro text-center mb-4 border-0 shadow-sm">
            <div class="position-relative d-inline-block mb-3 mt-2">
                <?php 
                $photo = !empty($res['profile_photo']) ? APP_URL . '/' . $res['profile_photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($res['name']) . '&size=150&background=4f46e5&color=fff';
                ?>
                <img src="<?php echo $photo; ?>" class="rounded-circle shadow-sm border border-4 border-white" width="130" height="130" style="object-fit:cover;">
                <span class="position-absolute bottom-0 end-0 p-2 bg-<?php echo ($res['status']=='active')?'success':'secondary'; ?> border border-white border-3 rounded-circle" title="<?php echo ucfirst($res['status']); ?>"></span>
            </div>
            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($res['name']); ?></h4>
            <p class="text-secondary small mb-3">ID: #<?php echo $res['id']; ?> &bull; Admitted on <?php echo formatDate($res['admit_date']); ?></p>
            
            <div class="d-flex justify-content-center gap-2 mb-4">
                <span class="badge badge-soft-primary px-3 py-2">Room <?php echo htmlspecialchars($res['room_number'] ?? 'N/A'); ?> [<?php echo htmlspecialchars($res['room_type'] ?? '-'); ?>]</span>
                <span class="badge badge-soft-info px-3 py-2">₹<?php echo number_format($res['monthly_fee'], 0); ?> / mo</span>
            </div>

            <hr class="opacity-10">

            <div class="row g-0 text-center py-2">
                <div class="col-6 border-end">
                    <small class="text-muted d-block mb-1">Stay Duration</small>
                    <span class="fw-bold text-dark"><?php echo $stay_str; ?></span>
                </div>
                <div class="col-6">
                    <small class="text-muted d-block mb-1">Status</small>
                    <span class="badge badge-soft-<?php 
                        echo ($res['status']=='active') ? 'success' : (($res['status']=='deceased') ? 'dark' : (($res['status']=='discharged')?'info':'warning')); 
                    ?>"><?php echo ucfirst($res['status']); ?></span>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                </a>
            </div>
        </div>

        <!-- Financial Widget -->
        <div class="card-hydro border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, var(--primary-color) 0%, #4338ca 100%); color: white;">
            <h6 class="fw-bold mb-4 opacity-75"><i class="fas fa-wallet me-2"></i>Financial Snapshop</h6>
            <div class="d-flex justify-content-between mb-3 border-bottom border-white border-opacity-10 pb-2">
                <span>Total Paid</span>
                <span class="fw-bold text-white">₹<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-3 border-bottom border-white border-opacity-10 pb-2">
                <span>Total Due</span>
                <span class="fw-bold text-warning">₹<?php echo number_format($stats['total_due'] ?? 0, 2); ?></span>
            </div>
            <div class="text-center mt-3">
                <a href="../payments/index.php" class="btn btn-white btn-sm px-4 rounded-pill fw-bold text-primary" style="background: white;">Collect payment</a>
            </div>
        </div>
    </div>

    <!-- Right Side: Details & History Tabs -->
    <div class="col-lg-8">
        <div class="card-hydro p-0 border-0 shadow-sm overflow-hidden h-100">
            <ul class="nav nav-tabs nav-justified border-0 bg-light" id="profileTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active py-3 border-0" data-bs-toggle="tab" data-bs-target="#personal">
                        <i class="fas fa-info-circle me-1"></i> Details
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 border-0" data-bs-toggle="tab" data-bs-target="#ledger">
                        <i class="fas fa-file-invoice-dollar me-1"></i> Ledger
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 border-0" data-bs-toggle="tab" data-bs-target="#history">
                        <i class="fas fa-history me-1"></i> Stay History
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 border-0" data-bs-toggle="tab" data-bs-target="#health">
                        <i class="fas fa-heartbeat me-1"></i> Health
                    </button>
                </li>
            </ul>

            <div class="tab-content p-4" id="profileTabsContent">
                <!-- Tab: Personal Details -->
                <div class="tab-pane fade show active" id="personal">
                    <h6 class="fw-bold mb-4 text-primary"><i class="fas fa-user-tag me-2"></i>General Information</h6>
                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Full Name</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($res['name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Date of Birth</label>
                            <p class="fw-bold mb-0"><?php echo formatDate($res['dob']); ?> (<?php echo date_diff(date_create($res['dob']), date_create('today'))->y; ?> yrs)</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Gender</label>
                            <p class="fw-bold mb-0"><?php echo $res['gender']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Primary Contact</label>
                            <p class="fw-bold mb-0"><?php echo $res['contact'] ?: '---'; ?></p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small d-block mb-1">Permanent Address</label>
                            <p class="fw-bold mb-0"><?php echo nl2br(htmlspecialchars($res['address'] ?? 'N/A')); ?></p>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-4 text-danger"><i class="fas fa-shield-alt me-2"></i>Guardian & Emergency Contact</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Emergency Contact</label>
                            <p class="fw-bold mb-0 text-danger"><?php echo htmlspecialchars($res['emergency_contact']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Family Contact Number</label>
                            <p class="fw-bold mb-0"><?php echo htmlspecialchars($res['family_contact'] ?? '---'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Monthly Ledger -->
                <div class="tab-pane fade" id="ledger">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0 text-primary">Month-Wise Fee Ledger</h6>
                        <span class="badge badge-soft-info py-2">System Standard Ledger</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Month/Year</th>
                                    <th>Fee Amt</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ledgers)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No ledger entries found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($ledgers as $l): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $l['month'] . ' ' . $l['year']; ?></td>
                                            <td>₹<?php echo number_format($l['total_fee'], 2); ?></td>
                                            <td class="text-success fw-bold">₹<?php echo number_format($l['paid_amount'], 2); ?></td>
                                            <td class="text-danger fw-bold">₹<?php echo number_format($l['due_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-soft-<?php 
                                                    echo ($l['status']=='Paid')?'success':(($l['status']=='Partial')?'warning':(($l['status']=='Advance')?'info':'danger')); 
                                                ?> px-3"><?php echo $l['status']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-warning py-2 mt-3 border-0 small">
                        <i class="fas fa-info-circle me-1"></i> Payments are automatically adjusted against the oldest pending months first.
                    </div>
                </div>

                <!-- Tab: Stay History -->
                <div class="tab-pane fade" id="history">
                    <h6 class="fw-bold mb-4 text-primary">Admission & Stay Logs</h6>
                    <div class="timeline-simple">
                        <div class="mb-4 ps-4 position-relative border-start border-2 border-primary">
                            <span class="position-absolute start-0 top-0 translate-middle-x bg-primary rounded-circle" style="width:12px; height:12px; margin-left:-1px;"></span>
                            <p class="fw-bold mb-1 text-primary">Admitted</p>
                            <p class="text-muted small mb-0"><?php echo formatDate($res['admit_date']); ?> &bull; Original Assignment</p>
                            <p class="small mt-1">Initial Room: <?php echo htmlspecialchars($res['room_number'] ?? 'N/A'); ?> [<?php echo htmlspecialchars($res['room_type'] ?? '-'); ?>]</p>
                        </div>
                        
                        <?php if ($res['status'] === 'discharged' || $res['status'] === 'deceased'): ?>
                        <div class="mb-0 ps-4 position-relative border-start border-2 border-danger">
                            <span class="position-absolute start-0 top-0 translate-middle-x bg-danger rounded-circle" style="width:12px; height:12px; margin-left:-1px;"></span>
                            <p class="fw-bold mb-1 text-danger"><?php echo ucfirst($res['status']); ?></p>
                            <p class="text-muted small mb-0"><?php echo formatDate($res['discharge_date']); ?> &bull; Record Finalized</p>
                            <p class="small mt-1">Total Stay: <?php echo $stay_str; ?></p>
                        </div>
                        <?php else: ?>
                        <div class="mb-0 ps-4 position-relative border-start border-2 border-dashed border-secondary">
                            <span class="position-absolute start-0 top-0 translate-middle-x bg-secondary rounded-circle" style="width:12px; height:12px; margin-left:-1px;"></span>
                            <p class="fw-bold mb-1 text-secondary">Currently In-stay</p>
                            <p class="text-muted small mb-0"><?php echo date('d/m/Y'); ?> &bull; Ongoing Record</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-5">
                        <h6 class="fw-bold mb-3 small text-muted text-uppercase">Medical Note History</h6>
                        <div class="medical-notes-box p-3 rounded border">
                            <?php echo $res['medical_history'] ? nl2br(htmlspecialchars($res['medical_history'])) : '<span class="text-muted fst-italic">No medical notes found.</span>'; ?>
                        </div>
                    </div>
                </div>

                <!-- Tab: Health Records -->
                <div class="tab-pane fade" id="health">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold mb-0 text-primary">Health Checkup Records</h6>
                        <a href="../health/create.php?resident_id=<?php echo $id; ?>" class="btn btn-sm btn-primary-custom rounded-pill px-3">
                            <i class="fas fa-plus me-1"></i> Add Record
                        </a>
                    </div>
                    <?php
                    $hr_stmt = $conn->prepare("SELECT * FROM health_records WHERE resident_id = ? ORDER BY checkup_date DESC LIMIT 5");
                    $hr_stmt->execute([$id]);
                    $hrs = $hr_stmt->fetchAll();
                    ?>
                    <?php if (empty($hrs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-heartbeat fa-3x text-muted opacity-25 mb-3"></i>
                            <p class="text-muted">No health records available for this resident.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush border rounded overflow-hidden">
                            <?php foreach ($hrs as $hr): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-bold text-dark"><i class="fas fa-calendar-day me-2 text-primary opacity-50"></i><?php echo formatDate($hr['checkup_date']); ?></span>
                                    <span class="badge badge-soft-info"><?php echo $hr['blood_pressure']; ?> BP &bull; <?php echo $hr['temp']; ?>°F</span>
                                </div>
                                <p class="small text-secondary mb-2"><?php echo nl2br(htmlspecialchars($hr['doctor_visit_notes'])); ?></p>
                                <div class="small">
                                    <span class="text-muted">Meds: <?php echo htmlspecialchars($hr['medicines'] ?: 'None'); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="../health/index.php?resident_id=<?php echo $id; ?>" class="small text-decoration-none">View full medical history <i class="fas fa-chevron-right ms-1"></i></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-dashed { border-style: dashed !important; }
.timeline-simple p { margin-bottom: 0; }
.card-widget, .card-hydro { transition: transform 0.2s; }
.nav-tabs .nav-link { font-weight: 500; font-size: 0.9rem; color: var(--text-secondary); transition: all 0.2s; }
.nav-tabs .nav-link.active { 
    color: var(--primary-color) !important; 
    background: var(--surface-color) !important; 
    border-top: 3px solid var(--primary-color) !important; 
}

/* Medical notes box — theme aware */
.medical-notes-box {
    background-color: var(--sidebar-active);
    color: var(--text-primary);
    border-color: var(--border-color) !important;
    font-size: 0.9rem;
    line-height: 1.8;
    min-height: 60px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
