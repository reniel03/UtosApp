<?php
session_start();
include 'db_connect.php';

echo "<!DOCTYPE html><html><head><title>Task Application Debug</title></head><body>";
echo "<h1>Task Application Debug Test</h1>";

// Check table structure
echo "<h2>1. Student_todos Table Structure:</h2>";
$result = $conn->query("DESC student_todos");
if ($result) {
    echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<strong style='color: red;'>ERROR: Table not found!</strong>";
    echo "<br>Error: " . $conn->error;
}

// Check current todos
echo "<h2>2. Current Student Todos (as 'debug@test.com'):</h2>";
$result = $conn->query("SELECT * FROM student_todos WHERE student_email = 'debug@test.com' LIMIT 10");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        $first = true;
        while ($row = $result->fetch_assoc()) {
            if ($first) {
                echo "<tr>";
                foreach ($row as $key => $val) {
                    echo "<th>" . htmlspecialchars($key) . "</th>";
                }
                echo "</tr>";
                $first = false;
            }
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars($val) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<em>No todos found for this student.</em>";
    }
} else {
    echo "<strong style='color: red;'>ERROR: Query failed!</strong>";
    echo "<br>Error: " . $conn->error;
}

// Test INSERT
echo "<h2>3. Test INSERT into student_todos:</h2>";
$test_email = 'debug@test.com';
$test_task_id = 1;

$insert_query = "INSERT INTO student_todos (student_email, task_id, is_completed, status) VALUES (?, ?, 0, 'pending')";
$stmt = $conn->prepare($insert_query);

if ($stmt) {
    $stmt->bind_param('si', $test_email, $test_task_id);
    if ($stmt->execute()) {
        echo "<strong style='color: green;'>INSERT successful!</strong>";
        $new_id = $stmt->insert_id;
        echo "<br>New ID: " . $new_id;
    } else {
        echo "<strong style='color: red;'>INSERT failed!</strong>";
        echo "<br>Error: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "<strong style='color: red;'>PREPARE failed!</strong>";
    echo "<br>Error: " . $conn->error;
}

// Check if task exists
echo "<h2>4. Check if task_id=1 exists:</h2>";
$result = $conn->query("SELECT id, title FROM tasks WHERE id = 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Task found: ID=" . $row['id'] . ", Title=" .$row['title'];
} else {
    echo "<strong style='color: orange;'>Task ID 1 not found!</strong>";
}

// Test the student_page query
echo "<h2>5. Test student_page.php Query (applied tasks):</h2>";
$query = "SELECT t.*, st.is_completed, st.status, st.created_at as applied_at
          FROM tasks t
          INNER JOIN student_todos st ON t.id = st.task_id AND st.student_email = ?
          WHERE st.is_completed = 0
          ORDER BY t.due_date ASC, t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $test_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<strong>Found " . $result->num_rows . " applied tasks:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo "Task: " . $row['title'] . " (ID: " . $row['id'] . ", Status: " . $row['status'] . ")<br>";
    }
} else {
    echo "<strong style='color: orange;'>No applied tasks found for student!</strong>";
}

$conn->close();
echo "</body></html>";
?>
