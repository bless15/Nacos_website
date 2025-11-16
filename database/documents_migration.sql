-- ============================================
-- DOCUMENTS TABLE ENHANCEMENTS
-- ============================================
-- Purpose: Add additional fields for better document management
-- Date: November 3, 2025
-- ============================================

-- Add new columns to DOCUMENTS table
ALTER TABLE DOCUMENTS 
ADD COLUMN title VARCHAR(255) NOT NULL AFTER doc_id,
ADD COLUMN document_date DATE NULL COMMENT 'Date the document relates to' AFTER description,
ADD COLUMN visibility ENUM('admin', 'members', 'public') DEFAULT 'admin' AFTER document_date,
ADD COLUMN tags VARCHAR(500) NULL COMMENT 'Comma-separated tags' AFTER visibility,
ADD COLUMN download_count INT DEFAULT 0 AFTER tags,
ADD COLUMN is_archived BOOLEAN DEFAULT FALSE AFTER download_count;

-- Add index for better search performance
ALTER TABLE DOCUMENTS ADD INDEX idx_visibility (visibility);
ALTER TABLE DOCUMENTS ADD INDEX idx_archived (is_archived);

-- Update doc_type enum to include more types
ALTER TABLE DOCUMENTS MODIFY COLUMN doc_type ENUM(
    'meeting_minutes', 
    'financial_report', 
    'constitution', 
    'policy', 
    'annual_report', 
    'event_report', 
    'proposal',
    'correspondence',
    'handover',
    'other'
) NOT NULL;
