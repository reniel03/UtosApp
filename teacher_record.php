<?php
session_start();

// Handle AJAX request to mark all notifications as read FIRST
if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $teacher_email = $_SESSION['email'] ?? '';
    if ($teacher_email) {
        $_SESSION['notif_last_read_' . $teacher_email] = date('Y-m-d H:i:s');
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'timestamp' => $_SESSION['notif_last_read_' . $teacher_email]]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No email in session']);
    }
    exit();
}

include 'db_connect.php';

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$teacher_email = $_SESSION['email'];

// Get the last read timestamp
$last_read = isset($_SESSION['notif_last_read_' . $teacher_email]) ? $_SESSION['notif_last_read_' . $teacher_email] : null;

// Add rating columns if they don't exist
$conn->query("ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS rating INT DEFAULT NULL");
$conn->query("ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS rated_at DATETIME DEFAULT NULL");

// Fetch teacher's completed tasks with student details
$query = "SELECT t.*, st.student_email, st.is_completed, st.rating, st.rated_at, st.created_at as accepted_at,
          s.first_name, s.middle_name, s.last_name, s.photo as student_photo, s.student_id, s.course, s.year_level
          FROM tasks t
          INNER JOIN student_todos st ON t.id = st.task_id
          LEFT JOIN students s ON st.student_email = s.email
          WHERE t.teacher_email = ? AND st.is_completed = 1
          ORDER BY t.created_at DESC, st.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $teacher_email);
$stmt->execute();
$result = $stmt->get_result();

// Group by task
$tasks_data = [];
while ($row = $result->fetch_assoc()) {
    $task_id = $row['id'];
    if (!isset($tasks_data[$task_id])) {
        $tasks_data[$task_id] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'room' => $row['room'],
            'due_date' => $row['due_date'],
            'due_time' => $row['due_time'],
            'attachments' => $row['attachments'],
            'created_at' => $row['created_at'],
            'students' => []
        ];
    }
    $tasks_data[$task_id]['students'][] = [
        'email' => $row['student_email'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'photo' => $row['student_photo'],
        'student_id' => $row['student_id'],
        'course' => $row['course'],
        'year_level' => $row['year_level'],
        'rating' => $row['rating'],
        'rated_at' => $row['rated_at'],
        'accepted_at' => $row['accepted_at']
    ];
}

$stmt->close();

// Fetch notifications for teachers (students who accepted their tasks)
$notifications = [];
$notification_count = 0;
$notif_query = "SELECT st.*, s.first_name, s.middle_name, s.last_name, s.photo as student_photo, 
          t.title as task_title
          FROM student_todos st
          INNER JOIN tasks t ON st.task_id = t.id
          LEFT JOIN students s ON st.student_email = s.email
          WHERE t.teacher_email = ?
          ORDER BY st.created_at DESC
          LIMIT 20";

$notif_stmt = $conn->prepare($notif_query);
if ($notif_stmt) {
    $notif_stmt->bind_param('s', $teacher_email);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    while ($row = $notif_result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $notif_stmt->close();
}

// Count only unread notifications (newer than last read time)
$notification_count = 0;
if ($last_read) {
    foreach ($notifications as $notif) {
        if ($notif['created_at'] > $last_read) {
            $notification_count++;
        }
    }
} else {
    $notification_count = count($notifications);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Task Records - Teacher Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
        html {
            scroll-behavior: smooth;
            overflow-y: scroll;
            overflow-x: hidden;
        }

        body {
            background: linear-gradient(135deg, #f5f5f5 0%, #fff5f5 100%);
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
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

        .nav-bar {
            background-color: #ff0000;
            padding: 60px 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
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
            transition: all 0.3s ease;
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

        .message-btn svg {
            transform: translateY(-4px) scale(1.1);
        }

        .icon-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.35);
            background: #ff1a1a;
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

        .profile-info {
            padding: 1.8rem;
            border-bottom: 3px solid #ff0000;
            background: linear-gradient(145deg, #ff0000, #ff3333);
            border-radius: 15px;
            margin-bottom: 1.5rem;
            color: white;
        }

        .profile-image-container {
            position: relative;
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
        }

        .detail-value {
            color: rgba(255, 255, 255, 0.98);
            font-size: 1.5em !important;
            font-weight: 700;
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
        }

        .profile-menu a:last-child {
            margin-top: 1rem;
            border-top: 3px solid #ff0000;
            padding-top: 1.5rem;
            color: #ff0000;
            font-weight: 600;
            font-size: 1.5em;
        }

        /* Settings Modal Styles */
        .settings-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 4000;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .settings-modal.show {
            display: flex;
            opacity: 1;
            justify-content: center;
            align-items: center;
        }

        .settings-container {
            width: 600px;
            max-width: 95%;
            max-height: 90vh;
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 80px rgba(255, 0, 0, 0.3), 0 0 0 1px rgba(255, 0, 0, 0.1);
            overflow: hidden;
            animation: settingsSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes settingsSlideIn {
            0% { opacity: 0; transform: scale(0.8) translateY(50px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .settings-header {
            background: linear-gradient(135deg, #ff0000, #ff4444, #ff6666);
            padding: 30px 35px;
            position: relative;
            overflow: hidden;
        }

        .settings-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: shimmerSettings 3s infinite;
        }

        @keyframes shimmerSettings {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
        }

        .settings-header-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .settings-title {
            color: white;
            font-size: 2em;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .settings-title-icon {
            font-size: 1.2em;
            animation: spinGear 4s linear infinite;
        }

        @keyframes spinGear {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .settings-close {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            font-size: 1.8em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .settings-close:hover {
            background: white;
            color: #ff0000;
            transform: rotate(90deg) scale(1.1);
        }

        .settings-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .settings-body::-webkit-scrollbar { width: 8px; }
        .settings-body::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 10px;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .settings-section:last-child { margin-bottom: 0; }

        .settings-section-title {
            font-size: 1.1em;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .settings-section-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1em;
        }

        .profile-photo-section {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 20px;
            border: 2px solid #fee;
        }

        .current-photo-container { position: relative; }

        .current-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .current-photo:hover { transform: scale(1.05); }

        .photo-edit-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 30px;
            height: 30px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9em;
            border: 3px solid white;
            box-shadow: 0 4px 15px rgba(255,0,0,0.4);
        }

        .photo-upload-area { flex: 1; }

        .photo-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 25px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.3);
        }

        .photo-upload-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.4);
        }

        .photo-upload-hint {
            margin-top: 10px;
            font-size: 0.85em;
            color: #888;
        }

        .password-form {
            padding: 20px;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 20px;
            border: 2px solid #fee;
        }

        .password-input-group { margin-bottom: 20px; }
        .password-input-group:last-of-type { margin-bottom: 25px; }

        .password-label {
            display: block;
            font-size: 0.95em;
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
        }

        .password-input-wrapper { position: relative; }

        .password-input {
            width: 100%;
            padding: 15px 50px 15px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .password-input:focus {
            outline: none;
            border-color: #ff0000;
            box-shadow: 0 0 0 4px rgba(255, 0, 0, 0.1);
        }

        .password-strength {
            margin-top: 10px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }

        .password-strength-bar.weak { width: 33%; background: #ff4444; }
        .password-strength-bar.medium { width: 66%; background: #ffaa00; }
        .password-strength-bar.strong { width: 100%; background: #00cc66; }

        .password-strength-text {
            font-size: 0.8em;
            margin-top: 5px;
            font-weight: 600;
        }

        .password-strength-text.weak { color: #ff4444; }
        .password-strength-text.medium { color: #ffaa00; }
        .password-strength-text.strong { color: #00cc66; }

        .save-password-btn {
            width: 100%;
            padding: 15px 25px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .save-password-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.4);
        }

        .save-password-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .settings-message {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 10px;
            animation: messageSlide 0.3s ease;
        }

        @keyframes messageSlide {
            0% { opacity: 0; transform: translateY(-10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .settings-message.success {
            display: flex;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .settings-message.error {
            display: flex;
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        /* Main Content */
        .main-content {
            padding-top: 20px;
            padding-bottom: 60px;
            min-height: 100vh;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            animation: fadeInDown 0.8s ease;
            margin-top: 10px;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-title {
            font-size: 5em;
            color: #ff0000;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 0 4px 20px rgba(255, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .page-subtitle {
            font-size: 1.8em;
            color: #666;
            font-weight: 500;
        }

        /* Stats Summary */
        .stats-summary {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 50px;
            flex-wrap: nowrap;
            padding: 0 20px;
            flex-direction: row;
        }

        .stat-card {
            background: white;
            border-radius: 25px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(255, 0, 0, 0.1);
            border: 3px solid transparent;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease backwards;
            width: 250px;
            height: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card:hover {
            transform: translateY(-8px);
            border-color: #ff0000;
            box-shadow: 0 20px 50px rgba(255, 0, 0, 0.2);
        }

        .stat-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .stat-card:nth-child(1) .stat-icon,
        .stat-card:nth-child(2) .stat-icon,
        .stat-card:nth-child(3) .stat-icon {
            position: relative;
            top: 10px;
        }

        .stat-number {
            font-size: 3.5em;
            font-weight: 800;
            color: #ff0000;
            line-height: 1; 
        }

        .stat-label {
            font-size: 1.1em;
            color: #888;
            font-weight: 600;
            margin-top: 8px;
        }

        /* Tasks Container */
        .tasks-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 70px 100px;
        }

        /* Task Card */
        .task-record-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 60px;
            width: 100%;
        }
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: slideInCard 0.5s ease backwards;
            border: 1px solid #e8e8e8;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .task-record-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(255, 0, 0, 0.15);
        }

        @keyframes slideInCard {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .task-record-header {
            background: white;
            padding: 0;
            color: #333;
            position: relative;
            overflow: visible;
            display: none;
            gap: 40px;
            align-items: center;
            padding: 40px;
        }

        .task-record-header::before {
            display: none;
        }

        .task-record-image {
            width: 200px;
            height: 200px;
            background: #f5f5f5;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .task-record-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .task-record-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .task-record-title {
            font-size: 1.8em;
            font-weight: 600;
            color: #333;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .task-meta-grid {
            display: flex;
            gap: 28px;
            font-size: 1.25em;
            flex-wrap: wrap;
        }

        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #888;
            opacity: 1;
        }

        .task-record-price {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
            min-width: 160px;
        }

        .task-record-price-label {
            font-size: 1.15em;
            color: #888;
        }

        .task-record-students {
            font-size: 2.4em;
            font-weight: 700;
            color: #ff0000;
        }

        .task-record-body {
            padding: 12px;
            border-top: 1px solid #f0f0f0;
            display: none;
        }

        /* Task Body Card */
        .task-body-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff8f8 100%);
            border: 2px solid #ffcccc;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 15px;
            position: relative;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }
        
        .task-body-card .task-body-image {
            grid-column: 1;
            grid-row: 2;
        }
        
        .task-body-card .task-body-meta {
            grid-column: 2;
            grid-row: 2;
            width: 100%;
        }
        
        .task-body-card .task-body-header {
            grid-column: 1 / -1;
            grid-row: 1;
        }
        
        .task-body-card .task-progress-tracker {
            grid-column: 1 / -1;
            grid-row: 3;
        }
        
        .task-body-card .task-body-student-info {
            grid-column: 1 / -1;
            grid-row: 4;
            margin-top: -15px;
        }
        
        .task-body-card .task-body-rate-btn {
            grid-column: 1 / -1;
            grid-row: 5;
        }

        .task-body-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.1);
            border-color: #ff0000;
        }

        .task-body-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            gap: 15px;
            border-bottom: 1px solid #d3d3d3;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }

        .task-body-completed-badge {
            color: #ff0000;
            padding: 12px 20px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .task-body-student-email {
            font-size: 0.95em;
            color: #666;
            font-weight: 600;
            width: 100%;
        }

        .task-body-image {
            width: 80px;
            height: 80px;
            aspect-ratio: 1/1;
            background: #f5f5f5;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            margin-top: 15px;
        }

        /* Progress Tracker */
        .task-progress-tracker {
            padding: 25px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            max-width: 500px;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
            align-items: center;
            margin-left: auto;
            margin-right: auto;
            display: flex;
            justify-content: center;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin-bottom: 0;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 12%;
            right: 22.4%;
            height: 6px;
            background: linear-gradient(to right, #ff0000 0%, #ff0000 100%);
            z-index: 0;
            transition: background 0.3s ease;
        }
        .progress-steps.has-progress::before {
            background: linear-gradient(to right, #ff0000 0%, #ff0000 70%, #ff0000 70%, #ff0000 100%);
        }
        .progress-steps.progress-complete::before {
            background: linear-gradient(to right, #ff0000 0%, #ff0000 100%);
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 2;
            flex: 1;
            transition: all 0.3s ease;
            padding: 0 25px;
        }
        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            background: #f5f5f5;
            border: 2px solid #ddd;
            transition: all 0.3s ease;
            position: relative;
            z-index: 3;
        }
        .step-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            z-index: 3;
        }
        .step-icon svg {
            width: 50px;
            height: 50px;
            transition: all 0.3s ease;
        }
        .progress-step.active .step-icon svg {
            transform: scale(1.1);
        }
        .progress-step.pending.has-applications .step-icon svg {
            filter: drop-shadow(0 4px 12px rgba(255, 0, 0, 0.3)) !important;
            color: #ff0000 !important;
        }
        .progress-step.pending.has-applications .step-icon svg path {
            fill: #ff0000 !important;
            stroke: #ff0000 !important;
        }
        .step-label {
            font-size: 0.9em;
            font-weight: 600;
            color: #666;
            text-align: center;
            white-space: nowrap;
        }
        .progress-step.active .step-label {
            color: #ff0000;
            font-weight: 600;
        }
        .progress-step.completed .step-label {
            color: #ff0000;
            font-weight: 600;
        }
        .progress-step.completed .step-icon svg rect {
            display: none;
        }
        .progress-line {
            display: none;
        }
        .progress-line.active {
            display: none;
        }

        /* Progress Bar */
        .progress-bar-wrapper {
            display: none;
        }
        .progress-bar {
            display: none;
            width: 0%;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }
        .progress-bar.progress-33 {
            width: 33%;
        }
        .progress-bar.progress-66 {
            width: 66%;
        }
        .progress-bar.progress-100 {
            width: 100%;
        }

        .task-body-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .task-body-meta-item {
            display: flex;
            align-items: center;
            gap: 2px;
            font-size: 0.95em;
        }

        .task-body-meta-item .meta-icon {
            font-size: 1.2em;
            min-width: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .task-body-meta-item .meta-label {
            color: #666;
            font-weight: 600;
            min-width: 85px;
        }

        .task-body-meta-item .meta-value {
            color: #ff0000;
            font-weight: 700;
        }

        .task-body-student-info {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            padding-top: 12px;
            border-top: 2px solid #f3f3f3;
        }

        .task-body-student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ff0000;
            flex-shrink: 0;
        }

        .task-body-student-avatar-wrapper {
            position: relative;
            width: 50px;
            height: 50px;
            flex-shrink: 0;
        }

        .task-body-student-avatar-default {
            position: absolute;
            top: 0;
            left: 0;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #ff0000;
            background: #fff5f5;
            display: none;
        }

        .task-body-student-details {
            flex: 1;
        }

        .task-body-student-name {
            font-size: 1em;
            font-weight: 700;
            color: #333;
        }

        .task-body-student-meta {
            display: flex;
            gap: 10px;
            font-size: 0.85em;
            color: #999;
            margin-top: 5px;
        }

        .task-body-rate-btn {
            background: white;
            color: #ff0000;
            border: 2px solid #ff0000;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9em;
            cursor: pointer;
            transition: all 0.3s ease;
            width: auto;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            bottom: 20px;
            right: 20px;
        }

        .task-body-rate-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(255, 0, 0, 0.3);
            background: #fff5f5;
        }

        .task-body-rate-btn:active {
            transform: scale(0.98);
        }

        /* Students Section */
        .students-section {
            margin-top: 0;
            display: none;
        }

        .students-section-title {
            font-size: 1.2em;
            color: #ff0000;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ffcccc;
        }

        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }

        /* Student Card */
        .student-card {
            background: linear-gradient(145deg, #ffffff, #fff8f8);
            border-radius: 25px;
            padding: 30px;
            border: 3px solid #f0f0f0;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #ff0000, #ff6666, #ff0000);
            background-size: 200% 100%;
            animation: gradientMove 3s ease infinite;
        }

        @keyframes gradientMove {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .student-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: #ff0000;
            box-shadow: 0 20px 50px rgba(255, 0, 0, 0.2);
        }

        .student-card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .student-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.25);
            transition: all 0.3s ease;
        }

        .student-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(255, 0, 0, 0.35);
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-size: 1.5em;
            font-weight: 800;
            color: #222;
            margin-bottom: 5px;
        }

        .student-email {
            font-size: 1em;
            color: #888;
            margin-bottom: 8px;
        }

        .student-details {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .student-detail-badge {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 700;
        }

        .completed-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.95em;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        /* Star Rating System */
        .rating-section {
            margin-top: 0;
            padding: 0;
            border-top: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 25px;
        }

        .rating-label {
            font-size: 1.1em;
            font-weight: 700;
            color: #333;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, #ffc107, #ffb300);
            border-radius: 16px 16px 0 0;
            color: white;
            margin: -20px -20px 0 -20px;
        }

        .star-rating {
            display: flex;
            gap: 12px;
            flex-direction: row;
            justify-content: center;
            align-items: center;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            cursor: pointer;
            font-size: 3em;
            color: #ddd;
            transition: all 0.2s ease;
            text-shadow: 0 2px 5px rgba(0,0,0,0.1);
            line-height: 1;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
            transform: scale(1.1);
            text-shadow: 0 4px 15px rgba(255, 193, 7, 0.5);
        }

        .star-rating.rated label {
            cursor: default;
        }

        .current-rating {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 15px;
        }

        .rating-stars {
            display: flex;
            gap: 5px;
        }

        .rating-stars .star {
            font-size: 1.8em;
            color: #ffc107;
            text-shadow: 0 2px 8px rgba(255, 193, 7, 0.4);
        }

        .rating-stars .star.empty {
            color: #ddd;
            text-shadow: none;
        }

        .rating-text {
            font-size: 1.1em;
            color: #666;
            font-weight: 600;
        }

        /* Rating Submit Section */
        .rating-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: calc(100% + 40px);
            margin-left: -20px;
            margin-right: -20px;
            align-items: center;
            padding: 15px 20px 25px 20px;
            box-sizing: border-box;
            background: white;
        }

        .rating-happy-star {
            font-size: 3.5em;
            margin: 0;
            padding: 0;
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
            width: calc(100% + 40px);
            margin-left: -20px;
            margin-right: -20px;
            height: 110px;
            background: linear-gradient(135deg, #ffc107, #ffb300);
            position: relative;
            z-index: 2;
        }

        .rating-happy-star::after {
            content: '';
            position: absolute;
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
        }

        .rating-happy-star::before {
            content: '';
            position: absolute;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 50px;
            background: white;
            clip-path: polygon(0 40%, 2% 35%, 4% 32%, 6% 30%, 8% 28%, 10% 27%, 12% 28%, 14% 30%, 16% 33%, 18% 37%, 20% 40%, 22% 42%, 24% 43%, 26% 42%, 28% 40%, 30% 37%, 32% 35%, 34% 34%, 36% 35%, 38% 37%, 40% 40%, 42% 42%, 44% 43%, 46% 42%, 48% 40%, 50% 37%, 52% 35%, 54% 34%, 56% 35%, 58% 37%, 60% 40%, 62% 42%, 64% 43%, 66% 42%, 68% 40%, 70% 37%, 72% 35%, 74% 34%, 76% 35%, 78% 37%, 80% 40%, 82% 42%, 84% 43%, 86% 42%, 88% 40%, 90% 37%, 92% 35%, 94% 32%, 96% 30%, 98% 28%, 100% 27%, 100% 100%, 0 100%);
        }

        .rating-label-text {
            font-size: 1.1em;
            font-weight: 700;
            color: #333;
            margin: 20px 20px 15px 20px;
            padding: 0;
            text-align: center;
            width: auto;
            background: transparent;
        }

        /* Container wrapper for equal width items */
        .rating-container-wrapper {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 0;
            align-items: stretch;
            box-sizing: border-box;
        }

        .rating-container-wrapper .rating-happy-star {
            flex: 1;
            width: 100%;
            margin: 0;
            padding: 20px;
            height: auto;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0;
            position: relative;
            box-sizing: border-box;
        }

        .rating-container-wrapper .rating-happy-star::before {
            display: none;
        }

        .rating-container-wrapper .rating-happy-star::after {
            display: none;
        }

        .rating-container-wrapper .rating-label-text {
            flex: 1;
            width: 100%;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0;
            box-sizing: border-box;
        }

        .rating-container-wrapper .modal-star-rating {
            flex: 1;
            width: 100%;
            margin: 0;
            padding: 20px;
            background: white;
            display: flex;
            gap: 8px;
            flex-direction: row-reverse;
            justify-content: center;
            align-items: center;
            border-radius: 0;
            box-sizing: border-box;
        }

        .rating-container-wrapper .modal-star-rating label {
            font-size: 0;
            padding: 0;
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rating-container-wrapper .modal-star-rating .star-svg {
            width: 50px;
            height: 50px;
            fill: #ddd;
            transition: all 0.2s ease;
        }

        .rating-container-wrapper .modal-star-rating input:checked ~ label .star-svg,
        .rating-container-wrapper .modal-star-rating label:hover .star-svg,
        .rating-container-wrapper .modal-star-rating label:hover ~ label .star-svg,
        .rating-container-wrapper .modal-star-rating input:checked ~ label ~ label .star-svg {
            fill: #ff0000;
            transform: scale(1.15);
        }

        .modal-rating-stars {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }

        .modal-rating-stars .star-svg {
            width: 45px;
            height: 45px;
        }

        .modal-rating-stars .star-svg.filled {
            fill: #ff0000;
        }

        .modal-rating-stars .star-svg.empty {
            fill: #ddd;
        }

        .rating-container-wrapper .rating-actions {
            flex: 1;
            width: 100%;
            margin: 0;
            padding: 20px;
            background: white;
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
            align-items: center;
            border-radius: 0;
            box-sizing: border-box;
        }

        @keyframes starPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .rating-submit-btn {
            width: 85%;
            max-width: 280px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #ff0000, #cc0000);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.3);
            box-sizing: border-box;
        }

        .rating-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.4);
        }

        .rating-skip-btn {
            background: none;
            border: none;
            color: #ff0000;
            font-size: 0.95em;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 6px 0;
            margin-top: 0;
        }

        .rating-skip-btn:hover {
            color: #999;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 40px;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .empty-icon {
            font-size: 8em;
            margin-bottom: 30px;
            animation: bounce 2s ease infinite;
            color: #ff0000;
            stroke: #ff0000;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .empty-title {
            font-size: 2.5em;
            color: #333;
            font-weight: 800;
            margin-bottom: 15px;
            margin-top: -30px;
        }

        .empty-subtitle {
            font-size: 1.3em;
            color: #888;
            margin-top: 0px;
        }

        /* Notification & Message Dropdowns */
        .notification-dropdown {
            position: absolute;
            top: 120%;
            right: 0;
            width: 450px;
            max-height: 600px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(255, 0, 0, 0.2), 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 3px solid #ff0000;
            padding: 0;
            display: none;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 1200;
            overflow: hidden;
        }

        .message-dropdown {
            position: absolute;
            top: 120%;
            right: 0;
            width: 400px;
            max-height: 550px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            border: 3px solid #ff0000;
            padding: 0;
            display: none;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            z-index: 1200;
            overflow: hidden;
        }

        .notification-dropdown.show, .message-dropdown.show {
            display: block;
            opacity: 1;
            transform: translateY(6px);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-bottom: none;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-bottom: none;
        }

        .notification-header h4 {
            margin: 0;
            font-size: 1.4em;
            color: white;
            font-weight: 800;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-header h4::before {
            content: '🔔';
            font-size: 1.2em;
        }

        .message-header h4 {
            margin: 0;
            font-size: 1.4em;
            color: white;
            font-weight: 800;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-header h4::before {
            content: '💬';
            font-size: 1.2em;
        }

        .message-pill {
            background: white;
            color: #ff0000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 800;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .notification-count-badge {
            background: white;
            color: #ff0000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 800;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .notification-actions button {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 8px 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-actions button:hover {
            background: white;
            color: #ff0000;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .notification-list {
            max-height: 480px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0;
            padding: 15px;
        }

        .notification-list::-webkit-scrollbar {
            width: 6px;
        }

        .notification-list::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 10px;
        }

        .notification-list::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 10px;
        }

        .notification-empty {
            text-align: center;
            padding: 50px 20px;
            color: #999;
            font-weight: 600;
            border: 2px dashed #eee;
            border-radius: 16px;
            background: linear-gradient(135deg, #fafafa, #fff5f5);
            margin: 10px;
        }

        .notification-empty::before {
            content: '📭';
            display: block;
            font-size: 3em;
            margin-bottom: 15px;
        }

        .message-empty {
            text-align: center;
            padding: 50px 20px;
            color: #888;
            font-weight: 600;
            background: linear-gradient(135deg, #fafafa, #fff5f5);
        }

        .message-empty::before {
            content: '💬';
            display: block;
            font-size: 3em;
            margin-bottom: 15px;
        }

        .message-search {
            padding: 15px 20px;
            background: #f8f9fa;
            position: relative;
        }

        .message-search input {
            width: 100%;
            padding: 12px 14px 12px 45px;
            border: 2px solid #e8e8e8;
            border-radius: 25px;
            font-size: 0.95em;
            background: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .message-search input:focus {
            outline: none;
            border-color: #ff0000;
            box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
        }

        .message-search svg {
            position: absolute;
            top: 50%;
            left: 35px;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            opacity: 0.5;
        }

        .message-list {
            max-height: 380px;
            overflow-y: auto;
            padding: 10px;
        }

        .message-list::-webkit-scrollbar {
            width: 6px;
        }

        .message-list::-webkit-scrollbar-track {
            background: #f5f5f5;
            border-radius: 10px;
        }

        .message-list::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 10px;
        }

        .message-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 8px;
            background: #ffffff;
            border: 2px solid transparent;
        }

        .message-card:hover {
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-color: #ffdddd;
            transform: translateX(5px);
        }

        .message-card.has-unread {
            background: linear-gradient(135deg, #fff0f0, #ffffff);
            border-color: #ff0000;
        }

        .message-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ff0000;
            flex-shrink: 0;
        }

        .message-meta {
            flex: 1;
            min-width: 0;
        }

        .message-meta h5 {
            margin: 0 0 4px 0;
            font-size: 1em;
            font-weight: 700;
            color: #222;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-meta p {
            margin: 0;
            font-size: 0.85em;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-time {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            flex-shrink: 0;
        }

        .message-time span {
            font-size: 0.75em;
            color: #999;
            font-weight: 600;
        }

        .message-unread-badge {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
        }

        .message-dot {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 22px;
            height: 22px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            color: white;
            font-weight: bold;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(255, 0, 0, 0.4);
        }

        /* Chat Modal */
        .chat-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 5000;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .chat-modal.show {
            display: flex;
            opacity: 1;
            justify-content: center;
            align-items: center;
        }

        .chat-container {
            width: 500px;
            max-width: 95%;
            height: 600px;
            max-height: 85vh;
            background: white;
            border-radius: 25px;
            box-shadow: 0 30px 80px rgba(255, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: chatSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes chatSlideIn {
            0% { opacity: 0; transform: scale(0.9) translateY(20px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .chat-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
            border: 3px solid rgba(255,255,255,0.3);
            overflow: hidden;
        }

        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chat-user-details {
            color: white;
        }

        .chat-user-name {
            font-size: 1.2em;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .chat-user-status {
            font-size: 0.85em;
            opacity: 0.9;
        }

        .chat-close-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .chat-close-btn:hover {
            background: white;
            color: #ff0000;
            transform: rotate(90deg);
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 10px;
        }

        .chat-empty {
            text-align: center;
            padding: 60px 20px;
            color: #888;
            font-weight: 600;
        }

        .chat-message {
            max-width: 80%;
            padding: 12px 18px;
            border-radius: 18px;
            font-size: 0.95em;
            line-height: 1.4;
            position: relative;
            animation: messageSlide 0.3s ease;
        }

        @keyframes messageSlide {
            0% { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .chat-message.sent {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 6px;
        }

        .chat-message.received {
            background: white;
            color: #333;
            align-self: flex-start;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .chat-message-time {
            display: block;
            font-size: 0.7em;
            opacity: 0.7;
            margin-top: 6px;
            text-align: right;
        }

        .chat-input-area {
            padding: 15px 20px;
            background: white;
            border-top: 2px solid #f0f0f0;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .chat-input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid #e8e8e8;
            border-radius: 25px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .chat-input:focus {
            outline: none;
            border-color: #ff0000;
            box-shadow: 0 0 0 3px rgba(255, 0, 0, 0.1);
        }

        .chat-send-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .chat-send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.4);
        }

        .chat-send-btn svg {
            width: 22px;
            height: 22px;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            padding-right: 45px;
            border-radius: 16px;
            border: 2px solid transparent;
            background: linear-gradient(135deg, #ffffff, #fff8f8);
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 10px;
            position: relative;
            overflow: hidden;
        }

        .notification-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 0 4px 4px 0;
        }

        .notification-item:hover {
            border-color: #ff0000;
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.15);
            background: linear-gradient(135deg, #fff5f5, #ffffff);
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-dismiss {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f0f0f0;
            border: none;
            color: #888;
            font-size: 1.2em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            opacity: 0;
        }

        .notification-item:hover .notification-dismiss {
            opacity: 1;
        }

        .notification-dismiss:hover {
            background: #ff0000;
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        @keyframes slideOutRight {
            0% { opacity: 1; transform: translateX(0); }
            100% { opacity: 0; transform: translateX(100%); }
        }

        /* Notification Detail Modal */
        .notif-detail-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 5000;
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .notif-detail-modal.show {
            display: flex;
            opacity: 1;
            justify-content: center;
            align-items: center;
        }

        .notif-detail-container {
            width: 500px;
            max-width: 95%;
            background: white;
            border-radius: 25px;
            box-shadow: 0 30px 80px rgba(255, 0, 0, 0.3);
            overflow: hidden;
            animation: notifDetailSlide 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes notifDetailSlide {
            0% { opacity: 0; transform: scale(0.8) translateY(30px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .notif-detail-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            padding: 25px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notif-detail-header h3 {
            color: white;
            margin: 0;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notif-detail-close {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .notif-detail-close:hover {
            background: white;
            color: #ff0000;
            transform: rotate(90deg);
        }

        .notif-detail-body { padding: 30px; }

        .notif-detail-student {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .notif-detail-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.3);
        }

        .notif-detail-info h4 {
            margin: 0 0 5px 0;
            font-size: 1.4em;
            color: #222;
        }

        .notif-detail-info p { margin: 0; color: #666; font-size: 1em; }

        .notif-detail-task {
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            padding: 20px;
            border-radius: 15px;
            border: 2px solid #fee;
        }

        .notif-detail-task-label {
            font-size: 0.85em;
            color: #888;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .notif-detail-task-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #ff0000;
            margin-bottom: 10px;
        }

        .notif-detail-task-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .notif-detail-time {
            text-align: center;
            margin-top: 20px;
            color: #888;
            font-size: 0.9em;
        }

        .notification-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ff0000;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.25);
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .notification-item:hover .notification-avatar {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.35);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-student-name {
            font-size: 1.1em;
            font-weight: 800;
            color: #222;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notification-new-badge {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: 700;
            text-transform: uppercase;
            animation: pulse-badge 2s infinite;
        }

        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .notification-task-title {
            font-size: 0.95em;
            color: #666;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-task-title::before {
            content: '📋';
            font-size: 0.9em;
        }

        .notification-message {
            font-size: 0.85em;
            color: #28a745;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-message::before {
            content: '✓';
            background: #28a745;
            color: white;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
        }

        .notification-time {
            font-size: 0.8em;
            color: #999;
            font-weight: 600;
            white-space: nowrap;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .notification-time-text {
            background: #f5f5f5;
            padding: 4px 10px;
            border-radius: 10px;
        }

        .notification-dot {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4em;
            color: white;
            font-weight: bold;
            border: 3px solid white;
            animation: pulse 2s infinite;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.4);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Profile Modal */
        .profile-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.95);
            z-index: 3000;
        }

        .profile-modal.show {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .profile-modal-close {
            position: fixed;
            top: 30px;
            right: 40px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            color: white;
            font-size: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            z-index: 3001;
        }

        .profile-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .profile-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .profile-modal-image {
            max-width: 85vw;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
        }

        /* Success Animation */
        .rating-success {
            animation: ratingSuccess 0.6s ease;
        }

        @keyframes ratingSuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Student Modal */
        .student-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85);
            z-index: 4000;
            backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
        }

        .student-modal.show {
            display: flex;
            animation: fadeInModal 0.3s ease;
        }

        @keyframes fadeInModal {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .student-modal-close {
            display: none;
        }

        .student-modal-close:hover {
            display: none;
        }

        .student-modal-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            width: auto;
            height: auto;
        }

        .student-card-modal {
            background: white;
            border-radius: 24px;
            padding: 0;
            border: none;
            position: relative;
            overflow: visible;
            max-width: 600px;
            width: 90%;
            animation: zoomInModal 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .student-card-modal::before {
            display: none;
        }

        @keyframes zoomInModal {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .student-card-modal .student-card-header {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            margin-top: 25px;
            width: 100%;
            text-align: center;
        }

        .student-card-modal .student-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.25);
            transition: all 0.3s ease;
            display: block;
            margin: 0 auto;
        }

        .student-card-modal .student-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 35px rgba(255, 0, 0, 0.35);
        }

        .student-card-modal .student-info {
            text-align: center;
            width: 100%;
        }

        .student-card-modal .student-name {
            font-size: 1.6em;
            font-weight: 800;
            color: #222;
            margin-bottom: 5px;
            text-align: center;
        }

        .student-card-modal .student-email {
            font-size: 1em;
            color: #888;
            margin-bottom: 10px;
            text-align: center;
        }

        .student-card-modal .student-details {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }

        .student-card-modal .student-detail-badge {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 700;
        }

        .student-card-modal .completed-badge {
            display: none;
        }

        .student-card-modal .rating-section {
            margin-top: 0;
            margin-left: -20px;
            margin-right: -20px;
            margin-bottom: 0;
            padding: 0;
            border-top: none;
            width: calc(100% + 40px);
            display: flex;
            flex-direction: column;
            gap: 0;
            align-items: center;
            box-sizing: border-box;
            position: relative;
        }

        .student-card-modal .rating-label {
            display: none;
        }

        .modal-star-rating {
            display: flex;
            gap: 15px;
            flex-direction: row-reverse;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 20px 20px 15px 20px;
            box-sizing: border-box;
            background: white;
        }

        .modal-star-rating input {
            display: none;
        }

        .modal-star-rating label {
            cursor: pointer;
            transition: all 0.2s ease;
            text-shadow: none;
            line-height: 1;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-star-rating label .star-svg {
            width: 50px;
            height: 50px;
            fill: #ddd;
            transition: all 0.2s ease;
        }

        .modal-star-rating label:hover .star-svg,
        .modal-star-rating label:hover ~ label .star-svg,
        .modal-star-rating input:checked ~ label .star-svg {
            fill: #ff0000;
            transform: scale(1.15);
        }

        .modal-star-rating label:hover,
        .modal-star-rating label:hover ~ label,
        .modal-star-rating input:checked ~ label {
            transform: scale(1.15);
        }

        .modal-star-rating.rated label {
            cursor: default;
        }

        .modal-current-rating {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .modal-rating-stars {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .modal-rating-stars .star {
            font-size: 2.8em;
            color: #ffc107;
            text-shadow: 0 4px 15px rgba(255, 193, 7, 0.5);
        }

        .modal-rating-stars .star.empty {
            color: #ddd;
            text-shadow: none;
        }



        /* Make student card preview clickable */
        .student-card-preview {
            cursor: pointer;
            position: relative;
        }

        .student-card-preview::after {
            content: '👆 Click to Rate';
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 700;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .student-card-preview.rated::after {
            content: '👆 View Rating';
        }

        .student-card-preview:hover::after {
            opacity: 1;
            bottom: 20px;
        }

        @media (max-width: 900px) {
            .main-content {
                padding-top: 180px;
            }

            .page-title {
                font-size: 2.5em;
            }

            .tasks-container {
                padding: 0 20px;
            }

            .students-grid {
                grid-template-columns: 1fr;
            }

            .task-body-card {
                padding: 15px;
            }

            .task-body-meta {
                padding: 12px;
            }

            .nav-links {
                gap: 30px;
            }

            .nav-links a {
                font-size: 1.5em;
            }
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

        /* ====== MOBILE RESPONSIVE STYLES ====== */
        @media (max-width: 768px) {
            html { font-size: 14px; }
            .nav-bar { padding: 15px 12px; flex-wrap: wrap; }
            .nav-links { gap: 15px; width: 100%; flex-direction: column; display: none; }
            .nav-links a { font-size: 1.1em; }
            .nav-right { gap: 8px; }
            .icon-btn { width: 50px; height: 50px; padding: 12px; }
            .icon-btn svg { width: 35px; height: 35px; }
            .profile-pic { width: 50px; height: 50px; }
            .main-content { padding: 20px 15px 50px 15px; }
            .records-container { padding: 15px; }
            .table-wrapper { overflow-x: auto; }
            table { font-size: 0.9em; }
            .page-title { font-size: 3.5em; }
            .page-subtitle { font-size: 1.5em; }
            .stats-summary { gap: 15px; flex-direction: row; padding: 0 15px; }
            .stat-card { width: 200px; height: 200px; padding: 15px; }
            .stat-number { font-size: 2.5em; }
            .tasks-container { padding: 45px 65px; }
            .task-record-card { margin-bottom: 40px; }
            .task-record-header { padding: 24px; gap: 20px; }
            .task-record-image { width: 150px; height: 150px; }
            .task-record-title { font-size: 1.5em; }
            .task-meta-grid { gap: 20px; font-size: 1.15em; }
            .task-record-price { min-width: 140px; }
            .task-record-price-label { font-size: 1.05em; }
            .task-record-students { font-size: 2em; }
            .task-record-body { padding: 15px; display: block; }
            .task-description { display: none; }
            .students-section { display: block; margin-top: 15px; }
            .students-grid { grid-template-columns: 1fr; gap: 15px; }
            .task-body-card { padding: 15px; gap: 12px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden; }
            .task-body-image { width: 80px; height: 80px; }
            .task-body-meta-item { gap: 2px; }
            .task-body-student-avatar { width: 50px; height: 50px; }
            .task-body-rate-btn { padding: 10px 15px; font-size: 0.9em; }
            .student-card { padding: 20px; border-radius: 18px; }
            .student-card-header { gap: 15px; }
            .student-avatar { width: 70px; height: 70px; }
            .student-name { font-size: 1.2em; }
            .student-email { font-size: 0.9em; }
            .rating-section { margin-top: 15px; padding-top: 15px; }
            .star-rating label { font-size: 2em; }
            .task-progress-tracker { max-width: 100%; width: 100%; box-sizing: border-box; overflow: hidden; }
            .task-progress-tracker { max-width: 100%; width: 100%; box-sizing: border-box; overflow: hidden; }
        }

        @media (max-width: 480px) {
            html { font-size: 12px; }
            .nav-bar { padding: 12px 8px; }
            .icon-btn { width: 45px; height: 45px; }
            .icon-btn svg { width: 30px; height: 30px; }
            .profile-pic { width: 45px; height: 45px; }
            .main-content { padding: 15px 12px 40px 12px; }
            .records-container { padding: 10px; }
            table { font-size: 0.85em; }
            .page-title { font-size: 2.5em; }
            .page-subtitle { font-size: 1.2em; }
            .stats-summary { gap: 12px; flex-direction: row; padding: 0 10px; }
            .stat-card { width: 90px; height: 90px; padding: 10px; }
            .stat-number { font-size: 2em; }
            .stat-label { font-size: 0.9em; }
            .tasks-container { padding: 0px 0px; }
            .task-body-card { padding: 12px; gap: 10px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden; }
            .task-body-image { width: 70px; height: 70px; }
            .task-body-meta { padding: 10px; }
            .task-body-meta-item { font-size: 0.9em; gap: 1px; }
            .task-body-student-avatar { width: 45px; height: 45px; }
            .task-body-student-details { flex: 1; }
            .task-body-student-name { font-size: 0.95em; }
            .task-body-student-meta { font-size: 0.8em; }
            .task-body-rate-btn { padding: 10px 12px; font-size: 0.85em; }
            .task-record-card { margin-bottom: 40px; }
            .task-record-header {height: 180px; padding: 28px; gap: 20px; align-items: flex-start; }
            .task-record-image { width: 50px; height: 80px; min-width: 70px; }
            .task-record-details { min-width: 0; }
            .task-record-title { font-size: 1.55em; margin: 0; }
            .task-meta-grid { gap: 14px; font-size: 1.15em; flex-wrap: wrap; }
            .task-meta-item { gap: 7px; }
            .task-record-price { min-width: 130px; text-align: right; }
            .task-record-price-label { font-size: 1.05em; }
            .task-record-students { font-size: 2em; }
            .task-record-body { display: block; padding: 15px; }
            .students-section { display: block; margin-top: 15px; }
            .students-grid { grid-template-columns: 1fr; gap: 12px; }
            .student-card { padding: 15px; border-radius: 12px; }
            .student-card-header { gap: 12px; margin-bottom: 15px; }
            .student-avatar { width: 55px; height: 55px; }
            .student-name { font-size: 1.05em; }
            .student-email { font-size: 0.8em; }
            .student-detail-badge { padding: 4px 8px; font-size: 0.75em; }
            .rating-section { margin-top: 12px; padding-top: 12px; }
            .rating-label { font-size: 0.95em; margin-bottom: 10px; }
            .star-rating label { font-size: 1.6em; gap: 4px; }
            .current-rating { flex-direction: column; gap: 8px; }
            .rated-badge { font-size: 0.8em; }
            .task-progress-tracker { max-width: 100%; width: 100%; box-sizing: border-box; overflow: hidden; }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .nav-bar { padding: 30px 40px; }
            .nav-links a { font-size: 1.5em; }
            .icon-btn { width: 80px; height: 80px; }
            .icon-btn svg { width: 50px; height: 50px; }
            .profile-pic { width: 80px; height: 80px; }
        }

        /* Mobile Navigation Bar */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            background: linear-gradient(to top, #ffffff, #fafafa);
            border-top: 3px solid #f0f0f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 75px;
            z-index: 999;
            box-shadow: 0 -4px 15px rgba(255, 0, 0, 0.08);
            padding: 8px 0;
            gap: 5px;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #666;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex: 1;
            height: 100%;
            gap: 5px;
            outline: none;
            position: relative;
            border-radius: 12px;
            margin: 0 4px;
            padding: 0 8px;
        }

        .bottom-nav-item:hover,
        .bottom-nav-item.active {
            color: #ff0000;
        }

        .bottom-nav-item:hover {
            transform: scale(1.05);
        }

        .bottom-nav-item.active {
            font-weight: 600;
        }

        .bottom-nav-icon {
            font-size: 26px;
            transition: transform 0.3s ease;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bottom-nav-icon svg {
            width: 26px;
            height: 26px;
            stroke: #666 !important;
            fill: none;
            stroke-width: 2;
        }

        .bottom-nav-item:hover .bottom-nav-icon,
        .bottom-nav-item.active .bottom-nav-icon {
            color: #ff0000;
        }

        .bottom-nav-item:hover .bottom-nav-icon svg,
        .bottom-nav-item.active .bottom-nav-icon svg {
            stroke: #ff0000 !important;
        }

        .bottom-nav-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
            opacity: 0.9;
            transition: opacity 0.3s ease;
        }

        .bottom-nav-item:hover .bottom-nav-label {
            opacity: 1;
        }

        /* Body padding for bottom nav */
        body {
            padding-bottom: 80px;
        }

        /* Mobile layout adjustments */
        @media (max-width: 768px) {
            .bottom-nav {
                height: 60px;
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
                font-size: 30px;
            }

            .bottom-nav-icon svg {
                width: 30px;
                height: 30px;
                stroke: #666 !important;
                fill: none;
                stroke-width: 2;
            }

            .bottom-nav-label {
                font-size: 10px;
            }

            body {
                padding-bottom: 85px;
            }
            .bottom-nav {
                height: 65px;
                padding: 8px 0;
            }
                margin: 0 1px;
                padding: 0 3px;
            }

            .bottom-nav-icon {
                font-size: 26px;
            }

            .bottom-nav-icon svg {
                width: 26px;
                height: 26px;
                stroke: #666 !important;
                fill: none;
                stroke-width: 2;
            }

            .bottom-nav-label {
                font-size: 9px;
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



    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Task Records & Ratings</h1>
            <p class="page-subtitle">View completed tasks and rate student performance</p>
        </div>

        <?php 
        $total_tasks = count($tasks_data);
        $total_students = 0;
        $total_rated = 0;
        foreach ($tasks_data as $task) {
            $total_students += count($task['students']);
            foreach ($task['students'] as $student) {
                if ($student['rating']) $total_rated++;
            }
        }
        ?>

        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 1024 1024" fill="#ff0000" version="1.1" xmlns="http://www.w3.org/2000/svg" stroke="#ff0000" style="width:40px;height:40px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M824.8 1003.2H203.2c-12.8 0-25.6-2.4-37.6-7.2-11.2-4.8-21.6-12-30.4-20.8-8.8-8.8-16-19.2-20.8-30.4-4.8-12-7.2-24-7.2-37.6V260c0-12.8 2.4-25.6 7.2-37.6 4.8-11.2 12-21.6 20.8-30.4 8.8-8.8 19.2-16 30.4-20.8 12-4.8 24-7.2 37.6-7.2h94.4v48H203.2c-26.4 0-48 21.6-48 48v647.2c0 26.4 21.6 48 48 48h621.6c26.4 0 48-21.6 48-48V260c0-26.4-21.6-48-48-48H730.4v-48H824c12.8 0 25.6 2.4 37.6 7.2 11.2 4.8 21.6 12 30.4 20.8 8.8 8.8 16 19.2 20.8 30.4 4.8 12 7.2 24 7.2 37.6v647.2c0 12.8-2.4 25.6-7.2 37.6-4.8 11.2-12 21.6-20.8 30.4-8.8 8.8-19.2 16-30.4 20.8-11.2 4.8-24 7.2-36.8 7.2z" fill="#ff0000"></path><path d="M752.8 308H274.4V152.8c0-32.8 26.4-60 60-60h61.6c22.4-44 67.2-72.8 117.6-72.8 50.4 0 95.2 28.8 117.6 72.8h61.6c32.8 0 60 26.4 60 60v155.2m-430.4-48h382.4V152.8c0-6.4-5.6-12-12-12H598.4l-5.6-16c-12-33.6-43.2-56-79.2-56s-67.2 22.4-79.2 56l-5.6 16H334.4c-6.4 0-12 5.6-12 12v107.2zM432.8 792c-6.4 0-12-2.4-16.8-7.2L252.8 621.6c-4.8-4.8-7.2-10.4-7.2-16.8s2.4-12 7.2-16.8c4.8-4.8 10.4-7.2 16.8-7.2s12 2.4 16.8 7.2L418.4 720c4 4 8.8 5.6 13.6 5.6s10.4-1.6 13.6-5.6l295.2-295.2c4.8-4.8 10.4-7.2 16.8-7.2s12 2.4 16.8 7.2c9.6 9.6 9.6 24 0 33.6L449.6 784.8c-4.8 4-11.2 7.2-16.8 7.2z" fill="#ff0000"></path></g></svg></div>
                <div class="stat-number"><?php echo $total_tasks; ?></div>
                <div class="stat-label">Completed<br>Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg fill="#ff0000" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.002 512.002" xml:space="preserve" stroke="#ff0000" style="width:40px;height:40px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M323.188,194.357c7.793,8.624,11.464,20.664,9.06,32.733h34.811v-32.733H323.188z"></path> </g> </g> <g> <g> <path d="M391.191,194.357h-9.967v32.733h9.967c2.665,0,4.826-2.161,4.826-4.826v-23.083 C396.017,196.518,393.856,194.357,391.191,194.357z"></path> </g> </g> <g> <g> <path d="M404.621,147.354h-12.8v32.734h12.8c2.665,0,4.826-2.161,4.826-4.826V152.18 C409.446,149.515,407.286,147.354,404.621,147.354z"></path> </g> </g> <g> <g> <path d="M308.517,147.354c-2.665,0-4.826,2.161-4.826,4.826v23.083c0,2.665,2.161,4.826,4.826,4.826h70.504v-32.734H308.517z"></path> </g> </g> <g> <g> <path d="M386.994,98.674h-69.979v32.734h69.979c2.665,0,4.826-2.161,4.826-4.826V103.5 C391.82,100.835,389.659,98.674,386.994,98.674z"></path> </g> </g> <g> <g> <path d="M290.891,98.674c-2.665,0-4.826,2.161-4.826,4.826v23.083c0,2.665,2.161,4.826,4.826,4.826h12.8V98.674H290.891z"></path> </g> </g> <g> <g> <path d="M308.517,62.164c-2.665,0-4.826,2.161-4.826,4.826v15.529c0,2.664,2.161,4.825,4.826,4.825h70.504v-25.18H308.517z"></path> </g> </g> <g> <g> <path d="M404.621,62.164h-15.843v25.18h15.843c2.665,0,4.826-2.161,4.826-4.826V66.989 C409.446,64.323,407.286,62.164,404.621,62.164z"></path> </g> </g> <g> <g> <circle cx="205.45" cy="41.918" r="41.918"></circle> </g> </g> <g> <g> <path d="M300.442,200.072l-68.058-18.322l-21.907-41.88l34.195,27.105l2.592-31.542c1.388-16.886-11.175-31.699-28.061-33.087 l-54.522-4.481c-16.886-1.388-31.699,11.175-33.087,28.061l-9.688,157.128l12.848,83.776l-31.325,114.255 c-3.57,13.02,4.091,26.469,17.111,30.038c13.004,3.571,26.467-4.082,30.038-17.111l32.702-119.277 c0.909-3.31,1.108-6.776,0.587-10.17L172,287.172l9.511,0.782l29.161,79.505l-24.36,115.027 c-3.216,15.186,8.368,29.516,23.938,29.516c11.303-0.001,21.455-7.887,23.89-19.386l25.817-121.906 c0.953-4.496,0.618-9.167-0.964-13.482l-24.476-66.731l3.852-46.856c-39.683-10.682-25.484-6.86-37.971-10.221 c-8.209-2.21-14.006-8.94-15.452-16.778l-10.573-57.88l26.045,49.788c2.637,5.041,7.262,8.748,12.755,10.228l76.676,20.641 c10.823,2.919,22.032-3.478,24.965-14.376C317.742,214.174,311.305,202.996,300.442,200.072z"></path> </g> </g> </g></svg></div>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Student Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg fill="#ff0000" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 496 496" xml:space="preserve" stroke="#ff0000" style="width:40px;height:40px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <g> <path d="M496,112C496,50.24,445.76,0,384,0H112C50.24,0,0,50.24,0,112s50.24,112,112,112h21.984 C104.56,253.784,88,293.552,88,336c0,88.224,71.776,160,160,160s160-71.776,160-160c0-42.448-16.56-82.216-45.984-112H384 C445.76,224,496,173.76,496,112z M320,460.512C298.792,472.832,274.24,480,248,480s-50.792-7.168-72-19.488V440 c0-39.696,32.296-72,72-72s72,32.304,72,72V460.512z M392,336c0,46.248-22.008,87.36-56,113.728V440c0-48.52-39.48-88-88-88 c-48.52,0-88,39.48-88,88v9.728c-33.992-26.368-56-67.48-56-113.728c0-43.8,19.592-84.448,53.8-112h45.048 C186.496,237.208,176,257.392,176,280c0,39.696,32.296,72,72,72s72-32.304,72-72c0-22.608-10.496-42.792-26.848-56H338.2 C372.408,251.552,392,292.2,392,336z M248,224c30.872,0,56,25.12,56,56s-25.128,56-56,56s-56-25.12-56-56S217.128,224,248,224z M112,208c-52.936,0-96-43.064-96-96s43.064-96,96-96h272c52.936,0,96,43.064,96,96c0,52.936-43.064,96-96,96H112z"></path> <path d="M392,149.88l44.528,23.416l-8.504-49.592l36.04-35.12l-49.8-7.232L392,36.232l-22.264,45.12L320,88.576l-49.736-7.224 L248,36.232l-22.264,45.12L176,88.576l-49.736-7.224L104,36.232l-22.264,45.12l-49.8,7.232l36.04,35.12l-8.504,49.592L104,149.88 l44.528,23.416l-8.504-49.592L176,88.648l35.976,35.064l-8.504,49.592L248,149.88l44.528,23.416l-8.504-49.592L320,88.648 l35.976,35.064l-8.504,49.592L392,149.88z M122.832,118.12l4.448,25.928L104,131.808l-23.28,12.24l4.448-25.928l-18.84-18.36 l26.032-3.784L104,72.392l11.64,23.584l26.032,3.784L122.832,118.12z M266.84,118.12l4.448,25.928L248,131.808l-23.28,12.24 l4.448-25.928l-18.84-18.36l26.032-3.784L248,72.392l11.64,23.584l26.032,3.784L266.84,118.12z M354.336,99.76l26.024-3.784 L392,72.392l11.64,23.584l26.032,3.784l-18.832,18.36l4.448,25.928L392,131.808l-23.28,12.24l4.448-25.928L354.336,99.76z"></path> </g> </g> </g> </g></svg></div>
                <div class="stat-number"><?php echo $total_rated; ?></div>
                <div class="stat-label">Students<br>Rated</div>
            </div>
        </div>

        <div class="tasks-container">
            <?php if (count($tasks_data) > 0): ?>
                <?php $card_index = 0; foreach ($tasks_data as $task): $card_index++; ?>
                    <div class="task-record-card" style="animation-delay: <?php echo ($card_index * 0.15); ?>s;">
                        <div class="task-record-header">
                            <!-- Image Section -->
                            <div class="task-record-image">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:100%; height:100%; color: #ff0000;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/></svg>
                            </div>

                            <!-- Task Details -->
                            <div class="task-record-details">
                                <div class="task-record-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                <div class="task-meta-grid">
                                    <div class="task-meta-item">
                                        <span>📍</span>
                                        <span><?php echo htmlspecialchars($task['room']); ?></span>
                                    </div>
                                    <div class="task-meta-item">
                                        <span>•</span>
                                        <span><?php echo date('M d', strtotime($task['due_date'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Students Count -->
                            <div class="task-record-price">
                                <div class="task-record-price-label">Completed</div>
                            </div>
                        </div>

                        <div class="task-record-body">
                            <div class="students-section">
                                <div class="students-grid">
                                    <?php foreach ($task['students'] as $student): 
                                        $student_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                                        if (empty($student_name)) $student_name = 'Unknown Student';
                                        $student_photo = !empty($student['photo']) ? $student['photo'] : 'profile-default.png';
                                        $unique_id = $task['id'] . '_' . md5($student['email']);
                                    ?>
                                        <div class="task-body-card">
                                            <!-- Header Row with Email and Badge -->
                                            <div class="task-body-header">
                                                <!-- Student Email -->
                                                <div class="task-body-student-email">
                                                    <svg viewBox="0 0 24 24" version="1.1" xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px; display: inline-block; margin-right: 8px; vertical-align: middle;"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" fill="#ff0000"></path></svg>
                                                    <?php echo htmlspecialchars($student['email']); ?>
                                                </div>
                                                <!-- Completed Badge -->
                                                <div class="task-body-completed-badge">
                                                    Completed
                                                </div>
                                            </div>

                                            <!-- Task Image -->
                                            <div class="task-body-image">
                                                <?php 
                                                    $attachment_path = !empty($task['attachments']) ? trim($task['attachments']) : null;
                                                ?>
                                                <?php if (!empty($attachment_path)): ?>
                                                    <img src="<?php echo htmlspecialchars($attachment_path); ?>" alt="Task Attachment" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <?php endif; ?>
                                                <svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" id="_x3C_Layer_x3E_" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000" style="width: 100%; height: 100%; <?php echo empty($attachment_path) ? 'display: flex;' : 'display: none;'; ?> align-items: center; justify-content: center;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="page_x2C__document_x2C__emoji_x2C__No_results_x2C__empty_page"> <g id="XMLID_1521_"> <path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z" fill="#ff0000" id="XMLID_1887_"></path> <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z" fill="#ff0000" id="XMLID_1885_"></path> </g> <g id="XMLID_1337_"> <g id="XMLID_4010_"> <polyline fill="none" id="XMLID_4073_" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#455A64" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" id="XMLID_4072_" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#455A64" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" id="XMLID_4071_" stroke="#455A64" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <g id="XMLID_4068_"> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" id="XMLID_4070_" stroke="#455A64" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" id="XMLID_4069_" stroke="#455A64" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> </g> </g> <g id="XMLID_2974_"> <polyline fill="none" id="XMLID_4009_" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" id="XMLID_4008_" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" id="XMLID_4007_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <g id="XMLID_4004_"> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" id="XMLID_4006_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" id="XMLID_4005_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> </g> </g> </g> </g> </g></svg>
                                            </div>

                                            <!-- Progress Status Animation -->
                                            <div class="task-progress-tracker">
                                                <div class="progress-steps">
                                                    <div class="progress-step active pending" id="progressPending">
                                                        <div class="step-icon"><svg fill="#ff0000" height="50px" width="50px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.016 512.016" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <g> <path d="M106.683,192.008c23.573,0,42.667-19.093,42.667-42.667c0-23.573-19.093-42.667-42.667-42.667 c-23.552,0-42.667,19.093-42.667,42.667C64.016,172.915,83.131,192.008,106.683,192.008z"></path> <path d="M160.016,426.675H61.904L21.093,243.037c-1.28-5.76-6.976-9.301-12.736-8.107c-5.76,1.28-9.365,6.976-8.107,12.736 l42.411,190.848v62.827c0,5.888,4.779,10.667,10.667,10.667s10.667-4.779,10.667-10.667v-53.333h64v53.333 c0,5.888,4.779,10.667,10.667,10.667s10.667-4.779,10.667-10.667v-53.333h10.667c5.888,0,10.667-4.779,10.667-10.667 C170.661,431.453,165.904,426.675,160.016,426.675z"></path> <path d="M394.683,0.008c-64.704,0-117.333,52.629-117.333,117.333s52.629,117.333,117.333,117.333 s117.333-52.629,117.333-117.333S459.387,0.008,394.683,0.008z M437.349,128.008h-42.667c-5.888,0-10.667-4.779-10.667-10.667 v-64c0-5.888,4.779-10.667,10.667-10.667s10.667,4.779,10.667,10.667v53.333h32c5.888,0,10.667,4.779,10.667,10.667 S443.237,128.008,437.349,128.008z"></path> <path d="M224.016,341.341h-74.667v-85.333c0-23.531-19.136-42.667-42.667-42.667c-23.531,0-42.667,19.136-42.667,42.667v117.333 c0,17.643,14.357,32,32,32h96v74.667c0,17.643,14.357,32,32,32s32-14.357,32-32V373.341 C256.016,355.699,241.658,341.341,224.016,341.341z"></path> </g> </g> </g> </g></svg></div>
                                                        <div class="step-label" style="color: #ff0000;">Pending</div>
                                                    </div>
                                                    <div class="progress-step in-progress" id="progressInProgress">
                                                        <div class="step-icon"><svg fill="#ff0000" height="50px" width="50px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.002 512.002" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M323.188,194.357c7.793,8.624,11.464,20.664,9.06,32.733h34.811v-32.733H323.188z"></path> </g> </g> <g> <g> <path d="M391.191,194.357h-9.967v32.733h9.967c2.665,0,4.826-2.161,4.826-4.826v-23.083 C396.017,196.518,393.856,194.357,391.191,194.357z"></path> </g> </g> <g> <g> <path d="M404.621,147.354h-12.8v32.734h12.8c2.665,0,4.826-2.161,4.826-4.826V152.18 C409.446,149.515,407.286,147.354,404.621,147.354z"></path> </g> </g> <g> <g> <path d="M308.517,147.354c-2.665,0-4.826,2.161-4.826,4.826v23.083c0,2.665,2.161,4.826,4.826,4.826h70.504v-32.734H308.517z"></path> </g> </g> <g> <g> <path d="M386.994,98.674h-69.979v32.734h69.979c2.665,0,4.826-2.161,4.826-4.826V103.5 C391.82,100.835,389.659,98.674,386.994,98.674z"></path> </g> </g> <g> <g> <path d="M290.891,98.674c-2.665,0-4.826,2.161-4.826,4.826v23.083c0,2.665,2.161,4.826,4.826,4.826h12.8V98.674H290.891z"></path> </g> </g> <g> <g> <path d="M308.517,62.164c-2.665,0-4.826,2.161-4.826,4.826v15.529c0,2.664,2.161,4.825,4.826,4.825h70.504v-25.18H308.517z"></path> </g> </g> <g> <g> <path d="M404.621,62.164h-15.843v25.18h15.843c2.665,0,4.826-2.161,4.826-4.826V66.989 C409.446,64.323,407.286,62.164,404.621,62.164z"></path> </g> </g> <g> <g> <circle cx="205.45" cy="41.918" r="41.918"></circle> </g> </g> <g> <g> <path d="M300.442,200.072l-68.058-18.322l-21.907-41.88l34.195,27.105l2.592-31.542c1.388-16.886-11.175-31.699-28.061-33.087 l-54.522-4.481c-16.886-1.388-31.699,11.175-33.087,28.061l-9.688,157.128l12.848,83.776l-31.325,114.255 c-3.57,13.02,4.091,26.469,17.111,30.038c13.004,3.571,26.467-4.082,30.038-17.111l32.702-119.277 c0.909-3.31,1.108-6.776,0.587-10.17L172,287.172l9.511,0.782l29.161,79.505l-24.36,115.027 c-3.216,15.186,8.368,29.516,23.938,29.516c11.303-0.001,21.455-7.887,23.89-19.386l25.817-121.906 c0.953-4.496,0.618-9.167-0.964-13.482l-24.476-66.731l3.852-46.856c-39.683-10.682-25.484-6.86-37.971-10.221 c-8.209-2.21-14.006-8.94-15.452-16.778l-10.573-57.88l26.045,49.788c2.637,5.041,7.262,8.748,12.755,10.228l76.676,20.641 c10.823,2.919,22.032-3.478,24.965-14.376C317.742,214.174,311.305,202.996,300.442,200.072z"></path> </g> </g> </g></svg></div>
                                                        <div class="step-label" style="color: #ff0000;">In Progress</div>
                                                    </div>
                                                    <div class="progress-step completed" id="progressCompleted">
                                                        <div class="step-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <rect width="24" height="24" fill="white"></rect> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.55879 3.6972C10.7552 2.02216 13.2447 2.02216 14.4412 3.6972L14.6317 3.96387C14.8422 4.25867 15.1958 4.41652 15.5558 4.37652L16.4048 4.28218C18.3156 4.06988 19.9301 5.68439 19.7178 7.59513L19.6235 8.44415C19.5835 8.8042 19.7413 9.15774 20.0361 9.36831L20.3028 9.55879C21.9778 10.7552 21.9778 13.2447 20.3028 14.4412L20.0361 14.6317C19.7413 14.8422 19.5835 15.1958 19.6235 15.5558L19.7178 16.4048C19.9301 18.3156 18.3156 19.9301 16.4048 19.7178L15.5558 19.6235C15.1958 19.5835 14.8422 19.7413 14.6317 20.0361L14.4412 20.3028C13.2447 21.9778 10.7553 21.9778 9.55879 20.3028L9.36831 20.0361C9.15774 19.7413 8.8042 19.5835 8.44414 19.6235L7.59513 19.7178C5.68439 19.9301 4.06988 18.3156 4.28218 16.4048L4.37652 15.5558C4.41652 15.1958 4.25867 14.8422 3.96387 14.6317L3.6972 14.4412C2.02216 13.2447 2.02216 10.7553 3.6972 9.55879L3.96387 9.36831C4.25867 9.15774 4.41652 8.8042 4.37652 8.44414L4.28218 7.59513C4.06988 5.68439 5.68439 4.06988 7.59513 4.28218L8.44415 4.37652C8.8042 4.41652 9.15774 4.25867 9.36831 3.96387L9.55879 3.6972ZM15.7071 9.29289C16.0976 9.68342 16.0976 10.3166 15.7071 10.7071L11.8882 14.526C11.3977 15.0166 10.6023 15.0166 10.1118 14.526L8.29289 12.7071C7.90237 12.3166 7.90237 11.6834 8.29289 11.2929C8.68342 10.9024 9.31658 10.9024 9.70711 11.2929L11 12.5858L14.2929 9.29289C14.6834 8.90237 15.3166 8.90237 15.7071 9.29289Z" fill="#ff0000"></path> </g></svg></div>
                                                        <div class="step-label">Completed</div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Task Meta Info -->
                                            <div class="task-body-meta">
                                                <div class="task-body-meta-item">
                                                    <svg fill="#ff0000" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" stroke="#ff0000" class="meta-icon" style="width: 20px; height: 20px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M360.548,29.538h-11.01V8.615c0-5.438-3.173-8.615-8.615-8.615H183.385c-5.442,0-11.077,3.178-11.077,8.615v20.923 h-18.538c-32.529,0-60.231,25.308-60.231,57.923V451.62c0,32.615,27.721,60.38,60.289,60.38h206.808 c32.567,0,57.827-27.764,57.827-60.38V87.461C418.462,54.846,393.164,29.538,360.548,29.538z M192,19.692h137.846v49.231H192 V19.692z M398.769,451.62c0,21.755-16.433,40.688-38.135,40.688H153.827c-21.702,0-40.596-18.933-40.596-40.688V87.461 c0-21.76,18.865-38.231,40.539-38.231h18.538v28.308c0,5.438,5.635,11.077,11.077,11.077h157.539 c5.442,0,8.615-5.639,8.615-11.077V49.231h11.01c21.75,0,38.221,16.471,38.221,38.231V451.62z"></path> </g> </g> <g> <g> <rect x="270.769" y="128" width="78.769" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="206.769" width="196.923" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="265.846" width="196.923" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="324.923" width="196.923" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="384" width="196.923" height="19.692"></rect> </g> </g> </g></svg>
                                                    <span class="meta-label">Task:</span>
                                                    <span class="meta-value"><?php echo htmlspecialchars($task['title']); ?></span>
                                                </div>
                                                <div class="task-body-meta-item">
                                                    <svg class="meta-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px; fill: #ff0000;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                                                    <span class="meta-label">Due Date:</span>
                                                    <span class="meta-value"><?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                                                </div>
                                                <div class="task-body-meta-item">
                                                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ff0000" class="meta-icon" style="width: 20px; height: 20px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M12 6V12" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M16.24 16.24L12 12" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
                                                    <span class="meta-label">Due Time:</span>
                                                    <span class="meta-value"><?php echo date('h:i A', strtotime($task['due_time'])); ?></span>
                                                </div>
                                                <div class="task-body-meta-item">
                                                    <svg version="1.0" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 64 64" enable-background="new 0 0 64 64" xml:space="preserve" fill="#000000" class="meta-icon" style="width: 20px; height: 20px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path fill="#ff0000" d="M32,0C18.745,0,8,10.745,8,24c0,5.678,2.502,10.671,5.271,15l17.097,24.156C30.743,63.686,31.352,64,32,64 s1.257-0.314,1.632-0.844L50.729,39C53.375,35.438,56,29.678,56,24C56,10.745,45.255,0,32,0z M48.087,39h-0.01L32,61L15.923,39 h-0.01C13.469,35.469,10,29.799,10,24c0-12.15,9.85-22,22-22s22,9.85,22,22C54,29.799,50.281,35.781,48.087,39z"></path> <path fill="#ff0000" d="M32,14c-5.523,0-10,4.478-10,10s4.477,10,10,10s10-4.478,10-10S37.523,14,32,14z M32,32 c-4.418,0-8-3.582-8-8s3.582-8,8-8s8,3.582,8,8S36.418,32,32,32z"></path> <path fill="#ff0000" d="M32,10c-7.732,0-14,6.268-14,14s6.268,14,14,14s14-6.268,14-14S39.732,10,32,10z M32,36 c-6.627,0-12-5.373-12-12s5.373-12,12-12s12,5.373,12,12S38.627,36,32,36z"></path> </g> </g></svg>
                                                    <span class="meta-label">Location:</span>
                                                    <span class="meta-value"><?php echo htmlspecialchars($task['room']); ?></span>
                                                </div>
                                            </div>

                                            <!-- Student Info -->
                                            <div class="task-body-student-info">
                                                <div class="task-body-student-avatar-wrapper">
                                                    <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Student" class="task-body-student-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <svg class="task-body-student-avatar-default" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="display:none;"><circle cx="12" cy="8" r="4" fill="#ff0000"/><path d="M12 14c-6 0-8 3-8 3v3h16v-3s-2-3-8-3z" fill="#ff0000"/></svg>
                                                </div>
                                                <div class="task-body-student-details">
                                                    <div class="task-body-student-name"><?php echo htmlspecialchars($student_name); ?></div>
                                                    <div class="task-body-student-meta">
                                                        <?php if (!empty($student['student_id'])): ?>
                                                            <span>ID: <?php echo htmlspecialchars($student['student_id']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($student['year_level'])): ?>
                                                            <span><?php echo htmlspecialchars($student['year_level']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Rate Button -->
                                            <?php if ($student['rating']): ?>
                                                <div id="rate-btn-<?php echo $unique_id; ?>" class="task-body-rate-btn" style="cursor: default; background: #f5f5f5; color: #333;">
                                                    <?php 
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo ($i <= $student['rating']) ? '★' : '☆';
                                                    }
                                                    echo ' ' . $student['rating'] . '/5';
                                                    ?>
                                                </div>
                                            <?php else: ?>
                                                <button id="rate-btn-<?php echo $unique_id; ?>" class="task-body-rate-btn" onclick="openStudentModal('<?php echo $unique_id; ?>', '<?php echo htmlspecialchars($student_photo); ?>', '<?php echo htmlspecialchars(addslashes($student_name)); ?>', '<?php echo htmlspecialchars($student['email']); ?>', '<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>', '<?php echo htmlspecialchars($student['year_level'] ?? ''); ?>', <?php echo $task['id']; ?>, <?php echo $student['rating'] ? $student['rating'] : 0; ?>, '<?php echo $student['rated_at'] ? date('M d', strtotime($student['rated_at'])) : ''; ?>')">
                                                    Rate Student
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 1024 1024" style="width: 120px; height: 120px;" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M660 103.2l-149.6 76 2.4 1.6-2.4-1.6-157.6-80.8L32 289.6l148.8 85.6v354.4l329.6-175.2 324.8 175.2V375.2L992 284.8z" fill="#FFFFFF"></path><path d="M180.8 737.6c-1.6 0-3.2 0-4-0.8-2.4-1.6-4-4-4-7.2V379.2L28 296c-2.4-0.8-4-4-4-6.4s1.6-5.6 4-7.2l320.8-191.2c2.4-1.6 5.6-1.6 8 0l154.4 79.2L656 96c2.4-1.6 4.8-0.8 7.2 0l332 181.6c2.4 1.6 4 4 4 7.2s-1.6 5.6-4 7.2l-152.8 88v350.4c0 3.2-1.6 5.6-4 7.2-2.4 1.6-5.6 1.6-8 0l-320-174.4-325.6 173.6c-1.6 0.8-2.4 0.8-4 0.8zM48 289.6L184.8 368c2.4 1.6 4 4 4 7.2v341.6l317.6-169.6c2.4-1.6 5.6-1.6 7.2 0l312.8 169.6V375.2c0-3.2 1.6-5.6 4-7.2L976 284.8 659.2 112.8 520 183.2c0 0.8-0.8 0.8-0.8 1.6-2.4 4-7.2 4.8-11.2 2.4l-1.6-1.6h-0.8l-152.8-78.4L48 289.6z" fill="#ff0000"></path><path d="M510.4 179.2l324.8 196v354.4L510.4 554.4z" fill="#ff0000"></path><path d="M510.4 179.2L180.8 375.2v354.4l329.6-175.2z" fill="#ff0000"></path><path d="M835.2 737.6c-1.6 0-2.4 0-4-0.8l-324.8-176c-2.4-1.6-4-4-4-7.2V179.2c0-3.2 1.6-5.6 4-7.2 2.4-1.6 5.6-1.6 8 0L839.2 368c2.4 1.6 4 4 4 7.2v355.2c0 3.2-1.6 5.6-4 7.2h-4zM518.4 549.6l308.8 167.2V379.2L518.4 193.6v356z" fill="#ff0000"></path><path d="M180.8 737.6c-1.6 0-3.2 0-4-0.8-2.4-1.6-4-4-4-7.2V375.2c0-3.2 1.6-5.6 4-7.2l329.6-196c2.4-1.6 5.6-1.6 8 0 2.4 1.6 4 4 4 7.2v375.2c0 3.2-1.6 5.6-4 7.2l-329.6 176h-4z m8-358.4v337.6l313.6-167.2V193.6L188.8 379.2z" fill="#ff0000"></path><path d="M510.4 550.4L372 496 180.8 374.4v355.2l329.6 196 324.8-196V374.4L688.8 483.2z" fill="#ff0000"></path><path d="M510.4 933.6c-1.6 0-3.2 0-4-0.8L176.8 736.8c-2.4-1.6-4-4-4-7.2V374.4c0-3.2 1.6-5.6 4-7.2 2.4-1.6 5.6-1.6 8 0L376 488.8l135.2 53.6 174.4-66.4L830.4 368c2.4-1.6 5.6-2.4 8-0.8 2.4 1.6 4 4 4 7.2v355.2c0 3.2-1.6 5.6-4 7.2l-324.8 196s-1.6 0.8-3.2 0.8z m-321.6-208l321.6 191.2 316.8-191.2V390.4L693.6 489.6c-0.8 0.8-1.6 0.8-1.6 0.8l-178.4 68c-1.6 0.8-4 0.8-5.6 0L369.6 504c-0.8 0-0.8-0.8-1.6-0.8L188.8 389.6v336z" fill="#ff0000"></path><path d="M510.4 925.6l324.8-196V374.4L665.6 495.2l-155.2 55.2z" fill="#ff0000"></path><path d="M510.4 933.6c-1.6 0-2.4 0-4-0.8-2.4-1.6-4-4-4-7.2V550.4c0-3.2 2.4-6.4 5.6-7.2L662.4 488l168-120c2.4-1.6 5.6-1.6 8-0.8 2.4 1.6 4 4 4 7.2v355.2c0 3.2-1.6 5.6-4 7.2l-324.8 196s-1.6 0.8-3.2 0.8z m8-377.6v355.2l308.8-185.6V390.4L670.4 501.6c-0.8 0.8-1.6 0.8-1.6 0.8l-150.4 53.6z" fill="#ff0000"></path><path d="M252.8 604l257.6 145.6V550.4l-147.2-49.6-182.4-126.4z" fill="#ff0000"></path><path d="M32 460l148.8-85.6 329.6 176L352 640.8z" fill="#FFFFFF"></path><path d="M659.2 693.6l176-90.4V375.2L692 480.8l-179.2 68-2.4 1.6z" fill="#ff0000"></path><path d="M510.4 550.4l148.8 85.6L992 464.8l-156.8-89.6z" fill="#FFFFFF"></path><path d="M352 648.8c-1.6 0-2.4 0-4-0.8l-320-180.8c-2.4-1.6-4-4-4-7.2s1.6-5.6 4-7.2L176.8 368c2.4-1.6 5.6-1.6 8 0l329.6 176c2.4 1.6 4 4 4 7.2s-1.6 5.6-4 7.2L356 648c-0.8 0.8-2.4 0.8-4 0.8zM48 460L352 632l141.6-80.8L180.8 384 48 460z" fill="#ff0000"></path><path d="M659.2 644c-1.6 0-2.4 0-4-0.8L506.4 557.6c-2.4-1.6-4-4-4-7.2s1.6-5.6 4-7.2l324.8-176c2.4-1.6 5.6-1.6 8 0l156.8 90.4c2.4 1.6 4 4 4 7.2s-1.6 5.6-4 7.2L663.2 643.2c-1.6 0.8-2.4 0.8-4 0.8zM527.2 550.4l132.8 76L976 464l-141.6-80-307.2 166.4z" fill="#ff0000"></path></g></svg>
                    </div>
                    <div class="empty-title">No completed tasks yet</div>
                    <div class="empty-subtitle">When students complete your tasks, they will appear here for you to rate</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="profile-modal" id="profileModal" onclick="if(event.target === this) closeProfileModal()">
        <button class="profile-modal-close" onclick="closeProfileModal()">×</button>
        <div class="profile-modal-content">
            <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-modal-image">
        </div>
    </div>

    <!-- Notification Detail Modal -->
    <div class="notif-detail-modal" id="notifDetailModal" onclick="if(event.target === this) closeNotifDetail()">
        <div class="notif-detail-container">
            <div class="notif-detail-header">
                <h3>🔔 Notification Details</h3>
                <button class="notif-detail-close" onclick="closeNotifDetail()">×</button>
            </div>
            <div class="notif-detail-body">
                <div class="notif-detail-student">
                    <img src="" alt="Student" class="notif-detail-avatar" id="notifDetailAvatar">
                    <div class="notif-detail-info">
                        <h4 id="notifDetailName">Student Name</h4>
                        <p id="notifDetailEmail">student@email.com</p>
                    </div>
                </div>
                <div class="notif-detail-task">
                    <div class="notif-detail-task-label">📋 Task</div>
                    <div class="notif-detail-task-title" id="notifDetailTask">Task Title</div>
                    <div class="notif-detail-task-action">✅ Accepted this task</div>
                </div>
                <div class="notif-detail-time" id="notifDetailTime">2 hours ago</div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="settings-modal" id="settingsModal" onclick="if(event.target === this) closeSettingsModal()">
        <div class="settings-container">
            <div class="settings-header">
                <div class="settings-header-content">
                    <h2 class="settings-title">
                        <span class="settings-title-icon">⚙️</span>
                        Settings
                    </h2>
                    <button class="settings-close" onclick="closeSettingsModal()">×</button>
                </div>
            </div>
            <div class="settings-body">
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <span class="settings-section-icon">📷</span>
                        Profile Photo
                    </h3>
                    <div class="profile-photo-section">
                        <div class="current-photo-container">
                            <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Current Photo" class="current-photo" id="settingsCurrentPhoto">
                            <span class="photo-edit-badge">✏️</span>
                        </div>
                        <div class="photo-upload-area">
                            <input type="file" id="settingsPhotoInput" accept="image/*" style="display: none;" onchange="previewSettingsPhoto(this)">
                            <button type="button" class="photo-upload-btn" onclick="document.getElementById('settingsPhotoInput').click()">
                                📤 Upload New Photo
                            </button>
                            <p class="photo-upload-hint">JPG, PNG or GIF. Max 5MB.</p>
                            <div class="settings-message" id="photoMessage"></div>
                        </div>
                    </div>
                </div>
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <span class="settings-section-icon">🔐</span>
                        Change Password
                    </h3>
                    <form class="password-form" id="passwordForm" onsubmit="changePassword(event)">
                        <div class="settings-message" id="passwordMessage"></div>
                        <div class="password-input-group">
                            <label class="password-label">Current Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" class="password-input" id="currentPassword" placeholder="Enter current password" required>
                                </div>
                        </div>
                        <div class="password-input-group">
                            <label class="password-label">New Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" class="password-input" id="newPassword" placeholder="Enter new password" required oninput="checkPasswordStrength(this.value)">
                                </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                            <p class="password-strength-text" id="strengthText"></p>
                        </div>
                        <div class="password-input-group">
                            <label class="password-label">Confirm New Password</label>
                            <div class="password-input-wrapper">
                                <input type="password" class="password-input" id="confirmPassword" placeholder="Confirm new password" required>
                                </div>
                        </div>
                        <button type="submit" class="save-password-btn" id="savePasswordBtn">
                            🔒 Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Rating Modal -->
    <div class="student-modal" id="studentModal" onclick="if(event.target === this) closeStudentModal()">
        <button class="student-modal-close" onclick="closeStudentModal()">×</button>
        <div class="student-modal-wrapper">
            <div class="student-card-modal" id="studentCardModal">
                <div class="student-card-header">
                    <img src="" alt="Student" class="student-avatar" id="modalStudentAvatar">
                    <div class="student-info">
                        <div class="student-name" id="modalStudentName"></div>
                        <div class="student-email" id="modalStudentEmail"></div>
                        <div class="student-details">
                            <span class="student-detail-badge" id="modalStudentId"></span>
                            <span class="student-detail-badge" id="modalStudentYear"></span>
                        </div>
                    </div>
                </div>

                <div class="rating-section">
                    <div class="rating-label">⭐ Rate Student Performance</div>
                    
                    <!-- Rating Stars Container -->
                    <div id="modalRatingContainer">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function submitRating(taskId, studentEmail, rating, uniqueId) {
            const ratingContainer = document.getElementById('rating-' + uniqueId);
            
            // Disable further clicks
            ratingContainer.classList.add('rated');
            const labels = ratingContainer.querySelectorAll('label');
            labels.forEach(label => label.style.pointerEvents = 'none');
            
            // Send rating to server
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('student_email', studentEmail);
            formData.append('rating', rating);
            
            fetch('rate_student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Show success animation
                    ratingContainer.classList.add('rating-success');
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Rating Saved!',
                        text: `You rated this student ${rating} star${rating > 1 ? 's' : ''}`,
                        confirmButtonColor: '#ff0000',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                    
                    // Replace with static stars
                    setTimeout(() => {
                        let starsHtml = '<div class="current-rating"><div class="rating-stars">';
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += `<span class="star ${i <= rating ? '' : 'empty'}">★</span>`;
                        }
                        starsHtml += '</div><span class="rated-badge">✓ Just Rated</span></div>';
                        ratingContainer.outerHTML = starsHtml;
                    }, 500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function handleLogout() {
            Swal.fire({
                title: '',
                html: '<svg viewBox="0 0 24 24" fill="none" stroke="#ff4444" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="width: 60px; height: 60px; margin: 0 auto 20px; display: block;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg><p style="margin: 16px 0 0 0; color: #333; font-size: 16px; font-weight: 500;">Are you sure you want to logout?</p>',
                icon: null,
                showCancelButton: true,
                confirmButtonColor: '#ff4444',
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

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const messageDropdown = document.getElementById('messageDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            if (messageDropdown) messageDropdown.classList.remove('show');
            if (notificationDropdown) notificationDropdown.classList.remove('show');
            dropdown.classList.toggle('show');
        }

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            const messageDropdown = document.getElementById('messageDropdown');
            if (messageDropdown) messageDropdown.classList.remove('show');
            if (dropdown) dropdown.classList.toggle('show');
        }

        function toggleMessageDropdown() {
            const dropdown = document.getElementById('messageDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            if (notificationDropdown) notificationDropdown.classList.remove('show');
            if (dropdown) dropdown.classList.toggle('show');
        }

        // Mark all notifications as read
        function markAllRead() {
            // Send AJAX request to persist the read state
            fetch('teacher_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            }).then(response => response.json())
            .then(data => {
                console.log('Mark all read response:', data);
            }).catch(error => {
                console.error('Error marking as read:', error);
            });
            
            // Hide the notification dot
            const notifDot = document.querySelector('.notification-dot');
            if (notifDot) {
                notifDot.style.display = 'none';
            }
            
            // Hide all "New" badges
            document.querySelectorAll('.notification-new-badge').forEach(badge => {
                badge.style.display = 'none';
            });
            
            // Hide the count badge in header
            const countBadge = document.querySelector('.notification-count-badge');
            if (countBadge) {
                countBadge.style.display = 'none';
            }
        }

        // View notification details
        function viewNotification(photo, name, task, time, email) {
            document.getElementById('notifDetailAvatar').src = photo;
            document.getElementById('notifDetailName').textContent = name;
            document.getElementById('notifDetailEmail').textContent = email || 'No email';
            document.getElementById('notifDetailTask').textContent = task;
            document.getElementById('notifDetailTime').textContent = '⏰ ' + time;
            const modal = document.getElementById('notifDetailModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Close notification detail modal
        function closeNotifDetail() {
            const modal = document.getElementById('notifDetailModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Dismiss notification
        function dismissNotification(btn, event) {
            event.stopPropagation();
            const item = btn.closest('.notification-item');
            item.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => {
                item.remove();
                const list = document.querySelector('.notification-list');
                if (list && list.querySelectorAll('.notification-item').length === 0) {
                    list.innerHTML = '<div class="notification-empty">No notifications</div>';
                }
            }, 300);
        }

        function openProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Settings Modal Functions
        function openSettingsModal() {
            const profileDropdown = document.getElementById('profileDropdown');
            if (profileDropdown) profileDropdown.classList.remove('show');
            const modal = document.getElementById('settingsModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSettingsModal() {
            const modal = document.getElementById('settingsModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
            document.getElementById('passwordForm').reset();
            document.getElementById('strengthBar').className = 'password-strength-bar';
            document.getElementById('strengthText').textContent = '';
            document.getElementById('passwordMessage').className = 'settings-message';
            document.getElementById('photoMessage').className = 'settings-message';
        }

        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            strengthBar.className = 'password-strength-bar';
            strengthText.className = 'password-strength-text';
            if (password.length === 0) {
                strengthText.textContent = '';
            } else if (strength <= 1) {
                strengthBar.classList.add('weak');
                strengthText.classList.add('weak');
                strengthText.textContent = '⚠️ Weak password';
            } else if (strength <= 2) {
                strengthBar.classList.add('medium');
                strengthText.classList.add('medium');
                strengthText.textContent = '👍 Medium strength';
            } else {
                strengthBar.classList.add('strong');
                strengthText.classList.add('strong');
                strengthText.textContent = '💪 Strong password';
            }
        }

        function previewSettingsPhoto(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    showPhotoMessage('File is too large. Maximum 5MB allowed.', 'error');
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    showPhotoMessage('Please select an image file.', 'error');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('settingsCurrentPhoto').src = e.target.result;
                    uploadProfilePhoto(file);
                };
                reader.readAsDataURL(file);
            }
        }

        function showPhotoMessage(message, type) {
            const msgDiv = document.getElementById('photoMessage');
            msgDiv.textContent = (type === 'success' ? '✅ ' : '❌ ') + message;
            msgDiv.className = 'settings-message ' + type;
        }

        function showPasswordMessage(message, type) {
            const msgDiv = document.getElementById('passwordMessage');
            msgDiv.textContent = (type === 'success' ? '✅ ' : '❌ ') + message;
            msgDiv.className = 'settings-message ' + type;
        }

        function uploadProfilePhoto(file) {
            const formData = new FormData();
            formData.append('action', 'update_photo');
            formData.append('photo', file);
            fetch('settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPhotoMessage('Profile photo updated successfully!', 'success');
                    document.querySelectorAll('.profile-pic, .profile-info-pic, .profile-modal-image').forEach(img => {
                        img.src = data.photo_url + '?t=' + new Date().getTime();
                    });
                } else {
                    showPhotoMessage(data.error || 'Failed to update photo', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showPhotoMessage('An error occurred. Please try again.', 'error');
            });
        }

        function changePassword(event) {
            event.preventDefault();
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (newPassword !== confirmPassword) {
                showPasswordMessage('New passwords do not match!', 'error');
                return;
            }
            if (newPassword.length < 6) {
                showPasswordMessage('Password must be at least 6 characters long.', 'error');
                return;
            }
            const btn = document.getElementById('savePasswordBtn');
            btn.disabled = true;
            btn.innerHTML = '⏳ Updating...';
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            fetch('settings_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '🔒 Update Password';
                if (data.success) {
                    showPasswordMessage('Password updated successfully!', 'success');
                    document.getElementById('passwordForm').reset();
                    document.getElementById('strengthBar').className = 'password-strength-bar';
                    document.getElementById('strengthText').textContent = '';
                } else {
                    showPasswordMessage(data.error || 'Failed to update password', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                btn.disabled = false;
                btn.innerHTML = '🔒 Update Password';
                showPasswordMessage('An error occurred. Please try again.', 'error');
            });
        }

        // Student Modal Variables
        let currentModalData = {};

        // Function to get the happy star SVG based on rating
        function getHappyStarSVG(rating) {
            const svgs = {
                1: `<svg style="width: 80px; height: 80px; position: relative; z-index: 3;" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <circle style="fill:#ff0000;" cx="256.006" cy="256.006" r="246.856"></circle> <path style="fill:#ff0000;" d="M126.309,385.698c-88.802-88.802-95.8-228.428-20.999-325.243 c-8.286,6.401-16.258,13.399-23.859,20.999c-96.402,96.402-96.402,252.7,0,349.102s252.7,96.402,349.102,0 c7.599-7.599,14.597-15.573,20.999-23.859C354.736,481.498,215.11,474.5,126.309,385.698z"></path> <path d="M303.047,359.125c0,12.468-2.623,24.106-7.149,33.951c-8.308,18.08-23.069,30.096-39.892,30.096 s-31.584-12.016-39.892-30.096c-4.538-9.845-7.161-21.483-7.161-33.951c0-35.366,21.069-64.047,47.053-64.047 S303.047,323.758,303.047,359.125z"></path> <path style="fill:#F2F2F2;" d="M216.102,325.173c8.308-18.08,23.069-30.096,39.892-30.096s31.584,12.017,39.892,30.096 c-1.465,0.298-22.757,8.599-39.892,8.627C235.186,333.836,217.882,325.535,216.102,325.173z"></path> <g> <path style="fill:#2197D8;" d="M422.797,424.884c0,5.734-0.671,10.553-1.806,14.737c-25.704,23.106-56.276,40.893-89.996,51.628 c2.306-30.767,25.778-32.438,25.778-66.365c0-35.684-25.961-35.684-25.961-71.367s25.961-35.683,25.961-71.367 c0-35.257-25.363-35.671-25.948-70.123c23.85,1.537,48.53,7.71,67.89,13.773c5.941,21.642,24.082,26.534,24.082,56.349 c0,35.683-25.96,35.683-25.96,71.367S422.797,389.2,422.797,424.884z"></path> <path style="fill:#2197D8;" d="M155.226,282.15c0,35.683,25.961,35.683,25.961,71.367s-25.96,35.683-25.96,71.367 c0,33.927,23.472,35.598,25.778,66.365c-33.719-10.736-64.291-28.522-89.996-51.628c-1.135-4.184-1.806-9.003-1.806-14.737 c0-35.684,25.96-35.684,25.96-71.367s-25.96-35.683-25.96-71.367c0-29.816,18.141-34.708,24.082-56.349 c19.361-6.063,44.04-12.236,67.89-13.773C180.589,246.478,155.226,246.893,155.226,282.15z"></path> </g> <path d="M256.006,285.928c-30.99,0-56.203,32.836-56.203,73.197c0,13.36,2.767,26.424,7.998,37.772 c10.193,22.182,28.215,35.425,48.206,35.425s38.012-13.243,48.206-35.424c5.224-11.364,7.986-24.426,7.986-37.773 C312.197,318.764,286.989,285.928,256.006,285.928z M256.006,304.227c10.287,0,19.623,5.978,26.458,15.651 c-7.481,2.223-17.787,4.76-26.485,4.774c-0.039,0-0.077,0-0.117,0c-10.355,0-19.834-2.326-26.542-4.469 C236.175,310.328,245.607,304.227,256.006,304.227z M287.586,389.256c-7.128,15.508-18.934,24.767-31.58,24.767 c-12.646,0-24.451-9.258-31.583-24.777c-4.136-8.971-6.322-19.387-6.322-30.121c0-7.932,1.177-15.469,3.277-22.285 c7.914,2.7,20.351,6.112,34.484,6.111c0.048,0,0.099,0,0.148,0c11.716-0.02,24.445-3.252,34.521-6.407 c2.157,6.893,3.367,14.533,3.367,22.581C293.898,369.852,291.715,380.27,287.586,389.256z"></path> <path d="M365.278,120.206c-22.617,0-43.046,11.592-54.646,31.007l15.71,9.385c8.265-13.834,22.82-22.093,38.936-22.094 c16.117,0,30.673,8.26,38.938,22.094c1.713,2.868,4.749,4.458,7.863,4.458c1.597,0,3.215-0.418,4.685-1.297 c4.338-2.592,5.753-8.209,3.162-12.547C408.325,131.798,387.896,120.206,365.278,120.206z"></path> <path d="M201.372,151.215c-11.6-19.415-32.029-31.007-54.645-31.007c-0.001,0,0.001,0-0.001,0 c-22.615,0-43.046,11.593-54.646,31.007c-2.591,4.338-1.176,9.956,3.162,12.547c1.47,0.878,3.086,1.297,4.685,1.297 c3.113,0,6.15-1.591,7.863-4.458c8.265-13.834,22.822-22.094,38.938-22.094c16.116,0,30.671,8.26,38.936,22.094 c2.591,4.338,8.21,5.753,12.547,3.162C202.548,161.17,203.963,155.553,201.372,151.215z"></path> <path d="M256.006,0C114.843,0,0,114.843,0,256.006c0,72.499,30.94,141.904,84.891,190.419 c27.234,24.481,58.637,42.494,93.335,53.541c25.053,7.985,51.221,12.034,77.78,12.034c26.551,0,52.715-4.049,77.767-12.034 c34.699-11.048,66.103-29.062,93.336-53.542l0.001-0.001C481.06,397.91,512,328.505,512,256.005C512,114.843,397.161,0,256.006,0z M98.352,424.884c0-14.866,4.955-21.677,11.228-30.301c6.905-9.491,14.731-20.249,14.731-41.066 c0-20.817-7.826-31.575-14.731-41.066c-6.274-8.624-11.228-15.435-11.228-30.301c0-14.853,4.953-21.66,11.221-30.279 c3.783-5.199,7.981-10.982,10.916-18.71c18.159-5.463,35.014-9.139,50.313-10.98c-1.843,7.682-5.58,12.846-9.995,18.914 c-6.904,9.489-14.73,20.245-14.73,41.055c0,20.817,7.826,31.575,14.731,41.066c6.274,8.624,11.228,15.435,11.228,30.301 c0,14.866-4.955,21.677-11.228,30.301c-6.905,9.491-14.731,20.249-14.731,41.066c0,20.815,7.825,31.572,14.728,41.063 c2.627,3.611,5.075,6.989,7.015,10.854c-25.061-9.985-48.057-24.107-68.57-42.122C98.654,431.687,98.352,428.463,98.352,424.884z M351.193,383.818c-6.274-8.624-11.23-15.435-11.23-30.301s4.957-21.677,11.23-30.301c6.905-9.491,14.731-20.249,14.731-41.066 c0-20.811-7.826-31.566-14.731-41.056c-4.416-6.068-8.152-11.233-9.995-18.914c15.292,1.84,32.149,5.517,50.313,10.98 c2.935,7.728,7.133,13.511,10.915,18.71c6.271,8.618,11.222,15.426,11.222,30.279c0,14.866-4.957,21.677-11.23,30.301 c-6.905,9.491-14.731,20.249-14.731,41.066s7.826,31.575,14.731,41.066c6.274,8.624,11.23,15.435,11.23,30.301 c0,3.577-0.301,6.802-0.9,9.795c-20.512,18.015-43.506,32.137-68.568,42.122c1.94-3.865,4.388-7.244,7.015-10.855 c6.904-9.491,14.728-20.249,14.728-41.063C365.923,404.065,358.097,393.308,351.193,383.818z M431.449,416.341 c-1.803-15.391-8.344-24.429-14.233-32.524c-6.274-8.624-11.23-15.435-11.23-30.301s4.957-21.677,11.23-30.301 c6.905-9.491,14.731-20.249,14.731-41.066c0-20.097-7.298-30.81-14.015-40.067c8.355,3.138,13.437,5.363,13.522,5.401 c1.199,0.528,2.452,0.778,3.684,0.778c3.516,0,6.868-2.037,8.376-5.462c2.039-4.624-0.057-10.024-4.681-12.063 c-0.629-0.278-15.663-6.873-37.384-13.67c-17.446-5.464-33.884-9.414-49.102-11.81c13.805-5.282,32.196-10.067,53.515-10.067 c5.054,0,9.15-4.097,9.15-9.15s-4.095-9.15-9.15-9.15c-53.614,0-89.902,26.284-91.42,27.402c-3.182,2.345-4.499,6.468-3.265,10.222 c1.235,3.755,4.74,6.294,8.692,6.294c0.822,0,1.659,0.028,2.489,0.041c2.045,14.478,8.352,23.194,14.037,31.007 c6.274,8.621,11.228,15.431,11.228,30.289c0,14.866-4.957,21.677-11.23,30.301c-6.905,9.491-14.731,20.249-14.731,41.066 c0,20.817,7.826,31.575,14.731,41.066c6.274,8.624,11.23,15.435,11.23,30.301c0,14.863-4.955,21.675-11.228,30.299 c-5.395,7.418-11.411,15.702-13.737,29.036c-21.558,6.289-43.949,9.483-66.653,9.483c-22.712,0-45.106-3.194-66.664-9.483 c-2.326-13.333-8.342-21.617-13.738-29.035c-6.273-8.624-11.227-15.436-11.227-30.3c0-14.866,4.955-21.677,11.228-30.301 c6.905-9.491,14.731-20.249,14.731-41.066c0-20.817-7.826-31.575-14.731-41.066c-6.274-8.624-11.228-15.435-11.228-30.301 c0-14.858,4.955-21.668,11.228-30.289c5.684-7.814,11.991-16.529,14.036-31.007c0.834-0.013,1.675-0.041,2.502-0.041 c3.953,0,7.458-2.539,8.692-6.294c1.235-3.755-0.082-7.878-3.265-10.222c-1.52-1.117-37.807-27.401-91.435-27.401 c-5.054,0-9.15,4.097-9.15,9.15s4.095,9.15,9.15,9.15c21.326,0,39.72,4.785,53.525,10.066c-15.224,2.396-31.663,6.346-49.109,11.81 c-21.722,6.796-36.757,13.393-37.387,13.67c-4.624,2.039-6.719,7.439-4.681,12.063c1.508,3.423,4.86,5.462,8.376,5.462 c1.233,0,2.486-0.251,3.685-0.78c0.085-0.038,5.152-2.261,13.522-5.403c-6.717,9.258-14.016,19.971-14.016,40.07 c0,20.817,7.826,31.575,14.731,41.066c6.274,8.624,11.228,15.435,11.228,30.301s-4.955,21.677-11.228,30.301 c-5.889,8.094-12.43,17.133-14.233,32.525c-39.796-43.601-62.25-100.842-62.25-160.337c0-131.072,106.634-237.707,237.707-237.707 c131.065,0,237.695,106.634,237.695,237.707C493.701,315.501,471.246,372.742,431.449,416.341z"></path> </g></svg>`,
                2: `<svg style="width: 80px; height: 80px; position: relative; z-index: 3;" viewBox="0 0 512.001 512.001" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <circle style="fill:#ff0000;" cx="256.004" cy="256.004" r="246.855"></circle> <g> <path style="fill:#ff0000;" d="M126.306,385.694c-88.801-88.802-95.798-228.426-20.998-325.242 C97.023,66.853,89.051,73.85,81.45,81.45c-96.401,96.401-96.401,252.698,0,349.099s252.698,96.401,349.099,0 c7.599-7.599,14.597-15.573,20.999-23.858C354.733,481.492,215.108,474.494,126.306,385.694z"></path> <path style="fill:#ff0000;" d="M287.082,363.982c-1.431,0-2.882-0.337-4.237-1.046c-8.209-4.298-17.494-6.571-26.85-6.571 c-9.352,0-18.633,2.272-26.841,6.569c-4.474,2.345-10.005,0.616-12.351-3.861c-2.344-4.476-0.615-10.006,3.861-12.349 c10.817-5.664,23.034-8.658,35.331-8.658c12.299,0,24.52,2.994,35.337,8.658c4.477,2.343,6.206,7.874,3.862,12.349 C293.561,362.198,290.377,363.982,287.082,363.982z"></path> </g> <path d="M256.001,0C114.841,0,0,114.841,0,256.001s114.841,256.001,256.001,256.001S512.001,397.16,512.001,256.001 C512,114.841,397.16,0,256.001,0z M256.001,493.701c-131.069,0-237.702-106.631-237.702-237.7S124.932,18.299,256.001,18.299 s237.702,106.632,237.702,237.7C493.701,387.07,387.068,493.701,256.001,493.701z"></path> <path d="M257.142,300.395c-37.723,0-73.189,14.69-99.863,41.364c-3.573,3.573-3.573,9.365,0,12.939c3.574,3.573,9.367,3.573,12.94,0 c23.217-23.218,54.087-36.005,86.923-36.005s63.706,12.787,86.923,36.005c1.787,1.787,4.128,2.68,6.471,2.68 c2.341,0,4.683-0.893,6.471-2.68c3.573-3.573,3.573-9.365,0-12.939C330.332,315.086,294.865,300.395,257.142,300.395z"></path> <path d="M161.852,136.57c0-5.053-4.095-9.15-9.15-9.15s-9.15,4.097-9.15,9.15c0,26.857-21.849,48.707-48.707,48.707 c-5.054,0-9.15,4.097-9.15,9.15s4.095,9.15,9.15,9.15C131.792,203.575,161.852,173.517,161.852,136.57z"></path> <path d="M417.155,185.276c-26.858,0-48.707-21.849-48.707-48.707c0-5.053-4.095-9.15-9.15-9.15c-5.054,0-9.15,4.097-9.15,9.15 c0,36.947,30.059,67.006,67.006,67.006c5.054,0,9.15-4.097,9.15-9.15C426.304,189.372,422.209,185.276,417.155,185.276z"></path> <path d="M180.577,229.983c0-18.666-15.186-33.852-33.852-33.852s-33.852,15.186-33.852,33.852s15.186,33.852,33.852,33.852 S180.577,248.649,180.577,229.983z"></path> <path d="M365.275,196.131c-18.666,0-33.852,15.186-33.852,33.852s15.186,33.852,33.852,33.852s33.852-15.186,33.852-33.852 S383.942,196.131,365.275,196.131z"></path> <g> <circle style="fill:#FFFFFF;" cx="150.48" cy="225.372" r="9.15"></circle> <circle style="fill:#FFFFFF;" cx="368.849" cy="225.372" r="9.15"></circle> </g> </g></svg>`,
                3: `<svg style="width: 80px; height: 80px; position: relative; z-index: 3;" viewBox="0 0 512.001 512.001" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <circle style="fill:#ff0000;" cx="256.004" cy="256.004" r="246.855"></circle> <g> <path style="fill:#ff0000;" d="M126.306,385.694c-88.801-88.802-95.798-228.426-20.998-325.241 c-8.286,6.401-16.258,13.399-23.858,20.999c-96.401,96.401-96.401,252.698,0,349.099s252.698,96.401,349.099,0 c7.599-7.599,14.597-15.573,20.999-23.858C354.733,481.492,215.108,474.495,126.306,385.694z"></path> <path style="fill:#ff0000;" d="M297.481,349.115h-85.403c-5.054,0-9.15-4.097-9.15-9.15s4.095-9.15,9.15-9.15h85.403 c5.054,0,9.15,4.097,9.15,9.15S302.534,349.115,297.481,349.115z"></path> </g> <path d="M256.001,0C114.841,0,0,114.841,0,256.001s114.841,256.001,256.001,256.001S512.001,397.16,512.001,256.001 S397.16,0,256.001,0z M256.001,493.701c-131.069,0-237.702-106.631-237.702-237.7S124.932,18.299,256.001,18.299 s237.702,106.632,237.702,237.702S387.068,493.701,256.001,493.701z"></path> <path d="M371.284,296.658H138.275c-5.054,0-9.15,4.097-9.15,9.15s4.095,9.15,9.15,9.15h233.008c5.054,0,9.15-4.097,9.15-9.15 C380.433,300.754,376.337,296.658,371.284,296.658z"></path> <path d="M180.577,226.834c0-18.666-15.186-33.852-33.852-33.852s-33.852,15.186-33.852,33.852s15.186,33.852,33.852,33.852 S180.577,245.501,180.577,226.834z"></path> <path d="M365.275,192.982c-18.666,0-33.852,15.186-33.852,33.852s15.186,33.852,33.852,33.852s33.852-15.186,33.852-33.852 S383.942,192.982,365.275,192.982z"></path> <g> <circle style="fill:#FFFFFF;" cx="155.969" cy="219.735" r="9.15"></circle> <circle style="fill:#FFFFFF;" cx="374.338" cy="219.735" r="9.15"></circle> </g> </g></svg>`,
                4: `<svg style="width: 80px; height: 80px; position: relative; z-index: 3;" viewBox="0 0 512.001 512.001" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <circle style="fill:#ff0000;" cx="256.004" cy="256.004" r="246.855"></circle> <g> <path style="fill:#ff0000;" d="M126.306,385.694c-88.801-88.802-95.798-228.426-20.998-325.242 C97.023,66.853,89.051,73.85,81.45,81.45c-96.401,96.401-96.401,252.698,0,349.099s252.698,96.401,349.099,0 c7.599-7.599,14.597-15.573,20.999-23.858C354.733,481.492,215.108,474.494,126.306,385.694z"></path> <path style="fill:#ff0000;" d="M256.001,400.831c-14.756,0-29.505-2.01-43.85-6.031c-4.865-1.364-7.704-6.414-6.34-11.281 c1.364-4.865,6.414-7.706,11.28-6.34c25.455,7.137,52.366,7.137,77.821,0c4.869-1.361,9.916,1.475,11.28,6.34 s-1.475,9.916-6.34,11.28C285.509,398.82,270.751,400.831,256.001,400.831z"></path> </g> <path d="M256.001,0C114.841,0,0,114.841,0,256.001s114.841,256.001,256.001,256.001S512.001,397.16,512.001,256.001 C512,114.841,397.16,0,256.001,0z M256.001,493.701c-131.069,0-237.702-106.631-237.702-237.7S124.932,18.299,256.001,18.299 s237.702,106.632,237.702,237.7C493.701,387.07,387.068,493.701,256.001,493.701z"></path> <path d="M380.101,295.723c-68.432,68.43-179.778,68.428-248.203,0c-3.574-3.573-9.367-3.573-12.94,0 c-3.573,3.573-3.573,9.367,0,12.939c37.788,37.786,87.405,56.673,137.042,56.673c49.623,0,99.263-18.896,137.042-56.673 c3.573-3.573,3.573-9.367,0-12.939C389.468,292.15,383.676,292.149,380.101,295.723z"></path> <path d="M146.723,231.974c18.666,0,33.852-15.186,33.852-33.852s-15.186-33.852-33.852-33.852s-33.852,15.186-33.852,33.852 S128.058,231.974,146.723,231.974z"></path> <path d="M365.275,164.27c-18.666,0-33.852,15.186-33.852,33.852s15.186,33.852,33.852,33.852s33.852-15.186,33.852-33.852 S383.942,164.27,365.275,164.27z"></path> <g> <circle style="fill:#FFFFFF;" cx="155.969" cy="193.507" r="9.15"></circle> <circle style="fill:#FFFFFF;" cx="374.338" cy="193.507" r="9.15"></circle> </g> </g></svg>`,
                5: `<svg style="width: 80px; height: 80px; position: relative; z-index: 3;" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <circle style="fill:#ff0000;" cx="256.004" cy="256.004" r="246.855"></circle> <path style="fill:#ff0000;" d="M126.306,385.694c-88.801-88.802-95.798-228.426-20.998-325.242 C97.023,66.853,89.051,73.85,81.45,81.45c-96.401,96.401-96.401,252.698,0,349.099s252.698,96.401,349.099,0 c7.599-7.599,14.597-15.573,20.999-23.858C354.733,481.492,215.108,474.494,126.306,385.694z"></path> <path style="fill:#FFFFFF;" d="M373.277,215.314v42.759c-64.767,64.767-169.779,64.767-234.558,0v-42.759 C203.498,280.093,308.51,280.093,373.277,215.314z"></path> <path style="fill:#F95428;" d="M285.844,336.845c19.031,0,35.573,10.626,44.028,26.265c-20.166,16.384-45.87,26.192-73.867,26.192 c-27.998,0-53.702-9.808-73.867-26.192c8.442-15.64,24.997-26.265,44.015-26.265c11.187,0,21.52,3.672,29.852,9.882 C264.336,340.517,274.657,336.845,285.844,336.845z"></path> <path style="fill:#A81004;" d="M373.277,258.073v13.956c0,32.389-13.127,61.705-34.354,82.919c-2.867,2.879-5.892,5.6-9.052,8.161 c-8.454-15.64-24.997-26.265-44.028-26.265c-11.187,0-21.508,3.672-29.84,9.882c-8.332-6.209-18.665-9.882-29.852-9.882 c-19.019,0-35.573,10.626-44.015,26.265c-26.497-21.483-43.418-54.312-43.418-91.081v-13.956 C203.498,322.84,308.51,322.84,373.277,258.073z"></path> <path d="M171.704,184.358c0,5.053,4.097,9.15,9.15,9.15c5.053,0,9.15-4.097,9.15-9.15c0-31.956-25.998-57.954-57.954-57.954 s-57.954,25.998-57.954,57.954c0,5.053,4.097,9.15,9.15,9.15s9.15-4.097,9.15-9.15c0-21.866,17.789-39.655,39.655-39.655 S171.704,162.493,171.704,184.358z"></path> <path d="M379.951,126.405c-31.956,0-57.954,25.998-57.954,57.954c0,5.053,4.097,9.15,9.15,9.15c5.053,0,9.15-4.097,9.15-9.15 c0-21.866,17.789-39.655,39.655-39.655s39.655,17.789,39.655,39.655c0,5.053,4.097,9.15,9.15,9.15s9.15-4.097,9.15-9.15 C437.906,152.403,411.908,126.405,379.951,126.405z"></path> <path d="M376.777,206.86c-3.417-1.415-7.355-0.633-9.971,1.984c-29.596,29.602-68.947,45.904-110.805,45.904 s-81.211-16.302-110.813-45.904c-2.617-2.618-6.552-3.399-9.971-1.984c-3.419,1.416-5.648,4.753-5.648,8.453v56.715 c0,38.283,17.06,74.071,46.797,98.182c22.415,18.211,50.698,28.24,79.638,28.24c28.941,0,57.223-10.029,79.629-28.234 c3.438-2.788,6.727-5.753,9.758-8.797c23.883-23.868,37.035-55.615,37.035-89.391v-56.715 C382.427,211.613,380.197,208.277,376.777,206.86z M256.002,273.048c39.719,0,77.414-13.141,108.124-37.372v18.541 c-60.387,57.607-155.859,57.607-216.259,0v-18.542C178.583,259.903,216.284,273.048,256.002,273.048z M194.55,360.969 c7.676-9.378,19.207-14.976,31.601-14.976c8.87,0,17.302,2.79,24.385,8.069c3.244,2.417,7.69,2.417,10.934,0 c7.083-5.279,15.51-8.069,24.373-8.069c12.393,0,23.928,5.598,31.611,14.978c-17.962,12.409-39.478,19.18-61.451,19.18 C234.033,380.153,212.517,373.382,194.55,360.969z M332.44,348.493c-0.279,0.282-0.572,0.557-0.856,0.837 c-11.12-13.555-27.812-21.634-45.739-21.634c-10.623,0-20.819,2.772-29.84,8.066c-9.023-5.295-19.223-8.066-29.852-8.066 c-17.933,0-34.626,8.082-45.737,21.641c-19.246-18.765-30.744-43.854-32.348-70.78c31.57,24.767,69.75,37.159,107.935,37.159 c38.177,0,76.347-12.388,107.91-37.145C362.345,304.993,351.328,329.615,332.44,348.493z"></path> <path d="M255.999,0C114.841,0,0,114.841,0,256.001S114.841,512,255.999,512C397.159,512,512,397.159,512,256.001 C512,114.841,397.159,0,255.999,0z M255.999,493.701c-131.068,0-237.7-106.632-237.7-237.7c0-131.069,106.632-237.702,237.7-237.702 c131.069,0,237.702,106.632,237.702,237.702C493.701,387.068,387.068,493.701,255.999,493.701z"></path> </g></svg>`
            };
            return svgs[rating] || svgs[5];
        }

        // Function to update the happy star SVG based on selected rating
        function updateHappyStarSVG(rating) {
            const happyStar = document.querySelector('.rating-happy-star');
            if (happyStar) {
                happyStar.innerHTML = getHappyStarSVG(rating);
            }
        }

        // Function to attach event listeners to star rating inputs
        function attachStarRatingListeners() {
            const radioButtons = document.querySelectorAll('input[name="modal-rating"]');
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    updateHappyStarSVG(parseInt(this.value));
                });
            });
        }

        function openStudentModal(uniqueId, photo, name, email, studentId, yearLevel, taskId, rating, ratedAt) {
            const modal = document.getElementById('studentModal');
            
            // Store current data for rating submission
            currentModalData = {
                uniqueId: uniqueId,
                taskId: taskId,
                email: email
            };
            
            // Populate modal
            document.getElementById('modalStudentAvatar').src = photo;
            document.getElementById('modalStudentName').textContent = name;
            document.getElementById('modalStudentEmail').textContent = email;
            document.getElementById('modalStudentId').textContent = studentId ? 'ID: ' + studentId : '';
            document.getElementById('modalStudentYear').textContent = yearLevel || '';
            
            // Hide empty badges
            document.getElementById('modalStudentId').style.display = studentId ? 'inline-block' : 'none';
            document.getElementById('modalStudentYear').style.display = yearLevel ? 'inline-block' : 'none';
            
            // Populate rating section
            const ratingContainer = document.getElementById('modalRatingContainer');
            
            if (rating > 0) {
                // Already rated - show static stars
                let starsHtml = '<div class="modal-current-rating"><div class="modal-rating-stars">';
                for (let i = 1; i <= 5; i++) {
                    starsHtml += `<svg class="star-svg ${i <= rating ? 'filled' : 'empty'}" viewBox="0 0 1920 1920" xmlns="http://www.w3.org/2000/svg"><path d="M1915.918 737.475c-10.955-33.543-42.014-56.131-77.364-56.131h-612.029l-189.063-582.1v-.112C1026.394 65.588 995.335 43 959.984 43c-35.237 0-66.41 22.588-77.365 56.245L693.443 681.344H81.415c-35.35 0-66.41 22.588-77.365 56.131-10.955 33.544.79 70.137 29.478 91.03l495.247 359.831-189.177 582.212c-10.955 33.657 1.13 70.25 29.817 90.918 14.23 10.278 30.946 15.487 47.66 15.487 16.716 0 33.432-5.21 47.775-15.6l495.134-359.718 495.021 359.718c28.574 20.781 67.087 20.781 95.662.113 28.687-20.668 40.658-57.261 29.703-91.03l-189.176-582.1 495.36-359.83c28.574-20.894 40.433-57.487 29.364-91.03" fill-rule="evenodd"></path></svg>`;
                }
                starsHtml += '</div></div>';
                ratingContainer.innerHTML = starsHtml;
            } else {
                // Not rated - show interactive stars with buttons
                let starsHtml = `
                    <div class="rating-container-wrapper">
                        <div class="rating-happy-star">
                            <svg style="width: 80px; height: 80px; position: relative; z-index: 3;" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                                <circle style="fill:#FF0000;" cx="256.004" cy="256.004" r="246.855"></circle>
                                <path style="fill:#FF0000;" d="M126.306,385.694c-88.801-88.802-95.798-228.426-20.998-325.242 C97.023,66.853,89.051,73.85,81.45,81.45c-96.401,96.401-96.401,252.698,0,349.099s252.698,96.401,349.099,0 c7.599-7.599,14.597-15.573,20.999-23.858C354.733,481.492,215.108,474.494,126.306,385.694z"></path>
                                <path style="fill:#FFFFFF;" d="M373.277,215.314v42.759c-64.767,64.767-169.779,64.767-234.558,0v-42.759 C203.498,280.093,308.51,280.093,373.277,215.314z"></path>
                                <path style="fill:#F95428;" d="M285.844,336.845c19.031,0,35.573,10.626,44.028,26.265c-20.166,16.384-45.87,26.192-73.867,26.192 c-27.998,0-53.702-9.808-73.867-26.192c8.442-15.64,24.997-26.265,44.015-26.265c11.187,0,21.52,3.672,29.852,9.882 C264.336,340.517,274.657,336.845,285.844,336.845z"></path>
                                <path style="fill:#A81004;" d="M373.277,258.073v13.956c0,32.389-13.127,61.705-34.354,82.919c-2.867,2.879-5.892,5.6-9.052,8.161 c-8.454-15.64-24.997-26.265-44.028-26.265c-11.187,0-21.508,3.672-29.84,9.882c-8.332-6.209-18.665-9.882-29.852-9.882 c-19.019,0-35.573,10.626-44.015,26.265c-26.497-21.483-43.418-54.312-43.418-91.081v-13.956 C203.498,322.84,308.51,322.84,373.277,258.073z"></path>
                                <path d="M171.704,184.358c0,5.053,4.097,9.15,9.15,9.15c5.053,0,9.15-4.097,9.15-9.15c0-31.956-25.998-57.954-57.954-57.954 s-57.954,25.998-57.954,57.954c0,5.053,4.097,9.15,9.15,9.15s9.15-4.097,9.15-9.15c0-21.866,17.789-39.655,39.655-39.655 S171.704,162.493,171.704,184.358z"></path>
                                <path d="M379.951,126.405c-31.956,0-57.954,25.998-57.954,57.954c0,5.053,4.097,9.15,9.15,9.15c5.053,0,9.15-4.097,9.15-9.15 c0-21.866,17.789-39.655,39.655-39.655s39.655,17.789,39.655,39.655c0,5.053,4.097,9.15,9.15,9.15s9.15-4.097,9.15-9.15 C437.906,152.403,411.908,126.405,379.951,126.405z"></path>
                                <path d="M376.777,206.86c-3.417-1.415-7.355-0.633-9.971,1.984c-29.596,29.602-68.947,45.904-110.805,45.904 s-81.211-16.302-110.813-45.904c-2.617-2.618-6.552-3.399-9.971-1.984c-3.419,1.416-5.648,4.753-5.648,8.453v56.715 c0,38.283,17.06,74.071,46.797,98.182c22.415,18.211,50.698,28.24,79.638,28.24c28.941,0,57.223-10.029,79.629-28.234 c3.438-2.788,6.727-5.753,9.758-8.797c23.883-23.868,37.035-55.615,37.035-89.391v-56.715 C382.427,211.613,380.197,208.277,376.777,206.86z M256.002,273.048c39.719,0,77.414-13.141,108.124-37.372v18.541 c-60.387,57.607-155.859,57.607-216.259,0v-18.542C178.583,259.903,216.284,273.048,256.002,273.048z M194.55,360.969 c7.676-9.378,19.207-14.976,31.601-14.976c8.87,0,17.302,2.79,24.385,8.069c3.244,2.417,7.69,2.417,10.934,0 c7.083-5.279,15.51-8.069,24.373-8.069c12.393,0,23.928,5.598,31.611,14.978c-17.962,12.409-39.478,19.18-61.451,19.18 C234.033,380.153,212.517,373.382,194.55,360.969z M332.44,348.493c-0.279,0.282-0.572,0.557-0.856,0.837 c-11.12-13.555-27.812-21.634-45.739-21.634c-10.623,0-20.819,2.772-29.84,8.066c-9.023-5.295-19.223-8.066-29.852-8.066 c-17.933,0-34.626,8.082-45.737,21.641c-19.246-18.765-30.744-43.854-32.348-70.78c31.57,24.767,69.75,37.159,107.935,37.159 c38.177,0,76.347-12.388,107.91-37.145C362.345,304.993,351.328,329.615,332.44,348.493z"></path>
                                <path d="M255.999,0C114.841,0,0,114.841,0,256.001S114.841,512,255.999,512C397.159,512,512,397.159,512,256.001 C512,114.841,397.159,0,255.999,0z M255.999,493.701c-131.068,0-237.7-106.632-237.7-237.7c0-131.069,106.632-237.702,237.7-237.702 c131.069,0,237.702,106.632,237.702,237.702C493.701,387.068,387.068,493.701,255.999,493.701z"></path>
                            </svg>
                        </div>
                        <div class="rating-label-text">Rate your Student Performance</div>
                        <div class="modal-star-rating" id="modalRating-${uniqueId}">
                `;
                for (let i = 5; i >= 1; i--) {
                    starsHtml += `
                            <input type="radio" name="modal-rating" value="${i}" id="modalStar${i}">
                            <label for="modalStar${i}" title="${i} stars" class="star-label">
                                <svg class="star-svg" viewBox="0 0 1920 1920" xmlns="http://www.w3.org/2000/svg"><path d="M1915.918 737.475c-10.955-33.543-42.014-56.131-77.364-56.131h-612.029l-189.063-582.1v-.112C1026.394 65.588 995.335 43 959.984 43c-35.237 0-66.41 22.588-77.365 56.245L693.443 681.344H81.415c-35.35 0-66.41 22.588-77.365 56.131-10.955 33.544.79 70.137 29.478 91.03l495.247 359.831-189.177 582.212c-10.955 33.657 1.13 70.25 29.817 90.918 14.23 10.278 30.946 15.487 47.66 15.487 16.716 0 33.432-5.21 47.775-15.6l495.134-359.718 495.021 359.718c28.574 20.781 67.087 20.781 95.662.113 28.687-20.668 40.658-57.261 29.703-91.03l-189.176-582.1 495.36-359.83c28.574-20.894 40.433-57.487 29.364-91.03" fill-rule="evenodd"></path></svg>
                            </label>
                        `;
                }
                starsHtml += `
                        </div>
                        <div class="rating-actions">
                            <button class="rating-submit-btn" onclick="submitModalRating(this)">Submit</button>
                            <button class="rating-skip-btn" onclick="closeStudentModal()">No, Thanks!</button>
                        </div>
                    </div>
                `;
                ratingContainer.innerHTML = starsHtml;
                // Attach event listeners to update happy star on selection
                attachStarRatingListeners();
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeStudentModal() {
            const modal = document.getElementById('studentModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function submitModalRating(btn) {
            const { uniqueId, taskId, email } = currentModalData;
            const ratingContainer = document.getElementById('modalRatingContainer');
            
            // Get the selected rating from radio button
            const selectedRating = document.querySelector('input[name="modal-rating"]:checked');
            if (!selectedRating) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Please select a rating',
                    confirmButtonColor: '#ff0000'
                });
                return;
            }
            
            const rating = parseInt(selectedRating.value);
            
            // Disable further clicks
            const starRating = ratingContainer.querySelector('.modal-star-rating');
            if (starRating) {
                starRating.classList.add('rated');
                const labels = starRating.querySelectorAll('label');
                labels.forEach(label => label.style.pointerEvents = 'none');
            }
            
            // Disable buttons
            const buttons = ratingContainer.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);
            
            // Send rating to server
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('student_email', email);
            formData.append('rating', rating);
            
            fetch('rate_student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Show success message
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
                                ">Student Rated Successfully!</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">You gave this student a <strong>${rating}/5 stars</strong> rating.</p>
                            </div>
                        `,
                        confirmButtonColor: '#ff0000',
                        confirmButtonText: 'Okay',
                        customClass: {
                            popup: 'swal2-success-popup',
                            confirmButton: 'swal2-success-button'
                        },
                        showConfirmButton: true,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (modal) => {
                            modal.style.borderRadius = '20px';
                            modal.style.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.15)';
                        }
                    });
                    
                    // Close the modal
                    setTimeout(() => {
                        closeStudentModal();
                        
                        // Update the button on the page
                        const rateBtn = document.getElementById(`rate-btn-${uniqueId}`);
                        if (rateBtn) {
                            // Create the star rating display
                            let starsDisplay = '';
                            for (let i = 1; i <= 5; i++) {
                                starsDisplay += (i <= rating) ? '★' : '☆';
                            }
                            starsDisplay += ' ' + rating + '/5';
                            
                            // Replace the button with the rated display
                            const newElement = document.createElement('div');
                            newElement.id = `rate-btn-${uniqueId}`;
                            newElement.className = 'task-body-rate-btn';
                            newElement.style.cursor = 'default';
                            newElement.style.background = '#f5f5f5';
                            newElement.style.color = '#333';
                            newElement.textContent = starsDisplay;
                            
                            rateBtn.parentNode.replaceChild(newElement, rateBtn);
                        }
                    }, 1000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProfileModal();
                closeStudentModal();
                closeChatModal();
                document.getElementById('messageDropdown')?.classList.remove('show');
                document.getElementById('notificationDropdown')?.classList.remove('show');
            }
        });

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
            if (messageDropdown && !messageDropdown.contains(event.target) && !event.target.closest('.message-btn')) {
                messageDropdown.classList.remove('show');
            }
            if (notificationDropdown && !notificationDropdown.contains(event.target) && !event.target.closest('.notification-btn')) {
                notificationDropdown.classList.remove('show');
            }
        });

        // ==================== MESSAGING SYSTEM ====================
        let messageContacts = [];
        let currentChatContact = null;
        let chatPollInterval = null;

        // Load message contacts on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMessageContacts();
            checkUnreadMessages();
            // Check for new messages every 10 seconds
            setInterval(checkUnreadMessages, 10000);
        });

        // Load contacts for messaging
        function loadMessageContacts() {
            fetch('message_handler.php?action=get_contacts')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageContacts = data.contacts;
                        renderMessageContacts(messageContacts);
                        updateMessageBadges();
                    } else {
                        document.getElementById('messageList').innerHTML = '<div class="message-empty">No approved students yet.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading contacts:', error);
                    document.getElementById('messageList').innerHTML = '<div class="message-empty">Error loading contacts.</div>';
                });
        }

        // Render message contacts list
        function renderMessageContacts(contacts) {
            const messageList = document.getElementById('messageList');
            
            if (contacts.length === 0) {
                messageList.innerHTML = '<div class="message-empty">No approved students yet.<br><small style="color:#aaa;">Students you approved will appear here.</small></div>';
                return;
            }

            let html = '';
            contacts.forEach(contact => {
                const timeAgo = contact.last_message_time ? formatTimeAgo(contact.last_message_time) : '';
                const hasUnread = contact.unread_count > 0;
                const lastMsg = contact.last_message ? (contact.last_message.length > 40 ? contact.last_message.substring(0, 40) + '...' : contact.last_message) : 'No messages yet';
                
                html += `
                    <div class="message-card ${hasUnread ? 'has-unread' : ''}" onclick="openChat('${contact.email}', '${contact.name.replace(/'/g, "\\'")}', '${contact.photo}', '${contact.role}')">
                        <img src="${contact.photo}" alt="${contact.name}" class="message-avatar" onerror="this.src='profile-default.png'">
                        <div class="message-meta">
                            <h5>${contact.name}</h5>
                            <p>${lastMsg}</p>
                        </div>
                        <div class="message-time">
                            ${timeAgo ? `<span>${timeAgo}</span>` : ''}
                            ${hasUnread ? `<span class="message-unread-badge">${contact.unread_count}</span>` : ''}
                        </div>
                    </div>
                `;
            });
            
            messageList.innerHTML = html;
        }

        // Filter message contacts
        function filterMessageContacts() {
            const searchTerm = document.getElementById('messageSearchInput').value.toLowerCase();
            const filtered = messageContacts.filter(contact => 
                contact.name.toLowerCase().includes(searchTerm) ||
                contact.email.toLowerCase().includes(searchTerm)
            );
            renderMessageContacts(filtered);
        }

        // Update message badges
        function updateMessageBadges() {
            const totalUnread = messageContacts.reduce((sum, c) => sum + c.unread_count, 0);
            const messageDot = document.getElementById('messageDot');
            const messagePill = document.getElementById('messagePill');
            
            if (totalUnread > 0) {
                messageDot.style.display = 'flex';
                messageDot.textContent = totalUnread > 99 ? '99+' : totalUnread;
                messagePill.style.display = 'inline-block';
                messagePill.textContent = totalUnread + ' new';
            } else {
                messageDot.style.display = 'none';
                messagePill.style.display = 'none';
            }
        }

        // Check for unread messages
        function checkUnreadMessages() {
            fetch('message_handler.php?action=get_unread_count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const messageDot = document.getElementById('messageDot');
                        if (data.unread_count > 0) {
                            messageDot.style.display = 'flex';
                            messageDot.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        } else {
                            messageDot.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error checking messages:', error));
        }

        // Open chat modal
        function openChat(email, name, photo, role) {
            currentChatContact = { email, name, photo, role };
            
            // Update chat header
            document.getElementById('chatAvatar').innerHTML = `<img src="${photo || 'profile-default.png'}" onerror="this.src='profile-default.png'" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
            document.getElementById('chatUserName').textContent = name;
            document.getElementById('chatUserStatus').textContent = role === 'teacher' ? 'Teacher' : 'Student';
            
            // Show modal
            document.getElementById('chatModal').classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Hide message dropdown
            document.getElementById('messageDropdown').classList.remove('show');
            
            // Load messages
            loadChatMessages();
            
            // Mark messages as read
            markMessagesAsRead(email);
            
            // Start polling for new messages
            if (chatPollInterval) clearInterval(chatPollInterval);
            chatPollInterval = setInterval(loadChatMessages, 3000);
            
            // Focus input
            setTimeout(() => document.getElementById('chatInput').focus(), 300);
        }

        // Close chat modal
        function closeChatModal() {
            document.getElementById('chatModal').classList.remove('show');
            document.body.style.overflow = '';
            currentChatContact = null;
            
            // Stop polling
            if (chatPollInterval) {
                clearInterval(chatPollInterval);
                chatPollInterval = null;
            }
            
            // Refresh contacts to update last message
            loadMessageContacts();
        }

        // Load chat messages
        function loadChatMessages() {
            if (!currentChatContact) return;
            
            fetch(`message_handler.php?action=get_messages&contact_email=${encodeURIComponent(currentChatContact.email)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderChatMessages(data.messages);
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        // Render chat messages
        function renderChatMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            
            if (messages.length === 0) {
                chatMessages.innerHTML = '<div class="chat-empty">Start a conversation!<br><small style="color:#aaa;">Send your first message below.</small></div>';
                return;
            }

            let html = '';
            messages.forEach(msg => {
                const time = formatMessageTime(msg.created_at);
                html += `
                    <div class="chat-message ${msg.is_mine ? 'sent' : 'received'}">
                        ${msg.message}
                        <span class="chat-message-time">${time}</span>
                    </div>
                `;
            });
            
            chatMessages.innerHTML = html;
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Send message
        function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (!message || !currentChatContact) return;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_email', currentChatContact.email);
            formData.append('receiver_role', currentChatContact.role);
            formData.append('message', message);
            
            // Clear input immediately
            input.value = '';
            
            // Add message to chat immediately (optimistic update)
            const chatMessages = document.getElementById('chatMessages');
            const emptyMsg = chatMessages.querySelector('.chat-empty');
            if (emptyMsg) emptyMsg.remove();
            
            const msgDiv = document.createElement('div');
            msgDiv.className = 'chat-message sent';
            msgDiv.innerHTML = `${message}<span class="chat-message-time">Just now</span>`;
            chatMessages.appendChild(msgDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            
            fetch('message_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to send message',
                        confirmButtonColor: '#ff0000'
                    });
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to send message',
                    confirmButtonColor: '#ff0000'
                });
            });
        }

        // Mark messages as read
        function markMessagesAsRead(contactEmail) {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('contact_email', contactEmail);
            
            fetch('message_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update local contact unread count
                    const contact = messageContacts.find(c => c.email === contactEmail);
                    if (contact) {
                        contact.unread_count = 0;
                        updateMessageBadges();
                    }
                }
            })
            .catch(error => console.error('Error marking read:', error));
        }

        // Format time ago
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return date.toLocaleDateString();
        }

        // Format message time
        function formatMessageTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const isToday = date.toDateString() === now.toDateString();
            
            if (isToday) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        // ==================== END MESSAGING SYSTEM ====================

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

        // Set active navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.bottom-nav-item');
            navItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href').includes(currentPage)) {
                    item.classList.add('active');
                }
            });
        });
    </script>

    <!-- Chat Modal -->
    <div class="chat-modal" id="chatModal">
        <div class="chat-container">
            <div class="chat-header">
                <div class="chat-user-info">
                    <div class="chat-avatar" id="chatAvatar">?</div>
                    <div class="chat-user-details">
                        <div class="chat-user-name" id="chatUserName">Student Name</div>
                        <div class="chat-user-status" id="chatUserStatus">Student</div>
                    </div>
                </div>
                <button class="chat-close-btn" onclick="closeChatModal()">×</button>
            </div>
            <div class="chat-messages" id="chatMessages">
                <div class="chat-empty">Select a conversation to start chatting</div>
            </div>
            <div class="chat-input-area">
                <input type="text" class="chat-input" id="chatInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="chat-send-btn" onclick="sendMessage()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 2L11 13M22 2L15 22L11 13M22 2L2 9L11 13"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="teacher_task_page.php" class="bottom-nav-item" title="Home">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </span>
            <span class="bottom-nav-label">Home</span>
        </a>
        <a href="assigned_tasks.php" class="bottom-nav-item" title="Activity">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                </svg>
            </span>
            <span class="bottom-nav-label">Activity</span>
        </a>
        <a href="teacher_record.php" class="bottom-nav-item active" title="History">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </span>
            <span class="bottom-nav-label">History</span>
        </a>
        <a href="teacher_message.php" class="bottom-nav-item" title="Messages">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="badge"></span>
            </span>
            <span class="bottom-nav-label">Messages</span>
        </a>
        <a href="teacher_profile.php" class="bottom-nav-item" title="Account">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </span>
            <span class="bottom-nav-label">Account</span>
        </a>
    </nav>
</body>
</html>


