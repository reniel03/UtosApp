<?php
session_start();

// Check if the user is logged in as a student
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

include 'db_connect.php';

// Handle delete task request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task_id'])) {
    $task_id = intval($_POST['delete_task_id']);
    $student_email_check = $_SESSION['email'];
    
    // Verify the task belongs to this student before deleting
    $verify_query = "SELECT id FROM student_todos WHERE id = ? AND student_email = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param('is', $task_id, $student_email_check);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows > 0) {
        $delete_query = "DELETE FROM student_todos WHERE id = ? AND student_email = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('is', $task_id, $student_email_check);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting task']);
            exit;
        }
    }
    
    $verify_stmt->close();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get student email from session
$student_email = $_SESSION['email'];

// Fetch student's completed tasks with task details
$query = "SELECT st.*, t.id AS task_id, t.title, t.room, t.due_date, t.due_time, t.description, t.attachments, t.teacher_email,
                 tr.first_name AS teacher_first_name, tr.middle_name AS teacher_middle_name, tr.last_name AS teacher_last_name, tr.photo AS teacher_photo
          FROM student_todos st
          JOIN tasks t ON t.id = st.task_id
          LEFT JOIN teachers tr ON tr.email = t.teacher_email
          WHERE st.student_email = ? AND st.is_completed = 1
          ORDER BY st.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();
$completed_tasks = [];
while ($row = $result->fetch_assoc()) {
    $completed_tasks[] = $row;
}
$stmt->close();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student History</title>
    <style>
       

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2.2em;
            }

            .main-content {
                padding: 20px 15px 100px 15px;
            }

            .task-card {
                gap: 12px;
                padding: 16px;
            }

            .task-image {
                width: 70px;
                height: 70px;
            }

            .task-title {
                font-size: 1.05em;
            }

            .tabs-container {
                gap: 10px;
            }

            .tab-btn {
                padding: 10px 20px;
                font-size: 0.9em;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.8em;
            }

            .main-content {
                padding: 15px 10px 100px 10px;
            }

            .task-card {
                gap: 10px;
                padding: 14px;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .card-bg {
                flex-direction: column;
                gap: 12px;
            }

            .task-image {
                width: 60px;
                height: 60px;
            }

            .task-arrow {
                width: 36px;
                height: 36px;
            }

            .task-info {
                width: 100%;
            }

            .task-title {
                font-size: 1em;
            }
        }
    </style>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Update button styles
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function viewTaskDetails(taskData) {
            // Navigate to task details or show modal
            // For now, just log the task
            console.log('Task clicked:', taskData);
        }

        function deleteTask(taskId, event) {
            event.stopPropagation();
            
            Swal.fire({
                title: 'Delete Task',
                text: 'Are you sure you want to delete this task from your history?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#999',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('delete_task_id', taskId);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Deleted',
                                text: 'Task deleted successfully',
                                icon: 'success',
                                confirmButtonColor: '#ff0000'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Error deleting task',
                                icon: 'error',
                                confirmButtonColor: '#ff0000'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'An error occurred while deleting the task',
                            icon: 'error',
                            confirmButtonColor: '#ff0000'
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>
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

        .main-content {
            padding: 40px 30px 100px 30px;
            margin-top: 0;
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
            min-height: calc(100vh - 240px);
            margin-top: 240px;
            padding: 100px 120px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .history-header {
            text-align: center;
            margin-bottom: 20px;
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

        .history-title {
            font-size: 500%;
            color: #ff0000;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 4px 20px rgba(255, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .history-subtitle {
            font-size: 1.2em;
            color: #666;
            font-weight: 500;
        }

        .history-container {
            width: 100%;
            max-width: 1400px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section-header {
            font-size: 2.4em;
            color: #ff0000;
            font-weight: bold;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: transparent;
            justify-content: center;
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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 28px;
        }

        .task-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 14px 48px rgba(255, 0, 0, 0.14);
            transition: all 0.3s ease;
            animation: slideIn 0.6s ease-out forwards;
            opacity: 0;
            border: 1px solid rgba(255, 0, 0, 0.16);
        }

        .task-card:hover {
            transform: translateY(-10px) scale(1.015);
            box-shadow: 0 20px 65px rgba(255, 0, 0, 0.2);
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
            position: relative;
            height: 150px;
            overflow: hidden;
        }
        
        .task-card-header::before {
            content: '';
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            mix-blend-mode: multiply;
        }

        .completed-stamp::before {
            content: 'COMPLETED';
            font-size: 3.4em;
            letter-spacing: 0.08em;
            font-weight: 900;
            color: #0f9d58;
            border: 6px solid #0f9d58;
            border-radius: 50%;
            padding: 34px 36px;
            transform: rotate(-15deg);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
            opacity: 0.22;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .task-card-title {
            font-size: 2.5em;
            font-weight: 900;
            margin-bottom: 12px;
            letter-spacing: 0.01em;
        }

        .task-card-room,
        .task-card-due {
            font-size: 1.45em;
            opacity: 0.95;
            margin-bottom: 8px;
        }

        .task-card-body {
            padding: 26px;
            background: white;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .view-btn {
            align-self: flex-start;
            background: linear-gradient(135deg, #ff4d4d, #ff0000);
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1.05em;
            cursor: pointer;
            box-shadow: 0 10px 26px rgba(255, 0, 0, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 0, 0, 0.22);
        }

        .task-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            padding: 20px;
        }

        .task-modal-overlay.show {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }

        .task-modal {
            background: #fff;
            border-radius: 28px;
            max-width: 1180px;
            width: 100%;
            box-shadow: 0 28px 78px rgba(0, 0, 0, 0.26);
            overflow: hidden;
            border: 3px solid #ff0000;
        }

        .task-modal-header {
            background: radial-gradient(circle at 20% 20%, #ff4d4d, #ff0000 60%);
            color: white;
            padding: 34px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-modal-title {
            font-size: 3em;
            font-weight: 900;
        }

        .task-modal-close {
            background: rgba(255, 255, 255, 0.18);
            border: 2px solid rgba(255, 255, 255, 0.6);
            color: white;
            width: 66px;
            height: 66px;
            border-radius: 50%;
            font-size: 2em;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .task-modal-close:hover {
            transform: rotate(8deg) scale(1.05);
            background: rgba(255, 255, 255, 0.3);
        }

        .task-modal-body {
            padding: 40px 44px 44px;
            display: grid;
            grid-template-columns: 210px 1fr;
            gap: 28px;
            align-items: start;
        }

        .teacher-avatar {
            width: 190px;
            height: 190px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ff0000;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.17);
        }

        .teacher-avatar-container {
            width: 190px;
            height: 190px;
            border-radius: 50%;
            border: 3px solid #ff0000;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.17);
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            overflow: hidden;
        }

        .teacher-avatar-default {
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }

        .teacher-name {
            font-size: 2.2em;
            font-weight: 900;
            color: #222;
            margin-bottom: 12px;
        }

        .teacher-email {
            color: #777;
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.22em;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .detail-chip {
            background: #fff6f6;
            border: 1px solid rgba(255, 0, 0, 0.15);
            border-radius: 18px;
            padding: 18px 20px;
            font-weight: 800;
            font-size: 1.32em;
            color: #b00000;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .task-modal-desc {
            margin-top: 22px;
            background: #fafafa;
            border: 1px solid #f0f0f0;
            border-radius: 20px;
            padding: 20px 22px;
            color: #444;
            line-height: 1.8;
            font-size: 1.34em;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .task-card-description {
            margin-bottom: 8px;
        }

        .task-desc-label {
            font-weight: 700;
            color: #222;
            font-size: 1.3em;
            margin-bottom: 8px;
        } 

        .task-desc-text {
            color: #555;
            font-size: 1.2em;
            line-height: 1.58;
        }

        .task-card-footer {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-top: 12px;
            border-top: 1px solid #f3f3f3;
            flex-wrap: wrap;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 16px;
            background: rgba(255, 0, 0, 0.08);
            color: #c00000;
            font-weight: 700;
            font-size: 1.15em;
            border: 1px solid rgba(255, 0, 0, 0.24);
        }

        .completed-badge {
            background: linear-gradient(135deg, #28a745, #32c75f);
            color: white;
            padding: 14px 20px;
            border-radius: 22px;
            font-size: 1.15em;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 16px 40px rgba(40, 167, 69, 0.3);
        }

        .posted-by {
            font-size: 1.05em;
            color: #444;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #999;
        }

        .empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
            color: #ff0000;
            stroke: #ff0000;
        }

        .empty-text {
            font-size: 1.6em;
            margin-bottom: 10px;
            font-weight: 700;
            color: #000;
        }

        .empty-subtext {
            font-size: 1.2em;
            color: #bbb;
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

        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* New Task Body Card Styles (matching teacher_record.php) */
        .task-body-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(255, 0, 0, 0.12);
            border: 2px solid rgba(255, 0, 0, 0.08);
            transition: all 0.3s ease;
            animation: slideIn 0.6s ease-out forwards;
            opacity: 0;
            display: grid;
            grid-template-columns: 80px 1fr;
            grid-template-rows: auto auto auto auto;
            gap: 15px 20px;
        }

        .task-body-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(255, 0, 0, 0.18);
        }

        /* Header with Email and Completed Badge */
        .task-body-header {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #f3f3f3;
        }

        .task-body-student-email {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05em;
            color: #333;
            font-weight: 600;
        }

        .task-body-student-email svg {
            width: 18px;
            height: 18px;
            color: #ff0000;
        }

        .task-body-completed-badge {
            background: transparent;
            color: #ff0000;
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 1em;
            font-weight: 900;
            box-shadow: none;
        }

        /* Task Image/Icon */
        .task-body-image {
            grid-row: 2;
            grid-column: 1;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #fff5f5, #ffe5e5);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid rgba(255, 0, 0, 0.1);
        }

        .task-body-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Meta Information (Due Date, Time, Location) */
        .task-body-meta {
            grid-column: 2;
            grid-row: 2;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-body-meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }

        .meta-icon {
            width: 18px;
            height: 18px;
            color: #ff0000;
            flex-shrink: 0;
        }

        .meta-label {
            font-weight: 700;
            color: #666;
            min-width: 100px;
        }

        .meta-value {
            color: #ff0000;
            font-weight: 700;
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
            grid-column: 1 / -1;
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
        }

        .progress-step.completed .step-icon svg rect {
            display: none;
        }

        /* Teacher/Student Information */
        .task-body-student-info {
            grid-column: 1 / -1;
            grid-row: 4;
            display: flex;
            align-items: center;
            gap: 15px;
            padding-top: 12px;
            border-top: 2px solid #f3f3f3;
        }

        .task-body-student-avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .task-body-student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ff0000;
            background: #f0f0f0;
        }

        .task-body-student-avatar-default {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            color: #ff0000;
        }

        .task-body-student-details {
            flex: 1;
        }

        .task-body-student-name {
            font-size: 1.1em;
            font-weight: 800;
            color: #222;
            margin-bottom: 4px;
        }

        .task-body-student-meta {
            font-size: 0.85em;
            color: #888;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .task-body-student-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        @media (max-width: 900px) {
            .main-content {
                padding: 40px 20px;
                margin-top: 150px;
            }

            .history-title {
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

        /* Rating Modal Styles */
        .rating-modal {
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

        .rating-modal.show {
            display: flex;
            opacity: 1;
            justify-content: center;
            align-items: center;
        }

        .rating-modal-container {
            background: white;
            border-radius: 28px;
            width: 500px;
            max-width: 95%;
            box-shadow: 0 30px 80px rgba(255, 0, 0, 0.3);
            animation: ratingSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            overflow: hidden;
        }

        @keyframes ratingSlideIn {
            from { opacity: 0; transform: scale(0.8) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .rating-modal-header {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: none;
        }

        .rating-modal-title {
            color: white;
            font-size: 1.8em;
            font-weight: 800;
            margin: 0;
        }

        .rating-modal-close {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            font-size: 1.8em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .rating-modal-close:hover {
            background: white;
            color: #ff0000;
            transform: rotate(90deg) scale(1.1);
        }

        .rating-modal-body {
            padding: 40px;
            text-align: center;
        }

        .rating-teacher-info {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f3f3f3;
        }

        .rating-teacher-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ff0000;
            box-shadow: 0 8px 25px rgba(255, 0, 0, 0.25);
        }

        .rating-teacher-avatar-default {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            color: #ff0000;
            background: #f5f5f5;
            border: 3px solid #ff0000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .rating-teacher-name {
            font-size: 1.3em;
            font-weight: 800;
            color: #222;
        }

        .rating-stars-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
            font-size: 4em;
        }

        .rating-star {
            color: #ffc107;
            text-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        .rating-star.empty {
            color: #e0e0e0;
            text-shadow: none;
        }

        .rating-message-box {
            background: linear-gradient(135deg, #fff5f5, #ffffff);
            border-radius: 16px;
            padding: 25px;
            border: 2px solid #ffeeee;
            text-align: left;
        }

        .rating-message-label {
            font-size: 0.9em;
            color: #999;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .rating-message-text {
            font-size: 1.1em;
            color: #333;
            line-height: 1.6;
            font-weight: 500;
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

        /* Rating Star SVG Styles */
        .modal-rating-stars {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
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

        /* ====== MOBILE RESPONSIVE STYLES ====== */
        @media (max-width: 900px) {
            .main-content {
                padding: 40px 20px;
                margin-top: 180px;
            }

            .history-title {
                font-size: 2.5em;
            }

            .nav-links {
                gap: 30px;
            }

            .nav-links a {
                font-size: 1.5em;
            }

            .task-list {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            html { font-size: 14px; }
            
            body {
                font-size: 14px;
            }

            .nav-bar {
                padding: 15px 12px;
                height: auto;
                flex-wrap: wrap;
                gap: 15px;
            }

            .nav-links {
                gap: 15px;
                width: 100%;
                flex-direction: column;
                display: none;
            }

            .nav-links a {
                font-size: 1.1em;
                padding: 8px 12px;
            }

            .nav-right {
                gap: 8px;
                flex-wrap: wrap;
                width: 100%;
                justify-content: center;
            }

            .icon-wrapper {
                margin: 5px;
            }

            .icon-btn {
                width: 50px;
                height: 50px;
                padding: 12px;
                margin: 0 5px;
            }

            .icon-btn svg {
                width: 35px;
                height: 35px;
            }

            .profile-pic {
                width: 50px;
                height: 50px;
            }

            .main-content {
                padding: 20px 15px 50px 15px;
                margin-top: 10px;
            }

            .history-title {
                font-size: 3.2em;
                gap: 10px;
                margin-top: 30px;
            }

            .history-subtitle {
                font-size: 1.5em;
            }

            .history-container {
                width: 100%;
                padding: 0 10px;
            }

            .section-header {
                font-size: 1.8em;
            }

            .task-list {
                display: grid;
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .task-card {
                border-radius: 16px;
            }

            .task-card-header {
                padding: 20px;
            }

            .task-card-title {
                font-size: 1.5em;
            }

            .task-card-room,
            .task-card-due {
                font-size: 1.1em;
            }

            .task-card-body {
                padding: 15px;
            }

            .meta-pill {
                font-size: 0.9em;
                padding: 10px 12px;
            }

            .view-btn {
                padding: 10px 15px;
                font-size: 0.95em;
            }

            .profile-dropdown {
                min-width: 320px;
                right: 0;
                left: auto;
            }

            .message-dropdown,
            .notification-dropdown {
                width: 320px;
                right: 0;
                left: auto;
            }

            .profile-info-pic {
                width: 100px;
                height: 100px;
            }

            .profile-menu a {
                font-size: 1.1em;
                padding: 1em 1.2rem;
            }
        }

        @media (max-width: 480px) {
            html { font-size: 12px; }
            
            body {
                font-size: 12px;
            }

            .nav-bar {
                padding: 12px 8px;
                gap: 10px;
            }

            .nav-links {
                gap: 10px;
            }

            .nav-links a {
                font-size: 0.9em;
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
            }

            .main-content {
                padding: 15px 12px 40px 12px;
                margin-top: 5px;
            }

            .history-title {
                font-size: 2.5em;
                margin-bottom: 10px;
            }

            .history-subtitle {
                font-size: 1.3em;
            }

            .history-container {
                gap: 15px;
            }

            .section-header {
                font-size: 1.3em;
                margin-bottom: 15px;
            }

            .task-count {
                padding: 5px 12px;
                font-size: 0.75em;
            }

            .task-card-header {
                padding: 15px;
            }

            .task-card-title {
                font-size: 1.2em;
            }

            .task-card-room,
            .task-card-due {
                font-size: 0.95em;
            }

            .task-card-body {
                padding: 12px;
                gap: 10px;
            }

            .task-card-footer {
                flex-direction: column;
                gap: 8px;
            }

            .meta-pill {
                font-size: 0.85em;
                padding: 8px 10px;
                flex: 1;
            }

            .view-btn {
                width: 100%;
                padding: 10px 12px;
                font-size: 0.9em;
            }

            .profile-dropdown {
                min-width: 280px;
                padding: 1.5rem;
            }

            .message-dropdown,
            .notification-dropdown {
                width: 280px;
            }

            .profile-info-pic {
                width: 80px;
                height: 80px;
            }

            .profile-menu a {
                font-size: 1em;
                padding: 0.9em 1rem;
            }

            .task-modal {
                border-radius: 20px;
            }

            .task-modal-header {
                padding: 20px 25px;
            }

            .task-modal-title {
                font-size: 1.5em;
            }

            .task-modal-body {
                padding: 20px;
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .teacher-avatar {
                width: 120px;
                height: 120px;
                margin: 0 auto;
            }

            .teacher-name {
                font-size: 1.3em;
            }

            .teacher-email {
                font-size: 0.9em;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-chip {
                font-size: 0.95em;
                padding: 12px 15px;
            }

            .task-modal-desc {
                font-size: 1em;
                padding: 15px 18px;
            }

            .profile-details {
                padding: 1rem;
            }

            .detail-group {
                padding: 0.6rem 0.8rem;
                margin-bottom: 0.6rem;
            }

            .empty-state {
                margin-top: 100px;
            }

        @media (min-width: 769px) and (max-width: 1024px) {
            .nav-bar { 
                padding: 30px 40px; 
            }

            .nav-links a { 
                font-size: 1.5em; 
            }

            .icon-btn { 
                width: 80px; 
                height: 80px; 
            }

            .icon-btn svg { 
                width: 50px; 
                height: 50px; 
            }

            .profile-pic { 
                width: 80px; 
                height: 80px; 
            }

            .main-content {
                padding: 60px 30px;
            }

            .history-title {
                font-size: 3em;
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

    <div class="main-content">
        <div class="history-header">
            <h1 class="history-title">Completed Tasks</h1>
            <p class="history-subtitle">View all your completed assignments</p>
        </div>

            <?php if (count($completed_tasks) > 0): ?>
                <div class="task-list">
                    <?php foreach ($completed_tasks as $index => $task): ?>
                        <div class="task-body-card" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                            <!-- Header: Email and Completed Badge -->
                            <div class="task-body-header">
                                <div class="task-body-student-email">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M20 4H4C2.9 4 2 4.9 2 6V18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 6L12 11L4 6H20ZM20 18H4V8L12 13L20 8V18Z"/></svg>
                                    <?php echo htmlspecialchars($task['teacher_email']); ?>
                                </div>
                                <div class="task-body-completed-badge">Completed</div>
                            </div>

                            <!-- Task Image/Icon -->
                            <div class="task-body-image">
                                <?php if (!empty($task['attachments'])): ?>
                                    <img src="<?php echo htmlspecialchars($task['attachments']); ?>" alt="Task Attachment">
                                <?php else: ?>
                                    <svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#ff0000" opacity="0.3" style="width: 80px; height: 80px;">
                                        <g id="page_document_emoji_empty">
                                            <g id="XMLID_1521_">
                                                <path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z"></path>
                                                <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z"></path>
                                            </g>
                                            <g id="XMLID_1337_">
                                                <g id="XMLID_4010_">
                                                    <polyline fill="none" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline>
                                                    <polyline fill="none" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline>
                                                    <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path>
                                                    <g>
                                                        <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path>
                                                        <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" stroke="#ff0000" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path>
                                                    </g>
                                                </g>
                                            </g>
                                        </g>
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <!-- Meta Information -->
                            <div class="task-body-meta">
                                <div class="task-body-meta-item">
                                    <svg fill="#ff0000" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 512 512" xml:space="preserve" stroke="#ff0000" class="meta-icon" style="width: 20px; height: 20px;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M360.548,29.538h-11.01V8.615c0-5.438-3.173-8.615-8.615-8.615H183.385c-5.442,0-11.077,3.178-11.077,8.615v20.923 h-18.538c-32.529,0-60.231,25.308-60.231,57.923V451.62c0,32.615,27.721,60.38,60.289,60.38h206.808 c32.567,0,57.827-27.764,57.827-60.38V87.461C418.462,54.846,393.164,29.538,360.548,29.538z M192,19.692h137.846v49.231H192 V19.692z M398.769,451.62c0,21.755-16.433,40.688-38.135,40.688H153.827c-21.702,0-40.596-18.933-40.596-40.688V87.461 c0-21.76,18.865-38.231,40.539-38.231h18.538v28.308c0,5.438,5.635,11.077,11.077,11.077h157.539 c5.442,0,8.615-5.639,8.615-11.077V49.231h11.01c21.75,0,38.221,16.471,38.221,38.231V451.62z"></path> </g> </g> <g> <g> <rect x="270.769" y="128" width="78.769" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="206.769" width="196.923" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="265.846" width="196.923" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="324.923" width="196.923" height="19.692"></rect> </g> </g> <g> <g> <rect x="152.615" y="384" width="196.923" height="19.692"></rect> </g> </g> </g></svg>
                                    <span class="meta-label">Task:</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($task['title']); ?></span>
                                </div>
                                <div class="task-body-meta-item">
                                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    <span class="meta-label">Due Date:</span>
                                    <span class="meta-value"><?php echo date('M d, Y', strtotime($task['due_date'])); ?></span>
                                </div>
                                <div class="task-body-meta-item">
                                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <span class="meta-label">Due Time:</span>
                                    <span class="meta-value"><?php echo date('h:i A', strtotime($task['due_time'])); ?></span>
                                </div>
                                <div class="task-body-meta-item">
                                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    <span class="meta-label">Location:</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($task['room']); ?></span>
                                </div>
                            </div>

                            <!-- Task Progress Tracker -->
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

                            <!-- Teacher Information -->
                            <div class="task-body-student-info">
                                <div class="task-body-student-avatar-wrapper">
                                    <?php if (!empty($task['teacher_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($task['teacher_photo']); ?>" alt="Teacher" class="task-body-student-avatar">
                                    <?php else: ?>
                                        <svg class="task-body-student-avatar-default" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M22 12C22 6.49 17.51 2 12 2C6.49 2 2 6.49 2 12C2 14.9 3.25 17.51 5.23 19.34C5.23 19.35 5.23 19.35 5.22 19.36C5.32 19.46 5.44 19.54 5.54 19.63C5.6 19.68 5.65 19.73 5.71 19.77C5.89 19.92 6.09 20.06 6.28 20.2C6.35 20.25 6.41 20.29 6.48 20.34C6.67 20.47 6.87 20.59 7.08 20.7C7.15 20.74 7.23 20.79 7.3 20.83C7.5 20.94 7.71 21.04 7.93 21.13C8.01 21.17 8.09 21.21 8.17 21.24C8.39 21.33 8.61 21.41 8.83 21.48C8.91 21.51 8.99 21.54 9.07 21.56C9.31 21.63 9.55 21.69 9.79 21.75C9.86 21.77 9.93 21.79 10.01 21.8C10.29 21.86 10.57 21.9 10.86 21.93C10.9 21.93 10.94 21.94 10.98 21.95C11.32 21.98 11.66 22 12 22C12.34 22 12.68 21.98 13.01 21.95C13.05 21.95 13.09 21.94 13.13 21.93C13.42 21.9 13.7 21.86 13.98 21.8C14.05 21.79 14.12 21.76 14.2 21.75C14.44 21.69 14.69 21.64 14.92 21.56C15 21.53 15.08 21.5 15.16 21.48C15.38 21.4 15.61 21.33 15.82 21.24C15.9 21.21 15.98 21.17 16.06 21.13C16.27 21.04 16.48 20.94 16.69 20.83C16.77 20.79 16.84 20.74 16.91 20.7C17.11 20.58 17.31 20.47 17.51 20.34C17.58 20.3 17.64 20.25 17.71 20.2C17.91 20.06 18.1 19.92 18.28 19.77C18.34 19.72 18.39 19.67 18.45 19.63C18.56 19.54 18.67 19.45 18.77 19.36C18.77 19.35 18.77 19.35 18.76 19.34C20.75 17.51 22 14.9 22 12ZM16.94 16.97C14.23 15.15 9.79 15.15 7.06 16.97C6.62 17.26 6.26 17.6 5.96 17.97C4.44 16.43 3.5 14.32 3.5 12C3.5 7.31 7.31 3.5 12 3.5C16.69 3.5 20.5 7.31 20.5 12C20.5 14.32 19.56 16.43 18.04 17.97C17.75 17.6 17.38 17.26 16.94 16.97Z" fill="currentColor"></path><path d="M12 6.92969C9.93 6.92969 8.25 8.60969 8.25 10.6797C8.25 12.7097 9.84 14.3597 11.95 14.4197C11.98 14.4197 12.02 14.4197 12.04 14.4197C12.06 14.4197 12.09 14.4197 12.11 14.4197C12.12 14.4197 12.13 14.4197 12.13 14.4197C14.15 14.3497 15.74 12.7097 15.75 10.6797C15.75 8.60969 14.07 6.92969 12 6.92969Z" fill="currentColor"></path></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="task-body-student-details">
                                    <div class="task-body-student-name"><?php echo htmlspecialchars($task['teacher_first_name'] . ' ' . $task['teacher_last_name']); ?></div>
                                    <div class="task-body-student-meta">
                                        <span>Posted By Teacher</span>
                                    </div>
                                </div>
                                <!-- Rating Display -->
                                <?php if ($task['rating']): ?>
                                    <div class="task-body-rate-btn" style="cursor: pointer; background: white; color: #ff0000; border: 2px solid #ff0000; padding: 10px 15px; border-radius: 8px; font-weight: 700; width: auto; display: inline-flex; align-items: center; justify-content: center; margin-left: auto;" onclick="openRatingModal(<?php echo $task['rating']; ?>, '<?php echo htmlspecialchars($task['teacher_first_name'] . ' ' . $task['teacher_last_name']); ?>', '<?php echo htmlspecialchars($task['teacher_photo']); ?>', getRatingMessage(<?php echo $task['rating']; ?>));">
                                        View Rating
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="task-modal-overlay" id="modal-<?php echo $task['task_id']; ?>" onclick="if(event.target === this) closeTaskModal('<?php echo $task['task_id']; ?>');">
                            <div class="task-modal">
                                <div class="task-modal-header">
                                    <div class="task-modal-title">Task Details</div>
                                    <button class="task-modal-close" aria-label="Close" onclick="closeTaskModal('<?php echo $task['task_id']; ?>');">×</button>
                                </div>
                                <div class="task-modal-body">
                                    <?php if (!empty($task['teacher_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($task['teacher_photo']); ?>" alt="Teacher photo" class="teacher-avatar">
                                    <?php else: ?>
                                        <div class="teacher-avatar-container">
                                            <svg class="teacher-avatar-default" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M22 12C22 6.49 17.51 2 12 2C6.49 2 2 6.49 2 12C2 14.9 3.25 17.51 5.23 19.34C5.23 19.35 5.23 19.35 5.22 19.36C5.32 19.46 5.44 19.54 5.54 19.63C5.6 19.68 5.65 19.73 5.71 19.77C5.89 19.92 6.09 20.06 6.28 20.2C6.35 20.25 6.41 20.29 6.48 20.34C6.67 20.47 6.87 20.59 7.08 20.7C7.15 20.74 7.23 20.79 7.3 20.83C7.5 20.94 7.71 21.04 7.93 21.13C8.01 21.17 8.09 21.21 8.17 21.24C8.39 21.33 8.61 21.41 8.83 21.48C8.91 21.51 8.99 21.54 9.07 21.56C9.31 21.63 9.55 21.69 9.79 21.75C9.86 21.77 9.93 21.79 10.01 21.8C10.29 21.86 10.57 21.9 10.86 21.93C10.9 21.93 10.94 21.94 10.98 21.95C11.32 21.98 11.66 22 12 22C12.34 22 12.68 21.98 13.01 21.95C13.05 21.95 13.09 21.94 13.13 21.93C13.42 21.9 13.7 21.86 13.98 21.8C14.05 21.79 14.12 21.76 14.2 21.75C14.44 21.69 14.69 21.64 14.92 21.56C15 21.53 15.08 21.5 15.16 21.48C15.38 21.4 15.61 21.33 15.82 21.24C15.9 21.21 15.98 21.17 16.06 21.13C16.27 21.04 16.48 20.94 16.69 20.83C16.77 20.79 16.84 20.74 16.91 20.7C17.11 20.58 17.31 20.47 17.51 20.34C17.58 20.3 17.64 20.25 17.71 20.2C17.91 20.06 18.1 19.92 18.28 19.77C18.34 19.72 18.39 19.67 18.45 19.63C18.56 19.54 18.67 19.45 18.77 19.36C18.77 19.35 18.77 19.35 18.76 19.34C20.75 17.51 22 14.9 22 12ZM16.94 16.97C14.23 15.15 9.79 15.15 7.06 16.97C6.62 17.26 6.26 17.6 5.96 17.97C4.44 16.43 3.5 14.32 3.5 12C3.5 7.31 7.31 3.5 12 3.5C16.69 3.5 20.5 7.31 20.5 12C20.5 14.32 19.56 16.43 18.04 17.97C17.75 17.6 17.38 17.26 16.94 16.97Z" fill="#ff0000"></path> <path d="M12 6.92969C9.93 6.92969 8.25 8.60969 8.25 10.6797C8.25 12.7097 9.84 14.3597 11.95 14.4197C11.98 14.4197 12.02 14.4197 12.04 14.4197C12.06 14.4197 12.09 14.4197 12.11 14.4197C12.12 14.4197 12.13 14.4197 12.13 14.4197C14.15 14.3497 15.74 12.7097 15.75 10.6797C15.75 8.60969 14.07 6.92969 12 6.92969Z" fill="#ff0000"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <?php
                                            $tFirst = trim($task['teacher_first_name'] ?? '');
                                            $tMiddle = trim($task['teacher_middle_name'] ?? '');
                                            $tLast = trim($task['teacher_last_name'] ?? '');
                                            $teacher_name = trim($tFirst . ' ' . ($tMiddle ? $tMiddle . ' ' : '') . $tLast);
                                        ?>
                                        <div class="teacher-name"><?php echo htmlspecialchars($teacher_name !== '' ? $teacher_name : 'Teacher'); ?></div>
                                        <div class="teacher-email"><?php echo htmlspecialchars($task['teacher_email'] ?? ''); ?></div>
                                        <div class="detail-grid">
                                            <div class="detail-chip">📌 <?php echo htmlspecialchars($task['title']); ?></div>
                                            <div class="detail-chip">🏫 <?php echo htmlspecialchars($task['room']); ?></div>
                                            <div class="detail-chip">⏳ Due: <?php echo htmlspecialchars($task['due_date'] . ' ' . $task['due_time']); ?></div>
                                            <div class="detail-chip">✅ Completed: <?php echo date('M d, Y • h:i A', strtotime($task['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 120 160" fill="none" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 100px; height: 140px;">
                            <!-- Clipboard outline -->
                        <rect x="15" y="20" width="90" height="140" rx="8" ry="8"/>
                        
                        <!-- "COMPLETED" Label -->
                        <text x="60" y="50" font-size="35" fill="#ff0000" text-anchor="middle" font-family="Arial, sans-serif" letter-spacing="1">☑</text>
                            <line x1="35" y1="60" x2="85" y2="60"/>
                            
                            <!-- First checkbox and lines -->
                            <circle cx="30" cy="80" r="6" fill="white"/>
                            <circle cx="30" cy="80" r="6"/>
                            <line x1="45" y1="75" x2="90" y2="75"/>
                            <line x1="45" y1="85" x2="90" y2="85"/>
                            
                            <!-- Second checkbox and lines -->
                            <circle cx="30" cy="110" r="6" fill="white"/>
                            <circle cx="30" cy="110" r="6"/>
                            <line x1="45" y1="105" x2="90" y2="105"/>
                            <line x1="45" y1="115" x2="90" y2="115"/>
                            
                            <!-- Third checkbox and lines -->
                            <circle cx="30" cy="140" r="6" fill="white"/>
                            <circle cx="30" cy="140" r="6"/>
                            <line x1="45" y1="135" x2="90" y2="135"/>
                            <line x1="45" y1="145" x2="90" y2="145"/>
                        </svg>
                    </div>
                    <div class="empty-text">No completed tasks yet</div>
                    <div class="empty-subtext">Tasks you complete will appear here</div>
                </div>
            <?php endif; ?>
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

        function openTaskModal(id) {
            const modal = document.getElementById(`modal-${id}`);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeTaskModal(id) {
            const modal = document.getElementById(`modal-${id}`);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        }

        // Rating Modal Functions
        function getRatingSVG(rating) {
            let starsHtml = '<div class="modal-rating-stars">';
            for (let i = 1; i <= 5; i++) {
                starsHtml += `<svg class="star-svg ${i <= rating ? 'filled' : 'empty'}" viewBox="0 0 1920 1920" xmlns="http://www.w3.org/2000/svg"><path d="M1915.918 737.475c-10.955-33.543-42.014-56.131-77.364-56.131h-612.029l-189.063-582.1v-.112C1026.394 65.588 995.335 43 959.984 43c-35.237 0-66.41 22.588-77.365 56.245L693.443 681.344H81.415c-35.35 0-66.41 22.588-77.365 56.131-10.955 33.544.79 70.137 29.478 91.03l495.247 359.831-189.177 582.212c-10.955 33.657 1.13 70.25 29.817 90.918 14.23 10.278 30.946 15.487 47.66 15.487 16.716 0 33.432-5.21 47.775-15.6l495.134-359.718 495.021 359.718c28.574 20.781 67.087 20.781 95.662.113 28.687-20.668 40.658-57.261 29.703-91.03l-189.176-582.1 495.36-359.83c28.574-20.894 40.433-57.487 29.364-91.03" fill-rule="evenodd"></path></svg>`;
            }
            starsHtml += '</div>';
            return starsHtml;
        }

        function getRatingMessage(rating) {
            const messages = {
                1: "I believe you have potential, but this task showed areas that need more attention and practice. I'm confident you'll improve with more effort.",
                2: "Your work was below the expected standard. Focus on understanding the fundamentals better and don't hesitate to ask for help when needed.",
                3: "Good job! You completed the task well. With a bit more refinement and attention to detail, you could achieve excellent results.",
                4: "I'm impressed with your attention to detail. You didn't need much guidance, which shows great progress",
                5: "Excellent work! Your performance was outstanding. You showed exceptional skill, independence, and dedication. Keep up this level of excellence!"
            };
            return messages[rating] || "Thank you for completing this task!";
        }

        function openRatingModal(rating, teacherName, teacherPhoto, message) {
            const modal = document.getElementById('ratingModal');
            const starsContainer = document.getElementById('ratingStarsContainer');
            const teacherNameEl = document.getElementById('ratingTeacherName');
            const avatarEl = document.getElementById('ratingTeacherAvatar');
            const messageEl = document.getElementById('ratingMessageText');

            // Set teacher name
            teacherNameEl.textContent = teacherName;

            // Set teacher avatar
            if (teacherPhoto && teacherPhoto.trim() !== '') {
                avatarEl.innerHTML = `<img src="${teacherPhoto}" alt="${teacherName}" class="rating-teacher-avatar">`;
            } else {
                avatarEl.innerHTML = '<div class="rating-teacher-avatar-default">👨‍🏫</div>';
            }

            // Generate star SVGs
            starsContainer.innerHTML = getRatingSVG(rating);

            // Set message
            messageEl.textContent = message;

            // Show modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeRatingModal() {
            const modal = document.getElementById('ratingModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }
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

    <!-- Rating Modal -->
    <div class="rating-modal" id="ratingModal" onclick="if(event.target === this) closeRatingModal()">
        <div class="rating-modal-container">
            <div class="rating-modal-header">
                <h2 class="rating-modal-title">Teacher's Rating</h2>
                <button class="rating-modal-close" onclick="closeRatingModal()">×</button>
            </div>
            <div class="rating-modal-body">
                <div class="rating-teacher-info">
                    <div id="ratingTeacherAvatar" class="rating-teacher-avatar-default">
                        👨‍🏫
                    </div>
                    <div class="rating-teacher-name" id="ratingTeacherName">Teacher Name</div>
                </div>
                <div class="rating-stars-container" id="ratingStarsContainer">
                </div>
                <div class="rating-message-box">
                    <div class="rating-message-label">Teacher's Feedback</div>
                    <div class="rating-message-text" id="ratingMessageText">Loading feedback...</div>
                </div>
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
        <a href="student_history.php" class="bottom-nav-item active" title="History">
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
</body>
</html>


