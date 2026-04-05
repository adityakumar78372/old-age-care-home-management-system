<?php
$page_title = 'Payments & Fees';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// Access Control: Admin and Manager
if (!in_array($_SESSION['role'], ['admin', 'manager'])) {
    set_flash_message('error', 'Access denied.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

// Sync Ledger for the specific resident being worked on (Lazy Sync)
if (isset($_POST['resident_id'])) {
    syncResidentLedger((int)$_POST['resident_id'], $conn);
}
if (isset($_GET['resident_id'])) {
    syncResidentLedger((int)$_GET['resident_id'], $conn);
}

// Handle AJAX request for Monthly Fee
if (isset($_GET['get_fee'])) {
    $res_id = (int)$_GET['get_fee'];
    $stmt = $conn->prepare("SELECT monthly_fee FROM residents WHERE id = ?");
    $stmt->execute([$res_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['fee' => $res['monthly_fee'] ?? 5000]);
    exit;
}

// Handle Add Payment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_payment'])) {
    $res_id  = (int)($_POST['resident_id'] ?? 0);
    $amount  = (float)($_POST['amount'] ?? 0);
    $mode    = $_POST['payment_mode'] ?? 'Cash';
    $txn_id  = trim($_POST['transaction_id'] ?? '');
    $note    = trim($_POST['note'] ?? '');
    $pay_date = date('Y-m-d');

    if ($res_id <= 0 || $amount <= 0) {
        $error = "Resident and valid amount are required.";
    } else {
        try {
            $conn->beginTransaction();
            
            // 1. Record the base payment
            $stmt = $conn->prepare("INSERT INTO payments (resident_id, amount, status, payment_date, payment_mode, transaction_id, note) VALUES (?, ?, 'paid', ?, ?, ?, ?)");
            $stmt->execute([$res_id, $amount, $pay_date, $mode, $txn_id, $note]);
            $payment_id = $conn->lastInsertId();

            // 1b. Assign Sequential Receipt Number (#INVXXXX)
            $receipt = generateInvoiceID($payment_id);
            $upd_rcpt = $conn->prepare("UPDATE payments SET receipt_no = ? WHERE id = ?");
            $upd_rcpt->execute([$receipt, $payment_id]);

            // 2. Allocate across ledger (Oldest Pending first)
            $remaining = $amount;
            
            // Fetch all non-paid months
            $ledger_stmt = $conn->prepare("SELECT * FROM monthly_ledger WHERE resident_id = ? AND status != 'Paid' ORDER BY year ASC, FIELD(month, 'January','February','March','April','May','June','July','August','September','October','November','December') ASC");
            $ledger_stmt->execute([$res_id]);
            $pending_months = $ledger_stmt->fetchAll();

            foreach ($pending_months as $month) {
                if ($remaining <= 0) break;

                $due = (float)$month['due_amount'];
                $can_pay = min($remaining, $due);
                
                $new_paid = (float)$month['paid_amount'] + $can_pay;
                $new_due = $due - $can_pay;
                $new_status = ($new_due <= 0) ? 'Paid' : 'Partial';

                $upd = $conn->prepare("UPDATE monthly_ledger SET paid_amount = ?, due_amount = ?, status = ? WHERE id = ?");
                $upd->execute([$new_paid, $new_due, $new_status, $month['id']]);
                
                $remaining -= $can_pay;
            }

            // 3. Handle Overpayment (Advance)
            if ($remaining > 0) {
                // Find latest month to apply advance or create next month
                $latest = $conn->prepare("SELECT * FROM monthly_ledger WHERE resident_id = ? ORDER BY year DESC, FIELD(month, 'December','November','October','September','August','July','June','May','April','March','February','January') DESC LIMIT 1");
                $latest->execute([$res_id]);
                $last_month = $latest->fetch();

                if ($last_month) {
                    $new_paid = (float)$last_month['paid_amount'] + $remaining;
                    // For advance, due remains 0 or negative? Usually status = 'Advance'
                    $upd = $conn->prepare("UPDATE monthly_ledger SET paid_amount = ?, status = 'Advance' WHERE id = ?");
                    $upd->execute([$new_paid, $last_month['id']]);
                }
            }

            $conn->commit();
            set_flash_message('success', "Payment of ₹" . number_format($amount, 2) . " processed successfully! Receipt: $receipt");
            header("Location: index.php");
            exit;
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("Payment error: " . $e->getMessage());
            $error = "System error during allocation. Please contact admin.";
        }
    }
}


// Get Stats
// Get Unified Stats from Ledger
$stats = $conn->query("
    SELECT 
        SUM(total_fee) as expected,
        SUM(paid_amount) as collected,
        SUM(due_amount) as pending
    FROM monthly_ledger
")->fetch(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<!-- Payment Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-hydro text-center border-0 shadow-sm" style="background-color: rgba(79, 70, 229, 0.05);">
            <small class="text-secondary text-uppercase fw-bold small">Ledger Target</small>
            <h3 class="fw-bold mb-0 text-primary mt-1">₹<?php echo number_format($stats['expected'] ?? 0, 2); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-hydro text-center border-0 shadow-sm" style="background-color: rgba(16, 185, 129, 0.05);">
            <small class="text-secondary text-uppercase fw-bold small">Fees Collected</small>
            <h3 class="fw-bold mb-0 text-success mt-1">₹<?php echo number_format($stats['collected'] ?? 0, 2); ?></h3>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-hydro text-center border-0 shadow-sm" style="background-color: rgba(239, 68, 68, 0.05);">
            <small class="text-secondary text-uppercase fw-bold small">Outstanding Dues</small>
            <h3 class="fw-bold mb-0 text-danger mt-1">₹<?php echo number_format($stats['pending'] ?? 0, 2); ?></h3>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-widget sticky-top" style="top: 90px; z-index: 10;">
            <h5 class="fw-bold mb-4"><i class="fas fa-plus-circle me-2 text-primary"></i>Quick Fee Entry</h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Resident</label>
                    <select name="resident_id" id="resident_select" class="form-select form-select-lg shadow-sm border-primary border-opacity-25" required>
                        <option value="">-- Start Typing Name --</option>
                        <?php
                        $resQuery = $conn->query("SELECT id, name, (SELECT SUM(due_amount) FROM monthly_ledger WHERE resident_id = residents.id) as current_due FROM residents WHERE status='active' ORDER BY name ASC");
                        while ($r = $resQuery->fetch(PDO::FETCH_ASSOC)) {
                            $due = number_format($r['current_due'] ?? 0, 0);
                            echo "<option value='{$r['id']}' data-due='{$r['current_due']}'>" . htmlspecialchars($r['name']) . " (Due: ₹$due)</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold text-danger">Amount Paying (₹) <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-danger text-white border-danger">₹</span>
                        <input type="number" step="0.01" min="1" name="amount" id="paying_amount" class="form-control border-danger" required placeholder="Enter amount">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Method</label>
                    <select name="payment_mode" class="form-select shadow-sm">
                        <option value="Cash">Cash</option>
                        <option value="UPI / Online">UPI / Online</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Debit/Credit Card">Debit/Credit Card</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Transaction ID / Ref. No</label>
                    <input type="text" name="transaction_id" class="form-control shadow-sm" placeholder="e.g. UPI Ref, Check No">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Internal Note</label>
                    <textarea name="note" class="form-control shadow-sm" rows="2" placeholder="Reference No. or other notes..."></textarea>
                </div>

                <button type="submit" name="add_payment" class="btn btn-primary-custom w-100 py-3 fw-bold rounded-pill shadow">
                    <i class="fas fa-receipt me-2"></i>Process Ledger Payment
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-widget h-100">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold mb-0">Collections & History</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="tableSearch" class="form-control" placeholder="Search resident..." style="width:200px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom filterable-table align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Resident</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("
                                SELECT p.*, r.name
                                FROM payments p
                                JOIN residents r ON p.resident_id = r.id
                                ORDER BY p.payment_date DESC, p.id DESC
                            ");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $row) {
                                $name  = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                                $amt   = number_format((float)$row['amount'], 2);
                                $date  = formatDate($row['payment_date']);
                                $receipt = $row['receipt_no'] ?: 'N/A';
                                $mode    = $row['payment_mode'] ?: 'Cash';

                                echo "<tr>
                                        <td><code class='text-primary small'>#{$receipt}</code></td>
                                        <td><strong>{$name}</strong></td>
                                        <td class='fw-bold'>₹{$amt}</td>
                                        <td><span class='badge badge-soft-secondary'>{$mode}</span></td>
                                        <td class='text-nowrap small'>{$date}</td>
                                        <td class='text-end'>
                                            <div class='dropdown d-inline-block'>
                                                <button class='btn btn-sm btn-light border rounded-pill' data-bs-toggle='dropdown'><i class='fas fa-ellipsis-h'></i></button>
                                                <ul class='dropdown-menu dropdown-menu-end shadow border-0'>
                                                    <li><a class='dropdown-item' href='receipt.php?id={$row['id']}'><i class='fas fa-print me-2 text-primary'></i> Print Receipt</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                      </tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='6' class='text-danger text-center'>Error loading payments.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic Fee Fetching
    const resSelect = document.getElementById('resident_select');
    const feeInput = document.getElementById('fee_amount');

    resSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const due = selected.getAttribute('data-due');
        if (due) {
            document.getElementById('paying_amount').value = parseFloat(due).toFixed(0);
        } else {
            document.getElementById('paying_amount').value = '';
        }
    });

    // Mark Paid Confirmation
    document.querySelectorAll('.mark-paid-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            Swal.fire({
                title: 'Confirm Payment?',
                text: 'Mark this record as fully paid?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Yes, Paid'
            }).then(v => {
                if(v.isConfirmed) this.closest('.mark-paid-form').submit();
            });
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
