<?php
include 'db_connect.php';

echo "ALL TASKS IN STUDENT_TODOS:\n";
echo "========================\n";
$result = $conn->query('SELECT * FROM student_todos');
while ($row = $result->fetch_assoc()) {
    echo "Title: " . $row['title'] . "\n";
    echo "Email: " . $row['student_email'] . "\n";
    echo "Room: " . $row['room'] . "\n";
    echo "Due: " . $row['due_date'] . " " . $row['due_time'] . "\n";
    echo "Completed: " . ($row['is_completed'] ? 'Yes' : 'No') . "\n";
    echo "---\n";
}
?>
