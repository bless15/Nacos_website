# 🧪 NACOS Dashboard - Testing Guide

## 🚀 Quick Start Testing

### 1. **Start XAMPP Services**
Make sure these are running:
- ✅ Apache (Web Server)
- ✅ MySQL (Database)

---

## 🔐 Test Accounts

### Admin Account
```
URL: http://localhost/nacos/public/login.php
Matric No: CSC/2024/001
Name: Ibrahim Musa
Role: Admin
Password: [Use the password he registered with]
```

### Regular Member Accounts
```
Matric No: CSC/2024/002
Name: Blessing Okonkwo
Role: Member

Matric No: CSC/2024/003
Name: Daniel Okoro
Role: Member

(Use their registration passwords)
```

---

## ✅ Testing Checklist

### Test 1: Admin Login & Access ⭐
1. Go to: `http://localhost/nacos/public/login.php`
2. Login as **CSC/2024/001**
3. **Expected**: Redirect to `/admin/index.php`
4. **Expected**: See admin dashboard with all menu items
5. Try accessing: Members, Events, Projects, Documents, etc.
6. **Expected**: All admin pages load successfully

### Test 2: Regular Member Login ⭐⭐⭐
1. Logout (click Logout button)
2. Login as **CSC/2024/002** (regular member)
3. **Expected**: Redirect to `/public/dashboard.php`
4. Try accessing: `http://localhost/nacos/admin/members.php`
5. **Expected**: BLOCKED with "Access denied" message

### Test 3: Role Management ⭐⭐⭐
1. Login as admin (CSC/2024/001)
2. Go to **Members** → Click **Blessing Okonkwo**
3. Look for role badge (should show "Member" in blue)
4. Click **"Manage Role"** button
5. Change role to **"Admin"**
6. Click **"Update Role"**
7. **Expected**: Success message
8. Logout and login as **CSC/2024/002**
9. **Expected**: Now redirects to admin panel!

### Test 4: Self-Demotion Prevention ⭐
1. Login as admin
2. Go to Members → Click your own name
3. Click "Manage Role"
4. Try to change your role to "Member"
5. **Expected**: Error message "Cannot remove your own admin privileges"

### Test 5: Member Registration ⭐
1. Logout
2. Go to: `http://localhost/nacos/public/register.php`
3. Register a new member:
   - Full Name: Test User
   - Matric No: CSC/2024/999
   - Email: test@example.com
   - Phone: 08012345678
   - Level: 100
   - Password: test123
4. **Expected**: Success message about pending approval
5. Try to login with new account
6. **Expected**: Error "Account pending approval"

### Test 6: Member Approval ⭐⭐
1. Login as admin
2. Go to **Members**
3. Look for unapproved members (yellow badge)
4. Click on pending member
5. Click **"Approve"** button
6. **Expected**: Email sent + member approved
7. Logout and login as the approved member
8. **Expected**: Login successful!

### Test 7: Event Management
1. Login as admin
2. Go to **Events** → **Add New Event**
3. Create a test event
4. **Expected**: Event created successfully
5. Try viewing event details
6. Try editing event
7. Try deleting event

### Test 8: Document Upload
1. Login as admin
2. Go to **Documents** → **Upload Document**
3. Upload a test file (PDF, DOC, etc.)
4. **Expected**: File uploaded successfully
5. Check if file appears in documents list

### Test 9: Logout
1. Click **Logout** button
2. **Expected**: Redirect to login page with success message
3. Try accessing admin page directly
4. **Expected**: Redirected to login

### Test 10: Public Website
1. Visit: `http://localhost/nacos/public/index.php`
2. Check these pages:
   - Home
   - About
   - Events
   - Projects
3. **Expected**: All pages load without errors

---

## 🐛 Common Issues & Solutions

### Issue: "Access Denied" when logging in as admin
**Solution**: Check database role
```sql
SELECT member_id, full_name, matric_no, role 
FROM MEMBERS 
WHERE matric_no = 'CSC/2024/001';
```
Should show `role = 'admin'`

### Issue: "Page not found"
**Solution**: Check XAMPP is running and URL is correct
- Should be: `http://localhost/nacos/...`
- NOT: `http://localhost:80/nacos/...`

### Issue: Database connection error
**Solution**: 
1. Check MySQL is running in XAMPP
2. Check database name in `config/database.php`
3. Verify database exists: `nacos_dashboard`

### Issue: Session not persisting
**Solution**: Clear browser cookies and cache

### Issue: Email not sending
**Solution**: Check `config/email.php` settings
- Default uses PHP `mail()` function
- May not work on localhost (expected)
- Email approval/rejection won't work locally

---

## 📊 What to Test

### ✅ Authentication System
- [x] Admin login redirects to admin panel
- [x] Member login redirects to member dashboard
- [x] Invalid credentials rejected
- [x] Unapproved members blocked
- [x] Session persists across pages
- [x] Logout clears session

### ✅ Role Management
- [x] Admin can view member roles
- [x] Admin can change member roles
- [x] Role changes save to database
- [x] Self-demotion prevented
- [x] Regular members blocked from admin

### ✅ CRUD Operations
- [x] Create members/events/projects
- [x] Read/view details
- [x] Update/edit records
- [x] Delete records
- [x] All operations work

### ✅ File Uploads
- [x] Documents upload
- [x] Resources upload
- [x] Partner logos upload
- [x] File size limits work

### ✅ Search & Filters
- [x] Member search works
- [x] Event filtering works
- [x] Pagination works

---

## 🎯 Success Criteria

Your system is working correctly if:

✅ Admins can access admin panel  
✅ Regular members CANNOT access admin panel  
✅ Role management works (promote/demote)  
✅ Self-demotion is prevented  
✅ Member approval workflow works  
✅ All CRUD operations work  
✅ File uploads work  
✅ Logout works properly  
✅ Public website loads  
✅ No PHP errors visible  

---

## 📱 Quick URLs

### Public Pages
- Homepage: `http://localhost/nacos/public/index.php`
- Login: `http://localhost/nacos/public/login.php`
- Register: `http://localhost/nacos/public/register.php`
- About: `http://localhost/nacos/public/about.php`
- Events: `http://localhost/nacos/public/events.php`
- Projects: `http://localhost/nacos/public/projects.php`

### Admin Pages
- Dashboard: `http://localhost/nacos/admin/index.php`
- Members: `http://localhost/nacos/admin/members.php`
- Events: `http://localhost/nacos/admin/events.php`
- Projects: `http://localhost/nacos/admin/projects.php`
- Documents: `http://localhost/nacos/admin/documents.php`
- Resources: `http://localhost/nacos/admin/resources.php`
- Partners: `http://localhost/nacos/admin/partners.php`

---

## 🔧 Debug Tools

### Run System Test
```bash
C:\xampp\php\php.exe c:\xampp\htdocs\nacos\test_admin_roles.php
```
Should show: **10/10 tests passed ✅**

### Check PHP Errors
Look at: `C:\xampp\php\logs\php_error_log.txt`

### Check Apache Errors
Look at: `C:\xampp\apache\logs\error.log`

### Database Queries
```bash
C:\xampp\mysql\bin\mysql.exe -u root nacos_dashboard
```

---

## 📞 Need Help?

If something doesn't work:
1. Check XAMPP services are running
2. Clear browser cache
3. Check browser console (F12) for errors
4. Run the automated test script
5. Check the documentation files

---

## 🎉 Happy Testing!

The system is ready for testing. Start with the admin login and work through the checklist above.

**Main Test URL**: http://localhost/nacos/public/login.php

Good luck! 🚀
