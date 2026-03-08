<?php
// File location: Civic_Portal/logout.php

// 1. Start the session to gain access to session variables
session_start();

// 2. Clear all session variables
$_SESSION = array(); 

// 3. Destroy the session (clears session file/cookie)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. Redirect the user back to the login page (index.php is in the same directory)
header("Location: index.php");
exit;
?>