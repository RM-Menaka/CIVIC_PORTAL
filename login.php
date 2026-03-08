<?php
session_start();
// Include the database connection file.
include "db.php"; 

// --- Helper Function ---
// Fetches the unit_id from office_bearers using the user's ID/Roll Number
function fetchAndSetUnitID($conn, $user_id) {
    // Check office_bearers table for unit assignment
    // Use the active connection object $conn
    $stmt_unit = $conn->prepare("SELECT unit_id FROM office_bearers WHERE student_id = ?");
    $stmt_unit->bind_param("s", $user_id);
    $stmt_unit->execute();
    $result_unit = $stmt_unit->get_result();
    $unit_data = $result_unit->fetch_assoc();
    $stmt_unit->close();
    
    if ($unit_data) {
        $_SESSION['unit_id'] = $unit_data['unit_id'];
    }
    // Do NOT close the connection here.
}


// --- LOGIN PROCESSING LOGIC ---
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Prepared statement for authentication (using plain text comparison)
    $stmt = $conn->prepare("SELECT user_id, password, role FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // --- FIX APPLIED HERE: REMOVED THE PREMATURE $conn->close(); ---

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        $user_role = trim($user['role']);
        $user_id = $user['user_id'];
        
        // 1. Set essential session variables
        $_SESSION['role'] = $user_role;
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $user_id; 

        // 2. CRITICAL STEP: Fetch and set unit_id for Officers/Bearers
        if ($user_role === 'officer' || $user_role === 'office_bearer') {
            // This call now uses the OPEN $conn object
            fetchAndSetUnitID($conn, $user_id); 
        }

        // 3. Role-Based Redirection
        if ($user_role === 'admin') {
            header("Location: admin/dashboard.php");
            
        } elseif ($user_role === 'officer' || $user_role === 'office_bearer') {
            // POs and Student Leaders share the 'bearer' dashboard
            header("Location: bearer/dashboard.php"); 
            
        } elseif ($user_role === 'student') {
            header("Location: student/dashboard.php");
            
        } else {
             $error_message = "Your account has an unrecognized role.";
        }
        exit;

    } else {
        // Authentication failure
        $error_message = "Invalid username or password.";
    }
    
    // Close the statement object after the login attempt
    if (isset($stmt)) {
        $stmt->close();
    }
}

// FINAL STEP: Ensure the connection is closed ONCE, at the very end of the file.
if (isset($conn) && $conn->ping()) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Civic Portal Login</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); width: 100%; max-width: 350px; text-align: center; }
        .login-box h2 { color: #007bff; margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; }
        .btn-login { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: bold; transition: background-color 0.2s; }
        .btn-login:hover { background-color: #218838; }
        .error { color: #dc3545; margin-bottom: 15px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Civic Portal Login</h2>
        
        <?php if ($error_message): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Log In</button>
        </form>
    </div>
</body>
</html>