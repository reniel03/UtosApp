<?php
session_start();
include 'db_connect.php';

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$teacher_email = $_SESSION['email'];

// Fetch teacher's completed tasks - either all students completed or task status is complete
$query = "SELECT t.*, COUNT(st.id) as total_students, 
          SUM(CASE WHEN st.is_completed = 1 THEN 1 ELSE 0 END) as completed_count
          FROM tasks t
          LEFT JOIN student_todos st ON t.id = st.task_id
          WHERE t.teacher_email = ?
          GROUP BY t.id
          HAVING completed_count > 0
          ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $teacher_email);
$stmt->execute();
$result = $stmt->get_result();
$completed_tasks = [];

while ($row = $result->fetch_assoc()) {
    $completed_tasks[] = $row;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Record</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        }

        .nav-bar {
            background-color: #ff0000;
            padding: 60px 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            margin-left: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            width: 100%;
            box-sizing: border-box;
            height: 160px;
        }

        .nav-links {
            display: flex;
            gap: 65px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 2.2em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 30px;
            position: relative;
        }

        .icon-wrapper {
            position: relative;
            margin: 0 5px;
        }

        .icon-btn {
            background: #ff0000;
            border: none;
            color: white;
            cursor: pointer;
            padding: 25px;
            border-radius: 20px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 110px;
            height: 110px;
            box-shadow: 0 6px 25px rgba(255, 0, 0, 0.25);
            margin: 0 10px;
        }

        .icon-btn svg {
            width: 70px;
            height: 70px;
            transition: all 0.3s ease;
            transform: translateY(-2px);
        }

        .message-btn {
            background: #ff0000;
        }

        .message-btn svg {
            transform: translateY(-4px) scale(1.1);
        }

        .icon-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.35);
            background: #ff1a1a;
        }

        .icon-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 15px rgba(255, 0, 0, 0.2);
        }

        .notification-dot {
            position: absolute;
            top: -15px;
            right: -15px;
            width: 55px;
            height: 55px;
            background-color: #ff0000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            color: white;
            font-weight: bold;
            border: none;
            animation: pulse 2s infinite;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .profile-pic {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background-color: #fff;
            cursor: pointer;
            box-shadow: 0 6px 25px rgba(255, 0, 0, 0.25);
            border: 4px solid #ff0000;
            transition: all 0.3s ease;
            object-fit: cover;
        }

        .profile-pic:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.35);
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem;
            min-width: 450px;
            color: #333;
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 2000;
            box-shadow: 0 10px 40px rgba(255, 0, 0, 0.2);
            border: 3px solid #ff0000;
            margin-top: 15px;
        }

        .profile-dropdown.show {
            display: block;
            opacity: 1;
            transform: translateY(10px);
        }

        /* Message dropdown styles */
        .message-dropdown {
            position: absolute;
            top: 120%;
            right: 0;
            width: 480px;
            max-height: 600px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.18);
            border: 3px solid #ff0000;
            padding: 16px;
            display: none;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            z-index: 1200;
        }

        .message-dropdown.show {
            display: block;
            opacity: 1;
            transform: translateY(6px);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .message-header h4 {
            margin: 0;
            font-size: 1.2em;
            color: #ff0000;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .message-pill {
            background: rgba(255, 0, 0, 0.08);
            color: #ff0000;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.9em;
            border: 1px solid rgba(255, 0, 0, 0.15);
        }

        .message-search {
            position: relative;
            margin-bottom: 12px;
        }

        .message-search input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            font-size: 0.95em;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        .message-search input:focus {
            outline: none;
            border-color: #ff0000;
            box-shadow: 0 0 0 4px rgba(255, 0, 0, 0.08);
        }

        .message-search svg {
            position: absolute;
            top: 10px;
            left: 12px;
            width: 20px;
            height: 20px;
            opacity: 0.6;
        }

        .message-list {
            max-height: 440px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-right: 4px;
        }

        .message-empty {
            text-align: center;
            padding: 32px 16px;
            color: #777;
            font-weight: 700;
            border: 1px dashed #e5e5e5;
            border-radius: 12px;
            background: #fafafa;
        }

        .message-card {
            display: grid;
            grid-template-columns: 50px 1fr 24px;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid #f0f0f0;
            background: linear-gradient(135deg, #ffffff, #fff8f8);
            transition: border 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }

        .message-card:hover {
            border-color: rgba(255, 0, 0, 0.25);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(255, 0, 0, 0.12);
        }

        .message-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid #ff0000;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
        }

        .message-meta h5 {
            margin: 0;
            font-size: 1em;
            color: #222;
            font-weight: 800;
        }

        .message-meta p {
            margin: 2px 0 0;
            color: #555;
            font-size: 0.92em;
            line-height: 1.35;
        }

        .message-time {
            font-size: 0.8em;
            color: #999;
            font-weight: 700;
        }

        /* Notification dropdown styles (match teacher task page) */
        .notification-dropdown {
            position: absolute;
            top: 120%;
            right: 0;
            width: 520px;
            max-height: 640px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.2);
            border: 3px solid #ff0000;
            padding: 20px;
            display: none;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            z-index: 1300;
        }

        .notification-dropdown.show {
            display: block;
            opacity: 1;
            transform: translateY(6px);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .notification-header h4 {
            margin: 0;
            font-size: 1.2em;
            color: #ff0000;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .notification-actions button {
            background: rgba(255, 0, 0, 0.08);
            color: #ff0000;
            border: 1px solid rgba(255, 0, 0, 0.15);
            padding: 6px 10px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .notification-actions button:hover {
            background: #ff0000;
            color: white;
            box-shadow: 0 10px 20px rgba(255, 0, 0, 0.15);
        }

        .notification-list {
            max-height: 460px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-right: 4px;
        }

        .notification-empty {
            text-align: center;
            padding: 32px 16px;
            color: #777;
            font-weight: 700;
            border: 1px dashed #e5e5e5;
            border-radius: 12px;
            background: #fafafa;
        }

        .notification-item {
            display: grid;
            grid-template-columns: 10px 1fr auto;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #f0f0f0;
            background: linear-gradient(135deg, #ffffff, #fff8f8);
            transition: border 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }

        .notification-item:hover {
            border-color: rgba(255, 0, 0, 0.25);
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(255, 0, 0, 0.12);
        }

        .notification-accent {
            width: 6px;
            height: 100%;
            border-radius: 12px;
            background: linear-gradient(180deg, #ff4d4d, #ff0000);
        }

        .notification-meta h5 {
            margin: 0;
            font-size: 1em;
            color: #222;
            font-weight: 800;
        }

        .notification-meta p {
            margin: 2px 0 0;
            color: #555;
            font-size: 0.93em;
            line-height: 1.35;
        }

        .notification-time {
            font-size: 0.82em;
            color: #999;
            font-weight: 700;
        }

        .profile-info {
            padding: 1.8rem;
            border-bottom: 3px solid #ff0000;
            background: linear-gradient(145deg, #ff0000, #ff3333);
            border-radius: 15px;
            margin-bottom: 1.5rem;
            color: white;
        }

        .profile-image-container {
            flex-shrink: 0;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-info-pic {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            cursor: pointer;
            box-shadow: 0 0 25px rgba(255, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .profile-info-pic:hover {
            transform: scale(1.05);
            box-shadow: 0 0 35px rgba(255, 255, 255, 0.5);
        }

        .profile-details {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .detail-group {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.8rem 1rem;
            margin-bottom: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(2px);
        }

        .detail-group:last-child {
            margin-bottom: 0;
        }

        .detail-group p {
            margin: 0;
            font-size: 1.3em;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.95);
        }

        .detail-group strong {
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.05em;
        }

        .detail-value {
            color: rgba(255, 255, 255, 0.98);
            font-size: 1.5em !important;
            margin-top: 0.3rem !important;
            font-family: Arial, sans-serif;
            font-weight: 700;
            letter-spacing: 0.01em;
            text-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        .profile-menu {
            padding: 1.5rem 0;
            background: white;
        }

        .profile-menu a {
            display: flex;
            align-items: center;
            padding: 1.3rem 1.5rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 10px;
            font-size: 1.4em;
            margin-bottom: 0.8rem;
            font-weight: 500;
        }

        .profile-menu a:hover {
            background: #ff0000;
            color: white;
            transform: translateX(8px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.25);
        }

        .profile-menu a:last-child {
            margin-top: 1rem;
            border-top: 3px solid #ff0000;
            padding-top: 1.5rem;
            color: #ff0000;
            font-weight: 600;
            font-size: 1.5em;
        }

        .profile-menu a:last-child:hover {
            color: white;
            background: #ff0000;
            transform: translateX(8px) scale(1.02);
        }

        .main-content {
            width: 100%;
            margin-top: 240px;
            padding: 60px 80px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: auto;
        }

        .record-header {
            text-align: center;
            margin-bottom: 60px;
            animation: fadeInDown 0.6s ease-out forwards;
        }

        @keyframes fadeInDown {
            0% {
                opacity: 0;
                transform: translateY(-30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .record-title {
            font-size: 5em;
            color: #ff0000;
            font-weight: bold;
            margin-bottom: 15px;
            text-shadow: 0 4px 15px rgba(255, 0, 0, 0.2);
        }

        .record-subtitle {
            font-size: 2.2em;
            color: #666;
            font-weight: 500;
        }

        .record-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .task-section {
            animation: fadeInUp 0.7s ease-out forwards;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            font-size: 2.8em;
            color: #ff0000;
            font-weight: bold;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            background: transparent;
        }

        .task-count {
            background: #ff0000;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .task-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .task-card {
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: slideIn 0.6s ease-out forwards;
            opacity: 0;
            border-left: 6px solid #28a745;
        }

        .task-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 32px rgba(40, 167, 69, 0.2);
        }

        .task-card-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 28px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .task-card-title {
            font-size: 2.3em;
            font-weight: 900;
            margin-bottom: 12px;
        }

        .task-card-room {
            font-size: 1.5em;
            margin-bottom: 8px;
            opacity: 0.95;
        }

        .task-card-due {
            font-size: 1.4em;
            opacity: 0.9;
        }

        .task-status-badge {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            border: 2px solid white;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .task-card-body {
            padding: 28px;
            background: white;
        }

        .task-card-description {
            margin-bottom: 16px;
        }

        .task-desc-label {
            font-weight: bold;
            color: #333;
            font-size: 1.5em;
        }

        .task-desc-text {
            color: #666;
            font-size: 1.4em;
            line-height: 1.5;
            margin-top: 4px;
        }

        .task-stats {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            margin-top: 16px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-label {
            font-size: 1.2em;
            color: #666;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2.5em;
            color: #28a745;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #999;
        }

        .empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .empty-text {
            font-size: 2em;
            margin-bottom: 10px;
        }

        .empty-subtext {
            font-size: 1.5em;
            color: #bbb;
        }

        /* Profile Modal Styles */
        .profile-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .profile-modal.show {
            display: flex;
            opacity: 1;
            justify-content: center;
            align-items: center;
        }

        .profile-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-modal-image {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 15px;
            box-shadow: 0 0 50px rgba(255, 255, 255, 0.3);
            animation: zoomIn 0.3s ease;
        }

        @keyframes zoomIn {
            0% {
                opacity: 0;
                transform: scale(0.8);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .profile-modal-close {
            position: absolute;
            top: 30px;
            left: 30px;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            color: white;
            transition: all 0.3s ease;
            z-index: 3001;
        }

        .profile-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        @media (max-width: 900px) {
            .main-content {
                padding: 40px 20px;
                margin-top: 150px;
            }

            .record-title {
                font-size: 2.5em;
            }

            .nav-links {
                gap: 30px;
            }

            .nav-links a {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <nav class="nav-bar">
        <div class="nav-links">
            <a href="teacher_task_page.php">Home</a>
            <a href="assigned_tasks.php">Assigned Tasks</a>
            <a href="teacher_record.php">Record</a>
        </div>
        <div class="nav-right">
            <div class="icon-wrapper">
                <button class="icon-btn notification-btn" onclick="toggleNotificationDropdown(); event.stopPropagation();">
                    <svg width="55" height="55" viewBox="0 0 24 24" fill="none">
                        <path d="M12 3C13.1046 3 14 3.89543 14 5V5.17071C16.9004 5.58254 19 8.02943 19 11V14.8293L20.8536 16.6829C21.5062 17.3355 20.9534 18.5 20.0294 18.5H3.97056C3.04662 18.5 2.49381 17.3355 3.14645 16.6829L5 14.8293V11C5 8.02943 7.09962 5.58254 10 5.17071V5C10 3.89543 10.8954 3 12 3Z" fill="white"/>
                        <path d="M12 22C13.1046 22 14 21.1046 14 20H10C10 21.1046 10.8954 22 12 22Z" fill="white"/>
                    </svg>
                </button>
                <span class="notification-dot" style="display:none;"></span>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <div class="notification-actions">
                            <button type="button">Mark all read</button>
                        </div>
                    </div>
                    <div class="notification-list">
                        <div class="notification-empty">No notifications yet.</div>
                    </div>
                </div>
            </div>
            <div class="icon-wrapper">
                <button class="icon-btn message-btn" onclick="toggleMessageDropdown(); event.stopPropagation();">
                    <svg width="55" height="55" viewBox="0 0 24 24" fill="none">
                        <path d="M20 2H4C2.9 2 2 2.9 2 4V16C2 17.1 2.9 18 4 18H7V21.7C7 22.3 7.7 22.7 8.2 22.4L13.9 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="white"/>
                        <circle cx="7" cy="10" r="1.5" fill="#ff0000"/>
                        <circle cx="12" cy="10" r="1.5" fill="#ff0000"/>
                        <circle cx="17" cy="10" r="1.5" fill="#ff0000"/>
                    </svg>
                </button>
                <div class="message-dropdown" id="messageDropdown">
                    <div class="message-header">
                        <h4>Messages</h4>
                        <span class="message-pill" style="display:none;"></span>
                    </div>
                    <div class="message-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"></circle>
                            <line x1="16.65" y1="16.65" x2="21" y2="21"></line>
                        </svg>
                        <input type="text" placeholder="Search messages..." aria-label="Search messages">
                    </div>
                    <div class="message-list">
                        <div class="message-empty">No messages yet.</div>
                    </div>
                </div>
            </div>
            <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-pic" onclick="toggleProfileDropdown(); event.stopPropagation();" style="cursor: pointer;">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-info">
                    <div class="profile-image-container">
                        <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-info-pic" onclick="openProfileModal(); event.stopPropagation();" style="cursor: pointer;">
                    </div>
                    <div class="profile-details">
                        <div class="detail-group">
                            <p class="detail-value"><?php
                                $first = isset($_SESSION['first_name']) ? trim($_SESSION['first_name']) : '';
                                $middle = isset($_SESSION['middle_name']) ? trim($_SESSION['middle_name']) : '';
                                $last = isset($_SESSION['last_name']) ? trim($_SESSION['last_name']) : '';
                                $full_name = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
                                echo $full_name !== '' ? htmlspecialchars($full_name) : 'Not set';
                            ?></p>
                        </div>
                    </div>
                </div>
                <div class="profile-menu">
                    <a href="#">⚙️ Settings</a>
                    <a href="#">📝 Give Feedback</a>
                    <a href="logout.php">📤 Log Out</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="record-header">
            <h1 class="record-title">📜 Completed Tasks Record</h1>
            <p class="record-subtitle">View all completed task submissions</p>
        </div>

        <div class="record-container">
            <?php if (count($completed_tasks) > 0): ?>
                <div class="task-section" style="width: 100%; max-width: 900px; margin: 0 auto;">
                    <div class="section-header">
                        ✅ Completed Tasks
                        <span class="task-count"><?php echo count($completed_tasks); ?></span>
                    </div>
                    <div class="task-list">
                        <?php foreach ($completed_tasks as $index => $task): ?>
                            <div class="task-card" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                                <div class="task-card-header">
                                    <div>
                                        <div class="task-card-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                        <div class="task-card-room">📍 Room: <?php echo htmlspecialchars($task['room']); ?></div>
                                        <div class="task-card-due">📅 Due: <?php echo htmlspecialchars($task['due_date']); ?> ⏰ <?php echo htmlspecialchars($task['due_time']); ?></div>
                                    </div>
                                    <div class="task-status-badge">
                                        ✅ Completed
                                    </div>
                                </div>
                                <div class="task-card-body">
                                    <div class="task-card-description">
                                        <div class="task-desc-label">📝 Description:</div>
                                        <div class="task-desc-text"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
                                    </div>
                                    <div class="task-stats">
                                        <div class="stat-item">
                                            <div class="stat-label">Total Submissions</div>
                                            <div class="stat-value"><?php echo $task['total_students']; ?></div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-label">Completed</div>
                                            <div class="stat-value"><?php echo $task['completed_count']; ?></div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-label">Completion Rate</div>
                                            <div class="stat-value">
                                                <?php 
                                                $rate = $task['total_students'] > 0 ? round(($task['completed_count'] / $task['total_students']) * 100) : 0;
                                                echo $rate . '%';
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <div class="empty-text">No completed tasks yet</div>
                    <div class="empty-subtext">Completed tasks will appear here</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Picture Modal -->
    <div class="profile-modal" id="profileModal" onclick="if(event.target === this) closeProfileModal()">
        <button class="profile-modal-close" onclick="closeProfileModal()">×</button>
        <div class="profile-modal-content">
            <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-modal-image">
        </div>
    </div>

    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const messageDropdown = document.getElementById('messageDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            if (messageDropdown) {
                messageDropdown.classList.remove('show');
            }
            if (notificationDropdown) {
                notificationDropdown.classList.remove('show');
            }
            dropdown.classList.toggle('show');
        }

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            const messageDropdown = document.getElementById('messageDropdown');
            if (messageDropdown) {
                messageDropdown.classList.remove('show');
            }
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function toggleMessageDropdown() {
            const dropdown = document.getElementById('messageDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            if (notificationDropdown) {
                notificationDropdown.classList.remove('show');
            }
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        // Open profile picture in modal
        function openProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Close profile picture modal
        function closeProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Allow ESC key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProfileModal();
                const messageDropdown = document.getElementById('messageDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                if (messageDropdown) messageDropdown.classList.remove('show');
                if (notificationDropdown) notificationDropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const profilePic = document.querySelector('.profile-pic');
            const messageDropdown = document.getElementById('messageDropdown');
            const messageBtn = document.querySelector('.message-btn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationBtn = document.querySelector('.notification-btn');
            
            if (dropdown && !dropdown.contains(event.target) && event.target !== profilePic) {
                dropdown.classList.remove('show');
            }

            if (messageDropdown && !messageDropdown.contains(event.target) && event.target !== messageBtn) {
                messageDropdown.classList.remove('show');
            }

            if (notificationDropdown && !notificationDropdown.contains(event.target) && event.target !== notificationBtn) {
                notificationDropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>
