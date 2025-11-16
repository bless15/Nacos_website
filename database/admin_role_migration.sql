-- ============================================
-- ADMIN ROLE MANAGEMENT MIGRATION
-- ============================================
-- Purpose: Add role system to distinguish admins from regular members
-- Date: November 3, 2025
-- ============================================

-- Add role column to MEMBERS table
ALTER TABLE MEMBERS
ADD COLUMN role ENUM('admin', 'executive', 'member') DEFAULT 'member' AFTER membership_status;

-- Add index for better query performance
ALTER TABLE MEMBERS ADD INDEX idx_role (role);

-- IMPORTANT: Set the first member as admin (you can change this after)
-- Replace 'YOUR_MATRIC_NUMBER' with the matric number of the first admin
UPDATE MEMBERS SET role = 'admin' WHERE matric_no = 'CSC/2021/001' LIMIT 1;

-- Alternatively, update by member_id
-- UPDATE MEMBERS SET role = 'admin' WHERE member_id = 1;
