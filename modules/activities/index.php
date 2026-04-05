<?php
$page_title = 'Activity & Event Management';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// Handle Delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id']) && in_array($_SESSION['role'], ['admin', 'manager'])) {
    $del_id = (int)$_POST['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
        $stmt->execute([$del_id]);
        set_flash_message('success', "Activity deleted successfully!");
        header("Location: " . APP_URL . "/modules/activities/index.php");
        exit;
    } catch (PDOException $e) {
        set_flash_message('error', "Error deleting activity.");
    }
}

// Handle Add/Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST['add_activity']) || isset($_POST['update_activity'])) && in_array($_SESSION['role'], ['admin', 'manager'])) {
    $title       = trim($_POST['title'] ?? '');
    $date        = $_POST['activity_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $act_id      = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;

    if (empty($title) || empty($date) || empty($description)) {
        $error = "Title, Date, and Description are required.";
    } else {
        try {
            if ($act_id > 0) {
                $stmt = $conn->prepare("UPDATE activities SET title=?, activity_date=?, description=? WHERE id=?");
                $stmt->execute([$title, $date, $description, $act_id]);
                set_flash_message('success', "Activity updated successfully!");
            } else {
                $stmt = $conn->prepare("INSERT INTO activities (title, activity_date, description) VALUES (?, ?, ?)");
                $stmt->execute([$title, $date, $description]);
                set_flash_message('success', "Activity '{$title}' added successfully!");
            }
            header("Location: " . APP_URL . "/modules/activities/index.php");
            exit;
        } catch (PDOException $e) {
            error_log("Activity error: " . $e->getMessage());
            $error = "Error saving activity. Please try again.";
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row g-4">
    <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
    <div class="col-lg-4">
        <div class="card-widget">
            <h5 class="fw-bold mb-4"><i class="fas fa-calendar-plus me-2 text-primary-custom"></i>Schedule Event</h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Event/Activity Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Diwali Celebration, Morning Yoga" maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="activity_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Event details, venue, instructions..." required maxlength="1000"></textarea>
                </div>
                <button type="submit" name="add_activity" class="btn btn-primary-custom w-100">
                    <i class="fas fa-plus me-2"></i>Add Activity
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
    <?php else: ?>
    <div class="col-12">
    <?php endif; ?>
        <div class="card-widget">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <h5 class="fw-bold mb-0"><i class="fas fa-calendar-check me-2 text-primary-custom"></i>Upcoming & Past Activities</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="tableSearch" class="form-control" placeholder="Search activities..." style="width:220px;">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom filterable-table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Event Title</th>
                            <th>Description</th>
                            <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                            <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT * FROM activities ORDER BY activity_date DESC LIMIT 100");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $row) {
                                $isPast = strtotime($row['activity_date']) < strtotime('today');
                                $isToday = date('Y-m-d', strtotime($row['activity_date'])) === date('Y-m-d');
                                $rowClass = $isPast ? 'text-muted' : 'fw-semibold';
                                $statusBadge = $isPast ? "<span class='badge bg-secondary'>Past</span>" : ($isToday ? "<span class='badge bg-warning text-dark'>Today!</span>" : "<span class='badge bg-success'>Upcoming</span>");

                                $title = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
                                $desc  = htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8');
                                $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                                echo "<tr class='{$rowClass}'>
                                        <td class='text-nowrap'><strong>" . formatDate($row['activity_date']) . "</strong></td>
                                        <td>{$statusBadge}</td>
                                        <td><strong>{$title}</strong></td>
                                        <td style='max-width:300px;'>{$desc}</td>";
                                
                                if (in_array($_SESSION['role'], ['admin', 'manager'])) {
                                    echo "<td class='text-end text-nowrap'>
                                            <button class='btn btn-sm btn-outline-primary edit-btn' data-activity='{$rowJson}'><i class='fas fa-edit'></i></button>
                                            <form method='post' class='d-inline delete-form'>
                                                <input type='hidden' name='delete_id' value='{$row['id']}'>
                                                <button type='button' class='btn btn-sm btn-outline-danger confirm-del-btn'><i class='fas fa-trash'></i></button>
                                            </form>
                                          </td>";
                                }
                                echo "</tr>";
                            }
                        } catch (Exception $e) {
                            $cols = in_array($_SESSION['role'], ['admin', 'manager']) ? 5 : 4;
                            echo "<tr><td colspan='{$cols}' class='text-danger text-center'>Error loading activities.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Activity Modal -->
<div class="modal fade" id="editActivityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Activity/Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="activity_id" id="edit_activity_id">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Event Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="edit_title" class="form-control" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                    <input type="date" name="activity_date" id="edit_activity_date" class="form-control" required>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="description" id="edit_description" class="form-control" rows="4" required maxlength="1000"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_activity" class="btn btn-primary-custom px-4 rounded-pill shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal logic
    const editModal = new bootstrap.Modal(document.getElementById('editActivityModal'));
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.getAttribute('data-activity'));
            document.getElementById('edit_activity_id').value = data.id;
            document.getElementById('edit_title').value = data.title;
            document.getElementById('edit_activity_date').value = data.activity_date;
            document.getElementById('edit_description').value = data.description;
            editModal.show();
        });
    });

    // Delete Confirmation
    document.querySelectorAll('.confirm-del-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            Swal.fire({
                title: 'Delete this activity?',
                text: 'This action cannot be undone and will be removed from the list.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(v => {
                if(v.isConfirmed) this.closest('.delete-form').submit();
            });
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
