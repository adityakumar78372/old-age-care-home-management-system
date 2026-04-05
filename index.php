<?php
session_start();
require_once 'db_connect.php';

$message = '';
$msg_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_inquiry'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $service_required = trim($_POST['service_required']);
    $msg = trim($_POST['message']);
    
    if(!empty($name) && !empty($phone) && !empty($email) && !empty($msg)) {
        try {
            // Check if inquiries table exists
            $conn->query("SELECT 1 FROM inquiries LIMIT 1");
            
            $stmt = $conn->prepare("INSERT INTO inquiries (name, phone, email, service_required, message) VALUES (:name, :phone, :email, :service, :message)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':service', $service_required);
            $stmt->bindParam(':message', $msg);
            if ($stmt->execute()) {
                $message = "Thank you! Your inquiry has been submitted successfully.";
                $msg_type = "success";
            }
        } catch(PDOException $e) {
            // Table doesn't exist or DB error
            $message = "Your inquiry could not be saved right now. (Admin needs to run setup).";
            $msg_type = "danger";
        }
    } else {
        $message = "Please fill in all required fields.";
        $msg_type = "warning";
    }
}

// Fetch feedback for display
$feedbacks = [];
try {
    $f_stmt = $conn->query("SELECT * FROM feedback WHERE status = 'approved' ORDER BY id DESC LIMIT 6");
    if($f_stmt) {
        $feedbacks = $f_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // Ignore error if table doesn't exist yet
}

// If no feedback or table doesn't exist, set some dummies for preview
if (empty($feedbacks)) {
    $feedbacks = [
        ['name' => 'John Doe', 'rating' => 5, 'message' => 'The staff here is absolutely amazing. My father feels like he is at home!'],
        ['name' => 'Sarah Smith', 'rating' => 4, 'message' => 'Great facilities and very attentive to medical needs. Highly recommended.'],
        ['name' => 'Raj Patel', 'rating' => 5, 'message' => 'A wonderful environment filled with love and care. Outstanding management.']
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
    <meta name="description" content="Old Age Home Management System - Compassion and Dignity for Seniors">
    <title>OAHMS - Old Age Home Management</title>
    
    <!-- Speed: Resource Hinting -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://unpkg.com">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>
        .star-rating { color: #f59e0b; }
        .theme-toggle { cursor: pointer; font-size: 1.2rem; }

        /* =====================================================
           MEAL PLAN — ABSOLUTE FINAL FIX
           Using html prefix = highest specificity, beats Bootstrap
           ===================================================== */

        /* Wrapper card */
        html body .meal-table-wrapper {
            background-color: #eef2f7;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            overflow: hidden;
        }
        html[data-theme="dark"] body .meal-table-wrapper {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }

        /* Kill Bootstrap's table background completely */
        html body .meal-table,
        html body .meal-table > * > * > *,
        html body .meal-table td,
        html body .meal-table th {
            background-color: transparent !important;
            --bs-table-bg: transparent !important;
            --bs-table-color: #111827 !important;
            --bs-table-striped-bg: transparent !important;
        }

        /* Every data cell — LIGHT MODE = BLACK text */
        html body .meal-cell {
            color: #111827 !important;
            background-color: transparent !important;
            font-size: 0.9rem;
            padding: 13px 8px !important;
            border-color: #d1d5db !important;
            font-weight: 500;
        }

        /* Cook / Helper tag — LIGHT MODE = BLACK text */
        html body .meal-staff-tag {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1.5px solid #64748b;
            color: #111827 !important;
            background: rgba(0,0,0,0.06);
        }

        /* DARK MODE overrides */
        html[data-theme="dark"] body .meal-cell {
            color: #f1f5f9 !important;
            border-color: #334155 !important;
        }
        html[data-theme="dark"] body .meal-staff-tag {
            color: #f1f5f9 !important;
            border-color: #94a3b8;
            background: rgba(255,255,255,0.08);
        }

        /* Row hover */
        html body .meal-table tr:hover .meal-cell {
            background-color: rgba(0,0,0,0.03) !important;
        }
        html[data-theme="dark"] body .meal-table tr:hover .meal-cell {
            background-color: rgba(255,255,255,0.05) !important;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg glass-navbar fixed-top py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#">
            <i class="fas fa-home-heart text-primary"></i>
            OAHMS
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="fas fa-bars text-primary" style="font-size: 1.5rem;"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-3">
                <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="#facilities">Facilities</a></li>
                <li class="nav-item"><a class="nav-link" href="#meal-plan">Meal Plan</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                <li class="nav-item">
                    <i class="fas fa-moon theme-toggle nav-link" id="themeToggleBtn" title="Toggle Dark/Light Mode"></i>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-primary-custom" href="login.php">
                        <i class="fas fa-sign-in-alt me-2"></i> Staff Login
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-section" id="home">
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-8" data-aos="fade-up" data-aos-duration="1000">
                <div class="hero-badge">
                    <i class="fas fa-heart me-2 text-danger"></i> Welcome to Old Age Home
                </div>
                <h1 class="hero-title">Empowering the golden years with <span>dignity</span> and <span>care</span>.</h1>
                <p class="lead mb-5 text-light" style="opacity: 0.9; max-width: 600px;">
                    We provide a loving, safe, and comfortable environment for seniors to live out their retirement years peacefully, with full medical and emotional support.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="#services" class="btn btn-primary-custom btn-lg">View Services</a>
                    <a href="#inquiry" class="btn btn-outline-light btn-lg rounded-pill" style="backdrop-filter: blur(5px);">Make an Inquiry</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- About Us -->
<section class="py-5 my-5 position-relative" id="about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 position-relative" data-aos="fade-right">
                <!-- Decorative element behind image -->
                <div class="position-absolute rounded-circle" style="width: 300px; height: 300px; background: var(--primary); opacity: 0.08; top: -20px; left: -20px; z-index: 0;"></div>
                
                <!-- Main Image -->
                <img src="assets/images/elder_care_about.png" alt="Happy elderly people and caretaker" class="img-fluid rounded-4 shadow-lg position-relative" style="object-fit: cover; height: 500px; width: 100%; border: 6px solid var(--light-surface); z-index: 1;">
                
                <!-- Floating Experience Card -->
                <div class="position-absolute bottom-0 end-0 mb-4 me-n4 glass-card p-4 text-center" style="z-index: 2; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
                    <h2 class="fw-bold text-primary mb-0" style="font-size: 2.5rem;">15+</h2>
                    <p class="mb-0 fw-semibold text-muted">Years of Care</p>
                </div>
            </div>
            <div class="col-lg-6 ps-lg-5" data-aos="fade-left">
                <div class="hero-badge text-primary mb-3 shadow-sm border border-primary border-opacity-25 bg-primary bg-opacity-10">
                    <i class="fas fa-leaf text-success me-2"></i> About Our Home
                </div>
                <h2 class="section-title">A Community built on Compassion & Respect</h2>
                <p class="section-subtitle mb-5">Since our founding, we have been dedicated to enhancing the quality of life for older adults, providing a sanctuary of peace, health, and happiness.</p>
                
                <div class="d-flex mb-4 p-4 rounded-4 shadow-sm glass-card" style="border-left: 5px solid var(--primary); transition: transform 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateX(10px)'" onmouseout="this.style.transform='translateX(0)'">
                    <div class="me-4 text-primary"><i class="fas fa-bullseye fa-3x"></i></div>
                    <div>
                        <h5 class="fw-bold mb-2">Our Mission</h5>
                        <p class="text-muted mb-0">To provide exceptional and compassionate care, ensuring that every resident feels valued, respected, and truly at home like family.</p>
                    </div>
                </div>
                
                <div class="d-flex p-4 rounded-4 shadow-sm glass-card" style="border-left: 5px solid var(--secondary); transition: transform 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateX(10px)'" onmouseout="this.style.transform='translateX(0)'">
                    <div class="me-4 text-secondary"><i class="fas fa-eye fa-3x"></i></div>
                    <div>
                        <h5 class="fw-bold mb-2">Our Vision</h5>
                        <p class="text-muted mb-0">A world where every senior citizen lives a fulfilling, dignified, and joyous life, supported by a loving community and top-tier facilities.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="py-5" id="services">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Our Premium Services</h2>
            <p class="section-subtitle mx-auto" style="max-width: 600px;">We go above and beyond to ensure our residents receive the highest standard of care across all aspects of their daily lives.</p>
        </div>
        
        <div class="row g-4">
            <!-- Service 1 -->
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card h-100">
                    <div class="icon-box"><i class="fas fa-user-nurse"></i></div>
                    <h4 class="fw-bold mb-3">Resident Care</h4>
                    <p class="text-muted mb-0">24/7 dedicated personal care tailored to the individual needs of each resident, fostering independence and well-being.</p>
                </div>
            </div>
            <!-- Service 2 -->
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card h-100">
                    <div class="icon-box"><i class="fas fa-stethoscope"></i></div>
                    <h4 class="fw-bold mb-3">Medical Support</h4>
                    <p class="text-muted mb-0">On-site medical professionals, regular health checkups, and immediate emergency response systems.</p>
                </div>
            </div>
            <!-- Service 3 -->
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card h-100">
                    <div class="icon-box"><i class="fas fa-bed"></i></div>
                    <h4 class="fw-bold mb-3">Room Facility</h4>
                    <p class="text-muted mb-0">Spacious, fully-furnished, and climate-controlled rooms designed for absolute comfort and safety.</p>
                </div>
            </div>
            <!-- Service 4 -->
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card h-100">
                    <div class="icon-box"><i class="fas fa-utensils"></i></div>
                    <h4 class="fw-bold mb-3">Food & Nutrition</h4>
                    <p class="text-muted mb-0">Nutritionally balanced, customized meals prepared by expert chefs under the guidance of dietitians.</p>
                </div>
            </div>
            <!-- Service 5 -->
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card h-100">
                    <div class="icon-box" style="background: linear-gradient(135deg, #ef4444, #f87171);"><i class="fas fa-ambulance"></i></div>
                    <h4 class="fw-bold mb-3">Emergency Care</h4>
                    <p class="text-muted mb-0">Rapid response teams and ambulance services on standby to handle any critical situations instantly.</p>
                </div>
            </div>
             <!-- Service 6 -->
             <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card h-100">
                    <div class="icon-box" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="fas fa-leaf"></i></div>
                    <h4 class="fw-bold mb-3">Recreational Activities</h4>
                    <p class="text-muted mb-0">Daily yoga, meditation, community events, and games to keep the mind and body active.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Facilities -->
<section class="py-5 my-5" id="facilities">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 order-lg-2" data-aos="fade-left">
                <h2 class="section-title">World-Class Facilities</h2>
                <p class="section-subtitle">A modern oasis of comfort and safety.</p>
                
                <ul class="list-unstyled mb-0">
                    <li class="d-flex align-items-center mb-4 p-3 glass-card" style="padding: 15px !important;">
                        <i class="fas fa-door-open fa-2x text-primary me-3"></i>
                        <div>
                            <h5 class="fw-bold mb-1">Luxury Rooms</h5>
                            <small class="text-muted">A/C, Non-A/C, and Shared/Private options.</small>
                        </div>
                    </li>
                    <li class="d-flex align-items-center mb-4 p-3 glass-card" style="padding: 15px !important;">
                        <i class="fas fa-users-cog fa-2x text-primary me-3"></i>
                        <div>
                            <h5 class="fw-bold mb-1">Trained Staff</h5>
                            <small class="text-muted">Highly qualified caregivers available round the clock.</small>
                        </div>
                    </li>
                    <li class="d-flex align-items-center mb-4 p-3 glass-card" style="padding: 15px !important;">
                        <i class="fas fa-shield-alt fa-2x text-primary me-3"></i>
                        <div>
                            <h5 class="fw-bold mb-1">Tight Security</h5>
                            <small class="text-muted">24/7 CCTV surveillance and secured campus.</small>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="col-lg-7 order-lg-1" data-aos="fade-right">
                <div class="row g-3">
                    <div class="col-6">
                        <img src="https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&q=80&w=400" alt="Facility 1" class="img-fluid rounded-4 shadow mb-3" style="height: 250px; width:100%; object-fit: cover;">
                        <img src="https://images.unsplash.com/photo-1579208575657-c595a05383b7?auto=format&fit=crop&q=80&w=400" alt="Facility 2" class="img-fluid rounded-4 shadow" style="height: 200px; width:100%; object-fit: cover;">
                    </div>
                    <div class="col-6 mt-4">
                        <img src="https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&q=80&w=400" alt="Facility 3" class="img-fluid rounded-4 shadow" style="height: 470px; width:100%; object-fit: cover;">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Meal Plan -->
<section class="py-5" id="meal-plan">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Weekly Meal Plan</h2>
            <p class="section-subtitle mx-auto">Nutritious and delicious meals prepared daily for our residents under expert supervision.</p>
        </div>
        
        <div class="table-responsive meal-table-wrapper p-4" data-aos="fade-up" data-aos-delay="100">
            <table class="table table-hover align-middle text-center mb-0 meal-table">
                <thead>
                    <tr style="background-color: #f59e0b;">
                        <th class="py-3 px-2 border-0" style="color:#fff;">Day</th>
                        <th class="py-3 px-2 border-0" style="color:#fff;">Breakfast</th>
                        <th class="py-3 px-2 border-0" style="color:#fff;">Lunch</th>
                        <th class="py-3 px-2 border-0" style="color:#fff;">Evening Tea</th>
                        <th class="py-3 px-2 border-0" style="color:#fff;">Dinner</th>
                        <th class="py-3 px-2 border-0" style="color:#fff;">Cook</th>
                        <th class="py-3 px-2 border-0" style="color:#fff;">Helper</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $mstmt = $conn->query("SELECT * FROM meal_plan ORDER BY id ASC");
                        while($mrow = $mstmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr>
                        <td class="meal-cell fw-bold py-3"><?= htmlspecialchars($mrow['day']) ?></td>
                        <td class="meal-cell"><?= htmlspecialchars($mrow['breakfast']) ?></td>
                        <td class="meal-cell"><?= htmlspecialchars($mrow['lunch']) ?></td>
                        <td class="meal-cell"><?= htmlspecialchars($mrow['tea']) ?></td>
                        <td class="meal-cell"><?= htmlspecialchars($mrow['dinner']) ?></td>
                        <td class="meal-cell"><span class="meal-staff-tag"><?= htmlspecialchars($mrow['cook']) ?></span></td>
                        <td class="meal-cell"><span class="meal-staff-tag"><?= htmlspecialchars($mrow['helper']) ?></span></td>
                    </tr>
                    <?php endwhile; } catch(PDOException $e) { echo "<tr><td colspan='7' class='meal-cell text-center'>Meal plan unavailable.</td></tr>"; } ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-5" id="testimonials">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">What Families Say</h2>
            <p class="section-subtitle mx-auto">Feedback from our residents and their loved ones.</p>
        </div>
        
        <div class="row g-4">
            <?php foreach($feedbacks as $fb): ?>
            <div class="col-md-4" data-aos="zoom-in" data-aos-delay="100">
                <div class="glass-card text-center h-100 p-4">
                    <div class="mb-3 star-rating">
                        <?php 
                            $rating = (int)$fb['rating'];
                            for($i = 0; $i < 5; $i++) {
                                if($i < $rating) echo '<i class="fas fa-star"></i>';
                                else echo '<i class="far fa-star"></i>';
                            }
                        ?>
                    </div>
                    <p class="mb-4 text-muted fst-italic">"<?= htmlspecialchars($fb['message']) ?>"</p>
                    <h5 class="fw-bold mb-0">- <?= htmlspecialchars($fb['name']) ?></h5>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Inquiry / Contact Form -->
<section class="py-5 my-5" id="inquiry">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="glass-card shadow-lg p-md-5">
                    <div class="text-center mb-4">
                        <h2 class="section-title">Get in Touch</h2>
                        <p class="text-muted">Have questions? Send us an inquiry and we'll reply shortly.</p>
                    </div>

                    <?php if(!empty($message)): ?>
                        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="#inquiry" method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" name="name" class="form-control" required placeholder="John Doe">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" required placeholder="e.g. 1234567890" pattern="[0-9]{10}" maxlength="10" title="Please enter exactly 10 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email Address</label>
                                <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Service Required</label>
                                <select name="service_required" class="form-select" required>
                                    <option value="" selected disabled>Select a service</option>
                                    <option value="Resident Care">Resident Care</option>
                                    <option value="Medical Admission">Medical Admission</option>
                                    <option value="General Inquiry">General Inquiry</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Message</label>
                                <textarea name="message" class="form-control" rows="4" required placeholder="How can we help you?"></textarea>
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" name="submit_inquiry" class="btn btn-primary-custom btn-lg w-100">Send Inquiry <i class="fas fa-paper-plane ms-2"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer" id="contact">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <h5 class="text-white d-flex align-items-center gap-2 mb-4">
                    <i class="fas fa-home-heart text-primary"></i> OAHMS
                </h5>
                <p class="footer-text pe-md-4">Providing love, care, and dignity to the elderly. A home away from home where every resident is family.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="social-icon fs-4"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="social-icon fs-4"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#" class="social-icon fs-4"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <h5 class="text-white mb-4">Quick Links</h5>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="#about" class="footer-link"><i class="fas fa-chevron-right me-2 text-primary" style="font-size:0.8rem;"></i> About Us</a></li>
                    <li><a href="#services" class="footer-link"><i class="fas fa-chevron-right me-2 text-primary" style="font-size:0.8rem;"></i> Services</a></li>
                    <li><a href="#facilities" class="footer-link"><i class="fas fa-chevron-right me-2 text-primary" style="font-size:0.8rem;"></i> Facilities</a></li>
                    <li><a href="login.php" class="footer-link"><i class="fas fa-chevron-right me-2 text-primary" style="font-size:0.8rem;"></i> Staff Portal</a></li>
                </ul>
            </div>
            
            <div class="col-lg-4 col-md-12">
                <h5 class="text-white mb-4">Contact Us</h5>
                <ul class="list-unstyled d-flex flex-column gap-3">
                    <li class="d-flex footer-text">
                        <i class="fas fa-map-marker-alt mt-1 me-3 text-primary"></i>
                        <span> AWAS VIKAS COLONY,CHHIBRAMAU,KANNAUJ,UP</span>
                    </li>
                    <li class="d-flex footer-text">
                        <i class="fas fa-phone mt-1 me-3 text-primary"></i>
                        <span>9696676903</span>
                    </li>
                    <li class="d-flex footer-text">
                        <i class="fas fa-envelope mt-1 me-3 text-primary"></i>
                        <span>adityakumar78372@gmail.com</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="row g-4 mt-4 pt-4 border-top border-secondary border-opacity-25">
            <div class="col-md-4">
                <h6 class="text-primary fw-bold mb-3 text-uppercase small">Academic Project</h6>
                <p class="footer-text mb-1" style="font-size: 0.9rem;">Title: Old Age Home Management System</p>
                <p class="footer-text mb-1" style="font-size: 0.85rem;">Guide: Mr. Mahaboob Hussain</p>
                <p class="footer-text small" style="font-size: 0.8rem; line-height: 1.4;">Objective: To develop a modern web-based system that streamlines resident care, health records, and financial tracking.</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-primary fw-bold mb-3 text-uppercase small">Developer Details</h6>
                <p class="footer-text mb-1" style="font-size: 0.9rem;">Name: Aditya Kumar</p>
                <p class="footer-text mb-1" style="font-size: 0.85rem;">Enrollment: AZ149050051</p>
                <p class="footer-text small" style="font-size: 0.8rem;">Center: Subhash Academy (9050)</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-primary fw-bold mb-3 text-uppercase small">Technical Stack</h6>
                <p class="footer-text mb-1" style="font-size: 0.85rem;"><span class="fw-bold">Frontend:</span> HTML, CSS, JS</p>
                <p class="footer-text mb-1" style="font-size: 0.85rem;"><span class="fw-bold">Backend:</span> PHP, MySQL Server</p>
                <p class="footer-text small" style="font-size: 0.85rem;"><span class="fw-bold">Environment:</span> XAMPP (Apache)</p>
            </div>
        </div>
        
        <div class="border-top border-secondary pt-4 text-center mt-4 border-opacity-10">
            <p class="mb-0 small text-muted">&copy; <?= date('Y') ?> Old Age Home Management System. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    // Initialize AOS
    AOS.init({
        once: true,
        duration: 800,
        offset: 50
    });

    // Theme Toggle Logic
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const htmlEl = document.documentElement;

    // Check saved theme or system preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        htmlEl.setAttribute('data-theme', 'dark');
        updateToggleIcon('dark');
    }

    themeToggleBtn.addEventListener('click', () => {
        const currentTheme = htmlEl.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        htmlEl.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateToggleIcon(newTheme);
    });

    function updateToggleIcon(theme) {
        if(theme === 'dark') {
            themeToggleBtn.classList.remove('fa-moon');
            themeToggleBtn.classList.add('fa-sun');
            themeToggleBtn.classList.add('text-warning');
        } else {
            themeToggleBtn.classList.remove('fa-sun');
            themeToggleBtn.classList.add('fa-moon');
            themeToggleBtn.classList.remove('text-warning');
        }
    }

    // Navbar blur on scroll
    window.addEventListener('scroll', () => {
        const navbar = document.querySelector('.glass-navbar');
        if (window.scrollY > 50) {
            navbar.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.boxShadow = 'none';
        }
    });
</script>

</body>
</html>
