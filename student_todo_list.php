<?php
session_start();

// Check if the user is logged in as a student
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

include 'db_connect.php';

// Add teacher_email column if it doesn't exist
$alter_query = "ALTER TABLE student_todos ADD COLUMN IF NOT EXISTS teacher_email VARCHAR(255) DEFAULT NULL";
$conn->query($alter_query);

// Get student email from session
$student_email = $_SESSION['email'];

// Handle AJAX request to get updated tasks
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_tasks') {
    header('Content-Type: application/json');
    $query = "SELECT st.*, 
              tk.teacher_email AS task_teacher_email,
              tk.attachments,
              t.first_name AS teacher_first_name, 
              t.middle_name AS teacher_middle_name, 
              t.last_name AS teacher_last_name, 
              t.photo AS teacher_photo,
              t.email AS teacher_email_info
              FROM student_todos st 
              LEFT JOIN tasks tk ON tk.id = st.task_id
              LEFT JOIN teachers t ON t.email = COALESCE(st.teacher_email, tk.teacher_email)
              WHERE st.student_email = ? 
              ORDER BY st.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $student_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
    echo json_encode($tasks);
    exit();
}

// Handle task completion toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_complete') {
        $todo_id = isset($_POST['todo_id']) ? intval($_POST['todo_id']) : 0;
        $is_completed = isset($_POST['is_completed']) ? intval($_POST['is_completed']) : 0;
        
        $new_status = $is_completed ? 0 : 1;
        $new_status_text = $new_status ? 'completed' : 'pending';
        $update_query = "UPDATE student_todos SET is_completed = ?, status = ? WHERE id = ? AND student_email = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('isis', $new_status, $new_status_text, $todo_id, $student_email);
        $stmt->execute();
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'new_status' => $new_status]);
        exit();
    } elseif ($_POST['action'] === 'pickup') {
        $todo_id = isset($_POST['todo_id']) ? intval($_POST['todo_id']) : 0;
        
        $update_query = "UPDATE student_todos SET status = 'ongoing' WHERE id = ? AND student_email = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('is', $todo_id, $student_email);
        $stmt->execute();
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } elseif ($_POST['action'] === 'delete') {
        $todo_id = isset($_POST['todo_id']) ? intval($_POST['todo_id']) : 0;
        
        $delete_query = "DELETE FROM student_todos WHERE id = ? AND student_email = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('is', $todo_id, $student_email);
        $stmt->execute();
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    }
}

// Fetch student's todo tasks with teacher info
$query = "SELECT st.*, 
          tk.teacher_email AS task_teacher_email,
          tk.attachments,
          t.first_name AS teacher_first_name, 
          t.middle_name AS teacher_middle_name, 
          t.last_name AS teacher_last_name, 
          t.photo AS teacher_photo,
          t.email AS teacher_email_info
          FROM student_todos st 
          LEFT JOIN tasks tk ON tk.id = st.task_id
          LEFT JOIN teachers t ON t.email = COALESCE(st.teacher_email, tk.teacher_email)
          WHERE st.student_email = ? 
          ORDER BY st.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();
$todos = [];
while ($row = $result->fetch_assoc()) {
    $todos[] = $row;
}
$stmt->close();

// Separate completed and pending tasks
$pending_tasks = array_filter($todos, function($task) { return !$task['is_completed']; });
$completed_tasks = array_filter($todos, function($task) { return $task['is_completed']; });

// Fetch notifications for students
$notifications = [];

// 1. Get notifications when teacher rated their task
$rating_query = "SELECT st.*, t.title as task_title, t.teacher_email,
                 tr.first_name as teacher_first_name, tr.middle_name as teacher_middle_name, 
                 tr.last_name as teacher_last_name, tr.photo as teacher_photo
                 FROM student_todos st
                 INNER JOIN tasks t ON st.task_id = t.id
                 LEFT JOIN teachers tr ON t.teacher_email = tr.email
                 WHERE st.student_email = ? AND st.rating IS NOT NULL
                 ORDER BY st.rated_at DESC
                 LIMIT 10";

$stmt = $conn->prepare($rating_query);
if ($stmt) {
    $stmt->bind_param('s', $student_email);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['notification_type'] = 'rating';
        $row['notification_date'] = $row['rated_at'];
        $notifications[] = $row;
    }
    $stmt->close();
}

// 2. Get notifications when application is approved (status = ongoing)
$approved_query = "SELECT st.*, t.title as task_title, t.teacher_email,
                   tr.first_name as teacher_first_name, tr.middle_name as teacher_middle_name, 
                   tr.last_name as teacher_last_name, tr.photo as teacher_photo
                   FROM student_todos st
                   INNER JOIN tasks t ON st.task_id = t.id
                   LEFT JOIN teachers tr ON t.teacher_email = tr.email
                   WHERE st.student_email = ? AND st.status = 'ongoing'
                   ORDER BY st.created_at DESC
                   LIMIT 10";

$stmt = $conn->prepare($approved_query);
if ($stmt) {
    $stmt->bind_param('s', $student_email);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['notification_type'] = 'approved';
        $row['notification_date'] = $row['created_at'];
        $notifications[] = $row;
    }
    $stmt->close();
}

// Sort all notifications by date
usort($notifications, function($a, $b) {
    return strtotime($b['notification_date']) - strtotime($a['notification_date']);
});

// Limit to 20 notifications
$notifications = array_slice($notifications, 0, 20);
$notification_count = count($notifications);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List</title>
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
            font-size: 3em;
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
            margin: 0 8px;
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

        .profile-pic:active {
            transform: translateY(0);
            box-shadow: 0 3px 15px rgba(255, 0, 0, 0.2);
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

        /* Notification dropdown styles */
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

        .notification-item.rating-notification::before {
            background: linear-gradient(180deg, #ffc107, #ffca2c);
        }

        .notification-item.approved-notification::before {
            background: linear-gradient(180deg, #28a745, #20c997);
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
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255, 0, 0, 0.1);
            border: none;
            color: #ff0000;
            font-size: 1.2em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 5;
        }

        .notification-item:hover .notification-dismiss {
            opacity: 1;
        }

        .notification-dismiss:hover {
            background: #ff0000;
            color: white;
        }

        @keyframes slideOutRight {
            to { transform: translateX(100%); opacity: 0; }
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
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-teacher-name {
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
        }

        .notification-task-title {
            font-size: 0.95em;
            color: #666;
            margin-bottom: 6px;
        }

        .notification-message {
            font-size: 0.85em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .notification-message.rating-message {
            color: #ffc107;
        }

        .notification-message.approved-message {
            color: #28a745;
        }

        .notification-time {
            font-size: 0.8em;
            color: #999;
            font-weight: 600;
            white-space: nowrap;
        }

        .notification-time-text {
            background: #f5f5f5;
            padding: 4px 10px;
            border-radius: 10px;
        }

        .notification-count-badge {
            background: white;
            color: #ff0000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 800;
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
            background: white;
            border-radius: 24px;
            width: 500px;
            max-width: 95%;
            box-shadow: 0 30px 80px rgba(255, 0, 0, 0.3);
            animation: notifDetailSlide 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes notifDetailSlide {
            from { opacity: 0; transform: scale(0.8) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .notif-detail-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            padding: 25px 30px;
            border-radius: 24px 24px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notif-detail-header h3 {
            color: white;
            margin: 0;
            font-size: 1.5em;
            font-weight: 800;
        }

        .notif-detail-close {
            width: 40px;
            height: 40px;
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
        }

        .notif-detail-close:hover {
            background: white;
            color: #ff0000;
            transform: rotate(90deg);
        }

        .notif-detail-body {
            padding: 30px;
        }

        .notif-detail-teacher {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 25px;
        }

        .notif-detail-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.25);
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
            border-radius: 16px;
            padding: 20px;
            border: 2px solid #ffeeee;
        }

        .notif-detail-task-label {
            font-size: 0.85em;
            color: #999;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .notif-detail-task-title {
            font-size: 1.2em;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .notif-detail-task-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.95em;
        }

        .notif-detail-task-action.rating {
            background: linear-gradient(135deg, #fff8e1, #ffecb3);
            color: #ff8f00;
        }

        .notif-detail-task-action.approved {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
        }

        .notif-detail-time {
            text-align: center;
            margin-top: 20px;
            color: #999;
            font-size: 0.9em;
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

        .main-content {
            width: 100%;
            min-height: calc(100vh - 200px);
            margin-top: 200px;
            padding: 80px 100px;
            background: linear-gradient(180deg, #ffffff 0%, #fff5f5 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .todo-header {
            text-align: center;
            margin-bottom: 80px;
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

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .todo-title {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 5.5em;
            background: linear-gradient(135deg, #ff0000, #ff4444);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: none;
            letter-spacing: -0.02em;
        }

        .todo-subtitle {
            font-size: 2.5em;
            color: #666;
            font-weight: 500;
            letter-spacing: 0.03em;
        }

        .todo-container {
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
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 3.2em;
            color: #ff0000;
            font-weight: 800;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            background: transparent;
            text-shadow: 0 4px 15px rgba(255, 0, 0, 0.15);
        }

        .task-count {
            background: linear-gradient(135deg, #ff0000, #ff3333);
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 0.9em;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(255, 0, 0, 0.3);
        }

        .task-list {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .task-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.12);
            transition: all 0.3s ease;
            animation: slideIn 0.6s ease-out forwards;
            opacity: 0;
            border-left: 8px solid #ff0000;
        }

        .task-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 18px 45px rgba(255, 0, 0, 0.25);
        }

        .task-card.completed {
            opacity: 0.7;
            border-left-color: #28a745;
        }

        .task-card.ongoing {
            border-left-color: #ff9800;
        }

        .task-card-header {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #f5f5f5;
            color: white;
            padding: 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
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

        .task-card-title {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 2.6em;
            font-weight: 900;
            margin-bottom: 15px;
            letter-spacing: 0.02em;
        }

        .task-card-room {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 1.7em;
            margin-bottom: 10px;
            opacity: 0.95;
        }

        .task-card-due {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 1.6em;
            opacity: 0.9;
        }

        .task-status-badge {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            padding: 12px 22px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 700;
            border: 3px solid white;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .task-card-body {
            padding: 32px;
            background: white;
        }

        .task-card-description {
            margin-bottom: 20px;
        }

        .task-desc-label {
            font-weight: bold;
            color: #ff0000;
            font-size: 1.7em;
            margin-bottom: 8px;
        }

        .task-desc-text {
            color: #555;
            font-size: 1.5em;
            line-height: 1.6;
            margin-top: 8px;
        }

        .task-card-footer {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 25px 32px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
            background: #fafafa;
        }

        .checkbox-large {
            width: 52px;
            height: 52px;
            border: 4px solid #ff0000;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
            background: white;
            font-size: 1.8em;
            color: white;
        }

        .checkbox-large:hover {
            transform: scale(1.1);
            background: #fff5f5;
        }

        .checkbox-large.checked {
            background: #28a745;
            border-color: #28a745;
        }

        .pickup-btn {
            background: linear-gradient(135deg, #ff9800, #ffb300);
            color: white;
            border: none;
            padding: 18px 42px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.5em;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 6px 18px rgba(255, 152, 0, 0.35);
        }

        .pickup-btn:hover {
            background: linear-gradient(135deg, #e68900, #ffa000);
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(255, 152, 0, 0.45);
        }

        .delete-btn-large {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            border: none;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            transition: all 0.3s ease;
            font-weight: bold;
            margin-left: auto;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
        }

        .delete-btn-large:hover {
            background: linear-gradient(135deg, #ff5252, #ff4444);
            transform: scale(1.15);
            box-shadow: 0 6px 18px rgba(255, 107, 107, 0.45);
        }

        .empty-state {
            text-align: center;
            padding: 80px 50px;
            color: #999;
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 20px;
            border: 2px dashed #ffcccc;
        }

        .empty-icon {
            font-size: 5em;
            margin-bottom: 25px;
        }

        .empty-text {
            font-size: 2em;
            margin-bottom: 12px;
            color: #666;
        }

        .empty-subtext {
            font-size: 1.4em;
            color: #999;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 40px;
            color: #ff0000;
            text-decoration: none;
            font-size: 1.3em;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            text-decoration: underline;
            transform: translateX(-5px);
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

        /* Task Modal Styles */
        .task-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .task-modal.show {
            display: flex;
            opacity: 1;
        }

        .task-modal-content {
            background: white;
            border-radius: 30px;
            padding: 45px;
            max-width: 1100px;
            width: 92%;
            max-height: 95vh;
            overflow-y: auto;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
            position: relative;
            animation: slideInUp 0.4s ease-out;
        }

        @keyframes slideInUp {
            0% {
                opacity: 0;
                transform: translateY(50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .task-modal-close {
            position: absolute;
            top: 25px;
            right: 25px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ff0000, #ff3333);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 2.5em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.3);
        }

        .task-modal-close:hover {
            background: linear-gradient(135deg, #e60000, #ff1a1a);
            transform: scale(1.12);
            box-shadow: 0 8px 28px rgba(255, 0, 0, 0.45);
        }

        .task-modal-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            padding: 35px;
            margin: -45px -45px 30px -45px;
            border-radius: 30px 30px 0 0;
        }

        .task-modal-title {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-size: 3em;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: 0.02em;
        }

        .task-modal-subtitle {
            font-size: 1.6em;
            opacity: 0.95;
        }

        .task-modal-body {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .modal-field {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .modal-field-label {
            font-family: 'Space Grotesk', Arial, sans-serif;
            font-weight: bold;
            color: #ff0000;
            font-size: 1.6em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-field-value {
            color: #333;
            font-size: 1.5em;
            background: linear-gradient(135deg, #f5f5f5, #fafafa);
            padding: 16px 20px;
            border-radius: 14px;
            border-left: 5px solid #ff0000;
        }

        @media (max-width: 900px) {
            .main-content {
                padding: 40px 20px;
                margin-top: 150px;
            }

            .todo-title {
                font-size: 2.5em;
            }

            .nav-links {
                gap: 30px;
            }

            .nav-links a {
                font-size: 1.5em;
            }
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
</head>
<body>
    <nav class="nav-bar">
        <div class="nav-links">
            <a href="student_task_page.php">Home</a>
            <a href="student_todo_list.php">To-Do List</a>
            <a href="student_history.php">History</a>
        
        </div>
        <div class="nav-right">
            <div class="icon-wrapper">
                <button class="icon-btn notification-btn" onclick="toggleNotificationDropdown(); event.stopPropagation();">
                    <svg width="55" height="55" viewBox="0 0 24 24" fill="none">
                        <path d="M12 3C13.1046 3 14 3.89543 14 5V5.17071C16.9004 5.58254 19 8.02943 19 11V14.8293L20.8536 16.6829C21.5062 17.3355 20.9534 18.5 20.0294 18.5H3.97056C3.04662 18.5 2.49381 17.3355 3.14645 16.6829L5 14.8293V11C5 8.02943 7.09962 5.58254 10 5.17071V5C10 3.89543 10.8954 3 12 3Z" fill="white"/>
                        <path d="M12 22C13.1046 22 14 21.1046 14 20H10C10 21.1046 10.8954 22 12 22Z" fill="white"/>
                    </svg>
                </button>
                <?php if ($notification_count > 0): ?>
                <span class="notification-dot"><?php echo $notification_count > 99 ? '99+' : $notification_count; ?></span>
                <?php endif; ?>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <span class="notification-count-badge"><?php echo $notification_count; ?> new</span>
                    </div>
                    <div class="notification-actions" style="padding: 10px 15px; background: #fff5f5; border-bottom: 1px solid #fee;">
                        <button type="button" onclick="markAllRead()">✓ Mark all read</button>
                    </div>
                    <div class="notification-list">
                        <?php if (empty($notifications)): ?>
                        <div class="notification-empty">No notifications yet.</div>
                        <?php else: ?>
                        <?php foreach ($notifications as $notif): 
                            $teacher_photo = !empty($notif['teacher_photo']) ? htmlspecialchars($notif['teacher_photo']) : 'profile-default.png';
                            $teacher_name = htmlspecialchars($notif['teacher_first_name'] . ' ' . $notif['teacher_last_name']);
                            $task_title = htmlspecialchars($notif['task_title']);
                            $notif_type = $notif['notification_type'];
                            
                            // Calculate time ago
                            $time_diff = time() - strtotime($notif['notification_date']);
                            if ($time_diff < 60) {
                                $time_ago = 'Just now';
                            } elseif ($time_diff < 3600) {
                                $mins = floor($time_diff / 60);
                                $time_ago = $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
                            } elseif ($time_diff < 86400) {
                                $hours = floor($time_diff / 3600);
                                $time_ago = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                            } elseif ($time_diff < 604800) {
                                $days = floor($time_diff / 86400);
                                $time_ago = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                            } else {
                                $time_ago = date('M j', strtotime($notif['notification_date']));
                            }
                            
                            $notif_action = $notif_type == 'rating' ? 
                                'Rated you ' . $notif['rating'] . ' star' . ($notif['rating'] > 1 ? 's' : '') . ' ' . str_repeat('⭐', $notif['rating']) : 
                                'Approved your application ✓';
                        ?>
                        <div class="notification-item <?php echo $notif_type == 'rating' ? 'rating-notification' : 'approved-notification'; ?>" onclick="viewNotification('<?php echo $teacher_photo; ?>', '<?php echo addslashes($teacher_name); ?>', '<?php echo addslashes($task_title); ?>', '<?php echo $time_ago; ?>', '<?php echo $notif_type; ?>', '<?php echo addslashes($notif_action); ?>')">
                            <button class="notification-dismiss" onclick="dismissNotification(this, event)">×</button>
                            <img src="<?php echo $teacher_photo; ?>" alt="Teacher" class="notification-avatar">
                            <div class="notification-content">
                                <div class="notification-teacher-name">
                                    <?php echo $teacher_name; ?>
                                    <span class="notification-new-badge">New</span>
                                </div>
                                <div class="notification-task-title"><?php echo $task_title; ?></div>
                                <?php if ($notif_type == 'rating'): ?>
                                <div class="notification-message rating-message">
                                    Rated you <?php echo $notif['rating']; ?> star<?php echo $notif['rating'] > 1 ? 's' : ''; ?>
                                    <?php for ($i = 0; $i < $notif['rating']; $i++): ?>⭐<?php endfor; ?>
                                </div>
                                <?php else: ?>
                                <div class="notification-message approved-message">
                                    Approved your application
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-time">
                                <span class="notification-time-text"><?php echo $time_ago; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
                <span class="message-dot" id="messageDot" style="display: none;">0</span>
                <div class="message-dropdown" id="messageDropdown">
                    <div class="message-header">
                        <h4>Messages</h4>
                        <span class="message-pill" id="messagePill" style="display:none;">0 new</span>
                    </div>
                    <div class="message-search">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7"></circle>
                            <line x1="16.65" y1="16.65" x2="21" y2="21"></line>
                        </svg>
                        <input type="text" id="messageSearchInput" placeholder="Search contacts..." aria-label="Search contacts" oninput="filterMessageContacts()">
                    </div>
                    <div class="message-list" id="messageList">
                        <div class="message-empty">Loading contacts...</div>
                    </div>
                </div>
            </div>
            <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-pic" onclick="toggleProfileDropdown(); event.stopPropagation();" style="cursor: pointer;">
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-info">
                    <div class="profile-image-container">
                        <img src="<?php echo isset($_SESSION['photo']) ? $_SESSION['photo'] : 'profile-default.png'; ?>" alt="Profile" class="profile-info-pic" onclick="openProfileModal()" style="cursor: pointer;">
                    </div>
                    <div class="profile-details">
                        <div class="detail-group">
                            <p class="detail-value"><?php
                                $first = isset($_SESSION['first_name']) ? trim($_SESSION['first_name']) : '';
                                $middle = isset($_SESSION['middle_name']) ? trim($_SESSION['middle_name']) : '';
                                $last = isset($_SESSION['last_name']) ? trim($_SESSION['last_name']) : '';
                                $full_name = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
                                echo $full_name !== '' ? $full_name : 'Not set';
                            ?></p>
                        </div>
                    </div>
                </div>
                <div class="profile-menu">
                    <a href="#" onclick="openSettingsModal(); return false;">⚙️ Settings</a>
                    <a href="logout.php">📤 Log Out</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="todo-header">
            <h1 class="todo-title">📋 To-Do List</h1>
            <p class="todo-subtitle">Manage and track your assigned tasks</p>
        </div>

        <div class="todo-container" style="align-items: center; max-width: 100%;">
            <!-- View All Accepted Tasks Button -->
            <div style="text-align: center; margin-bottom: 50px; width: 100%; display: flex; justify-content: center;">
                <button onclick="openAllTasksModal()" style="background: linear-gradient(135deg, #ff0000, #ff4444); color: white; border: none; padding: 28px 70px; border-radius: 20px; font-size: 2em; font-weight: 700; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 8px 28px rgba(255, 0, 0, 0.35); font-family: 'Space Grotesk', Arial, sans-serif; letter-spacing: 0.02em;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 12px 35px rgba(255, 0, 0, 0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 28px rgba(255, 0, 0, 0.35)';">
                    📋 View All Accepted Tasks
                </button>
            </div>

            <!-- Pending Tasks Section -->
            <div class="task-section" style="width: 100%; max-width: 950px; margin: 0 auto;">
                <div class="section-header">
                    📌 Pending Tasks
                    <span class="task-count"><?php echo count($pending_tasks); ?></span>
                </div>

                <?php if (count($pending_tasks) > 0): ?>
                    <div class="task-list">
                        <?php foreach ($pending_tasks as $index => $task): 
                            $due_date = new DateTime($task['due_date'] . ' ' . $task['due_time']);
                            $now = new DateTime();
                            $is_urgent = $due_date < $now;
                            $task_status = isset($task['status']) ? $task['status'] : 'pending';
                            $is_approved = ($task_status === 'ongoing');
                            $status_icon = $is_approved ? '✅' : '⏳';
                            $status_text = $is_approved ? 'Approved - In Progress' : 'Pending Approval';
                            
                            // Get teacher info
                            $teacher_photo = !empty($task['teacher_photo']) ? $task['teacher_photo'] : 'profile-default.png';
                            $teacher_name = trim(($task['teacher_first_name'] ?? '') . ' ' . ($task['teacher_middle_name'] ?? '') . ' ' . ($task['teacher_last_name'] ?? ''));
                            if (empty($teacher_name)) $teacher_name = 'Unknown Teacher';
                            $teacher_email = !empty($task['teacher_email_info']) ? $task['teacher_email_info'] : (!empty($task['task_teacher_email']) ? $task['task_teacher_email'] : 'No email available');
                            
                            // Format time with AM/PM
                            $formatted_time = date('g:i A', strtotime($task['due_time']));
                        ?>
                            <div class="task-card <?php echo $is_approved ? 'ongoing approved' : ''; ?>" style="animation-delay: <?php echo ($index * 0.1); ?>s;" data-task-id="<?php echo $task['id']; ?>" data-status="<?php echo $task_status; ?>">
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
                                <!-- Task Details Section -->
                                <div style="padding: 25px 30px; background: linear-gradient(135deg, #f5f5f5, #ffffff); border-bottom: 3px solid #e0e0e0;">
                                    <div class="task-card-title">📌 <?php echo htmlspecialchars($task['title']); ?></div>
                                    <div class="task-card-room">🏛️ Location: <?php echo htmlspecialchars($task['room']); ?></div>
                                    <div class="task-card-due">📅 Due: <?php echo htmlspecialchars($task['due_date']); ?> | ⏰ <?php echo htmlspecialchars($formatted_time); ?></div>
                                    <div class="task-status-badge <?php echo $is_approved ? 'status-approved' : ''; ?>" style="margin-top: 12px; display: inline-flex;">
                                        <span><?php echo $status_icon; ?></span>
                                        <span><?php echo $status_text; ?></span>
                                    </div>
                                </div>
                                <!-- Teacher Info Section -->
                                <div style="padding: 25px 30px; display: flex; align-items: center; gap: 20px; background: linear-gradient(135deg, #fff5f5, #ffffff); border-bottom: 3px solid #f0f0f0;">
                                    <img src="<?php echo htmlspecialchars($teacher_photo); ?>" alt="Teacher" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 4px solid #ff0000; box-shadow: 0 6px 18px rgba(255,0,0,0.25); cursor: pointer; transition: all 0.3s ease;" onclick="openTeacherProfileModal('<?php echo htmlspecialchars($teacher_photo); ?>')" onmouseover="this.style.transform='scale(1.08)'; this.style.boxShadow='0 8px 24px rgba(255,0,0,0.35)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 6px 18px rgba(255,0,0,0.25)';">
                                    <div style="flex: 1;">
                                        <div style="font-size: 1.1em; color: #888; font-weight: 600; margin-bottom: 4px;">👤 Posted by:</div>
                                        <div style="font-size: 1.6em; color: #333; font-weight: 800; margin-bottom: 6px; font-family: 'Space Grotesk', sans-serif;"><?php echo htmlspecialchars($teacher_name); ?></div>
                                        <div style="font-size: 1.2em; color: #666;">📧 <?php echo htmlspecialchars($teacher_email); ?></div>
                                    </div>
                                </div>
                                <div class="task-card-body">
                                    <div class="task-card-description">
                                        <div class="task-desc-label">📝 Description:</div>
                                        <div class="task-desc-text"><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
                                    </div>
                                    <?php if (!empty($task['attachments'])): 
                                        $img_types = ['jpg','jpeg','png','gif','webp'];
                                        $ext = strtolower(pathinfo($task['attachments'], PATHINFO_EXTENSION));
                                        if (in_array($ext, $img_types)):
                                    ?>
                                        <img src="<?php echo $task['attachments']; ?>" alt="Task Image" style="max-width:220px; max-height:220px; margin:15px 0 10px 0; border-radius:12px; border:2px solid #eee; box-shadow: 0 4px 12px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.3s ease;" onclick="openImageModal('<?php echo htmlspecialchars($task['attachments']); ?>')" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 6px 16px rgba(0,0,0,0.15)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)';" />
                                    <?php else: ?>
                                        <a href="<?php echo $task['attachments']; ?>" target="_blank" style="font-size: 1.3em; color: #ff0000; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 15px;">📁 Download File</a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="task-card-footer">
                                    <?php if ($task_status === 'pending'): ?>
                                        <div style="background: linear-gradient(135deg, #ffc107, #ffca2c); color: #333; padding: 14px 30px; border-radius: 12px; font-weight: 700; font-size: 1.3em; display: flex; align-items: center; gap: 10px;">
                                            ⏳ Waiting for Teacher Approval
                                        </div>
                                    <?php else: ?>
                                        <div style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 14px 30px; border-radius: 12px; font-weight: 700; font-size: 1.3em; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
                                            ✅ Approved - Task In Progress
                                        </div>
                                    <?php endif; ?>
                                    <div class="checkbox-large" onclick="toggleTask(this, <?php echo $task['id']; ?>, 0)" title="Mark as complete"></div>
                                    <button class="delete-btn-large" onclick="deleteTask(<?php echo $task['id']; ?>)" title="Delete Task">×</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">✨</div>
                        <div class="empty-text">No pending tasks</div>
                        <div class="empty-subtext">Great! You're all caught up</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div class="task-modal" id="taskModal" onclick="if(event.target === this) closeTaskModal()">
        <div class="task-modal-content">
            <button class="task-modal-close" onclick="closeTaskModal()">×</button>
            <div class="task-modal-header">
                <div class="task-modal-title" id="modalTaskTitle">Task Details</div>
                <div class="task-modal-subtitle" id="modalTaskRoom">Room: N/A</div>
            </div>
            <div class="task-modal-body">
                <div class="modal-field">
                    <div class="modal-field-label">📝 Description</div>
                    <div class="modal-field-value" id="modalTaskDescription">No description provided</div>
                </div>
                <div class="modal-field">
                    <div class="modal-field-label">📍 Room</div>
                    <div class="modal-field-value" id="modalTaskRoomValue">N/A</div>
                </div>
                <div class="modal-field">
                    <div class="modal-field-label">📅 Due Date</div>
                    <div class="modal-field-value" id="modalTaskDueDate">N/A</div>
                </div>
                <div class="modal-field">
                    <div class="modal-field-label">⏰ Due Time</div>
                    <div class="modal-field-value" id="modalTaskDueTime">N/A</div>
                </div>
            </div>
        </div>
    </div>

    <!-- All Accepted Tasks Modal -->
    <div class="task-modal" id="allTasksModal" onclick="if(event.target === this) closeAllTasksModal()">
        <div class="task-modal-content" style="max-width: 1000px; max-height: 90vh; overflow-y: auto; width: 95%;">
            <button class="task-modal-close" onclick="closeAllTasksModal()">×</button>
            <div class="task-modal-header">
                <div class="task-modal-title">📋 All Accepted Tasks</div>
                <div class="task-modal-subtitle" id="allTasksCount">Total: 0</div>
            </div>
            <div class="task-modal-body" id="allTasksContainer" style="max-height: 65vh; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
            </div>
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

        function toggleMessageDropdown() {
            const dropdown = document.getElementById('messageDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            if (notificationDropdown) {
                notificationDropdown.classList.remove('show');
            }
            if (profileDropdown) {
                profileDropdown.classList.remove('show');
            }
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            const messageDropdown = document.getElementById('messageDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            if (messageDropdown) {
                messageDropdown.classList.remove('show');
            }
            if (profileDropdown) {
                profileDropdown.classList.remove('show');
            }
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        // View notification details
        function viewNotification(photo, name, task, time, type, action) {
            document.getElementById('notifDetailAvatar').src = photo;
            document.getElementById('notifDetailName').textContent = name;
            document.getElementById('notifDetailRole').textContent = 'Teacher';
            document.getElementById('notifDetailTask').textContent = task;
            document.getElementById('notifDetailTime').textContent = time;
            
            const actionEl = document.getElementById('notifDetailAction');
            actionEl.textContent = action;
            actionEl.className = 'notif-detail-task-action ' + (type === 'rating' ? 'rating' : 'approved');
            
            document.getElementById('notifDetailModal').classList.add('show');
        }

        // Close notification detail modal
        function closeNotifDetail() {
            document.getElementById('notifDetailModal').classList.remove('show');
        }

        // Dismiss notification
        function dismissNotification(btn, event) {
            event.stopPropagation();
            const item = btn.closest('.notification-item');
            item.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => {
                item.remove();
                // Update count
                const countBadge = document.querySelector('.notification-count-badge');
                const notifDot = document.querySelector('.notification-dot');
                if (countBadge) {
                    let count = parseInt(countBadge.textContent) || 0;
                    count = Math.max(0, count - 1);
                    countBadge.textContent = count + ' new';
                    if (notifDot && count <= 0) {
                        notifDot.style.display = 'none';
                    }
                }
            }, 300);
        }

        // Mark all notifications as read
        function markAllRead() {
            document.querySelectorAll('.notification-new-badge').forEach(badge => {
                badge.style.display = 'none';
            });
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.add('read');
            });
            const notifDot = document.querySelector('.notification-dot');
            if (notifDot) {
                notifDot.style.display = 'none';
            }
            const countBadge = document.querySelector('.notification-count-badge');
            if (countBadge) {
                countBadge.textContent = '0 new';
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
                closeNotifDetail();
                closeSettingsModal();
                const messageDropdown = document.getElementById('messageDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                if (messageDropdown) messageDropdown.classList.remove('show');
                if (notificationDropdown) notificationDropdown.classList.remove('show');
            }
        });

        function viewTaskDetails(taskId, title, description, room, dueDate, dueTime) {
            document.getElementById('modalTaskTitle').textContent = title;
            document.getElementById('modalTaskRoom').textContent = 'Room: ' + room;
            document.getElementById('modalTaskDescription').textContent = description || 'No description provided';
            document.getElementById('modalTaskRoomValue').textContent = room;
            document.getElementById('modalTaskDueDate').textContent = dueDate;
            document.getElementById('modalTaskDueTime').textContent = dueTime;
            
            const modal = document.getElementById('taskModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeTaskModal() {
            const modal = document.getElementById('taskModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTaskModal();
            }
        });

        // Image Modal Functions
        function openImageModal(imageSrc) {
            Swal.fire({
                imageUrl: imageSrc,
                imageAlt: 'Task Attachment',
                showConfirmButton: false,
                allowEscapeKey: true,
                allowOutsideClick: true,
                customClass: {
                    image: 'swal-image-large'
                },
                didOpen: (modal) => {
                    const image = modal.querySelector('.swal2-image');
                    if (image) {
                        image.style.maxWidth = '90vw';
                        image.style.maxHeight = '80vh';
                        image.style.objectFit = 'contain';
                    }
                }
            });
        }

        function toggleTask(element, todoId, currentStatus) {
            const formData = new FormData();
            formData.append('action', 'toggle_complete');
            formData.append('todo_id', todoId);
            formData.append('is_completed', currentStatus);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const newStatus = data.new_status;
                    Swal.fire({
                        icon: 'success',
                        title: newStatus ? 'Task Completed!' : 'Task Reopened',
                        text: newStatus ? 'Great job! Task moved to completed.' : 'Task moved back to pending.',
                        confirmButtonColor: '#ff0000',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        location.reload();
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update task',
                    confirmButtonColor: '#ff0000'
                });
            });
        }

        function pickupTask(todoId, button) {
            // Add loading state to button
            const originalHTML = button.innerHTML;
            button.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 8px;"><span style="animation: spin 1s linear infinite;">⏳</span> Starting...</span>';
            button.disabled = true;
            button.style.opacity = '0.7';
            button.style.cursor = 'not-allowed';

            const formData = new FormData();
            formData.append('action', 'pickup');
            formData.append('todo_id', todoId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Task Started!',
                        text: 'Your task is now in progress. Great work!',
                        confirmButtonColor: '#ff9800',
                        timer: 1500,
                        timerProgressBar: true
                    }).then(() => {
                        // Refresh the modal content instead of reloading
                        refreshAllTasksModal();
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Restore button
                button.innerHTML = originalHTML;
                button.disabled = false;
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update task',
                    confirmButtonColor: '#ff0000'
                });
            });
        }

        function markTaskComplete(todoId, button) {
            // Add loading state to button
            const originalText = button.textContent;
            const originalHTML = button.innerHTML;
            button.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 8px;"><span style="animation: spin 1s linear infinite;">⏳</span> Completing...</span>';
            button.disabled = true;
            button.style.opacity = '0.7';
            button.style.cursor = 'not-allowed';

            const formData = new FormData();
            formData.append('action', 'toggle_complete');
            formData.append('todo_id', todoId);
            formData.append('is_completed', 0);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Task Completed!',
                        text: 'Congratulations! Task marked as completed.',
                        confirmButtonColor: '#28a745',
                        timer: 1500,
                        timerProgressBar: true
                    }).then(() => {
                        // Refresh the modal content instead of reloading the page
                        refreshAllTasksModal();
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Restore button
                button.innerHTML = originalHTML;
                button.disabled = false;
                button.style.opacity = '1';
                button.style.cursor = 'pointer';
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to mark task as complete',
                    confirmButtonColor: '#ff0000'
                });
            });
        }

        function refreshAllTasksModal() {
            const container = document.getElementById('allTasksContainer');
            const countElement = document.getElementById('allTasksCount');
            
            // Fetch fresh tasks from server
            fetch('?action=get_tasks', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(allTasks => {
                container.innerHTML = '';
                let count = 0;
                
                // Filter out completed tasks
                const incompleteTasks = allTasks.filter(task => !parseInt(task.is_completed));
                
                if (incompleteTasks.length === 0) {
                    container.innerHTML = `<div class="empty-state" style="padding: 50px 25px;"><div class="empty-icon">✨</div><div class="empty-text" style="font-size: 1.8em;">No tasks accepted yet</div></div>`;
                } else {
                    incompleteTasks.forEach((task, index) => {
                        count++;
                        const taskStatus = task.status || 'pending';
                        const isCompleted = parseInt(task.is_completed);
                        let statusBadge = '';
                        let statusColor = '';
                        let actionButton = '';
                        
                        // Get teacher info
                        const teacherPhoto = task.teacher_photo || 'profile-default.png';
                        const teacherName = [task.teacher_first_name, task.teacher_middle_name, task.teacher_last_name].filter(Boolean).join(' ') || 'Unknown Teacher';
                        const teacherEmail = task.teacher_email_info || task.task_teacher_email || 'No email available';
                        
                        // Format time to AM/PM
                        const formatTime = (timeStr) => {
                            if (!timeStr) return 'N/A';
                            const [hours, minutes] = timeStr.split(':');
                            const h = parseInt(hours);
                            const ampm = h >= 12 ? 'PM' : 'AM';
                            const hour12 = h % 12 || 12;
                            return hour12 + ':' + minutes + ' ' + ampm;
                        };
                        
                        if (taskStatus === 'ongoing') {
                            statusBadge = '⚙️ In Progress';
                            statusColor = '#28a745';
                            actionButton = `<button id="markCompleteBtn-${task.id}" onclick="markTaskComplete(${task.id}, this)" style="background: linear-gradient(135deg, #28a745, #32c75f); color: white; border: none; padding: 16px 32px; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 1.2em; transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 18px rgba(40, 167, 69, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(40, 167, 69, 0.3)';">✓ Mark Complete</button>`;
                        } else {
                            statusBadge = '⏳ Waiting for Approval';
                            statusColor = '#ff9800';
                            actionButton = `<div style="color: #888; font-size: 1.1em; font-style: italic; padding: 12px 0;">📋 Waiting for teacher to accept your application...</div>`;
                        }
                        
                        const taskHTML = `
                            <div style="background: white; border-radius: 24px; overflow: hidden; border-left: 8px solid ${statusColor}; box-shadow: 0 8px 28px rgba(0,0,0,0.12);">
                                <div style="background: linear-gradient(135deg, #ff0000, #ff4444); padding: 28px; color: white;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <div style="font-size: 1.8em; font-weight: 800; margin-bottom: 12px; font-family: 'Space Grotesk', sans-serif;">📌 ${task.title}</div>
                                            <div style="font-size: 1.3em; margin-bottom: 8px; opacity: 0.95;">🏛️ Location: ${task.room}</div>
                                            <div style="font-size: 1.3em; opacity: 0.9;">📅 Due: ${task.due_date} | ⏰ ${formatTime(task.due_time)}</div>
                                        </div>
                                        <span style="background: rgba(255,255,255,0.25); color: white; padding: 12px 22px; border-radius: 25px; font-size: 1.1em; font-weight: 700; border: 3px solid white; white-space: nowrap;">${statusBadge}</span>
                                    </div>
                                </div>
                                <!-- Teacher Info Section -->
                                <div style="padding: 25px 28px; display: flex; align-items: center; gap: 20px; background: linear-gradient(135deg, #fff5f5, #ffffff); border-bottom: 3px solid #f0f0f0;">
                                    <img src="${teacherPhoto}" alt="Teacher" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 4px solid #ff0000; box-shadow: 0 6px 18px rgba(255,0,0,0.25); cursor: pointer; transition: all 0.3s ease;" onclick="openTeacherProfileModal('${teacherPhoto}')" onmouseover="this.style.transform='scale(1.08)'; this.style.boxShadow='0 8px 24px rgba(255,0,0,0.35)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 6px 18px rgba(255,0,0,0.25)';">
                                    <div style="flex: 1;">
                                        <div style="font-size: 1em; color: #888; font-weight: 600; margin-bottom: 4px;">👤 Posted by:</div>
                                        <div style="font-size: 1.5em; color: #333; font-weight: 800; margin-bottom: 6px; font-family: 'Space Grotesk', sans-serif;">${teacherName}</div>
                                        <div style="font-size: 1.1em; color: #666;">📧 ${teacherEmail}</div>
                                    </div>
                                </div>
                                <div style="padding: 28px;">
                                    <div style="color: #ff0000; font-size: 1.4em; font-weight: 700; margin-bottom: 10px; font-family: 'Space Grotesk', sans-serif;">📝 Description:</div>
                                    <div style="color: #555; font-size: 1.25em; line-height: 1.7;">${task.description || 'No description provided'}</div>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 15px; padding: 22px 28px; border-top: 3px solid #f0f0f0; background: #fafafa;">
                                    ${actionButton}
                                </div>
                            </div>
                        `;
                        container.innerHTML += taskHTML;
                    });
                }
                
                countElement.textContent = `Total: ${count}`;
            })
            .catch(error => {
                console.error('Error refreshing tasks:', error);
            });
        }

        function deleteTask(todoId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Task?',
                text: 'This action cannot be undone!',
                confirmButtonColor: '#ff0000',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                showCancelButton: true
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('todo_id', todoId);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Task Deleted',
                                text: 'Task has been removed from your list.',
                                confirmButtonColor: '#ff0000',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                location.reload();
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to delete task',
                            confirmButtonColor: '#ff0000'
                        });
                    });
                }
            });
        }

        function openAllTasksModal() {
            const container = document.getElementById('allTasksContainer');
            const countElement = document.getElementById('allTasksCount');
            
            // Pass PHP data to JavaScript via JSON
            const allTasks = <?php echo json_encode($todos); ?>;
            
            container.innerHTML = '';
            let count = 0;
            
            // Filter out completed tasks
            const incompleteTasks = allTasks.filter(task => !task.is_completed);
            
            if (incompleteTasks.length === 0) {
                container.innerHTML = `<div class="empty-state" style="padding: 50px 25px;"><div class="empty-icon">✨</div><div class="empty-text" style="font-size: 1.8em;">No tasks accepted yet</div></div>`;
            } else {
                incompleteTasks.forEach((task, index) => {
                    count++;
                    const taskStatus = task.status || 'pending';
                    let statusBadge = '';
                    let statusColor = '';
                    let actionButton = '';
                    
                    // Get teacher info
                    const teacherPhoto = task.teacher_photo || 'profile-default.png';
                    const teacherName = [task.teacher_first_name, task.teacher_middle_name, task.teacher_last_name].filter(Boolean).join(' ') || 'Unknown Teacher';
                    const teacherEmail = task.teacher_email_info || task.task_teacher_email || 'No email available';
                    
                    // Format time to AM/PM
                    const formatTime = (timeStr) => {
                        if (!timeStr) return 'N/A';
                        const [hours, minutes] = timeStr.split(':');
                        const h = parseInt(hours);
                        const ampm = h >= 12 ? 'PM' : 'AM';
                        const hour12 = h % 12 || 12;
                        return hour12 + ':' + minutes + ' ' + ampm;
                    };
                    
                    if (taskStatus === 'ongoing') {
                        statusBadge = '⚙️ In Progress';
                        statusColor = '#28a745';
                        actionButton = `<button id="markCompleteBtn-${task.id}" onclick="markTaskComplete(${task.id}, this)" style="background: linear-gradient(135deg, #28a745, #32c75f); color: white; border: none; padding: 16px 32px; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 1.2em; transition: all 0.3s ease; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 6px 18px rgba(40, 167, 69, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(40, 167, 69, 0.3)';">✓ Mark Complete</button>`;
                    } else {
                        statusBadge = '⏳ Waiting for Approval';
                        statusColor = '#ff9800';
                        actionButton = `<div style="color: #888; font-size: 1.1em; font-style: italic; padding: 12px 0;">📋 Waiting for teacher to accept your application...</div>`;
                    }
                    
                    const taskHTML = `
                        <div style="background: white; border-radius: 24px; overflow: hidden; border-left: 8px solid ${statusColor}; box-shadow: 0 8px 28px rgba(0,0,0,0.12);">
                            <div style="background: linear-gradient(135deg, #ff0000, #ff4444); padding: 28px; color: white;">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <div style="font-size: 1.8em; font-weight: 800; margin-bottom: 12px; font-family: 'Space Grotesk', sans-serif;">📌 ${task.title}</div>
                                        <div style="font-size: 1.3em; margin-bottom: 8px; opacity: 0.95;">🏛️ Location: ${task.room}</div>
                                        <div style="font-size: 1.3em; opacity: 0.9;">📅 Due: ${task.due_date} | ⏰ ${formatTime(task.due_time)}</div>
                                    </div>
                                    <span style="background: rgba(255,255,255,0.25); color: white; padding: 12px 22px; border-radius: 25px; font-size: 1.1em; font-weight: 700; border: 3px solid white; white-space: nowrap;">${statusBadge}</span>
                                </div>
                            </div>
                            <!-- Teacher Info Section -->
                            <div style="padding: 25px 28px; display: flex; align-items: center; gap: 20px; background: linear-gradient(135deg, #fff5f5, #ffffff); border-bottom: 3px solid #f0f0f0;">
                                <img src="${teacherPhoto}" alt="Teacher" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 4px solid #ff0000; box-shadow: 0 6px 18px rgba(255,0,0,0.25); cursor: pointer; transition: all 0.3s ease;" onclick="openTeacherProfileModal('${teacherPhoto}')" onmouseover="this.style.transform='scale(1.08)'; this.style.boxShadow='0 8px 24px rgba(255,0,0,0.35)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 6px 18px rgba(255,0,0,0.25)';">
                                <div style="flex: 1;">
                                    <div style="font-size: 1em; color: #888; font-weight: 600; margin-bottom: 4px;">👤 Posted by:</div>
                                    <div style="font-size: 1.5em; color: #333; font-weight: 800; margin-bottom: 6px; font-family: 'Space Grotesk', sans-serif;">${teacherName}</div>
                                    <div style="font-size: 1.1em; color: #666;">📧 ${teacherEmail}</div>
                                </div>
                            </div>
                            <div style="padding: 28px;">
                                <div style="color: #ff0000; font-size: 1.4em; font-weight: 700; margin-bottom: 10px; font-family: 'Space Grotesk', sans-serif;">📝 Description:</div>
                                <div style="color: #555; font-size: 1.25em; line-height: 1.7;">${task.description || 'No description provided'}</div>
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 15px; padding: 22px 28px; border-top: 3px solid #f0f0f0; background: #fafafa;">
                                ${actionButton}
                            </div>
                        </div>
                    `;
                    container.innerHTML += taskHTML;
                });
            }
            
            countElement.textContent = `Total: ${count}`;
            const modal = document.getElementById('allTasksModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeAllTasksModal() {
            const modal = document.getElementById('allTasksModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
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

        // Close modals with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTaskModal();
                closeAllTasksModal();
                closeTeacherProfileModal();
                const messageDropdown = document.getElementById('messageDropdown');
                const notificationDropdown = document.getElementById('notificationDropdown');
                if (messageDropdown) messageDropdown.classList.remove('show');
                if (notificationDropdown) notificationDropdown.classList.remove('show');
            }
        });
        
        // Teacher Profile Modal Functions
        function openTeacherProfileModal(imageSrc) {
            const modal = document.getElementById('teacherProfileModal');
            const modalImg = document.getElementById('teacherModalImage');
            modalImg.src = imageSrc;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeTeacherProfileModal() {
            const modal = document.getElementById('teacherProfileModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Check for newly approved tasks
        let lastTaskStatuses = {};
        
        // Store initial task statuses
        document.querySelectorAll('.task-card[data-task-id]').forEach(card => {
            const taskId = card.dataset.taskId;
            const status = card.dataset.status;
            lastTaskStatuses[taskId] = status;
        });

        // Poll for task updates every 5 seconds
        setInterval(checkForApprovals, 5000);

        function checkForApprovals() {
            fetch('student_todo_list.php?action=get_tasks')
                .then(response => response.json())
                .then(tasks => {
                    tasks.forEach(task => {
                        const taskId = task.id;
                        const newStatus = task.status;
                        const oldStatus = lastTaskStatuses[taskId];
                        
                        // Check if task was just approved (changed from pending to ongoing)
                        if (oldStatus === 'pending' && newStatus === 'ongoing') {
                            showApprovalAnimation(task.title);
                            lastTaskStatuses[taskId] = newStatus;
                            
                            // Update the task card in DOM
                            setTimeout(() => {
                                location.reload();
                            }, 4000);
                        }
                    });
                })
                .catch(error => console.log('Poll error:', error));
        }

        function showApprovalAnimation(taskTitle) {
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'approved-overlay';
            overlay.innerHTML = `
                <div class="approved-icon">✅</div>
                <div class="approved-text">Task Approved!</div>
                <div class="approved-subtext">${taskTitle}</div>
                <div class="progress-to-inprogress">
                    <div class="loading-spinner"></div>
                    <span>Moving to In Progress...</span>
                </div>
            `;
            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';

            // Play sound effect (optional)
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBQlgpfPe');
                audio.volume = 0.3;
                audio.play().catch(() => {});
            } catch (e) {}

            // Remove overlay after animation
            setTimeout(() => {
                overlay.style.animation = 'fadeOut 0.5s ease forwards';
                setTimeout(() => {
                    overlay.remove();
                    document.body.style.overflow = '';
                }, 500);
            }, 3500);
        }

        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>

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
                <div class="notif-detail-teacher">
                    <img src="profile-default.png" alt="Teacher" class="notif-detail-avatar" id="notifDetailAvatar">
                    <div class="notif-detail-info">
                        <h4 id="notifDetailName">Teacher Name</h4>
                        <p id="notifDetailRole">Teacher</p>
                    </div>
                </div>
                <div class="notif-detail-task">
                    <div class="notif-detail-task-label">Task</div>
                    <div class="notif-detail-task-title" id="notifDetailTask">Task Title</div>
                    <div class="notif-detail-task-action" id="notifDetailAction">Action</div>
                </div>
                <div class="notif-detail-time" id="notifDetailTime">Time</div>
            </div>
        </div>
    </div>
    
    <!-- Teacher Profile Modal -->
    <div class="profile-modal" id="teacherProfileModal" onclick="if(event.target === this) closeTeacherProfileModal()">
        <button class="profile-modal-close" onclick="closeTeacherProfileModal()">×</button>
        <div class="profile-modal-content">
            <img id="teacherModalImage" class="profile-modal-image" alt="Teacher Profile" style="border-radius: 15px; max-width: 500px; max-height: 70vh; border: 5px solid white;">
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

    <!-- Chat Modal -->
    <div class="chat-modal" id="chatModal" onclick="if(event.target === this) closeChatModal()">
        <div class="chat-container">
            <div class="chat-header">
                <button class="chat-header-back" onclick="closeChatModal()">←</button>
                <img src="profile-default.png" alt="Contact" class="chat-header-avatar" id="chatAvatar">
                <div class="chat-header-info">
                    <h4 class="chat-header-name" id="chatName">Contact Name</h4>
                    <span class="chat-header-status" id="chatStatus">Teacher</span>
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
                        document.getElementById('messageList').innerHTML = '<div class="message-empty">No approved tasks yet.</div>';
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
                messageList.innerHTML = '<div class="message-empty">No approved tasks yet.<br><small style="color:#aaa;">You can message teachers who approved your task applications.</small></div>';
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
    </script>
</body>
</html>


