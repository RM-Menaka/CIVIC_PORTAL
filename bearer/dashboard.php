<?php
// File: Civic_Portal/bearer/dashboard.php
session_start();
include "../db.php"; 

// --- TEMPORARY LOGIN BYPASS (FOR DEMO ONLY) ---
$_SESSION['role'] = 'office_bearer';
$_SESSION['username'] = 'Menaka General Secretary';
$_SESSION['user_id'] = '2023242011';
$_SESSION['unit_id'] = 'Unit_105';
// --- END TEMPORARY LOGIN BYPASS ---

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? null;
$unit_id = $_SESSION['unit_id'] ?? 'Unit_105';
$message = '';

// Fetch unit name
$unit_name = 'N/A';
if ($unit_id) {
    $stmt = $conn->prepare("SELECT unit_name FROM units WHERE unit_id = ?");
    $stmt->bind_param("s", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unit = $result->fetch_assoc();
    $unit_name = $unit['unit_name'] ?? 'Unit Not Found';
    $stmt->close();
}

// Generate Activity ID
function generateActivityId($conn, $unit_id, $activity_type) {
    $category_map = [
        'Classroom Meet' => '1025',
        'Orphanage Visit' => '2025',
        'Annual Camp' => '3025',
        'General Event' => '4025',
    ];
    $prefix = $category_map[$activity_type] ?? '4025';
    $unit_suffix = substr($unit_id, -3);
    $pattern_start = $prefix . $unit_suffix;

    $sql = "SELECT MAX(activity_id) FROM activities WHERE activity_id LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_pattern = $pattern_start . '%';
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $max_id = $result->fetch_row()[0];
    $stmt->close();

    if ($max_id) {
        $current_sequence = substr($max_id, -3);
        $next_sequence = intval($current_sequence) + 1;
    } else {
        $next_sequence = 1;
    }

    $sequence_padded = str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
    return (int)($pattern_start . $sequence_padded);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_activity'])) {
    $activity_name = trim($_POST['activity_name']);
    $activity_date = trim($_POST['activity_date']);
    $activity_time = trim($_POST['activity_time']);
    $activity_desc = trim($_POST['description']);
    $activity_type = trim($_POST['activity_type']);
    
    $new_activity_id = generateActivityId($conn, $unit_id, $activity_type);

    if (empty($activity_name) || empty($activity_date) || empty($activity_desc)) {
        $message = "<div class='alert alert-danger'>❌ Error: All fields are required.</div>";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO activities (activity_id, activity_name, activity_date, activity_time, description, unit_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", $new_activity_id, $activity_name, $activity_date, $activity_time, $activity_desc, $unit_id);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Activity <b>" . htmlspecialchars($activity_name) . "</b> created successfully! (ID: {$new_activity_id})</div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ DB Error: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Office Bearer Dashboard - Civic Portal</title>
<style>
/* --- BASE STYLES WITH BLURRED BACKGROUND IMAGE --- */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
    margin: 0;
    padding: 0;
    position: relative;
    background-color: #f4f6f9; /* fallback */
}

/* Blurred background (same as Student Dashboard) */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('../anna_univ.jpg');
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    filter: blur(2px) grayscale(50%);
    opacity: 0.8;
    z-index: -1;
}

/* --- NAVBAR --- */
.navbar {
    background-color: rgba(0, 123, 255, 0.85);
    color: white;
    padding: 15px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    backdrop-filter: blur(3px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
.navbar h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 400;
}
.user-details b {
    font-weight: 600;
}

/* --- LAYOUT --- */
.main-content {
    display: flex;
    padding: 20px;
}
.dashboard-body {
    flex-grow: 1;
    padding-left: 20px;
    position: relative;
    z-index: 1;
}

/* --- SIDEBAR --- */
.sidebar {
    width: 250px;
    padding: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(0,0,0,0.08);
    height: fit-content;
    position: relative;
    z-index: 1;
}
.sidebar hr {
    border-color: #f1f1f1;
    margin: 15px 0;
}
.sidebar a {
    display: block;
    padding: 12px 15px;
    margin-bottom: 5px;
    text-decoration: none;
    color: #333;
    border-radius: 4px;
    font-weight: 500;
    transition: background-color 0.2s;
}
.sidebar a:hover, .sidebar .active {
    background: #007bff;
    color: white;
}

/* --- ALERTS --- */
.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 5px solid;
}
.alert-success {
    background-color: #e6f1e6;
    border-color: #28a745;
    color: #155724;
}
.alert-danger {
    background-color: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

/* --- FORM CARD --- */
.card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    border-left: 5px solid #007bff;
    margin-bottom: 25px;
}
.card h3 {
    margin-top: 0;
    color: #007bff;
    font-weight: 500;
    margin-bottom: 20px;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 6px;
    color: #444;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

/* --- BUTTON --- */
.btn-create {
    background-color: #007bff;
    color: white;
    padding: 12px 18px;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
    width: 100%;
}
.btn-create:hover {
    background-color: #0056b3;
}

/* --- QUICK ACTIONS --- */
.quick-actions a {
    display: block;
    padding: 12px;
    margin-bottom: 10px;
    background: #f8f9fa;
    border-left: 5px solid #007bff;
    border-radius: 5px;
    text-decoration: none;
    color: #007bff;
    font-weight: 500;
    transition: background-color 0.2s, color 0.2s;
}
.quick-actions a:hover {
    background-color: #e9f2ff;
    color: #0056b3;
}

/* --- LOGOUT --- */
.logout a {
    color: #dc3545;
    font-weight: 600;
    text-decoration: none;
    display: block;
    margin-top: 15px;
    padding: 10px 0;
    border: 1px solid #dc3545;
    border-radius: 4px;
    text-align: center;
}
.logout a:hover {
    background-color: #dc3545;
    color: white;
}
</style>
</head>
<body>

<div class="navbar">
    <h1>Civic Portal | Office Bearer Dashboard</h1>
    <div class="user-details">Welcome, <b><?php echo htmlspecialchars($username); ?></b></div>
</div>

<div class="main-content">
    <div class="sidebar">
        <a href="dashboard.php" class="active">🏠 Dashboard</a>
        <a href="view_activities.php">📋 View Activities</a>
        <a href="mark_attendance.php?unit_id=<?php echo htmlspecialchars($unit_id); ?>">✅ Mark Attendance</a>
        <hr>
        <div class="logout">
            <a href="../logout.php">🛑 Log Out</a>
        </div>
    </div>

    <div class="dashboard-body">
        <h2>Plan New Activity</h2>
        <?php echo $message; ?>

        <div class="card">
            <form method="POST" action="dashboard.php">
                <div class="form-group">
                    <label for="activity_type">Activity Type</label>
                    <select id="activity_type" name="activity_type" required>
                        <option value="Classroom Meet">Classroom Meet (Auto-ID: 1025...)</option>
                        <option value="Orphanage Visit">Orphanage Visit (Auto-ID: 2025...)</option>
                        <option value="Annual Camp">Annual Camp (Auto-ID: 3025...)</option>
                        <option value="General Event">General Event (Auto-ID: 4025...)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="activity_name">Activity Name</label>
                    <input type="text" id="activity_name" name="activity_name" required>
                </div>

                <div class="form-group" style="display: flex; gap: 15px;">
                    <div style="flex: 1;">
                        <label for="activity_date">Date</label>
                        <input type="date" id="activity_date" name="activity_date" required>
                    </div>
                    <div style="flex: 1;">
                        <label for="activity_time">Start Time</label>
                        <input type="time" id="activity_time" name="activity_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" required></textarea>
                </div>

                <button type="submit" name="create_activity" class="btn-create">Create Activity</button>
            </form>
        </div>

            </div>
</div>

</body>
</html>
