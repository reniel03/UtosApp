<?php
// Fix for existing teachers table - adds default value to department column

$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'WnKJkJjtmncxeZQmJSKkuXTKAhGyWRob';
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$port = getenv('MYSQLPORT') ?: '3306';
$db = new mysqli($host, $user, $pass, $dbname, (int)$port);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Check if teachers table exists
$tables_check = $db->query("SHOW TABLES LIKE 'teachers'");
if ($tables_check && $tables_check->num_rows > 0) {
    // Modify the department column to add a default value
    $result = $db->query("ALTER TABLE teachers MODIFY COLUMN department varchar(255) DEFAULT 'Not Specified'");
    
    if ($result) {
        echo "Successfully updated teachers table - department column now has default value 'Not Specified'";
    } else {
        echo "Error updating table: " . $db->error;
    }
} else {
    echo "Teachers table does not exist yet. It will be created with the default value when you sign up.";
}

$db->close();
?>
