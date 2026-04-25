<?php
include 'db_connect.php';

// Clear all student_todos
$conn->query("DELETE FROM student_todos");
echo "Cleared all student_todos\n";

// Verify it's empty
$result = $conn->query("SELECT COUNT(*) as cnt FROM student_todos");
$row = $result->fetch_assoc();
echo "Current todos count: " . $row['cnt'] . "\n";

// Show available tasks
echo "\nAvailable tasks to accept:\n";
$result = $conn->query("SELECT id, title, room FROM tasks");
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Title: " . $row['title'] . " | Room: " . $row['room'] . "\n";
}

$conn->close();
?>
