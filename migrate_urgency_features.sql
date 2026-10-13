-- Migration: Add Urgency and Deadline Features to Tasks Table
-- This script adds columns for urgent task marking, skill requirements, and notifications

ALTER TABLE tasks 
ADD COLUMN IF NOT EXISTS is_urgent BOOLEAN DEFAULT 0 AFTER due_time,
ADD COLUMN IF NOT EXISTS required_skills TEXT AFTER is_urgent,
ADD COLUMN IF NOT EXISTS notify_on_urgent BOOLEAN DEFAULT 1 AFTER required_skills,
ADD COLUMN IF NOT EXISTS teacher_email VARCHAR(255) AFTER attachments;

-- Add index for faster queries
ALTER TABLE tasks ADD INDEX idx_urgent (is_urgent);
ALTER TABLE tasks ADD INDEX idx_teacher_email (teacher_email);
