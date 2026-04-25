<?php
session_start();
include 'db_connect.php';

$student_email = $_SESSION['email'] ?? 'debug@test.com';

echo "=== DEBUG HOME PAGE QUERY ===<br>";
echo "Student Email: " . $student_email . "<br><br>";

// Check table structure
echo "=== TABLE STRUCTURE ===<br>";
$result = $conn->query("SHOW CREATE TABLE student_todos");
if($row = $result->fetch_row()) {
    echo "<pre>" . htmlspecialchars($row[1]) . "</pre>";
} else {
    echo "Table not found!<br>";
}

echo "<br>=== STUDENT_TODOS DATA ===<br>";
$result = $conn->query("SELECT * FROM student_todos WHERE student_email = '$student_email' LIMIT 5");
while($row = $result->fetch_assoc()) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}

echo "<br>=== HOME PAGE QUERY TEST ===<br>";
$query = "SELECT t.id, t.title, st.task_id, st.status
          FROM tasks t
          LEFT JOIN student_todos st ON t.id = st.task_id AND st.student_email = ?
          WHERE st.task_id IS NULL
          LIMIT 5";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();

echo "Query returned: " . $result->num_rows . " rows<br>";
while($row = $result->fetch_assoc()) {
    echo "Task ID: " . $row['id'] . " | Title: " . $row['title'] . "<br>";
}

$conn->close();
?>
