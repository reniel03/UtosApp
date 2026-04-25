<?php
session_start();
header('Content-Type: application/json');

// Check if teacher is logged in
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db_connect.php';

// Get task ID from request
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

if (!$task_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit;
}

try {
    // Verify the task belongs to the teacher
    $taskStmt = $conn->prepare("SELECT teacher_email FROM tasks WHERE id = ?");
    $taskStmt->bind_param("i", $task_id);
    $taskStmt->execute();
    $taskResult = $taskStmt->get_result();
    $task = $taskResult->fetch_assoc();
    
    if (!$task || $task['teacher_email'] !== $_SESSION['email']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Get all students who applied for this task with their details
    $stmt = $conn->prepare("
        SELECT 
            st.student_email, 
            st.status, 
            st.is_completed,
            st.created_at,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.student_id,
            s.year_level,
            s.course,
            s.photo,
            s.attachment
        FROM student_todos st
        JOIN students s ON st.student_email = s.email
        WHERE st.task_id = ?
        ORDER BY st.created_at DESC
    ");
    
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        // Build full name
        $full_name = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
        
        $students[] = [
            'student_email' => $row['student_email'],
            'status' => $row['status'],
            'is_completed' => $row['is_completed'],
            'created_at' => $row['created_at'],
            'full_name' => $full_name,
            'student_id' => $row['student_id'] ?? 'Not set',
            'year_level' => $row['year_level'] ?? 'Not set',
            'course' => $row['course'] ?? 'Not set',
            'photo' => $row['photo'] ?? 'profile-default.png',
            'com_picture' => $row['attachment'] ?? null,
            'is_verified' => !empty($row['attachment'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
