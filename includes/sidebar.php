<nav id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-home-heart"></i>
        <span>OAHMS</span>
    </div>

    <ul class="list-unstyled components">
        <li class="<?php echo isActive('dashboard.php'); ?>">
            <a href="<?php echo APP_URL; ?>/dashboard.php">
                <i class="fas fa-chart-pie fa-fw"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'doctor', 'nurse'])): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/residents/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/residents/index.php">
                <i class="fas fa-users-viewfinder fa-fw"></i>
                <span>Residents</span>
            </a>
        </li>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/approvals.php') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/admin/approvals.php">
                <i class="fas fa-user-check fa-fw"></i>
                <span>Approvals</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/rooms/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/rooms/index.php">
                <i class="fas fa-bed fa-fw"></i>
                <span>Rooms</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'doctor', 'nurse'])): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/health/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/health/index.php">
                <i class="fas fa-notes-medical fa-fw"></i>
                <span>Health Records</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/payments/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/payments/index.php">
                <i class="fas fa-file-invoice-dollar fa-fw"></i>
                <span>Payments</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/activities/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/activities/index.php">
                <i class="fas fa-calendar-check fa-fw"></i>
                <span>Activities</span>
            </a>
        </li>

        <!-- Meal Plan - Visible to Admin, Manager, and Cook -->
        <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'cook'])): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/meals/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/meals/index.php">
                <i class="fas fa-utensils fa-fw"></i>
                <span>Meal Plan</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/inquiries/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/inquiries/index.php">
                <i class="fas fa-envelope-open-text fa-fw"></i>
                <span>Inquiries</span>
            </a>
        </li>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/staff/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/staff/index.php">
                <i class="fas fa-user-nurse fa-fw"></i>
                <span>Staff Management</span>
            </a>
        </li>
        <li class="<?php echo strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/modules/reports/index.php">
                <i class="fas fa-chart-line fa-fw"></i>
                <span>Reports</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="<?php echo strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : ''; ?>">
            <a href="<?php echo APP_URL; ?>/settings.php">
                <i class="fas fa-cog fa-fw"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>
<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay" class="sidebar-overlay d-lg-none" onclick="closeSidebar()"></div>
