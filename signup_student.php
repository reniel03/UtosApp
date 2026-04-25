<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = htmlspecialchars(trim($_POST['first_name']), ENT_QUOTES, 'UTF-8');
    $middleName = htmlspecialchars(trim($_POST['middle_name']), ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars(trim($_POST['last_name']), ENT_QUOTES, 'UTF-8');
    $gender = htmlspecialchars(trim($_POST['gender']), ENT_QUOTES, 'UTF-8');
    $email = trim($_POST['email']);
    
    // Auto-format email: if no @, add @gmail.com
    if (strpos($email, '@') === false) {
        $email = $email . '@gmail.com';
    }
    
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $studentId = htmlspecialchars(trim($_POST['student_id']), ENT_QUOTES, 'UTF-8');
    $yearLevel = htmlspecialchars(trim($_POST['year_level']), ENT_QUOTES, 'UTF-8');
    $course = htmlspecialchars(trim($_POST['course']), ENT_QUOTES, 'UTF-8');
    $password = $_POST['password'];

    $photoPath = null;
    $photo = $_FILES['photo'];
    
    // Only process photo if one was uploaded
    if ($photo && $photo['size'] > 0) {
        $uploadDir = 'uploads/profiles/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $photoPath = $uploadDir . basename($photo['name']);
        if (!move_uploaded_file($photo['tmp_name'], $photoPath)) {
            echo "<script>alert('Error uploading file. Please try again.'); window.location.href = 'signup_student.php';</script>";
            exit;
        }
    }

    // Handle attachment upload (COM - Certificate of Matriculation)
    $attachmentPath = null;
    $attachment = $_FILES['attachment'] ?? null;
    
    if ($attachment && $attachment['size'] > 0 && $attachment['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $attachmentPath = $uploadDir . basename($attachment['name']);
        if (!move_uploaded_file($attachment['tmp_name'], $attachmentPath)) {
            echo "<script>alert('Error uploading COM file. Please try again.'); window.location.href = 'signup_student.php';</script>";
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

    // Initialize tables
    $conn = $db;
    include 'auto_init_tables.php';

    // Ensure attachment column exists in students table
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) AFTER photo");
    
    // Ensure gender column exists in students table
    $db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS gender VARCHAR(50) AFTER last_name");

    $stmt = $db->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { showCustomAlert('Email already exists'); });</script>";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $db->prepare("INSERT INTO students (first_name, middle_name, last_name, gender, email, password, student_id, year_level, course, photo, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param('sssssssssss', $firstName, $middleName, $lastName, $gender, $email, $hashedPassword, $studentId, $yearLevel, $course, $photoPath, $attachmentPath);

        if ($insertStmt->execute()) {
            $_SESSION['user_role'] = 'student';
            $_SESSION['first_name'] = $firstName;
            $_SESSION['email'] = $email;
            $_SESSION['photo'] = $photoPath;
            $_SESSION['attachment'] = $attachmentPath;
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showSuccessAlert('student'); });</script>";
        } else {
            echo "<script>alert('Error during signup'); window.location.href = 'signup_student.php';</script>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>UtosApp - Student Sign Up</title>
    <script async src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            min-height: 100%;
            touch-action: manipulation;
            -webkit-user-scalable: no;
            user-scalable: no;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            color: #000;
            overflow-x: hidden;
            overflow-y: auto;
            padding-bottom: 10px;
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
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s, opacity 0.3s, visibility 0.3s;
            z-index: 100;
            opacity: 1;
            visibility: visible;
        }

        .back-btn.hidden {
            opacity: 0;
            visibility: hidden;
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
            margin-top: 80px;
            overflow: visible;
            position: relative;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
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
            box-shadow: none;
        }

        .signup-form {
            margin-top: 20px;
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            border: none;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: visible;
            position: relative;
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

        label[for="attachment"] {
            display: block;
            font-size: 12px;
            color: #1a1a1a;
            margin-bottom: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .input-wrapper input {
            padding-right: 110px;
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

        .custom-dropdown {
            position: relative;
            display: block;
            width: 100%;
            overflow: visible;
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
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fb251d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
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

        .dropdown-search-container {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .dropdown-search {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #fb251d;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.3s;
        }

        .dropdown-search:focus {
            border-color: #ff6b4a;
            box-shadow: 0 0 0 3px rgba(251, 37, 29, 0.1);
        }

        .dropdown-group {
            padding: 0;
        }

        .dropdown-group-label {
            padding: 10px 14px;
            font-weight: 700;
            background: #f8f9fa;
            color: #fb251d;
            font-size: 11px;
            border-bottom: 1px solid #e9ecef;
            cursor: default;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dropdown-item {
            padding: 12px 14px;
            color: #333;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 1px solid #f5f5f5;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background-color: #ffe8e5;
            color: #fb251d;
        }

        .dropdown-item.hidden {
            display: none;
        }

        .dropdown-group.no-items {
            display: none;
        }

        .scan-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            transition: background 0.3s ease;
            margin: 12px auto 0;
            display: none;
        }

        .scan-btn:hover {
            background: #0056b3;
        }

        .scan-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .scan-btn.scanned {
            background: #28a745;
        }

        .scan-btn.scanned:hover {
            background: #218838;
        }

        .scan-btn.scan-failed {
            background: #dc3545;
        }

        .scan-btn.scan-failed:hover {
            background: #c82333;
        }

        .file-upload-container {
            width: 100%;
            margin-bottom: 15px;
        }

        .file-upload-area {
            border: 2.5px dashed #ccc;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f9f9f9;
        }

        .file-upload-area:hover {
            border-color: #fb251d;
            background: #f0f0f0;
        }

        .file-upload-area.dragover {
            border-color: #fb251d;
            background: #ffe8e5;
        }

        .file-upload-area.has-image {
            border-color: #66bb6a;
            background: #f0fef0;
        }

        .file-upload-area.has-image .upload-icon,
        .file-upload-area.has-image .upload-title,
        .file-upload-area.has-image .upload-subtitle,
        .file-upload-area.has-image .upload-divider,
        .file-upload-area.has-image .upload-limit,
        .file-upload-area.has-image .browse-btn {
            display: none;
        }

        .upload-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .upload-title {
            font-size: 14px;
            font-weight: 600;
            color: #000;
            margin-bottom: 4px;
        }

        .upload-subtitle {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .upload-divider {
            font-size: 12px;
            color: #999;
            margin: 10px 0;
            display: none;
        }

        .browse-btn {
            background: #fb251d;
            color: white !important;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
            display: inline-block;
            margin: 8px 0;
            min-width: 120px;
        }

        .file-upload-area .browse-btn {
            width: 100%;
            box-sizing: border-box;
        }

        .browse-btn:hover {
            background: #d91c14;
        }

        .upload-limit {
            font-size: 11px;
            color: #999;
            margin: 8px 0;
        }
        }

        .file-name {
            font-size: 13px;
            color: #28a745;
            margin-top: 10px;
            font-weight: 600;
            display: none !important;
        }

        .file-upload-area.has-image .file-name {
            display: none !important;
        }

        .preview-image {
            max-width: 100px;
            max-height: 100px;
            margin: 10px auto 0;
            border-radius: 6px;
            cursor: pointer;
            object-fit: cover;
            transition: transform 0.3s ease;
            display: block;
            border: 1px solid #ddd;
        }

        .preview-image:hover {
            transform: scale(1.05);
        }

        .file-upload-area.has-image .scan-btn {
            display: block;
        }

        .remove-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.3s;
            margin-top: 8px;
            display: none !important;
        }

        .drop-area.has-image #photoRemoveBtn,
        .file-upload-area.has-image #attachmentRemoveBtn {
            display: inline-block !important;
        }

        .drop-area.has-image .upload-icon,
        .drop-area.has-image .upload-text,
        .drop-area.has-image .or-text,
        .drop-area.has-image .max-size,
        .drop-area.has-image .browse-btn {
            display: none;
        }

        .remove-btn:hover {
            background: #e63939;
        }

        .file-input-hidden {
            display: none;
        }

        #scannedTextContainer {
            display: none;
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        #scannedTextContainer label {
            font-size: 13px;
            font-weight: 600;
            color: #000;
            margin-bottom: 8px;
            display: block;
        }

        #scannedTextOutput {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            background: white;
            min-height: 80px;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.95em;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: #333;
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
            background: #e0e0e0;
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
                margin-top: 70px;
            }

            .signup-form {
                padding: 20px;
                background: #ffffff;
            }

            .logo-section {
                margin-bottom: 20px;
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
            }

            .dropdown-menu {
                max-height: 300px;
            }

            .dropdown-item {
                padding: 11px 12px;
                font-size: 13px;
            }

            .dropdown-group-label {
                padding: 9px 12px;
                font-size: 10px;
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

            .file-upload-area {
                padding: 20px 15px;
                margin-bottom: 12px;
            }

            .upload-icon {
                font-size: 28px;
                margin-bottom: 8px;
            }

            .upload-title {
                font-size: 13px;
            }

            .upload-subtitle {
                font-size: 11px;
            }

            .file-name {
                font-size: 12px;
            }

            .scan-btn {
                padding: 9px 18px;
                font-size: 0.9em;
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
                margin-top: 60px;
            }

            .signup-form {
                padding: 15px;
            }

            .logo-section {
                margin-bottom: 15px;
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

            .dropdown-group-label {
                padding: 8px 11px;
                font-size: 10px;
            }

            .dropdown-search {
                font-size: 13px;
                padding: 9px 11px;
            }

            .file-upload-area {
                padding: 18px 12px;
                margin-bottom: 10px;
            }

            .upload-icon {
                font-size: 26px;
                margin-bottom: 6px;
            }

            .upload-title {
                font-size: 12px;
                margin-bottom: 3px;
            }

            .upload-subtitle {
                font-size: 10px;
                margin-bottom: 8px;
            }

            .file-name {
                font-size: 11px;
            }

            .scan-btn {
                padding: 8px 16px;
                font-size: 0.85em;
                margin-top: 10px;
            }

            #scannedTextContainer {
                margin-top: 15px;
                padding: 12px;
            }

            #scannedTextOutput {
                min-height: 60px;
                max-height: 150px;
                font-size: 0.9em;
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

            .logo-section h1 {
                font-size: 17px;
                margin-bottom: 10px;
                padding: 8px 16px;
            }

            .form-group input,
            .form-group select {
                height: 40px;
                font-size: 13px;
            }

            .dropdown-select {
                height: 40px;
                font-size: 13px;
                padding: 9px 10px;
                padding-right: 36px;
            }

            .dropdown-item {
                font-size: 12px;
                padding: 9px 10px;
            }

            .dropdown-group-label {
                font-size: 9px;
                padding: 7px 10px;
            }

            .dropdown-search {
                font-size: 12px;
            }

            .file-upload-area {
                padding: 16px 10px;
                margin-bottom: 8px;
            }

            .upload-icon {
                font-size: 24px;
                margin-bottom: 5px;
            }

            .upload-title {
                font-size: 11px;
                margin-bottom: 2px;
            }

            .upload-subtitle {
                font-size: 9px;
                margin-bottom: 6px;
            }

            .browse-btn {
                padding: 6px 14px;
                font-size: 11px;
            }

            .file-name {
                font-size: 10px;
                margin-top: 8px;
            }

            .scan-btn {
                padding: 7px 14px;
                font-size: 0.8em;
                margin-top: 8px;
            }

            .remove-btn {
                padding: 6px 14px;
                font-size: 11px;
            }

            .preview-image {
                max-width: 80px;
                max-height: 80px;
                margin: 8px auto 0;
            }

            #scannedTextContainer {
                margin-top: 12px;
                padding: 10px;
            }

            #scannedTextOutput {
                min-height: 50px;
                max-height: 120px;
                font-size: 0.85em;
                padding: 10px;
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
            <h1>STUDENT SIGN UP</h1>
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
                    <input type="text" id="email" name="email" placeholder="" required>
                    <span class="email-suffix">@gmail.com</span>
                </div>
            </div>

            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" placeholder="e.g., 2024-00001" required>
            </div>

            <div class="form-group">
                <label for="year_level">Year Level</label>
                <div class="custom-dropdown">
                    <div class="dropdown-select" id="yearLevelDropdownBtn" onclick="toggleYearLevelDropdown()">
                        <span id="yearLevelSelected">Select Year Level</span>
                    </div>
                    <div class="dropdown-menu" id="yearLevelDropdownMenu">
                        <div class="dropdown-item" onclick="selectYearLevel('1st Year', event)">1st Year</div>
                        <div class="dropdown-item" onclick="selectYearLevel('2nd Year', event)">2nd Year</div>
                        <div class="dropdown-item" onclick="selectYearLevel('3rd Year', event)">3rd Year</div>
                        <div class="dropdown-item" onclick="selectYearLevel('4th Year', event)">4th Year</div>
                    </div>
                    <input type="hidden" id="year_level" name="year_level" required>
                </div>
            </div>

            <div class="form-group">
                <label for="course">Course</label>
                <div class="custom-dropdown">
                    <div class="dropdown-select" id="courseDropdownBtn" onclick="toggleCourseDropdown()">
                        <span id="courseSelected">Select Course</span>
                    </div>
                    <div class="dropdown-menu" id="courseDropdownMenu">
                        <div class="dropdown-search-container">
                            <input type="text" id="courseSearchInput" class="dropdown-search" placeholder="Search course..." onclick="event.stopPropagation()">
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">BUSINESS ADMINISTRATION DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN ACCOUNTANCY', event)">BACHELOR OF SCIENCE IN ACCOUNTANCY</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN ACCOUNTING INFORMATION SYSTEM', event)">BACHELOR OF SCIENCE IN ACCOUNTING INFORMATION SYSTEM</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN ACCOUNTING TECHNOLOGY', event)">BACHELOR OF SCIENCE IN ACCOUNTING TECHNOLOGY</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION', event)">BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">ENGINEERING DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN CIVIL ENGINEERING', event)">BACHELOR OF SCIENCE IN CIVIL ENGINEERING</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">CRIMINOLOGY DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN CRIMINOLOGY', event)">BACHELOR OF SCIENCE IN CRIMINOLOGY</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">COMMUNICATION ARTS DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('AB ENGLISH LANGUAGE - MAJOR IN MASS COMMUNICATION', event)">AB ENGLISH LANGUAGE - MAJOR IN MASS COMMUNICATION</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">HOSPITALITY MANAGEMENT DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN HOTEL AND RESTAURANT MANAGEMENT', event)">BACHELOR OF SCIENCE IN HOTEL AND RESTAURANT MANAGEMENT</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT', event)">BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT</div>
                            <div class="dropdown-item" onclick="selectCourse('HOTEL AND RESTAURANT SERVICES', event)">HOTEL AND RESTAURANT SERVICES</div>
                            <div class="dropdown-item" onclick="selectCourse('FOOD AND BEVERAGE SERVICES NC II', event)">FOOD AND BEVERAGE SERVICES NC II</div>
                            <div class="dropdown-item" onclick="selectCourse('HOUSEKEEPING NC II', event)">HOUSEKEEPING NC II</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">INFORMATION TECHNOLOGY DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN INFORMATION SYSTEMS', event)">BACHELOR OF SCIENCE IN INFORMATION SYSTEMS</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', event)">BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN PROGRAMMING NC IV', event)">BACHELOR OF SCIENCE IN PROGRAMMING NC IV</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">PSYCHOLOGY DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN PSYCHOLOGY', event)">BACHELOR OF SCIENCE IN PSYCHOLOGY</div>
                        </div>
                        <div class="dropdown-group">
                            <div class="dropdown-group-label">TEACHER EDUCATION DIVISION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN ELEMENTARY EDUCATION', event)">BACHELOR OF SCIENCE IN ELEMENTARY EDUCATION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN SECONDARY EDUCATION', event)">BACHELOR OF SCIENCE IN SECONDARY EDUCATION</div>
                            <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN PHYSICAL EDUCATION', event)">BACHELOR OF SCIENCE IN PHYSICAL EDUCATION</div>
                            <div class="dropdown-item" onclick="selectCourse('CERTIFICATE IN TEACHING PROFESSION', event)">CERTIFICATE IN TEACHING PROFESSION</div>
                        </div>
                    </div>
                    <input type="hidden" id="course" name="course" required>
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
                    <button type="button" class="remove-btn" id="photoRemoveBtn" onclick="removePhotoFile(event)">Remove</button>
                </div>
            </div>

            <label for="attachment">Attach you Certificate of Matriculation (COM)</label>
            <div class="file-upload-container">
                <div class="file-upload-area" id="attachmentUploadArea" onclick="if(!this.classList.contains('has-image')) { document.getElementById('attachment').click(); }">
                    <div class="upload-icon">📄</div>
                    <div class="upload-title">Upload your file here</div>
                    <div class="upload-divider">OR</div>
                    <button type="button" class="browse-btn" onclick="event.preventDefault(); if(!document.getElementById('attachmentUploadArea').classList.contains('has-image')) { document.getElementById('attachment').click(); }">BROWSE</button>
                    <div class="file-name" id="attachmentFileName"></div>
                    <button type="button" class="scan-btn" id="scanBtn" onclick="scanAttachmentImage(event)">📄SCAN</button>
                    <button type="button" class="remove-btn" id="attachmentRemoveBtn" onclick="removeAttachmentFile(event)">Remove</button>
                </div>
                <input type="file" id="attachment" name="attachment" class="file-input-hidden" accept="image/*">
            </div>

            <!-- Hidden input to store scanned text for validation -->
            <input type="hidden" id="scannedTextData" name="scannedTextData" value="">

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

        function showSuccessAlert(userType = 'student') {
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

        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            if (met) {
                element.classList.add('met');
            } else {
                element.classList.remove('met');
            }
        }

        // Form submission validation
        function validateFormSubmission(event) {
            // Check required fields
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const gender = document.getElementById('gender').value.trim();
            const email = document.getElementById('email').value.trim();
            const studentId = document.getElementById('student_id').value.trim();
            const yearLevel = document.getElementById('year_level').value.trim();
            const course = document.getElementById('course').value.trim();
            const password = document.getElementById('password').value.trim();
            
            // Validate required fields
            if (!firstName) {
                alert('❌ First name is required.');
                event.preventDefault();
                return false;
            }
            
            if (!lastName) {
                alert('❌ Last name is required.');
                event.preventDefault();
                return false;
            }
            
            if (!gender) {
                alert('❌ Gender is required.');
                event.preventDefault();
                return false;
            }
            
            if (!email) {
                alert('❌ Email is required.');
                event.preventDefault();
                return false;
            }
            
            // Format email: if no @, add @gmail.com
            let formattedEmail = email;
            if (!email.includes('@')) {
                formattedEmail = email + '@gmail.com';
                document.getElementById('email').value = formattedEmail;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(formattedEmail)) {
                alert('❌ Please enter a valid email address.');
                event.preventDefault();
                return false;
            }
            
            if (!studentId) {
                alert('❌ Student ID is required.');
                event.preventDefault();
                return false;
            }
            
            if (!yearLevel) {
                alert('❌ Year level is required.');
                event.preventDefault();
                return false;
            }
            
            if (!course) {
                alert('❌ Course is required.');
                event.preventDefault();
                return false;
            }
            
            if (!password) {
                alert('❌ Password is required.');
                event.preventDefault();
                return false;
            }
            
            const passwordStrength = document.getElementById('passwordStrengthValue').value;
            
            // Check password strength (must be strong)
            if (passwordStrength < 4) {
                alert('⚠️ PASSWORD STRENGTH REQUIRED:\n\nYour password must be STRONG.\n\nPlease make sure your password has at least 8 characters and includes uppercase, lowercase, numbers, and special characters.');
                event.preventDefault();
                return false;
            }
            
            // Check if Certificate of Moral was scanned
            const scannedText = document.getElementById('scannedTextData').value.trim();
            
            if (!scannedText) {
                alert('❌ Please scan your Certificate of Matriculation (COM) before signing up.');
                event.preventDefault();
                return false;
            }
            
            // Validate scanned COM data against form details
            const mismatches = validateScannedCOMData(scannedText);
            
            if (mismatches.length > 0) {
                // Show alert with mismatched details and prevent submission
                alert('❌ VALIDATION FAILED:\n\nThe following details in your Certificate of Matriculation do NOT match your entered information:\n\n' + 
                      mismatches.join('\n') + 
                      '\n\nPlease correct your details or verify your document.');
                event.preventDefault();
                return false;
            }
            
            // All validations passed, allow form submission
            return true;
        }

        // File upload functionality - Define variables globally
        const dropArea = document.getElementById('drop-area');
        const fileInput = document.getElementById('photo');
        const previewContainer = document.getElementById('preview-container');
        const browseLabel = document.querySelector('.browse-btn');
        const maxSizeLabel = document.querySelector('.max-size');
        const uploadText = document.querySelector('.upload-text');
        const uploadIcon = document.querySelector('.upload-icon');
        const orText = document.querySelector('.or-text');

        document.addEventListener('DOMContentLoaded', function() {
            // Email input formatting - show formatted email in real-time
            const emailInput = document.getElementById('email');
            const emailSuffix = document.querySelector('.email-suffix');
            
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                if (email && !email.includes('@')) {
                    emailSuffix.textContent = '@gmail.com';
                    emailSuffix.style.display = 'block';
                } else if (email.includes('@')) {
                    emailSuffix.style.display = 'none';
                } else {
                    emailSuffix.style.display = 'block';
                }
            });
            
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
                    
                    // Add has-image class to show remove button
                    dropArea.classList.add('has-image');
                    
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

        // Hide back button on scroll
        let lastScrollTop = 0;
        const backBtn = document.querySelector('.back-btn');

        window.addEventListener('scroll', function() {
            let currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            
            if (currentScroll > lastScrollTop) {
                // Scrolling DOWN
                backBtn.classList.add('hidden');
            } else {
                // Scrolling UP
                backBtn.classList.remove('hidden');
            }
            
            lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
        });

        // Course dropdown functionality
        function toggleYearLevelDropdown() {
            const menu = document.getElementById('yearLevelDropdownMenu');
            const courseMenu = document.getElementById('courseDropdownMenu');
            const yearLevelDropdown = menu.closest('.custom-dropdown');
            const courseDropdown = courseMenu.closest('.custom-dropdown');
            
            // Close course dropdown if open
            if (courseMenu.classList.contains('show')) {
                courseMenu.classList.remove('show');
                courseDropdown.classList.remove('active');
            }
            
            menu.classList.toggle('show');
            yearLevelDropdown.classList.toggle('active');
        }

        function selectYearLevel(value, e) {
            e.stopPropagation();
            document.getElementById('year_level').value = value;
            document.getElementById('yearLevelSelected').textContent = value;
            const yearLevelDropdown = document.getElementById('yearLevelDropdownMenu').closest('.custom-dropdown');
            document.getElementById('yearLevelDropdownMenu').classList.remove('show');
            yearLevelDropdown.classList.remove('active');
        }

        function toggleCourseDropdown() {
            const menu = document.getElementById('courseDropdownMenu');
            const yearMenu = document.getElementById('yearLevelDropdownMenu');
            const courseDropdown = menu.closest('.custom-dropdown');
            const yearLevelDropdown = yearMenu.closest('.custom-dropdown');
            const searchInput = document.getElementById('courseSearchInput');
            
            // Close year level dropdown if open
            if (yearMenu.classList.contains('show')) {
                yearMenu.classList.remove('show');
                yearLevelDropdown.classList.remove('active');
            }
            
            if (menu.classList.contains('show')) {
                menu.classList.remove('show');
                courseDropdown.classList.remove('active');
                searchInput.value = '';
                clearCourseSearch();
            } else {
                menu.classList.add('show');
                courseDropdown.classList.add('active');
                // Focus on search input when dropdown opens
                setTimeout(() => {
                    searchInput.focus();
                }, 0);
            }
        }

        function positionDropdown(button, menu) {
            // Deprecated - using absolute positioning instead
        }

        function selectCourse(value, e) {
            e.stopPropagation();
            document.getElementById('course').value = value;
            document.getElementById('courseSelected').textContent = value;
            const courseDropdown = document.getElementById('courseDropdownMenu').closest('.custom-dropdown');
            document.getElementById('courseDropdownMenu').classList.remove('show');
            courseDropdown.classList.remove('active');
            document.getElementById('courseSearchInput').value = '';
            clearCourseSearch();
        }

        // Search functionality for courses
        function searchCourses() {
            const searchInput = document.getElementById('courseSearchInput');
            const searchTerm = searchInput.value.toLowerCase();
            const dropdownGroups = document.querySelectorAll('.dropdown-group');

            if (searchTerm === '') {
                clearCourseSearch();
                return;
            }

            dropdownGroups.forEach(group => {
                const items = group.querySelectorAll('.dropdown-item');
                let hasVisibleItems = false;

                items.forEach(item => {
                    const itemText = item.textContent.toLowerCase();
                    if (itemText.includes(searchTerm)) {
                        item.classList.remove('hidden');
                        hasVisibleItems = true;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                // Hide or show group based on whether it has visible items
                if (hasVisibleItems) {
                    group.classList.remove('no-items');
                } else {
                    group.classList.add('no-items');
                }
            });
        }

        function clearCourseSearch() {
            const dropdownGroups = document.querySelectorAll('.dropdown-group');
            dropdownGroups.forEach(group => {
                group.classList.remove('no-items');
                const items = group.querySelectorAll('.dropdown-item');
                items.forEach(item => {
                    item.classList.remove('hidden');
                });
            });
        }

        // Add search event listener
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('courseSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', searchCourses);
            }
        });

        // Add scroll event to reposition dropdowns - removed for absolute positioning
        // Dropdowns now use absolute positioning and will follow naturally

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
                positionGenderDropdown(menu, btn);
            }
        }

        function positionGenderDropdown(menu, btn) {
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
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const genderMenu = document.getElementById('genderDropdownMenu');
            const genderBtn = document.getElementById('genderDropdownBtn');
            const yearBtn = document.getElementById('yearLevelDropdownBtn');
            const courseBtn = document.getElementById('courseDropdownBtn');
            const yearMenu = document.getElementById('yearLevelDropdownMenu');
            const courseMenu = document.getElementById('courseDropdownMenu');
            const genderDropdown = genderMenu ? genderMenu.closest('.custom-dropdown') : null;
            const yearLevelDropdown = yearMenu.closest('.custom-dropdown');
            const courseDropdown = courseMenu.closest('.custom-dropdown');
            
            // Close gender dropdown if clicking outside it
            if (genderMenu && genderMenu.classList.contains('show') && genderBtn && !genderBtn.contains(e.target) && !genderMenu.contains(e.target)) {
                genderMenu.classList.remove('show');
                if (genderDropdown) {
                    genderDropdown.classList.remove('active');
                }
            }
            
            // Close year level dropdown if clicking outside it
            if (yearMenu && yearMenu.classList.contains('show') && !yearBtn.contains(e.target) && !yearMenu.contains(e.target)) {
                yearMenu.classList.remove('show');
                yearLevelDropdown.classList.remove('active');
            }
            
            // Close course dropdown if clicking outside it
            if (courseMenu && courseMenu.classList.contains('show') && !courseBtn.contains(e.target) && !courseMenu.contains(e.target)) {
                courseMenu.classList.remove('show');
                courseDropdown.classList.remove('active');
                document.getElementById('courseSearchInput').value = '';
                clearCourseSearch();
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

        // Handle window resize to reposition gender dropdown on mobile
        window.addEventListener('resize', () => {
            const genderMenu = document.getElementById('genderDropdownMenu');
            const genderBtn = document.getElementById('genderDropdownBtn');
            
            if (genderMenu && genderBtn && genderMenu.classList.contains('show')) {
                positionGenderDropdown(genderMenu, genderBtn);
            }
        });

        // Handle file inputs
        const attachmentInput = document.getElementById('attachment');
        const attachmentUploadArea = document.getElementById('attachmentUploadArea');
        const attachmentFileName = document.getElementById('attachmentFileName');

        attachmentInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Add has-image class immediately to prevent file picker from reopening
                attachmentUploadArea.classList.add('has-image');
                
                attachmentFileName.textContent = '✓ ' + this.files[0].name;
                
                // Show preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview if any
                    const existingPreview = attachmentUploadArea.querySelector('.preview-image');
                    if (existingPreview) {
                        existingPreview.remove();
                    }
                    
                    // Create new preview image
                    const previewImg = document.createElement('img');
                    previewImg.src = e.target.result;
                    previewImg.className = 'preview-image';
                    previewImg.style.cursor = 'pointer';
                    previewImg.onclick = function(event) {
                        event.stopPropagation();
                        openImageModal(this.src);
                    };
                    attachmentUploadArea.appendChild(previewImg);
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Drag and drop for attachment
        attachmentUploadArea.addEventListener('dragover', function(e) {
            if (this.classList.contains('has-image')) return;
            e.preventDefault();
            this.classList.add('dragover');
        });

        attachmentUploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });

        attachmentUploadArea.addEventListener('drop', function(e) {
            if (this.classList.contains('has-image')) return;
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                attachmentInput.files = e.dataTransfer.files;
                
                // Add has-image class immediately to prevent file picker from reopening
                attachmentUploadArea.classList.add('has-image');
                
                attachmentFileName.textContent = '✓ ' + e.dataTransfer.files[0].name;
                
                // Show preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview if any
                    const existingPreview = attachmentUploadArea.querySelector('.preview-image');
                    if (existingPreview) {
                        existingPreview.remove();
                    }
                    
                    // Create new preview image
                    const previewImg = document.createElement('img');
                    previewImg.src = e.target.result;
                    previewImg.className = 'preview-image';
                    previewImg.onclick = function(event) {
                        event.stopPropagation();
                        openImageModal(this.src);
                    };
                    attachmentUploadArea.appendChild(previewImg);
                };
                reader.readAsDataURL(e.dataTransfer.files[0]);
            }
        });

        // Remove attachment file function
        function removePhotoFile(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Clear file input
            fileInput.value = '';
            
            // Remove preview image
            previewContainer.innerHTML = '';
            
            // Remove has-image class to show upload elements
            dropArea.classList.remove('has-image');
            
            // Show original elements again
            browseLabel.style.display = 'inline-block';
            maxSizeLabel.style.display = 'block';
            uploadText.style.display = 'block';
            uploadIcon.style.display = 'block';
            orText.style.display = 'block';
        }

        function removeAttachmentFile(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Clear file input
            attachmentInput.value = '';
            
            // Remove preview image
            const previewImg = attachmentUploadArea.querySelector('.preview-image');
            if (previewImg) {
                previewImg.remove();
            }
            
            // Clear file name
            attachmentFileName.textContent = '';
            
            // Remove has-image class to show upload elements
            attachmentUploadArea.classList.remove('has-image');
            
            // Reset scan button
            const scanBtn = document.getElementById('scanBtn');
            scanBtn.textContent = 'SCAN';
            scanBtn.classList.remove('scanned');
            document.getElementById('scannedTextData').value = '';
            document.getElementById('scannedTextContainer').style.display = 'none';
        }

        // Validate scanned COM data against student details
        function validateScannedCOMData(scannedText) {
            const firstName = document.getElementById('first_name').value.trim();
            const middleName = document.getElementById('middle_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const studentId = document.getElementById('student_id').value.trim();
            const yearLevel = document.getElementById('year_level').value.trim();
            const course = document.getElementById('course').value.trim();
            
            const scannedLower = scannedText.toLowerCase();
            const mismatches = [];
            
            // Check first name
            if (firstName && !scannedLower.includes(firstName.toLowerCase())) {
                mismatches.push(`First name: "${firstName}"`);
            }
            
            // Check last name
            if (lastName && !scannedLower.includes(lastName.toLowerCase())) {
                mismatches.push(`Last name: "${lastName}"`);
            }
            
            // Check middle name if provided
            if (middleName && !scannedLower.includes(middleName.toLowerCase())) {
                mismatches.push(`Middle name: "${middleName}"`);
            }
            
            // Check student ID
            if (studentId && !scannedLower.includes(studentId.toLowerCase())) {
                mismatches.push(`Student ID: "${studentId}"`);
            }
            
            // Check year level if provided
            if (yearLevel && !scannedLower.includes(yearLevel.toLowerCase())) {
                mismatches.push(`Year level: "${yearLevel}"`);
            }
            
            // Check course if provided
            if (course && !scannedLower.includes(course.toLowerCase())) {
                mismatches.push(`Course: "${course}"`);
            }
            
            return mismatches;
        }

        // Scan attachment image function
        async function scanAttachmentImage(e) {
            e.preventDefault();
            const scanBtn = document.getElementById('scanBtn');
            
            if (!attachmentInput.files || !attachmentInput.files[0]) {
                alert('No file selected');
                return;
            }
            
            const file = attachmentInput.files[0];
            const reader = new FileReader();
            
            reader.onload = async function(e) {
                try {
                    scanBtn.disabled = true;
                    scanBtn.textContent = 'Scanning...';
                    
                    const { data: { text } } = await Tesseract.recognize(
                        e.target.result,
                        'eng',
                        { logger: m => console.log(m) }
                    );
                    
                    // Store scanned text for validation
                    document.getElementById('scannedTextData').value = text;
                    
                    // Just show scan complete - no validation during scan
                    scanBtn.textContent = '✓ SCAN COMPLETE';
                    scanBtn.classList.add('scanned');
                    scanBtn.classList.remove('scan-failed');
                    
                    scanBtn.disabled = false;
                } catch (error) {
                    console.error('OCR Error:', error);
                    scanBtn.textContent = 'SCAN';
                    scanBtn.disabled = false;
                    alert('Error scanning image: ' + error.message);
                }
            };
            
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
