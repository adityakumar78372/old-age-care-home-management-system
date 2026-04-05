<?php
session_start();
require_once 'db_connect.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = :username LIMIT 1");
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $user['password'])) {
                    // Regenerate session ID to prevent session fixation
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];

                    header("Location: " . APP_URL . "/dashboard.php");
                    exit;
                } else {
                    // Use generic message to avoid username enumeration
                    $error = "Invalid username or password.";
                }
            } else {
                // Check if system has no users at all, then initialize admin
                $checkCount = (int)$conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
                if ($checkCount === 0 && $username === 'admin' && $password === 'admin123') {
                    $hash = password_hash('admin123', PASSWORD_BCRYPT);
                    $stmt2 = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')");
                    $stmt2->execute(['admin', $hash]);
                    $error = "Admin account initialized. Please sign in again.";
                } else {
                    $error = "Invalid username or password.";
                }
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "A system error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Old Age Home Management System</title>
    <meta name="description" content="Sign in to the OAHMS management portal.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Apply saved theme before render to prevent flash
        (function() {
            const t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body class="login-page overflow-hidden position-relative">

<!-- Animated Blobs Background -->
<div class="blob-wrapper">
    <div class="blob shape-1"></div>
    <div class="blob shape-2"></div>
    <div class="blob shape-3"></div>
</div>

<div class="login-card glass-effect position-relative" style="z-index: 2;">
    <div class="mb-4">
        <i class="fas fa-house-heart fa-3x text-primary-custom mb-2"></i>
        <h2>OAHMS</h2>
        <p class="text-secondary mb-0">Sign in to manage the home</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 text-start" role="alert">
            <i class="fas fa-circle-exclamation me-2"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, 'UTF-8'); ?>" method="post" autocomplete="on">
        <div class="mb-3 text-start">
            <label for="username" class="form-label text-secondary">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user text-secondary"></i></span>
                <input type="text" id="username" name="username" class="form-control" required autocomplete="username"
                       value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="mb-4 text-start">
            <label for="password" class="form-label text-secondary">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock text-secondary"></i></span>
                <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                <button class="btn btn-outline-secondary" type="button" id="togglePass" aria-label="Toggle password visibility">
                    <i class="fas fa-eye" id="passIcon"></i>
                </button>
            </div>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary-custom btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </div>
        <div class="text-center mt-4">
            <a href="index.php" class="text-decoration-none text-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Home Page
            </a>
        </div>
    </form>
</div>

<script>
// Password visibility toggle
document.getElementById('togglePass')?.addEventListener('click', function() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('passIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fas fa-eye';
    }
});
</script>
</body>
</html>
