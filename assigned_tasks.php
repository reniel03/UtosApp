<?php
session_start();

// Handle AJAX request to mark all notifications as read FIRST
if (isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $user_email = $_SESSION['email'] ?? '';
    if ($user_email) {
        $_SESSION['notif_last_read_' . $user_email] = date('Y-m-d H:i:s');
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'timestamp' => $_SESSION['notif_last_read_' . $user_email]]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No email in session']);
    }
    exit();
}

include 'db_connect.php';

// Use email for user identification, as per process_task.php
if (!isset($_SESSION['email']) || !isset($_SESSION['user_role'])) {
    header('Location: login.php');
    exit();
}
$user_email = $_SESSION['email'];
$user_role = $_SESSION['user_role'];

// Get the last read timestamp
$last_read = isset($_SESSION['notif_last_read_' . $user_email]) ? $_SESSION['notif_last_read_' . $user_email] : null;

// Fetch submitted tasks for this user
$tasks = [];
$task_progress = [];

// For teacher, show tasks they posted with student progress (exclude completed tasks)
if ($user_role === 'teacher') {
    $stmt = $conn->prepare('SELECT * FROM tasks WHERE teacher_email = ? AND id NOT IN (
        SELECT DISTINCT task_id FROM student_todos WHERE is_completed = 1
    ) ORDER BY created_at DESC');
    $stmt->bind_param('s', $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
        
        // Get student progress for each task with full student details
        $task_id = $row['id'];
        $progress_stmt = $conn->prepare('
            SELECT 
                st.student_email, 
                st.is_completed, 
                st.status, 
                st.created_at,
                s.first_name,
                s.middle_name,
                s.last_name,
                s.student_id,
                s.year_level,
                s.course,
                s.photo,
                s.attachment
            FROM student_todos st
            JOIN students s ON st.student_email = s.email
            WHERE st.task_id = ? 
            ORDER BY st.created_at ASC
        ');
        $progress_stmt->bind_param('i', $task_id);
        $progress_stmt->execute();
        $progress_result = $progress_stmt->get_result();
        
        $students_accepted = [];
        $completed_count = 0;
        $ongoing_count = 0;
        $total_accepted = 0;
        
        while ($progress_row = $progress_result->fetch_assoc()) {
            $total_accepted++;
            if ($progress_row['is_completed']) {
                $completed_count++;
            }
            if ($progress_row['status'] === 'ongoing') {
                $ongoing_count++;
            }
            $students_accepted[] = $progress_row;
        }
        
        $task_progress[$task_id] = [
            'students' => $students_accepted,
            'total' => $total_accepted,
            'completed' => $completed_count,
            'ongoing' => $ongoing_count,
            'pending' => $total_accepted - $completed_count - $ongoing_count
        ];
        
        $progress_stmt->close();
    }
    $stmt->close();
}
// For student, show all tasks (or filter by class/section if you have that info)
else if ($user_role === 'student') {
    $result = $conn->query('SELECT * FROM tasks ORDER BY created_at DESC');
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
}

// Fetch notifications for teachers (students who accepted their tasks)
$notifications = [];
$notification_count = 0;
if ($user_role === 'teacher') {
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
        $notif_stmt->bind_param('s', $user_email);
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
    <title>UtosApp</title>
    <link rel="stylesheet" href="style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
        html {
            scroll-behavior: smooth;
            overflow-y: scroll;
        }
        body {
            background: #fff;
            margin: 0;
            font-family: Arial, sans-serif;
            overflow-x: hidden;
        }
        /* Navigation bar - HIDE ON ALL DEVICES */
        .nav-bar {
            display: none !important;
        }

        body, html {
            padding: 0;
            margin: 0;
            overflow-x: hidden;
        }

        body {
            background: #ffffff;
            min-height: 100vh;
            padding-bottom: 90px;
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

        /* Main Content Wrapper */
        .page-wrapper {
            width: 95%;
            max-width: 100%;
            margin: 0;
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
        }

        /* Page Header */
        .page-header-section {
            text-align: center;
            margin: 0;
            animation: fadeInDown 0.6s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header-title {
            font-size: 3.5em;
            color: #ff0000;
            font-weight: 800;
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            width: 100%;
        }

        .page-header-title-emoji {
            font-size: 1.2em;
        }

        .page-header-subtitle {
            font-size: 1.8em;
            color: #666;
            font-weight: 500;
            margin: 10px 0;
            text-align: center;
            width: 100%;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
            padding: 0 20px;
        }

        .stat-card-item {
            background: white;
            border-radius: 25px;
            padding: 35px 25px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(255, 0, 0, 0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            animation: slideUp 0.6s ease backwards;
        }

        .stat-card-item:nth-child(1) { animation-delay: 0.1s; }
        .stat-card-item:nth-child(2) { animation-delay: 0.2s; }
        .stat-card-item:nth-child(3) { animation-delay: 0.3s; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card-item:hover {
            transform: translateY(-8px);
            border-color: #ff0000;
            box-shadow: 0 20px 50px rgba(255, 0, 0, 0.2);
        }

        .stat-icon-large {
            font-size: 3.5em;
            margin-bottom: 12px;
        }

        .stat-number-large {
            font-size: 3.5em;
            font-weight: 800;
            color: #ff0000;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label-large {
            font-size: 1.1em;
            color: #888;
            font-weight: 600;
        }

        /* Content Section */
        .content-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 40px 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .page-wrapper {
                justify-content: flex-start;
                align-items: flex-start;
                padding: 15px;
                margin: 0;
            }

            .page-header-title {
                font-size: 2.8em;
                gap: 10px;
                width: 100%;
                justify-content: center;
            }

            .page-header-title-emoji {
                font-size: 1em;
            }

            .page-header-subtitle {
                font-size: 1.6em;
                text-align: center;
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                padding: 0 10px;
            }

            .stat-card-item {
                padding: 25px 20px;
            }

            .stat-icon-large {
                font-size: 2.5em;
            }

            .stat-number-large {
                font-size: 2.5em;
            }

            .content-section {
                padding: 0 15px 30px 15px;
            }
        }

        @media (max-width: 500px) {
            .page-wrapper {
                display: flex;
                justify-content: flex-start;
                align-items: flex-start;
                padding: 15px;
            }

            .page-header-title {
                font-size: 2.2em;
                width: 100%;
                justify-content: center;
            }

            .page-header-subtitle {
                width: 80%;
                text-align: center;
                font-size: 1.4em;
            }

            .stat-card-item {
                padding: 20px 15px;
            }

            .stat-icon-large {
                font-size: 2em;
                margin-bottom: 8px;
            }

            .stat-number-large {
                font-size: 2em;
                margin-bottom: 6px;
            }

            .stat-label-large {
                font-size: 1em;
            }
        }
            justify-content: space-between;
            align-items: center;
            color: white;
            margin-left: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            box-sizing: border-box;
            z-index: 1000;
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
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(255, 0, 0, 0.2), 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 3px solid #ff0000;
            padding: 0;
            display: none;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            z-index: 1200;
            overflow: hidden;
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
            padding: 20px 24px;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            border-bottom: none;
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
            font-size: 1.1em;
        }

        .message-pill {
            background: white;
            color: #ff0000;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.85em;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .message-search {
            position: relative;
            padding: 15px 20px;
            background: #fff5f5;
            border-bottom: 1px solid #fee;
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
            top: 26px;
            left: 32px;
            width: 20px;
            height: 20px;
            opacity: 0.6;
        }

        .message-list {
            max-height: 440px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0;
            padding: 15px;
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

        .message-empty {
            text-align: center;
            padding: 50px 20px;
            color: #999;
            font-weight: 600;
            border: 2px dashed #eee;
            border-radius: 16px;
            background: linear-gradient(135deg, #fafafa, #fff5f5);
        }

        .message-empty::before {
            content: '📭';
            display: block;
            font-size: 3em;
            margin-bottom: 15px;
        }

        .message-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            border-radius: 16px;
            border: 2px solid transparent;
            background: linear-gradient(135deg, #ffffff, #fff8f8);
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 10px;
            position: relative;
        }

        .message-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 0 4px 4px 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .message-card:hover::before {
            opacity: 1;
        }

        .message-card:hover {
            border-color: #ff0000;
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.15);
        }

        .message-card.has-unread {
            background: linear-gradient(135deg, #fff0f0, #ffffff);
            border-color: rgba(255, 0, 0, 0.2);
        }

        .message-card.has-unread::before {
            opacity: 1;
        }

        .message-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ff0000;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.25);
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .message-card:hover .message-avatar {
            transform: scale(1.1);
        }

        .message-meta {
            flex: 1;
            min-width: 0;
        }

        .message-meta h5 {
            margin: 0 0 4px 0;
            font-size: 1.1em;
            color: #222;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .message-meta p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
        }

        .message-time {
            font-size: 0.8em;
            color: #999;
            font-weight: 600;
            white-space: nowrap;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .message-unread-badge {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            min-width: 22px;
            height: 22px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75em;
            font-weight: 800;
            padding: 0 6px;
        }

        .message-dot {
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

        /* Chat Modal Styles */
        .chat-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 5000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .chat-modal.show {
            display: flex;
            opacity: 1;
        }

        .chat-container {
            width: 550px;
            max-width: 95%;
            height: 700px;
            max-height: 85vh;
            background: white;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(255, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: chatSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes chatSlideIn {
            0% {
                opacity: 0;
                transform: scale(0.9) translateY(30px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .chat-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .chat-header-back {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            transition: all 0.3s ease;
        }

        .chat-header-back:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .chat-header-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid white;
            object-fit: cover;
        }

        .chat-header-info {
            flex: 1;
        }

        .chat-header-name {
            font-size: 1.3em;
            font-weight: 800;
            margin: 0;
        }

        .chat-header-status {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: linear-gradient(135deg, #fafafa, #fff5f5);
        }

        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 10px;
        }

        .chat-message {
            max-width: 75%;
            padding: 12px 18px;
            border-radius: 18px;
            font-size: 1em;
            line-height: 1.5;
            position: relative;
            animation: messageIn 0.3s ease;
        }

        @keyframes messageIn {
            0% {
                opacity: 0;
                transform: translateY(10px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
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
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .chat-message-time {
            font-size: 0.75em;
            opacity: 0.7;
            margin-top: 6px;
            display: block;
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
            border: 2px solid #f0f0f0;
            border-radius: 25px;
            font-size: 1em;
            transition: all 0.3s ease;
            outline: none;
        }

        .chat-input:focus {
            border-color: #ff0000;
            box-shadow: 0 0 0 4px rgba(255, 0, 0, 0.1);
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
        }

        .chat-send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.4);
        }

        .chat-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .chat-send-btn svg {
            width: 24px;
            height: 24px;
        }

        .chat-empty {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .chat-empty::before {
            content: '💬';
            display: block;
            font-size: 3em;
            margin-bottom: 15px;
        }

        /* Notification dropdown styles (matched to teacher task page) */
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
            z-index: 1300;
            overflow: hidden;
            box-sizing: border-box;
        }

        .notification-dropdown *,
        .notification-dropdown *::before,
        .notification-dropdown *::after {
            box-sizing: border-box;
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

        .notification-count-badge {
            background: white;
            color: #ff0000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 800;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
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
            box-shadow: 0 0 35px rgba(255, 255, 255, 0.5);
        }
        .profile-details {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        .profile-info .detail-group {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.8rem 1rem;
            margin-bottom: 0.8rem;
        }

        .profile-info .detail-group:last-child {
            margin-bottom: 0;
        }

        .profile-info .detail-group p {
            margin: 0;
            font-size: 1.3em;
            line-height: 1.4;
            color: rgba(255, 255, 255, 0.95);
        }

        .profile-info .detail-group strong {
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.05em;
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
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-modal-image {
            max-width: 85vw;
            max-height: 80vh;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
        }

        /* Task Detail Modal Styles */
        .task-detail-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .task-detail-modal.show {
            display: flex;
        }
        body.modal-open {
            overflow: hidden !important;
        }
        .task-detail-content {
            position: relative;
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            margin: auto;
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
        .task-detail-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.2s;
        }
        .task-detail-close:hover {
            color: #f00;
        }
        .task-detail-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ff0000;
        }
        .task-detail-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.5em;
        }
        .task-detail-body {
            margin-bottom: 25px;
        }
        .task-detail-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.95em;
            flex-wrap: wrap;
        }
        .task-detail-row svg {
            flex-shrink: 0;
        }
        .task-detail-row label {
            font-weight: 600;
            color: #555;
            min-width: auto;
            text-align: center;
        }
        .task-detail-row span {
            color: #333;
            text-align: center;
        }
        .task-detail-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .task-detail-section label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        .task-detail-section p {
            margin: 0;
            color: #666;
            line-height: 1.5;
        }
        .task-detail-attachment-image {
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: transform 0.2s;
        }
        .task-detail-attachment-image:hover {
            transform: scale(1.02);
        }
        .task-detail-student-info {
            background: white !important;
            padding: 25px 20px !important;
            margin-bottom: 15px !important;
            border-radius: 12px !important;
            border: 1px solid #e0e0e0 !important;
            transition: background 0.3s ease;
        }
        .task-detail-student-info:hover {
            background: white !important;
        }
        .task-detail-footer {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .task-detail-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            background: #ff0000;
            color: white;
        }
        .task-detail-btn:hover {
            background: #cc0000;
            transform: translateY(-2px);
        }
        .task-detail-btn:active {
            transform: translateY(0);
        }

        /* Task Detail Modal - Header with Buttons */
        .task-detail-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ff0000;
        }
        .task-detail-header-top h2 {
            margin: 0;
            color: #333;
            font-size: 1.5em;
            flex: 1;
        }
        .task-detail-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .task-detail-close {
            position: relative;
            top: 0;
            right: 0;
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.2s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .task-detail-close:hover {
            color: #f00;
            background: #f9f9f9;
            border-radius: 50%;
        }

        /* Approve Button */
        .task-detail-approve-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        .task-detail-approve-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
        }
        .task-detail-approve-btn:active:not(:disabled) {
            transform: translateY(0);
        }
        .task-detail-approve-btn:disabled {
            background: linear-gradient(135deg, #cccccc, #999999);
            cursor: not-allowed;
            opacity: 0.6;
        }
        .approve-icon {
            font-size: 1.1em;
        }
        .approve-text {
            display: none;
        }
        @media (min-width: 480px) {
            .approve-text {
                display: inline;
            }
        }

        /* Progress Tracker */
        .task-progress-tracker {
            background: white;
            padding: 25px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
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
            right: 20%;
            height: 6px;
            background: #e0e0e0;
            z-index: 0;
            transition: background 0.3s ease;
        }
        .progress-steps.has-progress::before {
            background: linear-gradient(to right, #ff0000 0%, #ff0000 54%, #e0e0e0 54%, #e0e0e0 100%);
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
        .progress-step.completed .step-icon svg * {
            fill: #ff0000 !important;
            stroke: #ff0000 !important;
        }
        .progress-step.completed .step-icon svg path,
        .progress-step.completed .step-icon svg circle,
        .progress-step.completed .step-icon svg polyline {
            fill: #ff0000 !important;
            stroke: #ff0000 !important;
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

        .main-content {
            margin-left: 0;
            padding-top: 0;
            min-height: auto;
            background: #f9f9f9;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .assigned-tasks-container {
            max-width: 1200px;
            width: 100%;
            margin: 25px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(255, 0, 0, 0.12);
            padding: 40px;
            display: center;
        }
        .tasks-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 25px;
        }
        .tasks-title {
            font-size: 2.4em;
            color: #ff0000;
            font-weight: bold;
            text-align: center;
        }
        .tasks-date {
            font-size: 1.2em;
            color: #888;
        }
        .tasks-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .task-card {
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
        }
        .task-card:hover {
            transform: translateY(-5px);
            border-color: #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.15);
        }
        .task-card-header {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #f5f5f5;
            padding: 0;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 150px;
            overflow: hidden;
            position: relative;
        }
        .task-card-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            pointer-events: none;
        }
        .task-title {
            font-size: 1.5em;
            color: #000;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            text-align: center;
            word-break: break-word;
        }

        .task-title-badge {
            font-size: 1.5em;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        .task-meta {
            display: none;
        }
        .task-meta div {
            display: none;
        }
        .task-card-body {
            padding: 12px;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            font-size: 0.9em;
            color: #000;
        }
        .task-desc {
            display: none;
        }

        .task-attachment {
            display: none;
        }

        .task-attachment img {
            display: none;
        }

        .task-attachment img:hover {
            display: none;
        }

        .task-attachment a {
            display: none;
        }

        .task-attachment a:hover {
            display: none;
        }

        /* Progress Tracking Styles - Hidden in compact view */
        .progress-section {
            display: none;
        }

        .progress-header {
            display: none;
        }

        .progress-stats {
            display: none;
        }

        .stat-box {
            display: none;
        }

        .stat-label {
            display: none;
        }

        .stat-value {
            display: none;
        }

        .progress-bar-container {
            display: none;
        }

        .progress-bar {
            display: none;
        }

        .progress-percent {
            display: none;
        }

        .students-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .student-item {
            display: flex;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .student-item .click-hint {
            font-size: 10px;
            opacity: 0.6;
            margin-left: 5px;
        }

        .student-item-content {
            display: flex;
            width: 100%;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .student-info {
            flex: 1;
        }

        .student-email {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .student-date {
            font-size: 12px;
            color: #666;
        }

        .student-status-container {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .approve-btn {
            padding: 8px 12px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .approve-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #20c997, #17a2b8);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .approve-btn:disabled {
            background: linear-gradient(135deg, #cccccc, #999999);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .approve-btn.approving {
            opacity: 0.7;
        }

        .approve-btn .spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
            margin-right: 5px;
        }

        .reject-btn {
            padding: 8px 12px;
            background: linear-gradient(135deg, #dc3545, #e74c3c);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .reject-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .reject-btn:disabled {
            background: linear-gradient(135deg, #cccccc, #999999);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            min-width: 80px;
        }

        /* Student Details Modal */
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
            justify-content: center;
            align-items: center;
        }

        .student-modal.show {
            display: flex;
        }

        .student-modal-content {
            background: white;
            border-radius: 30px;
            width: 95%;
            max-width: 750px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.4s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .student-modal-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            padding: 40px;
            border-radius: 30px 30px 0 0;
            text-align: center;
            position: relative;
        }

        .student-modal-close {
            position: absolute;
            top: 20px;
            right: 25px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            color: white;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .student-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .student-modal-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-bottom: 15px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .student-modal-photo:hover {
            transform: scale(1.05);
        }

        .student-modal-name {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 2em;
            color: white;
            font-weight: 800;
            margin: 0 0 10px 0;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            animation: verifiedPulse 2s ease infinite;
        }

        .verified-badge svg {
            width: 22px;
            height: 22px;
            fill: white;
        }

        .unverified-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }

        .unverified-badge svg {
            width: 22px;
            height: 22px;
            fill: white;
        }

        .semi-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #ffc107, #ffca2c);
            color: #333;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
        }

        .semi-verified-badge svg {
            width: 22px;
            height: 22px;
            fill: #333;
        }

        @keyframes verifiedPulse {
            0%, 100% { box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(40, 167, 69, 0.7); }
        }

        .student-modal-body {
            padding: 35px 40px;
        }

        .student-detail-row {
            display: flex;
            align-items: center;
            padding: 22px 25px;
            background: linear-gradient(135deg, #fff8f8, #ffffff);
            border-radius: 18px;
            margin-bottom: 18px;
            border-left: 6px solid #ff0000;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .student-detail-row:hover {
            transform: translateX(5px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.15);
        }

        .student-detail-row:last-child {
            margin-bottom: 0;
        }

        .student-detail-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #ff4444, #ff0000);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.8em;
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.25);
        }

        .student-detail-content {
            flex: 1;
        }

        .student-detail-label {
            font-size: 1em;
            color: #ff0000;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }

        .student-detail-value {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 1.5em;
            color: #222;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .com-section {
            margin-top: 25px;
            padding: 25px;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 20px;
            border: 3px solid #ffcccc;
        }

        .com-section-title {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 1.4em;
            color: #ff0000;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .com-image-container {
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            cursor: zoom-in;
        }

        .com-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 15px;
            cursor: zoom-in;
            transition: transform 0.3s ease;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .com-image:hover {
            transform: scale(1.02);
        }

        .com-zoom-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.9);
            z-index: 5000;
            display: none;
            justify-content: center;
            align-items: center;
            cursor: zoom-out;
        }

        .com-zoom-overlay.show {
            display: flex;
        }

        .com-zoom-container {
            position: relative;
            width: 90vw;
            height: 90vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .com-zoom-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.1s ease-out;
            transform-origin: center center;
        }

        .com-zoom-image.zoomed {
            cursor: grab;
            max-width: none;
            max-height: none;
        }

        .com-zoom-image.zoomed:active {
            cursor: grabbing;
        }

        .com-zoom-close {
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
            z-index: 5001;
        }

        .com-zoom-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .no-com {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 1.1em;
            background: #f9f9f9;
            border-radius: 15px;
            border: 2px dashed #ddd;
        }

        .student-modal-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
            color: #666;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f0f0f0;
            border-top: 4px solid #ff0000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .student-info {
            flex: 1;
        }

        .student-email {
            font-size: 1.3em;
            color: #333;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .student-date {
            font-size: 1.1em;
            color: #888;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            display: none;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .status-ongoing {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .status-completed {
             background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .status-available {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #6c757d;
        }

        .status-badge.completed-animation {
            animation: completedPulse 0.6s ease;
        }

        @keyframes completedPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Task info row - show room and date */
        .task-info-row {
            font-size: 1.1em;
            color: #000;
            display: flex;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .task-room-info {
            white-space: nowrap;
        }

        .task-date-info {
            white-space: nowrap;
        }

        .task-info-badge {
            background: #f0e5e5;
            color: #ff0000;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            border: 2px solid #ff0000;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .no-tasks {
            text-align: center;
            color: #999;
            font-size: 1.5em;
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            grid-column: 1 / -1;
        }

        .no-students {
            text-align: center;
            color: #bbb;
            font-size: 0.9em;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            display: none;
        }

        @keyframes fadeInSlideUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .task-card {
            animation: fadeInSlideUp 0.6s ease-out forwards;
        }

        .task-card:nth-child(1) { animation-delay: 0.1s; }
        .task-card:nth-child(2) { animation-delay: 0.2s; }
        .task-card:nth-child(3) { animation-delay: 0.3s; }
        .task-card:nth-child(4) { animation-delay: 0.4s; }
        .task-card:nth-child(5) { animation-delay: 0.5s; }

        /* ====== MOBILE RESPONSIVE STYLES ====== */
        @media (max-width: 768px) {
            html {
                font-size: 14px;
            }

            .nav-bar {
                padding: 15px 12px;
                height: auto;
                flex-wrap: wrap;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                width: 100%;
                z-index: 1000;
                box-sizing: border-box;
            }

            .nav-links {
                gap: 15px;
                order: 3;
                width: 100%;
                flex-direction: column;
                display: none;
                margin-top: 10px;
                padding-top: 10px;
                border-top: 2px solid rgba(255,255,255,0.3);
            }

            .nav-links.show {
                display: flex;
            }

            .nav-links a {
                font-size: 1.1em;
                padding: 10px 0;
            }

            .nav-right {
                gap: 8px;
                order: 2;
            }

            .icon-btn {
                width: 50px;
                height: 50px;
                padding: 12px;
                margin: 0 5px;
                border-radius: 12px;
                box-shadow: 0 3px 12px rgba(255, 0, 0, 0.2);
            }

            .icon-btn svg {
                width: 35px;
                height: 35px;
            }

            .profile-pic {
                width: 50px;
                height: 50px;
                border: 3px solid #ff0000;
                box-shadow: 0 3px 12px rgba(255, 0, 0, 0.2);
            }

            .profile-dropdown,
            .message-dropdown,
            .notification-dropdown {
                position: fixed;
                top: 70px;
                right: 10px;
                left: 10px;
                width: calc(100% - 20px);
                max-width: 100%;
                min-width: auto;
                border-radius: 15px;
                padding: 1.2rem;
                z-index: 1001;
                max-height: 70vh;
            }

            .assigned-tasks-container {
                max-width: 95%;
                margin: 0 auto;
                padding: 15px 10px;
                margin-top: 0;
                margin-left: 0;
            }

            .progress-section {
                padding: 15px 10px;
                justify-content: flex-start;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .progress-stats {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 15px;
                border-radius: 10px;
            }

            .stat-number {
                font-size: 1.8em;
            }

            .stat-label {
                font-size: 0.95em;
            }

            .tasks-list {
                grid-template-columns: 1fr;
                gap: 12px;
                max-width: 100%;
                padding: 10px;
            }

            .task-card {
                padding: 12px;
                border-radius: 12px;
            }

            .task-title {
                font-size: 1.1em;
            }

            .task-info-row {
                justify-content: space-between;
            }

            .task-description {
                font-size: 0.9em;
                max-height: 80px;
            }

            .task-meta {
                font-size: 0.85em;
                flex-wrap: wrap;
            }

            .task-student-progress {
                flex-direction: column;
                gap: 8px;
            }

            .progress-item {
                font-size: 0.9em;
                padding: 8px 12px;
            }

            .message-list,
            .notification-list {
                max-height: 50vh;
            }

            .notification-item,
            .message-card {
                padding: 10px;
            }

            .notification-avatar,
            .message-avatar {
                width: 45px;
                height: 45px;
            }
        }

        @media (max-width: 480px) {
            html {
                font-size: 12px;
            }

            .nav-bar {
                padding: 12px 8px;
            }

            .nav-right {
                gap: 5px;
            }

            .icon-btn {
                width: 45px;
                height: 45px;
                padding: 10px;
                margin: 0 3px;
            }

            .icon-btn svg {
                width: 30px;
                height: 30px;
            }

            .profile-pic {
                width: 45px;
                height: 45px;
                border: 2px solid #ff0000;
            }

            .assigned-tasks-container {
                max-width: 92%;
                margin: 0 auto;
                padding: 12px 8px;
            }

            .progress-section {
                padding: 12px 8px;
            }

            .main-content {
                padding: 12px;
            }

            .progress-stats {
                gap: 10px;
                margin-bottom: 15px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-number {
                font-size: 1.5em;
            }

            .task-card {
                padding: 12px;
            }

            .task-title {
                font-size: 1em;
            }

            .task-info-row {
                justify-content: space-between;
            }

            .task-description {
                font-size: 0.85em;
            }
        }

        @media (max-width: 375px) {
            html {
                font-size: 11px;
            }

            .nav-bar {
                padding: 10px 6px;
            }

            .icon-btn {
                width: 42px;
                height: 42px;
            }

            .assigned-tasks-container {
                max-width: 90%;
                margin: 0 auto;
                padding: 10px 6px;
            }

            .task-info-row {
                justify-content: space-between;
            }

            .progress-section {
                padding: 10px 6px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .nav-bar {
                padding: 30px 40px;
                height: auto;
            }

            .nav-links {
                gap: 30px;
            }

            .nav-links a {
                font-size: 1.5em;
            }

            .icon-btn {
                width: 80px;
                height: 80px;
                padding: 18px;
            }

            .icon-btn svg {
                width: 50px;
                height: 50px;
            }

            .profile-pic {
                width: 80px;
                height: 80px;
            }

            .assigned-tasks-container {
                max-width: 95%;
                padding: 30px;
            }

            .tasks-list {
                grid-template-columns: repeat(2, 1fr);
            }
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
                height: 75px;
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

        /* Mobile-specific responsive styles for task modal */
        @media (max-width: 600px) {
            .task-detail-content {
                padding: 20px;
                width: 95%;
                max-height: 90vh;
                border-radius: 12px;
            }

            .task-detail-title {
                font-size: 18px;
                margin-bottom: 12px;
            }

            .task-detail-student-info {
                padding: 12px !important;
                margin-bottom: 15px !important;
            }

            .task-detail-student-info > div:last-child {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
                justify-content: flex-start !important;
            }

            .task-detail-student-info > strong {
                width: 100%;
                word-break: break-word;
            }

            .task-detail-student-info button {
                width: 100% !important;
                min-height: 44px !important;
                padding: 10px 12px !important;
                font-size: 13px !important;
                flex-shrink: 0;
            }

            .task-detail-body {
                gap: 10px;
            }

            .task-detail-row {
                font-size: 13px;
                gap: 8px;
            }

            .task-detail-footer {
                justify-content: center;
                gap: 8px;
                padding-top: 15px;
                margin-top: 15px;
                flex-wrap: wrap;
            }

            .approve-btn, .reject-btn {
                min-height: 44px;
                min-width: 100%;
                padding: 10px 15px;
                font-size: 13px;
                border-radius: 8px;
            }

            .task-detail-approve-btn {
                width: 100%;
                min-height: 44px;
                padding: 12px 15px;
                font-size: 13px;
                border-radius: 8px;
            }

            .task-detail-btn {
                width: 100%;
                min-height: 44px;
                padding: 12px 15px;
                font-size: 13px;
                border-radius: 8px;
            }

            /* Mobile student list in modal */
            #taskDetailStudentInfo {
                padding: 12px !important;
                margin-bottom: 12px !important;
                border-radius: 8px !important;
            }

            .task-detail-student-info {
                padding: 12px !important;
                margin-bottom: 12px !important;
                border-radius: 8px !important;
            }

            /* Student items in modal list */
            #taskDetailStudentInfo > div[style*="display: flex"] > div {
                padding: 8px !important;
                margin-bottom: 8px;
                border-radius: 6px !important;
                flex-direction: column;
            }

            #taskDetailStudentInfo button {
                min-height: 40px;
                min-width: 40px;
                padding: 6px 10px;
                font-size: 14px;
                border-radius: 6px;
                margin: 3px 2px;
            }

            #taskDetailStudentInfo .approve-btn,
            #taskDetailStudentInfo .reject-btn {
                flex: 0 1 auto;
                width: auto;
                min-width: 40px;
            }

            .status-badge {
                font-size: 12px;
                padding: 5px 10px !important;
                min-width: 70px;
            }
        }

        @media (max-width: 480px) {
            .task-detail-content {
                padding: 15px;
                width: 96%;
                max-height: 92vh;
            }

            .task-detail-title {
                font-size: 16px;
                margin-bottom: 10px;
            }

            .task-detail-row {
                font-size: 12px;
                gap: 6px;
                flex-direction: column;
            }

            .task-detail-row label {
                font-size: 12px;
                font-weight: bold;
            }

            .task-detail-row span {
                font-size: 12px;
                margin-left: 0;
            }

            .task-detail-approve-btn,
            .task-detail-btn,
            .approve-btn,
            .reject-btn {
                width: 100%;
                min-height: 44px;
                padding: 10px 12px;
                font-size: 12px;
                margin: 4px 0;
            }

            .task-detail-footer {
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 8px;
                padding: 10px 0;
                margin-top: 10px;
            }

            /* Mobile student list optimizations */
            .task-detail-student-info {
                padding: 12px !important;
            }

            #taskDetailStudentInfo > div:first-child {
                font-size: 11px !important;
                margin-bottom: 10px !important;
            }

            /* Ensure button container stacks on mobile */
            #taskDetailStudentInfo > div:nth-child(2) {
                flex-direction: column !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                gap: 10px !important;
            }

            #taskDetailStudentInfo > div:nth-child(2) > strong {
                word-break: break-all;
                width: 100%;
            }

            #taskDetailStudentInfo > div:nth-child(2) > button {
                width: 100% !important;
                min-height: 44px !important;
                padding: 10px 12px !important;
                font-size: 12px !important;
                margin: 0 !important;
            }

            .student-email {
                font-size: 12px;
            }

            .student-date {
                font-size: 11px;
                color: #666;
            }

            #taskDetailStudentInfo button {
                min-height: 40px;
                min-width: 40px;
                padding: 6px 8px;
                font-size: 13px;
                border-radius: 5px;
                flex: 0 1 auto;
            }

            .status-badge {
                font-size: 11px;
                padding: 4px 8px !important;
                min-width: 65px;
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

    <!-- Clean Header Section -->
    <div class="page-wrapper">
        <div class="page-header-section">
            <h1 class="page-header-title">
                Assigned Tasks
            </h1>
            <p class="page-header-subtitle">Manage your posted tasks and track student progress</p>
        </div>
    </div>

    <div class="content-section" id="tasksContent">
        <!-- Tasks will be loaded here -->
    </div>
        <!-- Profile Picture Modal -->
        <div class="profile-modal" id="profileModal" onclick="if(event.target === this) closeProfileModal()">
            <button class="profile-modal-close" onclick="closeProfileModal()">×</button>
            <div class="profile-modal-content">
                <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-modal-image" id="profileModalImage">
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

        <!-- Task Attachment Modal -->
        <div class="profile-modal" id="attachmentModal" onclick="if(event.target === this) closeAttachmentModal()">
            <button class="profile-modal-close" onclick="closeAttachmentModal()">×</button>
            <div class="profile-modal-content">
                <img id="attachmentModalImage" alt="Task Attachment" class="profile-modal-image">
            </div>
        </div>

        <!-- Task Details Modal -->
        <div class="task-detail-modal" id="taskDetailModal" onclick="if(event.target === this) closeTaskDetailModal()">
            <div class="task-detail-content">
                <div class="task-detail-header-top">
                    <h2 id="taskDetailTitle" style="display: flex; align-items: center; gap: 10px;"><svg fill="#ff0000" height="24px" width="24px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><g transform="translate(1 1)"><g><g><path d="M395.8,309.462V58.733c0-5.12-3.413-8.533-8.533-8.533H293.4v-8.533c0-5.12-3.413-8.533-8.533-8.533h-26.453 C254.147,13.507,237.08-1,216.6-1c-20.48,0-37.547,14.507-41.813,34.133h-26.453c-5.12,0-8.533,3.413-8.533,8.533V50.2H45.933 c-5.12,0-8.533,3.413-8.533,8.533V485.4c0,5.12,3.413,8.533,8.533,8.533h267.849C329.987,504.705,349.393,511,370.2,511 c56.32,0,102.4-46.08,102.4-102.4C472.6,361.111,439.838,320.904,395.8,309.462z M378.733,67.267V306.2c-2.56,0-5.973,0-8.533,0 c-2.873,0-5.718,0.126-8.533,0.361V92.867c0-5.12-3.413-8.533-8.533-8.533H293.4V67.267H378.733z M333.316,313.116 c-34.241,12.834-58.734,43.499-64.298,79.747c-0.04,0.257-0.076,0.516-0.114,0.774c-0.152,1.044-0.292,2.091-0.413,3.144 c-0.088,0.756-0.168,1.515-0.239,2.276c-0.04,0.434-0.082,0.867-0.117,1.302c-0.094,1.164-0.167,2.333-0.221,3.507 c-0.013,0.284-0.022,0.569-0.033,0.854c-0.049,1.288-0.081,2.58-0.081,3.88c0,10.578,1.342,20.487,4.611,30.32 c0.08,0.255,0.165,0.506,0.247,0.76c0.243,0.756,0.492,1.509,0.753,2.258c0.092,0.266,0.187,0.531,0.282,0.796H88.6V101.4h51.2 v8.533c0,5.12,3.413,8.533,8.533,8.533h136.533c5.12,0,8.533-3.413,8.533-8.533V101.4h51.2v208.062 C340.747,310.463,336.982,311.688,333.316,313.116z M156.867,50.2h25.6c5.12,0,8.533-3.413,8.533-8.533 c0-14.507,11.093-25.6,25.6-25.6c14.507,0,25.6,11.093,25.6,25.6c0,5.12,3.413,8.533,8.533,8.533h25.6v8.533v34.133v8.533 H156.867v-8.533V58.733V50.2z M54.467,67.267H139.8v17.067H80.067c-5.12,0-8.533,3.413-8.533,8.533v358.4 c0,5.12,3.413,8.533,8.533,8.533h201.549c3.558,6.115,7.731,11.831,12.431,17.067H54.467V67.267z M370.2,493.933 c-17.987,0-34.71-5.656-48.509-15.255c-0.045-0.035-0.085-0.071-0.131-0.105c-11.323-7.968-20.373-18.413-26.655-30.214 c-0.154-0.471-0.364-0.928-0.651-1.359c-4.836-9.672-7.992-19.904-9.02-30.695c-0.086-0.954-0.166-1.91-0.22-2.872 c-0.022-0.365-0.035-0.733-0.052-1.1c-0.054-1.239-0.095-2.481-0.095-3.733c0-1.39,0.039-2.771,0.106-4.145 c0.018-0.379,0.052-0.755,0.075-1.134c0.063-1.02,0.135-2.037,0.234-3.047c0.035-0.363,0.08-0.723,0.12-1.084 c0.119-1.074,0.251-2.144,0.411-3.206c0.035-0.236,0.073-0.471,0.11-0.706c4.646-29.31,24.349-53.775,50.871-65.154 c5.69-2.348,11.725-4.097,18.047-5.151c0.717-0.143,1.381-0.365,1.998-0.645c4.357-0.693,8.818-1.062,13.362-1.062 c0.759,0,1.51,0.038,2.264,0.058c4.365,0.199,8.73,0.921,13.096,1.649c0.964,0.321,1.929,0.4,2.847,0.283 c38.26,8.402,67.126,42.657,67.126,83.344C455.533,455.533,417.133,493.933,370.2,493.933z"></path><path d="M417.133,366.787c-4.267-2.56-9.387-1.707-11.947,2.56l-44.373,66.56l-17.92-23.893 c-2.56-3.413-8.533-4.267-11.947-1.707c-3.413,2.56-4.267,8.533-1.707,11.947l23.853,31.804c0.544,1.409,1.498,2.67,2.868,3.644 c1.612,1.534,3.655,2.1,5.706,2.1c0.266,0,0.533-0.027,0.8-0.065c0.118-0.016,0.235-0.037,0.353-0.06 c0.179-0.036,0.359-0.08,0.538-0.13c0.095-0.027,0.19-0.048,0.284-0.079c1.049-0.326,2.097-0.849,3.146-1.373 c1.31-0.983,2.239-2.469,2.746-4.12l50.16-75.24C422.253,374.467,421.4,369.347,417.133,366.787z"></path></g></g></g></g></svg><span id="taskDetailTitleText">Task Title</span></h2>
                    <button class="task-detail-close" onclick="closeTaskDetailModal()">×</button>
                </div>

                <!-- Student Info - Displays Dynamically -->
                <div class="task-detail-student-info" id="taskDetailStudentInfo">
                    <div style="font-size: 12px; color: #666; font-weight: bold; text-transform: uppercase; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                        <span>Students Applied</span>
                        <span id="studentCountBadge" style="background: #ff0000; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; display: none;">0</span>
                    </div>
                    <div id="studentListContent"></div>
                </div>

                <!-- Progress Status Animation -->
                <div class="task-progress-tracker">
                    <div class="progress-steps">
                        <div class="progress-step active pending" id="progressPending">
                            <div class="step-icon"><svg fill="#8f8f8f" height="50px" width="50px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.016 512.016" xml:space="preserve" stroke="#8f8f8f"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <g> <path d="M106.683,192.008c23.573,0,42.667-19.093,42.667-42.667c0-23.573-19.093-42.667-42.667-42.667 c-23.552,0-42.667,19.093-42.667,42.667C64.016,172.915,83.131,192.008,106.683,192.008z"></path> <path d="M160.016,426.675H61.904L21.093,243.037c-1.28-5.76-6.976-9.301-12.736-8.107c-5.76,1.28-9.365,6.976-8.107,12.736 l42.411,190.848v62.827c0,5.888,4.779,10.667,10.667,10.667s10.667-4.779,10.667-10.667v-53.333h64v53.333 c0,5.888,4.779,10.667,10.667,10.667s10.667-4.779,10.667-10.667v-53.333h10.667c5.888,0,10.667-4.779,10.667-10.667 C170.661,431.453,165.904,426.675,160.016,426.675z"></path> <path d="M394.683,0.008c-64.704,0-117.333,52.629-117.333,117.333s52.629,117.333,117.333,117.333 s117.333-52.629,117.333-117.333S459.387,0.008,394.683,0.008z M437.349,128.008h-42.667c-5.888,0-10.667-4.779-10.667-10.667 v-64c0-5.888,4.779-10.667,10.667-10.667s10.667,4.779,10.667,10.667v53.333h32c5.888,0,10.667,4.779,10.667,10.667 S443.237,128.008,437.349,128.008z"></path> <path d="M224.016,341.341h-74.667v-85.333c0-23.531-19.136-42.667-42.667-42.667c-23.531,0-42.667,19.136-42.667,42.667v117.333 c0,17.643,14.357,32,32,32h96v74.667c0,17.643,14.357,32,32,32s32-14.357,32-32V373.341 C256.016,355.699,241.658,341.341,224.016,341.341z"></path> </g> </g> </g> </g></svg></div>
                            <div class="step-label">Pending</div>
                        </div>
                        <div class="progress-step in-progress" id="progressInProgress">
                            <div class="step-icon"><svg fill="#8f8f8f" height="50px" width="50px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512.002 512.002" xml:space="preserve" stroke="#8f8f8f"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M323.188,194.357c7.793,8.624,11.464,20.664,9.06,32.733h34.811v-32.733H323.188z"></path> </g> </g> <g> <g> <path d="M391.191,194.357h-9.967v32.733h9.967c2.665,0,4.826-2.161,4.826-4.826v-23.083 C396.017,196.518,393.856,194.357,391.191,194.357z"></path> </g> </g> <g> <g> <path d="M404.621,147.354h-12.8v32.734h12.8c2.665,0,4.826-2.161,4.826-4.826V152.18 C409.446,149.515,407.286,147.354,404.621,147.354z"></path> </g> </g> <g> <g> <path d="M308.517,147.354c-2.665,0-4.826,2.161-4.826,4.826v23.083c0,2.665,2.161,4.826,4.826,4.826h70.504v-32.734H308.517z"></path> </g> </g> <g> <g> <path d="M386.994,98.674h-69.979v32.734h69.979c2.665,0,4.826-2.161,4.826-4.826V103.5 C391.82,100.835,389.659,98.674,386.994,98.674z"></path> </g> </g> <g> <g> <path d="M290.891,98.674c-2.665,0-4.826,2.161-4.826,4.826v23.083c0,2.665,2.161,4.826,4.826,4.826h12.8V98.674H290.891z"></path> </g> </g> <g> <g> <path d="M308.517,62.164c-2.665,0-4.826,2.161-4.826,4.826v15.529c0,2.664,2.161,4.825,4.826,4.825h70.504v-25.18H308.517z"></path> </g> </g> <g> <g> <path d="M404.621,62.164h-15.843v25.18h15.843c2.665,0,4.826-2.161,4.826-4.826V66.989 C409.446,64.323,407.286,62.164,404.621,62.164z"></path> </g> </g> <g> <g> <circle cx="205.45" cy="41.918" r="41.918"></circle> </g> </g> <g> <g> <path d="M300.442,200.072l-68.058-18.322l-21.907-41.88l34.195,27.105l2.592-31.542c1.388-16.886-11.175-31.699-28.061-33.087 l-54.522-4.481c-16.886-1.388-31.699,11.175-33.087,28.061l-9.688,157.128l12.848,83.776l-31.325,114.255 c-3.57,13.02,4.091,26.469,17.111,30.038c13.004,3.571,26.467-4.082,30.038-17.111l32.702-119.277 c0.909-3.31,1.108-6.776,0.587-10.17L172,287.172l9.511,0.782l29.161,79.505l-24.36,115.027 c-3.216,15.186,8.368,29.516,23.938,29.516c11.303-0.001,21.455-7.887,23.89-19.386l25.817-121.906 c0.953-4.496,0.618-9.167-0.964-13.482l-24.476-66.731l3.852-46.856c-39.683-10.682-25.484-6.86-37.971-10.221 c-8.209-2.21-14.006-8.94-15.452-16.778l-10.573-57.88l26.045,49.788c2.637,5.041,7.262,8.748,12.755,10.228l76.676,20.641 c10.823,2.919,22.032-3.478,24.965-14.376C317.742,214.174,311.305,202.996,300.442,200.072z"></path> </g> </g> </g></svg></div>
                            <div class="step-label">In Progress</div>
                        </div>
                        <div class="progress-step completed" id="progressCompleted">
                            <div class="step-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M9.55879 3.6972C10.7552 2.02216 13.2447 2.02216 14.4412 3.6972L14.6317 3.96387C14.8422 4.25867 15.1958 4.41652 15.5558 4.37652L16.4048 4.28218C18.3156 4.06988 19.9301 5.68439 19.7178 7.59513L19.6235 8.44415C19.5835 8.8042 19.7413 9.15774 20.0361 9.36831L20.3028 9.55879C21.9778 10.7552 21.9778 13.2447 20.3028 14.4412L20.0361 14.6317C19.7413 14.8422 19.5835 15.1958 19.6235 15.5558L19.7178 16.4048C19.9301 18.3156 18.3156 19.9301 16.4048 19.7178L15.5558 19.6235C15.1958 19.5835 14.8422 19.7413 14.6317 20.0361L14.4412 20.3028C13.2447 21.9778 10.7553 21.9778 9.55879 20.3028L9.36831 20.0361C9.15774 19.7413 8.8042 19.5835 8.44414 19.6235L7.59513 19.7178C5.68439 19.9301 4.06988 18.3156 4.28218 16.4048L4.37652 15.5558C4.41652 15.1958 4.25867 14.8422 3.96387 14.6317L3.6972 14.4412C2.02216 13.2447 2.02216 10.7553 3.6972 9.55879L3.96387 9.36831C4.25867 9.15774 4.41652 8.8042 4.37652 8.44414L4.28218 7.59513C4.06988 5.68439 5.68439 4.06988 7.59513 4.28218L8.44415 4.37652C8.8042 4.41652 9.15774 4.25867 9.36831 3.96387L9.55879 3.6972ZM15.7071 9.29289C16.0976 9.68342 16.0976 10.3166 15.7071 10.7071L11.8882 14.526C11.3977 15.0166 10.6023 15.0166 10.1118 14.526L8.29289 12.7071C7.90237 12.3166 7.90237 11.6834 8.29289 11.2929C8.68342 10.9024 9.31658 10.9024 9.70711 11.2929L11 12.5858L14.2929 9.29289C14.6834 8.90237 15.3166 8.90237 15.7071 9.29289Z" fill="#9c9c9c"></path> </g></svg></div>
                            <div class="step-label">Completed</div>
                        </div>
                    </div>
                </div>

                <div class="task-detail-body">
                    <div class="task-detail-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3" fill="#ff0000"></circle>
                        </svg>
                        <label>Room:</label>
                        <span id="taskDetailRoom">Room</span>
                    </div>
                    <div class="task-detail-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <label>Due Date:</label>
                        <span id="taskDetailDate">Date</span>
                    </div>
                    <div class="task-detail-row">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <label>Due Time:</label>
                        <span id="taskDetailTime">Time</span>
                    </div>
                    <div class="task-detail-section">
                        <label>Description:</label>
                        <p id="taskDetailDescription">No description provided.</p>
                    </div>
                    <div class="task-detail-section" id="taskDetailAttachmentSection" style="display: none;">
                        <label>Attachment:</label>
                        <img id="taskDetailAttachmentImage" alt="Task Attachment" class="task-detail-attachment-image" style="max-width: 100%; cursor: pointer;" onclick="openAttachmentModal(this.src)">
                    </div>
                </div>
                <div class="task-detail-footer">
                    <button class="task-detail-approve-btn" id="taskDetailApproveBtn" onclick="approveTaskFromModal()" title="Approve this student's application" style="display: none;">
                        <span class="approve-icon">Approve</span>
                        <span class="approve-text">Approve</span>
                    </button>
                    <button class="task-detail-btn" onclick="closeTaskDetailModal()">Close</button>
                </div>
            </div>
        </div>

        <!-- Student Details Modal -->
        <div class="student-modal" id="studentModal" onclick="if(event.target === this) closeStudentModal()">
            <div class="student-modal-content">
                <div id="studentModalData">
                    <div class="student-modal-header">
                        <button class="student-modal-close" onclick="closeStudentModal()">×</button>
                        <img id="studentPhoto" src="" alt="Student Photo" class="student-modal-photo" onclick="openStudentPhotoModal()">
                        <h2 id="studentName" class="student-modal-name"></h2>
                        <div id="verificationBadge"></div>
                    </div>
                    <div class="student-modal-body">
                        <div class="student-detail-row" style="border-left: 4px solid #ffffff; background: rgba(33, 150, 243, 0.05);">
                            <div class="student-detail-icon"><svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: #ffffff;"><path fill-rule="evenodd" clip-rule="evenodd" d="M3 5C2.44772 5 2 5.44771 2 6V18C2 18.5523 2.44772 19 3 19H21C21.5523 19 22 18.5523 22 18V6C22 5.44772 21.5523 5 21 5H3ZM0 6C0 4.34315 1.34314 3 3 3H21C22.6569 3 24 4.34315 24 6V18C24 19.6569 22.6569 21 21 21H3C1.34315 21 0 19.6569 0 18V6ZM6 10.5C6 9.67157 6.67157 9 7.5 9C8.32843 9 9 9.67157 9 10.5C9 11.3284 8.32843 12 7.5 12C6.67157 12 6 11.3284 6 10.5ZM10.1756 12.7565C10.69 12.1472 11 11.3598 11 10.5C11 8.567 9.433 7 7.5 7C5.567 7 4 8.567 4 10.5C4 11.3598 4.31002 12.1472 4.82438 12.7565C3.68235 13.4994 3 14.7069 3 16C3 16.5523 3.44772 17 4 17C4.55228 17 5 16.5523 5 16C5 15.1145 5.80048 14 7.5 14C9.19952 14 10 15.1145 10 16C10 16.5523 10.4477 17 11 17C11.5523 17 12 16.5523 12 16C12 14.7069 11.3177 13.4994 10.1756 12.7565ZM13 8C12.4477 8 12 8.44772 12 9C12 9.55228 12.4477 10 13 10H19C19.5523 10 20 9.55228 20 9C20 8.44772 19.5523 8 19 8H13ZM14 12C13.4477 12 13 12.4477 13 13C13 13.5523 13.4477 14 14 14H18C18.5523 14 19 13.5523 19 13C19 12.4477 18.5523 12 18 12H14Z"/></svg></div>
                            <div class="student-detail-content">
                                <div class="student-detail-label">Student ID</div>
                                <div class="student-detail-value" id="studentId"></div>
                            </div>
                        </div>
                        <div class="student-detail-row" style="border-left: 4px solid #ffffff; background: rgba(76, 175, 80, 0.05);">
                            <div class="student-detail-icon"><svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: none; stroke: #ffffff; stroke-width: 2;"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><path d="M22 4l-10 7-10-7"/></svg></div>
                            <div class="student-detail-content">
                                <div class="student-detail-label">Email Address</div>
                                <div class="student-detail-value" id="studentEmail"></div>
                            </div>
                        </div>
                        <div class="student-detail-row" style="border-left: 4px solid #ffffff; background: rgba(255, 152, 0, 0.05);">
                            <div class="student-detail-icon"><svg viewBox="0 0 256 256" style="width: 24px; height: 24px; fill: #ffffff;"><g><path d="M110,30h31v-8l6-1.2v22.6l-2.2,6.7h8.6l-1.4-5.2v-25l9.8-2.3L125.5,4.4L92.4,17.5c0.1,0,17.6,4.4,17.6,4.4V30z"/><path d="M110,34.4c0,8.7,7.1,15.9,15.8,15.9c8.7,0,15.8-7.2,15.8-16c0-0.4-0.1-1.3-0.1-1.3h-31.3C110.1,33,110,34,110,34.4z"/><path d="M171.9,148h10.7l-9.8-75.4c0-12-10.6-18.3-21.5-18.3l-11.9,0L129,76.9V54h-6v22.7l-10.3-22.3L79,54.4V35h-3V25h-0.2 c-0.2-5-3.4-7-7.4-7c-4,0-7.2,2-7.4,7h0v10h-2v71.8l34.6-26.9L93.4,218l4.6,0.2v21.5c0,6,5.5,11,11.5,11c6,0,11.5-4.9,11.5-11V219 h5v20.8c0,6,5.5,11,11.5,11c6,0,11.5-4.9,11.5-11v-21.5l3.4,0.2l0.1-139.8l3.6-0.2l-0.1,75.3c0.2,4.1,3.7,7.6,8,7.6 c4.4,0,7.8-3.4,7.8-7.9C171.8,153.3,171.9,148,171.9,148z"/></g></svg></div>
                            <div class="student-detail-content">
                                <div class="student-detail-label">Year Level</div>
                                <div class="student-detail-value" id="studentYearLevel"></div>
                            </div>
                        </div>
                        <div class="student-detail-row" style="border-left: 4px solid #ffffff; background: rgba(156, 39, 176, 0.05);">
                            <div class="student-detail-icon"><svg viewBox="0 0 512 512" style="width: 24px; height: 24px; fill: #ffffff;"><path d="M505.837,180.418L279.265,76.124c-7.349-3.385-15.177-5.093-23.265-5.093c-8.088,0-15.914,1.708-23.265,5.093 L6.163,180.418C2.418,182.149,0,185.922,0,190.045s2.418,7.896,6.163,9.627l226.572,104.294c7.349,3.385,15.177,5.101,23.265,5.101 c8.088,0,15.916-1.716,23.267-5.101l178.812-82.306v82.881c-7.096,0.8-12.63,6.84-12.63,14.138c0,6.359,4.208,11.864,10.206,13.618 l-12.092,79.791h55.676l-12.09-79.791c5.996-1.754,10.204-7.259,10.204-13.618c0-7.298-5.534-13.338-12.63-14.138v-95.148 l21.116-9.721c3.744-1.731,6.163-5.504,6.163-9.627S509.582,182.149,505.837,180.418z"/><path d="M256,346.831c-11.246,0-22.143-2.391-32.386-7.104L112.793,288.71v101.638 c0,22.314,67.426,50.621,143.207,50.621c75.782,0,143.209-28.308,143.209-50.621V288.71l-110.827,51.017 C278.145,344.44,267.25,346.831,256,346.831z"/></svg></div>
                            <div class="student-detail-content">
                                <div class="student-detail-label">Course</div>
                                <div class="student-detail-value" id="studentCourse"></div>
                            </div>
                        </div>
                        <div class="com-section">
                            <div class="com-section-title">Certificate of Matriculation</div>
                            <div id="comContainer"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Photo Fullscreen Modal -->
        <div class="profile-modal" id="studentPhotoModal" onclick="if(event.target === this) closeStudentPhotoModal()">
            <button class="profile-modal-close" onclick="closeStudentPhotoModal()">×</button>
            <div class="profile-modal-content">
                <img id="studentPhotoFullscreen" alt="Student Photo" class="profile-modal-image">
            </div>
        </div>

        <!-- COM Zoom Modal -->
        <div class="com-zoom-overlay" id="comZoomOverlay" onclick="if(event.target === this) closeCOMZoom()">
            <button class="com-zoom-close" onclick="closeCOMZoom()">×</button>
            <div class="com-zoom-container" id="comZoomContainer">
                <img id="comZoomImage" class="com-zoom-image" alt="Certificate of Matriculation">
            </div>
        </div>

        <!-- Chat Modal -->
        <div class="chat-modal" id="chatModal" onclick="if(event.target === this) closeChatModal()">
            <div class="chat-container">
                <div class="chat-header">
                    <button class="chat-header-back" onclick="closeChatModal()">←</button>
                    <img src="profile-default.png" alt="Contact" class="chat-header-avatar" id="chatAvatar">
                    <div class="chat-header-info">
                        <h4 class="chat-header-name" id="chatName">Contact Name</h4>
                        <span class="chat-header-status" id="chatStatus">Student</span>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-empty">Start a conversation!</div>
                </div>
                <div class="chat-input-area">
                    <input type="text" class="chat-input" id="chatInput" placeholder="Type a message..." onkeypress="if(event.key==='Enter') sendMessage()">
                    <button class="chat-send-btn" onclick="sendMessage()" id="chatSendBtn">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" fill="white"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </body>
    <script>
        // Cache for student details to avoid refetching on slow internet
        const studentDetailsCache = {};
        // Cache for task applications (student list) to display instantly
        const taskApplicationsCache = {};

        // Pre-fetch all task applications on page load
        function preloadTaskApplications() {
            // Get all task cards
            const taskCards = document.querySelectorAll('[onclick*="viewTaskDetails"]');
            taskCards.forEach(card => {
                // Extract task ID from onclick attribute
                const onclickAttr = card.getAttribute('onclick');
                const taskIdMatch = onclickAttr.match(/viewTaskDetails\({.*?"id"\s*:\s*(\d+)/);
                if (taskIdMatch && taskIdMatch[1]) {
                    const taskId = taskIdMatch[1];
                    // Pre-fetch the student list for this task
                    fetch('get_task_applications.php?task_id=' + taskId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.students !== undefined) {
                                taskApplicationsCache[taskId] = data;
                            }
                        })
                        .catch(err => console.error('Error preloading task:', taskId, err));
                }
            });
        }

        // Run on page load
        window.addEventListener('load', preloadTaskApplications);

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

        // Mark all notifications as read
        function markAllRead() {
            // Send AJAX request to persist the read state
            fetch('assigned_tasks.php', {
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

        // Open attachment in modal
        function openAttachmentModal(imageSrc) {
            const modal = document.getElementById('attachmentModal');
            const img = document.getElementById('attachmentModalImage');
            img.src = imageSrc;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Close attachment modal
        function closeAttachmentModal() {
            const modal = document.getElementById('attachmentModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Task Details Modal Functions
        let currentTaskDetailData = null;  // Store current task data for approve action

        function updateProgressTracker(status, hasPendingApplications = false) {
            // Reset all steps
            document.getElementById('progressPending').classList.remove('active', 'completed', 'has-applications');
            document.getElementById('progressInProgress').classList.remove('active', 'completed');
            document.getElementById('progressCompleted').classList.remove('active', 'completed');
            document.querySelector('.progress-steps').classList.remove('has-progress');

            // Reset all SVG colors to gray first
            const pendingSvg = document.getElementById('progressPending').querySelector('svg');
            const inProgressSvg = document.getElementById('progressInProgress').querySelector('svg');
            const completedSvg = document.getElementById('progressCompleted').querySelector('svg');
            
            const grayColor = '#8f8f8f';
            [pendingSvg, inProgressSvg].forEach(svg => {
                if (svg) {
                    svg.setAttribute('fill', grayColor);
                    svg.setAttribute('stroke', grayColor);
                    svg.querySelectorAll('*').forEach(el => {
                        if (el.getAttribute('fill')) el.setAttribute('fill', grayColor);
                        if (el.getAttribute('stroke')) el.setAttribute('stroke', grayColor);
                    });
                }
            });

            // Update based on status
            if (status === 'pending') {
                // If there are pending applications, show has-applications class instead of active
                if (hasPendingApplications) {
                    document.getElementById('progressPending').classList.add('has-applications');
                    // Set pending SVG to red
                    if (pendingSvg) {
                        pendingSvg.setAttribute('fill', '#ff0000');
                        pendingSvg.setAttribute('stroke', '#ff0000');
                        pendingSvg.querySelectorAll('*').forEach(el => {
                            if (el.getAttribute('fill')) el.setAttribute('fill', '#ff0000');
                            if (el.getAttribute('stroke')) el.setAttribute('stroke', '#ff0000');
                        });
                    }
                } else {
                    document.getElementById('progressPending').classList.add('active');
                }
            } else if (status === 'ongoing' || status === 'in progress') {
                document.getElementById('progressPending').classList.add('completed');
                const progressInProgress = document.getElementById('progressInProgress');
                progressInProgress.classList.add('active');
                document.querySelector('.progress-steps').classList.add('has-progress');
                
                // Force SVG color change for in-progress icon to red
                if (inProgressSvg) {
                    inProgressSvg.setAttribute('fill', '#ff0000');
                    inProgressSvg.setAttribute('stroke', '#ff0000');
                    // Also update all child elements
                    inProgressSvg.querySelectorAll('*').forEach(el => {
                        if (el.getAttribute('fill')) el.setAttribute('fill', '#ff0000');
                        if (el.getAttribute('stroke')) el.setAttribute('stroke', '#ff0000');
                    });
                }
            } else if (status === 'completed') {
                document.getElementById('progressPending').classList.add('completed');
                document.getElementById('progressInProgress').classList.add('completed');
                document.getElementById('progressCompleted').classList.add('completed');
                const progressSteps = document.querySelector('.progress-steps');
                progressSteps.classList.add('has-progress');
                progressSteps.classList.add('progress-complete');
            }
        }

        function viewTaskDetails(taskData, eventObj = null) {
            // Store task data for approve action
            currentTaskDetailData = taskData;

            // Populate modal with task data
            document.getElementById('taskDetailTitleText').textContent = taskData.title;
            document.getElementById('taskDetailRoom').textContent = taskData.room;
            document.getElementById('taskDetailDate').textContent = new Date(taskData.due_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            document.getElementById('taskDetailTime').textContent = new Date('1970-01-01T' + taskData.due_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('taskDetailDescription').textContent = taskData.description || 'No description provided.';
            
            // Show modal immediately
            const modal = document.getElementById('taskDetailModal');
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            
            // Try to show cached student list first (instant display)
            if (taskApplicationsCache[taskData.id]) {
                displayStudentList(taskApplicationsCache[taskData.id], taskData.id);
            }
            
            // Get the task card element to check for pre-rendered student data
            const taskCardElement = eventObj ? eventObj.currentTarget : null;
            if (taskCardElement) {
                const studentDataAttr = taskCardElement.getAttribute('data-task-students');
                if (studentDataAttr) {
                    try {
                        const preRenderedData = JSON.parse(studentDataAttr);
                        // Cache and display the pre-rendered data immediately
                        taskApplicationsCache[taskData.id] = preRenderedData;
                        displayStudentList(preRenderedData, taskData.id);
                        // Data is already on the page, no need to fetch
                        continueViewTaskDetails(taskData);
                        return;
                    } catch (e) {
                        console.error('Error parsing student data:', e);
                    }
                }
            }
            
            // Fetch fresh data in background (fallback if pre-rendered data not available)
            fetch('get_task_applications.php?task_id=' + taskData.id)
                .then(response => response.json())
                .then(data => {
                    // Only proceed if data has success flag or has students
                    if (data.students !== undefined) {
                        // Cache the result
                        taskApplicationsCache[taskData.id] = data;
                        // Display the fresh data
                        displayStudentList(data, taskData.id);
                    }
                })
                .catch(err => {
                    console.error('Error fetching applications:', err);
                    // Only show error if we don't have cached data
                    if (!taskApplicationsCache[taskData.id]) {
                        document.getElementById('studentCountBadge').textContent = '!';
                        document.getElementById('studentCountBadge').style.background = '#d32f2f';
                        document.getElementById('studentCountBadge').style.display = 'inline-block';
                        document.getElementById('studentListContent').innerHTML = '<div style="color: #d32f2f; padding: 10px; text-align: center;">⚠️ Error loading applications</div>';
                    }
                });
            
            // Continue with the rest of task details
            continueViewTaskDetails(taskData);
        }

        // Function to display student list
        function displayStudentList(data, taskId) {
            const studentListContainer = document.getElementById('taskDetailStudentInfo');
            const studentCountBadge = document.getElementById('studentCountBadge');
            const studentListContent = document.getElementById('studentListContent');
            
            // Determine progress status based on students' actual status
            let progressStatus = 'pending';
            let hasOngoingStudents = false;
            let hasCompletedStudents = false;
            let hasPendingApplications = false;
            
            if (data && data.students && data.students.length > 0) {
                // Check student statuses
                data.students.forEach(student => {
                    if (student.status === 'ongoing') {
                        hasOngoingStudents = true;
                    }
                    if (student.is_completed) {
                        hasCompletedStudents = true;
                    }
                    if (student.status !== 'ongoing' && student.status !== 'rejected' && !student.is_completed) {
                        hasPendingApplications = true;
                    }
                });
                
                // Determine the progress status
                // Priority: completed > ongoing > pending
                if (hasCompletedStudents && !hasOngoingStudents && !hasPendingApplications) {
                    progressStatus = 'completed';
                } else if (hasOngoingStudents && !hasPendingApplications) {
                    // Only show ongoing if there are NO pending applications
                    progressStatus = 'ongoing';
                } else {
                    progressStatus = 'pending';
                }
                
                // Cache all student details for fast access
                data.students.forEach(student => {
                    studentDetailsCache[student.student_email] = {
                        full_name: student.full_name,
                        email: student.student_email,
                        student_id: student.student_id,
                        year_level: student.year_level,
                        course: student.course,
                        photo: student.photo,
                        com_picture: student.com_picture,
                        is_verified: student.is_verified    
                    };
                });

                studentCountBadge.textContent = data.students.length;
                studentCountBadge.style.background = '#4CAF50';
                
                let studentsList = '<div style="display: flex; flex-direction: column; gap: 10px;">';
                data.students.forEach(student => {
                    // Determine status
                    const hasApprovedStatus = student.status === 'ongoing' || student.status === 'rejected' || student.is_completed;
                    const isPending = !hasApprovedStatus;
                    
                    const statusClass = student.is_completed ? 'status-completed' : 
                                      (student.status === 'ongoing' ? 'status-ongoing' : 
                                      (student.status === 'rejected' ? 'status-rejected' : 'status-pending'));
                    
                    const statusText = student.is_completed ? '✅ Completed' : 
                                     (student.status === 'ongoing' ? '✓ Approved' : 
                                     (student.status === 'rejected' ? '❌ Rejected' : '⏳ Pending'));
                    
                    // Determine border color based on status
                    let borderColor = '#ff0000'; // Default red for pending
                    if (student.status === 'ongoing') {
                        borderColor = '#dc3545'; // Red for approved
                    } else if (student.status === 'rejected') {
                        borderColor = '#6c757d'; // Gray for rejected
                    } else if (student.is_completed) {
                        borderColor = '#ff0000'; // Red for completed
                    }
                    
                    studentsList += '<div style="display: flex; flex-direction: column; gap: 8px; padding: 10px; background: white; border-radius: 6px; border-left: 3px solid ' + borderColor + ';">';
                    
                    // Header row with email and View button
                    studentsList += '<div style="display: flex; align-items: center; justify-content: space-between;">';
                    studentsList += '<div style="flex: 1;"><div style="font-size: 13px; color: #333;"><strong>✉️ ' + student.student_email + '</strong></div></div>';
                    studentsList += '<button onclick="openStudentModal(\'' + student.student_email.replace(/'/g, "\\'") + '\')" style="padding: 6px 12px; background: linear-gradient(135deg, #ff0000, #cc0000); color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.3s ease; flex-shrink: 0;" title="View student details">View</button>';
                    studentsList += '</div>';
                    
                    // Info row with date and status
                    studentsList += '<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">';
                    studentsList += '<div style="font-size: 11px; color: #666;">Applied: ' + new Date(student.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    
                    // Always show status badge for non-pending students inline
                    if (hasApprovedStatus) {
                        let badgeBg = '#ffffff';
                        let badgeColor = '#ff0000';
                        if (statusClass === 'status-rejected') {
                            badgeBg = '#ffffff';
                            badgeColor = '#ff0019';
                        } else if (statusClass === 'status-completed') {
                            badgeBg = '#ffffff';
                            badgeColor = '#ff0000';
                        }
                        studentsList += ' <span class="status-badge ' + statusClass + '" style="display: inline-block; margin-left: 8px; padding: 4px 10px; font-size: 11px; font-weight: 600; background: ' + badgeBg + '; color: ' + badgeColor + '; border-radius: 3px;">' + statusText + '</span>';
                    }
                    studentsList += '</div>';
                    studentsList += '</div>';
                    
                    // Action buttons row - Approve/Reject
                    if (isPending) {
                        studentsList += '<div style="display: flex; gap: 6px; align-items: center;">';
                        studentsList += '<button class="approve-btn" onclick="event.stopPropagation(); approveTaskFromModalList(' + taskId + ', \'' + student.student_email.replace(/'/g, "\\'") + '\', this)" style="flex: 1; padding: 6px 12px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; background: #28a745; color: white;">✓ Approve</button>';
                        studentsList += '<button class="reject-btn" onclick="event.stopPropagation(); rejectTaskFromModalList(' + taskId + ', \'' + student.student_email.replace(/'/g, "\\'") + '\', this)" style="flex: 1; padding: 6px 12px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; background: #dc3545; color: white;">✕ Reject</button>';
                        studentsList += '</div>';
                    }
                    
                    studentsList += '</div>';
                });
                studentsList += '</div>';
                studentListContent.innerHTML = studentsList;
                
                // Update progress tracker based on actual student status
                // Pass hasPendingApplications flag only if there are pending applications
                updateProgressTracker(progressStatus, hasPendingApplications && !hasOngoingStudents);
            } else {
                studentCountBadge.textContent = '0';
                studentCountBadge.style.background = '#999';
                studentCountBadge.style.display = 'inline-block';
                studentListContent.innerHTML = '<div style="font-size: 13px; color: #999; padding: 15px; text-align: center;">No student applications yet</div>';
                
                // No students, set to pending without has-applications
                updateProgressTracker('pending', false);
            }
        }
        
        // Continue with viewTaskDetails after displayStudentList
        // (This is now being called after the modal is shown and student list is displayed)
        function continueViewTaskDetails(taskData) {
            // Handle attachments
            const attachmentSection = document.getElementById('taskDetailAttachmentSection');
            if (taskData.attachments) {
                try {   
                    // Check if attachment is a URL or needs processing
                    attachmentSection.style.display = 'block';
                    document.getElementById('taskDetailAttachmentImage').src = taskData.attachments;
                } catch (e) {
                    attachmentSection.style.display = 'none';
                }
            } else {
                attachmentSection.style.display = 'none';
            }

            // Progress tracker is now set correctly in displayStudentList based on student status
        }

        function closeTaskDetailModal() {
            const modal = document.getElementById('taskDetailModal');
            modal.classList.remove('show');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            currentTaskDetailData = null;
        }

        // Open task details specifically for approval (with student context)
        function viewTaskDetailsForApproval(taskData, studentEmail) {
            viewTaskDetails(taskData, studentEmail);
        }

        // Approve task from modal
        function approveTaskFromModal() {
            if (!currentTaskDetailData) return;

            const approveBtn = document.getElementById('taskDetailApproveBtn');
            const originalText = approveBtn.innerHTML;
            
            // Show loading state
            approveBtn.disabled = true;
            approveBtn.innerHTML = '<span class="approve-icon">⏳</span><span class="approve-text">Approving...</span>';

            // Call backend to approve
            fetch('approve_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + currentTaskDetailData.id + '&student_email=' + encodeURIComponent(currentTaskDetailData.student_email)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Update to In Progress (ongoing) - DO NOT auto-complete
                    updateProgressTracker('ongoing');
                    
                    // Update button
                    approveBtn.innerHTML = '<span class="approve-icon">⚙️</span><span class="approve-text">In Progress</span>';
                    approveBtn.style.background = 'linear-gradient(135deg, #2196F3, #1976D2)';
                    approveBtn.disabled = true;
                    
                    // Show success message
                    setTimeout(() => {
                        alert('✅ Student application approved!\nStatus updated to "In Progress"\nStudent has been notified.');
                        closeTaskDetailModal();
                        // Refresh task list to show updated status
                        location.reload();
                    }, 500);
                } else {
                    // Error
                    approveBtn.innerHTML = '❌ Error';
                    alert('Error: ' + result.message);
                    setTimeout(() => {
                        approveBtn.innerHTML = originalText;
                        approveBtn.disabled = false;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                approveBtn.innerHTML = '❌ Error';
                alert('Connection error. Please try again.');
                setTimeout(() => {
                    approveBtn.innerHTML = originalText;
                    approveBtn.disabled = false;
                }, 2000);
            });
        }

        // Helper function to check if there are remaining pending students in the modal
        function updatePendingIconStatus() {
            const studentListContent = document.getElementById('studentListContent');
            if (!studentListContent) return;
            
            // Check if there are any visible approve or reject buttons
            // If there are, it means there are still pending students
            const approveButtons = studentListContent.querySelectorAll('.approve-btn');
            const rejectButtons = studentListContent.querySelectorAll('.reject-btn');
            
            let hasPendingStudents = false;
            
            // Check if any approve buttons are visible
            approveButtons.forEach(btn => {
                if (btn.style.display !== 'none') {
                    hasPendingStudents = true;
                }
            });
            
            // Check if any reject buttons are visible (in case approve already checked)
            if (!hasPendingStudents) {
                rejectButtons.forEach(btn => {
                    if (btn.style.display !== 'none') {
                        hasPendingStudents = true;
                    }
                });
            }
            
            // Update the progress icon based on whether there are pending students
            const progressPending = document.getElementById('progressPending');
            if (progressPending) {
                if (hasPendingStudents) {
                    progressPending.classList.add('has-applications');
                } else {
                    progressPending.classList.remove('has-applications');
                }
            }
        }

        // Approve task from modal list
        function approveTaskFromModalList(taskId, studentEmail, button) {
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = '⏳';
            
            fetch('approve_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + taskId + '&student_email=' + encodeURIComponent(studentEmail)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Find the buttons container and the entire student row
                    const buttonsContainer = button.parentElement;
                    const studentRow = button.closest('div[style*="display: flex"]');
                    const appliedDateDiv = studentRow.querySelector('div:nth-child(1) > div:last-child');
                    
                    // Hide the approve/reject buttons
                    const approveBtn = buttonsContainer.querySelector('.approve-btn');
                    const rejectBtn = buttonsContainer.querySelector('.reject-btn');
                    
                    if (approveBtn) approveBtn.style.display = 'none';
                    if (rejectBtn) rejectBtn.style.display = 'none';
                    
                    // Add approved status badge in the applied date line
                    if (appliedDateDiv && !appliedDateDiv.querySelector('.status-badge')) {
                        const statusBadge = document.createElement('span');
                        statusBadge.className = 'status-badge status-ongoing';
                        statusBadge.style.cssText = 'display: inline-block; margin-left: 8px; padding: 4px 10px; font-size: 11px; font-weight: 600; background: #f8d7da; color: #721c24; border-radius: 3px;';
                        statusBadge.innerHTML = '✓ Approved';
                        appliedDateDiv.appendChild(statusBadge);
                    }
                    
                    // Update progress tracker to in progress
                    updateProgressTracker('ongoing');
                    
                    // Check if there are remaining pending students
                    updatePendingIconStatus();
                    
                    // Reload the page to reflect rejected students
                    setTimeout(() => {
                        location.reload();
                    }, 2500);
                } else {
                    button.innerHTML = '❌';
                    alert('Error: ' + result.message);
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = '❌';
                alert('Connection error');
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            });
        }

        // Reject task from modal list
        function rejectTaskFromModalList(taskId, studentEmail, button) {
            if (!confirm('Are you sure you want to reject this student\'s application?')) {
                return;
            }
            
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = '⏳';
            
            fetch('reject_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + taskId + '&student_email=' + encodeURIComponent(studentEmail)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Find the buttons container and the entire student row
                    const buttonsContainer = button.parentElement;
                    const studentRow = button.closest('div[style*="display: flex"]');
                    const appliedDateDiv = studentRow.querySelector('div:nth-child(1) > div:last-child');
                    
                    // Hide the approve/reject buttons
                    const approveBtn = buttonsContainer.querySelector('.approve-btn');
                    const rejectBtn = buttonsContainer.querySelector('.reject-btn');
                    
                    if (approveBtn) approveBtn.style.display = 'none';
                    if (rejectBtn) rejectBtn.style.display = 'none';
                    
                    // Add rejected status badge in the applied date line
                    if (appliedDateDiv && !appliedDateDiv.querySelector('.status-badge')) {
                        const statusBadge = document.createElement('span');
                        statusBadge.className = 'status-badge status-rejected';
                        statusBadge.style.cssText = 'display: inline-block; margin-left: 8px; padding: 4px 10px; font-size: 11px; font-weight: 600; background: #f8d7da; color: #721c24; border-radius: 3px;';
                        statusBadge.innerHTML = '❌ Rejected';
                        appliedDateDiv.appendChild(statusBadge);
                    }
                    
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                } else {
                    button.innerHTML = originalText;
                    alert('Error: ' + result.message);
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = originalText;
                alert('Connection error');
                button.disabled = false;
            });
        }

        // Close modals when pressing Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const taskModal = document.getElementById('taskDetailModal');
                if (taskModal && taskModal.classList.contains('show')) {
                    closeTaskDetailModal();
                }
                const attachmentModal = document.getElementById('attachmentModal');
                if (attachmentModal && attachmentModal.classList.contains('show')) {
                    closeAttachmentModal();
                }
            }
        });

        // View student details from task modal
        function viewStudentDetailsFromTaskModal() {
            const email = document.getElementById('taskDetailStudentEmail').textContent.trim();
            if (email && email !== 'student@example.com') {
                openStudentModal(email);
            } else {
                alert('Student email not available');
            }
        }

        // Open student details modal
        function openStudentModal(email) {
            const modal = document.getElementById('studentModal');
            const data = document.getElementById('studentModalData');
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Try to use cached student details first (no network delay)
            if (studentDetailsCache[email]) {
                displayStudentModal(studentDetailsCache[email]);
            } else {
                // If not cached, fetch from server
                fetch('get_student_details.php?email=' + encodeURIComponent(email))
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            const student = result.data;
                            // Cache it for future use
                            studentDetailsCache[email] = student;
                            displayStudentModal(student);
                        } else {
                            alert('Error: ' + result.message);
                            closeStudentModal();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        }

        // Display student details in modal
        function displayStudentModal(student) {
            const defaultAvatarSVG = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"%3E%3Cdefs%3E%3ClinearGradient id="grad" x1="0%25" y1="0%25" x2="100%25" y2="100%25"%3E%3Cstop offset="0%25" style="stop-color:%23FF3333;stop-opacity:1" /%3E%3Cstop offset="100%25" style="stop-color:%23C41C3B;stop-opacity:1" /%3E%3C/linearGradient%3E%3C/defs%3E%3Ccircle cx="100" cy="100" r="100" fill="url(%23grad)"/%3E%3Ccircle cx="100" cy="70" r="35" fill="white"/%3E%3Cpath d="M 50 180 Q 50 140 100 140 Q 150 140 150 180" fill="white"/%3E%3C/svg%3E';
            
            // Populate modal
            const photoElement = document.getElementById('studentPhoto');
            if (student.photo && student.photo !== '' && student.photo !== 'profile-default.png') {
                photoElement.src = student.photo;
                photoElement.style.backgroundColor = 'transparent';
            } else {
                photoElement.src = defaultAvatarSVG;
                photoElement.style.backgroundColor = 'transparent';
            }
            document.getElementById('studentName').textContent = student.full_name;
            document.getElementById('studentId').textContent = student.student_id || 'Not set';
            document.getElementById('studentEmail').textContent = student.email;
            document.getElementById('studentYearLevel').textContent = student.year_level || 'Not set';
            document.getElementById('studentCourse').textContent = student.course || 'Not set';
            
            // Verification badge
            const badgeContainer = document.getElementById('verificationBadge');
            const hasPhoto = student.photo && student.photo !== '' && student.photo !== 'profile-default.png';
            
            if (student.is_verified && hasPhoto) {
                // Fully verified - has COM and profile picture
                badgeContainer.innerHTML = `
                    <span class="verified-badge">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                        </svg>
                        Verified Account
                    </span>`;
            } else if (student.is_verified && !hasPhoto) {
                // Semi-verified - has COM but no profile picture
                badgeContainer.innerHTML = `
                    <span class="semi-verified-badge">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                        </svg>
                        Semi-Verified - No Profile Picture
                    </span>`;
            } else if (!student.is_verified && hasPhoto) {
                // Has photo but no COM
                badgeContainer.innerHTML = `
                    <span class="unverified-badge">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        Unverified - No COM
                    </span>`;
            } else {
                // No photo and no COM
                badgeContainer.innerHTML = `
                    <span class="unverified-badge">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        Unverified Account
                    </span>`;
            }
            
            // COM picture
            const comContainer = document.getElementById('comContainer');
            if (student.com_picture) {
                comContainer.innerHTML = `<img src="${student.com_picture}" alt="Certificate of Matriculation" class="com-image" onclick="viewCOMImage('${student.com_picture}')">`;
            } else {
                comContainer.innerHTML = '<div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #fff5f5, #ffffff); border: 2px dashed #ffcccc; border-radius: 12px;"><div class="no-com">No Certificate of Matriculation uploaded.<br>This student is NOT verified.</div><svg viewBox="-0.5 0 25 25" style="width: 50px; height: 50px; fill: none; margin: 15px auto 0; display: block;" xmlns="http://www.w3.org/2000/svg"><path d="M18.2202 21.25H5.78015C5.14217 21.2775 4.50834 21.1347 3.94373 20.8364C3.37911 20.5381 2.90402 20.095 2.56714 19.5526C2.23026 19.0101 2.04372 18.3877 2.02667 17.7494C2.00963 17.111 2.1627 16.4797 2.47015 15.92L8.69013 5.10999C9.03495 4.54078 9.52077 4.07013 10.1006 3.74347C10.6804 3.41681 11.3346 3.24518 12.0001 3.24518C12.6656 3.24518 13.3199 3.41681 13.8997 3.74347C14.4795 4.07013 14.9654 4.54078 15.3102 5.10999L21.5302 15.92C21.8376 16.4797 21.9907 17.111 21.9736 17.7494C21.9566 18.3877 21.7701 19.0101 21.4332 19.5526C21.0963 20.095 20.6211 20.5381 20.0565 20.8364C19.4919 21.1347 18.8581 21.2775 18.2202 21.25V21.25Z" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path> <path d="M10.8809 17.15C10.8809 17.0021 10.9102 16.8556 10.9671 16.7191C11.024 16.5825 11.1074 16.4586 11.2125 16.3545C11.3175 16.2504 11.4422 16.1681 11.5792 16.1124C11.7163 16.0567 11.8629 16.0287 12.0109 16.03C12.2291 16.034 12.4413 16.1021 12.621 16.226C12.8006 16.3499 12.9398 16.5241 13.0211 16.7266C13.1023 16.9292 13.122 17.1512 13.0778 17.3649C13.0335 17.5786 12.9272 17.7745 12.7722 17.9282C12.6172 18.0818 12.4203 18.1863 12.2062 18.2287C11.9921 18.2711 11.7703 18.2494 11.5685 18.1663C11.3666 18.0833 11.1938 17.9426 11.0715 17.7618C10.9492 17.5811 10.8829 17.3683 10.8809 17.15ZM11.2409 14.42L11.1009 9.20001C11.0876 9.07453 11.1008 8.94766 11.1398 8.82764C11.1787 8.70761 11.2424 8.5971 11.3268 8.5033C11.4112 8.40949 11.5144 8.33449 11.6296 8.28314C11.7449 8.2318 11.8697 8.20526 11.9959 8.20526C12.1221 8.20526 12.2469 8.2318 12.3621 8.28314C12.4774 8.33449 12.5805 8.40949 12.6649 8.5033C12.7493 8.5971 12.8131 8.70761 12.852 8.82764C12.8909 8.94766 12.9042 9.07453 12.8909 9.20001L12.7609 14.42C12.7609 14.6215 12.6808 14.8149 12.5383 14.9574C12.3957 15.0999 12.2024 15.18 12.0009 15.18C11.7993 15.18 11.606 15.0999 11.4635 14.9574C11.321 14.8149 11.2409 14.6215 11.2409 14.42Z" fill="#ff0000"></path></svg><div style="font-size: 14px; color: #ff0000; font-weight: 600; margin-top: 8px;">(NO COM)</div></div>';
            }
        }

        // Close student modal
        function closeStudentModal() {
            const modal = document.getElementById('studentModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Open student photo in fullscreen
        function openStudentPhotoModal() {
            const modal = document.getElementById('studentPhotoModal');
            const img = document.getElementById('studentPhotoFullscreen');
            img.src = document.getElementById('studentPhoto').src;
            modal.classList.add('show');
        }

        // Close student photo modal
        function closeStudentPhotoModal() {
            const modal = document.getElementById('studentPhotoModal');
            modal.classList.remove('show');
        }

        // View COM image - close student modal first then open zoom overlay
        function viewCOMImage(imageSrc) {
            closeStudentModal();
            setTimeout(() => {
                openCOMZoom(imageSrc);
            }, 100);
        }

        // COM Zoom functionality
        let currentZoom = 1;
        let isDragging = false;
        let startX, startY, translateX = 0, translateY = 0;

        function openCOMZoom(imageSrc) {
            const overlay = document.getElementById('comZoomOverlay');
            const img = document.getElementById('comZoomImage');
            
            img.src = imageSrc;
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateCOMTransform();
            
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Add mouse wheel zoom
            img.addEventListener('wheel', handleWheelZoom);
            
            // Add drag functionality
            img.addEventListener('mousedown', startDrag);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', endDrag);
        }

        function closeCOMZoom() {
            const overlay = document.getElementById('comZoomOverlay');
            const img = document.getElementById('comZoomImage');
            
            overlay.classList.remove('show');
            document.body.style.overflow = '';
            
            // Remove event listeners
            img.removeEventListener('wheel', handleWheelZoom);
            img.removeEventListener('mousedown', startDrag);
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', endDrag);
            
            // Reset zoom
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            img.classList.remove('zoomed');
        }

        function handleWheelZoom(e) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.15 : 0.15;
            zoomCOM(delta);
        }

        function zoomCOM(delta) {
            currentZoom = Math.max(0.5, Math.min(5, currentZoom + delta));
            updateCOMTransform();
        }

        function resetCOMZoom() {
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateCOMTransform();
        }

        function updateCOMTransform() {
            const img = document.getElementById('comZoomImage');
            
            img.style.transform = `scale(${currentZoom}) translate(${translateX}px, ${translateY}px)`;
            
            if (currentZoom > 1) {
                img.classList.add('zoomed');
            } else {
                img.classList.remove('zoomed');
                translateX = 0;
                translateY = 0;
            }
        }

        function startDrag(e) {
            if (currentZoom > 1) {
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                e.preventDefault();
            }
        }

        function drag(e) {
            if (isDragging && currentZoom > 1) {
                translateX = e.clientX - startX;
                translateY = e.clientY - startY;
                updateCOMTransform();
            }
        }

        function endDrag() {
            isDragging = false;
        }

        // Approve task function
        function approveTask(taskId, studentEmail, button) {
            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="spinner">⏳</div> Approving...';
            button.classList.add('approving');
            button.disabled = true;
            
            // Find the status badge and reject button
            const studentHash = md5(studentEmail);
            const statusBadge = document.getElementById('status-badge-' + taskId + '-' + studentHash);
            const rejectBtn = button.parentElement.querySelector('.reject-btn');
            
            fetch('approve_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + taskId + '&student_email=' + encodeURIComponent(studentEmail)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Success animation
                    button.innerHTML = '✓ Approved!';
                    button.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                    button.classList.remove('approving');
                    
                    // Update status badge
                    if (statusBadge) {
                        statusBadge.className = 'status-badge status-ongoing';
                        statusBadge.innerHTML = '⚙️ In Progress';
                        statusBadge.style.animation = 'completedPulse 0.6s ease';
                    }
                    
                    // Update the stat boxes
                    updateTaskStats(taskId);
                    
                    // Hide both approve and reject buttons after a short delay
                    setTimeout(() => {
                        button.style.display = 'none';
                        if (rejectBtn) rejectBtn.style.display = 'none';
                        // Refresh the page to ensure all data is accurate
                        location.reload();
                    }, 1500);
                    
                } else {
                    // Error
                    button.innerHTML = '❌ Error';
                    button.classList.remove('approving');
                    alert('Error: ' + result.message);
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = '❌ Error';
                button.classList.remove('approving');
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            });
        }

        // Reject task function
        function rejectTask(taskId, studentEmail, button) {
            // Confirm rejection
            if (!confirm('Are you sure you want to reject this student\'s application?')) {
                return;
            }

            // Show loading state
            const originalText = button.innerHTML;
            button.innerHTML = '<div class="spinner">⏳</div> Rejecting...';
            button.classList.add('approving');
            button.disabled = true;
            
            // Find the status badge and approve button
            const studentHash = md5(studentEmail);
            const statusBadge = document.getElementById('status-badge-' + taskId + '-' + studentHash);
            const approveBtn = button.parentElement.querySelector('.approve-btn');
            
            fetch('reject_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'task_id=' + taskId + '&student_email=' + encodeURIComponent(studentEmail)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Success animation
                    button.innerHTML = '✓ Rejected!';
                    button.style.background = 'linear-gradient(135deg, #dc3545, #e74c3c)';
                    button.classList.remove('approving');
                    
                    // Update status badge
                    if (statusBadge) {
                        statusBadge.className = 'status-badge status-rejected';
                        statusBadge.innerHTML = '❌ Rejected';
                        statusBadge.style.animation = 'completedPulse 0.6s ease';
                    }
                    
                    // Update the stat boxes
                    updateTaskStats(taskId);
                    
                    // Hide both approve and reject buttons after a short delay
                    setTimeout(() => {
                        button.style.display = 'none';
                        if (approveBtn) approveBtn.style.display = 'none';
                        // Refresh the page to ensure all data is accurate
                        location.reload();
                    }, 1500);
                    
                } else {
                    // Error
                    button.innerHTML = '❌ Error';
                    button.classList.remove('approving');
                    alert('Error: ' + result.message);
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 2000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                button.innerHTML = '❌ Error';
                button.classList.remove('approving');
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 2000);
            });
        }
        
        // Update task statistics after approval
        function updateTaskStats(taskId) {
            // Find the task card and update stats
            const taskCard = document.querySelector('#students-list-' + taskId);
            if (taskCard) {
                const progressSection = taskCard.closest('.progress-section');
                if (progressSection) {
                    const statBoxes = progressSection.querySelectorAll('.stat-box .stat-value');
                    if (statBoxes.length >= 3) {
                        // Recalculate stats by counting actual visible student statuses
                        // instead of just incrementing/decrementing
                        const allStudentItems = taskCard.querySelectorAll('.student-item');
                        let pending = 0;
                        let inProgress = 0;
                        let completed = 0;
                        
                        allStudentItems.forEach(item => {
                            const statusBadge = item.querySelector('.status-badge');
                            if (statusBadge) {
                                if (statusBadge.classList.contains('status-pending') || 
                                    (!statusBadge.classList.contains('status-ongoing') && 
                                     !statusBadge.classList.contains('status-rejected') && 
                                     !statusBadge.classList.contains('status-completed'))) {
                                    pending++;
                                } else if (statusBadge.classList.contains('status-ongoing')) {
                                    inProgress++;
                                } else if (statusBadge.classList.contains('status-completed')) {
                                    completed++;
                                }
                            }
                        });
                        
                        // Update stat boxes with accurate counts
                        statBoxes[0].textContent = pending + inProgress + completed; // Total Applied
                        statBoxes[0].style.animation = 'completedPulse 0.6s ease';
                        statBoxes[1].textContent = inProgress; // In Progress
                        statBoxes[1].style.animation = 'completedPulse 0.6s ease';
                        statBoxes[2].textContent = completed; // Completed
                        statBoxes[2].style.animation = 'completedPulse 0.6s ease';
                        
                        // Update progress bar - In Progress = 50%, Completed = 100%
                        const total = pending + inProgress + completed;
                        const percentage = total > 0 ? ((completed * 100) + (inProgress * 50)) / total : 0;
                        
                        const progressBar = progressSection.querySelector('.progress-bar');
                        const progressIcon = progressSection.querySelector('.progress-icon');
                        const progressPercent = progressSection.querySelector('.progress-percent');
                        
                        if (progressBar) {
                            progressBar.style.width = percentage + '%';
                            progressBar.style.transition = 'width 0.8s ease';
                            
                            if (percentage >= 100) {
                                progressBar.classList.add('complete');
                                if (progressIcon) progressIcon.classList.add('complete');
                            }
                        }
                        
                        if (progressPercent) {
                            progressPercent.textContent = Math.round(percentage * 10) / 10 + '% Complete';
                            progressPercent.style.animation = 'completedPulse 0.6s ease';
                        }
                    }
                }
            }
        }
        
        // Simple MD5 hash function for generating student item IDs
        function md5(string) {
            function rotateLeft(value, shift) {
                return (value << shift) | (value >>> (32 - shift));
            }
            function addUnsigned(x, y) {
                var lsw = (x & 0xFFFF) + (y & 0xFFFF);
                var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
                return (msw << 16) | (lsw & 0xFFFF);
            }
            function f(x, y, z) { return (x & y) | ((~x) & z); }
            function g(x, y, z) { return (x & z) | (y & (~z)); }
            function h(x, y, z) { return x ^ y ^ z; }
            function i(x, y, z) { return y ^ (x | (~z)); }
            function ff(a, b, c, d, x, s, t) {
                a = addUnsigned(a, addUnsigned(addUnsigned(f(b, c, d), x), t));
                return addUnsigned(rotateLeft(a, s), b);
            }
            function gg(a, b, c, d, x, s, t) {
                a = addUnsigned(a, addUnsigned(addUnsigned(g(b, c, d), x), t));
                return addUnsigned(rotateLeft(a, s), b);
            }
            function hh(a, b, c, d, x, s, t) {
                a = addUnsigned(a, addUnsigned(addUnsigned(h(b, c, d), x), t));
                return addUnsigned(rotateLeft(a, s), b);
            }
            function ii(a, b, c, d, x, s, t) {
                a = addUnsigned(a, addUnsigned(addUnsigned(i(b, c, d), x), t));
                return addUnsigned(rotateLeft(a, s), b);
            }
            function convertToWordArray(string) {
                var len = string.length;
                var words = [];
                for (var i = 0; i < len; i++) {
                    words[i >> 2] |= (string.charCodeAt(i) & 0xFF) << ((i % 4) * 8);
                }
                words[len >> 2] |= 0x80 << ((len % 4) * 8);
                words[(((len + 8) >> 6) << 4) + 14] = len * 8;
                return words;
            }
            function wordToHex(value) {
                var hex = '', temp;
                for (var i = 0; i < 4; i++) {
                    temp = (value >> (i * 8)) & 0xFF;
                    hex += ('0' + temp.toString(16)).slice(-2);
                }
                return hex;
            }
            var x = convertToWordArray(string);
            var a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;
            for (var k = 0; k < x.length; k += 16) {
                var AA = a, BB = b, CC = c, DD = d;
                a = ff(a, b, c, d, x[k + 0], 7, 0xD76AA478);
                d = ff(d, a, b, c, x[k + 1], 12, 0xE8C7B756);
                c = ff(c, d, a, b, x[k + 2], 17, 0x242070DB);
                b = ff(b, c, d, a, x[k + 3], 22, 0xC1BDCEEE);
                a = ff(a, b, c, d, x[k + 4], 7, 0xF57C0FAF);
                d = ff(d, a, b, c, x[k + 5], 12, 0x4787C62A);
                c = ff(c, d, a, b, x[k + 6], 17, 0xA8304613);
                b = ff(b, c, d, a, x[k + 7], 22, 0xFD469501);
                a = ff(a, b, c, d, x[k + 8], 7, 0x698098D8);
                d = ff(d, a, b, c, x[k + 9], 12, 0x8B44F7AF);
                c = ff(c, d, a, b, x[k + 10], 17, 0xFFFF5BB1);
                b = ff(b, c, d, a, x[k + 11], 22, 0x895CD7BE);
                a = ff(a, b, c, d, x[k + 12], 7, 0x6B901122);
                d = ff(d, a, b, c, x[k + 13], 12, 0xFD987193);
                c = ff(c, d, a, b, x[k + 14], 17, 0xA679438E);
                b = ff(b, c, d, a, x[k + 15], 22, 0x49B40821);
                a = gg(a, b, c, d, x[k + 1], 5, 0xF61E2562);
                d = gg(d, a, b, c, x[k + 6], 9, 0xC040B340);
                c = gg(c, d, a, b, x[k + 11], 14, 0x265E5A51);
                b = gg(b, c, d, a, x[k + 0], 20, 0xE9B6C7AA);
                a = gg(a, b, c, d, x[k + 5], 5, 0xD62F105D);
                d = gg(d, a, b, c, x[k + 10], 9, 0x02441453);
                c = gg(c, d, a, b, x[k + 15], 14, 0xD8A1E681);
                b = gg(b, c, d, a, x[k + 4], 20, 0xE7D3FBC8);
                a = gg(a, b, c, d, x[k + 9], 5, 0x21E1CDE6);
                d = gg(d, a, b, c, x[k + 14], 9, 0xC33707D6);
                c = gg(c, d, a, b, x[k + 3], 14, 0xF4D50D87);
                b = gg(b, c, d, a, x[k + 8], 20, 0x455A14ED);
                a = gg(a, b, c, d, x[k + 13], 5, 0xA9E3E905);
                d = gg(d, a, b, c, x[k + 2], 9, 0xFCEFA3F8);
                c = gg(c, d, a, b, x[k + 7], 14, 0x676F02D9);
                b = gg(b, c, d, a, x[k + 12], 20, 0x8D2A4C8A);
                a = hh(a, b, c, d, x[k + 5], 4, 0xFFFA3942);
                d = hh(d, a, b, c, x[k + 8], 11, 0x8771F681);
                c = hh(c, d, a, b, x[k + 11], 16, 0x6D9D6122);
                b = hh(b, c, d, a, x[k + 14], 23, 0xFDE5380C);
                a = hh(a, b, c, d, x[k + 1], 4, 0xA4BEEA44);
                d = hh(d, a, b, c, x[k + 4], 11, 0x4BDECFA9);
                c = hh(c, d, a, b, x[k + 7], 16, 0xF6BB4B60);
                b = hh(b, c, d, a, x[k + 10], 23, 0xBEBFBC70);
                a = hh(a, b, c, d, x[k + 13], 4, 0x289B7EC6);
                d = hh(d, a, b, c, x[k + 0], 11, 0xEAA127FA);
                c = hh(c, d, a, b, x[k + 3], 16, 0xD4EF3085);
                b = hh(b, c, d, a, x[k + 6], 23, 0x04881D05);
                a = hh(a, b, c, d, x[k + 9], 4, 0xD9D4D039);
                d = hh(d, a, b, c, x[k + 12], 11, 0xE6DB99E5);
                c = hh(c, d, a, b, x[k + 15], 16, 0x1FA27CF8);
                b = hh(b, c, d, a, x[k + 2], 23, 0xC4AC5665);
                a = ii(a, b, c, d, x[k + 0], 6, 0xF4292244);
                d = ii(d, a, b, c, x[k + 7], 10, 0x432AFF97);
                c = ii(c, d, a, b, x[k + 14], 15, 0xAB9423A7);
                b = ii(b, c, d, a, x[k + 5], 21, 0xFC93A039);
                a = ii(a, b, c, d, x[k + 12], 6, 0x655B59C3);
                d = ii(d, a, b, c, x[k + 3], 10, 0x8F0CCC92);
                c = ii(c, d, a, b, x[k + 10], 15, 0xFFEFF47D);
                b = ii(b, c, d, a, x[k + 1], 21, 0x85845DD1);
                a = ii(a, b, c, d, x[k + 8], 6, 0x6FA87E4F);
                d = ii(d, a, b, c, x[k + 15], 10, 0xFE2CE6E0);
                c = ii(c, d, a, b, x[k + 6], 15, 0xA3014314);
                b = ii(b, c, d, a, x[k + 13], 21, 0x4E0811A1);
                a = ii(a, b, c, d, x[k + 4], 6, 0xF7537E82);
                d = ii(d, a, b, c, x[k + 11], 10, 0xBD3AF235);
                c = ii(c, d, a, b, x[k + 2], 15, 0x2AD7D2BB);
                b = ii(b, c, d, a, x[k + 9], 21, 0xEB86D391);
                a = addUnsigned(a, AA);
                b = addUnsigned(b, BB);
                c = addUnsigned(c, CC);
                d = addUnsigned(d, DD);
            }
            return wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);
        }

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

        // Allow ESC key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProfileModal();
                closeAttachmentModal();
                closeStudentModal();
                closeStudentPhotoModal();
                closeCOMZoom();
                closeChatModal();
                const messageDropdown = document.getElementById('messageDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                if (messageDropdown) messageDropdown.classList.remove('show');
                if (notificationDropdown) notificationDropdown.classList.remove('show');
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
            document.getElementById('chatAvatar').src = photo || 'profile-default.png';
            document.getElementById('chatName').textContent = name;
            document.getElementById('chatStatus').textContent = role === 'teacher' ? 'Teacher' : 'Student';
            
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
    <div class="main-content">
        <div class="assigned-tasks-container">
            <div class="tasks-header">
                <div class="tasks-title"></path></g></svg>Task Progress Tracking</div>
            </div>
            <div class="tasks-list">
                <?php if (count($tasks) > 0): ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php 
                        // Format student data for immediate display
                        $task_students = [];
                        if (isset($task_progress[$task['id']]['students'])) {
                            foreach ($task_progress[$task['id']]['students'] as $student) {
                                $full_name = trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']);
                                $task_students[] = [
                                    'student_email' => $student['student_email'],
                                    'full_name' => $full_name,
                                    'student_id' => $student['student_id'] ?? 'Not set',
                                    'year_level' => $student['year_level'] ?? 'Not set',
                                    'course' => $student['course'] ?? 'Not set',
                                    'photo' => $student['photo'] ?? 'profile-default.png',
                                    'com_picture' => $student['attachment'] ?? null,
                                    'is_verified' => !empty($student['attachment']),
                                    'status' => $student['status'],
                                    'is_completed' => $student['is_completed'],
                                    'created_at' => $student['created_at']
                                ];
                            }
                        }
                        $student_list_json = json_encode(['students' => $task_students]);
                        ?>
                        <div class="task-card" 
                             data-task-students="<?php echo htmlspecialchars($student_list_json); ?>"
                             onclick="viewTaskDetails({
                            id: <?php echo $task['id']; ?>,
                            title: '<?php echo addslashes($task['title']); ?>',
                            room: '<?php echo addslashes($task['room']); ?>',
                            due_date: '<?php echo addslashes($task['due_date']); ?>',
                            due_time: '<?php echo addslashes($task['due_time']); ?>',
                            description: '<?php echo addslashes($task['description']); ?>',
                            attachments: '<?php echo addslashes($task['attachments'] ?? ''); ?>',
                            created_at: '<?php echo addslashes($task['created_at']); ?>'
                        }, event)">
                            <!-- Task Header - Image/Attachment -->
                            <div class="task-card-header" <?php if (!empty($task['attachments'])): ?>style="background-image: url('<?php echo htmlspecialchars($task['attachments']); ?>');"<?php endif; ?>>
                                <?php if (empty($task['attachments'])): ?>
                                    <svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#cccccc" style="width: 80px; height: 80px; opacity: 0.6;">
                                        <g id="page_document_emoji_empty">
                                            <g id="XMLID_1521_">
                                                <path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z"></path>
                                                <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z"></path>
                                            </g>
                                            <g id="XMLID_1337_">
                                                <g id="XMLID_4010_">
                                                    <polyline fill="none" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#cccccc" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline>
                                                    <polyline fill="none" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#cccccc" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline>
                                                    <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" stroke="#cccccc" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path>
                                                    <g>
                                                        <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" stroke="#cccccc" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path>
                                                        <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" stroke="#cccccc" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path>
                                                    </g>
                                                </g>
                                            </g>
                                        </g>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Task Body - Title & Info -->
                            <div class="task-card-body">
                                <div class="task-title-badge" style="background: #f0e5e5; color: #ff0000; padding: 8px 12px; border-radius: 6px; border: 2px solid #ff0000; display: inline-flex; align-items: center; gap: 8px; align-self: center;"><svg fill="#ff0000" height="20px" width="20px" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><g transform="translate(1 1)"><g><g><path d="M395.8,309.462V58.733c0-5.12-3.413-8.533-8.533-8.533H293.4v-8.533c0-5.12-3.413-8.533-8.533-8.533h-26.453 C254.147,13.507,237.08-1,216.6-1c-20.48,0-37.547,14.507-41.813,34.133h-26.453c-5.12,0-8.533,3.413-8.533,8.533V50.2H45.933 c-5.12,0-8.533,3.413-8.533,8.533V485.4c0,5.12,3.413,8.533,8.533,8.533h267.849C329.987,504.705,349.393,511,370.2,511 c56.32,0,102.4-46.08,102.4-102.4C472.6,361.111,439.838,320.904,395.8,309.462z M378.733,67.267V306.2c-2.56,0-5.973,0-8.533,0 c-2.873,0-5.718,0.126-8.533,0.361V92.867c0-5.12-3.413-8.533-8.533-8.533H293.4V67.267H378.733z M333.316,313.116 c-34.241,12.834-58.734,43.499-64.298,79.747c-0.04,0.257-0.076,0.516-0.114,0.774c-0.152,1.044-0.292,2.091-0.413,3.144 c-0.088,0.756-0.168,1.515-0.239,2.276c-0.04,0.434-0.082,0.867-0.117,1.302c-0.094,1.164-0.167,2.333-0.221,3.507 c-0.013,0.284-0.022,0.569-0.033,0.854c-0.049,1.288-0.081,2.58-0.081,3.88c0,10.578,1.342,20.487,4.611,30.32 c0.08,0.255,0.165,0.506,0.247,0.76c0.243,0.756,0.492,1.509,0.753,2.258c0.092,0.266,0.187,0.531,0.282,0.796H88.6V101.4h51.2 v8.533c0,5.12,3.413,8.533,8.533,8.533h136.533c5.12,0,8.533-3.413,8.533-8.533V101.4h51.2v208.062 C340.747,310.463,336.982,311.688,333.316,313.116z M156.867,50.2h25.6c5.12,0,8.533-3.413,8.533-8.533 c0-14.507,11.093-25.6,25.6-25.6c14.507,0,25.6,11.093,25.6,25.6c0,5.12,3.413,8.533,8.533,8.533h25.6v8.533v34.133v8.533 H156.867v-8.533V58.733V50.2z M54.467,67.267H139.8v17.067H80.067c-5.12,0-8.533,3.413-8.533,8.533v358.4 c0,5.12,3.413,8.533,8.533,8.533h201.549c3.558,6.115,7.731,11.831,12.431,17.067H54.467V67.267z M370.2,493.933 c-17.987,0-34.71-5.656-48.509-15.255c-0.045-0.035-0.085-0.071-0.131-0.105c-11.323-7.968-20.373-18.413-26.655-30.214 c-0.154-0.471-0.364-0.928-0.651-1.359c-4.836-9.672-7.992-19.904-9.02-30.695c-0.086-0.954-0.166-1.91-0.22-2.872 c-0.022-0.365-0.035-0.733-0.052-1.1c-0.054-1.239-0.095-2.481-0.095-3.733c0-1.39,0.039-2.771,0.106-4.145 c0.018-0.379,0.052-0.755,0.075-1.134c0.063-1.02,0.135-2.037,0.234-3.047c0.035-0.363,0.08-0.723,0.12-1.084 c0.119-1.074,0.251-2.144,0.411-3.206c0.035-0.236,0.073-0.471,0.11-0.706c4.646-29.31,24.349-53.775,50.871-65.154 c5.69-2.348,11.725-4.097,18.047-5.151c0.717-0.143,1.381-0.365,1.998-0.645c4.357-0.693,8.818-1.062,13.362-1.062 c0.759,0,1.51,0.038,2.264,0.058c4.365,0.199,8.73,0.921,13.096,1.649c0.964,0.321,1.929,0.4,2.847,0.283 c38.26,8.402,67.126,42.657,67.126,83.344C455.533,455.533,417.133,493.933,370.2,493.933z"></path><path d="M417.133,366.787c-4.267-2.56-9.387-1.707-11.947,2.56l-44.373,66.56l-17.92-23.893 c-2.56-3.413-8.533-4.267-11.947-1.707c-3.413,2.56-4.267,8.533-1.707,11.947l23.853,31.804c0.544,1.409,1.498,2.67,2.868,3.644 c1.612,1.534,3.655,2.1,5.706,2.1c0.266,0,0.533-0.027,0.8-0.065c0.118-0.016,0.235-0.037,0.353-0.06 c0.179-0.036,0.359-0.08,0.538-0.13c0.095-0.027,0.19-0.048,0.284-0.079c1.049-0.326,2.097-0.849,3.146-1.373 c1.31-0.983,2.239-2.469,2.746-4.12l50.16-75.24C422.253,374.467,421.4,369.347,417.133,366.787z"></path></g></g></g></g></svg><span><?php echo htmlspecialchars($task['title']); ?></span></div>
                                <div class="task-info-row" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; width: 100%;">
                                    <div class="task-info-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3" fill="#ff0000"></circle>
                                        </svg>
                                        <?php echo htmlspecialchars($task['room']); ?>
                                    </div>
                                    <div class="task-info-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <?php echo htmlspecialchars(date('M d', strtotime($task['due_date']))); ?>
                                    </div>
                                    <div class="task-info-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                        <?php echo date('g:i A', strtotime($task['due_time'])); ?>
                                    </div>
                                    <div class="task-info-badge">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 18px; height: 18px;">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        <?php echo $task_progress[$task['id']]['total'] ?? 0; ?> Applied
                                    </div>
                                </div>
                                <!-- Student Status Summary -->
                                <div class="task-info-row" style="margin-top: 8px; gap: 8px;">
                                    <?php if (($task_progress[$task['id']]['ongoing'] ?? 0) > 0): ?>
                                        <div style="background: #f0e5e5; color: #ff0000; padding: 6px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; border: 2px solid #ff0000; display: flex; align-items: center; gap: 6px;">
                                            <svg viewBox="0 0 24 24" fill="#ff0000" stroke="none" style="width: 16px; height: 16px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <?php echo $task_progress[$task['id']]['ongoing']; ?> Approved
                                        </div>
                                    <?php endif; ?>
                                    <?php if (($task_progress[$task['id']]['pending'] ?? 0) > 0): ?>
                                        <div style="background: #f0e5e5; color: #ff0000; padding: 6px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; border: 2px solid #ff0000; display: flex; align-items: center; gap: 6px;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 16px; height: 16px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                            <?php echo $task_progress[$task['id']]['pending']; ?> Pending
                                        </div>
                                    <?php endif; ?>
                                    <?php if (($task_progress[$task['id']]['completed'] ?? 0) > 0): ?>
                                        <div style="background: #f0e5e5; color: #ff0000; padding: 6px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; border: 2px solid #ff0000; display: flex; align-items: center; gap: 6px;">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" style="width: 16px; height: 16px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                            <?php echo $task_progress[$task['id']]['completed']; ?> Completed
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: center; margin-top: 12px; font-size: 12px; color: #bc2222; opacity: 0.5;">Tap to View</div>
                            </div>

                            <!-- Progress Tracking Section - Hidden -->
                            <div class="progress-section">
                                <div class="progress-header">📈 Student Status</div>
                                
                                <!-- Progress Stats -->
                                <div class="progress-stats">
                                    <div class="stat-box">
                                        <div class="stat-label">Total Applied</div>
                                        <div class="stat-value"><?php echo $task_progress[$task['id']]['total'] ?? 0; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label">In Progress</div>
                                        <div class="stat-value"><?php echo $task_progress[$task['id']]['ongoing'] ?? 0; ?></div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-label">Completed</div>
                                        <div class="stat-value"><?php echo $task_progress[$task['id']]['completed'] ?? 0; ?></div>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <?php 
                                $total = $task_progress[$task['id']]['total'] ?? 0;
                                $completed = $task_progress[$task['id']]['completed'] ?? 0;
                                $ongoing = $task_progress[$task['id']]['ongoing'] ?? 0;
                                $approved_count = $completed + $ongoing;
                                
                                // Progress calculation: 0% if none approved, 50% if 1+ approved, 100% if all completed
                                if ($completed >= $total && $total > 0) {
                                    $percentage = 100;
                                } elseif ($approved_count > 0) {
                                    $percentage = 50;
                                } else {
                                    $percentage = 0;
                                }
                                ?>
                                <div class="progress-bar-container">
                                    <div class="progress-bar <?php echo $percentage >= 100 ? 'complete' : ''; ?>" style="width: <?php echo $percentage; ?>%">
                                        <div class="progress-icon <?php echo $percentage >= 100 ? 'complete' : ''; ?>">
                                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="17" cy="4" r="2.5"/>
                                                <path d="M15.5 7.5L12 9L8 8L6 12L8 13L10.5 10L12.5 11L10 17L7 23H10L12 18L15 21H18L14 13L16.5 10L19 12H22V9H18L15.5 7.5Z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress-percent">
                                    <?php 
                                    if ($percentage >= 100) {
                                        echo round($percentage, 1) . '% Complete';
                                    } elseif ($ongoing > 0) {
                                        echo round($percentage, 1) . '% In Progress';
                                    } else {
                                        echo '0% Incomplete';
                                    }
                                    ?>
                                </div>

                                <!-- Students List -->
                                <?php if (!empty($task_progress[$task['id']]['students'])): ?>
                                    <div class="students-list" id="students-list-<?php echo $task['id']; ?>">
                                        <?php foreach ($task_progress[$task['id']]['students'] as $student): ?>
                                            <div class="student-item" id="student-item-<?php echo $task['id']; ?>-<?php echo md5($student['student_email']); ?>">
                                                <div class="student-item-content">
                                                    <div class="student-info" onclick="openStudentModal('<?php echo htmlspecialchars($student['student_email']); ?>')" title="Click to view student details" style="cursor: pointer; flex: 1;">
                                                        <div class="student-email">✉️ <?php echo htmlspecialchars($student['student_email']); ?> <span class="click-hint">👆 Click for details</span></div>
                                                        <div class="student-date">Applied: <?php echo date('M d, Y H:i', strtotime($student['created_at'])); ?></div>
                                                    </div>
                                                    <div class="student-status-container">
                                                        <?php if (!$student['is_completed'] && (empty($student['status']) || $student['status'] === 'pending')): ?>
                                                            <button class="approve-btn" onclick="event.stopPropagation(); approveTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($student['student_email']); ?>', this)" title="Approve this student's application">
                                                                ✓ Approve
                                                            </button>
                                                            <button class="reject-btn" onclick="event.stopPropagation(); rejectTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($student['student_email']); ?>', this)" title="Reject this student's application">
                                                                ✕ Reject
                                                            </button>
                                                        <?php endif; ?>
                                                        <span class="status-badge <?php 
                                                            if ($student['is_completed']) {
                                                                echo 'status-completed';
                                                            } elseif ($student['status'] === 'ongoing') {
                                                                echo 'status-ongoing';
                                                            } elseif ($student['status'] === 'rejected') {
                                                                echo 'status-rejected';
                                                            } else {
                                                                echo 'status-pending';
                                                            }
                                                        ?>" id="status-badge-<?php echo $task['id']; ?>-<?php echo md5($student['student_email']); ?>">
                                                            <?php 
                                                            if ($student['is_completed']) {
                                                                echo '✅ Completed';
                                                            } elseif ($student['status'] === 'ongoing') {
                                                                echo '⚙️ In Progress';
                                                            } elseif ($student['status'] === 'rejected') {
                                                                echo '❌ Rejected';
                                                            } else {
                                                                echo '⏳ Pending';
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="students-list">
                                        <div class="student-item">
                                            <div class="student-info">
                                                <div class="student-email">Task Available for Acceptance</div>
                                                <div class="student-date">Posted: <?php echo date('M d, Y H:i', strtotime($task['created_at'])); ?></div>
                                            </div>
                                            <span class="status-badge status-available">
                                                Available
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-tasks"><svg width="70px" height="70px" viewBox="0 0 32 32" enable-background="new 0 0 32 32" id="_x3C_Layer_x3E_" version="1.1" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="page_x2C__document_x2C__emoji_x2C__No_results_x2C__empty_page"> <g id="XMLID_1521_"> <path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z" fill="#ff0000" id="XMLID_1887_"></path> <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z" fill="#ff0000" id="XMLID_1885_"></path> </g> <g id="XMLID_1337_"> <g id="XMLID_4010_"> <polyline fill="none" id="XMLID_4073_" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" id="XMLID_4072_" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" id="XMLID_4071_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <g id="XMLID_4068_"> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" id="XMLID_4070_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" id="XMLID_4069_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> </g> </g> <g id="XMLID_2974_"> <polyline fill="none" id="XMLID_4009_" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" id="XMLID_4008_" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" id="XMLID_4007_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <g id="XMLID_4004_"> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" id="XMLID_4006_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" id="XMLID_4005_" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> </g> </g> </g> </g> </g></svg>
                    <span>No tasks posted yet.</span></div>
                <?php endif; ?>
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
        <a href="assigned_tasks.php" class="bottom-nav-item active" title="Activity">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                </svg>
            </span>
            <span class="bottom-nav-label">Activity</span>
        </a>
        <a href="teacher_record.php" class="bottom-nav-item" title="History">
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

