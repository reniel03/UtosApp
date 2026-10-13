-- Create notifications table for tracking urgent task notifications
CREATE TABLE IF NOT EXISTS student_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_email VARCHAR(255) NOT NULL,
    task_id INT NOT NULL,
    notification_type VARCHAR(50) DEFAULT 'urgent_task',
    message TEXT,
    is_read BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_student_email (student_email),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);
