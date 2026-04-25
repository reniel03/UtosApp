<?php
session_start();

$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'WnKJjtmncxeZQmJSkkuXTKAhGyWRob';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '3306';
$db = new mysqli($host, $user, $pass, $dbname, (int)$port);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Test login with dragon@gmail.com
$testEmail = "dragon@gmail.com";
$stmt = $db->prepare("SELECT * FROM teachers WHERE email = ?");
$stmt->bind_param('s', $testEmail);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Test Login Info for: dragon@gmail.com</h2>";

if ($result->num_rows === 1) {
    $teacher = $result->fetch_assoc();
    echo "<p><strong>Teacher Found:</strong> " . htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($teacher['email']) . "</p>";
    echo "<p><strong>Gender in DB:</strong> [" . htmlspecialchars($teacher['gender'] ?? 'NULL') . "]</p>";
    echo "<p><strong>Password Hash:</strong> " . substr($teacher['password'], 0, 20) . "...</p>";
    echo "<hr>";
    echo "<h3>Try this:</h3>";
    echo "<form method='POST' action='process_login.php'>";
    echo "<input type='hidden' name='email' value='dragon@gmail.com'>";
    echo "<input type='text' name='password' placeholder='Enter your password'>";
    echo "<button type='submit'>Test Login</button>";
    echo "</form>";
} else {
    echo "<p style='color: red;'><strong>ERROR:</strong> Teacher with email 'dragon@gmail.com' not found!</p>";
}

// Also show current session
echo "<hr>";
echo "<h3>Current Session:</h3>";
if (isset($_SESSION['email'])) {
    echo "<p style='color: green;'><strong>✓ LOGGED IN AS:</strong> " . htmlspecialchars($_SESSION['email']) . "</p>";
    echo "<p><strong>Session Gender:</strong> [" . htmlspecialchars($_SESSION['gender'] ?? 'NOT SET') . "]</p>";
} else {
    echo "<p style='color: red;'><strong>✗ NOT LOGGED IN</strong></p>";
}

$db->close();
?>
