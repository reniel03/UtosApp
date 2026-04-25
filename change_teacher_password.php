<?php
session_start();
include 'db_connect.php';

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$teacher_email = $_SESSION['email'];
$response = ['success' => false, 'message' => ''];

try {
    // Get form data
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($current_password) || empty($new_password)) {
        throw new Exception('Please fill in all fields');
    }
    
    // Get teacher from database
    $stmt = $conn->prepare("SELECT password FROM teachers WHERE email = ?");
    $stmt->bind_param('s', $teacher_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows !== 1) {
        throw new Exception('Teacher not found');
    }
    
    $teacher = $result->fetch_assoc();
    
    // Verify current password
    if (!password_verify($current_password, $teacher['password'])) {
        throw new Exception('Current password is incorrect');
    }
    
    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Update password in database
    $updateStmt = $conn->prepare("UPDATE teachers SET password = ? WHERE email = ?");
    $updateStmt->bind_param('ss', $hashed_password, $teacher_email);
    
    if ($updateStmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Password changed successfully!';
    } else {
        throw new Exception('Failed to update password');
    }
    
    $updateStmt->close();
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
