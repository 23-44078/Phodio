<?php
// Start the session to check for logged-in admin later
session_start();

// If you implement a login system later, you would check it here:
// if (!isset($_SESSION['user_id'])) { ... }

// For now, simply redirect everyone to the login page
header("Location: client_login.php");
exit();
?>