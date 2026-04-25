<?php
// Session already started in index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="description" content="UtosApp - Connect. Assign. Complete. All in UtosApp - Your all-in-one platform task assistant.">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="UtosApp">
    <link rel="apple-touch-icon" href="icon-192x192.png">
    <link rel="icon" type="image/x-icon" href="icon-192x192.png">
    <title>UtosApp</title>
    <style>
        @keyframes panIn {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(0);
            }
        }

        @keyframes panIn {
            0% {
                transform: translateY(-100%);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            0% {
                transform: translateY(50px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideInFromBottom {
            0% {
                transform: translateY(100%);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background-image: url('building-background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }

        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                rgba(255, 255, 255, 0.9),
                rgba(240, 248, 255, 0.7)
            );
            z-index: 1;
        }

        .header {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 30px 50px;
            width: 100%;
            animation: panIn 1s ease-out forwards;
            transform: translateX(-100%);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo {
            height: 1000px;
            background-color: transparent;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-icon {
            height: 260px;
        }

        .login-btn {
            background: #fb251d;
            color: #fff;
            border: none;
            border-radius: 30px;
            padding: 28px 60px;
            font-size: 1.8em;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            letter-spacing: 1px;
            box-shadow: 0 4px 0 #ffd6d6, 0 6px 14px rgba(251,37,29,0.10);
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            margin-top: 40px;
            z-index: 3;
        }

        .login-btn:hover {
            background: #d91c14;
            color: #fff;
            box-shadow: 0 12px 0 #ffd6d6, 0 12px 32px rgba(251,37,29,0.18);
        }

        .main-content {
            position: relative;
            z-index: 2;
            max-width: 1400px;
            margin: 60px auto;
            padding: 0;
            animation: fadeInUp 1.2s ease-out;
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            width: fit-content;
            min-height: 60vh;
            gap: 20px;
        }

        .main-heading {
            font-size: 5em;
            color: #fb251d;
            margin-bottom: 30px;
            line-height: 1.1;
            max-width: 1000px;
            text-align: left;
            font-weight: 900;
            word-spacing: normal;
            letter-spacing: normal;
        }

        .sub-heading {
            font-size: 2em;
            color: #0e0d0dff;
            margin-bottom: 0;
            font-style: italic;
            text-align: left;
            letter-spacing: 0.02em;
            font-weight: 900;
        }

        .tagline {
            font-size: 2em;
            color: #0a0909ff;
            text-align: left;
            letter-spacing: 0.02em;
            font-weight: 900;
            margin-top: 0;
            margin-bottom: 30px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #fb251d;
            color: white;
            padding: 10px 0;
            text-align: center;
            z-index: 2;
            animation: slideInFromBottom 1s ease-out;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
            width: 100%;
            padding: 0 30px;
            font-size: 1.8em;
        }

        .footer-content span {
            font-weight: bold;
        }

        .footer a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .terms-link {
            font-weight: bold;
        }

        .policy-link {
            font-weight: bold;
        }

        /* ====== MOBILE RESPONSIVE STYLES ====== */
        @media (max-width: 768px) {
            body {
                background-size: contain;
                background-repeat: no-repeat;
                background-attachment: scroll;
                background-position: center top;
            }

            html {
                font-size: 14px;
            }

            .overlay {
                background: linear-gradient(
                    rgba(255, 255, 255, 0.92),
                    rgba(240, 248, 255, 0.85)
                );
            }

            .header {
                padding: 20px 15px;
                flex-wrap: wrap;
                gap: 15px;
            }

            .logo-container {
                gap: 8px;
                flex: 1;
                min-width: 200px;
            }

            .logo {
                height: 300px;
            }

            .logo-text h1 {
                font-size: 1.4em;
                letter-spacing: 0.05em;
            }

            .logo-text p {
                font-size: 0.8em;
            }

            .login-btn {
                padding: 16px 35px;
                font-size: 1.3em;
                border-radius: 20px;
                white-space: nowrap;
            }

            .main-content {
                align-items: flex-start;
            }

            .container {
                padding: 30px 20px;
                text-align: center;
            }

            .main-heading {
                font-size: 2.8em;
                line-height: 1.2;
                margin-bottom: 15px;
                text-align: left;
                font-weight: 900;
            }

            .sub-heading {
                font-size: 1.8em;
                margin-bottom: 0;
                line-height: 1.4;
                text-align: left;
                font-weight: 900;
            }

            .tagline {
                font-size: 1.5em;
                text-align: left;
                font-weight: 900;
                margin-top: 0;
                margin-bottom: 30px;
            }

            .features {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px 10px;
            }

            .feature-card {
                padding: 20px;
                border-radius: 12px;
            }

            .feature-icon {
                font-size: 3em;
                margin-bottom: 10px;
            }

            .feature-title {
                font-size: 1.3em;
                margin-bottom: 10px;
            }

            .feature-description {
                font-size: 0.95em;
                line-height: 1.5;
            }

            .footer {
                padding: 20px 15px;
                gap: 15px;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }

            .footer-content {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 0 10px;
                font-size: 1.1em;
                text-align: center;
                max-width: 100%;
                gap: 20px;
                position: static;
                bottom: auto;
                left: auto;
                right: auto;
            }

            .terms-link {
                position: static;
                left: auto;
                font-weight: 900 !important;
                font-size: 1.8em;
            }

            .policy-link {
                position: static;
                right: auto;
                font-weight: 900 !important;
                font-size: 1.8em;
            }

            .footer-content span {
                font-weight: bold;
            }

            .footer a {
                margin: 8px 0;
                font-size: 1.8em;
                display: inline-block;
                font-weight: 900 !important;

            }
        }

        @media (max-width: 480px) {
            body {
                background-size: auto;
                background-repeat: repeat;
                background-attachment: scroll;
            }

            html {
                font-size: 12px;
            }

            .header {
                padding: 15px 10px;
            }

            .logo-container {
                gap: 6px;
                min-width: 160px;
            }

            .logo {
                height: 110px;
            }

            .logo-text h1 {
                font-size: 1.1em;
            }

            .logo-text p {
                font-size: 0.7em;
            }

            .login-btn {
                padding: 14px 28px;
                font-size: 1.2em;
                border-radius: 15px;
            }

            .main-content {
                align-items: flex-start;
                min-height: 50vh;
            }

            .container {
                padding: 25px 15px;
            }

            .main-heading {
                font-size: 2.2em;
                margin-bottom: 12px;
                text-align: left;
                font-weight: 900;
            }

            .sub-heading {
                font-size: 1.5em;
                margin-bottom: 0;
                text-align: left;
                font-weight: 900;
            }

            .tagline {
                font-size: 1.3em;
                text-align: left;
                font-weight: 900;
                margin-top: 0;
                margin-bottom: 12px;
            }

            .features {
                padding: 15px 8px;
                gap: 15px;
            }

            .feature-card {
                padding: 15px;
            }

            .feature-icon {
                font-size: 2.5em;
                margin-bottom: 8px;
            }

            .feature-title {
                font-size: 1.1em;
                margin-bottom: 8px;
            }

            .feature-description {
                font-size: 0.9em;
                line-height: 1.4;
            }

            .footer {
                padding: 10px 10px;
                justify-content: center;
                align-items: center;
            }

            .footer-content {
                gap: 8px;
                padding: 0 8px;
                font-size: 0.95em;
                max-width: 100%;
                justify-content: space-between;
                align-items: center;
            }

            .footer a {
                margin: 5px 0;
                font-size: 1.3em;
                font-weight: 900 !important;
            }
        }

        @media (max-width: 375px) {
            body {
                background-size: auto;
                background-repeat: repeat;
                background-attachment: scroll;
            }

            html {
                font-size: 11px;
            }

            .header {
                padding: 12px 8px;
            }

            .logo {
                height: 100px;
            }

            .logo-text h1 {
                font-size: 1em;
            }

            .logo-text p {
                font-size: 0.65em;
            }

            .login-btn {
                padding: 12px 25px;
                font-size: 1.1em;
            }

            .main-content {
                align-items: flex-start;
                min-height: 50vh;
            }

            .container {
                padding: 20px 12px;
            }

            .main-heading {
                font-size: 2em;
                text-align: left;
                font-weight: 900;
            }

            .sub-heading {
                font-size: 1.3em;
                text-align: left;
                font-weight: 900;
                margin-bottom: 0;
            }

            .tagline {
                font-size: 1.2em;
                text-align: left;
                font-weight: 900;
                margin-top: 0;
                margin-bottom: 12px;
            }

            .features {
                gap: 12px;
                padding: 12px 5px;
            }

            .feature-card {
                padding: 12px;
            }

            .feature-icon {
                font-size: 2em;
            }

            .feature-title {
                font-size: 1em;
            }

            .feature-description {
                font-size: 0.85em;
            }

            .footer {
                padding: 12px 8px;
                justify-content: center;
                align-items: center;
            }

            .footer-content {
                font-size: 0.9em;
                max-width: 100%;
                justify-content: space-between;
                align-items: center;
            }

            .footer a {
                font-size: 1.2em;
                font-weight: 900 !important;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .header {
                padding: 25px 35px;
            }

            .logo {
                height: 210px;
            }

            .logo-text h1 {
                font-size: 2em;
            }

            .login-btn {
                padding: 21px 49px;
                font-size: 1.5em;
            }

            .container {
                padding: 50px 30px;
            }

            .features {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
            }

            .main-heading {
                font-size: 3em;
                margin-bottom: 20px;
                font-weight: 900;
            }

            .sub-heading {
                font-size: 1.8em;
                margin-bottom: 0;
                font-weight: 900;
            }

            .feature-card {
                padding: 30px;
            }

            .footer-content {
                padding: 0 30px;
                max-width: 100%;
                justify-content: space-between;
                align-items: center;
            }

            .footer a {
                margin-left: 30px;
                font-weight: bold;
            }
        }
    </style>
    <link rel="manifest" href="/manifest.json">
    <style>
    </style>
</head>
<body>
    <div class="overlay"></div>
    
    <header class="header">
        <div class="logo-container">
            <img src="utosapp_logo_new.png" alt="UtosApp Logo" class="logo">
        </div>
    </header>

    <main class="main-content">
        <h1 class="main-heading">Task made easy.<br>One click, one UtosApp.</h1>
        <p class="sub-heading">Connect. Assign. Complete. All in UtosApp.<br><span style="font-style: italic;">Your all-in-one platform task assistant.</span></p>
        <a href="index.php?login=1" class="login-btn">LOGIN</a>

    </main>

    <footer class="footer">
        <div class="footer-content">
            <a href="#" class="terms-link">Terms and Conditions</a>
            <a href="#" class="policy-link">Privacy Policy</a>
        </div>
    </footer>

    <script>
    </script>
</body>
</html>

