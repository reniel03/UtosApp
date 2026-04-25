<?php
session_start();
include 'db_connect.php';

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$teacher_email = $_SESSION['email'];

// Fetch messages from students
$messages = [];
$query = "SELECT DISTINCT st.student_email,
                 s.first_name, s.middle_name, s.last_name, s.photo,
                  (SELECT COUNT(*) FROM messages m 
                  WHERE m.sender_email = st.student_email 
                  AND m.receiver_email = ? 
                  AND m.is_read = 0) as unread_count,
                  (SELECT message FROM messages m2 
                  WHERE ((m2.sender_email = ? AND m2.receiver_email = st.student_email) 
                  OR (m2.sender_email = st.student_email AND m2.receiver_email = ?))
                  ORDER BY m2.created_at DESC LIMIT 1) as last_message,
                  (SELECT created_at FROM messages m3 
                  WHERE ((m3.sender_email = ? AND m3.receiver_email = st.student_email) 
                  OR (m3.sender_email = st.student_email AND m3.receiver_email = ?))
                  ORDER BY m3.created_at DESC LIMIT 1) as last_message_time
          FROM student_todos st
          INNER JOIN tasks t ON st.task_id = t.id
          LEFT JOIN students s ON st.student_email = s.email
          WHERE t.teacher_email = ?
          ORDER BY last_message_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('ssssss', $teacher_email, $teacher_email, $teacher_email, $teacher_email, $teacher_email, $teacher_email);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Count unread messages
$unread_count = 0;
foreach ($messages as $msg) {
    $unread_count += $msg['unread_count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Teacher Messages</title>
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

        /* Main wrapper */
        .main-wrapper {
            padding-top: 40px;
            padding-bottom: 60px;
            min-height: 100vh;
        }

        /* Page header */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease;
            padding: 0 20px;
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
            font-size: 3.5em;
            color: #ff0000;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 4px 20px rgba(255, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-title-icon {
            font-size: 1.1em;
        }

        .page-subtitle {
            font-size: 1.2em;
            color: #666;
            font-weight: 500;
        }

        /* Tab container */
        .tab-container {
            max-width: 1200px;
            margin: 0 auto 40px;
            padding: 0 20px;
        }

        .tabs-wrapper {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
            animation: slideInUp 0.6s ease;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-btn {
            padding: 12px 28px;
            border: none;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f0f0f0;
            color: #666;
        }

        .tab-btn.active {
            background: #ff0000;
            color: white;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.25);
        }

        .tab-btn:hover {
            transform: translateY(-2px);
        }

        /* Messages container */
        .messages-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Message item */
        .message-item {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border-left: 4px solid transparent;
            animation: messageSlide 0.5s ease backwards;
        }

        .message-item:nth-child(1) { animation-delay: 0.05s; }
        .message-item:nth-child(2) { animation-delay: 0.1s; }
        .message-item:nth-child(3) { animation-delay: 0.15s; }
        .message-item:nth-child(4) { animation-delay: 0.2s; }
        .message-item:nth-child(5) { animation-delay: 0.25s; }

        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .message-item:hover {
            background: #f9f9f9;
            transform: translateX(8px);
            box-shadow: 0 8px 20px rgba(255, 0, 0, 0.1);
            border-left-color: #ff0000;
        }

        /* Pin icon */
        .message-pin {
            font-size: 1.2em;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 0, 0, 0.1);
            border-radius: 8px;
            flex-shrink: 0;
            color: #ff0000;
        }

        /* Message content */
        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-title {
            font-size: 1em;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-preview {
            font-size: 0.85em;
            color: #999;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Message meta */
        .message-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            flex-shrink: 0;
        }

        .message-date {
            font-size: 0.8em;
            color: #999;
            font-weight: 500;
        }

        .message-unread {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ff0000;
            flex-shrink: 0;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 1.5em;
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .empty-text {
            font-size: 1em;
            color: #999;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5em;
                gap: 10px;
            }

            .page-header {
                margin-bottom: 30px;
            }

            .tab-container {
                padding: 0 15px;
            }

            .tabs-wrapper {
                gap: 10px;
            }

            .tab-btn {
                padding: 10px 20px;
                font-size: 0.95em;
            }

            .messages-container {
                padding: 0 15px;
            }

            .message-item {
                padding: 14px 15px;
                gap: 12px;
            }

            .message-pin {
                width: 28px;
                height: 28px;
                font-size: 1em;
            }

            .message-title {
                font-size: 0.95em;
            }

            .message-preview {
                font-size: 0.8em;
            }

            .message-date {
                font-size: 0.75em;
            }

            .empty-state {
                padding: 40px 15px;
            }

            .empty-icon {
                font-size: 3em;
                margin-bottom: 15px;
            }

            .empty-title {
                font-size: 1.3em;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 2em;
            }

            .tab-btn {
                padding: 8px 16px;
                font-size: 0.9em;
            }

            .message-item {
                padding: 12px 12px;
                gap: 10px;
            }

            .message-meta {
                gap: 6px;
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

        .bottom-nav-item:hover {
            color: #ff0000;
            transform: scale(1.05);
        }

        .bottom-nav-item.active {
            color: #ff0000;
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

        .bottom-nav-item:hover .bottom-nav-icon {
            transform: scale(1.15);
            color: #ff0000;
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
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
            opacity: 0.9;
            transition: opacity 0.3s ease;
        }

        .bottom-nav-item:hover .bottom-nav-label {
            opacity: 1;
        }

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
                font-size: 28px;
            }

            .bottom-nav-label {
                font-size: 10px;
            }

            body {
                padding-bottom: 85px;
            }
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
    <div class="main-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                Messages
            </div>
            <div class="page-subtitle">Stay connected with your students</div>
        </div>

        <!-- Tab Container -->
        <div class="tab-container">
            <div class="tabs-wrapper">
                <button class="tab-btn active" onclick="switchTab('chats')">Chats</button>
                <button class="tab-btn" onclick="switchTab('notifications')">Notifications</button>
            </div>
        </div>

        <!-- Messages Container -->
        <div class="messages-container">
            <!-- Chats Tab -->
            <div id="chats-tab">
                <?php if (count($messages) > 0): ?>
                    <?php foreach ($messages as $index => $msg): ?>
                        <?php 
                            $student_name = trim(($msg['first_name'] ?? '') . ' ' . ($msg['middle_name'] ? $msg['middle_name'] . ' ' : '') . ($msg['last_name'] ?? ''));
                            $last_message = $msg['last_message'] ?? 'No messages yet';
                            $message_date = '';
                            if ($msg['last_message_time']) {
                                $msg_time = strtotime($msg['last_message_time']);
                                $now = time();
                                $diff = $now - $msg_time;
                                
                                if ($diff < 3600) {
                                    $message_date = floor($diff / 60) . 'm ago';
                                } elseif ($diff < 86400) {
                                    $message_date = floor($diff / 3600) . 'h ago';
                                } elseif ($diff < 604800) {
                                    $days = floor($diff / 86400);
                                    $message_date = $days > 1 ? $days . 'd ago' : 'Yesterday';
                                } else {
                                    $message_date = date('M d', $msg_time);
                                }
                            }
                            $unread = ($msg['unread_count'] ?? 0) > 0;
                        ?>
                        <div class="message-item" onclick="openChat('<?php echo $msg['student_email']; ?>')">
                            <div class="message-pin">📌</div>
                            <div class="message-content">
                                <div class="message-title"><?php echo htmlspecialchars($student_name); ?></div>
                                <div class="message-preview"><?php echo htmlspecialchars(substr($last_message, 0, 50)); ?></div>
                            </div>
                            <div class="message-meta">
                                <div class="message-date"><?php echo $message_date; ?></div>
                                <?php if ($unread): ?>
                                    <div class="message-unread"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 120px; height: 120px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M7 8.5H12M7 12H15M9.68375 18H16.2C17.8802 18 18.7202 18 19.362 17.673C19.9265 17.3854 20.3854 16.9265 20.673 16.362C21 15.7202 21 14.8802 21 13.2V7.8C21 6.11984 21 5.27976 20.673 4.63803C20.3854 4.07354 19.9265 3.6146 19.362 3.32698C18.7202 3 17.8802 3 16.2 3H7.8C6.11984 3 5.27976 3 4.63803 3.32698C4.07354 3.6146 3.6146 4.07354 3.32698 4.63803C3 5.27976 3 6.11984 3 7.8V20.3355C3 20.8684 3 21.1348 3.10923 21.2716C3.20422 21.3906 3.34827 21.4599 3.50054 21.4597C3.67563 21.4595 3.88367 21.2931 4.29976 20.9602L6.68521 19.0518C7.17252 18.662 7.41617 18.4671 7.68749 18.3285C7.9282 18.2055 8.18443 18.1156 8.44921 18.0613C8.74767 18 9.0597 18 9.68375 18Z" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg></div>
                        <div class="empty-title">No Messages Yet</div>
                        <div class="empty-text">Start a conversation with your students</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" style="display: none;">
                <div class="empty-state">
                    <div class="empty-icon"><svg fill="#ff0000" viewBox="0 0 24 24" id="notification-copy" xmlns="http://www.w3.org/2000/svg" class="icon line" style="width: 120px; height: 120px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path id="primary" d="M19.38,14.38a2.12,2.12,0,0,1,.62,1.5h0A2.12,2.12,0,0,1,17.88,18H6.12A2.12,2.12,0,0,1,4,15.88H4a2.12,2.12,0,0,1,.62-1.5L6,13V9a6,6,0,0,1,6-6h0a6,6,0,0,1,6,6v4ZM15,18H9a3,3,0,0,0,3,3h0A3,3,0,0,0,15,18Z" style="fill: none; stroke: #ff0000; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.5;"></path></g></svg></div>
                    <div class="empty-title">No Notifications</div>
                    <div class="empty-text">You're all caught up!</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
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
        <a href="teacher_record.php" class="bottom-nav-item" title="History">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </span>
            <span class="bottom-nav-label">History</span>
        </a>
        <a href="teacher_message.php" class="bottom-nav-item active" title="Messages">
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

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.getElementById('chats-tab').style.display = 'none';
            document.getElementById('notifications-tab').style.display = 'none';
            
            // Show selected tab
            document.getElementById(tabName + '-tab').style.display = 'block';
            
            // Update button states
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function openChat(studentEmail) {
            Swal.fire({
                title: 'Opening Chat',
                text: 'Chat interface coming soon!',
                icon: 'info',
                confirmButtonColor: '#ff0000',
                confirmButtonText: 'OK'
            });
        }

        // Set active navigation item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = 'teacher_message.php';
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
