<?php
// db_connect.php
// Use Railway's environment variables
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSKkuXTKAhGyWRob';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '3306';

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Auto-initialize tables on first connection
$tables_check = $conn->query("SHOW TABLES LIKE 'teachers'");
if (!$tables_check || $tables_check->num_rows === 0) {
    // Create all tables
    $conn->query("CREATE TABLE IF NOT EXISTS `teachers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `first_name` varchar(255) NOT NULL,
      `middle_name` varchar(255) DEFAULT NULL,
      `last_name` varchar(255) NOT NULL,
      `email` varchar(255) NOT NULL,
      `password` varchar(255) NOT NULL,
      `department` varchar(255) NOT NULL,
      `photo` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `gender` varchar(10) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `students` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `first_name` varchar(100) DEFAULT NULL,
      `middle_name` varchar(100) DEFAULT NULL,
      `last_name` varchar(100) DEFAULT NULL,
      `email` varchar(255) NOT NULL,
      `password` varchar(255) NOT NULL,
      `year_level` varchar(50) DEFAULT NULL,
      `student_id` varchar(100) DEFAULT NULL,
      `course` varchar(100) DEFAULT NULL,
      `gender` varchar(50) DEFAULT NULL,
      `photo` varchar(255) DEFAULT NULL,
      `attachment` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `messages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `sender_email` varchar(255) NOT NULL,
      `sender_role` enum('student','teacher') NOT NULL,
      `receiver_email` varchar(255) NOT NULL,
      `receiver_role` enum('student','teacher') NOT NULL,
      `message` text NOT NULL,
      `is_read` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_sender` (`sender_email`),
      KEY `idx_receiver` (`receiver_email`),
      KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `tasks` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `teacher_email` varchar(255) NOT NULL,
      `title` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `room` varchar(100) NOT NULL,
      `due_date` date NOT NULL,
      `due_time` time NOT NULL,
      `attachments` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `teacher_email` (`teacher_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `task_files` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `file_path` varchar(255) NOT NULL,
      `file_name` varchar(255) NOT NULL,
      `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `task_id` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `student_todos` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_email` varchar(255) NOT NULL,
      `task_id` int(11) DEFAULT NULL,
      `title` varchar(255) NOT NULL,
      `description` text DEFAULT NULL,
      `room` varchar(100) DEFAULT NULL,
      `due_date` date NOT NULL,
      `due_time` time NOT NULL,
      `attachments` text DEFAULT NULL,
      `is_completed` tinyint(1) DEFAULT 0,
      `status` varchar(50) DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `teacher_email` varchar(255) DEFAULT NULL,
      `approved_at` datetime DEFAULT NULL,
      `rating` int(11) DEFAULT NULL,
      `rated_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Add foreign key constraints
    $conn->query("ALTER TABLE `tasks` ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`teacher_email`) REFERENCES `teachers` (`email`) ON DELETE CASCADE ON UPDATE CASCADE");
    $conn->query("ALTER TABLE `task_files` ADD CONSTRAINT `task_files_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE");
}
?>


