<?php
// ob_start() is handled in db_connect.php
require_once dirname(__DIR__) . '/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

// Function to get active state for sidebar
function isActive($page) {
    $current_file = basename($_SERVER['PHP_SELF']);
    return ($current_file == $page) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' — OAHMS' : 'OAHMS — Dashboard'; ?></title>
    <meta name="description" content="Old Age Home Management System - <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?>">
    
    <!-- Speed: Preconnect to CDNs & Resource Hinting -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        body.bolder-text, body.bolder-text * { font-weight: 600 !important; }
        body.bolder-text h1, body.bolder-text h2, body.bolder-text h3,
        body.bolder-text h4, body.bolder-text h5, body.bolder-text h6 { font-weight: 800 !important; }
    </style>
    <script>
        (function() {
            // Apply saved theme ASAP to prevent flash
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            // Apply Font Size scaling
            const size = localStorage.getItem('oahms_fontSize');
            if (size && size !== '100') {
                document.documentElement.style.fontSize = (parseInt(size) / 100 * 16) + 'px';
            }
            // Apply Bold Text
            const isBold = localStorage.getItem('oahms_fontWeight');
            if (isBold === 'bold') {
                document.documentElement.classList.add('bolder-text');
                document.addEventListener('DOMContentLoaded', () => {
                    document.body.classList.add('bolder-text');
                });
            }
        })();
    </script>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Page Content Area -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <button id="sidebarCollapse" class="btn btn-outline-secondary flex-shrink-0" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="m-0 d-none d-md-block fw-bold text-primary-custom text-truncate" style="max-width: 340px;">
                        <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?>
                    </h4>
                </div>

                <div class="nav-right">
                    <button id="theme-toggle" class="btn btn-light rounded-circle border" aria-label="Toggle dark mode">
                        <i id="theme-icon" class="fas fa-moon"></i>
                    </button>

                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle user-menu pb-0 text-primary-custom" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=4f46e5&color=fff" alt="User avatar" width="32" height="32" class="rounded-circle me-2" loading="lazy">
                            <strong><?php echo htmlspecialchars(ucfirst($_SESSION['username'])); ?></strong>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                            <li><span class="dropdown-item-text small text-muted">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign out</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Main Content Container -->
            <div class="container-fluid p-4">
