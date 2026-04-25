<?php
session_start();
include 'db_connect.php';

// Check if user is a teacher
if (!isset($_SESSION['email']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$teacher_email = $_SESSION['email'];

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending'; // pending, approved, rejected, all

// Build the query based on filter
$where_clause = "WHERE t.teacher_email = ?";
$params = [$teacher_email];
$types = 's';

if ($filter === 'pending') {
    $where_clause .= " AND st.status NOT IN ('approved', 'rejected')";
} elseif ($filter === 'approved') {
    $where_clause .= " AND st.status = 'approved'";
} elseif ($filter === 'rejected') {
    $where_clause .= " AND st.status = 'rejected'";
}

// Fetch applications with student information
$query = "SELECT 
            st.id as todo_id,
            st.student_email,
            st.task_id,
            st.status,
            st.created_at as applied_date,
            t.title as task_title,
            t.description as task_description,
            t.due_date,
            t.due_time,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.photo,
            s.year_level,
            s.course
            FROM student_todos st
            INNER JOIN tasks t ON st.task_id = t.id
            LEFT JOIN students s ON st.student_email = s.email
            $where_clause
            ORDER BY st.created_at DESC";

$stmt = $conn->prepare($query);

if ($stmt) {
    if ($filter === 'pending') {
        $stmt->bind_param('s', $teacher_email);
    } else {
        $stmt->bind_param($types, ...$params);
    }
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
                COUNT(CASE WHEN st.status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN st.status = 'rejected' THEN 1 END) as rejected_count
                FROM student_todos st
                INNER JOIN tasks t ON st.task_id = t.id
                WHERE t.teacher_email = ?";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param('s', $teacher_email);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications</title>
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
            max-width: 1200px;
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

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .student-email {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .student-details {
            color: #888;
            font-size: 12px;
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

        .task-info {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .task-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
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

        .card-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-view {
            background: #667eea;
            color: white;
        }

        .btn-view:hover {
            background: #5568d3;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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

        .loading {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="assigned_tasks.php" class="back-btn">← Back to Tasks</a>

        <div class="header">
            <h1>📋 Manage Student Applications</h1>
            
            <div class="stats">
                <div class="stat-box" style="---" onclick="setFilter('all')">
                    <span class="stat-number"><?php echo $stats['pending_count'] + $stats['approved_count'] + $stats['rejected_count']; ?></span>
                    <span class="stat-label">Total Applications</span>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);" onclick="setFilter('pending')">
                    <span class="stat-number"><?php echo $stats['pending_count']; ?></span>
                    <span class="stat-label">Pending Review</span>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);" onclick="setFilter('approved')">
                    <span class="stat-number"><?php echo $stats['approved_count']; ?></span>
                    <span class="stat-label">Approved</span>
                </div>
                <div class="stat-box" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);" onclick="setFilter('rejected')">
                    <span class="stat-number"><?php echo $stats['rejected_count']; ?></span>
                    <span class="stat-label">Rejected</span>
                </div>
            </div>

            <div class="filters">
                <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="setFilter('all')">All</button>
                <button class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>" onclick="setFilter('pending')">⏳ Pending</button>
                <button class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>" onclick="setFilter('approved')">✅ Approved</button>
                <button class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>" onclick="setFilter('rejected')">❌ Rejected</button>
            </div>
        </div>

        <div class="applications-section">
            <div class="section-title">
                <?php 
                if ($filter === 'pending') {
                    echo "⏳ Pending Applications (" . count($applications) . ")";
                } elseif ($filter === 'approved') {
                    echo "✅ Approved Applications (" . count($applications) . ")";
                } elseif ($filter === 'rejected') {
                    echo "❌ Rejected Applications (" . count($applications) . ")";
                } else {
                    echo "📋 All Applications (" . count($applications) . ")";
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
                                echo "No pending applications! All tasks have been reviewed.";
                            } elseif ($filter === 'approved') {
                                echo "No approved applications yet.";
                            } elseif ($filter === 'rejected') {
                                echo "No rejected applications.";
                            } else {
                                echo "No applications found.";
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <div class="application-card <?php echo $app['status']; ?>">
                            <div class="card-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        <?php echo htmlspecialchars($app['first_name'] . ' ' . ($app['middle_name'] ? substr($app['middle_name'], 0, 1) . '. ' : '') . $app['last_name']); ?>
                                    </div>
                                    <div class="student-email">📧 <?php echo htmlspecialchars($app['student_email']); ?></div>
                                    <div class="student-details">
                                        <?php
                                        $details = [];
                                        if (!empty($app['year_level'])) $details[] = $app['year_level'];
                                        if (!empty($app['course'])) $details[] = $app['course'];
                                        echo implode(' • ', $details);
                                        ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php
                                    if ($app['status'] === 'approved') {
                                        echo '✅ Approved';
                                    } elseif ($app['status'] === 'rejected') {
                                        echo '❌ Rejected';
                                    } else {
                                        echo '⏳ Pending';
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="task-info">
                                <div class="task-title">📝 <?php echo htmlspecialchars($app['task_title']); ?></div>
                                <div class="task-meta">
                                    <span class="meta-item">📅 Due: <?php echo date('M d, Y H:i', strtotime($app['due_date'] . ' ' . $app['due_time'])); ?></span>
                                    <span class="meta-item">📨 Applied: <?php echo date('M d, Y H:i', strtotime($app['applied_date'])); ?></span>
                                </div>
                            </div>

                            <?php if ($app['status'] === 'pending' || $app['status'] === NULL || $app['status'] === ''): ?>
                                <div class="card-footer">
                                    <button class="btn btn-view" onclick="viewDetails('<?php echo htmlspecialchars($app['student_email']); ?>', '<?php echo htmlspecialchars($app['task_title']); ?>')">
                                        👁️ View Details
                                    </button>
                                    <button class="btn btn-approve" onclick="approveApplication(<?php echo $app['task_id']; ?>, '<?php echo htmlspecialchars($app['student_email']); ?>', this)">
                                        ✓ Approve
                                    </button>
                                    <button class="btn btn-reject" onclick="rejectApplication(<?php echo $app['task_id']; ?>, '<?php echo htmlspecialchars($app['student_email']); ?>', this)">
                                        ✕ Reject
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="card-footer">
                                    <button class="btn btn-view" onclick="viewDetails('<?php echo htmlspecialchars($app['student_email']); ?>', '<?php echo htmlspecialchars($app['task_title']); ?>')">
                                        👁️ View Details
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function setFilter(filterType) {
            window.location.href = 'manage_applications.php?filter=' + filterType;
        }

        function approveApplication(taskId, studentEmail, button) {
            Swal.fire({
                title: 'Approve Application?',
                text: 'This student will be notified immediately.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Approve!'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeApproval(taskId, studentEmail, button, 'approved');
                }
            });
        }

        function rejectApplication(taskId, studentEmail, button) {
            Swal.fire({
                title: 'Reject Application?',
                text: 'The student will be notified about the rejection.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Reject!'
            }).then((result) => {
                if (result.isConfirmed) {
                    executeApproval(taskId, studentEmail, button, 'rejected');
                }
            });
        }

        function executeApproval(taskId, studentEmail, button, status) {
            button.disabled = true;
            button.innerHTML = '<span class="loading">⟳</span> Processing...';

            fetch('approve_student_task.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=approve_application&task_id=' + taskId + 
                      '&student_email=' + encodeURIComponent(studentEmail) + 
                      '&approval_status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find the card and update it immediately
                    const card = button.closest('.application-card');
                    const statusBadge = card.querySelector('.status-badge');
                    const cardFooter = card.querySelector('.card-footer');
                    const taskTitle = card.querySelector('.task-title').innerText;
                    
                    // Update the status badge
                    if (status === 'approved') {
                        statusBadge.className = 'status-badge status-approved';
                        statusBadge.innerHTML = '✅ Approved';
                        card.className = 'application-card approved';
                    } else {
                        statusBadge.className = 'status-badge status-rejected';
                        statusBadge.innerHTML = '❌ Rejected';
                        card.className = 'application-card rejected';
                    }
                    
                    // Replace action buttons with view-only button
                    cardFooter.innerHTML = '<button class="btn btn-view" onclick="viewDetails(\'' + studentEmail.replace(/'/g, "\\'") + '\', \'' + taskTitle.replace(/'/g, "\\'") + '\')">👁️ View Details</button>';
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: status === 'approved' ? 'Application approved! Student has been notified.' : 'Application rejected! Student has been notified.',
                        confirmButtonColor: '#667eea'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.error || 'Failed to update application',
                        confirmButtonColor: '#667eea'
                    });
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#667eea'
                });
                button.disabled = false;
            });
        }

        function viewDetails(studentEmail, taskTitle) {
            Swal.fire({
                title: 'Student Application',
                html: '<div style="text-align: left;"><p><strong>Email:</strong> ' + studentEmail + '</p><p><strong>Task:</strong> ' + taskTitle + '</p></div>',
                icon: 'info',
                confirmButtonColor: '#667eea'
            });
        }
    </script>
</body>
</html>
