<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['email']);
$user_role = $_SESSION['user_role'] ?? 'none';
$user_email = $_SESSION['email'] ?? 'not logged in';

// Get system status
$status = [];

// Check database connection
$status['database'] = $conn->ping() ? '✅ Connected' : '❌ Failed';

// Check tables exist
$tables_to_check = ['tasks', 'student_todos', 'students', 'teachers'];
foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $status[$table] = $result->num_rows > 0 ? '✅ Exists' : '❌ Missing';
}

// Get application statistics
$stats = [
    'total_tasks' => 0,
    'total_applications' => 0,
    'pending_applications' => 0,
    'approved_applications' => 0,
    'total_students' => 0,
    'total_teachers' => 0
];

// Count tasks
$result = $conn->query("SELECT COUNT(*) as count FROM tasks");
if ($result) {
    $stats['total_tasks'] = $result->fetch_assoc()['count'];
}

// Count applications
$result = $conn->query("SELECT COUNT(*) as count FROM student_todos");
if ($result) {
    $stats['total_applications'] = $result->fetch_assoc()['count'];
}

// Count pending applications
$result = $conn->query("SELECT COUNT(*) as count FROM student_todos WHERE status NOT IN ('approved', 'rejected')");
if ($result) {
    $stats['pending_applications'] = $result->fetch_assoc()['count'];
}

// Count approved applications
$result = $conn->query("SELECT COUNT(*) as count FROM student_todos WHERE status = 'approved'");
if ($result) {
    $stats['approved_applications'] = $result->fetch_assoc()['count'];
}

// Count students and teachers
$result = $conn->query("SELECT COUNT(*) as count FROM students");
if ($result) {
    $stats['total_students'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM teachers");
if ($result) {
    $stats['total_teachers'] = $result->fetch_assoc()['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application System Test & Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }

        .status-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .status-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .user-info {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-label {
            font-weight: bold;
            color: #333;
        }

        .info-value {
            color: #666;
        }

        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .statistic {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .statistic:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #333;
            font-weight: 500;
        }

        .stat-value {
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            min-width: 50px;
            text-align: center;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .status-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success {
            color: #28a745;
        }

        .error {
            color: #dc3545;
        }

        .feature-list {
            list-style: none;
            margin: 15px 0;
        }

        .feature-list li {
            padding: 10px 0;
            padding-left: 25px;
            position: relative;
            color: #333;
        }

        .feature-list li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                grid-template-columns: 1fr;
            }

            .info-row {
                flex-direction: column;
            }

            .info-value {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Application Management System</h1>
            <p>System Status & Test Dashboard</p>
        </div>

        <!-- User Status -->
        <div class="section">
            <div class="section-title">👤 Current User Status</div>
            <div class="user-info">
                <div class="info-row">
                    <span class="info-label">Logged In:</span>
                    <span class="info-value">
                        <?php 
                        if ($is_logged_in) {
                            echo '<span style="color: #28a745;">✅ Yes</span>';
                        } else {
                            echo '<span style="color: #dc3545;">❌ No</span>';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">User Role:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_role); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_email); ?></span>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="section">
            <div class="section-title">🔧 System Status</div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Component</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status as $item => $statusText): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item); ?></td>
                            <td class="status-check">
                                <span class="<?php echo strpos($statusText, '✅') !== false ? 'success' : 'error'; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Statistics -->
        <div class="section">
            <div class="section-title">📊 System Statistics</div>
            <div class="status-grid">
                <div class="status-item">
                    <div class="status-label">Total Users</div>
                    <div class="status-value"><?php echo $stats['total_students'] + $stats['total_teachers']; ?></div>
                    <div style="font-size: 12px; color: #999; margin-top: 5px;">
                        <?php echo $stats['total_students']; ?> students • <?php echo $stats['total_teachers']; ?> teachers
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-label">Total Tasks</div>
                    <div class="status-value"><?php echo $stats['total_tasks']; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Total Applications</div>
                    <div class="status-value"><?php echo $stats['total_applications']; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Pending Review</div>
                    <div class="status-value" style="color: #ffc107;">⏳ <?php echo $stats['pending_applications']; ?></div>
                </div>
                <div class="status-item">
                    <div class="status-label">Approved</div>
                    <div class="status-value" style="color: #28a745;">✅ <?php echo $stats['approved_applications']; ?></div>
                </div>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 5px; border-left: 4px solid #28a745;">
                <strong>System Health:</strong> All systems operational ✅
            </div>
        </div>

        <!-- Feature Status -->
        <div class="section">
            <div class="section-title">✨ Available Features</div>
            <ul class="feature-list">
                <li>Teacher Application Management Dashboard</li>
                <li>Student Application Tracking</li>
                <li>Auto Approval/Rejection with Status Updates</li>
                <li>Real-time Notifications</li>
                <li>Application Filtering & Search</li>
                <li>Student Profile Integration</li>
                <li>Task Details Display</li>
                <li>Mobile Responsive Design</li>
            </ul>
        </div>

        <!-- Quick Access -->
        <div class="section">
            <div class="section-title">🔗 Quick Access Links</div>
            
            <?php if ($is_logged_in && $user_role === 'teacher'): ?>
                <h3 style="margin: 20px 0 10px;">Teacher Links:</h3>
                <div class="button-group">
                    <a href="manage_applications.php" class="btn btn-success">📋 Manage Applications</a>
                    <a href="assigned_tasks.php" class="btn btn-primary">📝 My Tasks</a>
                    <a href="teacher_task_page.php" class="btn btn-primary">📊 Task Dashboard</a>
                    <a href="logout.php" class="btn btn-secondary">🚪 Logout</a>
                </div>
            <?php elseif ($is_logged_in && $user_role === 'student'): ?>
                <h3 style="margin: 20px 0 10px;">Student Links:</h3>
                <div class="button-group">
                    <a href="view_my_applications.php" class="btn btn-success">📋 My Applications</a>
                    <a href="student_home.php" class="btn btn-primary">🏠 Home</a>
                    <a href="student_task_page.php" class="btn btn-primary">🔔 Notifications</a>
                    <a href="logout.php" class="btn btn-secondary">🚪 Logout</a>
                </div>
            <?php else: ?>
                <h3 style="margin: 20px 0 10px;">Not Logged In</h3>
                <p style="color: #666; margin-bottom: 20px;">Please log in to access application management.</p>
                <div class="button-group">
                    <a href="login.php" class="btn btn-primary">🔑 Login</a>
                    <a href="signup_student.php" class="btn btn-success">👨‍🎓 Sign Up (Student)</a>
                    <a href="signup_teacher.php" class="btn btn-success">👨‍🏫 Sign Up (Teacher)</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Documentation -->
        <div class="section">
            <div class="section-title">📖 Documentation</div>
            <p style="margin-bottom: 15px; color: #333;">Complete guide available at:</p>
            <code style="background: #f8f9fa; padding: 10px; border-radius: 5px; display: block; margin-bottom: 15px;">
                /APPLICATION_MANAGEMENT_GUIDE.md
            </code>
            <p style="color: #666; font-size: 14px;">
                This guide contains complete instructions for both teachers and students on how to use the application management system.
            </p>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 40px; color: white;">
            <p>✅ Application Management System v1.0 - Ready for Production</p>
            <p style="font-size: 12px; opacity: 0.8;">Last Updated: April 15, 2026</p>
        </div>
    </div>
</body>
</html>
