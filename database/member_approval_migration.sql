-- ============================================
-- MEMBER APPROVAL SYSTEM
-- ============================================
-- Purpose: Add approval requirement for new member registrations
-- Date: November 3, 2025
-- ============================================

-- Add approval column to MEMBERS table
ALTER TABLE MEMBERS
ADD COLUMN is_approved BOOLEAN DEFAULT FALSE COMMENT 'Admin approval status' AFTER membership_status,
ADD COLUMN approved_by INT NULL COMMENT 'Admin who approved' AFTER is_approved,
ADD COLUMN approval_date DATETIME NULL COMMENT 'When approved' AFTER approved_by;

-- Add foreign key for approved_by
ALTER TABLE MEMBERS
ADD CONSTRAINT fk_approved_by FOREIGN KEY (approved_by) REFERENCES MEMBERS(member_id) ON DELETE SET NULL;

-- Add index for better search performance
ALTER TABLE MEMBERS ADD INDEX idx_is_approved (is_approved);

-- Approve all existing members (legacy data)
UPDATE MEMBERS SET is_approved = TRUE WHERE is_approved = FALSE;
