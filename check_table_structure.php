<?php
// Check database table structure
$host = getenv('MYSQLHOST') ?: 'junction.proxy.rlwy.net';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSkkuXTKAhGyWRob';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '23823';
$db = new mysqli($host, $user, $pass, $dbname, (int)$port);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get table structure
$result = $db->query("DESCRIBE teachers");
echo "<h3>Teachers Table Structure:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Also show a sample teacher record
echo "<h3>Sample Teacher Records:</h3>";
$result = $db->query("SELECT id, first_name, last_name, email, gender FROM teachers LIMIT 3");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th><th>Gender</th></tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>[" . htmlspecialchars($row['gender'] ?? 'NULL') . "]</td>";
    echo "</tr>";
}
echo "</table>";

$db->close();
?>
