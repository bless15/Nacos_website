-- ============================================
-- ADD START_TIME AND END_TIME TO EVENTS TABLE
-- ============================================
-- Purpose: Track event start and end times to show "Ongoing" or "Event Ended" status
-- Date: February 20, 2026
-- ============================================

-- Rename event_time to start_time for clarity
ALTER TABLE EVENTS 
CHANGE COLUMN event_time start_time TIME NULL COMMENT 'Event start time';

-- Add end_time column to EVENTS table
ALTER TABLE EVENTS 
ADD COLUMN end_time TIME NULL COMMENT 'Event end time' 
AFTER start_time;

-- Update existing events with default end time (2 hours after start time)
-- This is just a default - admin should update actual end times
UPDATE EVENTS 
SET end_time = ADDTIME(COALESCE(start_time, '09:00:00'), '02:00:00')
WHERE end_time IS NULL;
