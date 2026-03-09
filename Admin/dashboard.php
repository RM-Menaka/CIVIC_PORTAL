<?php
session_start();

// 1. AUTHORIZATION CHECK
// Only allow 'admin' role to access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

// 2. DATABASE CONNECTION
include "../db.php"; 
// Note: mysqli_report is good practice, but not always needed if the script handles errors
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 3. FETCH DASHBOARD METRICS
$unit_count = 0;
$student_count = 0;
$activity_count = 0; // Fetch actual activity count instead of static '03'

// Fetch total units
$result_units = $conn->query("SELECT COUNT(unit_id) as total_units FROM units");
if ($result_units) {
    $unit_count = $result_units->fetch_assoc()['total_units'];
}

// Fetch total enrolled students
$result_students = $conn->query("SELECT COUNT(student_id) as total_students FROM students");
if ($result_students) {
    $student_count = $result_students->fetch_assoc()['total_students'];
}

// Fetch total activities (using activity_date >= CURDATE() for 'pending')
$result_activities = $conn->query("SELECT COUNT(activity_id) as pending_activities FROM activities WHERE activity_date >= CURDATE()");
if ($result_activities) {
    $activity_count = $result_activities->fetch_assoc()['pending_activities'];
}

$conn->close();

$username = $_SESSION['username'];
$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Administrative Console - CEG Civic Portal</title>
<style>
/* --- BASE STYLES --- */
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    margin: 0; 
    background:#f4f6f9; 
    color:#333; 
}
h2 { 
    font-weight: 500; 
    color: #343a40; 
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
    margin-bottom: 25px;
}

/* --- NAVBAR --- */
.navbar { 
    background-color: #007bff; /* Primary Blue */
    color:white; 
    padding:15px 30px; 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.navbar h1 { 
    margin:0; 
    font-size:1.6rem; 
    font-weight: 400;
}
.user-details b {
    font-weight: 600;
    text-transform: capitalize;
}

/* --- LAYOUT --- */
.main-content { 
    display:flex; 
    padding:20px; 
}
.dashboard-body { 
    flex-grow:1; 
    padding-left: 20px;
}

/* --- SIDEBAR --- */
.sidebar { 
    width:250px; 
    padding:20px; 
    background:white; 
    border-radius:8px; 
    box-shadow:0 0 15px rgba(0,0,0,0.08); 
    height: fit-content;
}
.sidebar hr {
    border-color: #f1f1f1;
    margin: 15px 0;
}
.sidebar a { 
    display:block; 
    padding:12px 15px; 
    margin-bottom:5px; 
    text-decoration:none; 
    color:#333; 
    border-radius:4px; 
    font-weight:500; 
    transition: background-color 0.2s;
}
.sidebar a:hover, .sidebar .active { 
    background:#28a745; /* Success Green */
    color:white; 
}

/* --- METRIC CARDS --- */
.metric-container { 
    display:flex; 
    gap:20px; 
    margin-bottom:30px; 
}
.metric-card { 
    background:white; 
    padding:25px; 
    border-radius:8px; 
    box-shadow:0 4px 10px rgba(0,0,0,0.08); 
    flex:1; 
    text-align:center; 
    border-left:5px solid #007bff; /* Primary Blue Accent */
    transition: box-shadow 0.2s;
}
.metric-card:hover {
    box-shadow:0 6px 15px rgba(0,0,0,0.15);
}
.metric-card h3 { 
    margin-top:0; 
    color:#6c757d; 
    font-size:0.9rem; 
    text-transform:uppercase; 
    margin-bottom: 10px;
    border-bottom: none;
    padding-bottom: 0;
}
.metric-card .value { 
    font-size:2.8rem; 
    font-weight:700; 
    color:#007bff; 
}

/* --- LOGOUT --- */
.logout a { 
    color:#dc3545; /* Danger Red */
    font-weight:600; 
    text-decoration:none; 
    display:block; 
    margin-top:15px; 
    padding: 10px 0;
    border: 1px solid #dc3545;
    border-radius: 4px;
    transition: background-color 0.2s;
}
.logout a:hover {
    background-color: #dc3545;
    color: white;
}
</style>
</head>
<body>
<div class="navbar">
<h1>CEG Civic Portal | Administrative Console</h1>
<div class="user-details">Welcome, <b><?php echo htmlspecialchars($username); ?></b> (<?php echo htmlspecialchars($role); ?>)</div>
</div>

<div class="main-content">
    <div class="sidebar">
        <a href="dashboard.php" class="active">🏠 Dashboard Overview</a>
        <a href="manage_students.php">👥 Manage Students</a>
        <a href="manage_units.php">🌳 Manage Units</a>
        <a href="manage_activities.php">📅 Manage Activities</a>
        
        <hr>
        <div class="logout">
            <a href="../logout.php">🛑 Log Out</a>
        </div>
    </div>

    <div class="dashboard-body">
        <h2>Dashboard Overview</h2>

        <div class="metric-container">
            <div class="metric-card">
                <h3>Total Units</h3>
                <div class="value"><?php echo $unit_count; ?></div>
            </div>
            <div class="metric-card">
                <h3>Total Enrolled Students</h3>
                <div class="value"><?php echo $student_count; ?></div>
            </div>
            <div class="metric-card">
                <h3>Pending Activities</h3>
                <div class="value"><?php echo $activity_count; ?></div>
            </div>
        </div>
        
        </div>
</div>
</body>
</html>