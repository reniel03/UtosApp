<?php
session_start();
include 'db_connect.php';

echo "<h2>Database Debug Info</h2>";

// Check if tasks table exists and has data
$result = $conn->query("SELECT * FROM tasks LIMIT 5");
echo "<h3>Tasks Table:</h3>";
if ($result && $result->num_rows > 0) {
    echo "Total rows in tasks: " . $result->num_rows . "<br>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Title: " . htmlspecialchars($row['title']) . " | Room: " . $row['room'] . "<br>";
    }
} else {
    echo "No tasks found or table doesn't exist<br>";
}

// Check student_todos table
$student_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'Not logged in';
echo "<h3>Current User:</h3>";
echo "Email: " . $student_email . "<br>";

$result = $conn->query("SELECT * FROM student_todos WHERE student_email = '$student_email'");
echo "<h3>Student Todos:</h3>";
if ($result && $result->num_rows > 0) {
    echo "Total todos: " . $result->num_rows . "<br>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Title: " . htmlspecialchars($row['title']) . " | Room: " . $row['room'] . "<br>";
    }
} else {
    echo "No todos found for this student<br>";
}

// Check debug log
echo "<h3>Debug Log:</h3>";
if (file_exists('debug_log.txt')) {
    echo "<pre>" . htmlspecialchars(file_get_contents('debug_log.txt')) . "</pre>";
} else {
    echo "No debug log file yet<br>";
}

$conn->close();
?>
