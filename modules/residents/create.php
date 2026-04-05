<?php
require_once '../../db_connect.php';
require_once '../../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Only admins/managers can access create page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    set_flash_message('error', 'Access denied. Authorized staff only.');
    header("Location: " . APP_URL . "/modules/residents/index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_resident'])) {
    $name        = trim($_POST['name'] ?? '');
    $dob         = $_POST['dob'] ?? '';
    $gender      = $_POST['gender'] ?? '';
    $contact     = trim($_POST['contact'] ?? '');
    $emergency   = trim($_POST['emergency_contact'] ?? '');
    $admit       = $_POST['admit_date'] ?? date('Y-m-d');
    $room        = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $history     = trim($_POST['medical_history'] ?? '');
    $status      = $_POST['status'] ?? 'active';
    $type        = $_POST['resident_type'] ?? 'paid';
    $plan        = $_POST['plan'] ?? null;
    $reason      = trim($_POST['reason_for_free'] ?? '');
    $monthly_fee = ($type === 'free') ? 0 : (!empty($_POST['monthly_fee']) ? (float)$_POST['monthly_fee'] : 5000.00);
    $address     = trim($_POST['address'] ?? '');
    $family_contact = trim($_POST['family_contact'] ?? '');
    $photo_path  = null;

    // Set approval status logic
    $approval_status = ($type === 'free') ? 'pending' : 'approved';

    // Handle File Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../assets/uploads/residents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = uniqid('resident_', true) . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_file_name)) {
                $photo_path = 'assets/uploads/residents/' . $new_file_name;
            }
        }
    }

    // Server-side validation
    if (empty($name) || empty($dob) || empty($gender) || empty($emergency) || empty($admit) || empty($address)) {
        $error = "Please fill in all required fields (Name, DOB, Gender, Emergency Contact, Date, and Address).";
    } elseif (!preg_match('/^\d{10}$/', $emergency)) {
        $error = "Emergency contact must be exactly 10 digits.";
    } elseif (!empty($contact) && !preg_match('/^\d{10}$/', $contact)) {
        $error = "Contact number must be exactly 10 digits.";
    } else {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO residents (name, dob, gender, resident_type, approval_status, reason_for_free, contact, emergency_contact, admit_date, room_id, status, medical_history, profile_photo, plan, monthly_fee, address, family_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $dob, $gender, $type, $approval_status, $reason, $contact, $emergency, $admit, $room, $status, $history, $photo_path, $plan, $monthly_fee, $address, $family_contact]);
            $res_id = $conn->lastInsertId();

            // Initialize History Logs
            if ($room) {
                $h1 = $conn->prepare("INSERT INTO resident_room_history (resident_id, room_id, start_date) VALUES (?, ?, ?)");
                $h1->execute([$res_id, $room, $admit]);
            }
            $h2 = $conn->prepare("INSERT INTO resident_status_history (resident_id, status, change_date) VALUES (?, 'active', ?)");
            $h2->execute([$res_id, $admit]);

            // Sync Financial Ledger
            syncResidentLedger($res_id, $conn);

            $conn->commit();

            if ($type === 'free') {
                set_flash_message('info', "Request for '{$name}' submitted for approval.");
            } else {
                set_flash_message('success', "Resident '{$name}' added successfully!");
            }
            header("Location: " . APP_URL . "/modules/residents/index.php");
            exit;
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("Add resident error: " . $e->getMessage());
            $error = "Error saving resident. Status: " . $e->getMessage();
        }
    }
}

$page_title = 'Add Resident';
require_once '../../includes/header.php';
?>

<style>
    .wizard-step { display: none; }
    .wizard-step.active { display: block; animation: fadeInStep 0.4s; }
    .step-indicator { display: flex; justify-content: space-between; margin-bottom: 2.5rem; position: relative; }
    .step-indicator::before { content: ''; position: absolute; top: 17px; left: 0; width: 100%; height: 3px; background: var(--border-color); z-index: 1; }
    .step-btn { position: relative; z-index: 2; border-radius: 50%; width: 40px; height: 40px; border: none; background: var(--border-color); color: var(--text-secondary); font-weight: bold; transition: all 0.3s; cursor: default; }
    .step-btn.active { background: var(--primary-color); color: white; box-shadow: 0 0 0 5px rgba(79, 70, 229, 0.2); }
    .step-btn.completed { background: var(--secondary-color); color: white; }
    .step-label { display: block; margin-top: 8px; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
    @keyframes fadeInStep { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-widget position-relative overflow-hidden mb-5">
            <h5 class="fw-bold mb-4 border-bottom pb-3">
                <a href="index.php" class="text-decoration-none text-secondary me-2"><i class="fas fa-arrow-left"></i> Back</a>
                <span class="text-muted">|</span>
                <span class="ms-2">Add New Resident</span>
            </h5>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <!-- Wizard Progress -->
            <div class="step-indicator px-4">
                <div class="text-center">
                    <button class="step-btn active" id="btn-step-1" type="button">1</button>
                    <span class="step-label">Personal Info</span>
                </div>
                <div class="text-center">
                    <button class="step-btn" id="btn-step-2" type="button">2</button>
                    <span class="step-label">Admission</span>
                </div>
                <div class="text-center">
                    <button class="step-btn" id="btn-step-3" type="button">3</button>
                    <span class="step-label">Medical History</span>
                </div>
            </div>

            <form method="post" id="residentWizard" enctype="multipart/form-data" novalidate>
                <!-- Step 1: Personal Info -->
                <div class="wizard-step active" id="step-1">
                    <h6 class="fw-bold text-primary mb-3"><i class="fas fa-user-circle me-2"></i> Personal Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg" placeholder="e.g. Ram Kumar" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="dob" class="form-control form-control-lg" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select form-control-lg" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Permanent Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control form-control-lg" rows="2" placeholder="Full residential address" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-primary">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control form-control-lg" accept="image/*">
                            <small class="text-muted">Optional. Max 2MB.</small>
                        </div>
                    </div>
                    <div class="text-end mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-primary-custom px-4 rounded-pill btn-next shadow-sm">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </div>

                <!-- Step 2: Admission & Contact -->
                <div class="wizard-step" id="step-2">
                    <h6 class="fw-bold text-primary mb-3"><i class="fas fa-address-card me-2"></i> Contact & Assignment</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Primary Contact</label>
                            <input type="tel" name="contact" class="form-control form-control-lg" placeholder="10-digit number" pattern="[0-9]{10}" maxlength="10" oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-danger">Emergency Contact <span class="text-danger">*</span></label>
                            <input type="tel" name="emergency_contact" class="form-control form-control-lg border-danger" placeholder="10-digit number" pattern="[0-9]{10}" maxlength="10" required oninput="this.value=this.value.replace(/\D/g,'').slice(0,10)">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Family Contact Number</label>
                            <input type="tel" name="family_contact" class="form-control form-control-lg phone-prefix" placeholder="10-digit number" maxlength="15">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Admission Date <span class="text-danger">*</span></label>
                            <input type="date" name="admit_date" class="form-control form-control-lg" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assign Room</label>
                            <select name="room_id" id="room_select" class="form-select form-control-lg">
                                <option value="" data-type="Non-AC">-- No Room Assigned --</option>
                                <?php
                                $rooms = $conn->prepare("SELECT r.id, r.room_number, r.capacity, r.room_type, (SELECT COUNT(*) FROM residents WHERE room_id = r.id AND status='active') AS occ FROM rooms r WHERE r.status != 'maintenance'");
                                $rooms->execute();
                                while ($rm = $rooms->fetch(PDO::FETCH_ASSOC)) {
                                    if ($rm['occ'] < $rm['capacity']) {
                                        $rn = htmlspecialchars($rm['room_number'], ENT_QUOTES, 'UTF-8');
                                        $rt = htmlspecialchars($rm['room_type'] ?? 'Non-AC', ENT_QUOTES, 'UTF-8');
                                        echo "<option value='{$rm['id']}' data-type='{$rt}'>Room {$rn} [{$rt}] ({$rm['occ']}/{$rm['capacity']} beds)</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Resident Type <span class="text-danger">*</span></label>
                            <select name="resident_type" id="resident_type" class="form-select form-control-lg" required>
                                <option value="paid" selected>Paid / Private</option>
                                <option value="free">Free / NGO Support</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="plan_section">
                            <label class="form-label fw-semibold">Membership Plan</label>
                            <select name="plan" id="plan_select" class="form-select form-control-lg">
                                <option value="basic" data-fee="5000">Basic (₹5,000)</option>
                                <option value="standard" data-fee="6000">Standard (₹6,000)</option>
                                <option value="premium" data-fee="7000">Premium (₹7,000)</option>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="reason_section">
                            <label class="form-label fw-semibold">Reason for Free Residency <span class="text-danger">*</span></label>
                            <textarea name="reason_for_free" id="reason_for_free" class="form-control" rows="2" placeholder="Explain health/financial status for consideration..."></textarea>
                        </div>
                        <div class="col-md-6" id="fee_section">
                            <label class="form-label fw-semibold">Monthly Fee (₹)</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="monthly_fee" id="monthly_fee" class="form-control form-control-lg" value="5000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select form-control-lg" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-light border px-4 rounded-pill btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
                        <button type="button" class="btn btn-primary-custom px-4 rounded-pill btn-next shadow-sm">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </div>

                <!-- Step 3: Medical History -->
                <div class="wizard-step" id="step-3">
                    <h6 class="fw-bold text-primary mb-3"><i class="fas fa-notes-medical me-2"></i> Medical Details</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Medical History & Allergies</label>
                            <textarea name="medical_history" class="form-control" rows="6" placeholder="List any chronic conditions, allergies, regular medications, or past surgeries..."></textarea>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 border-0 rounded-3 mt-3">
                        <i class="fas fa-info-circle me-2 text-info"></i> Almost done! Please ensure all details are correct before saving.
                    </div>
                    <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                        <button type="button" class="btn btn-light border px-4 rounded-pill btn-prev"><i class="fas fa-arrow-left me-2"></i> Back</button>
                        <button type="submit" name="add_resident" class="btn btn-success px-5 rounded-pill fw-bold fs-5 shadow-sm"><i class="fas fa-save me-2"></i> Save Profile</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const steps = document.querySelectorAll('.wizard-step');
    const btnsNext = document.querySelectorAll('.btn-next');
    const btnsPrev = document.querySelectorAll('.btn-prev');
    const indicators = document.querySelectorAll('.step-btn');
    let currentStep = 0;

    function showStep(index) {
        steps.forEach((step, i) => {
            step.classList.toggle('active', i === index);
            if (i < index) {
                indicators[i].classList.add('completed');
                indicators[i].classList.remove('active');
                indicators[i].innerHTML = '<i class="fas fa-check"></i>';
            } else if (i === index) {
                indicators[i].classList.add('active');
                indicators[i].classList.remove('completed');
                indicators[i].textContent = i + 1;
            } else {
                indicators[i].classList.remove('active', 'completed');
                indicators[i].textContent = i + 1;
            }
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(index) {
        let isValid = true;
        const inputs = steps[index].querySelectorAll('[required]');
        inputs.forEach(input => {
            const valid = input.checkValidity() && input.value.trim() !== '';
            input.classList.toggle('is-invalid', !valid);
            if (!valid) isValid = false;
        });
        return isValid;
    }

    btnsNext.forEach(btn => {
        btn.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                currentStep++;
                showStep(currentStep);
            } else {
                if (typeof showToast === 'function') {
                    showToast("Please fill all required fields before proceeding.", "danger");
                }
            }
        });
    });

    btnsPrev.forEach(btn => {
        btn.addEventListener('click', () => {
            currentStep--;
            showStep(currentStep);
        });
    });

    // Clear is-invalid on input
    document.querySelectorAll('.form-control, .form-select').forEach(el => {
        el.addEventListener('input', () => el.classList.remove('is-invalid'));
    });

    // Phone Number auto-prefix +91
    document.querySelectorAll('.phone-prefix').forEach(input => {
        input.addEventListener('blur', function() {
            let val = this.value.replace(/\D/g, '');
            if (val.length === 10) {
                this.value = '+91' + val;
            } else if (val.length === 12 && val.startsWith('91')) {
                this.value = '+' + val;
            }
        });
    });

    // Monthly Fee Auto-Suggestion based on Plan or Room Type
    const roomSelect = document.getElementById('room_select');
    const feeInput = document.getElementById('monthly_fee');
    const typeSelect = document.getElementById('resident_type');
    const planSelect = document.getElementById('plan_select');
    const planSection = document.getElementById('plan_section');
    const reasonSection = document.getElementById('reason_section');
    const feeSection = document.getElementById('fee_section');
    const reasonInput = document.getElementById('reason_for_free');

    function updateVisibility() {
        if (typeSelect.value === 'free') {
            planSection.classList.add('d-none');
            feeSection.classList.add('d-none');
            reasonSection.classList.remove('d-none');
            reasonInput.setAttribute('required', 'required');
            feeInput.value = '0';
        } else {
            planSection.classList.remove('d-none');
            feeSection.classList.remove('d-none');
            reasonSection.classList.add('d-none');
            reasonInput.removeAttribute('required');
            updateFeeFromPlan();
        }
    }

    function updateFeeFromPlan() {
        if (typeSelect.value === 'paid') {
            const selectedOption = planSelect.options[planSelect.selectedIndex];
            feeInput.value = selectedOption.getAttribute('data-fee') || '5000';
        }
    }

    typeSelect.addEventListener('change', updateVisibility);
    planSelect.addEventListener('change', updateFeeFromPlan);

    roomSelect.addEventListener('change', function() {
        if (typeSelect.value === 'paid') {
            const selectedOption = this.options[this.selectedIndex];
            const roomType = selectedOption.getAttribute('data-type');
            if (roomType === 'AC') {
                planSelect.value = 'premium';
            } else {
                planSelect.value = 'basic';
            }
            updateFeeFromPlan();
        }
    });

    // Initial check
    updateVisibility();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
