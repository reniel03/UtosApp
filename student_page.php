<?php
session_start();
include 'db_connect.php';

// Check if the user is logged in as a student
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_email = $_SESSION['email'];

// Fetch only tasks where student has applied (accepted via student_todos) and not completed
$tasks = [];
$query = "SELECT t.*, st.is_completed, st.status, st.created_at as applied_at, tc.photo as teacher_photo, tc.first_name, tc.last_name
          FROM tasks t
          INNER JOIN student_todos st ON t.id = st.task_id AND st.student_email = ?
          LEFT JOIN teachers tc ON t.teacher_email = tc.email
          WHERE st.is_completed = 0
          ORDER BY st.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// Count stats
$total_tasks = count($tasks);
$pending_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$approved_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'approved'));
$completed_tasks = count(array_filter($tasks, fn($t) => $t['is_completed']));

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap');
        
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

        /* Main Content */
        .main-wrapper {
            padding-top: 20px;
            padding-bottom: 0;
            height: auto;
        }

        .page-header {
            text-align: center;
            margin-top: 60px;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-title {
            font-size: 500%;
            color: #ff0000;
            font-weight: 800;
            margin-bottom: 15px;
            margin-top: 20px;
            text-shadow: 0 2px 10px rgba(255, 0, 0, 0.15);
        }

        .page-subtitle {
            font-size: 1.4em;
            color: #666;
            font-weight: 500;
        }

        /* View All Button */
        .view-all-section {
            text-align: center;
            padding: 40px 20px;
            animation: fadeInUp 0.6s ease 0.2s both;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 50vh;
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

        .view-all-btn {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            border: none;
            padding: 20px 60px;
            border-radius: 30px;
            font-size: 1.5em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 25px rgba(255, 0, 0, 0.2);
            text-decoration: none;
            display: inline-block;
        }

        .view-all-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 35px rgba(255, 0, 0, 0.3);
            background: linear-gradient(135deg, #ff1a1a, #ff5555);
        }

        .view-all-icon {
            font-size: 6em;
            margin-bottom: 25px;
        }

        .view-all-title {
            font-size: 2.5em;
            color: #333;
            font-weight: 700;
            margin-bottom: 35px;
        }

        /* Tasks Container */
        .tasks-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Recent Section Header */
        .recent-header {
            padding: 20px 20px 10px 20px;
            font-size: 2.4em;
            font-weight: 700;
            color: #333;
            margin-top: 20px;
        }

        /* Task Card - List Layout */
        .task-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            animation: slideInCard 0.5s ease backwards;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            padding: 20px;
        }

        .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 0, 0, 0.15);
        }

        .task-icon {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            background-size: cover;
            background-position: center;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .task-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .task-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-header {
            background: none;
            padding: 0;
            color: #333;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .task-title {
            font-size: 1.5em;
            font-weight: 700;
            margin: 0;
            color: #333;
            line-height: 1.3;
        }

        .task-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            color: #888;
            margin: 6px 0 0 0;
        }

        .task-location-time {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .task-location {
            font-size: 2em;
            color: #000;
            font-weight: 500;
            white-space: nowrap;
        }

        .task-datetime {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 0.95em;
            color: #888;
        }

        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #888;
            font-size: 1.15em;
        }

        .task-body {
            padding: 0;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .task-description {
            background: none;
            padding: 0;
            border: none;
            margin: 0;
            font-size: 1.05em;
            color: #666;
            line-height: 1.4;
            display: none;
        }

        .task-status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 14px;
            font-size: 1.1em;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
        }

        .task-status.completed {
            background: #d4edda;
            color: #155724;
        }

        .task-status.approved {
            background: #d4edda;
            color: #155724;
        }

        .task-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .task-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            animation: fadeIn 0.4s ease;
        }

        .empty-icon {
            font-size: 3.5em;
            margin-bottom: 15px;
        }

        .empty-title {
            font-size: 1.4em;
            color: #333;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 0.95em;
            color: #888;
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
            html {
                font-size: 14px;
            }

            .main-wrapper {
                padding-top: 15px;
                padding-bottom: 0;
                height: auto;
            }

            .page-title {
                font-size: 3.8em;
                margin-top: 10px;
            }

            .page-subtitle {
                font-size: 1.6em;
            }

            .view-all-section {
                padding: 50px 15px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 50vh;
            }

            .view-all-icon {
                font-size: 2.5em;
            }

            .view-all-title {
                font-size: 2.2em;
                margin-bottom: 15px;
            }

            .view-all-btn {
                padding: 12px 30px;
                font-size: 1.1em;
            }

            .tasks-container {
                padding: 0 15px;
            }

            .task-card {
                margin-bottom: 16px;
                padding: 16px;
                gap: 14px;
            }

            .task-icon {
                width: 70px;
                height: 70px;
                font-size: 28px;
                border-radius: 50%;
                border-width: 2px;
            }

            .task-title {
                font-size: 1.05em;
            }

            .task-meta {
                gap: 6px;
            }

            .task-location-time {
                display: flex;
                gap: 16px;
                align-items: center;
            }

            .task-location {
                font-size: 1.6em;
            }

            .task-datetime {
                font-size: 0.95em;
            }

            .task-status {
                font-size: 0.95em;
                padding: 5px 12px;
            }

            .recent-header {
                padding: 15px 15px 10px 15px;
                font-size: 1.8em;
            }

            .empty-state {
                padding: 35px 15px;
            }

            .empty-icon {
                font-size: 2.5em;
                margin-bottom: 12px;
            }

            .empty-title {
                font-size: 1.1em;
            }

            .empty-text {
                font-size: 0.85em;
            }
        }

        @media (max-width: 480px) {
            html {
                font-size: 12px;
            }

            .recent-header {
                font-size: 1.4em;
            }

            .task-details-close {
                color: #ff0000;
            }

            .task-details-close svg {
                stroke: #ff0000 !important;
            }

            .task-details-content {
                max-height: 100vh;
                height: 100vh;
                margin-top: 20px;
            }

            .main-wrapper {
                padding-top: 10px;
                padding-bottom: 0;
                height: auto;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-title {
                font-size: 3em;
                margin-top: 8px;
            }

            .page-subtitle {
                font-size: 1.4em;
            }

            .view-all-section {
                padding: 60px 10px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 60vh;
            }

            .view-all-icon {
                font-size: 2.2em;
            }

            .view-all-title {
                font-size: 1.8em;
                margin-bottom: 12px;
            }

            .view-all-btn {
                padding: 10px 25px;
                font-size: 1em;
            }

            .tasks-container {
                padding: 0 10px;
            }

            .task-card {
                margin-bottom: 16px;
                padding: 14px;
                gap: 12px;
            }

            .task-icon {
                width: 60px;
                height: 60px;
                font-size: 24px;
                border-radius: 50%;
                border-width: 2px;
            }

            .task-title {
                font-size: 0.95em;
            }

            .task-meta {
                gap: 5px;
            }

            .task-location-time {
                display: flex;
                gap: 12px;
                align-items: center;
            }

            .task-location {
                font-size: 1.4em;
            }

            .task-datetime {
                font-size: 0.85em;
            }

            .task-status {
                font-size: 0.75em;
                padding: 4px 10px;
            }

            .empty-state {
                padding: 25px 10px;
            }

            .empty-icon {
                font-size: 2em;
            }
        }

        /* Task Action Buttons */
        .task-action-btn {
            background: linear-gradient(135deg, #ff0000, #ff4444);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(255, 0, 0, 0.2);
        }

        .task-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 0, 0, 0.3);
        }

        .task-action-btn:active {
            transform: translateY(0);
        }

        .task-action-btn.done-btn {
            background: linear-gradient(135deg, #28a745, #5cb85c);
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.2);
        }

        .task-action-btn.done-btn:hover {
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        /* Task Details Modal */
        .task-details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            animation: fadeIn 0.3s ease;
            align-items: flex-end;
            justify-content: center;
        }

        .task-details-modal.show {
            display: flex;
        }

        .task-details-content {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-width: 100%;
            max-height: 125vh;
            overflow-y: auto;
            box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
        }

        .task-details-header {
            background: white;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .task-details-close {
            background: none;
            border: none;
            color: #ff0000;
            font-size: 40px;
            cursor: pointer;
            width: auto;
            height: auto;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            margin: 0 auto;
        }

        .task-details-close:hover {
            color: #cc0000;
            transform: none;
        }

        .task-details-header-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
            justify-content: center;
            flex: 1;
        }

        .task-details-header-time {
            font-size: 1.4em;
            color: #000;
            text-align: center;
            font-weight: 600;
            margin-top: 20px;
            margin-right: 30px;
        }

        .task-details-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .task-details-meta {
            display: none;
        }

        .task-details-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .task-details-body {
            padding: 0;
            background: #f9f9f9;
        }

        /* Status Section */
        .task-status-section {
            padding: 30px;
            background: #fff5f5;
            border: 2px solid #ff0000;
            margin: 0;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
        }

        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 0;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            flex-shrink: 0;
        }

        .status-icon svg {
            width: 60px;
            height: 60px;
            animation: sandfall 2.5s ease-in-out infinite;
        }

        @keyframes sandfall {
            0% {
                opacity: 0.8;
                transform: translateY(0) scale(1);
            }
            50% {
                opacity: 1;
                transform: translateY(8px) scale(1);
            }
            100% {
                opacity: 0.8;
                transform: translateY(0) scale(1);
            }
        }

        .status-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-label {
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }

        /* Task Image Section */
        .task-image-section {
            padding: 3px;
            background: white;
            border: 2px solid #ff0000;
            border-bottom: 2px solid #ff0000;
            text-align: center;
            display: block;
            margin: 0;
            min-height: auto;
        }

        .task-image-section.hidden {
            display: none;
        }

        .task-image-container {
            width: 100%;
            max-width: 100%;
            min-height: 120px;
            height: auto;
            border-radius: 12px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .task-image-container img {
            width: 100%;
            height: auto;
            object-fit: contain;
            display: block;
            max-height: 400px;
        }

        .task-image-placeholder {
            width: 100%;
            min-height: 50px;
            background: #fff5f5;
            border: 2px dashed #ff0000;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff0000;
            font-size: 0.95em;
        }
        
        .task-modal-image-placeholder {
            width: 100%;
            min-height: 50px;
            background: #fff5f5;
            border: 2px dashed #ff0000;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff0000;
        }

        @media (max-width: 768px) {
            .task-image-section {
                padding: 3px;
                border: 2px solid #ff0000;
            }

            .task-image-container {
                min-height: auto;
            }

            .task-modal-image-placeholder {
                min-height: 50px;
            }

            .task-image-container img {
                max-height: 300px;
            }

            .task-details-value {
                color: #666 !important;
            }
        }

        @media (max-width: 480px) {
            .task-image-section {
                padding: 3px;
                border: 2px solid #ff0000;
            }

            .task-image-container {
                min-height: auto;
                border-radius: 8px;
            }

            .task-modal-image-placeholder {
                min-height: 50px;
                border-radius: 8px;
            }

            .task-image-container img {
                max-height: 250px;
            }

            .task-details-value {
                color: #666 !important;
            }
        }

        /* Location Details Section */
        .location-section {
            padding: 30px;
            background: white;
            border: 2px solid #ff0000;
            border-top: none;
            text-align: center;
        }

        .location-header {
            font-size: 1.2em;
            color: #000;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .location-item {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            padding-left: 0;
            position: relative;
            justify-content: center;
            align-items: center;
        }

        .location-item:last-child {
            margin-bottom: 0;
        }

        .location-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
            position: absolute;
            left: 0;
            top: 2px;
        }

        .location-dot.start {
            display: none;
        }

        .location-dot.end {
            background: #ff0000;
        }

        .location-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .location-name {
            font-weight: 600;
            color: #999;
            font-size: 1.1em;
        }

        .location-time {
            font-size: 0.95em;
            color: #999;
            display: none;
        }

        /* Description Section */
        .task-details-section {
            padding: 30px;
            background: white;
            border: 2px solid #ff0000;
            border-top: none;
            text-align: center;
        }

        .task-details-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            font-size: 1.3em;
        }

        .task-details-value {
            background: transparent;
            padding: 0;
            color: #ff0000;
            line-height: 1.6;
            word-wrap: break-word;
            font-size: 1.05em;
        }

        .task-details-actions {
            display: flex;
            gap: 10px;
            padding: 20px;
            margin: 0;
            border: 2px solid #ff0000;
            border-top: none;
            background: white;
            position: sticky;
            bottom: 0;
            justify-content: center;
        }

        .task-details-actions button {
            flex: 1;
            padding: 16px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.05em;
        }

        .task-details-actions .close-modal-btn {
            background: #ff0000 !important;
            color: white !important;
            border: 2px solid #ff0000 !important;
            box-shadow: 0 3px 10px rgba(255, 0, 0, 0.3) !important;
        }

        .task-details-actions .close-modal-btn:hover {
            background: #cc0000 !important;
            border-color: #cc0000 !important;
            box-shadow: 0 5px 15px rgba(204, 0, 0, 0.4) !important;
        }

        .task-details-actions .done-modal-btn {
            background: linear-gradient(135deg, #28a745, #5cb85c);
            color: white;
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.2);
        }

        .task-details-actions .done-modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
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

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">To-Do List</h1>
            <p class="page-subtitle">All your assigned tasks</p>
        </div>

        <!-- View All Accepted Tasks -->
        <?php if (count($tasks) > 0): ?>
            <div class="recent-header">Recent</div>

            <div class="tasks-container">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card" onclick="viewTaskDetails(<?php echo htmlspecialchars(json_encode($task)); ?>)" style="cursor: pointer;">
                        <div class="task-icon">
                            <?php 
                                $photo_path = '';
                                if (!empty($task['teacher_photo'])) {
                                    // Try multiple possible paths
                                    $possible_paths = [
                                        'uploads/profiles/' . $task['teacher_photo'],
                                        'uploads/' . $task['teacher_photo'],
                                        $task['teacher_photo']
                                    ];
                                    
                                    foreach ($possible_paths as $path) {
                                        if (file_exists($path)) {
                                            $photo_path = $path;
                                            break;
                                        }
                                    }
                                }
                                
                                if (!empty($photo_path)):
                            ?>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Teacher" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <?php endif; ?>
                                <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: <?php echo empty($photo_path) ? 'flex' : 'none'; ?>; align-items: center; justify-content: center;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M22 12C22 6.49 17.51 2 12 2C6.49 2 2 6.49 2 12C2 14.9 3.25 17.51 5.23 19.34C5.23 19.35 5.23 19.35 5.22 19.36C5.32 19.46 5.44 19.54 5.54 19.63C5.6 19.68 5.65 19.73 5.71 19.77C5.89 19.92 6.09 20.06 6.28 20.2C6.35 20.25 6.41 20.29 6.48 20.34C6.67 20.47 6.87 20.59 7.08 20.7C7.15 20.74 7.23 20.79 7.3 20.83C7.5 20.94 7.71 21.04 7.93 21.13C8.01 21.17 8.09 21.21 8.17 21.24C8.39 21.33 8.61 21.41 8.83 21.48C8.91 21.51 8.99 21.54 9.07 21.56C9.31 21.63 9.55 21.69 9.79 21.75C9.86 21.77 9.93 21.79 10.01 21.8C10.29 21.86 10.57 21.9 10.86 21.93C10.9 21.93 10.94 21.94 10.98 21.95C11.32 21.98 11.66 22 12 22C12.34 22 12.68 21.98 13.01 21.95C13.05 21.95 13.09 21.94 13.13 21.93C13.42 21.9 13.7 21.86 13.98 21.8C14.05 21.79 14.12 21.76 14.2 21.75C14.44 21.69 14.69 21.64 14.92 21.56C15 21.53 15.08 21.5 15.16 21.48C15.38 21.4 15.61 21.33 15.82 21.24C15.9 21.21 15.98 21.17 16.06 21.13C16.27 21.04 16.48 20.94 16.69 20.83C16.77 20.79 16.84 20.74 16.91 20.7C17.11 20.58 17.31 20.47 17.51 20.34C17.58 20.3 17.64 20.25 17.71 20.2C17.91 20.06 18.1 19.92 18.28 19.77C18.34 19.72 18.39 19.67 18.45 19.63C18.56 19.54 18.67 19.45 18.77 19.36C18.77 19.35 18.77 19.35 18.76 19.34C20.75 17.51 22 14.9 22 12ZM16.94 16.97C14.23 15.15 9.79 15.15 7.06 16.97C6.62 17.26 6.26 17.6 5.96 17.97C4.44 16.43 3.5 14.32 3.5 12C3.5 7.31 7.31 3.5 12 3.5C16.69 3.5 20.5 7.31 20.5 12C20.5 14.32 19.56 16.43 18.04 17.97C17.75 17.6 17.38 17.26 16.94 16.97Z" fill="#ff0000"></path> <path d="M12 6.92969C9.93 6.92969 8.25 8.60969 8.25 10.6797C8.25 12.7097 9.84 14.3597 11.95 14.4197C11.98 14.4197 12.02 14.4197 12.04 14.4197C12.06 14.4197 12.09 14.4197 12.11 14.4197C12.12 14.4197 12.13 14.4197 12.13 14.4197C14.15 14.3497 15.74 12.7097 15.75 10.6797C15.75 8.60969 14.07 6.92969 12 6.92969Z" fill="#ff0000"></path> </g></svg>
                        </div>
                        <div class="task-content">
                            <div class="task-header">
                                <div>
                                    <div class="task-meta">
                                        <div class="task-location-time">
                                            <div class="task-location"><?php echo htmlspecialchars($task['room']); ?></div>
                                        </div>
                                        <div class="task-datetime">
                                            <div class="task-meta-item"><?php echo date('M d, Y', strtotime($task['due_date'])) . ', '; ?><?php echo date('g:i A', strtotime($task['due_time'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="task-body">
                                <span class="task-status <?php 
                                    if ($task['is_completed']) echo 'completed'; 
                                    elseif ($task['status'] === 'ongoing' || $task['status'] === 'approved') echo 'approved'; 
                                    elseif ($task['status'] === 'rejected') echo 'rejected';
                                    else echo 'pending'; 
                                ?>">
                                    <?php 
                                        if ($task['is_completed']) echo 'Completed';
                                        elseif ($task['status'] === 'ongoing' || $task['status'] === 'approved') echo 'Approved';
                                        elseif ($task['status'] === 'rejected') echo 'Rejected';
                                        else echo 'Pending';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="view-all-section">
                <div class="view-all-icon">
                    <svg viewBox="0 0 120 160" fill="none" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 100px; height: 140px;">
                        <!-- Clipboard outline -->
                        <rect x="15" y="20" width="90" height="140" rx="8" ry="8"/>
                        
                        <!-- "TO-DO" Label -->
                        <text x="60" y="50" font-size="20" font-weight="bold" fill="#ff0000" text-anchor="middle" font-family="Arial, sans-serif" letter-spacing="2">TO-DO</text>
                        
                        <!-- Line under TO-DO -->
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
                <div class="view-all-title">No Applied Tasks</div>
                <p style="color: #888; margin-bottom: 20px; font-size: 1.2em;">You haven't applied for any tasks yet.</p>
                <a href="student_home.php" class="view-all-btn">Go to Home</a>
            </div>
        <?php endif; ?>
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
        <a href="student_page.php" class="bottom-nav-item active" title="To-Do">
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

    <script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
            event.stopPropagation();
        }

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const profilePic = document.querySelector('.profile-pic');
            
            if (!dropdown.contains(event.target) && event.target !== profilePic) {
                dropdown.classList.remove('show');
            }
        });

        // Task Details Modal Functions
        let currentTaskData = null;

        function viewTaskDetails(taskData) {
            currentTaskData = taskData;
            const modal = document.getElementById('taskDetailsModal');
            
            // Format datetime
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            document.getElementById('modalTaskDatetime').textContent = `${dateStr}, ${timeStr}`;
            
            // Task ID
            
            // Populate modal content
            document.getElementById('modalTaskTitleValue').textContent = taskData.title;
            document.getElementById('modalTaskRoom').textContent = taskData.room;
            document.getElementById('modalTaskTime').textContent = taskData.due_time.substring(0, 5);
            document.getElementById('modalTaskDescription').textContent = taskData.description;
            
            // Handle image display
            const imageSection = document.getElementById('taskImageSection');
            const imageContainer = document.getElementById('taskImageContainer');
            
            // Check if attachments field exists and has a value
            const hasAttachment = taskData.attachments && taskData.attachments.trim() !== '' && taskData.attachments !== 'null';
            
            if (hasAttachment) {
                const imageSrc = taskData.attachments;
                imageContainer.innerHTML = `<img src="${imageSrc}" alt="Task Image" style="width: 100%; height: auto; object-fit: contain; display: block; border-radius: 12px; max-height: 150px;" onerror="this.style.display='none'">`;
                imageSection.style.display = 'block';
            } else {
                imageContainer.innerHTML = '<div class="task-modal-image-placeholder"><svg viewBox="0 0 32 32" enable-background="new 0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#ff0000" style="width: 30%; height: 30%;"><g><path d="M21.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S21.09,14.75,21.5,14.75z" fill="#ff0000"></path> <path d="M10.5,14.75c0.41,0,0.75,0.34,0.75,0.75s-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75 S10.09,14.75,10.5,14.75z" fill="#ff0000"></path> <polyline fill="none" points=" 21.5,1.5 4.5,1.5 4.5,30.5 27.5,30.5 27.5,7.5 " stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <polyline fill="none" points=" 21.5,1.5 27.479,7.5 21.5,7.5 21.5,4 " stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polyline> <path d=" M14.5,18.5c0-0.83,0.67-1.5,1.5-1.5s1.5,0.67,1.5,1.5" fill="none" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M20.75,15.5c0,0.41,0.34,0.75,0.75,0.75s0.75-0.34,0.75-0.75s-0.34-0.75-0.75-0.75S20.75,15.09,20.75,15.5z" fill="none" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path> <path d=" M11.25,15.5c0,0.41-0.34,0.75-0.75,0.75s-0.75-0.34-0.75-0.75s0.34-0.75,0.75-0.75S11.25,15.09,11.25,15.5z" fill="none" stroke="#ff0000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></path></g></svg></div>';
                imageSection.style.display = 'block';
            }
            
            const statusSection = document.getElementById('modalStatusSection');
            const statusIcon = document.getElementById('statusIcon');
            const statusLabel = document.getElementById('statusLabel');
            
            if (taskData.is_completed) {
                statusSection.style.background = '#f0f8f4';
                statusSection.style.borderColor = '#d4edda';
                const svg = statusIcon.querySelector('svg');
                if (svg) {
                    svg.setAttribute('fill', '#28a745');
                    svg.setAttribute('stroke', '#28a745');
                }
                statusLabel.textContent = 'Task Completed';
                statusLabel.style.color = '#155724';
            } else if (taskData.status === 'approved' || taskData.status === 'ongoing') {
                statusSection.style.background = '#f0f8f4';
                statusSection.style.borderColor = '#d4edda';
                const svg = statusIcon.querySelector('svg');
                if (svg) {
                    svg.setAttribute('fill', '#28a745');
                    svg.setAttribute('stroke', '#28a745');
                }
                statusLabel.textContent = 'Task Approved';
                statusLabel.style.color = '#155724';
            } else if (taskData.status === 'rejected') {
                statusSection.style.background = '#fff5f5';
                statusSection.style.borderColor = '#ffe0e0';
                const svg = statusIcon.querySelector('svg');
                if (svg) {
                    svg.setAttribute('fill', '#ff0000');
                    svg.setAttribute('stroke', '#ff0000');
                }
                statusLabel.textContent = 'Task Cancelled';
                statusLabel.style.color = '#d32f2f';
            } else {
                statusSection.style.background = '#fff5f5';
                statusSection.style.borderColor = '#ffe0e0';
                const svg = statusIcon.querySelector('svg');
                if (svg) {
                    svg.setAttribute('fill', '#ff9800');
                    svg.setAttribute('stroke', '#ff9800');
                }
                statusLabel.textContent = 'Pending Review';
                statusLabel.style.color = '#e65100';
            }
            
            // Show/hide done button based on status
            const doneBtn = document.getElementById('modalDoneBtn');
            if ((taskData.status === 'approved' || taskData.status === 'ongoing') && !taskData.is_completed) {
                doneBtn.style.display = 'block';
            } else {
                doneBtn.style.display = 'none';
            }
            
            modal.classList.add('show');
        }

        function closeTaskModal() {
            const modal = document.getElementById('taskDetailsModal');
            modal.classList.remove('show');
            currentTaskData = null;
        }

        function markTaskDone(taskId, taskTitle) {
            // Close the task modal first
            closeTaskModal();

            Swal.fire({
                title: 'Complete Task?',
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
                        ">Mark task as complete?</p>
                        <p style="
                            margin: 0;
                            font-size: 14px;
                            color: #666;
                            line-height: 1.5;
                        ">"<strong>${taskTitle}</strong>" will be marked as complete.</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Mark as Done!',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'swal2-success-popup',
                    confirmButton: 'swal2-success-button'
                },
                didOpen: (modal) => {
                    modal.style.borderRadius = '20px';
                    modal.style.boxShadow = '0 10px 40px rgba(255, 0, 0, 0.25)';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Completing...',
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

                    // Send completion request
                    const formData = new FormData();
                    formData.append('action', 'complete_task');
                    formData.append('task_id', taskId);

                    fetch('complete_task.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
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
                                        ">Task Completed Successfully!</p>
                                        <p style="
                                            margin: 0;
                                            font-size: 14px;
                                            color: #666;
                                            line-height: 1.5;
                                        ">Your task has been marked as complete.</p>
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
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.error || 'Failed to complete task',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'Network error: ' + error.message,
                            icon: 'error'
                        });
                    });
                }
            });
        }

        // Close modal when clicking outside
        document.getElementById('taskDetailsModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeTaskModal();
            }
        });

        // ==================== PAGE LOADING ANIMATION ====================
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.getAttribute('onclick') && !this.target && this.hostname === window.location.hostname) {
                    const overlay = document.getElementById('pageLoadingOverlay');
                    if (overlay) overlay.classList.add('show');
                }
            });
        });
        window.addEventListener('load', function() {
            const overlay = document.getElementById('pageLoadingOverlay');
            if (overlay) overlay.classList.remove('show');
        });
        // ==================== END PAGE LOADING ANIMATION ====================
    </script>

    <!-- Task Details Modal -->
    <div id="taskDetailsModal" class="task-details-modal">
        <div class="task-details-content">
            <!-- Header with Close -->
            <div class="task-details-header">
                <button class="task-details-close" onclick="closeTaskModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <div class="task-details-header-info">
                    <div class="task-details-header-time" id="modalTaskDatetime"></div>
                </div>
            </div>

                <!-- Status Section -->
                <div class="task-status-section" id="modalStatusSection">
                    <div class="status-icon" id="statusIcon"><svg fill="#ff0000" height="60px" width="60px" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 297 297" xml:space="preserve" stroke="#ff0000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g> <path d="M251.01,277.015h-17.683l-0.002-31.558c0-31.639-17.358-60.726-48.876-81.901c-3.988-2.682-6.466-8.45-6.466-15.055 s2.478-12.373,6.464-15.053c31.52-21.178,48.878-50.264,48.88-81.904V19.985h17.683c5.518,0,9.992-4.475,9.992-9.993 c0-5.518-4.475-9.992-9.992-9.992H45.99c-5.518,0-9.992,4.475-9.992,9.992c0,5.519,4.475,9.993,9.992,9.993h17.683v31.558 c0,31.642,17.357,60.728,48.875,81.903c3.989,2.681,6.467,8.448,6.467,15.054s-2.478,12.373-6.466,15.053 c-31.519,21.177-48.876,50.263-48.876,81.903v31.558H45.99c-5.518,0-9.992,4.475-9.992,9.993c0,5.519,4.475,9.992,9.992,9.992 h205.02c5.518,0,9.992-4.474,9.992-9.992C261.002,281.489,256.527,277.015,251.01,277.015z M83.657,245.456 c0-33.425,25.085-55.269,40.038-65.314c9.583-6.441,15.304-18.269,15.304-31.642s-5.721-25.2-15.305-31.642 c-14.952-10.046-40.037-31.89-40.037-65.315V19.985h129.686l-0.002,31.558c0,33.424-25.086,55.269-40.041,65.317 c-9.581,6.441-15.301,18.269-15.301,31.64s5.72,25.198,15.303,31.642c14.953,10.047,40.039,31.892,40.041,65.314v31.558h-3.312 c-8.215-30.879-50.138-64.441-55.377-68.537c-3.616-2.828-8.694-2.826-12.309,0c-5.239,4.095-47.163,37.658-55.378,68.537h-3.311 V245.456z M189.033,277.015h-81.067c6.584-15.391,25.383-34.873,40.534-47.76C163.652,242.142,182.45,261.624,189.033,277.015z"></path> <path d="M148.497,191.014c2.628,0,5.206-1.069,7.064-2.928c1.868-1.858,2.928-4.437,2.928-7.064s-1.06-5.206-2.928-7.065 c-1.858-1.857-4.436-2.927-7.064-2.927c-2.628,0-5.206,1.069-7.064,2.927c-1.859,1.859-2.928,4.438-2.928,7.065 s1.068,5.206,2.928,7.064C143.291,189.944,145.869,191.014,148.497,191.014z"></path> <path d="M148.5,138.019c5.519,0,9.992-4.474,9.992-9.992v-17.664c0-5.518-4.474-9.993-9.992-9.993s-9.992,4.475-9.992,9.993v17.664 C138.508,133.545,142.981,138.019,148.5,138.019z"></path> </g> </g></svg></div>
                    <div class="status-content">
                        <div class="status-label" id="statusLabel">Pending Review</div>
                    </div>
                </div>

                <div class="task-details-body">
                <!-- Task Image Section (First) -->
                <div class="task-image-section" id="taskImageSection">
                    <div class="task-image-container" id="taskImageContainer">
                    </div>
                </div>


                <!-- Task Title -->
                <div class="task-details-section">
                    <div class="task-details-label">Task Title</div>
                    <div class="task-details-value" id="modalTaskTitleValue">-</div>
                </div>

                <!-- Description -->
                <div class="task-details-section">
                    <div class="task-details-label">Description</div>
                    <div class="task-details-value" id="modalTaskDescription">-</div>
                </div>

                <!-- Location Details -->
                <div class="location-section">
                    <div class="location-header">Location</div>
                    <div class="location-item">
                        <div class="location-info">
                            <div class="location-name" id="modalTaskRoom">Room -</div>
                            <div class="location-time" id="modalTaskTime">-</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="task-details-actions">
                    <button class="close-modal-btn" onclick="closeTaskModal()">Close</button>
                    <button class="task-action-btn done-modal-btn" id="modalDoneBtn" onclick="markTaskDone(currentTaskData.id, currentTaskData.title)" style="display: none;">Mark as Done</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>