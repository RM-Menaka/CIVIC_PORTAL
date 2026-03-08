<?php
// Database configuration
$host = "localhost";
$user = "root";   // Default XAMPP user
$pass = "";       // Default XAMPP password (empty)
$db   = "civic_portal"; // Your database name

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// If you want to debug connection, uncomment this line temporarily
// echo "Database connected successfully!";
?>
