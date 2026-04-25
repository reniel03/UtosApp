<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in as teacher
if (!isset($_SESSION['email']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $student_email = isset($_POST['student_email']) ? trim($_POST['student_email']) : '';
    
    if ($task_id <= 0 || empty($student_email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }
    
    // Verify this task belongs to the teacher
    $teacher_email = $_SESSION['email'];
    $verify_stmt = $conn->prepare('SELECT id FROM tasks WHERE id = ? AND teacher_email = ?');
    $verify_stmt->bind_param('is', $task_id, $teacher_email);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found or unauthorized']);
        $verify_stmt->close();
        exit();
    }
    $verify_stmt->close();
    
    // First, check if the application exists and get its current status
    $check_stmt = $conn->prepare('SELECT status, is_completed FROM student_todos WHERE task_id = ? AND student_email = ?');
    $check_stmt->bind_param('is', $task_id, $student_email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Student application not found']);
        $check_stmt->close();
        exit();
    }
    
    $current = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Check if already completed
    if ($current['is_completed']) {
        echo json_encode(['success' => false, 'message' => 'Cannot reject completed task']);
        exit();
    }
    
    // Check if already processed
    if ($current['status'] === 'ongoing' || $current['status'] === 'rejected') {
        echo json_encode(['success' => false, 'message' => 'Task already processed: ' . ($current['status'] === 'ongoing' ? 'In Progress' : 'Rejected')]);
        exit();
    }
    
    // Update the student's task status to rejected
    $update_stmt = $conn->prepare('UPDATE student_todos SET status = "rejected" WHERE task_id = ? AND student_email = ?');
    $update_stmt->bind_param('is', $task_id, $student_email);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Application rejected successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    
    $update_stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
