<?php
session_start();
$_SESSION['email'] = 'test@student.com'; // Simulate a logged-in user

include 'db_connect.php';

$student_email = $_SESSION['email'];

$result = $conn->query("SELECT * FROM student_todos WHERE student_email = '$student_email'");
if ($result) {
    echo "Student todos: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['title'] . " (Room: " . $row['room'] . ")\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

// List all emails in student_todos
echo "\nAll student emails in todos:\n";
$result = $conn->query("SELECT DISTINCT student_email FROM student_todos");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['student_email'] . "\n";
    }
}

$conn->close();
?>
