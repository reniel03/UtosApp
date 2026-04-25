<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit();
}

$student_email = $_GET['email'];

$stmt = $conn->prepare('SELECT first_name, middle_name, last_name, email, student_id, year_level, course, photo, attachment FROM students WHERE email = ?');
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Build full name
    $full_name = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
    
    // Check if COM (attachment) exists for verification
    $is_verified = !empty($row['attachment']);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'full_name' => $full_name,
            'email' => $row['email'],
            'student_id' => $row['student_id'] ?? 'Not set',
            'year_level' => $row['year_level'] ?? 'Not set',
            'course' => $row['course'] ?? 'Not set',
            'photo' => $row['photo'] ?? 'profile-default.png',
            'com_picture' => $row['attachment'] ?? null,
            'is_verified' => $is_verified
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
}

$stmt->close();
$conn->close();
?>
