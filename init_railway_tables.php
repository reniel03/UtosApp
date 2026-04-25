<?php
// Direct table initialization for Railway MySQL

$host = 'mysql.railway.internal';
$user = 'root';
$pass = 'WnKJkJjtmncxeZQmJSKkuXTKAhGyWRob';
$dbname = 'railway';
$port = '3306';

$db = new mysqli($host, $user, $pass, $dbname, (int)$port);

if ($db->connect_error) {
    die("❌ Connection failed: " . $db->connect_error);
}

echo "✅ Connected to Railway MySQL<br><br>";

// Create TEACHERS table
$sql1 = "CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(255) DEFAULT 'Not Specified',
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gender` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($db->query($sql1)) {
    echo "✅ Teachers table created/exists<br>";
} else {
    echo "❌ Error creating teachers table: " . $db->error . "<br>";
}

// Create STUDENTS table
$sql2 = "CREATE TABLE IF NOT EXISTS `students` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($db->query($sql2)) {
    echo "✅ Students table created/exists<br>";
} else {
    echo "❌ Error creating students table: " . $db->error . "<br>";
}

// Create MESSAGES table
$sql3 = "CREATE TABLE IF NOT EXISTS `messages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($db->query($sql3)) {
    echo "✅ Messages table created/exists<br>";
} else {
    echo "❌ Error creating messages table: " . $db->error . "<br>";
}

// Create TASKS table
$sql4 = "CREATE TABLE IF NOT EXISTS `tasks` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($db->query($sql4)) {
    echo "✅ Tasks table created/exists<br>";
} else {
    echo "❌ Error creating tasks table: " . $db->error . "<br>";
}

// Create TASK_FILES table
$sql5 = "CREATE TABLE IF NOT EXISTS `task_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($db->query($sql5)) {
    echo "✅ Task_files table created/exists<br>";
} else {
    echo "❌ Error creating task_files table: " . $db->error . "<br>";
}

// Create STUDENT_TODOS table
$sql6 = "CREATE TABLE IF NOT EXISTS `student_todos` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($db->query($sql6)) {
    echo "✅ Student_todos table created/exists<br>";
} else {
    echo "❌ Error creating student_todos table: " . $db->error . "<br>";
}

echo "<br><strong>🎉 All tables initialized successfully!</strong>";

$db->close();
?>
