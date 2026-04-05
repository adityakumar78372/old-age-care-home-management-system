<?php
require_once __DIR__ . '/config.php';

try {
    // Connect to MySQL server first
    $conn = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $dbname = DB_NAME;
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbname`";
    $conn->exec($sql);
    echo "Database created successfully\n";
    
    // Connect to the specific database
    $conn->exec("USE `$dbname`");
    
    // Create Users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff', 'manager', 'doctor', 'nurse', 'cook') NOT NULL DEFAULT 'staff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insert default admin user (password: admin123) using prepared statement
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, role) VALUES ('admin', ?, 'admin')");
    $stmt->execute([$password_hash]);
    
    // Create Rooms table
    $conn->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_number VARCHAR(20) NOT NULL UNIQUE,
        capacity INT NOT NULL DEFAULT 1,
        status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS residents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        dob DATE NOT NULL,
        gender ENUM('Male', 'Female', 'Other') NOT NULL,
        resident_type ENUM('free', 'paid') DEFAULT 'paid',
        approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
        reason_for_free TEXT,
        contact VARCHAR(20),
        emergency_contact VARCHAR(20) NOT NULL,
        admit_date DATE NOT NULL,
        room_id INT,
        plan ENUM('basic', 'standard', 'premium'),
        monthly_fee DECIMAL(10,2) DEFAULT 0,
        billing_start_date DATE NULL,
        next_due_date DATE NULL,
        status ENUM('active', 'inactive', 'deceased', 'discharged') DEFAULT 'active',
        medical_history TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
    )");
    
    // Create Staff table
    $conn->exec("CREATE TABLE IF NOT EXISTS staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        user_id INT NULL,
        role VARCHAR(50) NOT NULL,
        contact VARCHAR(20) NOT NULL,
        shift ENUM('Morning', 'Evening', 'Night') NOT NULL,
        joining_date DATE NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Create Health Records table
    $conn->exec("CREATE TABLE IF NOT EXISTS health_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resident_id INT NOT NULL,
        checkup_date DATE NOT NULL,
        temp VARCHAR(10),
        blood_pressure VARCHAR(20),
        medicines TEXT,
        doctor_visit_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    )");
    
    // Create Payments table
    $conn->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resident_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        month VARCHAR(20) NULL,
        year INT NULL,
        status ENUM('paid', 'unpaid', 'pending') DEFAULT 'unpaid',
        payment_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    )");
    
    // Create Activities table
    $conn->exec("CREATE TABLE IF NOT EXISTS activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        activity_date DATE NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create Activity Participants table
    $conn->exec("CREATE TABLE IF NOT EXISTS activity_participants (
        activity_id INT NOT NULL,
        resident_id INT NOT NULL,
        PRIMARY KEY (activity_id, resident_id),
        FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    )");

    // Create Inquiries table
    $conn->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(100) NOT NULL,
        service_required VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('unread', 'read') DEFAULT 'unread',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create Feedback table
    $conn->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
        message TEXT NOT NULL,
        status ENUM('pending', 'approved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create Meal Plan table
    $conn->exec("CREATE TABLE IF NOT EXISTS meal_plan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day VARCHAR(20) NOT NULL UNIQUE,
        breakfast VARCHAR(255),
        lunch VARCHAR(255),
        tea VARCHAR(255),
        dinner VARCHAR(255),
        cook VARCHAR(100),
        helper VARCHAR(100),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create Monthly Ledger table
    $conn->exec("CREATE TABLE IF NOT EXISTS monthly_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resident_id INT NOT NULL,
        year INT NOT NULL,
        month VARCHAR(20) NOT NULL,
        total_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        due_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('Paid', 'Partial', 'Pending', 'Advance') DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY(resident_id, year, month),
        FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
    )");

    echo "Tables created successfully.\n";

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
