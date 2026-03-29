# Event Status Update Instructions

## What Changed

The events page now shows 3 different statuses based on time:
1. **Upcoming** - Shows "Register Now" button
2. **Ongoing** - Shows "Ongoing" button (yellow/warning color with spinning icon)
3. **Event Ended** - Shows "Event Ended" button (gray/secondary color)

## SQL Migration Needed

Run this SQL in your phpMyAdmin or MySQL client:

```sql
-- Add end_time column to EVENTS table
ALTER TABLE EVENTS 
ADD COLUMN end_time TIME NULL COMMENT 'Event end time' 
AFTER event_time;

-- Update existing events with default end time (2 hours after start time)
UPDATE EVENTS 
SET end_time = ADDTIME(COALESCE(event_time, '09:00:00'), '02:00:00')
WHERE end_time IS NULL;
```

Or simply run the file: `database/add_event_end_time.sql`

## How It Works

- **Before event_time**: Shows "Register Now"
- **Between event_time and end_time**: Shows "Ongoing" 
- **After end_time**: Shows "Event Ended"

## Admin Side

When admins create/edit events, they should now set:
- `event_date` - The date
- `event_time` - When it starts (e.g., 10:00:00)
- `end_time` - When it ends (e.g., 12:00:00)

If no end_time is set, system assumes 2 hours after start time.

## Example

Event on Feb 20, 2026:
- Start: 10:00 AM
- End: 2:00 PM

At 9:00 AM → "Register Now"
At 11:00 AM → "Ongoing" (yellow button)
At 3:00 PM → "Event Ended" (gray button)

---
This happens automatically - no manual admin changes needed!
