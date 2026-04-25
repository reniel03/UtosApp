<?php
// process_task.php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $room = isset($_POST['room']) ? trim($_POST['room']) : '';
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
    $due_time = isset($_POST['due_time']) ? $_POST['due_time'] : '';
    $attachments = '';
    $teacher_email = isset($_SESSION['email']) ? $_SESSION['email'] : '';

    // Validate required fields
    if (empty($title) || empty($description) || empty($room) || empty($due_date) || empty($due_time) || empty($teacher_email)) {
        die("Error: Missing required fields. Teacher Email: $teacher_email");
    }

    // Handle image file upload only
    if (isset($_FILES['attachments']) && $_FILES['attachments']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($_FILES['attachments']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['attachments']['name'], PATHINFO_EXTENSION);
            $new_name = uniqid('img_', true) . '.' . $ext;
            $target = 'uploads/' . $new_name;
            if (move_uploaded_file($_FILES['attachments']['tmp_name'], $target)) {
                $attachments = $target;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO tasks (teacher_email, title, description, room, due_date, due_time, attachments) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('sssssss', $teacher_email, $title, $description, $room, $due_date, $due_time, $attachments);
    if (!$stmt->execute()) {
        die("Execute failed: " . $stmt->error);
    }
    $stmt->close();
    $conn->close();

    header('Location: teacher_task_page.php?success=1');
    exit();
}
?>
