CREATE DATABASE IF NOT EXISTS utosapp;
USE utosapp;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_email VARCHAR(255),
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    room VARCHAR(100) NOT NULL,
    due_date DATE NOT NULL,
    due_time TIME NOT NULL,
    is_urgent BOOLEAN DEFAULT 0,
    required_skills TEXT,
    notify_on_urgent BOOLEAN DEFAULT 1,
    attachments VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_urgent (is_urgent),
    INDEX idx_teacher_email (teacher_email)
);