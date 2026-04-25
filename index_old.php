<?php
// This tells ngrok to skip the warning page for your app
header("ngrok-skip-browser-warning: any-value");

session_start();

// If not a login POST request and not coming from login button, show frontpage.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['login'])) {
    include 'frontpage.php';
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

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

    // First check if it's a teacher
    $stmt = $db->prepare("SELECT password, first_name, last_name, middle_name, department, photo FROM teachers WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hashedPassword = $row['password'];

        if (password_verify($password, $hashedPassword)) {
            // Set session variables
            $_SESSION['email'] = $email;
            $_SESSION['user_role'] = 'teacher';
            $_SESSION['first_name'] = $row['first_name'];
            $_SESSION['last_name'] = $row['last_name'];
            $_SESSION['middle_name'] = $row['middle_name'];
            $_SESSION['department'] = $row['department'];
            $_SESSION['photo'] = $row['photo'];
            
            // Show success alert and redirect
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showSuccessAlert('teacher'); });</script>";
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showCustomAlert(); });</script>";
        }
    } else {
        // Check if it's a student
        $stmt = $db->prepare("SELECT password, first_name, last_name, middle_name, course, photo FROM students WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $hashedPassword = $row['password'];

            if (password_verify($password, $hashedPassword)) {
                // Set session variables for student
                $_SESSION['email'] = $email;
                $_SESSION['user_role'] = 'student';
                $_SESSION['first_name'] = $row['first_name'];
                $_SESSION['last_name'] = $row['last_name'];
                $_SESSION['middle_name'] = $row['middle_name'];
                $_SESSION['course'] = $row['course'];
                $_SESSION['photo'] = $row['photo'];
                
                // Show success alert and redirect
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showSuccessAlert('student'); });</script>";
            } else {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { showCustomAlert(); });</script>";
            }
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { showCustomAlert(); });</script>";
        }
    }

    $stmt->close();
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
    <title>UtosApp</title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#000000">
    <style>
        html {
            scroll-behavior: smooth;
            overflow-y: scroll;
        }

        body {
            overflow-x: hidden;
        }

        /* Custom Alert Popup */
        @keyframes alertPopup {
            0% {
                transform: translate(-50%, -70%);
                opacity: 0;
            }
            100% {
                transform: translate(-50%, -50%);
                opacity: 1;
            }
        }

        .custom-alert {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 60px 80px;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.25);
            z-index: 1000;
            text-align: center;
            min-width: 550px;
            animation: alertPopup 0.3s ease-out forwards;
        }

        .custom-alert h2 {
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 32px;
            margin-bottom: 30px;
            line-height: 1.4;
            font-weight: normal;
        }

        .alert-icon {
            margin-bottom: 30px;
            animation: iconBounce 0.5s ease-out;
            display: inline-block;
        }

        .alert-icon svg {
            width: 110px;
            height: 110px;
        }

        @keyframes iconBounce {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .alert-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 999;
        }

        .alert-button {
            background: #0066ff;
            color: white;
            border: none;
            padding: 15px 45px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 20px;
            transition: background 0.3s;
            font-weight: 500;
            min-width: 120px;
        }

        .alert-button:hover {
            background: #0052cc;
        }

        .home-btn {
            background: #fb251d;
            color: #fff;
            border: none;
            border-radius: 30px;
            padding: 20px 45px;
            font-size: 1.5em;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            letter-spacing: 1px;
            box-shadow: 0 4px 0 #ffd6d6, 0 6px 14px rgba(251,37,29,0.10);
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 3;
        }

        .home-btn:hover {
            background: #d91c14;
            color: #fff;
            box-shadow: 0 12px 0 #ffd6d6, 0 12px 32px rgba(251,37,29,0.18);
        }

        .container {
            font-size: 1.2em; /* Increased font size */
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .front-box {
            width: 800px; /* Increased width */
            padding: 40px; /* Adjusted padding */
        }

        .login-form input {
            font-size: 1.2em; /* Larger input text */
        }

        .login-form input[type="password"] {
            font-size: 1.2em; /* Match font size with phone number or email */
        }

        /* Hide native password reveal icon */
        input[type="password"]::-webkit-credentials-auto-fill-button,
        input[type="password"]::-webkit-outer-spin-button,
        input[type="password"]::-webkit-inner-spin-button {
            display: none !important;
        }

        input[type="password"]::-ms-reveal {
            display: none !important;
        }

        /* Password Input Group with Toggle */
        .password-input-group {
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }

        .password-field {
            width: 100% !important;
            padding-right: 45px !important;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-80%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fb251d;
            transition: color 0.2s;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #d91c14;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
        }

        .login-btn {
            font-size: 1.4em; /* Larger button text */
        }

        .social-btn {
            font-size: 1.3em; /* Larger social button text */
        }

        .utosapp-logo {
            max-width: 100%;
            object-fit: contain;
            display: block;
            margin: 0 auto 20px auto;
        }

        .frontbox-center {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .front-box.freeze-animation {
            animation: none !important;
        }

        body.alert-active .container {
            z-index: -1 !important;
        }

        body.alert-active .frontbox-left {
            z-index: -1 !important;
        }

        body.alert-active .front-box {
            opacity: 0 !important;
            visibility: hidden !important;
            transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
        }

        /* Logo Zoom Overlay Smooth Transition */
        #logoZoomOverlay {
            background-color: #ffffff;
            opacity: 0;
            transition: opacity 0.5s ease-out;
        }

        #logoZoomOverlay.show {
            opacity: 1;
        }

    </style>
</head>
<body>
    <a href="frontpage.php" class="home-btn">HOME</a>
    <div class="container">
        <div class="front-box front-box-flex no-animation">
            <div class="frontbox-left">
                <img src="utosapp_logo_new.png" alt="UtosApp Logo" class="utosapp-logo" style="width: 350px; height: auto; margin-bottom: 20px;">
            </div>
            <div class="frontbox-right">
                <form class="login-form" method="POST">
                    <input type="email" name="email" placeholder="Phone number or email" class="input-field" required>
                    <div class="password-input-group">
                        <input type="password" name="password" placeholder="Password" class="input-field password-field" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this, event)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <button type="submit" class="login-btn">Log In</button>
                </form>
                <div class="divider-row">
                    <hr class="divider"><span class="divider-text" style="font-size: 1.3em; font-weight: bold;">OR</span><hr class="divider">
                </div>
                <div class="divider-row">
                    <a href="signup.php" class="login-btn" style="text-decoration: none;">Sign Up</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Alert -->
    <div class="alert-overlay" id="alertOverlay"></div>
    <div class="custom-alert" id="customAlert">
        <div class="alert-icon">
            <svg width="70" height="70" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" fill="#ff3333"/>
                <rect x="11" y="7" width="2" height="7" fill="white"/>
                <circle cx="12" cy="16" r="1" fill="white"/>
            </svg>
        </div>
        <h2>Invalid email or password. Please try again.</h2>
        <button class="alert-button" onclick="closeAlert()">OK</button>
    </div>

    <!-- Success Alert -->
    <div class="alert-overlay" id="successOverlay"></div>
    <div class="custom-alert" id="successAlert">
        <div class="alert-icon">
            <svg width="70" height="70" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" fill="#4CAF50"/>
                <path d="M9 12.5L11 14.5L15.5 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Login successful!</h2>
        <button class="alert-button" style="background: #4CAF50" onclick="closeSuccessAlert()">OK</button>
    </div>

    <!-- Loading Animation Screen -->
    <div id="loadingScreen" style="display: none;">
        <div class="loading-content">
            <div class="app-name" id="appNameText">UtosApp</div>
        </div>
        <div class="custom-cursor" id="customCursor"></div>
    </div>

    <!-- Logo Zoom Overlay -->
    <div id="logoZoomOverlay" style="display: none;">
        <div class="logo-zoom-container">
            <img src="utosapp_logo_new.png" alt="UtosApp Logo" class="logo-zoom" id="logoZoom">
        </div>
    </div>



    <style>
        #loadingScreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #fb251d;
            z-index: 9999;
            display: none;
            cursor: none;
        }

        .loading-content {
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .app-name {
            color: white;
            font-size: 6em;
            font-weight: bold;
            text-align: center;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            letter-spacing: 2px;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 99999;
        }

        .app-name.clicked {
            transform: scale(0.95);
        }

        .custom-cursor {
            width: 128px;
            height: 128px;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="white" d="M7,2L19,13.17L13.17,13.67L16.5,21L14.33,22L11.07,14.9L7,19L7,2Z"/></svg>');
            background-size: contain;
            position: fixed;
            pointer-events: none;
            z-index: 100000;
            opacity: 1;
            transform: translate(-50%, -50%);
            filter: drop-shadow(0 0 12px rgba(255, 255, 255, 0.8));
            transition: transform 0.3s ease;
        }

        @keyframes cursorMove {
            0% {
                opacity: 1;
                left: 90%;
                top: 90%;
            }
            60% {
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%) scale(1);
            }
            80% {
                transform: translate(-50%, -50%) scale(1.2);
            }
            100% {
                transform: translate(-50%, -50%) scale(1);
                left: 50%;
                top: 50%;
            }

        }

        /* Logo Zoom Overlay Styles */
        #logoZoomOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #ffffff;
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .logo-zoom-container {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .logo-zoom {
            width: 150px;
            height: 150px;
            animation: logoZoomEffect 2s ease-out forwards;
            object-fit: contain;
        }

        @keyframes logoZoomEffect {
            0% {
                width: 150px;
                height: 150px;
                opacity: 1;
                transform: scale(1);
            }
            100% {
                width: 150px;
                height: 150px;
                opacity: 0;
                transform: scale(30);
            }
        }

        @keyframes fadeOutOverlay {
            0% {
                opacity: 1;
            }
            100% {
                opacity: 0;
            }
        }

        #logoZoomOverlay.fade-out {
            animation: fadeOutOverlay 0.3s ease-out forwards;
        }

        body.transitioning {
            opacity: 0;
            transition: opacity 0.5s ease-out;
            background-color: #ffffff;
        }

        html {
            background-color: #ffffff;
        }

        /* ===== MOBILE RESPONSIVE STYLES ===== */
        @media (max-width: 768px) {
            body {
                padding: 0;
                margin: 0;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .container {
                min-height: auto;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
                font-size: 1em;
                width: 100%;
            }

            .front-box {
                width: 100% !important;
                min-width: 100%;
                padding: 30px 20px !important;
                max-width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 20px;
            }

            .frontbox-left {
                width: 100%;
                text-align: center;
            }

            .frontbox-right {
                width: 100%;
            }

            .utosapp-logo {
                width: 180px !important;
                height: auto !important;
                margin-bottom: 20px;
            }

            .login-form {
                width: 100%;
            }

            .login-form input,
            .input-field {
                font-size: 1em !important;
                padding: 14px 15px !important;
                height: 50px;
                border-radius: 8px;
                margin-bottom: 15px;
            }

            .password-input-group {
                margin-bottom: 15px;
            }

            .password-field {
                padding-right: 45px !important;
            }

            .password-toggle {
                right: 12px;
                top: 50%;
                transform: translateY(-60%);
            }

            .password-toggle svg {
                width: 18px;
                height: 18px;
            }

            .login-btn {
                font-size: 1em !important;
                padding: 14px 30px !important;
                width: 100%;
                border-radius: 8px;
                margin-bottom: 15px;
                font-weight: bold;
            }

            .divider-row {
                margin: 15px 0;
            }

            .divider-row .divider {
                border-color: #ddd;
                margin: 0 10px;
            }

            .divider-text {
                font-size: 1em !important;
            }

            .home-btn {
                padding: 12px 24px !important;
                font-size: 0.95em !important;
                top: 15px !important;
                right: 15px !important;
                border-radius: 25px;
            }

            .custom-alert {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 30px 20px;
                border-radius: 12px;
                box-shadow: 0 5px 25px rgba(0, 0, 0, 0.25);
                z-index: 1000;
                text-align: center;
                min-width: auto;
                width: 90%;
                max-width: 350px;
                animation: alertPopup 0.3s ease-out forwards;
            }

            .custom-alert h2 {
                font-size: 1.1em !important;
                margin-bottom: 20px;
                line-height: 1.4;
                color: #333;
            }

            .alert-icon svg {
                width: 60px !important;
                height: 60px !important;
            }

            .alert-button {
                padding: 12px 30px;
                font-size: 0.95em;
                min-width: 100px;
                border-radius: 6px;
            }
        }

        @media (max-width: 480px) {
            body {
                background-size: contain;
                background-attachment: scroll;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .container {
                min-height: auto;
                padding: 15px;
                font-size: 0.95em;
                width: 100%;
                flex-direction: column;
                justify-content: flex-start;
                padding-top: 80px;
            }

            .front-box {
                width: 100% !important;
                padding: 20px 15px !important;
                gap: 15px;
            }

            .utosapp-logo {
                width: 140px !important;
                margin-bottom: 15px;
            }

            .login-form input,
            .input-field {
                font-size: 1em !important;
                padding: 12px 12px !important;
                height: 48px;
                margin-bottom: 12px;
                border-radius: 6px;
            }

            .password-toggle {
                right: 10px;
            }

            .login-btn {
                font-size: 0.95em !important;
                padding: 12px 25px !important;
                margin-bottom: 12px;
                border-radius: 6px;
            }

            .home-btn {
                padding: 10px 20px !important;
                font-size: 0.85em !important;
                top: 12px !important;
                right: 12px !important;
                letter-spacing: 0.5px;
            }

            .custom-alert {
                padding: 25px 15px;
                width: 85%;
                max-width: 300px;
            }

            .custom-alert h2 {
                font-size: 1em !important;
                margin-bottom: 15px;
                word-wrap: break-word;
            }

            .alert-icon svg {
                width: 50px !important;
                height: 50px !important;
                margin-bottom: 15px;
            }

            .alert-button {
                padding: 10px 25px;
                font-size: 0.9em;
                width: 100%;
            }

            .divider-text {
                font-size: 0.9em !important;
            }
        }

        @media (max-width: 375px) {
            body {
                overflow-y: auto;
                overflow-x: hidden;
            }

            .container {
                padding: 10px;
                min-height: auto;
                padding-top: 70px;
            }

            .front-box {
                padding: 15px 10px !important;
            }

            .utosapp-logo {
                width: 120px !important;
                margin-bottom: 12px;
            }

            .login-form input,
            .input-field {
                font-size: 16px !important;
                height: 45px;
                margin-bottom: 10px;
            }

            .login-btn {
                font-size: 0.9em !important;
                padding: 10px 20px !important;
                margin-bottom: 10px;
            }

            .custom-alert {
                padding: 20px 12px;
                width: 92%;
            }

            .home-btn {
                padding: 8px 16px !important;
                font-size: 0.8em !important;
                border-radius: 20px;
            }
        }
    </style>

    <script>
        function togglePasswordVisibility(btn, event) {
            event.preventDefault();
            const input = btn.parentElement.querySelector('.password-field');
            const svg = btn.querySelector('svg');
            
            if (input.type === 'password') {
                input.type = 'text';
                // Change to closed eye icon
                svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                input.type = 'password';
                // Change to open eye icon
                svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }

        function showCustomAlert() {
            document.getElementById('alertOverlay').style.display = 'block';
            document.getElementById('customAlert').style.display = 'block';
        }

        function closeAlert() {
            document.getElementById('alertOverlay').style.display = 'none';
            document.getElementById('customAlert').style.display = 'none';
        }

        function showSuccessAlert(userType = 'teacher') {
            // First show success alert
            document.getElementById('successOverlay').style.display = 'block';
            document.getElementById('successAlert').style.display = 'block';
            
            // Add alert-active class to push form behind
            document.body.classList.add('alert-active');
            
            // Freeze the front box animation
            const frontBox = document.querySelector('.front-box');
            frontBox.classList.add('freeze-animation');

            // After 1.5 seconds, hide success alert and show loading screen
            setTimeout(function() {
                // Hide success alert
                document.getElementById('successOverlay').style.display = 'none';
                document.getElementById('successAlert').style.display = 'none';
                
                // Remove alert-active class
                document.body.classList.remove('alert-active');
                
                // Show loading screen and start animation
                handleSuccessfulLogin(userType);
            }, 1500);
        }

        // Function to handle successful login
        function handleSuccessfulLogin(userType) {
            const loadingScreen = document.getElementById('loadingScreen');
            const cursor = document.getElementById('customCursor');
            const appName = document.getElementById('appNameText');
            const logoZoomOverlay = document.getElementById('logoZoomOverlay');
            const frontBox = document.querySelector('.front-box');
            const container = document.querySelector('.container');
            
            loadingScreen.style.display = 'block';
            
            // Hide front box completely
            container.style.display = 'none';
            frontBox.classList.add('freeze-animation');
            
            // Start cursor animation
            cursor.style.animation = 'cursorMove 1.5s forwards';
            
            // Add click effect after cursor reaches the text
            setTimeout(() => {
                appName.classList.add('clicked');
                cursor.style.transform = 'translate(-50%, -50%) scale(0.8)';
                
                // Remove click effect and show logo zoom overlay
                setTimeout(() => {
                    appName.classList.remove('clicked');
                    cursor.style.transform = 'translate(-50%, -50%) scale(1)';
                    
                    // Hide loading screen and show logo zoom overlay with smooth transition
                    loadingScreen.style.display = 'none';
                    logoZoomOverlay.style.display = 'flex';
                    
                    // Trigger smooth fade-in effect
                    setTimeout(() => {
                        logoZoomOverlay.classList.add('show');
                    }, 50);
                    
                    // After logo zoom animation completes, redirect
                    setTimeout(() => {
                        // Immediately hide the logo overlay and fade out the body
                        logoZoomOverlay.style.display = 'none';
                        document.body.classList.add('transitioning');
                        
                        // Redirect while fading out (no wait)
                        if (userType === 'student') {
                            window.location.href = 'student_task_page.php';
                        } else if (userType === 'teacher') {
                            window.location.href = 'teacher_task_page.php';
                        }
                    }, 2000);
                }, 300);
            }, 1200);
        }

        function closeSuccessAlert() {
            document.getElementById('successOverlay').style.display = 'none';
            document.getElementById('successAlert').style.display = 'none';
        }
    </script>
    <script>
        if ('serviceWorker' in navigator) {
          window.addEventListener('load', function() {
            navigator.serviceWorker.register('/sw.js');
          });
        }
    </script>
    <script>
        document.addEventListener("backbutton", onBackKeyDown, false);
        function onBackKeyDown(e) {
            e.preventDefault();
            window.history.back();
        }
    </script>
    
</body>
</html>
