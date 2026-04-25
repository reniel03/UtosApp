<?php
// db_connect.php
// Use Railway's environment variables if they exist, otherwise use Railway internal connection
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'WnKJjtmncxeZQmJSkkuXTKAhGyWRob';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '3306';

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>
