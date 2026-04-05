<?php
$page_title = 'Health Records';
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// Access Control: Admin, Manager, Doctor, Nurse
$allowed_roles = ['admin', 'manager', 'doctor', 'nurse'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    set_flash_message('error', 'Access denied.');
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

$resident_id = isset($_GET['resident_id']) ? (int)$_GET['resident_id'] : 0;

// Handle Delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    $del_id = (int)$_POST['delete_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM health_records WHERE id = ?");
        $stmt->execute([$del_id]);
        if ($is_ajax) jsonResponse('success', "Record deleted.");
        
        set_flash_message('success', "Health record deleted.");
        header("Location: " . $_SERVER['PHP_SELF'] . ($resident_id ? "?resident_id={$resident_id}" : ""));
        exit;
    } catch (PDOException $e) {
        if ($is_ajax) jsonResponse('error', "Could not delete record.");
        set_flash_message('error', "Could not delete record.");
    }
}

// Handle Add/Update
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST['add_record']) || isset($_POST['update_record']))) {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    $res_id  = (int)($_POST['resident_id'] ?? 0);
    $date    = $_POST['checkup_date'] ?? date('Y-m-d');
    $temp    = trim($_POST['temp'] ?? '');
    $bp      = trim($_POST['blood_pressure'] ?? '');
    $meds    = trim($_POST['medicines'] ?? '');
    $notes   = trim($_POST['doctor_visit_notes'] ?? '');
    $rec_id  = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;

    if ($res_id <= 0 || empty($date)) {
        $error = "Resident and checkup date are required.";
        if ($is_ajax) { echo json_encode(['status'=>'error','message'=>$error]); exit; }
    } else {
        try {
            if ($rec_id > 0) {
                $stmt = $conn->prepare("UPDATE health_records SET checkup_date=?, temp=?, blood_pressure=?, medicines=?, doctor_visit_notes=?, added_by=? WHERE id=?");
                $stmt->execute([$date, $temp, $bp, $meds, $notes, $_SESSION['user_id'], $rec_id]);
                $msg = "Health record updated.";
            } else {
                $stmt = $conn->prepare("INSERT INTO health_records (resident_id, checkup_date, temp, blood_pressure, medicines, doctor_visit_notes, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$res_id, $date, $temp, $bp, $meds, $notes, $_SESSION['user_id']]);
                $rec_id = $conn->lastInsertId();
                $msg = "Health record saved.";
            }
            
            if ($is_ajax) {
                // Get fresh row HTML for instant insert
                $r_name = "";
                $rnQuery = $conn->prepare("SELECT name FROM residents WHERE id=?"); $rnQuery->execute([$res_id]);
                $r_name = $rnQuery->fetchColumn();
                
                $vitals = [];
                if ($temp) $vitals[] = 'T: ' . htmlspecialchars($temp);
                if ($bp)   $vitals[] = 'BP: ' . htmlspecialchars($bp);
                $vStr = $vitals ? implode(', ', $vitals) : '<span class="text-muted">—</span>';
                
                // Get the updated JSON for the new row's edit button
                $newRowStmt = $conn->prepare("SELECT * FROM health_records WHERE id=?");
                $newRowStmt->execute([$rec_id]);
                $newRow = $newRowStmt->fetch(PDO::FETCH_ASSOC);
                $newRow['name'] = $r_name;
                $rowJson = htmlspecialchars(json_encode($newRow), ENT_QUOTES, 'UTF-8');

                $newRowHtml = "
                    <tr class='table-success' id='hr-row-{$rec_id}'>
                        <td class='text-nowrap'><strong>" . formatDate($date) . "</strong></td>";
                if (!isset($_GET['resident_id'])) {
                    $newRowHtml .= "<td>" . htmlspecialchars($r_name) . "</td>";
                }
                $newRowHtml .= "
                        <td><small>{$vStr}</small></td>
                        <td>" . nl2br(htmlspecialchars($meds)) . "</td>
                        <td>" . nl2br(htmlspecialchars($notes)) . "</td>
                        <td class='text-end'>
                            <button class='btn btn-sm btn-outline-primary edit-btn' data-record='{$rowJson}'><i class='fas fa-edit'></i></button>
                            <form method='post' class='d-inline delete-form'>
                                <input type='hidden' name='delete_id' value='{$rec_id}'>
                                <button type='button' class='btn btn-sm btn-outline-danger confirm-del-btn'><i class='fas fa-trash'></i></button>
                            </form>
                        </td>
                    </tr>";
                
                echo json_encode(['status'=>'success', 'message'=>$msg, 'html'=>$newRowHtml, 'is_update'=>(isset($_POST['update_record']))]); 
                exit;
            }
            
            set_flash_message('success', $msg);
            header("Location: " . APP_URL . "/modules/health/index.php" . ($res_id ? "?resident_id={$res_id}" : ""));
            exit;
        } catch (PDOException $e) {
            error_log("Health record error: " . $e->getMessage());
            $error = "Error saving health record.";
            if ($is_ajax) { echo json_encode(['status'=>'error','message'=>$error]); exit; }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card-widget sticky-top" style="top: 90px; z-index: 10;">
            <h5 class="fw-bold mb-4"><i class="fas fa-heartbeat me-2 text-danger"></i>Log Daily Health</h5>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Resident</label>
                    <select name="resident_id" class="form-select" required onchange="window.location.href='index.php?resident_id='+this.value">
                        <option value="">-- Choose Resident --</option>
                        <?php
                        $resQuery = $conn->query("SELECT id, name FROM residents WHERE status='active' ORDER BY name ASC");
                        while ($r = $resQuery->fetch(PDO::FETCH_ASSOC)) {
                            $sel   = ($resident_id === (int)$r['id']) ? 'selected' : '';
                            $rname = htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8');
                            echo "<option value='{$r['id']}' {$sel}>{$rname}</option>";
                        }
                        ?>
                    </select>
                </div>

                <?php if ($resident_id > 0): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Checkup Date</label>
                    <input type="date" name="checkup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Temp (°F/°C)</label>
                        <input type="text" name="temp" class="form-control" placeholder="e.g. 98.6°F">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control" placeholder="e.g. 120/80">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Medicines Given</label>
                    <textarea name="medicines" class="form-control" rows="2" placeholder="List medications"></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Doctor Visit / Notes</label>
                    <textarea name="doctor_visit_notes" class="form-control" rows="3" placeholder="Doctor recommendations or observations"></textarea>
                </div>
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="add_record" value="1">
                <button type="submit" id="submitHealthBtn" class="btn btn-primary-custom w-100">
                    <i class="fas fa-save me-2"></i>Save Health Record
                </button>
                <?php else: ?>
                    <div class="alert alert-info py-2"><i class="fas fa-info-circle me-2"></i>Select a resident to add a record.</div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card-widget h-100">
            <h5 class="fw-bold mb-4">
                <?php echo $resident_id > 0 ? "Health History" : "Recent Health Updates (All Residents)"; ?>
            </h5>

            <div class="table-responsive">
                <table class="table table-custom filterable-table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <?php if (!$resident_id) echo "<th>Resident</th>"; ?>
                            <th>Vitals</th>
                            <th>Medicines</th>
                            <th>Notes</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            if ($resident_id > 0) {
                                $stmt = $conn->prepare("SELECT h.*, r.name FROM health_records h JOIN residents r ON h.resident_id = r.id WHERE h.resident_id = ? ORDER BY h.checkup_date DESC, h.id DESC");
                                $stmt->execute([$resident_id]);
                            } else {
                                $stmt = $conn->query("SELECT h.*, r.name FROM health_records h JOIN residents r ON h.resident_id = r.id ORDER BY h.checkup_date DESC, h.id DESC");
                            }

                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($rows as $row) {
                                $vitals = [];
                                if ($row['temp']) $vitals[] = 'T: ' . htmlspecialchars($row['temp']);
                                if ($row['blood_pressure']) $vitals[] = 'BP: ' . htmlspecialchars($row['blood_pressure']);
                                $vitalsStr = $vitals ? implode(', ', $vitals) : '<span class="text-muted">—</span>';
                                
                                // JSON for edit modal
                                $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                                echo "<tr id='hr-row-{$row['id']}'>
                                        <td class='text-nowrap'><strong>" . formatDate($row['checkup_date']) . "</strong></td>";

                                if (!$resident_id) {
                                    echo "<td>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</td>";
                                }

                                echo "  <td><small>{$vitalsStr}</small></td>
                                        <td>" . nl2br(htmlspecialchars($row['medicines'], ENT_QUOTES, 'UTF-8')) . "</td>
                                        <td>" . nl2br(htmlspecialchars($row['doctor_visit_notes'], ENT_QUOTES, 'UTF-8')) . "</td>
                                        <td class='text-end'>
                                            <button class='btn btn-sm btn-outline-primary edit-btn' data-record='{$rowJson}'><i class='fas fa-edit'></i></button>
                                            <form method='post' class='d-inline delete-form' data-id='{$row['id']}'>
                                                <input type='hidden' name='delete_id' value='{$row['id']}'>
                                                <input type='hidden' name='ajax' value='1'>
                                                <button type='button' class='btn btn-sm btn-outline-danger confirm-del-btn'><i class='fas fa-trash'></i></button>
                                            </form>
                                        </td>
                                      </tr>";
                            }
                        } catch (Exception $e) {
                            $cols = $resident_id ? 5 : 6;
                            echo "<tr><td colspan='{$cols}' class='text-danger'>Error loading records.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Health Record Modal -->
<div class="modal fade" id="editHealthModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Health Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="record_id" id="edit_record_id">
                <input type="hidden" name="resident_id" id="edit_resident_id">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Checkup Date</label>
                    <input type="date" name="checkup_date" id="edit_checkup_date" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Temp</label>
                        <input type="text" name="temp" id="edit_temp" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">BP</label>
                        <input type="text" name="blood_pressure" id="edit_blood_pressure" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Medicines</label>
                    <textarea name="medicines" id="edit_medicines" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="doctor_visit_notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="update_record" value="1">
                <button type="submit" id="updateHealthBtn" class="btn btn-primary-custom px-4 rounded-pill">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Declare editModal at top scope so all functions can access it
    const editModal = new bootstrap.Modal(document.getElementById('editHealthModal'));

    // AJAX submission for "Add Record"
    const hrForm = document.querySelector('form[method="post"]:not(.modal-content)');
    if (hrForm) {
        hrForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitHealthBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            btn.disabled = true;

            const formData = new FormData(this);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, "success");
                    const tbody = document.querySelector('.table-custom tbody');
                    const emptyRow = tbody.querySelector('.text-muted');
                    if (emptyRow && emptyRow.closest('tr')) emptyRow.closest('tr').remove();
                    
                    if (data.is_update) {
                        const recId = formData.get('record_id');
                        const oldRow = document.getElementById('hr-row-' + recId);
                        if (oldRow) oldRow.outerHTML = data.html; 
                    } else {
                        tbody.insertAdjacentHTML('afterbegin', data.html);
                    }
                    
                    hrForm.reset();
                    if (document.getElementsByName('resident_id')[0]) {
                        document.getElementsByName('resident_id')[0].value = formData.get('resident_id');
                    }
                } else {
                    showToast(data.message, "danger");
                }
            })
            .catch(err => {
                console.error(err);
                showToast("System error. Please try conventional reload.", "danger");
            })
            .finally(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                // Re-bind listeners for the new row's buttons
                bindDynamicEvents(); 
            });
        });
    }

    function bindDynamicEvents() {
        // Edit Modal re-binding for AJAX injected rows
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.onclick = function() {
                const data = JSON.parse(this.getAttribute('data-record'));
                document.getElementById('edit_record_id').value = data.id;
                document.getElementById('edit_resident_id').value = data.resident_id;
                document.getElementById('edit_checkup_date').value = data.checkup_date;
                document.getElementById('edit_temp').value = data.temp;
                document.getElementById('edit_blood_pressure').value = data.blood_pressure;
                document.getElementById('edit_medicines').value = data.medicines;
                document.getElementById('edit_notes').value = data.doctor_visit_notes;
                editModal.show();
            };
        });

        document.querySelectorAll('.confirm-del-btn').forEach(btn => {
            btn.onclick = function() {
                const form = this.closest('.delete-form');
                const rowId = form.getAttribute('data-id');
                
                Swal.fire({
                    title: 'Delete this record?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    confirmButtonText: 'Yes, Delete'
                }).then(v => {
                    if(v.isConfirmed) {
                        const formData = new FormData(form);
                        fetch(window.location.href, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                showToast(data.message, "success");
                                const row = document.getElementById('hr-row-' + rowId);
                                if (row) row.style.opacity = '0';
                                setTimeout(() => { if (row) row.remove(); }, 300);
                            } else {
                                showToast(data.message, "danger");
                            }
                        });
                    }
                });
            };
        });
    }
    
    // AJAX submission for "Update"
    const editForm = document.querySelector('#editHealthModal form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('updateHealthBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            btn.disabled = true;

            const formData = new FormData(this);
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, "success");
                    const recId = formData.get('record_id');
                    const oldRow = document.getElementById('hr-row-' + recId);
                    if (oldRow) oldRow.outerHTML = data.html;
                    editModal.hide();
                    bindDynamicEvents();
                } else { showToast(data.message, "danger"); }
            })
            .finally(() => { 
                btn.innerHTML = originalHtml; 
                btn.disabled = false; 
            });
        });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
