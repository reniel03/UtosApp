<?php
include 'db_connect.php';

// Test 1: Check tasks table
$result = $conn->query("SELECT COUNT(*) as cnt FROM tasks");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Tasks in database: " . $row['cnt'] . "\n";
} else {
    echo "Error checking tasks: " . $conn->error . "\n";
}

// Test 2: Check student_todos table exists
$result = $conn->query("SHOW TABLES LIKE 'student_todos'");
if ($result && $result->num_rows > 0) {
    echo "student_todos table EXISTS\n";
} else {
    echo "student_todos table does NOT exist yet\n";
}

// Test 3: Try creating it manually
$create_table = "CREATE TABLE IF NOT EXISTS student_todos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_email VARCHAR(255) NOT NULL,
    task_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    room VARCHAR(100),
    due_date DATE NOT NULL,
    due_time TIME NOT NULL,
    is_completed BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($create_table)) {
    echo "student_todos table created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?>
