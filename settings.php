<?php
$page_title = 'Settings';
require_once 'db_connect.php';
require_once 'includes/header.php';
?>

<div class="row g-4">
    <div class="col-12 col-xl-8 mx-auto mt-4">
        <div class="card-widget border-0 shadow-sm" style="border-radius: 16px;">
            <div class="card-body p-5">
                <div class="d-flex align-items-center mb-5 border-bottom pb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                        <i class="fas fa-palette text-primary fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1">Appearance Settings</h4>
                        <p class="text-muted mb-0 small">Customize how the dashboard looks and feels to you.</p>
                    </div>
                </div>

                <!-- Text Size Setting -->
                <div class="row mb-5 pb-4 border-bottom align-items-center">
                    <div class="col-md-5 mb-3 mb-md-0">
                        <h6 class="fw-bold fs-6 mb-1"><i class="fas fa-text-height me-2 text-primary"></i> Text Size</h6>
                        <p class="text-muted small mb-0">Adjust the font size across the whole application.</p>
                    </div>
                    <div class="col-md-7">
                        <div class="d-flex justify-content-between align-items-center mb-2 px-2 text-muted fw-bold small">
                            <span>A</span>
                            <span id="scaleValueBadge" class="badge bg-primary rounded-pill px-3 py-2">100%</span>
                            <span class="fs-5">A</span>
                        </div>
                        <input type="range" class="form-range" id="uiScaleSlider" min="80" max="150" step="5" value="100">
                        
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="setScale(80)">80%</button>
                            <button class="btn btn-outline-primary btn-sm flex-fill fw-bold" onclick="setScale(100)" id="scaleDefault">100%</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="setScale(120)">120%</button>
                            <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="setScale(150)">150%</button>
                        </div>
                    </div>
                </div>

                <!-- Bolder Text Setting -->
                <div class="row align-items-center">
                    <div class="col-md-8 mb-3 mb-md-0">
                        <h6 class="fw-bold fs-6 mb-1"><i class="fas fa-bold me-2 text-primary"></i> Bolder Text</h6>
                        <p class="text-muted small mb-0">Increase font weight to improve readability.</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="form-check form-switch d-inline-block" style="transform: scale(1.3);">
                            <input class="form-check-input" type="checkbox" role="switch" id="boldTextToggle" onchange="toggleBoldText()">
                        </div>
                    </div>
                </div>

            </div>
        </div>
        

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <!-- Database Maintenance Setting -->
        <div class="card-widget border-0 shadow-sm mt-4" style="border-radius: 16px;">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="fas fa-database text-success fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Database Maintenance</h5>
                            <p class="text-muted mb-0 small">Download a complete SQL backup of all system data.</p>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo APP_URL; ?>/modules/admin/backup_db.php" class="btn btn-success px-4 rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-download me-2"></i> Download Backup
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Optional Notification -->
        <div class="text-center mt-4">
            <p class="text-muted small"><i class="fas fa-info-circle me-1"></i> These settings are saved in your browser and automatically apply on your next visit.</p>
        </div>
    </div>
</div>

<script>
    // Initialize UI on load
    document.addEventListener('DOMContentLoaded', () => {
        const slider = document.getElementById('uiScaleSlider');
        const badge = document.getElementById('scaleValueBadge');
        const boldToggle = document.getElementById('boldTextToggle');
        
        // Setup initial text size
        const currentSize = localStorage.getItem('oahms_fontSize') || '100';
        slider.value = currentSize;
        badge.innerText = currentSize + '%';
        updatePresetButtons(currentSize);
        
        slider.addEventListener('input', function() {
            setScale(this.value);
        });

        // Setup bolder text
        const currentWeight = localStorage.getItem('oahms_fontWeight');
        if (currentWeight === 'bold') {
            boldToggle.checked = true;
        }
    });

    function setScale(value) {
        document.getElementById('uiScaleSlider').value = value;
        document.getElementById('scaleValueBadge').innerText = value + '%';
        
        let val = parseInt(value, 10);
        document.documentElement.style.fontSize = (val / 100 * 16) + 'px';
        localStorage.setItem('oahms_fontSize', val);
        
        updatePresetButtons(value);
    }
    
    function updatePresetButtons(value) {
        // Highlight active preset
        document.querySelectorAll('button[onclick^="setScale"]').forEach(btn => {
            if (btn.innerText === value + '%') {
                btn.className = "btn btn-outline-primary btn-sm flex-fill fw-bold";
            } else {
                btn.className = "btn btn-outline-secondary btn-sm flex-fill";
            }
        });
    }

    function toggleBoldText() {
        const isChecked = document.getElementById('boldTextToggle').checked;
        if (isChecked) {
            document.documentElement.classList.add('bolder-text');
            document.body.classList.add('bolder-text');
            localStorage.setItem('oahms_fontWeight', 'bold');
        } else {
            document.documentElement.classList.remove('bolder-text');
            document.body.classList.remove('bolder-text');
            localStorage.removeItem('oahms_fontWeight');
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
