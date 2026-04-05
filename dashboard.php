<?php
$page_title = 'Dashboard';
require_once 'db_connect.php';
require_once 'includes/header.php';

// Fetch Statistics — initialize with defaults so chart vars are ALWAYS available
$stats = [
    'residents'        => 0,
    'rooms'            => 0,
    'staff'            => 0,
    'pending_payments' => 0,
    'pending_approvals' => 0
];
$chart_payments = ['paid' => 0, 'unpaid' => 0];

try {
    // Consolidated Statistics Query for optimal performance
    $stats_query = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM residents WHERE status='active' AND approval_status='approved') as total_residents,
            (SELECT COUNT(*) FROM residents WHERE status='active' AND approval_status='approved' AND room_id IS NOT NULL) as assigned_residents,
            (SELECT COALESCE(SUM(capacity), 0) FROM rooms) as total_capacity,
            (SELECT COUNT(*) FROM staff WHERE status='active') as total_staff,
            (SELECT SUM(paid_amount) FROM monthly_ledger) as total_paid,
            (SELECT SUM(due_amount) FROM monthly_ledger) as total_pending,
            (SELECT COUNT(*) FROM residents WHERE approval_status='pending') as pending_approvals
    ")->fetch();
    
    $stats['residents']        = (int)$stats_query['total_residents'];
    $stats['rooms']            = max(0, (int)$stats_query['total_capacity'] - (int)$stats_query['assigned_residents']);
    $stats['staff']            = (int)$stats_query['total_staff'];
    $stats['pending_payments'] = (float)($stats_query['total_pending'] ?? 0);
    $stats['pending_approvals'] = (int)$stats_query['pending_approvals'];
    
    $chart_payments['paid']    = (float)($stats_query['total_paid'] ?? 0);
    $chart_payments['unpaid']  = $stats['pending_payments'];


} catch (PDOException $e) {
    // Defaults are already set
}
?>

<div class="row g-4 mb-4">
    <!-- Total Residents -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-widget h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-secondary mb-1">Active Residents</h5>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['residents']; ?></h2>
                </div>
                <div class="card-widget-icon" style="background-color: var(--primary-color);">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <a href="modules/residents/index.php" class="text-decoration-none text-primary-custom fw-medium">View details <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>

    <!-- Available Rooms -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-widget h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-secondary mb-1">Available Beds</h5>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['rooms']; ?></h2>
                </div>
                <div class="card-widget-icon" style="background-color: var(--secondary-color);">
                    <i class="fas fa-bed"></i>
                </div>
            </div>
            <a href="modules/rooms/index.php" class="text-decoration-none text-primary-custom fw-medium">Manage rooms <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>

    <!-- Total Staff -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-widget h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-secondary mb-1">Active Staff</h5>
                    <h2 class="mb-0 fw-bold"><?php echo $stats['staff']; ?></h2>
                </div>
                <div class="card-widget-icon" style="background-color: var(--info-color);">
                    <i class="fas fa-user-nurse"></i>
                </div>
            </div>
            <a href="modules/staff/index.php" class="text-decoration-none text-primary-custom fw-medium">View directory <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>

    <!-- Pending Payments -->
    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-widget h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-secondary mb-1">Pending Fees</h5>
                    <h2 class="mb-0 fw-bold text-danger">₹<?php echo number_format($stats['pending_payments'], 2); ?></h2>
                </div>
                <div class="card-widget-icon" style="background-color: var(--warning-color);">
                    <i class="fas fa-rupee-sign"></i>
                </div>
            </div>
            <a href="modules/payments/index.php" class="text-decoration-none text-primary-custom fw-medium">Collect payments <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Approvals (Admin Only) -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card-widget h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="text-secondary mb-1">Pending Approvals</h5>
                    <h2 class="mb-0 fw-bold <?php echo $stats['pending_approvals'] > 0 ? 'text-warning' : ''; ?>"><?php echo $stats['pending_approvals']; ?></h2>
                </div>
                <div class="card-widget-icon" style="background-color: var(--warning-color);">
                    <i class="fas fa-user-clock"></i>
                </div>
            </div>
            <a href="modules/admin/approvals.php" class="text-decoration-none text-primary-custom fw-medium">Review requests <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Charts Section -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card-widget h-100 position-relative">
            <h5 class="fw-bold mb-4">Facility Occupancy</h5>
            <div id="occupancyChart" style="min-height: 250px;"></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-widget h-100 position-relative">
            <h5 class="fw-bold mb-4">Revenue Status (Total)</h5>
            <div id="revenueChart" style="min-height: 250px;"></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card-widget h-100">
            <h5 class="fw-bold mb-4">Quick Navigation</h5>
            <div class="row g-3">
                <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <div class="col-sm-6 col-md-4">
                    <a href="modules/residents/create.php" class="btn btn-primary-custom w-100 text-start">
                        <i class="fas fa-user-plus me-2"></i> Add Resident
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'doctor', 'nurse'])): ?>
                <div class="col-sm-6 col-md-4">
                    <a href="modules/health/index.php" class="btn btn-info w-100 text-start text-white shadow-sm">
                        <i class="fas fa-notes-medical me-2"></i> Update Health
                    </a>
                </div>
                <?php endif; ?>

                <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <div class="col-sm-6 col-md-4">
                    <a href="modules/activities/index.php" class="btn btn-warning w-100 text-start text-dark shadow-sm">
                        <i class="fas fa-calendar-check me-2"></i> Add Event
                    </a>
                </div>
                <?php endif; ?>

                <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'cook'])): ?>
                <div class="col-sm-6 col-md-4">
                    <a href="modules/meals/index.php" class="btn btn-success w-100 text-start text-white shadow-sm">
                        <i class="fas fa-utensils me-2"></i> Today's Menu
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <h5 class="fw-bold mt-5 mb-3">Recent Residents</h5>
            <div class="table-responsive">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Room</th>
                            <th>Admit Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT r.name, r.admit_date, r.status, rm.room_number
                                                  FROM residents r
                                                  LEFT JOIN rooms rm ON r.room_id = rm.id
                                                  ORDER BY r.id DESC LIMIT 5");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (empty($rows)) {
                                echo "<tr><td colspan='4' class='text-center text-muted py-3'>No residents yet.</td></tr>";
                            }
                            foreach ($rows as $row) {
                                $badge = $row['status'] === 'active' ? 'bg-success' : 'bg-secondary';
                                $admit_date_formatted = formatDate($row['admit_date']);
                                $room_display = $row['room_number'] ? htmlspecialchars($row['room_number']) : 'Not Assigned';
                                $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                                echo "<tr>
                                        <td>{$name}</td>
                                        <td>{$room_display}</td>
                                        <td>{$admit_date_formatted}</td>
                                        <td><span class='badge {$badge}'>".ucfirst(htmlspecialchars($row['status']))."</span></td>
                                      </tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='4' class='text-center text-muted'>Could not load data.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-widget h-100" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); color: white;">
            <div class="text-center p-4">
                <i class="fas fa-heartbeat fa-4x mb-3 shadow rounded-circle p-3" style="background: rgba(255,255,255,0.2);"></i>
                <h4 class="fw-bold">Elderly Health Tip</h4>
                <p class="mt-3 opacity-75">Ensure all residents receive their prescribed medications on time. Daily walks in the morning improve cardiovascular health.</p>
                <button class="btn btn-light mt-3 fw-bold" onclick="location.href='modules/health/index.php'">Check Health Records</button>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Theme colors matching CSS variables
    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#4f46e5';
    const infoColor = getComputedStyle(document.documentElement).getPropertyValue('--info-color').trim() || '#3b82f6';
    const warningColor = getComputedStyle(document.documentElement).getPropertyValue('--warning-color').trim() || '#f59e0b';
    const successColor = getComputedStyle(document.documentElement).getPropertyValue('--secondary-color').trim() || '#10b981';
    
    // 1. Occupancy Donut Chart
    var occOptions = {
        series: [<?php echo $stats['residents']; ?>, <?php echo $stats['rooms']; ?>],
        chart: { type: 'donut', height: 280, background: 'transparent' },
        labels: ['Occupied Beds', 'Available Beds'],
        colors: [primaryColor, '#cbd5e1'],
        plotOptions: {
            pie: {
                donut: { size: '75%', labels: { show: true, name: { show: true }, value: { show: true, fontSize: '24px', fontWeight: 600 } } }
            }
        },
        dataLabels: { enabled: false },
        stroke: { show: false },
        theme: { mode: document.documentElement.getAttribute('data-theme') || 'light' },
        legend: { position: 'bottom' }
    };
    var occChart = new ApexCharts(document.querySelector("#occupancyChart"), occOptions);
    occChart.render();

    // 2. Revenue Bar Chart
    var revOptions = {
        series: [{ name: 'Amount (₹)', data: [<?php echo $chart_payments['paid']; ?>, <?php echo $chart_payments['unpaid']; ?>] }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
        colors: [successColor, warningColor],
        plotOptions: { bar: { borderRadius: 6, columnWidth: '45%', distributed: true } },
        dataLabels: { enabled: true, formatter: function (val) { return "₹" + val; }, style: { fontSize: '12px', colors: ["#fff"] } },
        xaxis: { categories: ['Collected Revenue', 'Pending Dues'], labels: { style: { fontSize: '13px' } } },
        theme: { mode: document.documentElement.getAttribute('data-theme') || 'light' },
        legend: { show: false }
    };
    var revChart = new ApexCharts(document.querySelector("#revenueChart"), revOptions);
    revChart.render();

    // Listen for theme toggle to update charts
    const themeBtn = document.getElementById('theme-toggle');
    if(themeBtn) {
        themeBtn.addEventListener('click', () => {
            setTimeout(() => {
                let currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
                occChart.updateOptions({ theme: { mode: currentTheme } });
                revChart.updateOptions({ theme: { mode: currentTheme } });
            }, 100);
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
