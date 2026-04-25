<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>UtosApp - Sign Up</title>
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

        .signup-container {
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

        .signup-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 15px;
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
            margin-bottom: 12px;
        }

        .signup-btn:hover {
            background: #f0f0f0;
            border-color: #f0f0f0;
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

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 16px;
            border-radius: 6px;
            display: inline-block;
        }

        .login-link a:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.15);
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

            .signup-btn {
                padding: 12px 14px;
                height: 48px;
                font-size: 16px;
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
            }

            .logo-section {
                margin-bottom: 25px;
                margin-top: 50px;
            }

            .logo-section img {
                width: 100px;
                height: 100px;
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

            .signup-btn {
                padding: 11px 12px;
                height: 46px;
                font-size: 15px;
                margin-bottom: 10px;
                border-radius: 6px;
            }

            .signup-form {
                margin-top: 25px;
            }

            .divider {
                margin: 25px 0;
            }

            .login-link {
                margin-top: 15px;
            }

            .login-link a {
                font-size: 13px;
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
    </style>
</head>
<body>
    <button class="back-btn" onclick="goBack()" title="Back to Home">
        <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
    </button>

    <div class="signup-container">
        <div class="logo-section">
            <img src="utosapp_logo_new.png" alt="UtosApp Logo">
            <h1>UtosApp</h1>
            <p>Your all-in-one platform task assistant</p>
        </div>

        <form class="signup-form">
            <a href="signup_teacher.php" class="signup-btn">Sign Up as Teacher</a>
            <a href="signup_student.php" class="signup-btn">Sign Up as Student</a>
        </form>

        <div class="divider">
            <div class="divider-line"></div>
            <div class="divider-text">OR</div>
            <div class="divider-line"></div>
        </div>

        <div class="login-link">
            <p style="margin-bottom: 10px;">Already have an account?</p>
            <a href="index.php?login=1">Log in here</a>
        </div>
    </div>

    <script>
        function goBack() {
            window.location.href = 'frontpage.php';
        }
    </script>
</body>
</html>
