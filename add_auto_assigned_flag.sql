-- Add auto_assigned flag to tasks table
-- This flag tracks whether an urgent task has been auto-assigned to a student
-- when the countdown timer expires. Prevents duplicate assignments.

ALTER TABLE tasks 
ADD COLUMN IF NOT EXISTS auto_assigned BOOLEAN DEFAULT 0 AFTER is_urgent;

-- Set index for faster queries
ALTER TABLE tasks 
ADD INDEX IF NOT EXISTS idx_auto_assigned (auto_assigned);
