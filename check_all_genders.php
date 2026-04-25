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

// Show ALL teacher records with their gender values
$result = $db->query("SELECT id, first_name, last_name, email, gender FROM teachers");

echo "<h2>ALL Teachers in Database:</h2>";
echo "<table border='1' cellpadding='10' style='font-family: monospace;'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Gender Value</th><th>Gender Length</th></tr>";

while ($row = $result->fetch_assoc()) {
    $genderLen = strlen($row['gender'] ?? '');
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td style='background: yellow;'>[" . htmlspecialchars($row['gender'] ?? 'NULL/EMPTY') . "]</td>";
    echo "<td>" . $genderLen . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>Current Session Data (if logged in):</h2>";
if (isset($_SESSION['email'])) {
    echo "<p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email']) . "</p>";
    echo "<p><strong>Session Gender:</strong> [" . htmlspecialchars($_SESSION['gender'] ?? 'NOT SET') . "]</p>";
} else {
    echo "<p style='color: red;'>Not logged in - Please log in first</p>";
}

$db->close();
?>
