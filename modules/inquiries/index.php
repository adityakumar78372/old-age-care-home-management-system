<?php
$page_title = 'Inquiries Management';
require_once '../../db_connect.php';
require_once '../../includes/header.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Access denied. Admin only.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

// Handle Mark as Read/Unread
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $new_status = in_array($_POST['new_status'] ?? '', ['read', 'unread']) ? $_POST['new_status'] : null;

    if ($inquiry_id > 0 && $new_status) {
        try {
            $stmt = $conn->prepare("UPDATE inquiries SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $inquiry_id]);
            set_flash_message('success', "Inquiry marked as '{$new_status}'.");
        } catch (PDOException $e) {
            set_flash_message('error', "Error updating inquiry status.");
        }
    }
    header("Location: " . APP_URL . "/modules/inquiries/index.php");
    exit;
}

// Fetch Inquiries (single query, split in PHP)
$unread_inquiries = [];
$read_inquiries   = [];

try {
    $stmt = $conn->query("SELECT * FROM inquiries ORDER BY created_at DESC LIMIT 200");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['status'] === 'unread') {
            $unread_inquiries[] = $row;
        } else {
            $read_inquiries[] = $row;
        }
    }
} catch (PDOException $e) {
    $dbError = "Database Error: " . $e->getMessage();
}
?>

<div class="row g-4">
    <div class="col-12">
        <div class="card-widget">
            <div class="d-flex align-items-center mb-4 gap-3 flex-wrap">
                <h5 class="fw-bold mb-0"><i class="fas fa-envelope-open-text me-2 text-primary-custom"></i>Inquiries Management</h5>
                <?php if (!empty($unread_inquiries)): ?>
                <span class="badge bg-danger fs-6"><?php echo count($unread_inquiries); ?> Unread</span>
                <?php endif; ?>
            </div>
            <?php if (isset($dbError)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($dbError); ?></div>
            <?php endif; ?>

            <!-- Nav Tabs -->
            <ul class="nav nav-tabs" id="inquiryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#unread" type="button" role="tab">
                        <i class="fas fa-envelope me-2 text-danger"></i>Unread (<?php echo count($unread_inquiries); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#read" type="button" role="tab">
                        <i class="fas fa-envelope-open me-2 text-success"></i>Read (<?php echo count($read_inquiries); ?>)
                    </button>
                </li>
            </ul>

            <!-- Tab Contents -->
            <div class="tab-content mt-4">

                <!-- Unread Tab -->
                <div class="tab-pane fade show active" id="unread" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Service</th>
                                    <th>Message</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($unread_inquiries)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>All caught up! No unread inquiries.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($unread_inquiries as $row): ?>
                                    <tr style="background-color: rgba(79,70,229,0.04);">
                                        <td class="text-nowrap small">
                                            <strong><?php echo date('d M Y', strtotime($row['created_at'])); ?></strong><br>
                                            <span class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($row['service_required'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td style="max-width:280px; white-space:normal;"><?php echo nl2br(htmlspecialchars($row['message'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="inquiry_id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="new_status" value="read">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-success mb-1" title="Mark Read">
                                                    <i class="fas fa-check-double"></i> Read
                                                </button>
                                            </form>
                                            <?php if (!empty($row['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-primary" title="Reply">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Read Tab -->
                <div class="tab-pane fade" id="read" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Service</th>
                                    <th>Message</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($read_inquiries)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No read inquiries.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($read_inquiries as $row): ?>
                                    <tr class="text-muted">
                                        <td class="text-nowrap small">
                                            <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                            <span><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?><br>
                                            <small><?php echo htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['service_required'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td style="max-width:280px; white-space:normal;"><?php echo nl2br(htmlspecialchars($row['message'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="inquiry_id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="new_status" value="unread">
                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-outline-warning" title="Mark Unread">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
