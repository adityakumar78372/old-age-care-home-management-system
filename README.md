<div align="center">

# 🏠 Old Age Care Home Management System

### A comprehensive web-based management system for old age care homes

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=apache&logoColor=white)](https://apachefriends.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

</div>

---

## 📋 Table of Contents

- [About the Project](#-about-the-project)
- [Features](#-features)
- [Screenshots](#-screenshots)
- [Tech Stack](#-tech-stack)
- [Getting Started](#-getting-started)
- [Project Structure](#-project-structure)
- [Modules](#-modules)
- [Default Credentials](#-default-credentials)
- [Contributing](#-contributing)

---

## 🌟 About the Project

The **Old Age Care Home Management System (OAHMS)** is a full-featured web application designed to digitize and streamline the operations of an old age care home. It helps administrators and staff efficiently manage residents, rooms, health records, payments, meals, activities, and more — all from a single, easy-to-use dashboard.

> 💡 Built as an academic project to demonstrate real-world web application development using PHP & MySQL.

---

## ✨ Features

### 👤 Resident Management
- Complete resident registration with photo upload
- Multi-step admission approval workflow
- View, edit, and delete resident profiles
- Track resident status (Active / Pending / Discharged)

### 🏥 Health Monitoring
- Record and track health checkup details
- Export health reports to PDF/Excel
- Medical history per resident

### 🏠 Room Management
- Add and manage rooms with capacity tracking
- Real-time room availability status
- Assign and deallocate rooms to residents

### 💰 Payment & Billing
- Generate monthly billing automatically
- Track paid/unpaid dues
- Printable payment receipts
- Financial reports and summaries

### 🍽️ Meal Management
- Schedule and manage meal plans
- Track daily meal service

### 🏃 Activities Management
- Plan and record recreational activities
- Assign activities to residents

### 👥 Staff Management
- Add/manage staff members
- Role-based access control (Admin / Staff)

### 📊 Reports & Analytics
- Dashboard with live statistics
- Generate financial & occupancy reports
- Export data to PDF/Excel

### 🔔 Inquiry Management
- Handle admission inquiries from families
- Track inquiry status

### 🔐 Security
- CSRF protection on all forms
- Session-based authentication
- Role-based access control
- Password hashing with bcrypt
- Input sanitization & validation

---

## 📸 Screenshots

> _Screenshots coming soon..._

---

## 🛠️ Tech Stack

| Technology | Purpose |
|------------|---------|
| **PHP 8.0+** | Backend / Server-side logic |
| **MySQL 8.0+** | Database |
| **HTML5 / CSS3** | Frontend Structure & Styling |
| **JavaScript (Vanilla)** | Client-side interactivity |
| **Bootstrap 5** | Responsive UI components |
| **ApexCharts** | Dashboard data visualization |
| **Font Awesome** | Icons |
| **XAMPP** | Local development server |

---

## 🚀 Getting Started

### Prerequisites

Make sure you have the following installed:
- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP + MySQL stack)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- A modern web browser

---

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/adityakumar78372/old-age-care-home-management-system.git
```

**2. Move to your server's root directory**
```bash
# For XAMPP (Windows)
mv old-age-care-home-management-system C:/xampp/htdocs/oahms

# For XAMPP (Linux/Mac)
mv old-age-care-home-management-system /opt/lampp/htdocs/oahms
```

**3. Import the Database**
- Open [phpMyAdmin](http://localhost/phpmyadmin)
- Create a new database named `oahmsdb`
- Click **Import** and select the `.sql` file from the `/database/` folder

**4. Configure the Database Connection**

Edit `config.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // your MySQL username
define('DB_PASS', '');            // your MySQL password
define('DB_NAME', 'oahmsdb');
```

**5. Start XAMPP**
- Start **Apache** and **MySQL** from XAMPP Control Panel

**6. Run Setup (First Time Only)**

Visit the setup page to initialize the database:
```
http://localhost/oahms/setup.php
```

**7. Access the Application**
```
http://localhost/oahms/
```

---

## 📁 Project Structure

```
old-age-care-home-management-system/
│
├── 📄 index.php              # Landing page
├── 📄 login.php              # Login page
├── 📄 logout.php             # Logout handler
├── 📄 dashboard.php          # Admin dashboard
├── 📄 config.php             # App configuration
├── 📄 db_connect.php         # Database connection
├── 📄 setup.php              # First-time setup
├── 📄 settings.php           # System settings
│
├── 📁 assets/
│   ├── 📁 css/               # Stylesheets
│   ├── 📁 js/                # JavaScript files
│   └── 📁 images/            # Static images
│
├── 📁 includes/
│   ├── header.php            # Common header
│   ├── footer.php            # Common footer
│   ├── sidebar.php           # Navigation sidebar
│   └── helpers.php           # Helper functions (CSRF, formatting etc.)
│
└── 📁 modules/
    ├── 📁 residents/         # Resident management
    ├── 📁 rooms/             # Room management
    ├── 📁 staff/             # Staff management
    ├── 📁 health/            # Health records
    ├── 📁 payments/          # Billing & payments
    ├── 📁 meals/             # Meal management
    ├── 📁 activities/        # Activity management
    ├── 📁 inquiries/         # Inquiry management
    ├── 📁 reports/           # Reports & analytics
    └── 📁 admin/             # Admin controls & approvals
```

---

## 📦 Modules

| Module | Description |
|--------|-------------|
| `residents/` | Add, view, edit, delete residents; approval workflow |
| `rooms/` | Manage room inventory and assignments |
| `staff/` | Staff profiles and role management |
| `health/` | Health records, checkups, export |
| `payments/` | Billing, receipts, financial tracking |
| `meals/` | Meal plans and daily scheduling |
| `activities/` | Recreational activity planning |
| `inquiries/` | Admission inquiry handling |
| `reports/` | Financial and occupancy reports |
| `admin/` | Resident approvals, database backup |

---

## 🔑 Default Credentials

After running `setup.php`, use these to log in:

| Role | Username | Password |
|------|----------|----------|
| **Admin** | `admin` | `admin123` |
| **Staff** | `staff` | `staff123` |

> ⚠️ **Important:** Change default passwords immediately after first login!

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License.

---

<div align="center">

Made with ❤️ by **Aditya Kumar**

⭐ **Star this repo if you found it helpful!** ⭐

</div>
