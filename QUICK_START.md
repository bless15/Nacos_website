# ============================================
# NACOS DASHBOARD - QUICK SETUP GUIDE
# ============================================

## 🚀 5-Minute Setup

### 1️⃣ Start XAMPP
- Open XAMPP Control Panel
- Start Apache ✅
- Start MySQL ✅

### 2️⃣ Create Database
Open browser → http://localhost/phpmyadmin
- Click "New"
- Database name: nacos_dashboard
- Collation: utf8mb4_unicode_ci
- Click "Create"

### 3️⃣ Import Files
Still in phpMyAdmin:

**Import Schema:**
- Select nacos_dashboard
- Click "Import" tab
- Choose: C:\xampp\htdocs\nacos\database\schema.sql
- Click "Go"
- ✅ Should see: "10 tables created"

**Import Data:**
- Click "Import" tab again
- Choose: C:\xampp\htdocs\nacos\database\seed_data.sql
- Click "Go"
- ✅ Should see: "Data inserted"

### 4️⃣ Access Application
- Public Site: http://localhost/nacos/public/
- Admin Login: http://localhost/nacos/admin/login.php

### 5️⃣ Test Login
Username: super_admin
Password: Admin@2025

## ✅ Done! You're ready to develop.

---

## 📊 What's Included

### Database Tables (10)
✅ ADMINISTRATORS - Admin accounts
✅ MEMBERS - 20 sample students
✅ PROJECTS - 7 innovation projects
✅ EVENTS - 7 workshops/events
✅ RESOURCES - 6 learning materials
✅ PARTNERS - 5 sponsors/mentors
✅ DOCUMENTS - 5 admin files
✅ MEMBER_EVENTS - Attendance tracking
✅ MEMBER_PROJECTS - Contribution tracking

### Sample Data
- 4 admin accounts
- 20 student members (all levels)
- 7 projects with GitHub links
- 7 events (completed & upcoming)
- Real-world partnership data

---

## 🔍 Verify Setup

### Check Database
In phpMyAdmin:
```sql
USE nacos_dashboard;
SHOW TABLES;              -- Should show 10 tables
SELECT COUNT(*) FROM MEMBERS;  -- Should return 20
```

### Check Connection
Create test file: C:\xampp\htdocs\nacos\test.php
```php
<?php
define('NACOS_ACCESS', true);
require_once 'config/database.php';

try {
    $db = getDB();
    $members = $db->fetchOne("SELECT COUNT(*) as total FROM MEMBERS");
    echo "✅ Database connected! Total members: " . $members['total'];
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
```

Visit: http://localhost/nacos/test.php

---

## 🆘 Troubleshooting

### "Table doesn't exist"
→ Re-import schema.sql

### "Access denied for user"
→ Check config/database.php credentials
→ Default XAMPP: user='root', password=''

### "404 Not Found"
→ Verify file location: C:\xampp\htdocs\nacos\
→ Check Apache is running in XAMPP

### "Database connection error"
→ Ensure MySQL is running
→ Verify database name: nacos_dashboard

---

## 📞 Need Help?

See full README.md for:
- Detailed architecture
- Security guidelines
- Development roadmap
- Contribution guide

---

**Happy Coding! 🚀**
