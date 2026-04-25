<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

// Check if user is a student
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$student_email = $_SESSION['email'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

if ($action === 'complete_task' && $task_id > 0) {
    // Verify that student has this task and it's not already completed
    $check_query = "SELECT st.id, st.status FROM student_todos st 
                   WHERE st.student_email = ? AND st.task_id = ? AND st.is_completed = 0";
    $stmt = $conn->prepare($check_query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param('si', $student_email, $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Task not found or already completed']);
        $stmt->close();
        exit();
    }
    $stmt->close();
    
    // Update task to completed
    $update_query = "UPDATE student_todos SET is_completed = 1 WHERE student_email = ? AND task_id = ?";
    $update_stmt = $conn->prepare($update_query);
    
    if (!$update_stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $update_stmt->bind_param('si', $student_email, $task_id);
    
    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Task marked as complete successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task already completed or not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to complete task: ' . $update_stmt->error]);
    }
    $update_stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

$conn->close();
?>
