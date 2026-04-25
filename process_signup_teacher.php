<?php
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $requiredFields = ['first_name', 'middle_name', 'last_name', 'email', 'password', 'department'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            die("Error: $field is required.");
        }
    }

    // Validate file upload
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        die("Error: Photo upload failed.");
    }

    // Sanitize inputs
    $firstName = htmlspecialchars($_POST['first_name']);
    $middleName = htmlspecialchars($_POST['middle_name']);
    $lastName = htmlspecialchars($_POST['last_name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $department = htmlspecialchars($_POST['department']);

    // Handle file upload
    $photo = $_FILES['photo'];
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $uploadFile = $uploadDir . basename($photo['name']);

    if (!move_uploaded_file($photo['tmp_name'], $uploadFile)) {
        die("Error: Failed to upload photo.");
    }

    // Database connection
    $host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSKkuXTKAhGyWRob';
    $dbname = getenv('MYSQLDATABASE') ?: 'railway';
    $port = getenv('MYSQLPORT') ?: '3306';
    $db = new mysqli($host, $user, $pass, $dbname, (int)$port);
    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

}
?>