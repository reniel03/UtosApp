<?php
session_start();

$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'utosapp';
$port = getenv('MYSQLPORT') ?: '3306';
$db = new mysqli($host, $user, $pass, $dbname, $port);

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo "<h3>Not logged in. Session gender is empty.</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    exit;
}

// Database connection
$db = new mysqli('localhost', 'root', '', 'utosapp');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$email = $_SESSION['email'];

// Get teacher from database
$stmt = $db->prepare("SELECT first_name, middle_name, last_name, gender, email FROM teachers WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $teacher = $result->fetch_assoc();
    echo "<h3>Teacher: " . htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) . "</h3>";
    echo "<p><strong>Session Gender:</strong> " . htmlspecialchars($_SESSION['gender'] ?? 'NOT SET') . "</p>";
    echo "<p><strong>Database Gender:</strong> " . htmlspecialchars($teacher['gender'] ?? 'NULL') . "</p>";
    echo "<p><strong>Gender Match (session):</strong> " . ($teacher['gender'] === ($_SESSION['gender'] ?? '') ? '✓ YES' : '✗ NO') . "</p>";
    echo "<hr>";
    echo "<h4>Full Record:</h4>";
    echo "<pre>";
    print_r($teacher);
    echo "</pre>";
    echo "<h4>Session Data:</h4>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<h3>Teacher not found in database</h3>";
}

$stmt->close();
$db->close();
?>
