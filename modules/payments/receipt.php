<?php
$page_title = 'Payment Receipt';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$pay_id = (int)$_GET['id'];

try {
    // 1. Fetch Core Payment & Resident Info
    $stmt = $conn->prepare("
        SELECT p.*, r.name, r.contact, r.address, r.admit_date, r.monthly_fee as current_assigned_fee, rm.room_number, rm.room_type 
        FROM payments p 
        JOIN residents r ON p.resident_id = r.id 
        LEFT JOIN rooms rm ON r.room_id = rm.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pay_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) die("Payment record not found.");

    $resident_id = $payment['resident_id'];

    // 2. Fetch Ledger Financial Summary
    $summaryStmt = $conn->prepare("
        SELECT 
            SUM(total_fee) as expected,
            SUM(paid_amount) as collected,
            SUM(due_amount) as pending
        FROM monthly_ledger
        WHERE resident_id = ?
    ");
    $summaryStmt->execute([$resident_id]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Month-wise Status (Legacy/Coverage)
    $ledgerStmt = $conn->prepare("
        SELECT month, year, status, paid_amount, due_amount 
        FROM monthly_ledger 
        WHERE resident_id = ? 
        ORDER BY year DESC, FIELD(month, 'December','November','October','September','August','July','June','May','April','March','February','January') DESC 
        LIMIT 6
    ");
    $ledgerStmt->execute([$resident_id]);
    $ledger_rows = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - <?php echo $payment['receipt_no']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #6366f1;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-light: #f1f5f9;
        }

        body { 
            background-color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            color: var(--text-dark);
            -webkit-print-color-adjust: exact; 
        }

        .receipt-container {
            max-width: 850px;
            margin: 40px auto;
        }

        .receipt-card { 
            background: #fff; 
            padding: 50px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.05); 
            border-radius: 24px; 
            position: relative; 
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .receipt-card::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 8px; 
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color)); 
        }

        .header-section {
            border-bottom: 2px solid var(--border-light);
            padding-bottom: 30px;
            margin-bottom: 30px;
        }

        .receipt-label { 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            font-weight: 700; 
            color: var(--text-muted); 
            font-size: 0.75rem; 
        }

        .details-box {
            background-color: #fcfdfe;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            padding: 24px;
        }

        .financial-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .fin-stat {
            background: #fff;
            border: 1px solid #f1f5f9;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }

        .fin-stat .label { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; display: block; }
        .fin-stat .value { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); }

        .table-custom { margin-top: 20px; }
        .table-custom th { background: #f8fafc; color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; font-weight: 700; border: none; padding: 12px 15px; }
        .table-custom td { padding: 15px; border-bottom: 1px solid var(--border-light); }

        .coverage-badge {
            font-size: 0.65rem;
            padding: 4px 10px;
            border-radius: 50px;
            font-weight: 700;
        }
        .status-Paid { background: #dcfce7; color: #166534; }
        .status-Partial { background: #fef9c3; color: #854d0e; }
        .status-Advance { background: #dbeafe; color: #1e40af; }
        .status-Pending { background: #fee2e2; color: #991b1b; }

        .footer-note {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        @media print {
            body { background: white !important; margin: 0 !important; }
            .receipt-container { margin: 0 !important; max-width: 100% !important; }
            .receipt-card { box-shadow: none !important; border: none !important; padding: 20px !important; border-radius: 0 !important; width: 100% !important; }
            .no-print { display: none !important; }
            .receipt-card::before { height: 4px !important; }
            @page { margin: 0; size: A4; }
            body { padding: 1.5cm; }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <a href="index.php" class="btn btn-light border rounded-pill px-4 shadow-sm fw-600">
            <i class="fas fa-arrow-left me-2"></i>Dashboard
        </a>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow fw-600">
            <i class="fas fa-print me-2"></i>Print Invoice
        </button>
    </div>

    <div class="receipt-card">
        <!-- Logo & Header -->
        <div class="header-section d-flex justify-content-between align-items-start">
            <div>
                <h1 class="fw-800 text-primary mb-0" style="letter-spacing:-1.5px; font-size: 2.2rem;">OAHMS CARE</h1>
                <p class="text-secondary small fw-bold mb-0">Professional Elderly Management</p>
                <div class="mt-3 fs-6">
                    <i class="fas fa-location-dot me-2 text-muted"></i> Sector 5, HSR Layout, Bengaluru
                </div>
            </div>
            <div class="text-end">
                <span class="receipt-label">Official Receipt</span>
                <h3 class="fw-800 text-dark mt-1 mb-1"><?php echo $payment['receipt_no']; ?></h3>
                <div class="text-muted fw-bold small">
                    Date: <?php echo formatDate($payment['payment_date']); ?>
                </div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="row g-4 mb-4">
            <div class="col-7">
                <div class="details-box h-100">
                    <span class="receipt-label d-block mb-3">Resident Information</span>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:48px; height:48px;">
                            <i class="fas fa-user-circle fa-xl"></i>
                        </div>
                        <div>
                            <h5 class="fw-800 mb-0"><?php echo htmlspecialchars($payment['name']); ?></h5>
                            <span class="text-muted small fw-600">ID: #<?php echo $payment['resident_id']; ?></span>
                        </div>
                    </div>
                    <div class="row small g-2">
                        <div class="col-6">
                            <span class="text-muted d-block">Room Details</span>
                            <span class="fw-bold fs-6"><?php echo $payment['room_number'] ? "Room {$payment['room_number']} [{$payment['room_type']}]" : 'No Room Assigned'; ?></span>
                        </div>
                        <div class="col-6 text-end text-sm-start">
                            <span class="text-muted d-block">Admission Date</span>
                            <span class="fw-bold fs-6"><?php echo formatDate($payment['admit_date']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-5">
                <div class="details-box h-100">
                    <span class="receipt-label d-block mb-3">Payment Summary</span>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">Payment Mode:</span>
                        <span class="fw-bold small text-primary"><?php echo $payment['payment_mode']; ?></span>
                    </div>
                    <?php if(!empty($payment['transaction_id'])): ?>
                    <div class="mb-2 d-flex justify-content-between border-top pt-2">
                        <span class="text-muted small">Transaction ID:</span>
                        <span class="fw-bold small"><?php echo htmlspecialchars($payment['transaction_id']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mt-3 p-3 bg-success bg-opacity-10 rounded-3 text-center border border-success border-opacity-10">
                        <div class="text-success small fw-800 text-uppercase" style="font-size:0.6rem;">Status</div>
                        <div class="text-success fw-bold fs-5"><i class="fas fa-check-circle me-1"></i> Received</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Statistics -->
        <span class="receipt-label d-block mb-3 text-center">Comprehensive Financial Ledger</span>
        <div class="financial-grid">
            <div class="fin-stat">
                <span class="label">Monthly Fee</span>
                <span class="value">₹<?php echo number_format($payment['current_assigned_fee'], 0); ?></span>
            </div>
            <div class="fin-stat" style="border-color: var(--primary-color);">
                <span class="label text-primary">Paid Now</span>
                <span class="value text-primary">₹<?php echo number_format($payment['amount'], 0); ?></span>
            </div>
            <div class="fin-stat">
                <span class="label">Total Paid</span>
                <span class="value text-success">₹<?php echo number_format($summary['collected'] ?? 0, 0); ?></span>
            </div>
            <div class="fin-stat">
                <span class="label">Remaining</span>
                <span class="value text-danger">₹<?php echo number_format($summary['pending'] ?? 0, 0); ?></span>
            </div>
        </div>

        <!-- Detailed Breakdown -->
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th width="50%">Description / Fee Type</th>
                        <th class="text-center">Billing Month</th>
                        <th class="text-end">Credit Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-bold">Resident Care Settlement</div>
                            <div class="text-muted small mt-1">This payment is adjusted against the oldest outstanding dues in the ledger.</div>
                            <?php if(!empty($payment['note'])): ?>
                                <div class="mt-2 text-secondary font-monospace" style="font-size:0.75rem;">Note: <?php echo htmlspecialchars($payment['note']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-middle">
                            <span class="fw-600 text-muted small"><?php echo date('F Y', strtotime($payment['payment_date'])); ?></span>
                        </td>
                        <td class="text-end align-middle">
                            <h4 class="fw-800 text-dark mb-0">₹<?php echo number_format($payment['amount'], 2); ?></h4>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Month-wise Adjustment Status -->
        <div class="mt-4 p-4 border rounded-4 bg-light bg-opacity-50">
            <h6 class="fw-800 mb-3 small text-uppercase text-muted" style="letter-spacing:1px;">Recent Billing Coverage</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach($ledger_rows as $lr): ?>
                    <div class="bg-white border rounded-3 px-3 py-2 d-flex align-items-center gap-2 shadow-sm">
                        <div class="small fw-bold"><?php echo $lr['month']; ?> '<?php echo substr($lr['year'], 2); ?></div>
                        <span class="coverage-badge status-<?php echo $lr['status']; ?>"><?php echo $lr['status']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-3 mb-0 small text-muted italic">
                <i class="fas fa-info-circle me-1"></i> As per institutional policy, payments are applied to the oldest debt records first.
            </p>
        </div>

        <!-- Footer -->
        <div class="mt-5 pt-4 text-center border-top">
            <h6 class="fw-800 text-dark">Proprietor / Authorised Signatory</h6>
            <div class="footer-note mt-3">
                <p class="mb-1">This document serves as an official confirmation of the payment received. <br>This is a system-generated receipt and does not require a physical signature.</p>
                <p class="fw-bold text-primary mt-2">Thank you for choosing OAHMS CARE for your family's needs.</p>
            </div>
            <div class="mt-4 no-print">
                <button onclick="window.print()" class="btn btn-sm btn-outline-primary rounded-pill px-3">Print this Page</button>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4 text-muted small no-print">
        &copy; <?php echo date('Y'); ?> OAHMS Care Management System. All Rights Reserved.
    </div>
</div>

<script>
    window.onload = function() {
        // Optional: Auto-trigger print if requested
        // window.print();
    }
</script>

</body>
</html>
