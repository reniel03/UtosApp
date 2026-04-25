<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$email = $_SESSION['email'];
$role = $_SESSION['user_role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_photo':
        updateProfilePhoto($conn, $email, $role);
        break;
    case 'change_password':
        changePassword($conn, $email, $role);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function updateProfilePhoto($conn, $email, $role) {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        return;
    }
    
    $file = $_FILES['photo'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum 5MB.']);
        return;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.']);
        return;
    }
    
    // Create uploads directory if not exists
    $uploadDir = 'uploads/profiles/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . md5($email . time()) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Determine which table to update
        $table = ($role === 'teacher') ? 'teachers' : 'students';
        
        // Get old photo to delete
        $stmt = $conn->prepare("SELECT photo FROM $table WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $oldPhoto = $row['photo'];
            // Delete old photo if exists and not default
            if ($oldPhoto && file_exists($oldPhoto) && strpos($oldPhoto, 'profile-default') === false) {
                @unlink($oldPhoto);
            }
        }
        $stmt->close();
        
        // Update database
        $stmt = $conn->prepare("UPDATE $table SET photo = ? WHERE email = ?");
        $stmt->bind_param('ss', $filepath, $email);
        
        if ($stmt->execute()) {
            // Update session
            $_SESSION['photo'] = $filepath;
            echo json_encode(['success' => true, 'photo_url' => $filepath]);
        } else {
            // Delete uploaded file on database error
            @unlink($filepath);
            echo json_encode(['success' => false, 'error' => 'Failed to update database']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    }
}

function changePassword($conn, $email, $role) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
        return;
    }
    
    // Determine which table to use
    $table = ($role === 'teacher') ? 'teachers' : 'students';
    
    // Get current password hash
    $stmt = $conn->prepare("SELECT password FROM $table WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Verify current password
        if (!password_verify($currentPassword, $row['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            $stmt->close();
            return;
        }
        $stmt->close();
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE email = ?");
        $stmt->bind_param('ss', $hashedPassword, $email);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update password']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        $stmt->close();
    }
}

$conn->close();
?>
