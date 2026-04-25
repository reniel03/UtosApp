<?php
session_start();

$host = getenv('MYSQLHOST') ?: 'localhost';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('MYSQLDATABASE') ?: 'utosapp';
$port = getenv('MYSQLPORT') ?: '3306';
$db = new mysqli($host, $user, $pass, $dbname, $port);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$testEmail = "dragon@gmail.com";

// Get the record directly from database
$stmt = $db->prepare("SELECT id, first_name, last_name, email, gender, password FROM teachers WHERE email = ?");
$stmt->bind_param('s', $testEmail);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Dragon Teacher Record in Database:</h2>";

if ($result->num_rows === 1) {
    $teacher = $result->fetch_assoc();
    echo "<p><strong>ID:</strong> " . $teacher['id'] . "</p>";
    echo "<p><strong>Name:</strong> " . $teacher['first_name'] . " " . $teacher['last_name'] . "</p>";
    echo "<p><strong>Email:</strong> " . $teacher['email'] . "</p>";
    echo "<p><strong>Gender in DB:</strong> [" . htmlspecialchars($teacher['gender']) . "]</p>";
    echo "<p><strong>Password Hash exists:</strong> " . (!empty($teacher['password']) ? 'YES' : 'NO') . "</p>";
    
    echo "<hr>";
    echo "<h3>Current Session Data:</h3>";
    if (isset($_SESSION['email'])) {
        echo "<p style='color: green;'><strong>✓ LOGGED IN</strong></p>";
        echo "<p>Email: " . $_SESSION['email'] . "</p>";
        echo "<p>Gender in Session: [" . ($_SESSION['gender'] ?? 'NOT SET') . "]</p>";
    } else {
        echo "<p style='color: red;'><strong>✗ NOT LOGGED IN</strong></p>";
    }
} else {
    echo "<p>Teacher not found!</p>";
}

$db->close();
?>
