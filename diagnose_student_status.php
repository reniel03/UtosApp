<?php
session_start();
include 'db_connect.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Student Status Diagnostic Report</h2>";

// Get all tasks with students
$tasks_query = "SELECT t.id, t.title, COUNT(st.id) as student_count 
                FROM tasks t 
                LEFT JOIN student_todos st ON t.id = st.task_id 
                GROUP BY t.id 
                ORDER BY t.created_at DESC";

$tasks_result = $conn->query($tasks_query);

if ($tasks_result && $tasks_result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Task ID</th><th>Task Title</th><th>Total Students</th><th>Status Breakdown</th><th>Student Details</th>";
    echo "</tr>";
    
    while ($task = $tasks_result->fetch_assoc()) {
        $task_id = $task['id'];
        $students_query = "SELECT student_email, status, is_completed, created_at 
                          FROM student_todos 
                          WHERE task_id = ? 
                          ORDER BY created_at";
        
        $stmt = $conn->prepare($students_query);
        $stmt->bind_param('i', $task_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        
        $pending = 0;
        $ongoing = 0;
        $rejected = 0;
        $completed = 0;
        $student_details = "";
        
        while ($student = $students_result->fetch_assoc()) {
            $status = $student['status'];
            $is_completed = $student['is_completed'];
            
            if ($is_completed) {
                $completed++;
                $status_label = "✅ COMPLETED";
                $status_color = "#4CAF50";
            } elseif ($status === 'ongoing') {
                $ongoing++;
                $status_label = "⚙️ ONGOING";
                $status_color = "#2196F3";
            } elseif ($status === 'rejected') {
                $rejected++;
                $status_label = "❌ REJECTED";
                $status_color = "#f44336";
            } else {
                $pending++;
                $status_label = "⏳ PENDING";
                $status_color = "#FF9800";
            }
            
            $student_details .= "<div style='margin: 5px 0; padding: 8px; background: " . $status_color . "40; border-left: 3px solid " . $status_color . ";'>";
            $student_details .= "<strong>" . htmlspecialchars($student['student_email']) . "</strong> - ";
            $student_details .= $status_label . " (Applied: " . $student['created_at'] . ")";
            $student_details .= "</div>";
        }
        $stmt->close();
        
        echo "<tr>";
        echo "<td>" . $task_id . "</td>";
        echo "<td>" . htmlspecialchars($task['title']) . "</td>";
        echo "<td>" . $task['student_count'] . "</td>";
        echo "<td>Pending: " . $pending . " | Ongoing: " . $ongoing . " | Rejected: " . $rejected . " | Completed: " . $completed . "</td>";
        echo "<td>" . $student_details . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No tasks with students found.</p>";
}

$conn->close();
?>
