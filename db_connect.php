<?php
// db_connect.php
// Use Railway's environment variables if they exist, otherwise use provided Railway credentials
$host = getenv('MYSQLHOST') ?: 'junction.proxy.rlwy.net';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSkkuXTKAhGyWRob';
$db   = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '23823';

$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
?>
