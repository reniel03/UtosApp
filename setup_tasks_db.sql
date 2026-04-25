CREATE DATABASE IF NOT EXISTS utosapp;
USE utosapp;

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    room VARCHAR(100) NOT NULL,
    due_date DATE NOT NULL,
    due_time TIME NOT NULL,
    attachments VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);