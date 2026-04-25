<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$teacher_email = $_SESSION['email'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$student_email = isset($_POST['student_email']) ? $_POST['student_email'] : '';
$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$approval_status = isset($_POST['approval_status']) ? $_POST['approval_status'] : ''; // 'approved' or 'rejected'

if (empty($action) || empty($student_email) || $task_id <= 0 || empty($approval_status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

if ($approval_status !== 'approved' && $approval_status !== 'rejected') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid approval status']);
    exit();
}

// Verify the task belongs to this teacher
$task_query = "SELECT id FROM tasks WHERE id = ? AND teacher_email = ?";
$stmt = $conn->prepare($task_query);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param('is', $task_id, $teacher_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Task not found or unauthorized']);
    $stmt->close();
    exit();
}
$stmt->close();

// Update the student_todos status
if ($action === 'approve_application') {
    $update_query = "UPDATE student_todos SET status = ? WHERE student_email = ? AND task_id = ?";
    $stmt = $conn->prepare($update_query);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param('ssi', $approval_status, $student_email, $task_id);

    if ($stmt->execute()) {
        // If approved, auto-reject all other pending students for this task
        if ($approval_status === 'approved') {
            $reject_others_query = "UPDATE student_todos SET status = 'rejected' WHERE student_email != ? AND task_id = ? AND status = 'pending'";
            $reject_stmt = $conn->prepare($reject_others_query);
            if ($reject_stmt) {
                $reject_stmt->bind_param('si', $student_email, $task_id);
                $reject_stmt->execute();
                $reject_stmt->close();
            }
        }
        
        http_response_code(200);
        $message = $approval_status === 'approved' ? 'Task approved! Other pending applications have been rejected.' : 'Task rejected!';
        echo json_encode(['success' => true, 'message' => $message, 'status' => $approval_status]);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update: ' . $stmt->error]);
        $stmt->close();
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
?>
