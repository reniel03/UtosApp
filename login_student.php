<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>UtosApp</title>
    <link rel="stylesheet" href="style.css">
    <script async src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <style>
            html {
                scroll-behavior: smooth;
                overflow-y: scroll;
            }

            body {
                overflow-x: hidden;
            }

            .modal-box {
                width: 850px; /* Increased width */
                padding: 50px; /* Adjusted padding */
                border-radius: 12px; /* Rounded corners */
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); /* Subtle shadow */
                font-size: 1.2em; /* Increased font size */
                max-height: 90vh; /* Maximum height */
                overflow-y: auto; /* Enable vertical scrolling */
            }

            .login-form label {
                font-size: 1.3em; /* Smaller label text, matches screenshot */
                margin-bottom: 8px; /* Spacing between label and input */
                display: block; /* Ensure labels are above inputs */
                font-weight: 700; /* Bold text */
                color: #333; /* Darker color for better visibility */
            }

            .login-form input {
                width: 100%; /* Full width inputs */
                padding: 12px; /* Adjusted padding */
                margin-bottom: 16px; /* Spacing between inputs */
                border: 1px solid #ccc; /* Subtle border */
                border-radius: 8px; /* Rounded corners */
                font-size: 1.2em; /* Larger input text */
            }

            /* Form Row for 2-Column Layout */
            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 0;
            }

            .form-row .form-group {
                display: flex;
                flex-direction: column;
            }

            .form-row .form-group label {
                margin-bottom: 10px;
            }

            .form-row .form-group input {
                margin-bottom: 0;
            }

            .form-group {
                margin-bottom: 16px;
            }

            .form-group label {
                font-size: 1.3em;
                margin-bottom: 8px;
                display: block;
                font-weight: 700;
                color: #333;
            }

            .form-group input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-size: 1.2em;
            }

            .login-btn {
                width: 100%;
                padding: 16px 0;
                font-size: 1.3em;
                background: #ea2d2d;
                color: #fff;
                border: none;
                border-radius: 28px;
                font-weight: bold;
                box-shadow: 0 4px 0 #c82323, 0 2px 12px rgba(234,45,45,0.12);
                cursor: pointer;
                transition: background 0.2s, box-shadow 0.2s;
            }

            .login-btn:hover {
                background: #d32f2f;
                box-shadow: 0 2px 0 #a71d1d, 0 2px 12px rgba(211,47,47,0.18);
            }

            /* Custom File Upload Styles */
            .file-upload-container {
                margin-bottom: 16px;
                display: flex;
                justify-content: center;
            }

            .file-upload-area {
                border: 2px dashed #007bff;
                border-radius: 8px;
                padding: 40px 20px;
                text-align: center;
                background: #f8f9ff;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 85%;
                max-width: 500px;
                overflow: hidden;
                min-height: 200px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .file-upload-area.has-image {
                padding: 20px;
                min-height: auto;
            }

            .file-upload-area.has-image .upload-icon,
            .file-upload-area.has-image .upload-title,
            .file-upload-area.has-image .upload-subtitle,
            .file-upload-area.has-image .upload-divider,
            .file-upload-area.has-image .browse-btn,
            .file-upload-area.has-image .upload-limit,
            .file-upload-area.has-image .file-name {
                display: none;
            }

            .remove-btn {
                background: #ff4d4d;
                color: white;
                border: none;
                padding: 8px 20px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.95em;
                font-weight: 600;
                transition: background 0.3s ease;
                margin-top: 12px;
                display: none;
            }

            .remove-btn:hover {
                background: #e63939;
            }

            .file-upload-area.has-image .remove-btn {
                display: inline-block;
            }

            .file-upload-area:hover {
                border-color: #0056b3;
                background: #e7f1ff;
            }

            .file-upload-area.dragover {
                border-color: #0056b3;
                background: #e7f1ff;
            }

            .upload-icon {
                font-size: 3em;
                margin-bottom: 15px;
                color: #007bff;
            }

            .upload-title {
                font-size: 1.3em;
                font-weight: 700;
                color: #333;
                margin-bottom: 8px;
            }

            .upload-subtitle {
                font-size: 0.95em;
                color: #999;
                margin-bottom: 15px;
            }

            .upload-divider {
                font-size: 1em;
                color: #999;
                margin: 15px 0;
            }

            .browse-btn {
                background: white;
                color: #040404ff;
                border: 2px solid #007bff;
                padding: 10px 30px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 1em;
                font-weight: 600;
                transition: all 0.3s ease;
                margin-bottom: 15px;
            }

            .browse-btn:hover {
                background: #007bff;
                color: white;
            }

            .upload-limit {
                font-size: 0.9em;
                color: #999;
            }

            .file-input-hidden {
                display: none;
            }

            /* Email Input with Domain Styling */
            .email-input-wrapper {
                display: flex;
                align-items: center;
                border: 1px solid #ccc;
                border-radius: 8px;
                overflow: hidden;
                margin-bottom: 16px;
                background: white;
            }

            .email-input-wrapper input {
                flex: 1;
                border: none;
                padding: 12px;
                font-size: 1.2em;
                outline: none;
                margin: 0;
                background: transparent;
            }

            .email-domain {
                padding: 12px 15px;
                background: #f5f5f5;
                color: #999;
                font-size: 1.2em;
                border-left: 1px solid #ccc;
                white-space: nowrap;
                font-weight: 500;
            }

            /* Select Dropdown Styling */
            .form-group select {
                width: 100%;
                padding: 12px;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-size: 1.2em;
                background-color: white;
                color: #333;
                cursor: pointer;
                appearance: none;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 10px center;
                background-size: 20px;
                padding-right: 40px;
            }

            .form-group select:hover {
                border-color: #999;
            }

            .form-group select:focus {
                outline: none;
                border-color: #007bff;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            }

            /* Optgroup styling */
            .form-group select optgroup {
                font-weight: bold;
                color: #333;
            }

            .form-group select option {
                padding: 8px;
                color: #333;
            }

            /* Custom Dropdown for Course */
            .custom-dropdown {
                position: relative;
                display: block;
            }

            .dropdown-select {
                width: 100%;
                padding: 12px;
                border: 1px solid #ccc;
                border-radius: 8px;
                font-size: 1.2em;
                background-color: white;
                color: #333;
                cursor: pointer;
                appearance: none;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 10px center;
                background-size: 20px;
                padding-right: 40px;
            }

            .dropdown-select:hover {
                border-color: #999;
            }

            .dropdown-select:focus {
                outline: none;
                border-color: #007bff;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            }

            .dropdown-menu {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ccc;
                border-top: none;
                border-radius: 0 0 8px 8px;
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                margin-top: -1px;
            }

            .dropdown-search-container {
                padding: 10px;
                border-bottom: 1px solid #e0e0e0;
                position: sticky;
                top: 0;
                background: white;
                z-index: 10;
            }

            .dropdown-search {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 1em;
                outline: none;
                box-sizing: border-box;
            }

            .dropdown-search:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            }

            .dropdown-menu.show {
                display: block;
            }

            .dropdown-group {
                padding: 0;
            }

            .dropdown-group-label {
                padding: 10px 12px;
                font-weight: bold;
                background: #f5f5f5;
                color: #333;
                font-size: 1em;
                border-bottom: 1px solid #e0e0e0;
                cursor: default;
            }

            .dropdown-item {
                padding: 12px 12px;
                color: #333;
                cursor: pointer;
                font-size: 1.1em;
                border-bottom: 1px solid #f0f0f0;
                transition: background-color 0.2s;
            }

            .dropdown-item:hover {
                background-color: #e7f1ff;
                color: #007bff;
            }

            .dropdown-item.hidden {
                display: none;
            }

            .dropdown-group.no-items {
                display: none;
            }

            .file-name {
                font-size: 1.1em;
                color: #28a745;
                margin-top: 10px;
                font-weight: 600;
            }

            /* Image Preview Styles */
            .preview-image {
                max-width: 100%;
                width: 80px;
                height: 80px;
                margin: 8px auto 0;
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
                margin-top: 12px;
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

            .image-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.9);
                z-index: 2000;
                align-items: center;
                justify-content: center;
            }

            .image-modal.show {
                display: flex;
            }

            .image-modal-content {
                position: relative;
                max-width: 90%;
                max-height: 90%;
            }

            .image-modal-img {
                max-width: 100%;
                max-height: 80vh;
                object-fit: contain;
                border-radius: 8px;
            }

            .image-modal-close {
                position: absolute;
                top: 20px;
                right: 30px;
                background: white;
                border: none;
                color: #333;
                font-size: 40px;
                cursor: pointer;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
            }

            .image-modal-close:hover {
                background: #ff4d4d;
                color: white;
            }

            /* Custom Error Popup Styles */
            .error-popup-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                z-index: 1999;
            }

            .error-popup-overlay.show {
                display: block;
            }

            @keyframes errorPopupSlide {
                0% {
                    transform: translate(-50%, -50%) scale(0.8);
                    opacity: 0;
                }
                100% {
                    transform: translate(-50%, -50%) scale(1);
                    opacity: 1;
                }
            }

            .error-popup {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 50px 40px;
                border-radius: 18px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
                z-index: 2000;
                text-align: center;
                min-width: 480px;
                animation: errorPopupSlide 0.4s ease-out forwards;
            }

            .error-popup.show {
                display: block;
            }

            .error-popup-icon {
                margin-bottom: 25px;
                display: inline-block;
                animation: errorIconShake 0.5s ease-out;
            }

            .error-popup-icon svg {
                width: 80px;
                height: 80px;
            }

            @keyframes errorIconShake {
                0% {
                    transform: scale(0) rotate(-10deg);
                }
                50% {
                    transform: scale(1.1) rotate(5deg);
                }
                100% {
                    transform: scale(1) rotate(0deg);
                }
            }

            .error-popup h2 {
                margin: 0 0 15px 0;
                color: #d32f2f;
                font-size: 24px;
                font-weight: 700;
            }

            .error-popup-message {
                color: #555;
                font-size: 15px;
                line-height: 1.6;
                margin-bottom: 30px;
                text-align: left;
            }

            .error-popup-message strong {
                color: #d32f2f;
                display: block;
                margin-top: 15px;
                font-size: 16px;
            }

            .error-popup-button {
                background: #d32f2f;
                color: white;
                border: none;
                padding: 14px 50px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 12px rgba(211, 47, 47, 0.25);
            }

            .error-popup-button:hover {
                background: #b71c1c;
                box-shadow: 0 6px 16px rgba(211, 47, 47, 0.35);
                transform: translateY(-2px);
            }

            .error-popup-button:active {
                transform: translateY(0);
            }

            /* Password Strength Indicator */
            .password-strength-container {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-top: 8px;
            }

            .password-strength-bar-bg {
                flex: 1;
                height: 8px;
                background: #e0e0e0;
                border-radius: 4px;
                overflow: hidden;
            }

            .password-strength-bar {
                height: 100%;
                width: 0%;
                border-radius: 4px;
                transition: width 0.3s ease, background-color 0.3s ease;
            }

            .password-strength-bar.weak {
                width: 33%;
                background: linear-gradient(90deg, #ff4444, #ff6b6b);
            }

            .password-strength-bar.medium {
                width: 66%;
                background: linear-gradient(90deg, #ffa726, #ffcc00);
            }

            .password-strength-bar.strong {
                width: 100%;
                background: linear-gradient(90deg, #4caf50, #66bb6a);
            }

            .password-strength-text {
                font-size: 0.9em;
                font-weight: 600;
                min-width: 60px;
                text-align: right;
            }

            .password-strength-text.weak {
                color: #ff4444;
            }

            .password-strength-text.medium {
                color: #ffa726;
            }

            .password-strength-text.strong {
                color: #4caf50;
            }

            /* Password Requirements */
            .password-requirements {
                margin-top: 12px;
                padding: 12px;
                background: #f5f5f5;
                border-radius: 8px;
                border-left: 4px solid #ff9800;
            }

            .requirements-title {
                font-size: 0.9em;
                font-weight: 700;
                color: #333;
                margin-bottom: 8px;
            }

            .requirements-list {
                list-style: none;
                padding: 0;
                margin: 0;
                font-size: 0.85em;
            }

            .requirements-list li {
                padding: 4px 0;
                color: #666;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .requirements-list li.met {
                color: #4caf50;
                font-weight: 600;
            }

            .requirement-icon {
                width: 18px;
                height: 18px;
                border: 2px solid #ccc;
                border-radius: 3px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.7em;
            }

            .requirements-list li.met .requirement-icon {
                background: #4caf50;
                border-color: #4caf50;
                color: white;
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
    </style>
<head>
    <meta charset="UTF-8">
    <title>Sign Up as a Student</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="front-box modal-box">
            <span class="close-btn" onclick="window.location.href='index.php'">&times;</span>
            <h1>Sign Up as a Student</h1>
            <form action="process_signup_student.php" method="POST" enctype="multipart/form-data" class="login-form" id="signupForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="input-field" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" class="input-field">
                    </div>
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="input-field" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="email-input-wrapper">
                        <input type="text" id="email" name="email_username" class="input-field" placeholder="Username" required>
                        <div class="email-domain">@gmail.com</div>
                    </div>
                    <input type="hidden" id="email_full" name="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input-field" required oninput="checkPasswordStrength(this.value)">
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
                            <li id="req-special"><span class="requirement-icon">✓</span> Special character (!@#$%^&*)</li>
                        </ul>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="year_level">Year Level</label>
                        <select id="year_level" name="year_level" required>
                            <option value="" disabled selected>Select Year Level</option>
                            <option value="1st year">1st year</option>
                            <option value="2nd year">2nd year</option>
                            <option value="3rd year">3rd year</option>
                            <option value="4th year">4th year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input type="text" id="student_id" name="student_id" class="input-field" required>
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
                                <div class="dropdown-item" onclick="selectCourse('AB ENGLISH LANGUAGE - MAJOR IN MASS COMMUNICATION', event)">AB English Language - Major in Mass Communication</div>
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
                                <div class="dropdown-item" onclick="selectCourse('BACHELOR OF SCIENCE IN PROGRAMMING NC IV', event)">BACHELOR OF SCIENCE IN Programming NC IV</div>
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
                                <div class="dropdown-item" onclick="selectCourse('CERTIFICATE IN TEACHING PROFESSION', event)">Certificate in Teaching Profession</div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="course" name="course" required>
                </div>

                <label for="photo">Profile Picture</label>
                <div class="file-upload-container">
                    <div class="file-upload-area" id="photoUploadArea" onclick="if(!this.classList.contains('has-image')) { document.getElementById('photo').click(); }">
                        <div class="upload-icon">⬆️</div>
                        <div class="upload-title">Upload your file here</div>
                        <button type="button" class="browse-btn" onclick="event.preventDefault(); if(!document.getElementById('photoUploadArea').classList.contains('has-image')) { document.getElementById('photo').click(); }">BROWSE</button>
                        <div class="upload-limit">Maximum size 2MB</div>
                        <div class="file-name" id="photoFileName"></div>
                        <button type="button" class="remove-btn" onclick="removePhotoFile(event)">Remove</button>
                    </div>
                    <input type="file" id="photo" name="photo" class="file-input-hidden" accept="image/*">
                </div>

                <label for="attachment">Attach your COM</label>
                <div class="file-upload-container">
                    <div class="file-upload-area" id="attachmentUploadArea" onclick="if(!this.classList.contains('has-image')) { document.getElementById('attachment').click(); }">
                        <div class="upload-icon">⬆️</div>
                        <div class="upload-title">Upload your file here</div>
                        <button type="button" class="browse-btn" onclick="event.preventDefault(); if(!document.getElementById('attachmentUploadArea').classList.contains('has-image')) { document.getElementById('attachment').click(); }">BROWSE</button>
                        <div class="upload-limit">Maximum size 2MB</div>
                        <div class="file-name" id="attachmentFileName"></div>
                        <button type="button" class="scan-btn" id="scanBtn" style="display:none;" onclick="scanAttachmentImage(event)">📄SCAN</button>
                        <button type="button" class="remove-btn" onclick="removeAttachmentFile(event)">Remove</button>
                    </div>
                    <input type="file" id="attachment" name="attachment" class="file-input-hidden" accept="image/*">
                </div>

                <!-- Scanned Text Display -->
                <div id="scannedTextContainer" style="display:none; margin-top: 20px;">
                    <label>Scanned Text:</label>
                    <div id="scannedTextOutput" style="border: 1px solid #ddd; border-radius: 8px; padding: 12px; background: #f9f9f9; min-height: 80px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.95em; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></div>
                </div>

                <!-- Hidden input to store scanned text for validation -->
                <input type="hidden" id="scannedTextData" name="scannedTextData" value="">

                <button type="submit" class="login-btn">Sign Up</button>
            </form>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="image-modal" id="imageModal">
        <div class="image-modal-content">
            <button class="image-modal-close" onclick="closeImageModal()">&times;</button>
            <img id="modalImage" class="image-modal-img" src="" alt="Preview">
        </div>
    </div>

    <!-- Error Popup Modal -->
    <div class="error-popup-overlay" id="errorPopupOverlay"></div>
    <div class="error-popup" id="errorPopup">
        <div class="error-popup-icon">
            <svg width="80" height="80" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" fill="#ffebee" stroke="#d32f2f" stroke-width="2"/>
                <line x1="12" y1="8" x2="12" y2="12" stroke="#d32f2f" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="0.5" fill="#d32f2f"/>
            </svg>
        </div>
        <h2>Validation Error</h2>
        <div class="error-popup-message" id="errorPopupMessage"></div>
        <button class="error-popup-button" onclick="closeErrorPopup()">OK</button>
    </div>

    <script>
        // Image Modal Functions
        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('imageModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Error Popup Functions
        function showErrorPopup(missingFields) {
            let message;
            
            // Check if this is the "Scanned the COM First" message
            if (missingFields.length === 1 && missingFields[0].includes('Scanned')) {
                message = '<strong>• ' + missingFields[0] + '</strong><br/><br/>' +
                         'Please make sure all your details in the form match exactly with the information in your COM file.';
            } else {
                message = 'The following details from the form were NOT found in the scanned COM file:<br/><br/>' +
                         '<strong>• ' + missingFields.join('<br/>• ') + '</strong><br/><br/>' +
                         'Please make sure all your details in the form match exactly with the information in your COM file.';
            }
            
            document.getElementById('errorPopupMessage').innerHTML = message;
            document.getElementById('errorPopupOverlay').classList.add('show');
            document.getElementById('errorPopup').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeErrorPopup() {
            document.getElementById('errorPopupOverlay').classList.remove('show');
            document.getElementById('errorPopup').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Close error popup when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('errorPopupOverlay').addEventListener('click', function() {
                closeErrorPopup();
            });
        });

        // Close error popup with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const popup = document.getElementById('errorPopup');
                if (popup && popup.classList.contains('show')) {
                    closeErrorPopup();
                }
            }
        });

        // Close modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Password Strength Checker
        function checkPasswordStrength(password) {
            const bar = document.getElementById('passwordStrengthBar');
            const text = document.getElementById('passwordStrengthText');
            
            // Reset classes
            bar.className = 'password-strength-bar';
            text.className = 'password-strength-text';
            
            // Check each requirement
            const hasLength = password.length >= 8;
            const hasLowercase = /[a-z]/.test(password);
            const hasUppercase = /[A-Z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement('req-length', hasLength);
            updateRequirement('req-lowercase', hasLowercase);
            updateRequirement('req-uppercase', hasUppercase);
            updateRequirement('req-number', hasNumber);
            updateRequirement('req-special', hasSpecial);
            
            if (password.length === 0) {
                bar.style.width = '0%';
                text.textContent = '';
                return;
            }
            
            let strength = 0;
            if (hasLength) strength += 1;
            if (hasLowercase && hasUppercase) strength += 1;
            if (hasNumber) strength += 1;
            if (hasSpecial) strength += 1;
            
            // Determine strength level
            if (strength <= 1) {
                bar.classList.add('weak');
                text.classList.add('weak');
                text.textContent = 'Weak';
            } else if (strength <= 2) {
                bar.classList.add('medium');
                text.classList.add('medium');
                text.textContent = 'Medium';
            } else {
                bar.classList.add('strong');
                text.classList.add('strong');
                text.textContent = 'Strong';
            }
        }
        
        function updateRequirement(elementId, isMet) {
            const element = document.getElementById(elementId);
            if (isMet) {
                element.classList.add('met');
            } else {
                element.classList.remove('met');
            }
        }

        // Handle file inputs
        const photoInput = document.getElementById('photo');
        const attachmentInput = document.getElementById('attachment');
        const photoUploadArea = document.getElementById('photoUploadArea');
        const attachmentUploadArea = document.getElementById('attachmentUploadArea');
        const photoFileName = document.getElementById('photoFileName');
        const attachmentFileName = document.getElementById('attachmentFileName');
        const emailUsernameInput = document.getElementById('email');
        const emailFullInput = document.getElementById('email_full');

        // Handle email input - auto combine username with @gmail.com
        emailUsernameInput.addEventListener('input', function() {
            emailFullInput.value = this.value + '@gmail.com';
        });

        // Also handle when form is submitted
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            if (emailUsernameInput.value) {
                emailFullInput.value = emailUsernameInput.value + '@gmail.com';
            }
            
            // Validate details match scanned text
            if (!validateDetailsWithScannedText()) {
                e.preventDefault();
                return false;
            }
        });

        // Photo file input change
        photoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Add has-image class immediately to prevent file picker from reopening
                photoUploadArea.classList.add('has-image');
                
                photoFileName.textContent = '✓ ' + this.files[0].name;
                
                // Show preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview if any
                    const existingPreview = photoUploadArea.querySelector('.preview-image');
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
                    photoUploadArea.appendChild(previewImg);
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Attachment file input change
        attachmentInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Add has-image class immediately to prevent file picker from reopening
                attachmentUploadArea.classList.add('has-image');
                
                attachmentFileName.textContent = '✓ ' + this.files[0].name;
                
                // Show scan button
                document.getElementById('scanBtn').style.display = 'inline-block';
                
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

        // Drag and drop for photo
        photoUploadArea.addEventListener('dragover', function(e) {
            if (this.classList.contains('has-image')) return;
            e.preventDefault();
            this.classList.add('dragover');
        });

        photoUploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });

        photoUploadArea.addEventListener('drop', function(e) {
            if (this.classList.contains('has-image')) return;
            e.preventDefault();
            this.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                photoInput.files = e.dataTransfer.files;
                
                // Add has-image class immediately to prevent file picker from reopening
                photoUploadArea.classList.add('has-image');
                
                photoFileName.textContent = '✓ ' + e.dataTransfer.files[0].name;
                
                // Show preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Remove existing preview if any
                    const existingPreview = photoUploadArea.querySelector('.preview-image');
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
                    photoUploadArea.appendChild(previewImg);
                };
                reader.readAsDataURL(e.dataTransfer.files[0]);
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
                    previewImg.style.cursor = 'pointer';
                    previewImg.onclick = function(event) {
                        event.stopPropagation();
                        openImageModal(this.src);
                    };
                    attachmentUploadArea.appendChild(previewImg);
                };
                reader.readAsDataURL(e.dataTransfer.files[0]);
            }
        });

        // Custom Dropdown for Course
        function toggleCourseDropdown() {
            const menu = document.getElementById('courseDropdownMenu');
            const searchInput = document.getElementById('courseSearchInput');
            menu.classList.toggle('show');
            
            // Focus on search input when dropdown opens
            if (menu.classList.contains('show')) {
                setTimeout(() => {
                    searchInput.focus();
                }, 0);
            } else {
                // Clear search when closing
                searchInput.value = '';
                clearCourseSearch();
            }
        }

        function selectCourse(value, e) {
            e.stopPropagation();
            document.getElementById('course').value = value;
            document.getElementById('courseSelected').textContent = value;
            document.getElementById('courseDropdownMenu').classList.remove('show');
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

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.custom-dropdown');
            if (!dropdown.contains(e.target)) {
                document.getElementById('courseDropdownMenu').classList.remove('show');
                document.getElementById('courseSearchInput').value = '';
                clearCourseSearch();
            }
        });

        // Remove photo file function
        function removePhotoFile(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Clear file input
            photoInput.value = '';
            
            // Remove preview image
            const previewImg = photoUploadArea.querySelector('.preview-image');
            if (previewImg) {
                previewImg.remove();
            }
            
            // Clear file name
            photoFileName.textContent = '';
            
            // Remove has-image class to show upload elements
            photoUploadArea.classList.remove('has-image');
        }

        // Remove attachment file function
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
            
            // Hide scan button and reset it
            const scanBtn = document.getElementById('scanBtn');
            scanBtn.style.display = 'none';
            scanBtn.textContent = 'SCAN';
            scanBtn.classList.remove('scanned');
            document.getElementById('scannedTextData').value = '';
        }

        // OCR Scanning Function
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
                    
                    // Change button to green and show success
                    scanBtn.textContent = 'SCAN COMPLETE';
                    scanBtn.classList.add('scanned');
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

        // Validation function to check if details match
        function validateDetailsWithScannedText() {
            const scannedText = document.getElementById('scannedTextData').value;
            
            if (!scannedText) {
                showErrorPopup(['Scanned the COM First']);
                return false;
            }
            
            const firstName = document.getElementById('first_name').value.trim().toLowerCase();
            const lastName = document.getElementById('last_name').value.trim().toLowerCase();
            const studentId = document.getElementById('student_id').value.trim().toLowerCase();
            const yearLevel = document.getElementById('year_level').value.trim().toLowerCase();
            const course = document.getElementById('course').value.trim().toLowerCase();
            
            const scannedLower = scannedText.toLowerCase();
            
            // Check if all required fields are present in scanned text
            const requiredFields = [
                { name: 'First Name', value: firstName },
                { name: 'Last Name', value: lastName },
                { name: 'Student ID', value: studentId },
                { name: 'Year Level', value: yearLevel },
                { name: 'Course', value: course }
            ];
            
            let missingFields = [];
            
            for (let field of requiredFields) {
                if (field.value && !scannedLower.includes(field.value)) {
                    missingFields.push(field.name + ' (' + field.value + ')');
                }
            }
            
            if (missingFields.length > 0) {
                showErrorPopup(missingFields);
                return false;
            }
            
            return true;
        }
    </script>
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <style>
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
            padding: 40px 50px;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.25);
            z-index: 1000;
            text-align: center;
            min-width: 420px;
            animation: alertPopup 0.3s ease-out forwards;
        }
        .custom-alert h2 {
            margin: 0;
            padding: 0;
            color: #333;
            font-size: 24px;
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
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        .alert-button {
            background: #43b047;
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
            background: #388e3c;
        }
        @keyframes spin-login {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        .loading-spinner-login {
            width: 50px;
            height: 50px;
            margin: 20px auto;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #fb251d;
            border-radius: 50%;
            animation: spin-login 1s linear infinite;
        }
    </style>
    <div class="alert-overlay" id="successOverlay"></div>
    <div class="custom-alert" id="successAlert">
        <div class="loading-spinner-login"></div>
        <h2>Sign up successful! Redirecting...</h2>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showSuccessAlert();
        });
        function showSuccessAlert() {
            document.getElementById('successOverlay').style.display = 'block';
            document.getElementById('successAlert').style.display = 'block';
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 1500);
        }
        function closeSuccessAlert() {
            document.getElementById('successOverlay').style.display = 'none';
            document.getElementById('successAlert').style.display = 'none';
        }
    </script>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Sign Up successful!',
            showConfirmButton: true,
            confirmButtonColor: '#43b047',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'swal2-popup-custom',
                confirmButton: 'swal2-confirm-custom',
                icon: 'swal2-icon-success'
            }
        }).then(() => {
            window.location.href = 'index.php';
        });
    </script>
    <style>
    .swal2-popup-custom {
        border-radius: 18px !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18) !important;
        font-size: 1.5em !important;
        padding: 2.5em 2em !important;
    }
    .swal2-confirm-custom {
        background-color: #43b047 !important;
        color: #fff !important;
        font-size: 1.2em !important;
        border-radius: 8px !important;
        padding: 0.7em 2.5em !important;
        box-shadow: 0 2px 8px rgba(67,176,71,0.15) !important;
    }
    .swal2-icon-success {
        border-color: #43b047 !important;
        color: #43b047 !important;
    }
    </style>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo addslashes($_GET['error']); ?>',
            confirmButtonColor: '#1976d2',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'swal2-popup-custom',
                confirmButton: 'swal2-confirm-custom',
                icon: 'swal2-icon-error'
            }
        });
    </script>
    <style>
    .swal2-popup-custom {
        border-radius: 18px !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18) !important;
        font-size: 1.5em !important;
        padding: 2.5em 2em !important;
    }
    .swal2-confirm-custom {
        background-color: #1976d2 !important;
        color: #fff !important;
        font-size: 1.2em !important;
        border-radius: 8px !important;
        padding: 0.7em 2.5em !important;
        box-shadow: 0 2px 8px rgba(25,118,210,0.15) !important;
    }
    .swal2-icon-error {
        border-color: #f44336 !important;
        color: #f44336 !important;
    }
    </style>
    <?php endif; ?>
</body>
</html>