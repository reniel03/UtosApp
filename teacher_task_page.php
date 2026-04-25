<?php
session_start();

// Handle AJAX request to mark all notifications as read FIRST (before db connection)
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

// Check if the user is logged in as a teacher
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$teacher_email = $_SESSION['email'];

// Get the last read timestamp
$last_read = isset($_SESSION['notif_last_read_' . $teacher_email]) ? $_SESSION['notif_last_read_' . $teacher_email] : null;

// Fetch students who applied/accepted tasks posted by this teacher
$notifications = [];
$query = "SELECT st.*, s.first_name, s.middle_name, s.last_name, s.photo as student_photo, 
          t.title as task_title
          FROM student_todos st
          INNER JOIN tasks t ON st.task_id = t.id
          LEFT JOIN students s ON st.student_email = s.email
          WHERE t.teacher_email = ?
          ORDER BY st.created_at DESC
          LIMIT 20";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('s', $teacher_email);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Teacher Task Page</title>
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

        /* Main Content Wrapper */
        .page-wrapper {
            width: 100%;
            max-width: 100%;
            padding: 20px;
            margin-top: 20px;
        }

        /* Page Header */
        .page-header-section {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .page-header-title {
            font-size: 2.5em;
            color: #ff0000;
            font-weight: 800;
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-header-title-emoji {
            font-size: 1.2em;
        }

        .page-header-subtitle {
            font-size: 1.2em;
            color: #666;
            font-weight: 500;
            margin: 10px 0;
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

        /* Empty state */
        .empty-notification-state {
            text-align: center;
            padding: 80px 40px;
        }

        .empty-notification-icon {
            font-size: 5em;
            margin-bottom: 20px;
        }

        .empty-notification-title {
            font-size: 2em;
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .empty-notification-text {
            font-size: 1.1em;
            color: #888;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 15px;
                margin-top: 10px;
            }

            .page-header-title {
                font-size: 1.8em;
                gap: 10px;
            }

            .page-header-title-emoji {
                font-size: 1em;
            }

            .page-header-subtitle {
                font-size: 1em;
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

        @media (max-width: 480px) {
            .page-header-title {
                font-size: 1.5em;
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
        .nav-links {
            display: flex;
            gap: 65px;
            opacity: 1;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            visibility: visible;
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

        .message-btn {
            background: #ff0000;
        }

        .icon-btn svg {
            width: 70px;
            height: 70px;
            transition: all 0.3s ease;
            transform: translateY(-2px); /* Adjust for the tail */
        }
        
        .message-btn svg {
            transform: translateY(-4px) scale(1.1); /* Slight adjustments for message icon */
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

        .icon-wrapper {
            margin: 0 8px;
        }

        .icon-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        }

        .profile-pic:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.35);
        }

        .profile-pic:active {
            transform: translateY(0);
            box-shadow: 0 3px 15px rgba(255, 0, 0, 0.2);
        }

        .profile-pic-wrapper {
            position: relative;
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
            z-index: 1000;
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

        /* Notification dropdown styles */
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

        .notif-detail-body {
            padding: 30px;
        }

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

        .notif-detail-info p {
            margin: 0;
            color: #666;
            font-size: 1em;
        }

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

        @keyframes slideOutRight {
            0% { opacity: 1; transform: translateX(0); }
            100% { opacity: 0; transform: translateX(100%); }
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
            border: 5px solid white;
            box-shadow: 0 0 25px rgba(255, 0, 0, 0.3);
            object-fit: cover;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-info-pic:hover {
            transform: scale(1.05);
            box-shadow: 0 0 35px rgba(255, 255, 255, 0.5);
        }

        .image-info {
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.9);
            color: #ff0000;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
            border: 2px solid #ff0000;
            pointer-events: none;
        }

        .name-section {
            text-align: center;
            margin: 1rem 0;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
        }

        .profile-info h3 {
            font-size: 2.2em;
            margin-bottom: 0.5rem;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .department-name {
            font-size: 1.6em;
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
            margin: 0;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }

        .profile-info p {
            color: rgba(255, 255, 255, 0.95);
            margin: 0.7rem 0;
            font-size: 1.5em;
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
            0% {
                opacity: 0;
                transform: scale(0.8) translateY(50px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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

        .settings-body::-webkit-scrollbar {
            width: 8px;
        }

        .settings-body::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #ff0000, #ff6666);
            border-radius: 10px;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .settings-section:last-child {
            margin-bottom: 0;
        }

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

        /* Profile Photo Section */
        .profile-photo-section {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 20px;
            border: 2px solid #fee;
        }

        .current-photo-container {
            position: relative;
        }

        .current-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .current-photo:hover {
            transform: scale(1.05);
        }

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

        .photo-upload-area {
            flex: 1;
        }

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

        /* Password Section */
        .password-form {
            padding: 20px;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 20px;
            border: 2px solid #fee;
        }

        .password-input-group {
            margin-bottom: 20px;
        }

        .password-input-group:last-of-type {
            margin-bottom: 25px;
        }

        .password-label {
            display: block;
            font-size: 0.95em;
            font-weight: 600;
            color: #444;
            margin-bottom: 8px;
        }

        .password-input-wrapper {
            position: relative;
        }

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

        /* Success/Error Messages */
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

        .main-content {
            width: 100%;
            min-height: calc(100vh - 85px); /* Subtract navbar height */
            margin: 0;
            padding: 15px 20px;
            text-align: center;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-top: 0;
            margin-top: -80px;
            perspective: 1000px;
        }

        .welcome-text {
            color: #ff0000;
            font-size: 7.5em;
            margin-bottom: 5px;
            font-weight: bold;
            transform-origin: center;
            transform-style: preserve-3d;
            text-shadow: 0 10px 30px rgba(255, 0, 0, 0.3);
            position: relative;
            padding-bottom: 25px;
            display: inline-block;
            letter-spacing: 0.05em;
            animation: centerPopOut 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .welcome-text span {
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

        .welcome-text::after {
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

        .subtitle {
            font-size: 2.8em;
            color: #333;
            font-style: italic;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
            animation: fadeInSubtitle 0.8s ease-out 0.8s forwards;
            opacity: 0;
        }

        .subtitle::before {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            background: linear-gradient(90deg, #ff0000, #ff3333);
            border-radius: 15px;
            z-index: 1;
            animation: blockReveal 0.8s ease-out 0.8s forwards;
            transform: scaleX(1);
            transform-origin: right;
            pointer-events: none;
        }

        @keyframes fadeInSubtitle {
            0% {
                opacity: 0;
            }
            100% {
                opacity: 1;
            }
        }

        @keyframes blockReveal {
            0% {
                transform: scaleX(1);
                transform-origin: right;
            }
            100% {
                transform: scaleX(0);
                transform-origin: right;
            }
        }

        .post-task-btn {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            text-decoration: none;
            color: #333;
            transform: scale(1.5);
            animation: slideUpBaseline 0.6s ease-out 0.8s forwards;
            opacity: 0;
        }

        .post-task-icon {
            width: 150px;
            height: 150px;
            border: 5px solid #333;
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4.5em;
        }

        .post-task-text {
            font-size: 2em;
            font-weight: bold;
        }

        @keyframes slideUpBaseline {
            0% {
                opacity: 0;
                transform: scale(1.2) translateY(30px);
            }
            100% {
                opacity: 1;
                transform: scale(1.2) translateY(0);
            }
        }

        /* Task Creation Modal Styles */
        .task-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .task-modal.show {
            opacity: 1;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding-top: 20px;
        }

        .task-form {
            background: white;
            padding: 60px;
            border-radius: 25px;
            width: 90%;
            max-width: 900px;
            min-height: 90vh;
            position: relative;
            transform: translateY(-50px);
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(255, 0, 0, 0.2);
            border: 3px solid #ff0000;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .task-modal.show .task-form {
            transform: translateY(0);
            opacity: 1;
        }

        /* Hide bottom nav when task modal is shown */
        .task-modal.show ~ .bottom-nav,
        body:has(.task-modal.show) .bottom-nav {
            display: none !important;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border: none;
            background: none;
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .close-modal svg {
            width: 28px;
            height: 28px;
        }

        .close-modal:hover {
            transform: rotate(90deg);
        }

        .form-title {
            color: #ff0000;
            font-size: 3.5em;
            margin-bottom: 45px;
            text-align: center;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 35px;
        }

        .form-label {
            display: block;
            color: #333;
            font-size: 1.6em;
            margin-bottom: 12px;
            font-weight: bold;
        }

        .form-input {
            width: 100%;
            padding: 25px;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 1.8em;
            transition: all 0.3s ease;
        }

        .form-input::-webkit-calendar-picker-indicator {
            width: 60px;
            height: 60px;
            cursor: pointer;
            filter: brightness(0.8);
        }

        .form-input::-webkit-time-picker-indicator {
            width: 45px;
            height: 45px;
            cursor: pointer;
        }

        .form-input:focus {
            border-color: #ff0000;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.1);
            outline: none;
        }

        .form-textarea {
            min-height: 180px;
            resize: vertical;
        }

        .form-select {
            appearance: none;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23ff0000' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 15px center;
            background-color: white;
            padding-right: 100px;
            height: auto;
            min-height: 300px;
        }

        .form-select option {
            padding: 10px;
            line-height: 1.5;
        }

        .deadline-group {
            display: flex;
            gap: 30px;
        }

        .deadline-group .form-group {
            flex: 1;
        }

        .submit-btn {
            background: #ff0000;
            color: white;
            border: none;
            padding: 20px 50px;
            font-size: 1.6em;
            border-radius: 12px;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: all 0.3s ease;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(255, 0, 0, 0.2);
        }

        .submit-btn:hover {
            background: #e60000;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .attachment-area {
            border: 2px dashed #ddd;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .attachment-area:hover {
            border-color: #ff0000;
            background: rgba(255, 0, 0, 0.05);
        }

        .attachment-area i {
            font-size: 3em;
            color: #ff0000;
            margin-bottom: 15px;
        }

        /* Custom File Upload Styles */
        .file-upload-container {
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
        }

        /* File Input Styling to Match Modal Design */
        .form-control {
            border: 2px solid #ddd !important;
            border-radius: 12px !important;
            font-size: 1.2em !important;
            padding: 20px !important;
            transition: all 0.3s ease !important;
            background: white !important;
        }

        .form-control:hover {
            border-color: #ff0000 !important;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.1) !important;
        }

        .form-control:focus {
            border-color: #ff0000 !important;
            box-shadow: 0 0 15px rgba(255, 0, 0, 0.2) !important;
            outline: none !important;
        }

        .form-text {
            font-size: 0.95em !important;
            color: #666 !important;
            margin-top: 8px !important;
            display: block !important;
        }

        /* Professional Upload Box Styling */
        .upload-box {
            border: 2px dashed #ff0000;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            background: #ffe6e6;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            overflow: hidden;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .upload-box:hover {
            border-color: #cc0000;
            background: #ffcccc;
        }

        .upload-box.dragover {
            border-color: #cc0000;
            background: #ffcccc;
        }

        .upload-box.has-image {
            padding: 20px;
            min-height: auto;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .upload-box.has-image .upload-icon,
        .upload-box.has-image .upload-title,
        .upload-box.has-image .upload-files,
        .upload-box.has-image .upload-or,
        .upload-box.has-image .browse-button,
        .upload-box.has-image .upload-size,
        .upload-box.has-image .file-name {
            display: none !important;
        }

        .upload-icon {
            font-size: 3em;
            margin-bottom: 15px;
            color: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .upload-icon svg {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .upload-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .upload-files {
            font-size: 0.95em;
            color: #999;
            margin-bottom: 15px;
        }

        .upload-or {
            color: #999;
            font-size: 1em;
            margin: 15px 0;
        }

        .browse-button {
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

        .browse-button:hover {
            background: #007bff;
            color: white;
        }

        .upload-size {
            font-size: 0.9em;
            color: #999;
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
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
            display: block;
            border: 1px solid #ddd;
        }

        .preview-image:hover {
            transform: scale(1.05);
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
            margin-bottom: 12px;
            display: none;
        }

        .remove-btn:hover {
            background: #e63939;
        }

        .upload-box.has-image .remove-btn {
            display: inline-block;
        }

        /* Custom Dropdown Styles */
        .custom-dropdown {
            position: relative;
            width: 100%;
        }

        .dropdown-header {
            width: 100%;
            padding: 20px;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 1.4em;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .dropdown-header:hover {
            border-color: #ff0000;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.1);
        }

        .dropdown-arrow {
            color: #ff0000;
            transition: transform 0.3s ease;
        }

        .custom-dropdown.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #ff0000;
            border-radius: 12px;
            margin-top: 5px;
            display: none;
            z-index: 1000;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.15);
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .custom-dropdown.active .dropdown-menu {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown-search {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .dropdown-search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .dropdown-search-input:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 8px rgba(255, 0, 0, 0.1);
        }

        .dropdown-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-item {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
            font-size: 1.3em;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: #f5f5f5;
            padding-left: 20px;
            color: #ff0000;
            font-weight: 500;
        }

        .dropdown-item.selected {
            background: rgba(255, 0, 0, 0.1);
            color: #ff0000;
            font-weight: bold;
        }

        .dropdown-item.hidden {
            display: none;
        }

        /* Profile Picture Modal Styles */
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

    /* Ensure modal displays above everything */
    #previewModal {
        z-index: 9999 !important;
    }

    #previewModal .modal-backdrop {
        z-index: 9998 !important;
    }

    /* Hide filename/size when viewing preview */
    #fileInfo {
        display: none;
    }

    /* ====== MOBILE RESPONSIVE STYLES ====== */
    @media (max-width: 768px) {
        /* Typography Adjustments */
        html {
            font-size: 14px;
        }

        /* Navigation Bar - Mobile */
        .nav-bar {
            display: none !important;
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

        /* Icon Buttons - Mobile */
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

        /* Profile Picture - Mobile */
        .profile-pic {
            width: 50px;
            height: 50px;
            border: 3px solid #ff0000;
            box-shadow: 0 3px 12px rgba(255, 0, 0, 0.2);
        }

        /* Dropdowns - Mobile */
        .profile-dropdown {
            position: fixed;
            top: 70px;
            right: 10px;
            left: 10px;
            min-width: auto;
            width: calc(100% - 20px);
            max-width: 100%;
            border-radius: 15px;
            padding: 1.2rem;
            z-index: 1001;
        }

        .message-dropdown,
        .notification-dropdown {
            position: fixed;
            top: 70px;
            right: 10px;
            left: 10px;
            width: calc(100% - 20px);
            max-width: 100%;
            max-height: 70vh;
            border-radius: 15px;
            padding: 0;
            z-index: 1001;
        }

        .message-meta p,
        .notification-content p {
            max-width: 100%;
        }

        /* Chat Modal - Mobile */
        .chat-container {
            width: 95%;
            max-width: 100%;
            height: 90%;
            max-height: 90vh;
            border-radius: 15px;
        }

        .chat-header-name {
            font-size: 1.1em;
        }

        .chat-message {
            max-width: 85%;
            padding: 10px 14px;
            font-size: 0.95em;
        }

        .chat-input {
            padding: 12px 15px;
            font-size: 1em;
            border-radius: 20px;
        }

        .chat-send-btn {
            width: 45px;
            height: 45px;
        }

        /* Settings Modal - Mobile */
        .settings-container {
            width: 95%;
            max-width: 100%;
            max-height: 90vh;
            border-radius: 20px;
            padding: 0;
        }

        .settings-header {
            padding: 20px 20px;
        }

        .settings-title {
            font-size: 1.5em;
        }

        .settings-body {
            padding: 20px;
            max-height: 60vh;
        }

        .profile-photo-section {
            flex-direction: column;
            gap: 15px;
        }

        .current-photo {
            width: 80px;
            height: 80px;
        }

        .photo-upload-btn {
            width: 100%;
            padding: 12px 20px;
            font-size: 0.95em;
        }

        /* Task Modal - Mobile */
        .task-form {
            width: 90%;
            max-width: 95%;
            max-height: 90vh;
            padding: 25px 18px;
            border-radius: 12px;
            margin: 20px auto;
        }

        .form-title {
            font-size: 2em;
            margin-bottom: 25px;
        }

        .form-label {
            font-size: 1.1em;
            margin-bottom: 8px;
        }

        .form-input {
            padding: 15px;
            font-size: 1em;
            border-radius: 10px;
        }

        .form-textarea {
            min-height: 120px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .deadline-group {
            flex-direction: column;
            gap: 20px;
        }

        .submit-btn {
            padding: 15px 25px;
            font-size: 1em;
            margin-top: 20px;
        }

        /* Main Content - Mobile */
        .main-content {
            padding: 0 15px 50px 15px;
            min-height: 100vh;
            margin-top: -60px;
        }

        .welcome-text {
            font-size: 2.5em;
            margin-bottom: 5px;
            padding-bottom: 15px;
        }

        .subtitle {
            font-size: 1.3em;
            margin-bottom: 12px;
        }

        .post-task-btn {
            transform: scale(1);
        }

        .post-task-icon {
            width: 100px;
            height: 100px;
            font-size: 2.5em;
            border: 4px solid #333;
        }

        .post-task-text {
            font-size: 1.2em;
        }

        /* Notification Item - Mobile */
        .notification-item {
            padding: 12px;
            padding-right: 35px;
            margin-bottom: 8px;
        }

        .notification-avatar {
            width: 50px;
            height: 50px;
        }

        .notification-student-name {
            font-size: 0.95em;
        }

        .notification-task-title {
            font-size: 0.85em;
        }

        .notification-message {
            font-size: 0.8em;
        }

        .notification-time {
            font-size: 0.75em;
        }

        /* Message Card - Mobile */
        .message-card {
            padding: 12px;
            gap: 10px;
            margin-bottom: 8px;
        }

        .message-avatar {
            width: 45px;
            height: 45px;
        }

        .message-meta h5 {
            font-size: 1em;
        }

        .message-meta p {
            font-size: 0.85em;
        }

        .message-time {
            font-size: 0.75em;
        }

        /* Profile Dropdown Content - Mobile */
        .profile-info {
            padding: 1.2rem;
            margin-bottom: 1rem;
            border-radius: 12px;
        }

        .profile-info h3 {
            font-size: 1.5em;
        }

        .department-name {
            font-size: 1.1em;
        }

        .profile-info p {
            font-size: 1.1em;
        }

        .profile-menu a {
            padding: 1rem 1.2rem;
            font-size: 1.1em;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-size: 1.2em !important;
        }

        /* General Modal Styling - Mobile */
        .settings-message,
        .password-form,
        .password-input {
            font-size: 0.95em;
        }

        .password-input-group {
            margin-bottom: 15px;
        }

        .save-password-btn {
            padding: 12px 20px;
            font-size: 1em;
        }

        /* Hide large elements on mobile */
        .upload-box {
            min-height: 150px;
            padding: 25px 15px;
        }

        /* Make message/notification dropdowns scrollable */
        .message-list,
        .notification-list {
            max-height: 50vh;
        }
    }

    @media (max-width: 480px) {
        /* Extra Small Screens */
        html {
            font-size: 12px;
        }

        .nav-bar {
            display: none !important;
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

        .welcome-text {
            font-size: 1.8em;
            margin-bottom: 15px;
        }

        .subtitle {
            font-size: 1em;
            margin-bottom: 30px;
        }

        .main-content {
            padding: 100px 12px 40px 12px;
        }

        .task-form {
            padding: 20px 15px;
        }

        .form-title {
            font-size: 1.5em;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 1em;
        }

        .form-input {
            padding: 12px;
            font-size: 0.95em;
        }

        .submit-btn {
            padding: 12px 20px;
            font-size: 0.95em;
        }

        .close-modal {
            width: 35px;
            height: 35px;
        }

        .close-modal svg {
            width: 24px;
            height: 24px;
        }

        .post-task-icon {
            width: 80px;
            height: 80px;
            font-size: 2em;
            border: 3px solid #333;
        }

        .post-task-text {
            font-size: 1em;
        }

        .notification-item,
        .message-card {
            padding: 10px;
        }

        .notification-avatar,
        .message-avatar {
            width: 40px;
            height: 40px;
        }

        .settings-container {
            border-radius: 15px;
        }

        .settings-header {
            padding: 15px;
        }

        .settings-title {
            font-size: 1.3em;
        }

        .settings-body {
            padding: 15px;
        }

        .profile-photo-section {
            padding: 15px;
        }

        .current-photo {
            width: 70px;
            height: 70px;
        }

        .chat-container {
            border-radius: 12px;
        }

        .chat-header-name {
            font-size: 1em;
        }

        .chat-message {
            max-width: 90%;
            padding: 8px 12px;
            font-size: 0.9em;
        }

        .message-list,
        .notification-list {
            max-height: 55vh;
        }
    }

    @media (max-width: 375px) {
        /* Very Small Screens (iPhone 6 and below) */
        html {
            font-size: 11px;
        }

        .nav-bar {
            display: none !important;
            padding: 10px 6px;
        }

        .icon-btn {
            width: 42px;
            height: 42px;
        }

        .welcome-text {
            font-size: 1.5em;
        }

        .subtitle {
            font-size: 0.9em;
        }

        .form-title {
            font-size: 1.3em;
        }

        .task-form {
            padding: 15px 12px;
        }
    }

    /* Tablet Landscape - Better Layout */
    @media (min-width: 769px) and (max-width: 1024px) {
        .nav-bar {
            display: none !important;
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

        .chat-container {
            width: 600px;
            max-width: 90%;
        }

        .settings-container {
            width: 550px;
            max-width: 90%;
        }

        .task-form {
            width: 85%;
            max-width: 700px;
            padding: 40px;
        }

        .message-dropdown,
        .notification-dropdown {
            width: 450px;
            max-width: 95%;
        }

        /* Mobile Navigation Bar */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            background: #ffffff;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            height: 80px;
            z-index: 999;
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
            padding: 10px 0;
            gap: 0;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #999999;
            transition: all 0.2s ease;
            flex: 1;
            height: 100%;
            gap: 4px;
            outline: none;
            position: relative;
            border-radius: 0;
            margin: 0;
            padding: 0;
        }

        .bottom-nav-item:hover {
            color: #ff0000;
        }

        .bottom-nav-item.active {
            color: #ff0000;
            font-weight: 500;
        }

        .bottom-nav-icon {
            font-size: 24px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .bottom-nav-icon svg {
            width: 24px;
            height: 24px;
            stroke: currentColor !important;
            fill: none;
            stroke-width: 2.2;
        }

        .bottom-nav-item:hover .bottom-nav-icon {
            transform: scale(1.1);
        }

        .bottom-nav-item:hover .bottom-nav-icon svg {
            stroke: #ff0000 !important;
        }

        .bottom-nav-item.active .bottom-nav-icon {
            color: #ff0000;
        }

        .bottom-nav-item.active .bottom-nav-icon svg {
            stroke: #ff0000 !important;
        }

        .bottom-nav-label {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0px;
            opacity: 1;
            transition: color 0.2s ease;
            color: inherit;
        }

        .bottom-nav-item:hover .bottom-nav-label {
            color: #ff0000;
        }

        .bottom-nav-item.active .bottom-nav-label {
            color: #ff0000;
        }

        /* Body padding for bottom nav */
        body {
            padding-bottom: 95px;
        }

        /* Tablet and mobile layout adjustments */
        @media (max-width: 768px) {
            .task-form {
                width: 95%;
                min-height: 90vh;
                padding: 40px;
                border-radius: 15px;
            }

            .nav-bar {
                display: none;
            }

            .bottom-nav {
                height: 80px;
                padding: 10px 0;
                gap: 0;
                background: #ffffff;
                border-top: 1px solid #e0e0e0;
                box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
            }

            .bottom-nav-item {
                gap: 4px;
                margin: 0;
                padding: 0;
                border-radius: 0;
            }

            .bottom-nav-icon {
                font-size: 24px;
                width: 32px;
                height: 32px;
            }

            .bottom-nav-icon svg {
                width: 24px;
                height: 24px;
                stroke-width: 2.2;
                stroke: currentColor !important;
                fill: none;
            }

            .bottom-nav-label {
                font-size: 10px;
                font-weight: 500;
            }

            body {
                padding-bottom: 95px;
            }
        }

        /* Small phone adjustments */
        @media (max-width: 480px) {
            .task-form {
                width: 100%;
                min-height: 100vh;
                padding: 30px 20px;
                border-radius: 0;
                max-width: 100%;
            }

            .task-modal.show {
                padding-top: 0;
            }

            .bottom-nav {
                height: 80px;
                padding: 10px 0;
                background: #ffffff;
                border-top: 1px solid #e0e0e0;
            }

            .bottom-nav-item {
                gap: 4px;
                border-radius: 8px;
            }

            .bottom-nav-icon {
                font-size: 24px;
            }

            .bottom-nav-icon svg {
                width: 24px;
                height: 24px;
                stroke: #666 !important;
                fill: none;
                stroke-width: 2.2;
            }

            .bottom-nav-label {
                font-size: 9px;
                font-weight: 700;
            }

            body {
                padding-bottom: 95px;
            }
        }
    </style>
    <style>
        /* Global Bottom Navigation override: keep fixed and on top across sizes */
        .bottom-nav {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            background: #ffffff !important;
            border-top: 1px solid #e0e0e0 !important;
            display: flex !important;
            justify-content: space-around !important;
            align-items: center !important;
            height: 80px !important;
            z-index: 9999 !important;
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06) !important;
            padding: 10px 0 !important;
            gap: 0 !important;
            touch-action: manipulation !important;
        }

        .bottom-nav-item {
            flex: 1 1 auto !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            text-decoration: none !important;
            color: #999999 !important;
            gap: 4px !important;
            padding: 0 !important;
            border-radius: 0 !important;
            height: 100% !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }

        .bottom-nav-item:hover {
            color: #ff0000 !important;
        }

        .bottom-nav-item.active {
            color: #ff0000 !important;
            font-weight: 500 !important;
        }

        .bottom-nav-icon { 
            font-size: 24px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            transition: all 0.2s ease !important;
        }

        .bottom-nav-icon svg {
            width: 24px !important;
            height: 24px !important;
            stroke: currentColor !important;
            fill: none !important;
            stroke-width: 2.2 !important;
        }

        .bottom-nav-item:hover .bottom-nav-icon {
            transform: scale(1.1) !important;
        }

        .bottom-nav-item:hover .bottom-nav-icon svg {
            stroke: #ff0000 !important;
        }

        .bottom-nav-item.active .bottom-nav-icon svg {
            stroke: #ff0000 !important;
        }

        .bottom-nav-label {
            font-size: 10px !important;
            font-weight: 500 !important;
            letter-spacing: 0px !important;
            opacity: 1 !important;
            transition: color 0.2s ease !important;
            color: inherit !important;
        }

        .bottom-nav-item:hover .bottom-nav-label {
            color: #ff0000 !important;
        }

        .bottom-nav-item.active .bottom-nav-label {
            color: #ff0000 !important;
        }

        /* Ensure page content isn't obscured by the nav */
        body { padding-bottom: 95px !important; }

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

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f0f0f0;
            border-top: 5px solid #ff0000;
            border-radius: 50%;
            margin: 20px auto;
            animation: spinnerRotate 1s linear infinite;
        }

        @keyframes spinnerRotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.3em;
            color: #ff0000;
            font-weight: 700;
            margin-top: 20px;
            letter-spacing: 2px;
            animation: textBlink 1.5s ease-in-out infinite;
        }

        @keyframes textBlink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
    </style>
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

        // Mark all notifications as read
        function markAllRead() {
            // Send AJAX request to persist the read state
            fetch('teacher_task_page.php', {
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
                // Check if notification list is empty
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
            // Reset form
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
                const maxSize = 5 * 1024 * 1024; // 5MB
                
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
                    // Update all profile images on the page
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

        // Allow ESC key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeProfileModal();
                closeSettingsModal();
                closeNotifDetail();
                hideTaskForm();
                const messageDropdown = document.getElementById('messageDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                if (messageDropdown) messageDropdown.classList.remove('show');
                if (notificationDropdown) notificationDropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.getElementById('profileDropdown');
            const profilePic = document.querySelector('.profile-pic');
            const messageDropdown = document.getElementById('messageDropdown');
            const messageBtn = document.querySelector('.message-btn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationBtn = document.querySelector('.notification-btn');
            
            if (profileDropdown && !profileDropdown.contains(event.target) && event.target !== profilePic) {
                profileDropdown.classList.remove('show');
            }

            if (messageDropdown && !messageDropdown.contains(event.target) && event.target !== messageBtn) {
                messageDropdown.classList.remove('show');
            }

            if (notificationDropdown && !notificationDropdown.contains(event.target) && event.target !== notificationBtn) {
                notificationDropdown.classList.remove('show');
            }
        });

        // Task Form Functions
        function showTaskForm() {
            const modal = document.getElementById('taskModal');
            const bottomNav = document.querySelector('.bottom-nav');
            modal.classList.add('show');
            if (bottomNav) {
                bottomNav.style.display = 'none';
            }
            document.body.style.overflow = 'hidden';
        }

        function hideTaskForm() {
            const modal = document.getElementById('taskModal');
            const bottomNav = document.querySelector('.bottom-nav');
            modal.classList.remove('show');
            if (bottomNav) {
                bottomNav.style.display = '';
            }
            document.body.style.overflow = '';
        }

        function selectPriority(element, priority) {
            // Remove selected class from all options
            document.querySelectorAll('.priority-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            // Add selected class to clicked option
            element.classList.add('selected');
        }

        // Handle file input
        let fileUploadArea = null;
        let fileInput = null;

        document.addEventListener('DOMContentLoaded', function() {
            fileInput = document.getElementById('fileInput');
            fileUploadArea = document.getElementById('fileUploadArea');
            
            // Close modal when clicking outside
            const taskModal = document.getElementById('taskModal');
            if (taskModal) {
                taskModal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        hideTaskForm();
                    }
                });
            }

            if (fileInput) {
                // Handle file input change (do not auto-open modal)
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        // Read file and update preview only
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const imageData = e.target.result;
                            
                            const imagePreviewEl = document.getElementById('imagePreview');
                            if (imagePreviewEl) imagePreviewEl.src = imageData;
                            
                            // Display file in upload box
                            displayFileInUploadBoxWithData(file, imageData);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Allow drag and drop
            if (fileUploadArea) {
                fileUploadArea.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.classList.add('dragover');
                });

                fileUploadArea.addEventListener('dragleave', function() {
                    this.classList.remove('dragover');
                });

                fileUploadArea.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.classList.remove('dragover');
                    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                        fileInput.files = e.dataTransfer.files;
                        // Trigger change event
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }
        });

        function displayFileInUploadBox(file) {
            const uploadBox = document.getElementById('fileUploadArea');
            
            // Add has-image class immediately
            uploadBox.classList.add('has-image');
            
            // Show the file name
            document.getElementById('fileFileName').textContent = '✓ ' + file.name;
            
            // Show preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                // Remove existing preview if any
                const existingPreview = uploadBox.querySelector('.preview-image');
                if (existingPreview) {
                    existingPreview.remove();
                }
                
                // Create new preview image
                const previewImg = document.createElement('img');
                previewImg.src = e.target.result;
                previewImg.className = 'preview-image';
                previewImg.onclick = function(event) {
                    event.stopPropagation();
                    openFilePreviewModal(this.src, file.name);
                };
                uploadBox.appendChild(previewImg);
            };
            reader.readAsDataURL(file);
        }

        function displayFileInUploadBoxWithData(file, imageData) {
            const uploadBox = document.getElementById('fileUploadArea');
            
            // Add has-image class immediately
            uploadBox.classList.add('has-image');
            
            // Show the file name
            document.getElementById('fileFileName').textContent = '✓ ' + file.name;
            
            // Remove existing preview if any
            const existingPreview = uploadBox.querySelector('.preview-image');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            // Create new preview image with already-loaded data
            const previewImg = document.createElement('img');
            previewImg.src = imageData;
            previewImg.className = 'preview-image';
            previewImg.onclick = function(event) {
                event.stopPropagation();
                openFilePreviewModal(this.src, file.name);
            };
            uploadBox.appendChild(previewImg);
        }

        function openFilePreviewModal(src, fileName) {
            document.getElementById('imagePreview').src = src;
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        }

        function removeSelectedFile(event) {
            event.preventDefault();
            
            const fileInput = document.getElementById('fileInput');
            const uploadBox = document.getElementById('fileUploadArea');
            
            fileInput.value = '';
            document.getElementById('fileFileName').textContent = '';
            
            // Remove preview image
            const previewImg = uploadBox.querySelector('.preview-image');
            if (previewImg) {
                previewImg.remove();
            }
            
            uploadBox.classList.remove('has-image');
        }

        function submitTask(event) {
            event.preventDefault();
            
            // Get the form
            const form = document.getElementById('taskForm');
            
            // Check if room is selected
            const selectedRoom = document.getElementById('selectedRoomValue').value;
            if (!selectedRoom) {
                hideTaskForm();
                Swal.fire({
                    title: 'Error!',
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
                                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <p style="
                                margin: 0 0 10px 0;
                                font-size: 16px;
                                color: #333;
                                font-weight: 600;
                            ">Please select a location!</p>
                            <p style="
                                margin: 0;
                                font-size: 14px;
                                color: #666;
                                line-height: 1.5;
                            ">You need to choose a location before creating the task.</p>
                        </div>
                    `,
                    confirmButtonColor: '#ff0000',
                    confirmButtonText: 'Okay',
                    customClass: {
                        popup: 'swal2-error-popup',
                        confirmButton: 'swal2-error-button'
                    },
                    showConfirmButton: true,
                    didOpen: (modal) => {
                        modal.style.borderRadius = '20px';
                        modal.style.boxShadow = '0 10px 40px rgba(255, 0, 0, 0.25)';
                        modal.style.zIndex = '5001';
                    }
                });
                return;
            }
            
            // Create FormData from the form
            const formData = new FormData(form);
            
            // Submit the form using fetch
            fetch('process_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    hideTaskForm();
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
                                ">Task Created Successfully!</p>
                                <p style="
                                    margin: 0;
                                    font-size: 14px;
                                    color: #666;
                                    line-height: 1.5;
                                ">Your task has been posted and students will be able to see it now.</p>
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
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to post task. Please try again.'
                });
            });
        }

        // Room Dropdown Functions
        function toggleRoomDropdown() {
            const dropdown = document.querySelector('.custom-dropdown');
            dropdown.classList.toggle('active');
        }

        function selectRoom(element) {
            const value = element.getAttribute('data-value');
            const text = element.textContent;
            
            document.getElementById('selectedRoom').textContent = text;
            document.getElementById('selectedRoomValue').value = value;
            
            // Update selected state
            document.querySelectorAll('.dropdown-item').forEach(item => {
                item.classList.remove('selected');
            });
            element.classList.add('selected');
            
            // Close dropdown
            toggleRoomDropdown();
        }

        function filterRooms() {
            const searchInput = document.getElementById('roomSearch');
            const filter = searchInput.value.toUpperCase();
            const items = document.querySelectorAll('.dropdown-item');
            
            items.forEach(item => {
                const text = item.textContent;
                if (text.includes(filter)) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.custom-dropdown');
            const dropdownHeader = document.querySelector('.dropdown-header');
            
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });


    </script>
</head>
<body style="overflow-x: hidden;">
    <!-- Page Loading Overlay -->
    <div class="page-loading-overlay" id="pageLoadingOverlay">
        <div class="loading-container">
            <svg class="loading-svg-icon" fill="#ff0000" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 612 612" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><g><path d="M509.833,0c-53.68,0-97.353,43.673-97.353,97.353c0,53.683,43.673,97.356,97.353,97.356 c53.683,0,97.356-43.673,97.356-97.356C607.192,43.673,563.519,0,509.833,0z M541.092,112.035h-31.259 c-8.11,0-14.681-6.571-14.681-14.681V47.185c0-8.11,6.571-14.681,14.681-14.681c8.11,0,14.681,6.571,14.681,14.681v35.487h16.578 c8.11,0,14.681,6.571,14.681,14.681S549.202,112.035,541.092,112.035z M562.066,496.442c-1.283-10.145-6.439-19.185-14.52-25.451 L404.343,359.943c-6.777-5.256-14.884-8.033-23.449-8.033c-0.81,0-1.603,0.088-2.405,0.135c-0.294-0.006-0.581-0.038-0.875-0.038 c-2.625,0-5.262,0.273-7.843,0.81l-139.556,29.101l-8.638-39.478c3.353,0.945,6.847,1.456,10.418,1.456h0.003 c0.041,0,0.079-0.006,0.117-0.006c1.177,0.112,2.364,0.179,3.562,0.185l97.941,0.279c0.015,0,0.088,0,0.103,0c0,0,0,0,0.003,0 c21.053,0,38.23-17.127,38.288-38.18c0.021-7.109-1.926-13.909-5.511-19.843l39.595-82.951c3.491-7.317,0.391-16.082-6.924-19.576 c-7.329-3.488-16.085-0.393-19.576,6.924l-37.258,78.054c-2.763-0.634-5.605-0.998-8.506-1.004l-86.04-0.244l-59.177-57.714 c-2.484-2.422-5.256-4.44-8.213-6.081c-6.565-4.633-14.505-7.346-22.914-7.346c-2.869,0-5.749,0.311-8.565,0.928 c-10.427,2.279-19.338,8.486-25.099,17.471c-5.758,8.985-7.672,19.673-5.391,30.099l41.468,189.498 c2.29,10.453,8.685,19.244,17.23,24.846c-24.203-6.363-44.81-23.971-54.206-48.571L33.211,175.847 c-2.895-7.575-11.381-11.375-18.953-8.477c-7.575,2.892-11.369,11.381-8.477,18.953l89.716,234.822 c16.325,42.728,57.315,70.073,101.775,70.073c6.357,0,12.79-0.558,19.229-1.712c0.247-0.047,0.493-0.094,0.737-0.153 l138.138-32.122l30.519,125.521C390.082,599.973,405.371,612,423.077,612c3.045,0,6.096-0.367,9.067-1.089 c9.939-2.414,18.34-8.556,23.66-17.294c5.32-8.735,6.915-19.021,4.498-28.957l-19.3-79.384l59.617,46.231 c6.777,5.253,14.881,8.031,23.443,8.031h0.003c11.93,0,22.964-5.406,30.272-14.828C560.603,516.629,563.349,506.59,562.066,496.442 z M333.721,329.667L333.721,329.667v0.003V329.667z M118.302,156.313c-9.396-11.034-13.932-25.067-12.778-39.513 c2.358-29.442,28.564-52.147,58.419-49.748c14.446,1.157,27.577,7.872,36.973,18.903c9.396,11.031,13.932,25.067,12.778,39.513 c-2.246,27.994-25.983,49.925-54.038,49.925c-1.451,0-2.91-0.059-4.378-0.176C140.829,174.062,127.698,167.347,118.302,156.313z"></path></g></g></svg>
            <div class="loading-text">Loading...</div>
        </div>
    </div>

    
    <!-- Navigation bar copied exactly from teacher_record.php -->
    <nav class="nav-bar">
        <div class="nav-links">
            <a href="teacher_task_page.php" class="active">Home</a>
            <a href="assigned_tasks.php">Assigned Tasks</a>
            <a href="teacher_record.php">Record</a>
        </div>
    </nav>

    <div class="content-section" id="notificationContent">
        <!-- Content will be loaded here -->
    </div>

    <main class="main-content">

        <h1 class="welcome-text">Welcome to UtosApp!</h1>
        <p class="subtitle">Your all-in-one platform task assistant.</p>
        
        <a href="#" class="post-task-btn" onclick="showTaskForm()">
            <div class="post-task-icon">+</div>
            <span class="post-task-text">Post Task</span>
        </a>
    </main>

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
                <!-- Profile Photo Section -->
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

                <!-- Change Password Section -->
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

    <!-- Task Creation Modal -->
    <div class="task-modal" id="taskModal">
        <div class="task-form">
            <button class="close-modal" onclick="hideTaskForm()"><svg height="24px" width="24px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" fill="#af1212" stroke="#af1212"><path style="fill:#FF6465;" d="M256.002,503.671c136.785,0,247.671-110.886,247.671-247.672S392.786,8.329,256.002,8.329 S8.33,119.215,8.33,256.001S119.216,503.671,256.002,503.671z"></path> <path style="opacity:0.1;enable-background:new ;" d="M74.962,256.001c0-125.485,93.327-229.158,214.355-245.434 c-10.899-1.466-22.016-2.238-33.316-2.238C119.216,8.329,8.33,119.215,8.33,256.001s110.886,247.672,247.671,247.672 c11.3,0,22.417-0.772,33.316-2.238C168.289,485.159,74.962,381.486,74.962,256.001z"></path> <path style="fill:#FFFFFF;" d="M311.525,256.001l65.206-65.206c4.74-4.74,4.74-12.425,0-17.163l-38.36-38.36 c-4.74-4.74-12.425-4.74-17.164,0l-65.206,65.206l-65.206-65.206c-4.74-4.74-12.425-4.74-17.163,0l-38.36,38.36 c-4.74,4.74-4.74,12.425,0,17.163l65.206,65.206l-65.206,65.206c-4.74,4.74-4.74,12.425,0,17.164l38.36,38.36 c4.74,4.74,12.425,4.74,17.163,0l65.206-65.206l65.206,65.206c4.74,4.74,12.425,4.74,17.164,0l38.36-38.36 c4.74-4.74,4.74-12.425,0-17.164L311.525,256.001z"></path> <path d="M388.614,182.213c0-5.467-2.129-10.607-5.995-14.471l-38.36-38.36c-3.865-3.865-9.004-5.994-14.471-5.994 s-10.605,2.129-14.471,5.994l-59.316,59.316l-59.316-59.316c-3.865-3.865-9.004-5.994-14.471-5.994 c-5.467,0-10.606,2.129-14.471,5.994l-38.36,38.36c-7.979,7.979-7.979,20.962,0,28.943l59.316,59.316l-59.316,59.316 c-7.979,7.979-7.979,20.962,0,28.943l38.36,38.36c3.865,3.865,9.004,5.993,14.471,5.993c5.467,0,10.606-2.129,14.471-5.993 l59.316-59.316l59.316,59.316c3.865,3.865,9.004,5.993,14.471,5.993s10.605-2.129,14.471-5.993l38.36-38.36 c3.866-3.865,5.995-9.004,5.995-14.471c0-5.467-2.129-10.607-5.995-14.471l-59.315-59.316l59.315-59.315 C386.485,192.818,388.614,187.68,388.614,182.213z M370.84,184.905l-65.204,65.206c-3.253,3.253-3.253,8.527,0,11.778l65.204,65.207 c0.971,0.971,1.115,2.103,1.115,2.692c0,0.589-0.144,1.721-1.115,2.692l-38.36,38.36c-0.971,0.971-2.103,1.115-2.692,1.115 c-0.589,0-1.722-0.144-2.692-1.115l-65.206-65.206c-1.626-1.626-3.758-2.44-5.889-2.44c-2.131,0-4.263,0.813-5.889,2.44 l-65.206,65.206c-0.971,0.971-2.103,1.115-2.692,1.115c-0.59,0-1.722-0.144-2.693-1.115l-38.36-38.36 c-1.484-1.485-1.484-3.9,0-5.385l65.206-65.206c3.253-3.253,3.253-8.527,0-11.778l-65.206-65.206c-1.484-1.485-1.484-3.9,0-5.385 l38.359-38.36c0.971-0.971,2.104-1.115,2.693-1.115s1.722,0.144,2.692,1.115l65.206,65.206c3.253,3.253,8.527,3.253,11.778,0 l65.206-65.206c0.971-0.971,2.103-1.115,2.692-1.115c0.589,0,1.722,0.144,2.692,1.115l38.36,38.36 c0.971,0.971,1.115,2.103,1.115,2.692S371.811,183.934,370.84,184.905z"></path> <path d="M423.9,73.756c-3.229,3.276-3.191,8.55,0.086,11.778c46.016,45.349,71.358,105.89,71.358,170.466 c0,63.931-24.896,124.035-70.102,169.241s-105.31,70.102-169.241,70.102c-35.385,0-69.471-7.555-101.311-22.455 c-4.166-1.95-9.124-0.153-11.074,4.013c-1.95,4.166-0.153,9.124,4.013,11.074C181.695,503.917,218.156,512,255.999,512 c68.381,0,132.668-26.629,181.019-74.982c48.352-48.352,74.98-112.64,74.98-181.019c0-69.072-27.106-133.825-76.323-182.331 C432.401,70.44,427.128,70.478,423.9,73.756z"></path> <path d="M116.34,470.563c1.405,0.916,2.982,1.354,4.542,1.354c2.72,0,5.387-1.332,6.984-3.78c2.513-3.852,1.427-9.013-2.426-11.526 c-68.115-44.424-108.78-119.419-108.78-200.611c0-63.931,24.896-124.035,70.102-169.24c45.206-45.206,105.31-70.102,169.241-70.102 c52.234,0,101.864,16.528,143.525,47.796c3.679,2.761,8.9,2.017,11.66-1.662c2.761-3.679,2.017-8.9-1.662-11.661 C364.958,17.681,311.87,0,256.002,0c-68.38,0-132.668,26.629-181.019,74.98C26.63,123.333,0.001,187.62,0.001,255.999 C0.001,342.841,43.493,423.051,116.34,470.563z"></path></svg></button>
            <h2 class="form-title">Create New Task</h2>
            <form id="taskForm" action="process_task.php" method="POST" enctype="multipart/form-data" onsubmit="submitTask(event)">
                <div class="form-group">
                    <label class="form-label">Task Title</label>
                    <input type="text" class="form-input" name="title" placeholder="Enter task title" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-input form-textarea" name="description" placeholder="Enter task description" required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <div class="custom-dropdown">
                        <div class="dropdown-header" onclick="toggleRoomDropdown()">
                            <span id="selectedRoom">Select Location</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="dropdown-menu" id="roomDropdownMenu">
                            <div class="dropdown-search">
                                <input type="text" id="roomSearch" placeholder="Search location..." class="dropdown-search-input" onkeyup="filterRooms()">
                            </div>
                            <div class="dropdown-list">
                                <div class="dropdown-item" data-value="COMPUTER LAB" onclick="selectRoom(this)">COMPUTER LAB</div>
                                <div class="dropdown-item" data-value="GRANDSTAND" onclick="selectRoom(this)">GRANDSTAND</div>
                                <div class="dropdown-item" data-value="CBAS" onclick="selectRoom(this)">CBAS</div>
                                <?php for ($i = 100; $i <= 500; $i++): ?>
                                    <div class="dropdown-item" data-value="ROOM <?php echo $i; ?>" onclick="selectRoom(this)">ROOM <?php echo $i; ?></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" id="selectedRoomValue" name="room" required>
                </div>
                <div class="deadline-group">
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-input" name="due_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Time</label>
                        <input type="time" class="form-input" name="due_time" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Attachments</label>
                    <div class="file-upload-container">
                        <div class="upload-box" id="fileUploadArea" onclick="if(!this.classList.contains('has-image')) { document.getElementById('fileInput').click(); }">
                            <div class="upload-icon"><svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#ff0000" stroke="#ff0000"><path d="M511.260755 514.037532m-432.292575 0a432.292575 432.292575 0 1 0 864.58515 0 432.292575 432.292575 0 1 0-864.58515 0Z" fill="#ff0000"></path><path d="M511.258707 960.021502c-245.91473 0-445.982946-200.061049-445.982946-445.982946 0-245.91473 200.067192-445.982946 445.982946-445.982946s445.982946 200.067192 445.982946 445.982946c0 245.921897-200.067192 445.982946-445.982946 445.982946z m0-864.586175c-230.821636 0-418.604252 187.781592-418.604252 418.603229 0 230.815493 187.781592 418.603228 418.604252 418.603228s418.603228-187.78876 418.603228-418.603228c0.001024-230.821636-187.780569-418.603228-418.603228-418.603229z" fill="#ff0000"></path><path d="M629.233846 291.053738H291.442814a8.66207 8.66207 0 0 0-8.663093 8.663094v428.642424a8.66207 8.66207 0 0 0 8.663093 8.66207h439.636905a8.66207 8.66207 0 0 0 8.66207-8.66207V405.022413l-110.507943-113.968675z" fill="#FFFFFF"></path><path d="M731.078695 750.709649H291.445886c-12.325532 0-22.358584-10.026909-22.358584-22.352441V299.71376c0-12.325532 10.033052-22.352441 22.358584-22.35244h337.791031c3.703393 0 7.25218 1.504088 9.826228 4.157998l110.504871 113.973795a13.683203 13.683203 0 0 1 3.86312 9.531348v323.332747c0 12.325532-10.026909 22.352441-22.352441 22.352441z m-434.612699-27.378694h429.586446V410.572896L623.441727 304.740013H296.465996v418.590942z" fill="#ff0000"></path><path d="M629.720191 291.053738V395.857616a8.66207 8.66207 0 0 0 8.66207 8.663094h101.360552l-110.022622-113.466972z" fill="#FFFFFF"></path><path d="M739.741789 418.213128h-101.360552c-12.325532 0-22.352441-10.033052-22.35244-22.358584V291.050666a13.689347 13.689347 0 0 1 8.549442-12.686963c5.166525-2.078487 11.095845-0.842658 14.966132 3.154591l110.023645 113.472091a13.684227 13.684227 0 0 1 2.780873 14.865791 13.688323 13.688323 0 0 1-12.6071 8.356952z m-96.334299-27.378693h63.995905l-63.995905-66.000672v66.000672z" fill="#ff0000"></path><path d="M478.915193 473.803958s-14.616987-14.813573-34.446573-2.715344c-17.742909 11.549426-14.6047 32.686515-14.604701 32.686515s-39.409346 8.072312-39.409346 50.423281c0.87747 42.282368 42.798407 42.716496 42.798407 42.716496l62.92492 0.067576V551.71344H466.004l45.269041-45.26597 45.262899 45.26597h-30.17902v45.269042l61.100357-0.0686s39.010031 0.033788 44.492937-40.191595c2.606812-43.999424-37.709697-52.670709-37.709697-52.670709s4.587006-65.131393-52.029757-72.556609c-48.532164-5.221815-63.296591 42.308989-63.29659 42.308989z" fill="#ff0000"></path></svg></div>
                            <div class="upload-title">Upload your file here</div>
                            <div class="file-name" id="fileFileName"></div>
                            <button type="button" class="remove-btn" onclick="removeSelectedFile(event)">Remove</button>
                        </div>
                        <input type="file" id="fileInput" name="attachments" accept="image/*" style="display: none;">
                    </div>
                </div>
                <button type="submit" class="submit-btn">Create Task</button>
            </form>
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

    <script>
        // Messaging System Variables
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

    <!-- File Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: 3px solid #ff0000;">
          <div class="modal-header" style="background: #ff0000; color: white;">
            <h5 class="modal-title" style="color: white; font-weight: bold;">File Preview</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center" style="padding: 30px;">
            <div id="fileInfo" style="margin-bottom: 20px;">
              <p id="fileName" style="font-size: 1.3em; font-weight: bold; color: #333;"></p>
              <p id="fileSize" style="font-size: 1em; color: #666;"></p>
            </div>
            <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(255, 0, 0, 0.2);">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" style="background: #ff0000; color: white; font-weight: bold;" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <nav class="bottom-nav">
        <a href="teacher_task_page.php" class="bottom-nav-item active" title="Home">
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

