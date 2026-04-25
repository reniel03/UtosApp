<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in as a student
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_email = $_SESSION['email'];

// Fetch only tasks that:
// 1. Student has NOT applied for yet
// 2. Do NOT have any student currently working on them (status = 'ongoing')
// 3. Do NOT have been completed by any student (is_completed = 1)
// 4. Prioritize newer tasks (created_at DESC) to show newly posted tasks first
$tasks = [];
$query = "SELECT t.*, tc.first_name, tc.last_name, tc.photo as teacher_photo
          FROM tasks t
          LEFT JOIN teachers tc ON t.teacher_email = tc.email
          WHERE t.id NOT IN (
              SELECT st.task_id FROM student_todos st WHERE st.student_email = ?
          )
          AND t.id NOT IN (
              SELECT DISTINCT st.task_id FROM student_todos st 
              WHERE st.status = 'ongoing' AND st.is_completed = 0
          )
          AND t.id NOT IN (
              SELECT DISTINCT st.task_id FROM student_todos st 
              WHERE st.is_completed = 1
          )
          ORDER BY t.created_at DESC, t.due_date ASC
          LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Home - UtosApp</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }

        input, textarea {
            -webkit-user-select: text;
        }

        html {
            scroll-behavior: smooth;
            overflow-y: scroll;
            overflow-x: hidden;
            font-size: 16px;
        }

        body {
            background: linear-gradient(135deg, #f5f5f5 0%, #fff5f5 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            overflow-x: hidden;
            padding-bottom: 80px;
        }

        /* Welcome Section */
        .welcome-section {
            width: 100%;
            min-height: 60vh;
            margin: 0;
            padding: 50px 20px;
            text-align: center;
            background-color: transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            perspective: 1000px;
        }

        .welcome-title {
            color: #ff0000;
            font-size: 7.5em;
            margin-bottom: 40px;
            font-weight: bold;
            transform-origin: center;
            transform-style: preserve-3d;
            text-shadow: 0 10px 30px rgba(255, 0, 0, 0.3);
            position: relative;
            padding-bottom: 25px;
            display: inline-block;
            letter-spacing: 0.05em;
            animation: centerPopOut 1.1s cubic-bezier(0.50, 1.56, 0.64, 1) forwards;
        }

        .welcome-title span {
            display: inline-block;
        }

        @keyframes centerPopOut {
            0% {
                opacity: 0;
                transform: scale(0);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .welcome-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ff0000, #ff3333, #ff0000);
            border-radius: 2px;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.4);
            animation: wipeRight 0.8s ease-out 0.8s forwards;
            transform: scaleX(0);
            transform-origin: left;
        }

        @keyframes wipeRight {
            0% {
                transform: scaleX(0);
                transform-origin: left;
                opacity: 0;
            }
            100% {
                transform: scaleX(1);
                transform-origin: left;
                opacity: 1;
            }
        }

        .welcome-subtitle {
            font-size: 2.8em;
            color: #333;
            font-style: italic;
            margin-bottom: 80px;
            position: relative;
            animation: fadeInSubtitle 0.8s ease-out 0.8s forwards;
            opacity: 0;
        }

        .welcome-subtitle::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: linear-gradient(90deg, #ff0000, #ff3333);
            border-radius: 15px;
            animation: blockReveal 0.8s ease-out 0.8s forwards;
            transform: scaleX(1);
            transform-origin: right;
            pointer-events: none;
            visibility: hidden;
        }

        @keyframes fadeInSubtitle {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes blockReveal {
            0% {
                transform: scaleX(1);
                transform-origin: right;
                visibility: visible;
            }
            100% {
                transform: scaleX(0);
                transform-origin: right;
                visibility: visible;
            }
        }

        /* Main Content */
        .main-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
            opacity: 0;
            animation: fadeInContent 0.6s ease 1.6s forwards;
        }

        @keyframes fadeInContent {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            animation: fadeInUp 0.6s ease 1.8s both;
        }

        .section-icon {
            font-size: 2em;
        }

        .section-title {
            font-size: 1.8em;
            color: #333;
            font-weight: 800;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tasks Grid */
        .tasks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
            opacity: 0;
            animation: fadeInUp 0.6s ease 1.8s forwards;
        }

        .task-preview {
            background: white;
            border-radius: 15px;
            padding: 0;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.08);
            border: 3px solid #ff0000;
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            aspect-ratio: 1 / 1;
        }

        .task-preview:hover {
            transform: translateY(-5px);
            border-color: #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.15);
        }

        .task-preview-image-container {
            width: 100%;
            height: 160px;
            background: linear-gradient(135deg, #f5f5f5 0%, #efefef 100%);
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .task-preview-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
        }

        .task-preview:hover .task-preview-image {
            transform: scale(1.02);
        }

        .task-preview-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            opacity: 0.2;
        }

        .task-preview-content {
            padding: 8px;
            display: flex;
            flex-direction: column;
            flex: 1;
            gap: 6px;
        }

        .task-preview-poster {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
        }

        .task-preview-poster-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            background: #f0f0f0;
            border: 2px solid #ff0000;
            flex-shrink: 0;
        }

        .task-preview-poster-name {
            font-size: 0.9em;
            font-weight: 600;
            color: #666;
            flex: 1;
            line-height: 1.2;
        }

        .task-preview-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
            margin-bottom: 0;
        }

        /* Task Detail Modal */
        .task-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 5000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .task-modal.show {
            display: flex;
            animation: fadeInMotion 0.3s ease;
        }

        @keyframes fadeInMotion {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .task-modal-content {
            background: white;
            border-radius: 20px;
            padding: 0;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .task-modal-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: start;
            border-radius: 20px 20px 0 0;
        }

        .task-modal-header h2 {
            margin: 0;
            font-size: 1.4em;
            flex: 1;
            line-height: 1.3;
        }

        .task-modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
            margin-left: 15px;
        }

        .task-modal-close:hover {
            background: white;
            color: #ff0000;
            transform: rotate(90deg);
        }

        .task-modal-body {
            padding: 0;
        }

        .task-modal-image-container {
            width: 100%;
            height: 250px;
            background: linear-gradient(135deg, #f5f5f5 0%, #efefef 100%);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .task-modal-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .task-modal-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            opacity: 0.15;
        }

        .task-modal-content-section {
            padding: 25px;
        }

        .modal-poster-section {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .task-modal-section {
            margin-bottom: 20px;
        }

        .task-modal-section:last-child {
            margin-bottom: 0;
        }

        .task-modal-label {
            font-size: 0.85em;
            color: #000000;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        .task-modal-value {
            font-size: 1em;
            color: #333;
            line-height: 1.6;
        }

        .task-modal-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .task-modal-meta-item {
            background: #f9f9f9;
            padding: 12px;
            border-radius: 10px;
            border-left: 4px solid #ff0000;
        }

        .task-modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
        }

        .task-modal-footer button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .task-modal-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .task-modal-cancel:hover {
            background: #e0e0e0;
        }

        .task-modal-apply {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
        }

        .task-modal-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            opacity: 0;
            animation: fadeInUp 0.6s ease 1.8s forwards;
        }

        .empty-icon {
            font-size: 4em;
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .empty-title {
            font-size: 1.5em;
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .empty-text {
            font-size: 1em;
            color: #888;
            margin-bottom: 25px;
        }

        .view-all-btn {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.2);
            text-decoration: none;
            display: inline-block;
        }

        .view-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.3);
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
            border-radius: 50%;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .welcome-section {
                min-height: 50vh;
                padding: 40px 15px;
            }

            .welcome-title {
                font-size: 3.5em;
                margin-bottom: 30px;
                padding-bottom: 15px;
            }

            .welcome-subtitle {
                font-size: 1.8em;
                margin-bottom: 50px;
            }

            .welcome-subtitle::before {
                top: -8px;
                left: -8px;
                right: -8px;
                bottom: -8px;
            }

            .section-title {
                font-size: 1.4em;
            }

            .tasks-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .task-preview {
                border: 2px solid #ff0000;
                aspect-ratio: 1 / 1;
            }

            .task-preview-image-container {
                height: 105px;
            }

            .task-preview-content {
                padding: 6px;
                gap: 4px;
            }

            .task-preview-poster {
                gap: 3px;
                margin-bottom: 0;
            }

            .task-preview-poster-img {
                width: 24px;
                height: 24px;
            }

            .task-preview-poster-name {
                font-size: 0.8em;
            }

            .task-preview-title {
                font-size: 0.9em;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-icon {
                font-size: 3em;
            }

            .empty-title {
                font-size: 1.2em;
            }
        }

        @media (max-width: 480px) {
            .welcome-section {
                padding: 30px 12px;
                min-height: 45vh;
            }

            .welcome-title {
                font-size: 2.5em;
                margin-bottom: 20px;
                padding-bottom: 12px;
            }

            .welcome-subtitle {
                font-size: 1.4em;
                margin-bottom: 40px;
            }

            .welcome-subtitle::before {
                top: -6px;
                left: -6px;
                right: -6px;
                bottom: -6px;
            }

            .main-content {
                padding: 15px 15px;
            }

            .section-title {
                font-size: 1.2em;
            }

            .tasks-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 12px;
            }

            .task-preview {
                border: 2px solid #ff0000;
                aspect-ratio: 1 / 1;
            }

            .task-preview-image-container {
                height: 100px;
            }

            .task-preview-content {
                padding: 5px;
                gap: 3px;
            }

            .task-preview-poster {
                gap: 3px;
                margin-bottom: 0;
            }

            .task-preview-poster-img {
                width: 22px;
                height: 22px;
            }

            .task-preview-poster-name {
                font-size: 0.75em;
            }

            .task-preview-title {
                font-size: 0.85em;
            }

            .empty-state {
                padding: 30px 10px;
            }

            .empty-icon {
                font-size: 2.5em;
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

        /* Student Applicants Section - Mobile Responsive */
        @media (max-width: 768px) {
            #modalApplicantsSection {
                padding-top: 12px !important;
                margin-top: 12px !important;
            }

            #modalApplicantsList {
                max-height: 250px !important;
            }

            #modalApplicantsList > div {
                padding: 6px !important;
                margin-bottom: 6px !important;
                font-size: 12px;
            }

            #modalApplicantsList .status-badge {
                font-size: 10px;
                padding: 3px 6px !important;
            }
        }

        @media (max-width: 480px) {
            #modalApplicantsSection {
                padding-top: 10px !important;
                margin-top: 10px !important;
            }

            #modalApplicantsList {
                max-height: 200px !important;
            }

            #modalApplicantsList > div {
                padding: 5px !important;
                margin-bottom: 5px !important;
                flex-wrap: wrap;
                gap: 5px !important;
            }

            #modalApplicantsList > div > div:first-child {
                flex-basis: 100%;
                min-width: 100% !important;
            }

            #modalApplicantsList .status-badge {
                font-size: 9px;
                padding: 3px 5px !important;
                flex: 0 1 auto;
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
    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1 class="welcome-title">Welcome to UtosApp!</h1>
        <p class="welcome-subtitle">Your all-in-one platform task assistant.</p>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Available Tasks Section -->
        <?php if (count($tasks) > 0): ?>
            <div class="section-header">
                <h2 class="section-title">Available Tasks</h2>
            </div>

            <div class="tasks-grid">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-preview" onclick="viewTaskDetails(<?php echo htmlspecialchars(json_encode($task)); ?>)">
                        <!-- Task Image -->
                        <div class="task-preview-image-container">
                            <?php if (!empty($task['attachments'])): ?>
                                <img src="<?php echo htmlspecialchars($task['attachments']); ?>" alt="Task" class="task-preview-image">
                            <?php else: ?>
                                <div class="task-preview-image-placeholder">
                                    <svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#000000" style="width: 95%; height: 95%;"><g><path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z" fill="#000000"></path> <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z" fill="#000000"></path> <polyline fill="none" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path></g></svg>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Task Content -->
                        <div class="task-preview-content">
                            <!-- Poster Info -->
                            <div class="task-preview-poster">
                                <?php 
                                    $poster_path = '';
                                    if (!empty($task['teacher_photo'])) {
                                        $possible_paths = [
                                            'uploads/profiles/' . $task['teacher_photo'],
                                            'uploads/' . $task['teacher_photo'],
                                            $task['teacher_photo']
                                        ];
                                        
                                        foreach ($possible_paths as $path) {
                                            if (file_exists($path)) {
                                                $poster_path = $path;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if (!empty($poster_path)):
                                ?>
                                    <img src="<?php echo htmlspecialchars($poster_path); ?>" alt="Poster" class="task-preview-poster-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <?php endif; ?>
                                <div class="task-preview-poster-img" style="background: linear-gradient(135deg, #ff0000, #ff4444); display: <?php echo empty($poster_path) ? 'flex' : 'none'; ?>; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px;"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M22 12C22 6.49 17.51 2 12 2C6.49 2 2 6.49 2 12C2 14.9 3.25 17.51 5.23 19.34C5.23 19.35 5.23 19.35 5.22 19.36C5.32 19.46 5.44 19.54 5.54 19.63C5.6 19.68 5.65 19.73 5.71 19.77C5.89 19.92 6.09 20.06 6.28 20.2C6.35 20.25 6.41 20.29 6.48 20.34C6.67 20.47 6.87 20.59 7.08 20.7C7.15 20.74 7.23 20.79 7.3 20.83C7.5 20.94 7.71 21.04 7.93 21.13C8.01 21.17 8.09 21.21 8.17 21.24C8.39 21.33 8.61 21.41 8.83 21.48C8.91 21.51 8.99 21.54 9.07 21.56C9.31 21.63 9.55 21.69 9.79 21.75C9.86 21.77 9.93 21.79 10.01 21.8C10.29 21.86 10.57 21.9 10.86 21.93C10.9 21.93 10.94 21.94 10.98 21.95C11.32 21.98 11.66 22 12 22C12.34 22 12.68 21.98 13.01 21.95C13.05 21.95 13.09 21.94 13.13 21.93C13.42 21.9 13.7 21.86 13.98 21.8C14.05 21.79 14.12 21.76 14.2 21.75C14.44 21.69 14.69 21.64 14.92 21.56C15 21.53 15.08 21.5 15.16 21.48C15.38 21.4 15.61 21.33 15.82 21.24C15.9 21.21 15.98 21.17 16.06 21.13C16.27 21.04 16.48 20.94 16.69 20.83C16.77 20.79 16.84 20.74 16.91 20.7C17.11 20.58 17.31 20.47 17.51 20.34C17.58 20.3 17.64 20.25 17.71 20.2C17.91 20.06 18.1 19.92 18.28 19.77C18.34 19.72 18.39 19.67 18.45 19.63C18.56 19.54 18.67 19.45 18.77 19.36C18.77 19.35 18.77 19.35 18.76 19.34C20.75 17.51 22 14.9 22 12ZM16.94 16.97C14.23 15.15 9.79 15.15 7.06 16.97C6.62 17.26 6.26 17.6 5.96 17.97C4.44 16.43 3.5 14.32 3.5 12C3.5 7.31 7.31 3.5 12 3.5C16.69 3.5 20.5 7.31 20.5 12C20.5 14.32 19.56 16.43 18.04 17.97C17.75 17.6 17.38 17.26 16.94 16.97Z" fill="currentColor"></path><path d="M12 6.92969C9.93 6.92969 8.25 8.60969 8.25 10.6797C8.25 12.7097 9.84 14.3597 11.95 14.4197C11.98 14.4197 12.02 14.4197 12.04 14.4197C12.06 14.4197 12.09 14.4197 12.11 14.4197C12.12 14.4197 12.13 14.4197 12.13 14.4197C14.15 14.3497 15.74 12.7097 15.75 10.6797C15.75 8.60969 14.07 6.92969 12 6.92969Z" fill="currentColor"></path></g></svg></div>
                                <span class="task-preview-poster-name"><?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?></span>
                            </div>

                            <!-- Task Title -->
                            <div class="task-preview-title"><?php echo htmlspecialchars($task['title']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <svg viewBox="0 0 1024 1024" style="width: 130px; height: 130px;" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M660 103.2l-149.6 76 2.4 1.6-2.4-1.6-157.6-80.8L32 289.6l148.8 85.6v354.4l329.6-175.2 324.8 175.2V375.2L992 284.8z" fill="#FFFFFF"></path><path d="M180.8 737.6c-1.6 0-3.2 0-4-0.8-2.4-1.6-4-4-4-7.2V379.2L28 296c-2.4-0.8-4-4-4-6.4s1.6-5.6 4-7.2l320.8-191.2c2.4-1.6 5.6-1.6 8 0l154.4 79.2L656 96c2.4-1.6 4.8-0.8 7.2 0l332 181.6c2.4 1.6 4 4 4 7.2s-1.6 5.6-4 7.2l-152.8 88v350.4c0 3.2-1.6 5.6-4 7.2-2.4 1.6-5.6 1.6-8 0l-320-174.4-325.6 173.6c-1.6 0.8-2.4 0.8-4 0.8zM48 289.6L184.8 368c2.4 1.6 4 4 4 7.2v341.6l317.6-169.6c2.4-1.6 5.6-1.6 7.2 0l312.8 169.6V375.2c0-3.2 1.6-5.6 4-7.2L976 284.8 659.2 112.8 520 183.2c0 0.8-0.8 0.8-0.8 1.6-2.4 4-7.2 4.8-11.2 2.4l-1.6-1.6h-0.8l-152.8-78.4L48 289.6z" fill="#ff0000"></path><path d="M510.4 179.2l324.8 196v354.4L510.4 554.4z" fill="#ff0000"></path><path d="M510.4 179.2L180.8 375.2v354.4l329.6-175.2z" fill="#ff0000"></path><path d="M835.2 737.6c-1.6 0-2.4 0-4-0.8l-324.8-176c-2.4-1.6-4-4-4-7.2V179.2c0-3.2 1.6-5.6 4-7.2 2.4-1.6 5.6-1.6 8 0L839.2 368c2.4 1.6 4 4 4 7.2v355.2c0 3.2-1.6 5.6-4 7.2h-4zM518.4 549.6l308.8 167.2V379.2L518.4 193.6v356z" fill="#ff0000"></path><path d="M180.8 737.6c-1.6 0-3.2 0-4-0.8-2.4-1.6-4-4-4-7.2V375.2c0-3.2 1.6-5.6 4-7.2l329.6-196c2.4-1.6 5.6-1.6 8 0 2.4 1.6 4 4 4 7.2v375.2c0 3.2-1.6 5.6-4 7.2l-329.6 176h-4z m8-358.4v337.6l313.6-167.2V193.6L188.8 379.2z" fill="#ff0000"></path><path d="M510.4 550.4L372 496 180.8 374.4v355.2l329.6 196 324.8-196V374.4L688.8 483.2z" fill="#ff0000"></path><path d="M510.4 933.6c-1.6 0-3.2 0-4-0.8L176.8 736.8c-2.4-1.6-4-4-4-7.2V374.4c0-3.2 1.6-5.6 4-7.2 2.4-1.6 5.6-1.6 8 0L376 488.8l135.2 53.6 174.4-66.4L830.4 368c2.4-1.6 5.6-2.4 8-0.8 2.4 1.6 4 4 4 7.2v355.2c0 3.2-1.6 5.6-4 7.2l-324.8 196s-1.6 0.8-3.2 0.8z m-321.6-208l321.6 191.2 316.8-191.2V390.4L693.6 489.6c-0.8 0.8-1.6 0.8-1.6 0.8l-178.4 68c-1.6 0.8-4 0.8-5.6 0L369.6 504c-0.8 0-0.8-0.8-1.6-0.8L188.8 389.6v336z" fill="#ff0000"></path><path d="M510.4 925.6l324.8-196V374.4L665.6 495.2l-155.2 55.2z" fill="#ff0000"></path><path d="M510.4 933.6c-1.6 0-2.4 0-4-0.8-2.4-1.6-4-4-4-7.2V550.4c0-3.2 2.4-6.4 5.6-7.2L662.4 488l168-120c2.4-1.6 5.6-1.6 8-0.8 2.4 1.6 4 4 4 7.2v355.2c0 3.2-1.6 5.6-4 7.2l-324.8 196s-1.6 0.8-3.2 0.8z m8-377.6v355.2l308.8-185.6V390.4L670.4 501.6c-0.8 0.8-1.6 0.8-1.6 0.8l-150.4 53.6z" fill="#ff0000"></path><path d="M252.8 604l257.6 145.6V550.4l-147.2-49.6-182.4-126.4z" fill="#ff0000"></path><path d="M32 460l148.8-85.6 329.6 176L352 640.8z" fill="#FFFFFF"></path><path d="M659.2 693.6l176-90.4V375.2L692 480.8l-179.2 68-2.4 1.6z" fill="#ff0000"></path><path d="M510.4 550.4l148.8 85.6L992 464.8l-156.8-89.6z" fill="#FFFFFF"></path><path d="M352 648.8c-1.6 0-2.4 0-4-0.8l-320-180.8c-2.4-1.6-4-4-4-7.2s1.6-5.6 4-7.2L176.8 368c2.4-1.6 5.6-1.6 8 0l329.6 176c2.4 1.6 4 4 4 7.2s-1.6 5.6-4 7.2L356 648c-0.8 0.8-2.4 0.8-4 0.8zM48 460L352 632l141.6-80.8L180.8 384 48 460z" fill="#ff0000"></path><path d="M659.2 644c-1.6 0-2.4 0-4-0.8L506.4 557.6c-2.4-1.6-4-4-4-7.2s1.6-5.6 4-7.2l324.8-176c2.4-1.6 5.6-1.6 8 0l156.8 90.4c2.4 1.6 4 4 4 7.2s-1.6 5.6-4 7.2L663.2 643.2c-1.6 0.8-2.4 0.8-4 0.8zM527.2 550.4l132.8 76L976 464l-141.6-80-307.2 166.4z" fill="#ff0000"></path></g></svg>
                </div>
                <style>
                    @keyframes float {
                        0%, 100% { transform: translateY(0px); }
                        50% { transform: translateY(-8px); }
                    }
                </style>
                <div class="empty-title">No Tasks Yet</div>
                <div class="empty-text">You don't have any assigned tasks at the moment. Check back soon!</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="student_home.php" class="bottom-nav-item active" title="Home">
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
        <a href="student_profile.php" class="bottom-nav-item" title="Account">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </span>
            <span class="bottom-nav-label">Account</span>
        </a>
    </nav>

    <!-- Task Detail Modal -->
    <div id="taskModal" class="task-modal">
        <div class="task-modal-content">
            <div class="task-modal-header">
                <h2 id="modalTaskTitle">Task Title</h2>
                <div class="task-modal-close" onclick="closeTaskModal()" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; padding: 0;">
                    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 506.4 506.4" xml:space="preserve" width="35" height="35">
                        <circle style="fill:#ff1900;" cx="253.2" cy="253.2" r="249.2"></circle>
                        <path style="fill:#F4EFEF;" d="M281.6,253.2l90.8-90.8c4.4-4.4,4.4-12,0-16.4l-11.2-11.2c-4.4-4.4-12-4.4-16.4,0L254,225.6 l-90.8-90.8c-4.4-4.4-12-4.4-16.4,0L135.6,146c-4.4,4.4-4.4,12,0,16.4l90.8,90.8L135.6,344c-4.4,4.4-4.4,12,0,16.4l11.2,11.6 c4.4,4.4,12,4.4,16.4,0l90.8-90.8l90.8,90.8c4.4,4.4,12,4.4,16.4,0l11.2-11.6c4.4-4.4,4.4-12,0-16.4L281.6,253.2z"></path>
                        <path d="M253.2,506.4C113.6,506.4,0,392.8,0,253.2S113.6,0,253.2,0s253.2,113.6,253.2,253.2S392.8,506.4,253.2,506.4z M253.2,8 C118,8,8,118,8,253.2s110,245.2,245.2,245.2s245.2-110,245.2-245.2S388.4,8,253.2,8z"></path>
                        <path d="M352.8,379.6c-4,0-8-1.6-11.2-4.4l-88-88l-88,88c-2.8,2.8-6.8,4.4-11.2,4.4c-4,0-8-1.6-11.2-4.4L132,364 c-2.8-2.8-4.4-6.8-4.4-11.2c0-4,1.6-8,4.4-11.2l88-88l-88-88c-2.8-2.8-4.4-6.8-4.4-11.2c0-4,1.6-8,4.4-11.2l11.2-11.2 c6-6,16.4-6,22,0l88,88l88-88c2.8-2.8,6.8-4.4,11.2-4.4l0,0c4,0,8,1.6,11.2,4.4l11.2,11.2c6,6,6,16,0,22l-88,88l88,88 c2.8,2.8,4.4,6.8,4.4,11.2c0,4-1.6,8-4.4,11.2l-11.2,11.2C360.8,378,357.2,379.6,352.8,379.6L352.8,379.6z M253.6,277.2 c1.2,0,2,0.4,2.8,1.2l90.8,90.8c1.6,1.6,3.2,2.4,5.6,2.4l0,0c2,0,4-0.8,5.6-2.4l11.6-11.6c1.6-1.6,2.4-3.2,2.4-5.6 c0-2-0.8-4-2.4-5.6l-90.8-90.8c-0.8-0.8-1.2-1.6-1.2-2.8s0.4-2,1.2-2.8l90.8-90.8c2.8-2.8,2.8-8,0-10.8l-11.2-11.2 c-1.6-1.6-3.2-2.4-5.6-2.4l0,0c-2,0-4,0.8-5.6,2.4L256.8,228c-1.6,1.6-4,1.6-5.6,0l-90.8-90.8c-2.8-2.8-8-2.8-10.8,0L138,148.4 c-1.6,1.6-2.4,3.2-2.4,5.6s0.8,4,2.4,5.6l90.8,90.8c1.6,1.6,1.6,4,0,5.6L138,346.8c-1.6,1.6-2.4,3.2-2.4,5.6c0,2,0.8,4,2.4,5.6 l11.6,11.6c2.8,2.8,8,2.8,10.8,0l90.8-90.8C251.6,277.6,252.4,277.2,253.6,277.2z"></path>
                    </svg>
                </div>
            </div>
            <div class="task-modal-body">
                <!-- Task Image in Modal -->
                <div class="task-modal-image-container" id="modalImageContainer">
                    <div class="task-modal-image-placeholder">
                        <svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#000000" style="width: 95%; height: 95%;"><g><path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z" fill="#000000"></path> <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z" fill="#000000"></path> <polyline fill="none" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path></g></svg>
                    </div>
                </div>

                <div class="task-modal-content-section">
                    <!-- Poster Info -->
                    <div class="modal-poster-section" id="modalPosterInfo">
                        <!-- Populated by JS -->
                    </div>

                    <div class="task-modal-meta">
                        <div class="task-modal-meta-item">
                            <div class="task-modal-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>Location</div>
                            <div class="task-modal-value" id="modalTaskRoom">Room</div>
                        </div>
                        <div class="task-modal-meta-item">
                            <div class="task-modal-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>Due Date</div>
                            <div class="task-modal-value" id="modalTaskDueDate">Date</div>
                        </div>
                        <div class="task-modal-meta-item">
                            <div class="task-modal-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>Time</div>
                            <div class="task-modal-value" id="modalTaskTime">Time</div>
                        </div>
                        <div class="task-modal-meta-item">
                            <div class="task-modal-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><polyline points="20 6 9 17 4 12"></polyline></svg>Status</div>
                            <div class="task-modal-value" id="modalTaskStatus">Status</div>
                        </div>
                    </div>
                    <div class="task-modal-section">
                        <div class="task-modal-label"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline; margin-right: 6px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg>Description</div>
                        <div class="task-modal-value" id="modalTaskDesc">Description</div>
                    </div>

                    <!-- Student Applicants Section -->
                    <div class="task-modal-section" id="modalApplicantsSection" style="margin-top: 15px; display: none; border-top: 1px solid #ddd; padding-top: 15px;">
                        <div class="task-modal-label" style="margin-bottom: 12px;">👥 Student Applicants</div>
                        <div id="modalApplicantsList" style="max-height: 300px; overflow-y: auto;"></div>
                    </div>
                </div>
            </div>

            
            <div class="task-modal-footer">
                <button class="task-modal-cancel" onclick="closeTaskModal()">Close</button>
                <button class="task-modal-apply" id="modalApplyBtn" onclick="applyFromModal()">Apply Now</button>
            </div>
        </div>
    </div>

    <script>
        let currentTaskId = null;
        let currentTask = null;

        // Convert 24-hour time format (HH:MM:SS) to 12-hour format (hh:MM AM/PM)
        function convertTo12Hour(time24) {
            if (!time24) return 'N/A';
            const [hours, minutes] = time24.split(':');
            const hour = parseInt(hours);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return `${hour12}:${minutes} ${ampm}`;
        }

        function viewTaskDetails(task) {
            currentTask = task;
            currentTaskId = task.id;
            
            const modal = document.getElementById('taskModal');
            const isApplied = task.is_completed !== null;
            
            // Populate modal
            document.getElementById('modalTaskTitle').textContent = task.title;
            document.getElementById('modalTaskRoom').textContent = task.room;
            document.getElementById('modalTaskDueDate').textContent = new Date(task.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            document.getElementById('modalTaskTime').textContent = convertTo12Hour(task.due_time);
            document.getElementById('modalTaskDesc').textContent = task.description;
            
            // Handle image display
            const imageContainer = document.getElementById('modalImageContainer');
            if (task.attachments && task.attachments.trim() !== '') {
                imageContainer.innerHTML = '<img src="' + task.attachments + '" alt="Task Image" class="task-modal-image">';
            } else {
                imageContainer.innerHTML = '<div class="task-modal-image-placeholder"><svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#000000" style="width: 95%; height: 95%;"><g><path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z" fill="#000000"></path> <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z" fill="#000000"></path> <polyline fill="none" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path></g></svg></div>';
            }

            // Show poster info
            const posterContainer = document.getElementById('modalPosterInfo');
            if (posterContainer) {
                let posterHTML = '';
                let hasValidPhoto = false;
                
                // Generate unique IDs for this modal instance
                const photoImgId = 'modal-poster-img-' + task.id;
                const photoFallbackId = 'modal-poster-fallback-' + task.id;
                
                // Check for valid photo paths
                if (task.teacher_photo) {
                    validPhotoPaths = [
                        'uploads/profiles/' + task.teacher_photo,
                        'uploads/' + task.teacher_photo,
                        task.teacher_photo
                    ];
                    hasValidPhoto = true;
                }
                
                // If we have teacher photo, render both image and fallback
                if (hasValidPhoto) {
                    posterHTML += '<img id="' + photoImgId + '" src="' + validPhotoPaths[0] + '" alt="Poster" class="modal-poster-img" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 2px solid #ff0000;" onerror="document.getElementById(\'' + photoFallbackId + '\').style.display=\'flex\'; this.style.display=\'none\';">';
                    posterHTML += '<div id="' + photoFallbackId + '" class="modal-poster-fallback" style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #ff0000, #ff4444); display: none; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 10px; flex-shrink: 0;"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M22 12C22 6.49 17.51 2 12 2C6.49 2 2 6.49 2 12C2 14.9 3.25 17.51 5.23 19.34C5.23 19.35 5.23 19.35 5.22 19.36C5.32 19.46 5.44 19.54 5.54 19.63C5.6 19.68 5.65 19.73 5.71 19.77C5.89 19.92 6.09 20.06 6.28 20.2C6.35 20.25 6.41 20.29 6.48 20.34C6.67 20.47 6.87 20.59 7.08 20.7C7.15 20.74 7.23 20.79 7.3 20.83C7.5 20.94 7.71 21.04 7.93 21.13C8.01 21.17 8.09 21.21 8.17 21.24C8.39 21.33 8.61 21.41 8.83 21.48C8.91 21.51 8.99 21.54 9.07 21.56C9.31 21.63 9.55 21.69 9.79 21.75C9.86 21.77 9.93 21.79 10.01 21.8C10.29 21.86 10.57 21.9 10.86 21.93C10.9 21.93 10.94 21.94 10.98 21.95C11.32 21.98 11.66 22 12 22C12.34 22 12.68 21.98 13.01 21.95C13.05 21.95 13.09 21.94 13.13 21.93C13.42 21.9 13.7 21.86 13.98 21.8C14.05 21.79 14.12 21.76 14.2 21.75C14.44 21.69 14.69 21.64 14.92 21.56C15 21.53 15.08 21.5 15.16 21.48C15.38 21.4 15.61 21.33 15.82 21.24C15.9 21.21 15.98 21.17 16.06 21.13C16.27 21.04 16.48 20.94 16.69 20.83C16.77 20.79 16.84 20.74 16.91 20.7C17.11 20.58 17.31 20.47 17.51 20.34C17.58 20.3 17.64 20.25 17.71 20.2C17.91 20.06 18.1 19.92 18.28 19.77C18.34 19.72 18.39 19.67 18.45 19.63C18.56 19.54 18.67 19.45 18.77 19.36C18.77 19.35 18.77 19.35 18.76 19.34C20.75 17.51 22 14.9 22 12ZM16.94 16.97C14.23 15.15 9.79 15.15 7.06 16.97C6.62 17.26 6.26 17.6 5.96 17.97C4.44 16.43 3.5 14.32 3.5 12C3.5 7.31 7.31 3.5 12 3.5C16.69 3.5 20.5 7.31 20.5 12C20.5 14.32 19.56 16.43 18.04 17.97C17.75 17.6 17.38 17.26 16.94 16.97Z" fill="currentColor"></path><path d="M12 6.92969C9.93 6.92969 8.25 8.60969 8.25 10.6797C8.25 12.7097 9.84 14.3597 11.95 14.4197C11.98 14.4197 12.02 14.4197 12.04 14.4197C12.06 14.4197 12.09 14.4197 12.11 14.4197C12.12 14.4197 12.13 14.4197 12.13 14.4197C14.15 14.3497 15.74 12.7097 15.75 10.6797C15.75 8.60969 14.07 6.92969 12 6.92969Z" fill="currentColor"></path></g></svg></div>';

                } else {
                    // No photo, show fallback directly
                    posterHTML += '<div class="modal-poster-fallback" style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #ff0000, #ff4444); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 10px; flex-shrink: 0;"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M22 12C22 6.49 17.51 2 12 2C6.49 2 2 6.49 2 12C2 14.9 3.25 17.51 5.23 19.34C5.23 19.35 5.23 19.35 5.22 19.36C5.32 19.46 5.44 19.54 5.54 19.63C5.6 19.68 5.65 19.73 5.71 19.77C5.89 19.92 6.09 20.06 6.28 20.2C6.35 20.25 6.41 20.29 6.48 20.34C6.67 20.47 6.87 20.59 7.08 20.7C7.15 20.74 7.23 20.79 7.3 20.83C7.5 20.94 7.71 21.04 7.93 21.13C8.01 21.17 8.09 21.21 8.17 21.24C8.39 21.33 8.61 21.41 8.83 21.48C8.91 21.51 8.99 21.54 9.07 21.56C9.31 21.63 9.55 21.69 9.79 21.75C9.86 21.77 9.93 21.79 10.01 21.8C10.29 21.86 10.57 21.9 10.86 21.93C10.9 21.93 10.94 21.94 10.98 21.95C11.32 21.98 11.66 22 12 22C12.34 22 12.68 21.98 13.01 21.95C13.05 21.95 13.09 21.94 13.13 21.93C13.42 21.9 13.7 21.86 13.98 21.8C14.05 21.79 14.12 21.76 14.2 21.75C14.44 21.69 14.69 21.64 14.92 21.56C15 21.53 15.08 21.5 15.16 21.48C15.38 21.4 15.61 21.33 15.82 21.24C15.9 21.21 15.98 21.17 16.06 21.13C16.27 21.04 16.48 20.94 16.69 20.83C16.77 20.79 16.84 20.74 16.91 20.7C17.11 20.58 17.31 20.47 17.51 20.34C17.58 20.3 17.64 20.25 17.71 20.2C17.91 20.06 18.1 19.92 18.28 19.77C18.34 19.72 18.39 19.67 18.45 19.63C18.56 19.54 18.67 19.45 18.77 19.36C18.77 19.35 18.77 19.35 18.76 19.34C20.75 17.51 22 14.9 22 12ZM16.94 16.97C14.23 15.15 9.79 15.15 7.06 16.97C6.62 17.26 6.26 17.6 5.96 17.97C4.44 16.43 3.5 14.32 3.5 12C3.5 7.31 7.31 3.5 12 3.5C16.69 3.5 20.5 7.31 20.5 12C20.5 14.32 19.56 16.43 18.04 17.97C17.75 17.6 17.38 17.26 16.94 16.97Z" fill="currentColor"></path><path d="M12 6.92969C9.93 6.92969 8.25 8.60969 8.25 10.6797C8.25 12.7097 9.84 14.3597 11.95 14.4197C11.98 14.4197 12.02 14.4197 12.04 14.4197C12.06 14.4197 12.09 14.4197 12.11 14.4197C12.12 14.4197 12.13 14.4197 12.13 14.4197C14.15 14.3497 15.74 12.7097 15.75 10.6797C15.75 8.60969 14.07 6.92969 12 6.92969Z" fill="currentColor"></path></g></svg></div>';
                }
                posterHTML += '<div><strong>' + (task.first_name + ' ' + task.last_name) + '</strong><br><small style="color: #888; font-size: 0.85em;">' + (task.teacher_email || '') + '</small></div>';
                posterContainer.innerHTML = posterHTML;
            }
            
            const statusBtn = document.getElementById('modalTaskStatus');
            if (task.is_completed) {
                statusBtn.innerHTML = '<span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 0.9em;">✓ Completed</span>';
            } else if (isApplied) {
                statusBtn.innerHTML = '<span style="background:  #c3e6cb; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 0.9em;">Available</span>';
            } else {
                statusBtn.innerHTML = '<span style="background: #c3e6cb; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 0.9em;">Applied</span>';
            }
            
            // Fetch and display applicants for this task
            fetch('get_task_applications.php?task_id=' + task.id)
                .then(response => response.json())
                .then(data => {
                    const applicantsSection = document.getElementById('modalApplicantsSection');
                    const applicantsList = document.getElementById('modalApplicantsList');
                    
                    if (data.students && data.students.length > 0) {
                        applicantsSection.style.display = 'block';
                        let applicantsHTML = '';
                        data.students.forEach(student => {
                            const statusClass = student.is_completed ? 'status-completed' : 
                                              (student.status === 'ongoing' ? 'status-ongoing' : 
                                              (student.status === 'rejected' ? 'status-rejected' : 'status-pending'));
                            
                            const statusText = student.is_completed ? '✅ Completed' : 
                                             (student.status === 'ongoing' ? '⚙️ In Progress' : 
                                             (student.status === 'rejected' ? '❌ Rejected' : '⏳ Pending'));
                            
                            applicantsHTML += '<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px; background: #f9f9f9; border-radius: 6px; margin-bottom: 8px; border-left: 3px solid #007bff;">';
                            applicantsHTML += '<div style="flex: 1; min-width: 150px;">';
                            applicantsHTML += '<div style="font-size: 12px; color: #333; margin-bottom: 2px;"><strong>✉️ ' + student.student_email + '</strong></div>';
                            applicantsHTML += '<div style="font-size: 10px; color: #666;">Applied: ' + new Date(student.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + '</div>';
                            applicantsHTML += '</div>';
                            applicantsHTML += '<span class="status-badge ' + statusClass + '" style="padding: 4px 8px; font-size: 11px;">' + statusText + '</span>';
                            applicantsHTML += '</div>';
                        });
                        applicantsList.innerHTML = applicantsHTML;
                    } else {
                        applicantsSection.style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Error fetching applicants:', err);
                    document.getElementById('modalApplicantsSection').style.display = 'none';
                });
            
            // Update apply button
            const applyBtn = document.getElementById('modalApplyBtn');
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            applyBtn.style.background = 'linear-gradient(135deg, #ff0000, #ff4444)';
            applyBtn.style.cursor = 'pointer';
            
            modal.classList.add('show');
        }

        function closeTaskModal() {
            const modal = document.getElementById('taskModal');
            modal.classList.remove('show');
            currentTaskId = null;
            currentTask = null;
        }

        function applyTask(taskId) {
            // Show loading
            Swal.fire({
                title: 'Applying...',
                html: `
                    <div style="text-align: center; padding: 0;">
                        <div style="
                            background: linear-gradient(135deg, #ff0000, #ff4444);
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
                                <svg height="50px" width="50px" version="1.1" viewBox="0 0 496 496" style="animation: spin 1s linear infinite;">
                                    <g>
                                        <path style="fill:#ffffff;" d="M248,92c-13.6,0-24-10.4-24-24V24c0-13.6,10.4-24,24-24s24,10.4,24,24v44C272,80.8,261.6,92,248,92z"></path>
                                        <path style="fill:#f0f0f0;" d="M248,496c-13.6,0-24-10.4-24-24v-44c0-13.6,10.4-24,24-24s24,10.4,24,24v44 C272,485.6,261.6,496,248,496z"></path>
                                        <path style="fill:#ffffff;" d="M157.6,116c-8,0-16-4-20.8-12l-21.6-37.6c-6.4-11.2-2.4-26.4,8.8-32.8s26.4-2.4,32.8,8.8L178.4,80 c6.4,11.2,2.4,26.4-8.8,32.8C166.4,114.4,161.6,116,157.6,116z"></path>
                                        <path style="fill:#f0f0f0;" d="M360,465.6c-8,0-16-4-20.8-12L317.6,416c-6.4-11.2-2.4-26.4,8.8-32.8c11.2-6.4,26.4-2.4,32.8,8.8 l21.6,37.6c6.4,11.2,2.4,26.4-8.8,32.8C368,464.8,364,465.6,360,465.6z"></path>
                                        <path style="fill:#ffffff;" d="M92,181.6c-4,0-8-0.8-12-3.2l-37.6-21.6c-11.2-6.4-15.2-21.6-8.8-32.8s21.6-15.2,32.8-8.8l37.6,21.6 c11.2,6.4,15.2,21.6,8.8,32.8C108,177.6,100,181.6,92,181.6z"></path>
                                        <path style="fill:#f0f0f0;" d="M442.4,384c-4,0-8-0.8-12-3.2L392,359.2c-11.2-6.4-15.2-21.6-8.8-32.8c6.4-11.2,21.6-15.2,32.8-8.8 l37.6,21.6c11.2,6.4,15.2,21.6,8.8,32.8C458.4,380,450.4,384,442.4,384z"></path>
                                        <path style="fill:#ffffff;" d="M68,272H24c-13.6,0-24-10.4-24-24s10.4-24,24-24h44c13.6,0,24,10.4,24,24S80.8,272,68,272z"></path>
                                        <path style="fill:#f0f0f0;" d="M472,272h-44c-13.6,0-24-10.4-24-24s10.4-24,24-24h44c13.6,0,24,10.4,24,24S485.6,272,472,272z"></path>
                                        <path style="fill:#ffffff;" d="M53.6,384c-8,0-16-4-20.8-12c-6.4-11.2-2.4-26.4,8.8-32.8l37.6-21.6c11.2-6.4,26.4-2.4,32.8,8.8 c6.4,11.2,2.4,26.4-8.8,32.8l-37.6,21.6C62.4,383.2,58.4,384,53.6,384z"></path>
                                        <path style="fill:#f0f0f0;" d="M404,181.6c-8,0-16-4-20.8-12c-6.4-11.2-2.4-26.4,8.8-32.8l37.6-21.6c11.2-6.4,26.4-2.4,32.8,8.8 s2.4,26.4-8.8,32.8L416,178.4C412,180.8,408,181.6,404,181.6z"></path>
                                        <path style="fill:#ffffff;" d="M136,465.6c-4,0-8-0.8-12-3.2c-11.2-6.4-15.2-21.6-8.8-32.8l21.6-37.6c6.4-11.2,21.6-15.2,32.8-8.8 c11.2,6.4,15.2,21.6,8.8,32.8l-21.6,37.6C152,461.6,144,465.6,136,465.6z"></path>
                                        <path style="fill:#f0f0f0;" d="M338.4,116c-4,0-8-0.8-12-3.2c-11.2-6.4-15.2-21.6-8.8-32.8l21.6-37.6c6.4-11.2,21.6-15.2,32.8-8.8 c11.2,6.4,15.2,21.6,8.8,32.8L359.2,104C354.4,111.2,346.4,116,338.4,116z"></path>
                                    </g>
                                </svg>
                            </div>
                        </div>
                        <p style="
                            margin: 20px 0 0 0;
                            font-size: 16px;
                            color: #333;
                            font-weight: 600;
                        ">Please wait...</p>
                    </div>
                `,
                allowOutsideClick: false,
                confirmButtonColor: '#ff0000',
                customClass: {
                    popup: 'swal2-loading-popup',
                },
                showConfirmButton: false,
                didOpen: (modal) => {
                    modal.style.borderRadius = '20px';
                    modal.style.boxShadow = '0 10px 40px rgba(255, 0, 0, 0.25)';
                    modal.style.zIndex = '5001';
                    
                    // Add spin animation if not already in style
                    if (!document.querySelector('style[data-spin]')) {
                        const style = document.createElement('style');
                        style.setAttribute('data-spin', 'true');
                        style.textContent = `
                            @keyframes spin {
                                from { transform: rotate(0deg); }
                                to { transform: rotate(360deg); }
                            }
                        `;
                        document.head.appendChild(style);
                    }
                }
            });

            // Send apply request
            const formData = new FormData();
            formData.append('action', 'accept_task');
            formData.append('task_id', taskId);

            fetch('accept_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check content type
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json().then(data => ({
                        status: response.status,
                        data: data
                    }));
                } else {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        return {
                            status: response.status,
                            data: { success: false, error: 'Server error: ' + text }
                        };
                    });
                }
            })
            .then(result => {
                console.log('Response:', result);
                if (result.data.success) {
                    Swal.fire({
                        title: 'Success',
                        html: `
                            <div style="text-align: center; padding: 0;">
                                <div style="
                                    background: linear-gradient(135deg, #ff0000, #ff4444);
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
                                ">Task Applied Successfully!</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">You have successfully applied for this task and can now view it in your to-do list.</p>
                            </div>
                        `,
                        confirmButtonColor: '#ff0000',
                        confirmButtonText: 'Okay',
                        customClass: {
                            popup: 'swal2-success-popup',
                            confirmButton: 'swal2-success-button'
                        },
                        showConfirmButton: true,
                        didOpen: (modal) => {
                            modal.style.borderRadius = '20px';
                            modal.style.boxShadow = '0 10px 40px rgba(255, 0, 0, 0.25)';
                            modal.style.zIndex = '5001';
                        }
                    }).then(() => {
                        // Redirect to student page to view applied task details
                        window.location.href = 'student_page.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: result.data.error || 'Failed to apply for task',
                        icon: 'error',
                        confirmButtonColor: '#ff0000'
                    });
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Network error: ' + error.message,
                    icon: 'error',
                    confirmButtonColor: '#ff0000'
                });
            });
        }

        function applyFromModal() {
            const taskId = currentTaskId;  // Save the task ID before closing modal
            closeTaskModal();  // Close modal (this sets currentTaskId = null)
            applyTask(taskId);  // Apply with the saved task ID
        }

        // Close modal when clicking outside
        document.getElementById('taskModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeTaskModal();
            }
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
        // ==================== END PAGE LOADING ANIMATION ====================
    </script>
</body>
</html>
