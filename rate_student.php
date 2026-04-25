<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $student_email = isset($_POST['student_email']) ? $_POST['student_email'] : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $teacher_email = $_SESSION['email'];
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid rating. Must be 1-5.']);
        exit();
    }
    
    // Verify this task belongs to the teacher
    $check_stmt = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND teacher_email = ?");
    $check_stmt->bind_param('is', $task_id, $teacher_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found or unauthorized']);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Update the rating in student_todos
    $update_stmt = $conn->prepare("UPDATE student_todos SET rating = ?, rated_at = NOW() WHERE task_id = ? AND student_email = ?");
    $update_stmt->bind_param('iis', $rating, $task_id, $student_email);
    
    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Rating saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No record found to update']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save rating']);
    }
    
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
