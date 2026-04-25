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
    $name = $_POST['name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    
    $photo_path = null;
    
    // Handle file upload
    if (isset($_FILES['photo']) && $_FILES['photo']['size'] > 0) {
        $uploadDir = 'uploads/profiles/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $photoFile = $_FILES['photo'];
        $fileName = time() . '_' . basename($photoFile['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($photoFile['tmp_name'], $uploadFile)) {
            $photo_path = $uploadFile;
        } else {
            throw new Exception('Failed to upload photo');
        }
    }
    
    // Update database
    $updateFields = [];
    $updateParams = [];
    $types = '';
    
    if (!empty($gender)) {
        $updateFields[] = "gender = ?";
        $updateParams[] = $gender;
        $types .= 's';
    }
    
    if (!empty($bio)) {
        $updateFields[] = "bio = ?";
        $updateParams[] = $bio;
        $types .= 's';
    }
    
    if (!empty($birthday)) {
        $updateFields[] = "birthday = ?";
        $updateParams[] = $birthday;
        $types .= 's';
    }
    
    if (!empty($phone)) {
        $updateFields[] = "phone = ?";
        $updateParams[] = $phone;
        $types .= 's';
    }
    
    if ($photo_path) {
        $updateFields[] = "photo = ?";
        $updateParams[] = $photo_path;
        $types .= 's';
    }
    
    // Only update if there are fields to update
    if (!empty($updateFields)) {
        $updateParams[] = $teacher_email;
        $types .= 's';
        
        $sql = "UPDATE teachers SET " . implode(', ', $updateFields) . " WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$updateParams);
        
        if ($stmt->execute()) {
            // Update session
            if (!empty($gender)) {
                $_SESSION['gender'] = $gender;
            }
            if ($photo_path) {
                $_SESSION['photo'] = $photo_path;
            }
            
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully';
            $response['photo_path'] = $photo_path;
        } else {
            throw new Exception('Database update failed: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $response['success'] = true;
        $response['message'] = 'No changes to save';
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>
