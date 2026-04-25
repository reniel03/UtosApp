<?php
include 'db_connect.php';

// Clear the student_todos table
$conn->query("DELETE FROM student_todos");
echo "Cleared student_todos table\n";

// Insert test data with correct information
$student_email = 'jxrxnx@gmail.com';
$insert = "INSERT INTO student_todos (student_email, task_id, title, description, room, due_date, due_time) VALUES 
('$student_email', 1, 'dasdasassistant', 'This is a test task', 'ROOM 100', '2025-11-05', '02:36:00')";

if ($conn->query($insert)) {
    echo "Inserted test task\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "\nCurrent todos:\n";
$result = $conn->query("SELECT * FROM student_todos WHERE student_email = '$student_email'");
while ($row = $result->fetch_assoc()) {
    echo "Title: " . $row['title'] . " | Room: " . $row['room'] . "\n";
}
?>
