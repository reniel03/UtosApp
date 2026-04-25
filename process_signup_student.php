<?php
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $requiredFields = ['first_name', 'last_name', 'email', 'password', 'year_level', 'student_id', 'course'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            showError("$field is required.");
        }
    }

    // Profile picture is optional - no validation required

    // Sanitize inputs
    $firstName = htmlspecialchars($_POST['first_name']);
    $middleName = htmlspecialchars($_POST['middle_name']);
    $lastName = htmlspecialchars($_POST['last_name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $yearLevel = htmlspecialchars($_POST['year_level']);
    $studentId = htmlspecialchars($_POST['student_id']);
    $course = htmlspecialchars($_POST['course']);

    // Handle profile picture upload (optional)
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadFile = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo = $_FILES['photo'];
        $uploadFile = $uploadDir . basename($photo['name']);
        if (!move_uploaded_file($photo['tmp_name'], $uploadFile)) {
            showError("Failed to upload photo.");
        }
    }
    
    // Handle attachment upload (optional)
    $attachmentFile = '';
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $attachment = $_FILES['attachment'];
        $attachmentFile = $uploadDir . basename($attachment['name']);
        if (!move_uploaded_file($attachment['tmp_name'], $attachmentFile)) {
            showError("Failed to upload attachment.");
        }
    }

    // Database connection
    $host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSKkuXTKAhGyWRob';
    $dbname = getenv('MYSQLDATABASE') ?: 'railway';
    $port = getenv('MYSQLPORT') ?: '3306';
    $db = new mysqli($host, $user, $pass, $dbname, (int)$port);
    if ($db->connect_error) {
        showError("Connection failed: " . $db->connect_error);
    }

    // Initialize all tables
    $conn = $db;
    include 'auto_init_tables.php';

    // Add missing columns if they don't exist
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS middle_name VARCHAR(100) AFTER first_name");
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) AFTER middle_name");
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS year_level VARCHAR(50) AFTER password");
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS student_id VARCHAR(100) AFTER year_level");
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS course VARCHAR(100) AFTER student_id");
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS photo VARCHAR(255) AFTER course");
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) AFTER photo");

    // Check if email already exists
    $checkEmailSQL = "SELECT id FROM students WHERE email = ?";
    $stmt = $db->prepare($checkEmailSQL);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $stmt->close();
        $db->close();
        showError("Email already exists. Please use a different email.");
    }
    $stmt->close();

    // Insert student data
    $insertSQL = "INSERT INTO students (first_name, middle_name, last_name, email, password, year_level, student_id, course, photo, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($insertSQL);
    if (!$stmt) {
        showError("Error preparing statement: " . $db->error);
    }
    $stmt->bind_param("ssssssssss", $firstName, $middleName, $lastName, $email, $password, $yearLevel, $studentId, $course, $uploadFile, $attachmentFile);
    if (!$stmt->execute()) {
        showError("Error executing statement: " . $stmt->error);
    }
    $stmt->close();
    $db->close();

    // Start session and store user information
    session_start();
    $_SESSION['user_role'] = 'student';
    $_SESSION['first_name'] = $firstName;
    $_SESSION['middle_name'] = $middleName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['email'] = $email;
    $_SESSION['year_level'] = $yearLevel;
    $_SESSION['student_id'] = $studentId;
    $_SESSION['course'] = $course;
    $_SESSION['photo'] = $uploadFile;

    // Redirect to student page or show success
    header("Location: login_student.php?success=1");
    exit();
}

function showError($message) {
    $encodedMessage = urlencode($message);
    header("Location: login_student.php?error=" . $encodedMessage);
    exit();
}