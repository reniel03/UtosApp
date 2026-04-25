<?php
session_start();
include 'db_connect.php';

// Check if user is a student
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'student') {
    header('Location: frontpage.php');
    exit();
}

$student_email = $_SESSION['email'];
$student_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['middle_name'] ? $_SESSION['middle_name'] . ' ' : '') . ($_SESSION['last_name'] ?? ''));
$student_photo = $_SESSION['photo'] ?? null;
$student_email_display = $_SESSION['email'] ?? '';
$student_grade = $_SESSION['grade'] ?? 'Grade';

// Fetch student details including Student ID, Year Level, and Course
$student_query = "SELECT student_id, year_level, course FROM students WHERE email = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$student_result = $stmt->get_result();
$student_data = $student_result->fetch_assoc();
$stmt->close();

$student_id = $student_data['student_id'] ?? '';
$student_year_level = $student_data['year_level'] ?? '';
$student_course = $student_data['course'] ?? '';
$student_gender = $_SESSION['gender'] ?? '';

// Check if photo exists or use placeholder
$has_photo = !empty($student_photo) && file_exists($student_photo);
$use_placeholder = !$has_photo;

// Fetch task statistics
$stats_query = "SELECT 
    COUNT(*) as total_applied,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as total_approved,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as total_completed,
    AVG(CASE WHEN rating IS NOT NULL THEN rating ELSE NULL END) as avg_rating
    FROM student_todos 
    WHERE student_email = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stmt->close();

$total_applied = $stats['total_applied'] ?? 0;
$total_approved = $stats['total_approved'] ?? 0;
$total_completed = $stats['total_completed'] ?? 0;
$avg_rating = $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Student Profile</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        html {
            scroll-behavior: smooth;
            overflow-y: scroll;
            overflow-x: hidden;
        }

        body {
            background: #fff;
            margin: 0;
            font-family: Arial, sans-serif;
            min-height: 120vh;
            padding-bottom: 90px;
        }

        body, html {
            padding: 0;
            margin: 0;
            overflow-x: hidden;
        }

        /* Main Content Wrapper */
        .page-wrapper {
            width: 100%;
            max-width: 100%;
            padding: 20px;
            margin-top: 20px;
        }

        /* Profile Section */
        .profile-section {
            background: linear-gradient(135deg, #ff0000, #ff3333);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.15);
            text-align: center;
            color: white;
            animation: slideInDown 0.6s ease;
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid white;
            object-fit: cover;
            box-shadow: 0 0 25px rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 35px rgba(255, 255, 255, 0.5);
        }

        .profile-avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid white;
            background: linear-gradient(135deg, #ffd4d1, #ffcccc);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 25px rgba(255, 255, 255, 0.3);
            color: #ff8877;
        }

        .profile-avatar-placeholder:hover {
            transform: scale(1.05);
            box-shadow: 0 0 35px rgba(255, 255, 255, 0.5);
            background: linear-gradient(135deg, #ffccc9, #ffbbbb);
        }

        .profile-name {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .profile-email {
            font-size: 0.95em;
            opacity: 0.95;
            margin-bottom: 15px;
            word-break: break-all;
        }

        .profile-grade {
            font-size: 0.85em;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            backdrop-filter: blur(10px);
        }

        /* Menu Items Container */
        .menu-container {
            max-width: 100%;
            animation: slideInUp 0.6s ease 0.2s both;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .menu-section {
            margin-bottom: 25px;
        }

        .menu-section-title {
            font-size: 0.9em;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 20px 12px 20px;
            margin: 20px 0 10px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: #f9f9f9;
            border-radius: 12px;
            margin: 0 20px 10px 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid transparent;
            text-decoration: none;
            color: #333;
        }

        .menu-item:hover {
            background: #f0f0f0;
            transform: translateX(10px);
            border-left-color: #ff0000;
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.1);
        }

        .menu-item:active {
            transform: translateX(8px);
            background: #efefef;
        }

        .menu-item-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .menu-item-icon {
            font-size: 1.5em;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(251, 37, 29, 0.08);
            color: #fb251d;
            transition: all 0.3s ease;
        }

        .menu-item:hover .menu-item-icon {
            background: rgba(251, 37, 29, 0.15);
            transform: scale(1.1);
        }

        .menu-item-text {
            display: flex;
            flex-direction: column;
        }

        .menu-item-title {
            font-weight: 600;
            color: #333;
            font-size: 1em;
        }

        .menu-item-subtitle {
            font-size: 0.8em;
            color: #999;
            margin-top: 2px;
        }

        .menu-item-arrow {
            font-size: 1.2em;
            color: #ddd;
            transition: all 0.3s ease;
        }

        .menu-item:hover .menu-item-arrow {
            color: #bbb;
            transform: translateX(5px);
        }

        /* Badge for new items */
        .menu-badge {
            background: #ff0000;
            color: white;
            font-size: 0.7em;
            padding: 4px 8px;
            border-radius: 12px;
            margin-left: 8px;
            font-weight: 700;
        }

        /* Statistics Section */
        .records-section {
            max-width: 100%;
            margin: 30px 20px 20px 20px;
            animation: slideInUp 0.6s ease 0.4s both;
        }

        .records-title {
            font-size: 0.9em;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 0 12px 0;
            margin: 0 0 15px 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #ff0000, #ff3333);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.25);
        }

        .stat-number {
            font-size: 2em;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.8em;
            opacity: 0.9;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.completed {
            background: linear-gradient(135deg, #28a745, #5cb85c);
        }

        .stat-card.approved {
            background: linear-gradient(135deg, #17a2b8, #5bc0de);
        }

        .stat-card.rating {
            background: linear-gradient(135deg, #ffc107, #ffb300);
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            border-top: 1px solid #e8e8e8;
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 70px;
            z-index: 999;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.08);
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #bbb;
            transition: all 0.3s ease;
            flex: 1;
            height: 100%;
            gap: 6px;
            position: relative;
        }

        .bottom-nav-item:hover {
            color: #999;
        }

        .bottom-nav-item.active {
            color: #fb251d;
        }

        .bottom-nav-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .bottom-nav-icon svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        .bottom-nav-label {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.3px;
            text-transform: capitalize;
        }

        .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 8px;
            height: 8px;
            background: #ffeb3b;
            border-radius: 50%;
            border: 2px solid #fb251d;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 15px;
                margin-top: 10px;
            }

            .profile-section {
                padding: 30px 20px;
                margin-bottom: 20px;
                border-radius: 16px;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                border-width: 4px;
            }

            .profile-avatar-placeholder {
                width: 100px;
                height: 100px;
                border-width: 4px;
            }

            .profile-avatar-placeholder svg {
                width: 50px;
                height: 50px;
            }

            .profile-name {
                font-size: 1.6em;
                margin-bottom: 6px;
            }

            .profile-email {
                font-size: 0.9em;
                margin-bottom: 12px;
            }

            .profile-grade {
                font-size: 0.8em;
                padding: 6px 12px;
            }

            .menu-section-title {
                padding: 0 15px 10px 15px;
                font-size: 0.85em;
                margin: 15px 0 8px 0;
            }

            .menu-item {
                padding: 14px 15px;
                margin: 0 15px 8px 15px;
                border-radius: 10px;
            }

            .menu-item-icon {
                font-size: 1.3em;
                width: 28px;
                height: 28px;
            }

            .menu-item-title {
                font-size: 0.95em;
            }

            .menu-item-subtitle {
                font-size: 0.75em;
            }

            .menu-item-arrow {
                font-size: 1em;
            }

            .bottom-nav {
                height: 80px;
                padding: 10px 0;
                gap: 0;
            }

            .bottom-nav-item {
                gap: 6px;
                margin: 0 2px;
                padding: 0 4px;
                border-radius: 10px;
            }

            .bottom-nav-icon {
                font-size: 28px;
            }

            .bottom-nav-label {
                font-size: 10px;
            }

            body {
                padding-bottom: 95px;
            }

            .records-section {
                margin: 20px 15px 15px 15px;
            }

            .records-title {
                padding: 0 0 10px 0;
                margin: 0 0 12px 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 1.6em;
            }

            .stat-label {
                font-size: 0.75em;
            }
        }

        @media (max-width: 480px) {
            .bottom-nav {
                height: 75px;
                padding: 8px 0;
            }

            .bottom-nav-item {
                gap: 4px;
                margin: 0 1px;
                padding: 0 3px;
            }

            .bottom-nav-icon {
                font-size: 24px;
            }

            .bottom-nav-label {
                font-size: 9px;
            }
        }

        /* Loading Animation Overlay */
        .page-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .page-loading-overlay.show {
            display: flex;
            opacity: 1;
        }

        .loading-container {
            text-align: center;
            animation: fadeInScale 0.6s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeInScale {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        .loading-svg-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            animation: svgPulse 2s ease-in-out infinite;
        }

        @keyframes svgPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.15); opacity: 0.8; }
        }

        .loading-text {
            font-size: 1.1em;
            color: #ff0000;
            font-weight: 700;
            letter-spacing: 1px;
            animation: textBlink 1.5s ease-in-out infinite;
        }

        @keyframes textBlink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        /* Logout Alert Styling */
        .logout-alert-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logout-alert-popup {
            border-radius: 20px !important;
            background: white !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
            padding: 30px 25px !important;
            max-width: 320px !important;
        }

        .logout-alert-popup .swal2-title {
            display: none;
        }

        .logout-alert-popup .swal2-html-container {
            margin: 20px 0 !important;
        }

        .logout-alert-button {
            background-color: #fb251d !important;
            color: white !important;
            border-radius: 8px !important;
            padding: 12px 40px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            border: none !important;
            width: 100% !important;
            transition: all 0.3s ease !important;
        }

        .logout-alert-button:hover {
            background-color: #e01910 !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(251, 37, 29, 0.4) !important;
        }

        .logout-alert-button:active {
            transform: translateY(0);
        }

        /* Edit Profile Modal Styles */
        .edit-profile-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #fff;
            z-index: 10000;
            overflow-y: auto;
            animation: slideInRight 0.3s ease;
        }

        .edit-profile-modal.show {
            display: block;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .edit-profile-container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .edit-profile-header {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .back-arrow {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: #666;
            position: absolute;
            left: 0;
        }

        .back-arrow:hover {
            background: #f0f0f0;
            color: #fb251d;
        }

        .back-arrow svg {
            width: 24px;
            height: 24px;
            stroke: currentColor;
        }

        .edit-profile-header h2 {
            font-size: 1.5em;
            font-weight: 700;
            color: #333;
            margin: 0;
            margin-top: 25px;
            text-align: center;
        }

        /* Profile Picture Edit Section */
        .profile-edit-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 16px;
        }

        .profile-pic-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 12px;
            display: inline-block;
        }

        .profile-pic-edit,
        .profile-pic-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .profile-pic-placeholder {
            background: linear-gradient(135deg, #ffd4d1, #ffcccc);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff8877;
        }

        .profile-pic-placeholder svg {
            width: 60px;
            height: 60px;
        }

        .edit-pic-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fb251d;
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(251, 37, 29, 0.3);
            transition: all 0.3s ease;
        }

        .edit-pic-btn:hover {
            background: #e01910;
            transform: scale(1.1);
        }

        .edit-pic-btn svg {
            width: 20px;
            height: 20px;
            stroke: white;
        }

        .edit-label {
            font-size: 0.9em;
            color: #666;
            margin: 0;
            font-weight: 500;
        }

        /* Form Fields */
        .form-field-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 0.95em;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            text-transform: capitalize;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: inherit;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: #fb251d;
            box-shadow: 0 0 0 3px rgba(251, 37, 29, 0.1);
        }

        .form-input.textarea {
            resize: vertical;
            min-height: 100px;
        }

        .save-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #fb251d, #ff4444);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1.05em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(251, 37, 29, 0.2);
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 37, 29, 0.3);
        }

        .save-btn:active {
            transform: translateY(0);
        }

        /* Password Toggle */
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
            color: #cc0000;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .edit-profile-container {
                padding: 15px;
            }

            .edit-profile-header h2 {
                font-size: 1.3em;
            }

            .profile-pic-wrapper {
                width: 100px;
                height: 100px;
            }

            .edit-pic-btn {
                width: 36px;
                height: 36px;
            }

            .form-input {
                padding: 10px;
                font-size: 1em;
            }

            .save-btn {
                padding: 12px;
                font-size: 1em;
            }
        }

        @media (max-width: 480px) {
            .edit-profile-container {
                padding: 12px;
            }

            .edit-profile-header {
                margin-bottom: 20px;
            }

            .edit-profile-header h2 {
                font-size: 1.2em;
            }

            .profile-edit-section {
                padding: 15px;
                margin-bottom: 25px;
            }

            .profile-pic-wrapper {
                width: 90px;
                height: 90px;
            }

            .form-field-group {
                margin-bottom: 15px;
            }

            .form-input {
                padding: 10px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Page Loading Overlay -->
    <div class="page-loading-overlay" id="pageLoadingOverlay">
        <div class="loading-container">
            <svg class="loading-svg-icon" fill="#ff0000" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 612 612" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><g><path d="M509.833,0c-53.68,0-97.353,43.673-97.353,97.353c0,53.683,43.673,97.356,97.353,97.356 c53.683,0,97.356-43.673,97.356-97.356C607.192,43.673,563.519,0,509.833,0z M541.092,112.035h-31.259 c-8.11,0-14.681-6.571-14.681-14.681V47.185c0-8.11,6.571-14.681,14.681-14.681c8.11,0,14.681,6.571,14.681,14.681v35.487h16.578 c8.11,0,14.681,6.571,14.681,14.681S549.202,112.035,541.092,112.035z M562.066,496.442c-1.283-10.145-6.439-19.185-14.52-25.451 L404.343,359.943c-6.777-5.256-14.884-8.033-23.449-8.033c-0.81,0-1.603,0.088-2.405,0.135c-0.294-0.006-0.581-0.038-0.875-0.038 c-2.625,0-5.262,0.273-7.843,0.81l-139.556,29.101l-8.638-39.478c3.353,0.945,6.847,1.456,10.418,1.456h0.003 c0.041,0,0.079-0.006,0.117-0.006c1.177,0.112,2.364,0.179,3.562,0.185l97.941,0.279c0.015,0,0.088,0,0.103,0c0,0,0,0,0.003,0 c21.053,0,38.23-17.127,38.288-38.18c0.021-7.109-1.926-13.909-5.511-19.843l39.595-82.951c3.491-7.317,0.391-16.082-6.924-19.576 c-7.329-3.488-16.085-0.393-19.576,6.924l-37.258,78.054c-2.763-0.634-5.605-0.998-8.506-1.004l-86.04-0.244l-59.177-57.714 c-2.484-2.422-5.256-4.44-8.213-6.081c-6.565-4.633-14.505-7.346-22.914-7.346c-2.869,0-5.749,0.311-8.565,0.928 c-10.427,2.279-19.338,8.486-25.099,17.471c-5.758,8.985-7.672,19.673-5.391,30.099l41.468,189.498 c2.29,10.453,8.685,19.244,17.23,24.846c-24.203-6.363-44.81-23.971-54.206-48.571L33.211,175.847 c-2.895-7.575-11.381-11.375-18.953-8.477c-7.575,2.892-11.369,11.381-8.477,18.953l89.716,234.822 c16.325,42.728,57.315,70.073,101.775,70.073c6.357,0,12.79-0.558,19.229-1.712c0.247-0.047,0.493-0.094,0.737-0.153 l138.138-32.122l30.519,125.521C390.082,599.973,405.371,612,423.077,612c3.045,0,6.096-0.367,9.067-1.089 c9.939-2.414,18.34-8.556,23.66-17.294c5.32-8.735,6.915-19.021,4.498-28.957l-19.3-79.384l59.617,46.231 c6.777,5.253,14.881,8.031,23.443,8.031h0.003c11.93,0,22.964-5.406,30.272-14.828C560.603,516.629,563.349,506.59,562.066,496.442 z M333.721,329.667L333.721,329.667v0.003V329.667z M118.302,156.313c-9.396-11.034-13.932-25.067-12.778-39.513 c2.358-29.442,28.564-52.147,58.419-49.748c14.446,1.157,27.577,7.872,36.973,18.903c9.396,11.031,13.932,25.067,12.778,39.513 c-2.246,27.994-25.983,49.925-54.038,49.925c-1.451,0-2.91-0.059-4.378-0.176C140.829,174.062,127.698,167.347,118.302,156.313z"></path></g></g></svg>
            <div class="loading-text">Loading...</div>
        </div>
    </div>

    <div class="page-wrapper">
        <!-- Profile Header -->
        <div class="profile-section">
            <?php if ($has_photo): ?>
                <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Profile" class="profile-avatar" title="Click to change profile picture">
            <?php else: ?>
                <div class="profile-avatar-placeholder" title="Click to change profile picture">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width: 60px; height: 60px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
            <?php endif; ?>
            <div class="profile-name"><?php echo htmlspecialchars($student_name); ?></div>
            <div class="profile-email"><?php echo htmlspecialchars($student_email_display); ?></div>
            <div class="profile-grade">Student</div>
        </div>

        <!-- Menu Items -->
        <div class="menu-container">
            <!-- My Account Section -->
            <div class="menu-section-title">My Account</div>
            
            <div class="menu-item" onclick="handleMenuClick('edit_profile')">
                <div class="menu-item-left">
                    <div class="menu-item-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fb251d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="menu-item-text">
                        <div class="menu-item-title">Edit Profile</div>
                        <div class="menu-item-subtitle">Update your information</div>
                    </div>
                </div>
                <div class="menu-item-arrow">›</div>
            </div>

            <div class="menu-item" onclick="handleMenuClick('change_password')">
                <div class="menu-item-left">
                    <div class="menu-item-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fb251d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div class="menu-item-text">
                        <div class="menu-item-title">Change Password</div>
                        <div class="menu-item-subtitle">Update your password regularly</div>
                    </div>
                </div>
                <div class="menu-item-arrow">›</div>
            </div>

            <!-- General Section -->
            <div class="menu-section-title">General</div>

            <div class="menu-item" onclick="handleMenuClick('help_center')">
                <div class="menu-item-left">
                    <div class="menu-item-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fb251d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                    </div>
                    <div class="menu-item-text">
                        <div class="menu-item-title">Help Center</div>
                        <div class="menu-item-subtitle">Get help and support</div>
                    </div>
                </div>
                <div class="menu-item-arrow">›</div>
            </div>

            <div class="menu-item" onclick="handleMenuClick('settings')">
                <div class="menu-item-left">
                    <div class="menu-item-icon">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 24px; height: 24px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M14 5.28988H13C13 5.7323 13.2907 6.12213 13.7148 6.24833L14 5.28988ZM15.3302 5.84137L14.8538 6.72058C15.2429 6.93144 15.7243 6.86143 16.0373 6.54847L15.3302 5.84137ZM16.2426 4.92891L15.5355 4.2218V4.2218L16.2426 4.92891ZM17.6569 4.92891L16.9498 5.63601L16.9498 5.63602L17.6569 4.92891ZM19.0711 6.34312L19.7782 5.63602V5.63602L19.0711 6.34312ZM19.0711 7.75734L18.364 7.05023L19.0711 7.75734ZM18.1586 8.66978L17.4515 7.96268C17.1386 8.27563 17.0686 8.75709 17.2794 9.14621L18.1586 8.66978ZM18.7101 10L17.7517 10.2853C17.8779 10.7093 18.2677 11 18.7101 11V10ZM18.7101 14V13C18.2677 13 17.8779 13.2907 17.7517 13.7148L18.7101 14ZM18.1586 15.3302L17.2794 14.8538C17.0686 15.2429 17.1386 15.7244 17.4515 16.0373L18.1586 15.3302ZM19.0711 16.2427L19.7782 15.5356V15.5356L19.0711 16.2427ZM19.0711 17.6569L18.364 16.9498L18.364 16.9498L19.0711 17.6569ZM17.6569 19.0711L18.364 19.7782V19.7782L17.6569 19.0711ZM15.3302 18.1586L16.0373 17.4515C15.7243 17.1386 15.2429 17.0686 14.8538 17.2794L15.3302 18.1586ZM14 18.7101L13.7148 17.7517C13.2907 17.8779 13 18.2677 13 18.7101H14ZM10 18.7101H11C11 18.2677 10.7093 17.8779 10.2853 17.7517L10 18.7101ZM8.6698 18.1586L9.14623 17.2794C8.7571 17.0685 8.27565 17.1385 7.96269 17.4515L8.6698 18.1586ZM7.75736 19.071L7.05026 18.3639L7.05026 18.3639L7.75736 19.071ZM6.34315 19.071L5.63604 19.7782H5.63604L6.34315 19.071ZM4.92894 17.6568L4.22183 18.3639H4.22183L4.92894 17.6568ZM4.92894 16.2426L4.22183 15.5355H4.22183L4.92894 16.2426ZM5.84138 15.3302L6.54849 16.0373C6.86144 15.7243 6.93146 15.2429 6.7206 14.8537L5.84138 15.3302ZM5.28989 14L6.24835 13.7147C6.12215 13.2907 5.73231 13 5.28989 13V14ZM5.28989 10V11C5.73231 11 6.12215 10.7093 6.24835 10.2852L5.28989 10ZM5.84138 8.66982L6.7206 9.14625C6.93146 8.75712 6.86145 8.27567 6.54849 7.96272L5.84138 8.66982ZM4.92894 7.75738L4.22183 8.46449H4.22183L4.92894 7.75738ZM4.92894 6.34317L5.63605 7.05027H5.63605L4.92894 6.34317ZM6.34315 4.92895L7.05026 5.63606L7.05026 5.63606L6.34315 4.92895ZM7.75737 4.92895L8.46447 4.22185V4.22185L7.75737 4.92895ZM8.6698 5.84139L7.9627 6.54849C8.27565 6.86145 8.7571 6.93146 9.14623 6.7206L8.6698 5.84139ZM10 5.28988L10.2853 6.24833C10.7093 6.12213 11 5.7323 11 5.28988H10ZM11 2C9.89545 2 9.00002 2.89543 9.00002 4H11V4V2ZM13 2H11V4H13V2ZM15 4C15 2.89543 14.1046 2 13 2V4H15ZM15 5.28988V4H13V5.28988H15ZM15.8066 4.96215C15.3271 4.70233 14.8179 4.48994 14.2853 4.33143L13.7148 6.24833C14.1132 6.36691 14.4944 6.52587 14.8538 6.72058L15.8066 4.96215ZM15.5355 4.2218L14.6231 5.13426L16.0373 6.54847L16.9498 5.63602L15.5355 4.2218ZM18.364 4.2218C17.5829 3.44075 16.3166 3.44075 15.5355 4.2218L16.9498 5.63602V5.63601L18.364 4.2218ZM19.7782 5.63602L18.364 4.2218L16.9498 5.63602L18.364 7.05023L19.7782 5.63602ZM19.7782 8.46444C20.5592 7.68339 20.5592 6.41706 19.7782 5.63602L18.364 7.05023L18.364 7.05023L19.7782 8.46444ZM18.8657 9.37689L19.7782 8.46444L18.364 7.05023L17.4515 7.96268L18.8657 9.37689ZM19.6686 9.71475C19.5101 9.18211 19.2977 8.67285 19.0378 8.19335L17.2794 9.14621C17.4741 9.50555 17.6331 9.8868 17.7517 10.2853L19.6686 9.71475ZM18.7101 11H20V9H18.7101V11ZM20 11H22C22 9.89543 21.1046 9 20 9V11ZM20 11V13H22V11H20ZM20 13V15C21.1046 15 22 14.1046 22 13H20ZM20 13H18.7101V15H20V13ZM19.0378 15.8066C19.2977 15.3271 19.5101 14.8179 19.6686 14.2852L17.7517 13.7148C17.6331 14.1132 17.4741 14.4944 17.2794 14.8538L19.0378 15.8066ZM19.7782 15.5356L18.8657 14.6231L17.4515 16.0373L18.364 16.9498L19.7782 15.5356ZM19.7782 18.364C20.5592 17.5829 20.5592 16.3166 19.7782 15.5356L18.364 16.9498H18.364L19.7782 18.364ZM18.364 19.7782L19.7782 18.364L18.364 16.9498L16.9498 18.364L18.364 19.7782ZM15.5355 19.7782C16.3166 20.5592 17.5829 20.5592 18.364 19.7782L16.9498 18.364L15.5355 19.7782ZM14.6231 18.8657L15.5355 19.7782L16.9498 18.364L16.0373 17.4515L14.6231 18.8657ZM14.2853 19.6686C14.8179 19.5101 15.3271 19.2977 15.8066 19.0378L14.8538 17.2794C14.4944 17.4741 14.1132 17.6331 13.7148 17.7517L14.2853 19.6686ZM15 20V18.7101H13V20H15ZM13 22C14.1046 22 15 21.1046 15 20H13V22ZM11 22H13V20H11V22ZM9.00002 20C9.00002 21.1046 9.89545 22 11 22V20H9.00002ZM9.00002 18.7101V20H11V18.7101H9.00002ZM8.19337 19.0378C8.67287 19.2977 9.18213 19.5101 9.71477 19.6686L10.2853 17.7517C9.88681 17.6331 9.50557 17.4741 9.14623 17.2794L8.19337 19.0378ZM8.46447 19.7782L9.3769 18.8657L7.96269 17.4515L7.05026 18.3639L8.46447 19.7782ZM5.63604 19.7782C6.41709 20.5592 7.68342 20.5592 8.46447 19.7781L7.05026 18.3639L5.63604 19.7782ZM4.22183 18.3639L5.63604 19.7782L7.05026 18.3639L5.63604 16.9497L4.22183 18.3639ZM4.22183 15.5355C3.44078 16.3166 3.44078 17.5829 4.22183 18.3639L5.63604 16.9497V16.9497L4.22183 15.5355ZM5.13427 14.6231L4.22183 15.5355L5.63604 16.9497L6.54849 16.0373L5.13427 14.6231ZM4.33144 14.2852C4.48996 14.8179 4.70234 15.3271 4.96217 15.8066L6.7206 14.8537C6.52589 14.4944 6.36693 14.1132 6.24835 13.7147L4.33144 14.2852ZM5.28989 13H4V15H5.28989V13ZM4 13H4H2C2 14.1046 2.89543 15 4 15V13ZM4 13V11H2V13H4ZM4 11V9C2.89543 9 2 9.89543 2 11H4ZM4 11H5.28989V9H4V11ZM4.96217 8.1934C4.70235 8.67288 4.48996 9.18213 4.33144 9.71475L6.24835 10.2852C6.36693 9.88681 6.52589 9.50558 6.7206 9.14625L4.96217 8.1934ZM4.22183 8.46449L5.13428 9.37693L6.54849 7.96272L5.63605 7.05027L4.22183 8.46449ZM4.22183 5.63606C3.44078 6.41711 3.44079 7.68344 4.22183 8.46449L5.63605 7.05027L5.63605 7.05027L4.22183 5.63606ZM5.63605 4.22185L4.22183 5.63606L5.63605 7.05027L7.05026 5.63606L5.63605 4.22185ZM8.46447 4.22185C7.68343 3.4408 6.4171 3.4408 5.63605 4.22185L7.05026 5.63606V5.63606L8.46447 4.22185ZM9.37691 5.13428L8.46447 4.22185L7.05026 5.63606L7.9627 6.54849L9.37691 5.13428ZM9.71477 4.33143C9.18213 4.48995 8.67287 4.70234 8.19337 4.96218L9.14623 6.7206C9.50557 6.52588 9.88681 6.36692 10.2853 6.24833L9.71477 4.33143ZM9.00002 4V5.28988H11V4H9.00002Z" fill="#ff0000"></path> <circle cx="12" cy="12" r="3" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></circle> </g></svg>
                    </div>
                    <div class="menu-item-text">
                        <div class="menu-item-title">Settings</div>
                        <div class="menu-item-subtitle">App preferences</div>
                    </div>
                </div>
                <div class="menu-item-arrow">›</div>
            </div>

            <div class="menu-item" onclick="handleLogout()" style="border-left-color: #fb251d;">
                <div class="menu-item-left">
                    <div class="menu-item-icon" style="background: rgba(251, 37, 29, 0.1);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fb251d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px;">
                            <path d="M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4"></path>
                            <polyline points="17 16 21 12 17 8"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </div>
                    <div class="menu-item-text">
                        <div class="menu-item-title" style="color: #fb251d;">Logout</div>
                        <div class="menu-item-subtitle">Sign out from your account</div>
                    </div>
                </div>
                <div class="menu-item-arrow">›</div>
            </div>
        </div>

        <!-- Records & Statistics Section -->
        <div class="records-section">
            <div class="records-title">Your Records</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_applied; ?></div>
                    <div class="stat-label">Applied</div>
                </div>

                <div class="stat-card approved">
                    <div class="stat-number"><?php echo $total_approved; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $total_completed; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card rating">
                    <div class="stat-number">⭐ <?php echo $avg_rating; ?></div>
                    <div class="stat-label">Avg Rating</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="edit-profile-modal" id="editProfileModal">
        <div class="edit-profile-container">
            <div class="edit-profile-header">
                <button class="back-arrow" onclick="closeEditProfile()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </button>
                <h2>Edit Profile</h2>
                <div></div>
            </div>

            <form class="edit-profile-form" id="editProfileForm" onsubmit="handleProfileUpdate(event)">
                <!-- Profile Picture Section -->
                <div class="profile-edit-section">
                    <div class="profile-pic-wrapper">
                        <?php if ($has_photo): ?>
                            <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Profile" id="profilePicPreview" class="profile-pic-edit">
                        <?php else: ?>
                            <div class="profile-pic-placeholder" id="profilePicPreview">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="edit-pic-btn" onclick="document.getElementById('profilePhotoInput').click()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <input type="file" id="profilePhotoInput" style="display: none;" accept="image/*" onchange="previewProfilePic(event)">
                    </div>
                    <p class="edit-label">Edit</p>
                </div>

                <!-- Form Fields -->
                <div class="form-field-group">
                    <label for="editName" class="form-label">Name</label>
                    <input type="text" id="editName" name="name" class="form-input" value="<?php echo htmlspecialchars($student_name); ?>" required>
                </div>

                <div class="form-field-group">
                    <label for="editStudentID" class="form-label">Student ID</label>
                    <input type="text" id="editStudentID" name="student_id" class="form-input" value="<?php echo htmlspecialchars($student_id); ?>" disabled style="background-color: #f5f5f5; cursor: not-allowed;">
                </div>

                <div class="form-field-group">
                    <label for="editBio" class="form-label">Bio</label>
                    <textarea id="editBio" name="bio" class="form-input textarea" placeholder="Tell us about yourself"></textarea>
                </div>

                <div class="form-field-group">
                    <label for="editYearLevel" class="form-label">Year Level</label>
                    <select id="editYearLevel" name="year_level" class="form-input">
                        <option value="">Select Year Level</option>
                        <option value="1st Year" <?php echo ($student_year_level === '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2nd Year" <?php echo ($student_year_level === '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3rd Year" <?php echo ($student_year_level === '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4th Year" <?php echo ($student_year_level === '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                </div>

                <div class="form-field-group">
                    <label for="editCourse" class="form-label">Course</label>
                    <input type="text" id="editCourse" name="course" class="form-input" placeholder="Your Course" value="<?php echo htmlspecialchars($student_course); ?>">
                </div>

                <div class="form-field-group">
                    <label for="editGender" class="form-label">Gender</label>
                    <select id="editGender" name="gender" class="form-input">
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo (strtolower($student_gender) === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (strtolower($student_gender) === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo (strtolower($student_gender) === 'other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-field-group">
                    <label for="editBirthday" class="form-label">Birthday</label>
                    <input type="date" id="editBirthday" name="birthday" class="form-input">
                </div>

                <div class="form-field-group">
                    <label for="editPhone" class="form-label">Phone</label>
                    <input type="tel" id="editPhone" name="phone" class="form-input" placeholder="+63 9XX XXX XXXX">
                </div>

                <div class="form-field-group">
                    <label for="editEmail" class="form-label">Email</label>
                    <input type="email" id="editEmail" name="email" class="form-input" value="<?php echo htmlspecialchars($student_email_display); ?>" required>
                </div>

                <button type="submit" class="save-btn">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="edit-profile-modal" id="changePasswordModal">
        <div class="edit-profile-container">
            <div class="edit-profile-header">
                <button class="back-arrow" onclick="closeChangePassword()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                </button>
                <h2>Change Password</h2>
                <div></div>
            </div>

            <form class="edit-profile-form" id="changePasswordForm" onsubmit="handlePasswordChange(event)">
                <div class="form-field-group">
                    <label for="currentPassword" class="form-label">Current Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="currentPassword" name="current_password" class="form-input" placeholder="Enter your current password" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordField('currentPassword')">
                            <svg class="openEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="closedEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="newPassword" class="form-label">New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="newPassword" name="new_password" class="form-input" placeholder="Enter your new password" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordField('newPassword')">
                            <svg class="openEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="closedEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="confirmPassword" class="form-label">Confirm New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmPassword" name="confirm_password" class="form-input" placeholder="Confirm your new password" required>
                        <button type="button" class="password-toggle" onclick="togglePasswordField('confirmPassword')">
                            <svg class="openEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="closedEye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="save-btn">Change Password</button>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="student_home.php" class="bottom-nav-item" title="Home">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </span>
            <span class="bottom-nav-label">Home</span>
        </a>
        <a href="student_page.php" class="bottom-nav-item" title="To-Do">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                </svg>
            </span>
            <span class="bottom-nav-label">To-Do</span>
        </a>
        <a href="student_history.php" class="bottom-nav-item" title="History">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </span>
            <span class="bottom-nav-label">History</span>
        </a>
        <a href="student_message.php" class="bottom-nav-item" title="Messages">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="badge"></span>
            </span>
            <span class="bottom-nav-label">Messages</span>
        </a>
        <a href="student_profile.php" class="bottom-nav-item active" title="Account">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </span>
            <span class="bottom-nav-label">Account</span>
        </a>
    </nav>

    <script>
        function handleMenuClick(action) {
            switch(action) {
                case 'edit_profile':
                    openEditProfile();
                    break;
                case 'change_password':
                    openChangePassword();
                    break;
                case 'my_grades':
                    Swal.fire({
                        title: 'My Grades',
                        text: 'Feature coming soon!',
                        icon: 'info',
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'enrolled_classes':
                    Swal.fire({
                        title: 'Enrolled Classes',
                        text: 'Feature coming soon!',
                        icon: 'info',
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'safety_settings':
                    Swal.fire({
                        title: 'Safety Settings',
                        text: 'Feature coming soon!',
                        icon: 'info',
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'help_center':
                    Swal.fire({
                        title: 'Help Center',
                        text: 'Feature coming soon!',
                        icon: 'info',
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'OK'
                    });
                    break;
                case 'settings':
                    Swal.fire({
                        title: 'Settings',
                        text: 'Feature coming soon!',
                        icon: 'info',
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'OK'
                    });
                    break;
            }
        }

        function openEditProfile() {
            const modal = document.getElementById('editProfileModal');
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeEditProfile() {
            const modal = document.getElementById('editProfileModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }

        function openChangePassword() {
            const modal = document.getElementById('changePasswordModal');
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeChangePassword() {
            const modal = document.getElementById('changePasswordModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
                // Clear the form
                document.getElementById('changePasswordForm').reset();
            }
        }

        function togglePasswordField(fieldId) {
            const input = document.getElementById(fieldId);
            const wrapper = input.parentElement;
            const openEye = wrapper.querySelector('.openEye');
            const closedEye = wrapper.querySelector('.closedEye');
            
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

        function previewProfilePic(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePicPreview');
                    if (preview) {
                        if (preview.tagName === 'IMG') {
                            preview.src = e.target.result;
                        } else {
                            preview.innerHTML = `<img src="${e.target.result}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function handleProfileUpdate(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('editProfileForm'));
            const photoInput = document.getElementById('profilePhotoInput');
            
            if (photoInput.files.length > 0) {
                formData.append('photo', photoInput.files[0]);
            }

            // Send data to server
            fetch('update_student_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is ok and try to parse as JSON
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server error: ' + response.status);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid response format. Server returned: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                // Close modal immediately
                closeEditProfile();
                
                if (data.success) {
                    // Update profile avatar if photo was uploaded
                    if (data.photo_path) {
                        const profileAvatar = document.querySelector('.profile-avatar');
                        if (profileAvatar) {
                            profileAvatar.src = data.photo_path + '?' + new Date().getTime();
                        }
                    }
                    
                    Swal.fire({
                        title: 'Success',
                        html: `
                            <div style="text-align: center; padding: 0;">
                                <div style="
                                    background: linear-gradient(135deg, #fb251d, #ff4444);
                                    padding: 40px 20px;
                                    border-radius: 20px 20px 0 0;
                                    margin: -20px -20px 20px -20px;
                                    position: relative;
                                ">
                                    <div style="
                                        width: 80px;
                                        height: 80px;
                                        background: rgba(255, 255, 255, 0.2);
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin: 0 auto 15px;
                                    ">
                                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                            <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                                <p style="
                                    margin: 0 0 10px 0;
                                    font-size: 16px;
                                    color: #333;
                                    font-weight: 600;
                                ">Profile Updated Successfully!</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">${data.message}</p>
                            </div>
                        `,
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'Okay',
                        customClass: {
                            popup: 'swal2-success-popup',   
                            confirmButton: 'swal2-success-button'
                        },
                        showConfirmButton: true,
                        didOpen: (modal) => {
                            modal.style.borderRadius = '20px';
                            modal.style.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.15)';
                            modal.style.zIndex = '10001';
                        }
                    }).then(() => {
                        // Reload the page to show updated information
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        html: `
                            <div style="text-align: center; padding: 0;">
                                <div style="
                                    background: linear-gradient(135deg, #fb251d, #ff4444);
                                    padding: 40px 20px;
                                    border-radius: 20px 20px 0 0;
                                    margin: -20px -20px 20px -20px;
                                    position: relative;
                                ">
                                    <div style="
                                        width: 80px;
                                        height: 80px;
                                        background: rgba(255, 255, 255, 0.2);
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin: 0 auto 15px;
                                    ">
                                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                            <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                                <p style="
                                    margin: 0 0 10px 0;
                                    font-size: 16px;
                                    color: #333;
                                    font-weight: 600;
                                ">Failed to Update Profile</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">${data.message || 'Failed to update profile'}</p>
                            </div>
                        `,
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'Okay',
                        customClass: {
                            popup: 'swal2-error-popup',
                            confirmButton: 'swal2-error-button'
                        },
                        showConfirmButton: true,
                        didOpen: (modal) => {
                            modal.style.borderRadius = '20px';
                            modal.style.boxShadow = '0 10px 40px rgba(251, 37, 29, 0.25)';
                            modal.style.zIndex = '10001';
                        }
                    });
                }
            })
            .catch(error => {
                // Close modal immediately
                closeEditProfile();
                
                Swal.fire({
                    title: 'Error!',
                    html: `
                        <div style="text-align: center; padding: 0;">
                            <div style="
                                background: linear-gradient(135deg, #fb251d, #ff4444);
                                padding: 40px 20px;
                                border-radius: 20px 20px 0 0;
                                margin: -20px -20px 20px -20px;
                                position: relative;
                            ">
                                <div style="
                                    width: 80px;
                                    height: 80px;
                                    background: rgba(255, 255, 255, 0.2);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    margin: 0 auto 15px;
                                ">
                                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <p style="
                                margin: 0 0 10px 0;
                                font-size: 16px;
                                color: #333;
                                font-weight: 600;
                            ">An Error Occurred</p>
                            <p style="
                                margin: 0;
                                font-size: 14px;
                                color: #666;
                                line-height: 1.5;
                            ">${error.message}</p>
                        </div>
                    `,
                    confirmButtonColor: '#fb251d',
                    confirmButtonText: 'Okay',
                    customClass: {
                        popup: 'swal2-error-popup',
                        confirmButton: 'swal2-error-button'
                    },
                    showConfirmButton: true,
                    didOpen: (modal) => {
                        modal.style.borderRadius = '20px';
                        modal.style.boxShadow = '0 10px 40px rgba(251, 37, 29, 0.25)';
                        modal.style.zIndex = '10001';
                    }
                });
            });
        }

        function handlePasswordChange(event) {
            event.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Validate passwords match
            if (newPassword !== confirmPassword) {
                closeChangePassword();
                Swal.fire({
                    title: 'Error!',
                    html: `
                        <div style="text-align: center; padding: 0;">
                            <div style="
                                background: linear-gradient(135deg, #fb251d, #ff4444);
                                padding: 40px 20px;
                                border-radius: 20px 20px 0 0;
                                margin: -20px -20px 20px -20px;
                                position: relative;
                            ">
                                <div style="
                                    width: 80px;
                                    height: 80px;
                                    background: rgba(255, 255, 255, 0.2);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    margin: 0 auto 15px;
                                ">
                                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <p style="
                                margin: 0 0 10px 0;
                                font-size: 16px;
                                color: #333;
                                font-weight: 600;
                            ">New passwords do not match!</p>
                            <p style="
                                margin: 0;
                                font-size: 14px;
                                color: #666;
                                line-height: 1.5;
                            ">Please make sure both password fields are the same.</p>
                        </div>
                    `,
                    confirmButtonColor: '#fb251d',
                    confirmButtonText: 'Okay',
                    customClass: {
                        popup: 'swal2-error-popup',
                        confirmButton: 'swal2-error-button'
                    },
                    showConfirmButton: true,
                    didOpen: (modal) => {
                        modal.style.borderRadius = '20px';
                        modal.style.boxShadow = '0 10px 40px rgba(251, 37, 29, 0.25)';
                        modal.style.zIndex = '10001';
                    }
                });
                return;
            }
            
            // Validate password length
            if (newPassword.length < 6) {
                closeChangePassword();
                Swal.fire({
                    title: 'Error!',
                    html: `
                        <div style="text-align: center; padding: 0;">
                            <div style="
                                background: linear-gradient(135deg, #fb251d, #ff4444);
                                padding: 40px 20px;
                                border-radius: 20px 20px 0 0;
                                margin: -20px -20px 20px -20px;
                                position: relative;
                            ">
                                <div style="
                                    width: 80px;
                                    height: 80px;
                                    background: rgba(255, 255, 255, 0.2);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    margin: 0 auto 15px;
                                ">
                                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <p style="
                                margin: 0 0 10px 0;
                                font-size: 16px;
                                color: #333;
                                font-weight: 600;
                            ">Password too short!</p>
                            <p style="
                                margin: 0;
                                font-size: 14px;
                                color: #666;
                                line-height: 1.5;
                            ">Password must be at least 6 characters long.</p>
                        </div>
                    `,
                    confirmButtonColor: '#fb251d',
                    confirmButtonText: 'Okay',
                    customClass: {
                        popup: 'swal2-error-popup',
                        confirmButton: 'swal2-error-button'
                    },
                    showConfirmButton: true,
                    didOpen: (modal) => {
                        modal.style.borderRadius = '20px';
                        modal.style.boxShadow = '0 10px 40px rgba(251, 37, 29, 0.25)';
                        modal.style.zIndex = '10001';
                    }
                });
                return;
            }
            
            // Send password change request
            Swal.fire({
                title: 'Changing Password...',
                text: 'Please wait while we update your password',
                icon: 'info',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('change_student_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeChangePassword();
                    Swal.fire({
                        title: 'Success',
                        html: `
                            <div style="text-align: center; padding: 0;">
                                <div style="
                                    background: linear-gradient(135deg, #fb251d, #ff4444);
                                    padding: 40px 20px;
                                    border-radius: 20px 20px 0 0;
                                    margin: -20px -20px 20px -20px;
                                    position: relative;
                                ">
                                    <div style="
                                        width: 80px;
                                        height: 80px;
                                        background: rgba(255, 255, 255, 0.2);
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin: 0 auto 15px;
                                    ">
                                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                            <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                                <p style="
                                    margin: 0 0 10px 0;
                                    font-size: 16px;
                                    color: #333;
                                    font-weight: 600;
                                ">Password Changed Successfully!</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">${data.message}</p>
                            </div>
                        `,
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'Okay',
                        customClass: {
                            popup: 'swal2-success-popup',   
                            confirmButton: 'swal2-success-button'
                        },
                        showConfirmButton: true,
                        didOpen: (modal) => {
                            modal.style.borderRadius = '20px';
                            modal.style.boxShadow = '0 10px 40px rgba(251, 37, 29, 0.25)';
                            modal.style.zIndex = '10001';
                        }
                    });
                } else {
                    closeChangePassword();
                    Swal.fire({
                        title: 'Error!',
                        html: `
                            <div style="text-align: center; padding: 0;">
                                <div style="
                                    background: linear-gradient(135deg, #fb251d, #ff4444);
                                    padding: 40px 20px;
                                    border-radius: 20px 20px 0 0;
                                    margin: -20px -20px 20px -20px;
                                    position: relative;
                                ">
                                    <div style="
                                        width: 80px;
                                        height: 80px;
                                        background: rgba(255, 255, 255, 0.2);
                                        border-radius: 50%;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin: 0 auto 15px;
                                    ">
                                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                            <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                                <p style="
                                    margin: 0 0 10px 0;
                                    font-size: 16px;
                                    color: #333;
                                    font-weight: 600;
                                ">Failed to Change Password</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">${data.message || 'Failed to change password'}</p>
                            </div>
                        `,
                        confirmButtonColor: '#fb251d',
                        confirmButtonText: 'Okay',
                        customClass: {
                            popup: 'swal2-error-popup',
                            confirmButton: 'swal2-error-button'
                        },
                        showConfirmButton: true,
                        didOpen: (modal) => {
                            modal.style.borderRadius = '20px';
                            modal.style.boxShadow = '0 10px 40px rgba(251, 37, 29, 0.25)';
                            modal.style.zIndex = '10001';
                        }
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred: ' + error.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        function handleLogout() {
            Swal.fire({
                title: '',
                html: '<svg viewBox="0 0 24 24" fill="none" stroke="#fb251d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 60px; height: 60px; margin: 0 auto 20px; display: block;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg><p style="margin: 16px 0 0 0; color: #333; font-size: 16px; font-weight: 500;">Are you sure you want to logout?</p>',
                icon: null,
                showCancelButton: true,
                confirmButtonColor: '#fb251d',
                cancelButtonColor: '#999',
                confirmButtonText: 'Yes, Logout',
                cancelButtonText: 'Cancel',
                didOpen: (modal) => {
                    // Apply custom styling to buttons
                    const confirmButton = modal.querySelector('.swal2-confirm');
                    const cancelButton = modal.querySelector('.swal2-cancel');
                    if (confirmButton) {
                        confirmButton.style.fontSize = '16px';
                        confirmButton.style.fontWeight = '600';
                        confirmButton.style.padding = '12px 40px';
                        confirmButton.style.borderRadius = '8px';
                    }
                    if (cancelButton) {
                        cancelButton.style.fontSize = '16px';
                        cancelButton.style.fontWeight = '600';
                        cancelButton.style.padding = '12px 40px';
                        cancelButton.style.borderRadius = '8px';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        // Set active navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = 'student_profile.php';
            const navItems = document.querySelectorAll('.bottom-nav-item');
            navItems.forEach(item => {
                if (item.href.includes(currentPage)) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });

        // ==================== PAGE LOADING ANIMATION ====================
        // Show loading overlay when navigating to other pages
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                // Only show loader for internal navigation links that actually navigate
                if (this.href && 
                    !this.href.includes('#') && 
                    !this.getAttribute('onclick') &&
                    !this.target &&
                    this.hostname === window.location.hostname) {
                    // Show the loading overlay
                    const overlay = document.getElementById('pageLoadingOverlay');
                    if (overlay) {
                        overlay.classList.add('show');
                    }
                }
            });
        });

        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', function() {
            const overlay = document.getElementById('pageLoadingOverlay');
            if (overlay) {
                overlay.classList.remove('show');
            }
        });

        // Also hide on page hide (when navigating away)
        window.addEventListener('beforeunload', function() {
            const overlay = document.getElementById('pageLoadingOverlay');
            if (overlay) {
                overlay.classList.add('show');
            }
        });
        // ==================== END PAGE LOADING ANIMATION ====================
    </script>
</body>
</html>
