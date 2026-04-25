<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Database connection
    $host = getenv('MYSQLHOST') ?: 'junction.proxy.rlwy.net';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSkkuXTKAhGyWRob';
    $dbname = getenv('MYSQLDATABASE') ?: 'railway';
    $port = getenv('MYSQLPORT') ?: '23823';
    $db = new mysqli($host, $user, $pass, $dbname, (int)$port);

    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

    // Get teacher information
    $stmt = $db->prepare("SELECT * FROM teachers WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $teacher = $result->fetch_assoc();
        
        if (password_verify($password, $teacher['password'])) {
            // Set all session variables
            $_SESSION['user_role'] = 'teacher';
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['first_name'] = $teacher['first_name'];
            $_SESSION['middle_name'] = $teacher['middle_name'];
            $_SESSION['last_name'] = $teacher['last_name'];
            $_SESSION['email'] = $teacher['email'];
            $_SESSION['department'] = $teacher['department'];
            $_SESSION['photo'] = $teacher['photo'];
            $_SESSION['gender'] = $teacher['gender'];
            
            header('Location: teacher_task_page.php');
            exit();
        } else {
            echo "<script>alert('Invalid password!'); window.location.href = 'login.php';</script>";
        }
    } else {
        echo "<script>alert('User not found!'); window.location.href = 'login.php';</script>";
    }

    $stmt->close();
    $db->close();
}
?>
