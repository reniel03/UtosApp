<?php
session_start();
include 'db_connect.php';

// Check if user is logged in as a student
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$student_email = $_SESSION['email'];
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

try {
    // Validate inputs
    if (empty($current_password) || empty($new_password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit();
    }

    // Get current password from database
    $query = "SELECT password FROM students WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $student_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit();
    }

    // Verify current password
    if (!password_verify($current_password, $student['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Hash new password
    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $updateQuery = "UPDATE students SET password = ? WHERE email = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ss", $hashedPassword, $student_email);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to change password']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
