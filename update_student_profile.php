<?php
session_start();
include 'db_connect.php';

// Check if user is logged in as a student
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$student_email = $_SESSION['email'];

try {
    // Get POST data
    $name = $_POST['name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $year_level = $_POST['year_level'] ?? '';
    $course = $_POST['course'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';

    // Validate email
    if ($email && $email !== $student_email) {
        // Check if new email already exists
        $checkEmailSQL = "SELECT id FROM students WHERE email = ? AND email != ?";
        $stmt = $conn->prepare($checkEmailSQL);
        $stmt->bind_param("ss", $email, $student_email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        $stmt->close();
    }

    // Handle profile photo upload
    $photoPath = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $file = $_FILES['photo'];
        $filename = time() . '_' . basename($file['name']);
        $uploadFile = $uploadDir . $filename;

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images are allowed']);
            exit();
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
            $photoPath = $uploadFile;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload photo']);
            exit();
        }
    }

    // Parse name into first, middle, last names
    $nameParts = explode(' ', trim($name));
    $firstName = $nameParts[0] ?? '';
    $middleName = '';
    $lastName = '';

    if (count($nameParts) === 2) {
        $lastName = $nameParts[1];
    } elseif (count($nameParts) >= 3) {
        $middleName = implode(' ', array_slice($nameParts, 1, -1));
        $lastName = end($nameParts);
    }

    // Update students table
    if ($photoPath) {
        // Update with new photo
        $updateSQL = "UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, year_level = ?, course = ?, gender = ?, photo = ? WHERE email = ?";
        $stmt = $conn->prepare($updateSQL);
        $stmt->bind_param("ssssssss", $firstName, $middleName, $lastName, $year_level, $course, $gender, $photoPath, $student_email);
    } else {
        // Update without photo
        $updateSQL = "UPDATE students SET first_name = ?, middle_name = ?, last_name = ?, year_level = ?, course = ?, gender = ? WHERE email = ?";
        $stmt = $conn->prepare($updateSQL);
        $stmt->bind_param("sssssss", $firstName, $middleName, $lastName, $year_level, $course, $gender, $student_email);
    }

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        exit();
    }
    $stmt->close();

    // Update session variables
    $_SESSION['first_name'] = $firstName;
    $_SESSION['middle_name'] = $middleName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['gender'] = $gender;
    $_SESSION['year_level'] = $year_level;
    $_SESSION['course'] = $course;
    if ($photoPath) {
        $_SESSION['photo'] = $photoPath;
    }

    // Return success response
    $response = [
        'success' => true,
        'message' => 'Profile updated successfully!',
        'photo_path' => $photoPath ?: null
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>
