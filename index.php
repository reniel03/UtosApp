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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="utosapp_logo_new.png">
    <title>UtosApp - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: radial-gradient(circle at 30% 50%, #ff0000, #ff4d4d, #f1efef, #ffb3b3);
            background-size: 200% 200%;
            animation: gradientShift 10s ease infinite;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
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

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
            margin-top: 20px;
        }

        .logo-section img {
            width: 140px;
            height: 140px;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            object-fit: contain;
        }

        .logo-section h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #fff;
        }

        .logo-section p {
            color: #fff;
            font-size: 14px;
            line-height: 1.5;
        }

        .login-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            color: #fff;
            margin-bottom: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: #333;
            font-size: 16px;
            height: 50px;
            transition: all 0.3s;
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group input:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #fb251d;
            outline: none;
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

        .login-btn {
            width: 100%;
            padding: 14px 16px;
            background: #fff;
            border: none;
            border-radius: 8px;
            color: #fb251d;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            height: 50px;
            transition: all 0.3s;
        }

        .login-btn:hover {
            background: #f0f0f0;
            box-shadow: 0 4px 12px rgba(251, 37, 29, 0.25);
        }

        .login-btn:active {
            background: #e8e8e8;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.3);
        }

        .divider-text {
            padding: 0 15px;
            color: #fff;
            font-size: 14px;
        }

        .signup-btn {
            width: 100%;
            padding: 14px 16px;
            background: #fff;
            border: 2px solid #fff;
            border-radius: 8px;
            color: #fb251d;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            height: 50px;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signup-btn:hover {
            background: #f0f0f0;
            border-color: #f0f0f0;
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .forgot-password a:hover {
            color: #f0f0f0;
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

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            margin: 20px auto;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #fb251d;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .alert-box.success-loading .alert-icon {
            display: none;
        }

        .alert-box.success-loading h2 {
            margin-top: 0;
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

            .login-container {
                max-width: 100%;
                padding: 15px;
            }

            .logo-section {
                margin-bottom: 30px;
                margin-top: 60px;
            }

            .logo-section img {
                width: 120px;
                height: 120px;
                padding: 8px;
                background: white;
            }

            .logo-section h1 {
                font-size: 24px;
            }

            .logo-section p {
                font-size: 13px;
            }

            .form-group input {
                padding: 12px 14px;
                height: 48px;
                font-size: 16px;
            }

            .login-btn,
            .signup-btn {
                height: 48px;
                font-size: 16px;
                padding: 12px 14px;
            }

            .alert-box {
                padding: 30px 20px;
                max-width: 90%;
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

            .login-container {
                padding: 12px;
            }

            .logo-section {
                margin-bottom: 25px;
                margin-top: 50px;
            }

            .logo-section img {
                width: 100px;
                height: 100px;
                padding: 8px;
                background: white;
            }

            .logo-section h1 {
                font-size: 22px;
            }

            .logo-section p {
                font-size: 12px;
            }

            .form-group {
                margin-bottom: 12px;
            }

            .form-group input {
                padding: 11px 12px;
                height: 46px;
                font-size: 16px;
                border-radius: 6px;
            }

            .login-btn,
            .signup-btn {
                height: 46px;
                font-size: 15px;
                margin-top: 18px;
                padding: 11px 12px;
                border-radius: 6px;
            }

            .login-form {
                margin-top: 25px;
            }

            .divider {
                margin: 25px 0;
            }

            .forgot-password {
                margin-top: 15px;
            }

            .forgot-password a {
                font-size: 13px;
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

            .logo-section {
                margin-top: 45px;
            }

            .logo-section img {
                width: 90px;
                height: 90px;
                padding: 8px;
                background: white;
            }

            .logo-section h1 {
                font-size: 20px;
            }
        }

        /* PWA Install Banner Styles */
        .pwa-install-banner {
            display: block;
            position: fixed;
            top: 80px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            max-width: 320px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
        }

        .pwa-install-banner.hide {
            display: none;
        }

        .pwa-banner-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pwa-banner-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .pwa-banner-icon {
            font-size: 24px;
        }

        .pwa-banner-text {
            flex: 1;
        }

        .pwa-banner-title {
            font-weight: 600;
            color: white;
            font-size: 16px;
            margin: 0;
        }

        .pwa-banner-description {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.9);
            margin: 4px 0 0 0;
        }

        .pwa-banner-buttons {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .pwa-install-btn {
            flex: 1;
            padding: 10px 16px;
            background-color: white;
            color: #667eea;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pwa-install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .pwa-install-btn:active {
            transform: translateY(0);
        }

        .pwa-close-btn {
            padding: 10px 16px;
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pwa-close-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        @media (max-width: 480px) {
            .pwa-install-banner {
                top: 70px;
                right: 10px;
                left: 10px;
                max-width: none;
            }

            .pwa-banner-buttons {
                gap: 8px;
            }

            .pwa-install-btn,
            .pwa-close-btn {
                padding: 12px 14px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- PWA Install Banner -->
    <div id="pwa-install-banner" class="pwa-install-banner">
        <div class="pwa-banner-content">
            <div class="pwa-banner-header">
                <div class="pwa-banner-icon">⬇️</div>
                <div class="pwa-banner-text">
                    <h3 class="pwa-banner-title">Install UtosApp</h3>
                    <p class="pwa-banner-description">Install this app for faster access and notifications.</p>
                </div>
            </div>
            <div class="pwa-banner-buttons">
                <button id="pwa-install-btn" class="pwa-install-btn">Install App</button>
                <button id="pwa-close-btn" class="pwa-close-btn">Later</button>
            </div>
        </div>
    </div>

    <button class="back-btn" onclick="goBack()" title="Back to Home">
        <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
    </button>

    <div class="login-container">
        <div class="logo-section">
            <img src="utosapp_logo_new.png" alt="UtosApp Logo">
            <h1>UtosApp</h1>
            <p>Your all-in-one platform task assistant</p>
        </div>

        <form class="login-form" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
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
            </div>

            <button type="submit" class="login-btn">Log In</button>
        </form>

        <div class="divider">
            <div class="divider-line"></div>
            <div class="divider-text">OR</div>
            <div class="divider-line"></div>
        </div>

        <a href="signup.php" class="signup-btn">Sign up</a>
    </div>

    <!-- Error Alert -->
    <div class="alert-overlay" id="errorAlert">
        <div class="alert-box">
            <div class="alert-icon">❌</div>
            <h2>Invalid email or password. Please try again.</h2>
            <button class="alert-btn" onclick="closeAlert('errorAlert')">OK</button>
        </div>
    </div>

    <!-- Success Alert -->
    <div class="alert-overlay" id="successAlert">
        <div class="alert-box success-loading">
            <div class="loading-spinner"></div>
            <h2>Login successful!</h2>
        </div>
    </div>

    <script>
        let deferredPrompt;

        // Register Service Worker for PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('sw.js').catch(err => console.log('SW registration failed:', err));
        }

        // Hide install banner
        function hideInstallBanner() {
            const banner = document.getElementById('pwa-install-banner');
            if (banner) {
                banner.classList.add('hide');
            }
        }

        // Capture the install prompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event for later use
            deferredPrompt = e;
        });

        // Install button click handler
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (deferredPrompt) {
                    // Show the install prompt
                    deferredPrompt.prompt();
                    // Wait for the user to respond to the prompt
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`User response to the install prompt: ${outcome}`);
                    // We've used the prompt, and can't use it again
                    deferredPrompt = null;
                    // Hide the banner
                    hideInstallBanner();
                } else {
                    hideInstallBanner();
                }
            });
        }

        // Close button click handler
        const closeBtn = document.getElementById('pwa-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                // Hide the banner when user clicks "Later"
                hideInstallBanner();
            });
        }

        // Hide banner when app is installed
        window.addEventListener('appinstalled', () => {
            console.log('UtosApp was installed');
            hideInstallBanner();
            deferredPrompt = null;
        });

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

        function showCustomAlert() {
            document.getElementById('errorAlert').classList.add('show');
        }

        function closeAlert(alertId) {
            document.getElementById(alertId).classList.remove('show');
        }

        function showSuccessAlert(userType = 'teacher') {
            document.getElementById('successAlert').classList.add('show');
            
            setTimeout(() => {
                if (userType === 'teacher') {
                    window.location.href = 'teacher_task_page.php';
                } else {
                    window.location.href = 'student_home.php';
                }
            }, 1500);
        }

        function goBack() {
            window.location.href = 'frontpage.php';
        }
    </script>
</body>
</html>
