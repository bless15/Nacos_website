-- ============================================
-- EXECUTIVE POSITION MIGRATION
-- ============================================
-- Purpose: Add executive_position for position-based executive permissions
-- Date: March 21, 2026

ALTER TABLE members
ADD COLUMN executive_position ENUM(
    'social_director',
    'general_secretary',
    'academic_director',
    'creative_innovative_director',
    'public_relations_officer'
) NULL AFTER role;

ALTER TABLE members
ADD INDEX idx_executive_position (executive_position);

-- Optional cleanup: ensure non-executives don't carry executive position
UPDATE members
SET executive_position = NULL
WHERE role <> 'executive';
