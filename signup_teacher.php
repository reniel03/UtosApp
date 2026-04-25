<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8');
    $middleName = htmlspecialchars(trim($_POST['middle_name']), ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8');
    $gender = htmlspecialchars(trim($_POST['gender']), ENT_QUOTES, 'UTF-8');
    $emailUsername = htmlspecialchars(trim($_POST['email_username']), ENT_QUOTES, 'UTF-8');
    // Only append @gmail.com if not already present
    if (strpos($emailUsername, '@') === false) {
        $email = $emailUsername . '@gmail.com';
    } else {
        $email = $emailUsername;
    }
    $password = $_POST['password'];

    $photo = $_FILES['photo'];
    $photoPath = null;
    
    // Only process photo if one was uploaded
    if ($photo && $photo['size'] > 0) {
        $uploadDir = 'uploads/profiles/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $photoPath = $uploadDir . basename($photo['name']);
        if (!move_uploaded_file($photo['tmp_name'], $photoPath)) {
            echo "<script>alert('Error uploading file. Please try again.'); window.location.href = 'signup_teacher.php';</script>";
            exit;
        }
    }

    $host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSKkuXTKAhGyWRob';
    $dbname = getenv('MYSQLDATABASE') ?: 'railway';
    $port = getenv('MYSQLPORT') ?: '3306';
    $db = new mysqli($host, $user, $pass, $dbname, (int)$port);

    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }

    // Auto-initialize tables on first connection
    $tables_check = $db->query("SHOW TABLES LIKE 'teachers'");
    if (!$tables_check || $tables_check->num_rows === 0) {
        $db->query("CREATE TABLE IF NOT EXISTS `teachers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `first_name` varchar(255) NOT NULL,
          `middle_name` varchar(255) DEFAULT NULL,
          `last_name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `department` varchar(255) DEFAULT 'Not Specified',
          `photo` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `gender` varchar(10) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        $db->query("CREATE TABLE IF NOT EXISTS `students` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `first_name` varchar(100) DEFAULT NULL,
          `middle_name` varchar(100) DEFAULT NULL,
          `last_name` varchar(100) DEFAULT NULL,
          `email` varchar(255) NOT NULL,
          `password` varchar(255) NOT NULL,
          `year_level` varchar(50) DEFAULT NULL,
          `student_id` varchar(100) DEFAULT NULL,
          `course` varchar(100) DEFAULT NULL,
          `gender` varchar(50) DEFAULT NULL,
          `photo` varchar(255) DEFAULT NULL,
          `attachment` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        $db->query("CREATE TABLE IF NOT EXISTS `messages` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `sender_email` varchar(255) NOT NULL,
          `sender_role` enum('student','teacher') NOT NULL,
          `receiver_email` varchar(255) NOT NULL,
          `receiver_role` enum('student','teacher') NOT NULL,
          `message` text NOT NULL,
          `is_read` tinyint(1) DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_sender` (`sender_email`),
          KEY `idx_receiver` (`receiver_email`),
          KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        $db->query("CREATE TABLE IF NOT EXISTS `tasks` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `teacher_email` varchar(255) NOT NULL,
          `title` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `room` varchar(100) NOT NULL,
          `due_date` date NOT NULL,
          `due_time` time NOT NULL,
          `attachments` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `teacher_email` (`teacher_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        $db->query("CREATE TABLE IF NOT EXISTS `task_files` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `task_id` int(11) NOT NULL,
          `file_path` varchar(255) NOT NULL,
          `file_name` varchar(255) NOT NULL,
          `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `task_id` (`task_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        
        $db->query("CREATE TABLE IF NOT EXISTS `student_todos` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `student_email` varchar(255) NOT NULL,
          `task_id` int(11) DEFAULT NULL,
          `title` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `room` varchar(100) DEFAULT NULL,
          `due_date` date NOT NULL,
          `due_time` time NOT NULL,
          `attachments` text DEFAULT NULL,
          `is_completed` tinyint(1) DEFAULT 0,
          `status` varchar(50) DEFAULT 'pending',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `teacher_email` varchar(255) DEFAULT NULL,
          `approved_at` datetime DEFAULT NULL,
          `rating` int(11) DEFAULT NULL,
          `rated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    $stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showCustomAlert('Email already exists'); });</script>";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $db->prepare("INSERT INTO teachers (first_name, middle_name, last_name, gender, email, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param('sssssss', $firstName, $middleName, $lastName, $gender, $email, $hashedPassword, $photoPath);

        if ($insertStmt->execute()) {
            $_SESSION['user_role'] = 'teacher';
            $_SESSION['first_name'] = $firstName;
            $_SESSION['middle_name'] = $middleName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;
            $_SESSION['photo'] = $photoPath;
            $_SESSION['gender'] = $gender;
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showSuccessAlert('teacher'); });</script>";
        } else {
            echo "<script>alert('Error during signup'); window.location.href = 'signup_teacher.php';</script>";
        }
        $insertStmt->close();
    }

    $stmt->close();
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>UtosApp - Teacher Sign Up</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #ffffff;
            background-size: 200% 200%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #000;
            overflow-x: hidden;
            overflow-y: auto;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #fb251d;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 55px;
            height: 55px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 0 #ffd6d6, 0 6px 14px rgba(251,37,29,0.10);
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
            z-index: 100;
        }

        .back-btn:hover {
            background: #d91c14;
            box-shadow: 0 12px 0 #ffd6d6, 0 12px 32px rgba(251,37,29,0.18);
            transform: scale(1.1);
        }

        .back-btn svg {
            width: 28px;
            height: 28px;
            stroke: currentColor;
            fill: none;
        }

        .signup-container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
            margin-top: 20px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section img {
            width: 120px;
            height: 120px;
            margin-bottom: 15px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            object-fit: contain;
        }

        .logo-section h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #000;
            background: transparent;
            padding: 15px 30px;
            border-radius: 10px;
            letter-spacing: 0.5px;
            font-family: 'Segoe UI', 'Trebuchet MS', sans-serif;
            box-shadow: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .logo-section h1:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(251, 37, 29, 0.4);
        }

        .signup-form {
            margin-top: 20px;
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            border: none;
            overflow: visible;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 12px;
            overflow: visible;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #1a1a1a;
            margin-bottom: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-row .form-group label {
            color: #000;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #fb251d;
            border-radius: 8px;
            color: #333;
            font-size: 15px;
            height: 46px;
            transition: all 0.3s;
        }

        .form-row .form-group input,
        .form-row .form-group select {
            background: #fff;
            border: 2px solid #fb251d;
        }

        .form-group input::placeholder,
        .form-group select::placeholder {
            color: #999;
        }

        .form-group input:focus,
        .form-group select:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #fb251d;
            outline: none;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            padding-right: 110px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #fb251d;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #d91c14;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        .email-suffix {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 15px;
            pointer-events: none;
            user-select: none;
        }

        .password-strength-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .password-strength-bar-bg {
            flex: 1;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: width 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
        }

        .password-strength-bar.weak {
            width: 33%;
            background: #fb251d;
            box-shadow: 0 0 12px rgba(251, 37, 29, 0.8);
        }

        .password-strength-bar.medium {
            width: 66%;
            background: #ffa726;
            box-shadow: 0 0 12px rgba(255, 167, 38, 0.8);
        }

        .password-strength-bar.strong {
            width: 100%;
            background: #66bb6a;
            box-shadow: 0 0 12px rgba(102, 187, 106, 0.8);
        }

        .password-strength-text {
            font-size: 11px;
            font-weight: 600;
            color: #000;
            min-width: 50px;
        }

        .password-requirements {
            background: #f5f5f5;
            border-left: 4px solid #fb251d;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 12px;
            margin-top: 8px;
        }

        .requirements-title {
            font-size: 11px;
            font-weight: 700;
            color: #000;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirements-list li {
            font-size: 12px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 0;
        }

        .requirements-list li.met {
            color: #66bb6a;
            font-weight: 600;
        }

        .requirement-icon {
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
            color: #ccc;
        }

        .requirements-list li.met .requirement-icon {
            background: #66bb6a;
            border-color: #66bb6a;
            color: white;
        }

        .drop-area {
            border: 2.5px dashed #ccc;
            background: #f9f9f9;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .drop-area:hover,
        .drop-area.dragover {
            border-color: #fb251d;
            background: #f0f0f0;
        }

        .upload-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .upload-text {
            font-size: 13px;
            font-weight: 600;
            color: #000;
            margin-bottom: 8px;
        }

        .or-text {
            font-size: 12px;
            color: #666;
            margin: 10px 0;
        }

        .browse-btn {
            background: #fb251d;
            color: #ffffff !important;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.3s;
            margin: 8px 0;
            display: inline-block;
        }

        .browse-btn:hover {
            background: #d91c14;
        }

        .max-size {
            font-size: 11px;
            color: #666;
            margin-top: 8px;
        }

        .file-preview {
            margin-top: 12px;
        }

        .preview-img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
            display: block;
            margin: 0 auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .preview-img:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .image-modal.show {
            display: flex;
        }

        .modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            background: #fff;
            border-radius: 12px;
            padding: 0;
            overflow: hidden;
        }

        .modal-content img {
            width: 100%;
            height: auto;
            display: block;
            max-height: 80vh;
            object-fit: contain;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #fb251d;
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
            z-index: 2001;
        }

        .modal-close:hover {
            background: #d91c14;
        }

        .remove-btn {
            background: #ff6b6b;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            transition: background 0.3s;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .remove-btn:hover {
            background: #e63939;
        }

        .signup-btn {
            width: 100%;
            padding: 13px 16px;
            background: linear-gradient(135deg, #fb251d, #ff6b4a);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            height: 48px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(251, 37, 29, 0.3);
            position: relative;
            overflow: hidden;
        }

        .signup-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ff6b4a, #fb251d);
            transition: left 0.3s;
            z-index: -1;
        }

        .signup-btn:hover {
            box-shadow: 0 6px 20px rgba(251, 37, 29, 0.4);
            transform: translateY(-2px);
        }

        .alert-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .alert-overlay.show {
            display: flex;
        }

        .alert-box {
            background: #fff;
            border-radius: 12px;
            padding: 40px 30px;
            text-align: center;
            max-width: 320px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .alert-box h2 {
            color: #1a1a2e;
            font-size: 18px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .alert-btn {
            background: #fb251d;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
            width: 100%;
        }

        .alert-btn:hover {
            background: #d91c14;
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 768px) {
            .back-btn {
                width: 50px;
                height: 50px;
            }

            .back-btn svg {
                width: 24px;
                height: 24px;
            }

            .signup-container {
                max-width: 100%;
                padding: 15px;
                overflow: visible;
            }

            .signup-form {
                padding: 20px;
                background: rgba(255, 255, 255, 0.1);
                overflow: visible;
            }

            .form-group {
                overflow: visible;
            }

            .custom-dropdown {
                overflow: visible;
                z-index: 1000;
            }

            .dropdown-menu {
                z-index: 10000 !important;
                position: fixed !important;
                max-height: 250px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                overflow: hidden;
            }

            .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            .logo-section {
                margin-bottom: 20px;
            }

            .logo-section img {
                width: 100px;
                height: 100px;
            }

            .logo-section h1 {
                font-size: 22px;
                margin-bottom: 15px;
                padding: 12px 24px;
            }

            .form-row {
                gap: 8px;
                margin-bottom: 10px;
            }

            .form-group input,
            .form-group select {
                padding: 11px 12px;
                height: 44px;
                font-size: 15px;
            }

            .dropdown-select {
                height: 44px;
                padding: 11px 12px;
                padding-right: 40px;
                font-size: 15px;
                overflow: visible;
            }

            .dropdown-item {
                padding: 14px 12px;
                font-size: 14px;
                background: #ffffff;
            }

            .signup-btn {
                height: 46px;
                font-size: 15px;
                margin-top: 12px;
            }

            .password-requirements {
                padding: 10px;
                margin-bottom: 10px;
            }

            .requirements-title {
                font-size: 10px;
                margin-bottom: 6px;
            }

            .requirements-list li {
                font-size: 11px;
                padding: 2px 0;
            }

            .drop-area {
                padding: 20px 15px;
                margin-bottom: 10px;
            }

            .upload-icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .upload-text {
                font-size: 12px;
                margin-bottom: 6px;
            }

            .browse-btn {
                padding: 6px 16px;
                font-size: 12px;
            }

            .alert-box {
                padding: 30px 20px;
                max-width: 90%;
            }

            .modal-content {
                max-width: 95vw;
                max-height: 85vh;
            }

            .modal-close {
                width: 35px;
                height: 35px;
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .back-btn {
                width: 48px;
                height: 48px;
                top: 15px;
                left: 15px;
            }

            .back-btn svg {
                width: 22px;
                height: 22px;
            }

            .signup-container {
                padding: 12px;
                margin-top: 15px;
                overflow: visible;
            }

            .signup-form {
                padding: 15px;
                overflow: visible;
            }

            .form-group {
                overflow: visible;
            }

            .custom-dropdown {
                overflow: visible;
                z-index: 1000;
            }

            .dropdown-menu {
                z-index: 10000 !important;
                position: fixed !important;
                max-height: 200px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                overflow: hidden;
            }

            .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }

            .logo-section {
                margin-bottom: 15px;
            }

            .logo-section img {
                width: 85px;
                height: 85px;
            }

            .logo-section h1 {
                font-size: 19px;
                margin-bottom: 12px;
                padding: 10px 20px;
            }

            .form-row {
                gap: 6px;
                margin-bottom: 8px;
            }

            .form-group {
                margin-bottom: 10px;
            }

            .form-group label {
                font-size: 11px;
                margin-bottom: 4px;
            }

            .form-group input,
            .form-group select {
                padding: 10px 11px;
                height: 42px;
                font-size: 14px;
                border-radius: 6px;
            }

            .dropdown-select {
                height: 42px;
                padding: 10px 11px;
                padding-right: 38px;
                font-size: 14px;
                border-radius: 6px;
            }

            .dropdown-menu {
                max-height: 280px;
            }

            .dropdown-item {
                padding: 10px 11px;
                font-size: 12px;
            }

            .signup-btn {
                height: 44px;
                font-size: 14px;
                margin-top: 10px;
                padding: 10px 14px;
            }

            .password-strength-container {
                gap: 6px;
                margin-top: 3px;
                margin-bottom: 6px;
            }

            .password-strength-text {
                font-size: 10px;
                min-width: 45px;
            }

            .alert-box {
                padding: 25px 18px;
                max-width: 85%;
            }

            .alert-box h2 {
                font-size: 16px;
            }

            .alert-icon {
                font-size: 40px;
            }

            .modal-content {
                max-width: 90vw;
                max-height: 80vh;
            }

            .modal-close {
                width: 32px;
                height: 32px;
                font-size: 18px;
            }
        }

        @media (max-width: 375px) {
            .back-btn {
                width: 46px;
                height: 46px;
            }

            .back-btn svg {
                width: 20px;
                height: 20px;
            }

            .signup-form {
                padding: 12px;
            }

            .logo-section {
                margin-bottom: 12px;
            }

            .logo-section img {
                width: 80px;
                height: 80px;
            }

            .logo-section h1 {
                font-size: 17px;
                margin-bottom: 10px;
                padding: 8px 16px;
            }
            }

            .form-group input,
            .form-group select {
                height: 40px;
                font-size: 13px;
            }

            .dropdown-select {
                height: 40px;
                padding: 9px 10px;
                padding-right: 36px;
                font-size: 13px;
            }

            .dropdown-menu {
                max-height: 250px;
            }

            .dropdown-item {
                padding: 9px 10px;
                font-size: 11px;
            }

            .signup-btn {
                height: 42px;
                font-size: 13px;
            }

            .modal-content {
                max-width: 92vw;
                max-height: 75vh;
            }

            .modal-close {
                width: 30px;
                height: 30px;
                font-size: 16px;
                top: 5px;
                right: 5px;
            }
        }

        .custom-dropdown {
            position: relative;
            display: block;
            width: 100%;
            overflow: visible;
            z-index: 100;
        }

        .custom-dropdown.active {
            z-index: 1001;
        }

        .dropdown-select {
            width: 100%;
            padding: 12px 14px;
            padding-right: 45px;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid #fb251d;
            border-radius: 8px;
            color: #333;
            font-size: 15px;
            height: 46px;
            cursor: pointer;
            appearance: none;
            user-select: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fb251d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            overflow: visible;
            box-sizing: border-box;
        }

        .dropdown-select:hover {
            border-color: #d91c14;
            box-shadow: 0 2px 8px rgba(251, 37, 29, 0.15);
        }

        .dropdown-select:focus {
            outline: none;
            border-color: #fb251d;
            background-color: rgba(255, 255, 255, 1);
        }

        #genderSelected {
            display: block;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: auto;
            background: #ffffff;
            border: 2px solid #fb251d;
            border-radius: 8px;
            max-height: 350px;
            overflow-x: hidden;
            overflow-y: auto;
            z-index: 10000;
            display: none;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            margin-top: 5px;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s, visibility 0.2s;
            box-sizing: border-box;
            width: 100%;
        }

        .dropdown-menu.show {
            display: block;
            visibility: visible;
            opacity: 1;
        }

        .dropdown-item {
            padding: 14px 16px;
            color: #333;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
            background: #ffffff;
            box-sizing: border-box;
            overflow: hidden;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background-color: #ffe8e5;
            color: #fb251d;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <button class="back-btn" onclick="goBack()" title="Back">
        <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
    </button>

    <div class="signup-container">
        <div class="logo-section">
            <h1>TEACHER SIGN UP</h1>
        </div>

        <form class="signup-form" method="POST" enctype="multipart/form-data" onsubmit="validateFormSubmission(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="John" required>
                </div>
                <div class="form-group">
                    <label for="middle_name">Middle Initial</label>
                    <input type="text" id="middle_name" name="middle_name" placeholder="D">
                </div>
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" placeholder="David" required>
            </div>

            <div class="form-group">
                <label for="gender">Gender</label>
                <div class="custom-dropdown">
                    <div class="dropdown-select" id="genderDropdownBtn" onclick="toggleGenderDropdown()">
                        <span id="genderSelected">Select your gender</span>
                    </div>
                    <div class="dropdown-menu" id="genderDropdownMenu">
                        <div class="dropdown-item" onclick="selectGender('male', event)">Male</div>
                        <div class="dropdown-item" onclick="selectGender('female', event)">Female</div>
                    </div>
                    <input type="hidden" id="gender" name="gender" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <input type="text" id="email" name="email_username" placeholder="" required>
                    <span class="email-suffix">@gmail.com</span>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="password-toggle" onclick="togglePassword()" id="eyeIcon">
                        <svg id="openEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg id="closedEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <div class="password-strength-container">
                    <div class="password-strength-bar-bg">
                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                    </div>
                    <span class="password-strength-text" id="passwordStrengthText"></span>
                </div>
                <div class="password-requirements">
                    <div class="requirements-title">Password must contain:</div>
                    <ul class="requirements-list">
                        <li id="req-length"><span class="requirement-icon">✓</span> At least 8 characters</li>
                        <li id="req-lowercase"><span class="requirement-icon">✓</span> Lowercase letter (a-z)</li>
                        <li id="req-uppercase"><span class="requirement-icon">✓</span> Uppercase letter (A-Z)</li>
                        <li id="req-number"><span class="requirement-icon">✓</span> Number (0-9)</li>
                        <li id="req-special"><span class="requirement-icon">✓</span> Special character (@_#*)</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="photo">Profile Picture</label>
                <div id="drop-area" class="drop-area">
                    <div class="upload-icon">📷</div>
                    <div class="upload-text">Upload your photo</div>
                    <div class="or-text">OR</div>
                    <label for="photo" class="browse-btn">BROWSE</label>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                    <div class="max-size">Maximum size 2MB</div>
                    <div id="preview-container"></div>
                </div>
            </div>

            <!-- Hidden input to store password strength value -->
            <input type="hidden" id="passwordStrengthValue" name="passwordStrengthValue" value="0">

            <button type="submit" class="signup-btn">SIGN UP</button>
        </form>
    </div>

    <!-- Error Alert -->
    <div class="alert-overlay" id="errorAlert">
        <div class="alert-box">
            <div class="alert-icon">❌</div>
            <h2>This email already exists. Please try another.</h2>
            <button class="alert-btn" onclick="closeAlert('errorAlert')">OK</button>
        </div>
    </div>

    <!-- Success Alert -->
    <div class="alert-overlay" id="successAlert">
        <div class="alert-box">
            <div class="alert-icon">✓</div>
            <h2>Sign up successful! Redirecting...</h2>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div class="image-modal" id="imageModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeImageModal()">&times;</button>
            <img id="modalImage" src="" alt="Preview">
        </div>
    </div>

    <script>
        function goBack() {
            window.location.href = 'frontpage.php';
        }

        function showCustomAlert(message = '') {
            document.getElementById('errorAlert').classList.add('show');
        }

        function closeAlert(alertId) {
            document.getElementById(alertId).classList.remove('show');
        }

        function showSuccessAlert(userType = 'teacher') {
            document.getElementById('successAlert').classList.add('show');
            
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1500);
        }

        function togglePassword() {
            const input = document.getElementById('password');
            const openEye = document.getElementById('openEye');
            const closedEye = document.getElementById('closedEye');
            
            if (input.type === 'password') {
                input.type = 'text';
                openEye.style.display = 'none';
                closedEye.style.display = 'block';
            } else {
                input.type = 'password';
                openEye.style.display = 'block';
                closedEye.style.display = 'none';
            }
        }

        function checkPasswordStrength(password) {
            const bar = document.getElementById('passwordStrengthBar');
            const text = document.getElementById('passwordStrengthText');
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasLower = /[a-z]/.test(password);
            const hasUpper = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(password);
            
            // Update requirement visual indicators
            updateRequirement('req-length', hasLength);
            updateRequirement('req-lowercase', hasLower);
            updateRequirement('req-uppercase', hasUpper);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-special', hasSpecial);
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength++;
            if (hasLower) strength++;
            if (hasUpper) strength++;
            if (hasNumber) strength++;
            if (hasSpecial) strength++;
            
            bar.className = 'password-strength-bar';
            text.textContent = '';
            
            if (password.length === 0) {
                bar.style.width = '0%';
            } else if (strength <= 2) {
                bar.classList.add('weak');
                text.textContent = 'Weak';
            } else if (strength <= 3) {
                bar.classList.add('medium');
                text.textContent = 'Medium';
            } else {
                bar.classList.add('strong');
                text.textContent = 'Strong';
            }
            
            // Store strength value for form validation
            document.getElementById('passwordStrengthValue').value = strength;
            return strength;
        }

        // Gender Dropdown Functions
        function toggleGenderDropdown() {
            const menu = document.getElementById('genderDropdownMenu');
            const btn = document.getElementById('genderDropdownBtn');
            
            if (!menu || !btn) return;
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                if (m !== menu) {
                    m.classList.remove('show');
                }
            });
            
            // Toggle the menu
            const isOpen = menu.classList.contains('show');
            
            if (isOpen) {
                menu.classList.remove('show');
            } else {
                menu.classList.add('show');
                // Position the dropdown
                positionDropdown(menu, btn);
            }
        }

        function positionDropdown(menu, btn) {
            if (window.innerWidth <= 768) {
                const rect = btn.getBoundingClientRect();
                const menuWidth = rect.width;
                
                menu.style.position = 'fixed';
                menu.style.top = (rect.bottom + 5) + 'px';
                menu.style.left = rect.left + 'px';
                menu.style.right = 'auto';
                menu.style.width = menuWidth + 'px';
                menu.style.maxWidth = (window.innerWidth - 40) + 'px';
            } else {
                menu.style.position = 'absolute';
                menu.style.width = '';
                menu.style.maxWidth = '';
            }
        }

        function selectGender(value, e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!value) return;
            
            const genderDisplay = value.charAt(0).toUpperCase() + value.slice(1);
            document.getElementById('gender').value = value;
            document.getElementById('genderSelected').textContent = genderDisplay;
            
            // Close the dropdown
            const menu = document.getElementById('genderDropdownMenu');
            if (menu) {
                menu.classList.remove('show');
                menu.style.position = '';
                menu.style.width = '';
            }
        }

        // Form submission validation
        function validateFormSubmission(event) {
            const gender = document.getElementById('gender').value;
            const passwordStrength = document.getElementById('passwordStrengthValue').value;
            
            // Check if gender is selected
            if (!gender) {
                alert('⚠️ GENDER REQUIRED:\n\nPlease select your gender from the dropdown.');
                event.preventDefault();
                return false;
            }
            
            // Check password strength (must be strong)
            if (passwordStrength < 4) {
                alert('⚠️ PASSWORD STRENGTH REQUIRED:\n\nYour password must be STRONG.\n\nPlease make sure your password has at least 8 characters and includes uppercase, lowercase, numbers, and special characters.');
                event.preventDefault();
                return false;
            }
            
            // All validations passed, allow form submission
            return true;
        }

        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            if (met) {
                element.classList.add('met');
            } else {
                element.classList.remove('met');
            }
        }

        // File upload functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropArea = document.getElementById('drop-area');
            const fileInput = document.getElementById('photo');
            const previewContainer = document.getElementById('preview-container');
            const browseLabel = document.querySelector('.browse-btn');
            const maxSizeLabel = document.querySelector('.max-size');
            const uploadText = document.querySelector('.upload-text');
            const uploadIcon = document.querySelector('.upload-icon');
            const orText = document.querySelector('.or-text');

            // Drag and drop
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropArea.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropArea.classList.remove('dragover');
                });
            });

            dropArea.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    showFilePreview();
                }
            });

            fileInput.addEventListener('change', showFilePreview);

            function showFilePreview() {
                if (fileInput.files && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    const objectUrl = URL.createObjectURL(file);
                    
                    // Hide original elements
                    browseLabel.style.display = 'none';
                    maxSizeLabel.style.display = 'none';
                    uploadText.style.display = 'none';
                    uploadIcon.style.display = 'none';
                    orText.style.display = 'none';
                    
                    // Show preview
                    previewContainer.innerHTML = '';
                    const img = document.createElement('img');
                    img.className = 'preview-img';
                    img.src = objectUrl;
                    img.onclick = () => openImageModal(objectUrl);
                    img.title = 'Click to view full image';
                    previewContainer.appendChild(img);
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'remove-btn';
                    removeBtn.textContent = 'Remove';
                    removeBtn.onclick = (e) => {
                        e.preventDefault();
                        fileInput.value = '';
                        previewContainer.innerHTML = '';
                        browseLabel.style.display = 'inline-block';
                        maxSizeLabel.style.display = 'block';
                        uploadText.style.display = 'block';
                        uploadIcon.style.display = 'block';
                        orText.style.display = 'block';
                        URL.revokeObjectURL(objectUrl);
                    };
                    previewContainer.appendChild(removeBtn);
                }
            }
        });

        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            modal.classList.add('show');
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('show');
        }

        // Close modal when clicking outside the content
        document.getElementById('imageModal').addEventListener('click', (e) => {
            if (e.target.id === 'imageModal') {
                closeImageModal();
            }
        });

        // Close gender dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const genderMenu = document.getElementById('genderDropdownMenu');
            const genderBtn = document.getElementById('genderDropdownBtn');
            
            if (genderMenu && genderBtn) {
                const genderDropdown = genderBtn.closest('.custom-dropdown');
                // If clicking outside the dropdown container and menu is open, close it
                if (genderDropdown && !genderDropdown.contains(e.target) && !genderMenu.contains(e.target) && genderMenu.classList.contains('show')) {
                    genderMenu.classList.remove('show');
                    genderMenu.style.position = '';
                    genderMenu.style.width = '';
                }
            }
        });

        // Handle window resize to reposition dropdown on mobile
        window.addEventListener('resize', () => {
            const genderMenu = document.getElementById('genderDropdownMenu');
            const genderBtn = document.getElementById('genderDropdownBtn');
            
            if (genderMenu && genderBtn && genderMenu.classList.contains('show')) {
                positionDropdown(genderMenu, genderBtn);
            }
        });
    </script>
</body>
</html>
