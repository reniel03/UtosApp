<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php';

// Ensure student_todos table has all required columns
$conn->query("ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'pending'");
$conn->query("ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS teacher_email VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS rating INT DEFAULT NULL");
$conn->query("ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS rated_at DATETIME DEFAULT NULL");

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

if ($action === 'accept_task' && $task_id > 0) {
    // Fetch task details for verification AND to get the data needed for insertion
    $task_query = "SELECT id, title, description, room, due_date, due_time FROM tasks WHERE id = ?";
    $stmt = $conn->prepare($task_query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param('i', $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        $stmt->close();
        exit();
    }
    
    // Fetch task data
    $task_data = $result->fetch_assoc();
    $task_title = $task_data['title'];
    $task_description = $task_data['description'];
    $task_room = $task_data['room'];
    $task_due_date = $task_data['due_date'];
    $task_due_time = $task_data['due_time'];
    $stmt->close();
    
    // Check if student already accepted this task
    $check_query = "SELECT id FROM student_todos WHERE student_email = ? AND task_id = ?";
    $stmt = $conn->prepare($check_query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param('si', $student_email, $task_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'You have already applied for this task']);
        $stmt->close();
        exit();
    }
    $stmt->close();
    
    // Insert into student_todos with all required fields
    $insert_query = "INSERT INTO student_todos (student_email, task_id, title, description, room, due_date, due_time, is_completed, status) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pending')";
    $stmt = $conn->prepare($insert_query);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param('sisssss', $student_email, $task_id, $task_title, $task_description, $task_room, $task_due_date, $task_due_time);
    
    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Successfully applied for task']);
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to apply: ' . $stmt->error]);
        $stmt->close();
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}

$conn->close();
?>
