<?php
/**
 * Application Helper Functions
 */

/**
 * Generate CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * Set a Flash Message (Success, Error, Warning, Info)
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get and Clear Flash Message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Sanitize Output for forms / html
 */
function sanitize_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date to DD/MM/YYYY (Institutional Standard)
 */
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($date));
}

/**
 * Auto-format Phone Numbers to +91XXXXXXXXXX
 */
function formatPhoneNumber($num) {
    $num = preg_replace('/\D/', '', $num); // Remove non-digits
    if (strlen($num) === 10) {
        return '+91' . $num;
    }
    if (strlen($num) === 12 && substr($num, 0, 2) === '91') {
        return '+' . $num;
    }
    return $num;
}

/**
 * Generate Sequential Invoice ID (#INV0001)
 */
function generateInvoiceID($id) {
    return '#INV' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

/**
 * Synchronize all missing ledger entries for a resident from admission to current month
 */
function syncResidentLedger($resident_id, $conn) {
    if (!$resident_id) return;
    
    $stmt = $conn->prepare("SELECT admit_date, monthly_fee, status, resident_type, approval_status FROM residents WHERE id = ?");
    $stmt->execute([$resident_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$res || $res['resident_type'] === 'free' || $res['approval_status'] !== 'approved') return;
    
    $start_date = new DateTime($res['admit_date']);
    $start_date->modify('first day of this month');
    $end_date   = new DateTime('first day of this month');
    $interval   = new DateInterval('P1M');
    $period     = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

    foreach ($period as $dt) {
        $month = $dt->format('F');
        $year  = $dt->format('Y');

        // Check if ledger entry exists
        $check = $conn->prepare("SELECT id FROM monthly_ledger WHERE resident_id = ? AND month = ? AND year = ?");
        $check->execute([$resident_id, $month, $year]);
        
        if (!$check->fetch()) {
            // Create entry
            $ins = $conn->prepare("INSERT INTO monthly_ledger (resident_id, month, year, total_fee, paid_amount, due_amount, status) VALUES (?, ?, ?, ?, 0, ?, 'Pending')");
            $ins->execute([$resident_id, $month, $year, $res['monthly_fee'], $res['monthly_fee']]);
        }
    }
}

/**
 * Standardized JSON Response for AJAX
 */
function jsonResponse($status, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));
    exit;
}
