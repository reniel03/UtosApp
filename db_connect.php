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

// Auto-initialize tables if they don't exist
$init_file = __DIR__ . '/init_database.php';
if (file_exists($init_file)) {
    // Check if teachers table exists
    $result = $conn->query("SHOW TABLES LIKE 'teachers'");
    if ($result && $result->num_rows === 0) {
        // Tables don't exist, initialize them
        include $init_file;
    }
}
?>

