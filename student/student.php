<?php
// File: Civic_Portal/student/dashboard.php
// WARNING: AUTHORIZATION AND SESSION CHECKS ARE DISABLED FOR DIRECT ACCESS.
// This file is NOT SECURE in this state and must be reverted for production.

// session_start() is needed here for session handling in the event you use the logout link
session_start();
include "../db.php"; 

// --- TEMPORARY LOGIN BYPASS ---
// This hard-codes the identity needed for the page to function without logging in.
$username = 'Demo Student';
$student_roll = '2025116033'; // Use a real student_id for fetching attendance
$selected_unit_id = $_GET['selected_unit'] ?? 'Unit_105'; // Default unit for viewing (can be changed via dropdown)
// --- END TEMPORARY LOGIN BYPASS ---


// 1. Authorization Check (DISABLED)
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}
*/

$all_units = [];
$unit_roster = [];
$unit_activities = [];
$attendance_percentage = 'N/A';
$message = '';

// 2. FETCH ALL UNITS for Selection Dropdown
$units_result = $conn->query("SELECT unit_id, unit_name FROM units ORDER BY unit_id ASC");
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $all_units[] = $row;
    }
}

// 3. FETCH DATA based on Selected Unit
if ($selected_unit_id && $selected_unit_id !== 'UNASSIGNED') {
    
    // A. Fetch Unit Roster (All students in the selected unit)
    $stmt_roster = $conn->prepare("
        SELECT student_id, name, department 
        FROM students 
        WHERE unit_id = ? 
        ORDER BY name ASC
    ");
    $stmt_roster->bind_param("s", $selected_unit_id);
    $stmt_roster->execute();
    $result_roster = $stmt_roster->get_result();
    while ($row = $result_roster->fetch_assoc()) {
        $unit_roster[] = $row;
    }
    $stmt_roster->close();

    // B. Fetch Student's OWN Attendance Percentage (for the currently hardcoded student)
    $sql_att = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count 
                FROM attendance 
                WHERE student_id = ?";
    $stmt_att = $conn->prepare($sql_att);
    $stmt_att->bind_param("s", $student_roll);
    $stmt_att->execute();
    $att_counts = $stmt_att->get_result()->fetch_assoc();
    
    if ($att_counts['total'] > 0) {
        $attendance_percentage = round(($att_counts['present_count'] / $att_counts['total']) * 100, 2);
    }
    $stmt_att->close();
    
    // C. Fetch Unit Events (for the selected unit)
    $stmt_act = $conn->prepare("
        SELECT activity_name, activity_date, activity_time, description 
        FROM activities 
        WHERE unit_id = ? AND activity_date >= CURDATE()
        ORDER BY activity_date ASC
    ");
    $stmt_act->bind_param("s", $selected_unit_id);
    $stmt_act->execute();
    $result_act = $stmt_act->get_result();
    while ($row = $result_act->fetch_assoc()) {
        $unit_activities[] = $row;
    }
    $stmt_act->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal Dashboard (DEMO)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        h2 { color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        h3 { color: #28a745; margin-top: 25px; padding-bottom: 5px; border-bottom: 1px dashed #c8e6c9; }
        .selector-box { margin-bottom: 20px; padding: 15px; background: #e6f3ff; border-left: 5px solid #007bff; border-radius: 4px; }
        .selector-box select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; }
        .metric-card { padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 1.1rem; font-weight: bold; margin-bottom: 20px; }
        .roster-table, .events-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.95rem; }
        .roster-table th, .events-table th, .roster-table td, .events-table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        .roster-table th, .events-table th { background-color: #f1f1f1; }
        .event-desc { font-size: 0.85rem; color: #6c757d; font-style: italic; }
        .logout a { color: #dc3545; text-decoration: none; font-weight: bold; }
        .alert-info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin-top: 20px;}
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color: #dc3545;">DEMO MODE: Direct Access Enabled</h2>
        <h2>Welcome to the Student Portal, <?php echo htmlspecialchars($username); ?>!</h2>
        
        <div class="selector-box">
            <form method="GET" action="dashboard.php">
                <label for="unit_select">Select Unit to View Details:</label>
                <select id="unit_select" name="selected_unit" onchange="this.form.submit()">
                    <option value="UNASSIGNED" 
                        <?php echo ($selected_unit_id === 'UNASSIGNED' || !$selected_unit_id) ? 'selected' : ''; ?>>
                        -- Select Your Unit --
                    </option>
                    <?php foreach ($all_units as $unit): ?>
                        <option value="<?php echo $unit['unit_id']; ?>" 
                            <?php echo ($unit['unit_id'] == $selected_unit_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unit['unit_id'] . " - " . $unit['unit_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php 
        // Display content ONLY if a valid, non-placeholder unit is selected
        if ($selected_unit_id && $selected_unit_id !== 'UNASSIGNED'): 
        ?>
            
            <div class="metric-card">
                Your Current Attendance Percentage (Overall): **<?php echo htmlspecialchars($attendance_percentage); ?>%**
            </div>

            <h3>👥 Unit Roster (Unit <?php echo htmlspecialchars($selected_unit_id); ?>)</h3>

            <?php if (!empty($unit_roster)): ?>
                <table class="roster-table">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Name</th>
                            <th>Department</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unit_roster as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['department']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No students found in the selected unit roster.</p>
            <?php endif; ?>
            
            <h3>📅 Upcoming Unit Activities</h3>
            
            <?php if (!empty($unit_activities)): ?>
                <table class="events-table">
                    <thead>
                        <tr>
                            <th>Activity Name</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unit_activities as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['activity_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['activity_date']); ?></td>
                                <td><?php echo htmlspecialchars(substr($activity['activity_time'], 0, 5)); ?></td>
                                <td><span class="event-desc"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No upcoming activities are scheduled for this unit.</p>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info">Please select a unit from the dropdown above to view the roster, attendance, and event details.</div>
        <?php endif; ?>

        <p style="margin-top: 30px;" class="logout"><a href="../logout.php">🛑 Log Out (Not currently functional in demo mode)</a></p>
    </div>
</body>
</html>