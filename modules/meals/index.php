<?php
$page_title = 'Weekly Meal Plan';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// Auth check
if (!in_array($_SESSION['role'], ['admin', 'manager', 'cook'])) {
    set_flash_message('error', 'Access denied.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

// Handle Update Meal - Restriction: Only Admin/Manager
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_meal']) && in_array($_SESSION['role'], ['admin', 'manager'])) {
    $id        = (int)$_POST['meal_id'];
    $breakfast = trim($_POST['breakfast'] ?? '');
    $lunch     = trim($_POST['lunch'] ?? '');
    $tea       = trim($_POST['tea'] ?? '');
    $dinner    = trim($_POST['dinner'] ?? '');
    $cook      = trim($_POST['cook'] ?? '');
    $helper    = trim($_POST['helper'] ?? '');

    try {
        $stmt = $conn->prepare("UPDATE meal_plan SET breakfast=?, lunch=?, tea=?, dinner=?, cook=?, helper=? WHERE id=?");
        $stmt->execute([$breakfast, $lunch, $tea, $dinner, $cook, $helper, $id]);
        set_flash_message('success', "Meal plan updated successfully!");
    } catch (PDOException $e) {
        set_flash_message('error', "Error updating meal plan.");
        error_log("Meal update error: " . $e->getMessage());
    }
    header("Location: index.php");
    exit;
}

require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-12">
        <div class="card-widget">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">
                    <i class="fas fa-utensils me-2 text-primary-custom"></i>Weekly Meal Plan Management
                </h5>
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print Plan
                </button>
            </div>
            
            <div class="alert alert-info py-2 mb-4 border-0 rounded-3">
                <i class="fas fa-info-circle me-2"></i> Update the weekly menu here. These changes will reflect on the public landing page.
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle text-center">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Breakfast</th>
                            <th>Lunch</th>
                            <th>Evening Tea</th>
                            <th>Dinner</th>
                            <th>Cook</th>
                            <th>Helper</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->query("SELECT * FROM meal_plan ORDER BY id ASC");
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            $data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?php echo $row['day']; ?></td>
                            <td><?php echo htmlspecialchars($row['breakfast']); ?></td>
                            <td><?php echo htmlspecialchars($row['lunch']); ?></td>
                            <td><?php echo htmlspecialchars($row['tea']); ?></td>
                            <td><?php echo htmlspecialchars($row['dinner']); ?></td>
                            <td><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($row['cook']); ?></span></td>
                            <td><span class="badge badge-soft-secondary"><?php echo htmlspecialchars($row['helper']); ?></span></td>
                            <td class="text-end">
                                <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary edit-meal-btn" data-meal='<?php echo $data; ?>'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php else: ?>
                                <span class="text-muted small">View Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Meal Modal -->
<div class="modal fade" id="editMealModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit <span id="modal-day-name"></span> Meal Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="meal_id" id="modal-meal-id">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Breakfast</label>
                        <input type="text" name="breakfast" id="modal-breakfast" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Lunch</label>
                        <input type="text" name="lunch" id="modal-lunch" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Evening Tea</label>
                        <input type="text" name="tea" id="modal-tea" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Dinner</label>
                        <input type="text" name="dinner" id="modal-dinner" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-primary">Assigned Cook</label>
                        <input type="text" name="cook" id="modal-cook" class="form-control" placeholder="e.g. LAXMI">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-primary">Assigned Helper</label>
                        <input type="text" name="helper" id="modal-helper" class="form-control" placeholder="e.g. MTS">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_meal" class="btn btn-primary-custom rounded-pill px-4 shadow-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = new bootstrap.Modal(document.getElementById('editMealModal'));
    
    document.querySelectorAll('.edit-meal-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.getAttribute('data-meal'));
            
            document.getElementById('modal-day-name').textContent = data.day;
            document.getElementById('modal-meal-id').value = data.id;
            document.getElementById('modal-breakfast').value = data.breakfast;
            document.getElementById('modal-lunch').value = data.lunch;
            document.getElementById('modal-tea').value = data.tea;
            document.getElementById('modal-dinner').value = data.dinner;
            document.getElementById('modal-cook').value = data.cook;
            document.getElementById('modal-helper').value = data.helper;
            
            editModal.show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
