<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo "Not logged in";
    exit();
}

$student_email = $_SESSION['email'];

// Check if student_todos table exists
$check_table = "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'utosapp' AND TABLE_NAME = 'student_todos'";
$result = $conn->query($check_table);
$row = $result->fetch_assoc();
echo "Table exists: " . ($row['count'] > 0 ? "YES" : "NO") . "<br>";

// Fetch all tasks for this student
$query = "SELECT * FROM student_todos WHERE student_email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();

echo "Student Email: " . htmlspecialchars($student_email) . "<br>";
echo "Total Tasks: " . $result->num_rows . "<br><br>";

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Title</th><th>Description</th><th>Room</th><th>Due Date</th><th>Due Time</th><th>Completed</th></tr>";
    while ($task = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $task['id'] . "</td>";
        echo "<td>" . htmlspecialchars($task['title']) . "</td>";
        echo "<td>" . htmlspecialchars($task['description']) . "</td>";
        echo "<td>" . htmlspecialchars($task['room']) . "</td>";
        echo "<td>" . $task['due_date'] . "</td>";
        echo "<td>" . $task['due_time'] . "</td>";
        echo "<td>" . ($task['is_completed'] ? "Yes" : "No") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No tasks found for this student";
}

$stmt->close();
$conn->close();
?>
