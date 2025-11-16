# 🎓 NACOS DASHBOARD

**Comprehensive Student Management & Engagement Platform**  
*Rebuilding NACOS, Empowering Innovators*

---

## 📋 Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Installation Guide](#installation-guide)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Project Structure](#project-structure)
- [Default Credentials](#default-credentials)
- [Security Notes](#security-notes)
- [Roadmap](#roadmap)

---

## 🎯 Overview

The NACOS Dashboard is a dual-interface web application designed to:
- **Empower Students**: Track achievements, discover projects, and access resources
- **Support Administrators**: Manage members, events, projects, and documentation
- **Enable Partnerships**: Showcase value through accurate data and verified innovations
- **Ensure Continuity**: Maintain institutional knowledge through secure documentation

**Manifesto Pillars:**
- ✅ Accurate Headcount
- ✅ Documentation & Continuity
- ✅ Member Empowerment
- ✅ Innovation Showcase
- ✅ Strategic Partnerships

---

## ✨ Features

### Public-Facing Dashboard
1. **Home Page** - Live metrics, vision statement, call-to-action
2. **Innovation Portfolio** - Filterable showcase of student projects
3. **Resource & Events Hub** - Calendar, workshops, learning materials
4. **Member Impact Scorecard** - Personal achievement tracking (authenticated)
5. **Partner & Sponsor Portal** - Value proposition for external stakeholders

### Administrator Backend
1. **Secure Login System** - Role-based access control
2. **Admin Control Panel** - Organizational health metrics
3. **Member & Headcount Manager** - CRUD operations for student database
4. **Project & Portfolio Manager** - Innovation content management
5. **Documentation Hub** - Secure file repository for institutional knowledge

---

## 🛠️ Technology Stack

| Layer | Technologies |
|-------|-------------|
| **Frontend** | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5 |
| **Backend** | PHP 8.x |
| **Database** | MySQL 8.x with InnoDB engine |
| **Security** | PDO prepared statements, password hashing, session management |
| **Server** | Apache 2.4 (XAMPP compatible) |

---

## 📦 Installation Guide

### Prerequisites
- **XAMPP** (includes Apache, MySQL, PHP)
- **Web Browser** (Chrome, Firefox, Edge)
- **Text Editor** (VS Code recommended)

### Step 1: Clone or Download
```bash
# Option A: Clone from repository
git clone https://github.com/your-repo/nacos-dashboard.git

# Option B: Extract ZIP to XAMPP htdocs
# Extract to: C:\xampp\htdocs\nacos
```

### Step 2: Start XAMPP Services
1. Open **XAMPP Control Panel**
2. Start **Apache**
3. Start **MySQL**

### Step 3: Access phpMyAdmin
- Open browser and navigate to: `http://localhost/phpmyadmin`

---

## 🗄️ Database Setup

### Method 1: Using phpMyAdmin (Recommended)

#### Step 1: Create Database
1. In phpMyAdmin, click **"New"** in the left sidebar
2. Database name: `nacos_dashboard`
3. Collation: `utf8mb4_unicode_ci`
4. Click **"Create"**

#### Step 2: Import Schema
1. Select the `nacos_dashboard` database
2. Click the **"Import"** tab
3. Click **"Choose File"**
4. Navigate to: `C:\xampp\htdocs\nacos\database\schema.sql`
5. Click **"Go"** at the bottom
6. ✅ Success message: "10 tables created"

#### Step 3: Import Seed Data
1. Click the **"Import"** tab again
2. Choose file: `C:\xampp\htdocs\nacos\database\seed_data.sql`
3. Click **"Go"**
4. ✅ Success message: "Data inserted successfully"

### Method 2: Using MySQL Command Line
```bash
# Navigate to MySQL bin directory
cd C:\xampp\mysql\bin

# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE nacos_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Use the database
USE nacos_dashboard;

# Import schema
SOURCE C:/xampp/htdocs/nacos/database/schema.sql;

# Import seed data
SOURCE C:/xampp/htdocs/nacos/database/seed_data.sql;

# Verify
SHOW TABLES;
```

---

## ⚙️ Configuration

### Database Configuration
Edit `config/database.php` if your MySQL setup differs:

```php
// Default XAMPP settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'nacos_dashboard');
define('DB_USER', 'root');
define('DB_PASS', ''); // Empty for default XAMPP

// Change for production
define('ENVIRONMENT', 'development'); // Change to 'production' when live
```

### Security Configuration
1. **Change default passwords** in production
2. **Set strong admin passwords** (see Default Credentials below)
3. **Update environment** to `'production'` before deployment
4. **Secure file permissions** on `logs/` and `uploads/` directories

---

## 📁 Project Structure

```
nacos/
├── admin/                  # Administrator backend pages
│   ├── index.php          # Admin control panel
│   ├── login.php          # Secure login page
│   ├── members.php        # Member management
│   ├── projects.php       # Project management
│   └── documents.php      # Document repository
│
├── assets/                 # Static assets (CSS, JS, images)
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/                 # Configuration files
│   └── database.php       # Database connection (PDO singleton)
│
├── database/               # SQL files
│   ├── schema.sql         # Database structure (10 tables)
│   └── seed_data.sql      # Sample data for testing
│
├── includes/               # Reusable PHP components
│   ├── auth.php           # Authentication helpers
│   ├── confirm_modal.php  # Shared confirmation modal (Bootstrap)
│   ├── public_footer.php  # Public-facing footer & JS includes
│   ├── public_navbar.php  # Public navbar include
│   └── footer.php         # Common footer (admin)
│
├── logs/                   # Error logs (protected by .htaccess)
│   └── .htaccess          # Deny direct access
│
├── public/                 # Public-facing pages
│   ├── index.php          # Home page
│   ├── portfolio.php      # Innovation portfolio
│   ├── events.php         # Resource & events hub
│   ├── scorecard.php      # Member impact scorecard
│   └── partners.php       # Partner portal
│
├── uploads/                # User uploaded files (protected)
│   └── .htaccess          # Deny PHP execution
│
├── .htaccess               # Apache rewrite rules (optional)
└── README.md               # This file
```

---

## 🔑 Default Credentials

### Administrator Accounts
**⚠️ IMPORTANT: Change these passwords immediately in production!**

| Username | Password | Role | Purpose |
|----------|----------|------|---------|
| `super_admin` | `Admin@2025` | Super Admin | Full system access |
| `admin_tech` | `Admin@2025` | Admin | Technical management |
| `admin_events` | `Admin@2025` | Admin | Events & resources |
| `moderator_1` | `Admin@2025` | Moderator | Limited access |

### Sample Member Accounts
- **Matric Numbers**: Check `seed_data.sql` for 20 sample students
- **Example**: `CSC/2024/001` (Ibrahim Musa)

---

## 🔐 Security Notes

### Built-in Security Features
✅ **Password Hashing**: All passwords use `password_hash()` with bcrypt  
✅ **SQL Injection Prevention**: PDO prepared statements throughout  
✅ **Session Management**: Secure session handling for authentication  
✅ **File Upload Protection**: `.htaccess` prevents PHP execution in uploads  
✅ **Direct Access Prevention**: `NACOS_ACCESS` constant gates protected files  
✅ **Error Handling**: Environment-based error display (dev vs production)  

### Security Checklist Before Going Live
- [ ] Change all default passwords
- [ ] Set `ENVIRONMENT` to `'production'` in `config/database.php`
- [ ] Use HTTPS (SSL certificate)
- [ ] Set strong MySQL root password
- [ ] Restrict database user permissions
- [ ] Enable PHP `open_basedir` restriction
- [ ] Regular backups of database
- [ ] Keep PHP and MySQL updated

---

## 🚀 Getting Started

### 1. Access the Application
```
Public Homepage:    http://localhost/nacos/public/
Admin Login:        http://localhost/nacos/admin/login.php
```

### 2. Test the System
1. **Browse public pages** without logging in
2. **Login as admin** using credentials above
3. **Explore the dashboard** and sample data
4. **Add test members** to verify CRUD operations
5. **Register for events** to test attendance tracking

### 3. Customize Content
- Update mission statement and branding
- Replace sample projects with real ones
- Add upcoming events and workshops
- Upload partner logos and information

---

## 🗺️ Roadmap

### Phase 1: Foundation ✅ (COMPLETED)
- [x] Database schema with 10 tables
- [x] Secure PDO connection layer
- [x] Sample seed data for testing
- [x] Project structure and security basics

### Phase 2: Authentication & Admin Core (NEXT)
- [ ] Secure login system with session management
- [ ] Admin control panel with live metrics
- [ ] Member CRUD operations
- [ ] Role-based access control

### Phase 3: Public-Facing MVP
- [ ] Home page with live statistics
- [ ] Innovation portfolio with filtering
- [ ] Events calendar and resource hub
- [ ] Responsive design implementation

### Phase 4: Advanced Features
- [ ] Member Impact Scorecard (authenticated)
- [ ] Partner portal with data visualization
- [ ] Document management system
- [ ] Search functionality across modules

### Phase 5: Polish & Deploy
- [ ] Performance optimization
- [ ] Security hardening
- [ ] User acceptance testing
- [ ] Production deployment

---

## 📞 Support & Contribution

### Documentation
- **Architecture**: See top-level project brief
- **Database Schema**: `database/schema.sql` (commented)
- **Code Comments**: Inline documentation throughout

### Contributing
1. Fork the repository
2. Create feature branch: `git checkout -b feature-name`
3. Commit changes: `git commit -m "Add feature"`
4. Push to branch: `git push origin feature-name`
5. Submit pull request

### Issues & Questions
- Report bugs via GitHub Issues
- Contact NACOS tech team: tech@nacos.edu.ng

---

## 📄 License

This project is licensed under the MIT License - see LICENSE file for details.

---

## 🎓 Credits

**Developed by NACOS Tech Team**  
*Nigerian Association of Computing Students*  
*Building the future, one line of code at a time.*

---

**🚀 Ready to build? Let's rebuild NACOS together!**
