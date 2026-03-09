<?php
session_start();
session_unset();
session_destroy();

// Prevent back button caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login
header("Location: login.php");
exit;
?>