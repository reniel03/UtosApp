<?php
session_start();
include 'db_connect.php';

// Check if user is a student
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_email = $_SESSION['email'];

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, pending, approved, rejected, completed

// Build the query based on filter
$where_clause = "WHERE st.student_email = ?";
$params = [$student_email];

if ($filter === 'pending') {
    $where_clause .= " AND st.status NOT IN ('approved', 'rejected')";
} elseif ($filter === 'approved') {
    $where_clause .= " AND st.status = 'approved' AND st.is_completed = 0";
} elseif ($filter === 'rejected') {
    $where_clause .= " AND st.status = 'rejected'";
} elseif ($filter === 'completed') {
    $where_clause .= " AND st.is_completed = 1";
}

// Fetch applications with task information
$query = "SELECT 
            st.id as todo_id,
            st.task_id,
            st.status,
            st.is_completed,
            st.created_at as applied_date,
            st.rating,
            st.rated_at,
            t.title as task_title,
            t.description as task_description,
            t.due_date,
            t.due_time,
            t.teacher_email,
            tr.first_name as teacher_first_name,
            tr.middle_name as teacher_middle_name,
            tr.last_name as teacher_last_name,
            tr.photo as teacher_photo
            FROM student_todos st
            INNER JOIN tasks t ON st.task_id = t.id
            LEFT JOIN teachers tr ON t.teacher_email = tr.email
            $where_clause
            ORDER BY st.created_at DESC";

$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param('s', $student_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $applications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $applications = [];
}

// Get summary statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN st.status NOT IN ('approved', 'rejected') THEN 1 END) as pending_count,
                COUNT(CASE WHEN st.status = 'approved' AND st.is_completed = 0 THEN 1 END) as approved_count,
                COUNT(CASE WHEN st.is_completed = 1 THEN 1 END) as completed_count,
                COUNT(CASE WHEN st.status = 'rejected' THEN 1 END) as rejected_count
                FROM student_todos st
                WHERE st.student_email = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param('s', $student_email);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        }

        .header h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: #667eea;
            color: white;
        }

        .filter-btn:hover {
            background: #667eea;
            color: white;
        }

        .applications-section {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            background: #f8f9fa;
            padding: 20px 30px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #667eea;
        }

        .applications-list {
            padding: 20px;
        }

        .application-card {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
        }

        .application-card:hover {
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transform: translateX(5px);
        }

        .application-card.pending {
            border-left-color: #ffc107;
        }

        .application-card.approved {
            border-left-color: #28a745;
        }

        .application-card.rejected {
            border-left-color: #dc3545;
        }

        .application-card.completed {
            border-left-color: #17a2b8;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .task-info {
            flex: 1;
        }

        .task-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .teacher-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .teacher-name {
            color: #666;
            font-size: 14px;
        }

        .task-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .card-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-message {
            background: #667eea;
            color: white;
        }

        .btn-message:hover {
            background: #5568d3;
        }

        .btn-rating {
            background: #ffc107;
            color: #333;
        }

        .btn-rating:hover {
            background: #ffb300;
        }

        .rating-display {
            background: white;
            padding: 10px 15px;
            border-radius: 5px;
            color: #ffc107;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .empty-state-text {
            font-size: 16px;
        }

        .back-btn {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border: 2px solid #667eea;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .header {
                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
            }

            .status-badge {
                margin-top: 10px;
            }

            .card-footer {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="student_home.php" class="back-btn">← Back to Dashboard</a>

        <div class="header">
            <h1>📝 My Task Applications</h1>
            
            <div class="stats">
                <div class="stat-box" style="cursor: pointer;" onclick="setFilter('all')">
                    <span class="stat-number"><?php echo $stats['pending_count'] + $stats['approved_count'] + $stats['rejected_count'] + $stats['completed_count']; ?></span>
                    <span class="stat-label">Total Applications</span>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); cursor: pointer;" onclick="setFilter('pending')">
                    <span class="stat-number"><?php echo $stats['pending_count']; ?></span>
                    <span class="stat-label">Pending Review</span>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); cursor: pointer;" onclick="setFilter('approved')">
                    <span class="stat-number"><?php echo $stats['approved_count']; ?></span>
                    <span class="stat-label">In Progress</span>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #17a2b8 0%, #00bcd4 100%); cursor: pointer;" onclick="setFilter('completed')">
                    <span class="stat-number"><?php echo $stats['completed_count']; ?></span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>

            <div class="filters">
                <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="setFilter('all')">All</button>
                <button class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>" onclick="setFilter('pending')">⏳ Pending</button>
                <button class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>" onclick="setFilter('approved')">✅ In Progress</button>
                <button class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>" onclick="setFilter('completed')">✔️ Completed</button>
                <button class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>" onclick="setFilter('rejected')">❌ Rejected</button>
            </div>
        </div>

        <div class="applications-section">
            <div class="section-title">
                <?php 
                if ($filter === 'pending') {
                    echo "⏳ Applications Awaiting Review (" . count($applications) . ")";
                } elseif ($filter === 'approved') {
                    echo "✅ In Progress (" . count($applications) . ")";
                } elseif ($filter === 'rejected') {
                    echo "❌ Rejected Applications (" . count($applications) . ")";
                } elseif ($filter === 'completed') {
                    echo "✔️ Completed Tasks (" . count($applications) . ")";
                } else {
                    echo "📝 All Applications (" . count($applications) . ")";
                }
                ?>
            </div>

            <div class="applications-list">
                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <div class="empty-state-text">
                            <?php
                            if ($filter === 'pending') {
                                echo "No applications pending review.<br><small style='color:#aaa;'>All your applications have been reviewed.</small>";
                            } elseif ($filter === 'approved') {
                                echo "No tasks in progress.<br><small style='color:#aaa;'>Wait for teachers to approve your applications.</small>";
                            } elseif ($filter === 'rejected') {
                                echo "No rejected applications!<br><small style='color:#aaa;'>Keep up the good work!</small>";
                            } elseif ($filter === 'completed') {
                                echo "No completed tasks yet.<br><small style='color:#aaa;'>Start applying for tasks to complete them.</small>";
                            } else {
                                echo "No applications found.<br><small style='color:#aaa;'>Start applying for tasks now!</small>";
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <div class="application-card <?php echo $app['is_completed'] ? 'completed' : ($app['status'] === 'approved' ? 'approved' : ($app['status'] === 'rejected' ? 'rejected' : 'pending')); ?>">
                            <div class="card-header">
                                <div class="task-info">
                                    <div class="task-title">📝 <?php echo htmlspecialchars($app['task_title']); ?></div>
                                    <div class="teacher-info">
                                        <span>👨‍🏫</span>
                                        <span class="teacher-name">
                                            <?php echo htmlspecialchars($app['teacher_first_name'] . ' ' . ($app['teacher_middle_name'] ? substr($app['teacher_middle_name'], 0, 1) . '. ' : '') . $app['teacher_last_name']); ?>
                                        </span>
                                    </div>
                                    <div class="task-meta">
                                        <span class="meta-item">📅 Due: <?php echo date('M d, Y H:i', strtotime($app['due_date'] . ' ' . $app['due_time'])); ?></span>
                                        <span class="meta-item">📨 Applied: <?php echo date('M d, Y', strtotime($app['applied_date'])); ?></span>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo 'status-' . ($app['is_completed'] ? 'completed' : ($app['status'] === 'approved' ? 'approved' : ($app['status'] === 'rejected' ? 'rejected' : 'pending'))); ?>">
                                    <?php
                                    if ($app['is_completed']) {
                                        echo '✔️ Completed';
                                    } elseif ($app['status'] === 'approved') {
                                        echo '✅ In Progress';
                                    } elseif ($app['status'] === 'rejected') {
                                        echo '❌ Rejected';
                                    } else {
                                        echo '⏳ Pending';
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="card-footer">
                                <?php if ($app['status'] === 'approved'): ?>
                                    <a href="student_message.php?teacher_email=<?php echo urlencode($app['teacher_email']); ?>" class="btn btn-message">
                                        💬 Message Teacher
                                    </a>
                                    <?php if (!$app['is_completed'] && $app['rating']): ?>
                                        <div class="rating-display">
                                            ⭐ Rating: <?php echo $app['rating']; ?>/5
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($app['status'] === 'pending' || $app['status'] === NULL || $app['status'] === ''): ?>
                                    <span style="color: #666; font-style: italic;">Waiting for teacher's review...</span>
                                <?php elseif ($app['status'] === 'rejected'): ?>
                                    <span style="color: #dc3545; font-style: italic;">This application was rejected. Check with the teacher for feedback.</span>
                                <?php endif; ?>

                                <?php if ($app['is_completed']): ?>
                                    <span style="color: #17a2b8; font-weight: bold;">✔️ Task Completed!</span>
                                    <?php if ($app['rating']): ?>
                                        <div class="rating-display">
                                            ⭐ Rating: <?php echo $app['rating']; ?>/5
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function setFilter(filterType) {
            window.location.href = 'view_my_applications.php?filter=' + filterType;
        }
    </script>
</body>
</html>
