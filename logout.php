<?php
session_start();

// Clear only authentication-related session variables
unset($_SESSION['user_role']);
unset($_SESSION['email']);

// Redirect to frontpage
header("Location: frontpage.php");
exit();
?>