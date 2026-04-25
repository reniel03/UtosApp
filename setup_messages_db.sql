-- Create messages table for UtosApp
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_email VARCHAR(255) NOT NULL,
    sender_role ENUM('student', 'teacher') NOT NULL,
    receiver_email VARCHAR(255) NOT NULL,
    receiver_role ENUM('student', 'teacher') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_email),
    INDEX idx_receiver (receiver_email),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for faster conversation lookups
CREATE INDEX idx_conversation ON messages (sender_email, receiver_email, created_at);
