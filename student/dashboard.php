 <?php
// File: Civic_Portal/student/dashboard.php
// WARNING: AUTHORIZATION AND SESSION CHECKS ARE DISABLED FOR DIRECT ACCESS.
// This file is NOT SECURE in this state and must be reverted for production.

// session_start() is needed here for session handling in the event you use the logout link
session_start();
include "../db.php"; 

// --- TEMPORARY LOGIN BYPASS ---
// This hard-codes the identity needed for the page to function without logging in.
$username = 'Aakash TR';
$student_roll = '2025116033'; // Use a real student_id for fetching attendance
$selected_unit_id = $_GET['selected_unit'] ?? 'Unit_105'; // Default unit for viewing (can be changed via dropdown)
// --- END TEMPORARY LOGIN BYPASS ---


$all_units = [];
$unit_roster = [];
$unit_activities = [];
$unit_bearers = []; 
$attendance_percentage = 'N/A';
$message = '';

// 1. FETCH ALL UNITS for Selection Dropdown
$units_result = $conn->query("SELECT unit_id, unit_name FROM units ORDER BY unit_id ASC");
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $all_units[] = $row;
    }
}

// 2. FETCH DATA based on Selected Unit
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
    
    // D. Fetch Office Bearer Details
    $stmt_bearers = $conn->prepare("
        SELECT student_id, name, designation, contact_number
        FROM office_bearers
        WHERE unit_id = ?
        ORDER BY designation ASC
    ");
    $stmt_bearers->bind_param("s", $selected_unit_id);
    $stmt_bearers->execute();
    $result_bearers = $stmt_bearers->get_result();
    while ($row = $result_bearers->fetch_assoc()) {
        $unit_bearers[] = $row;
    }
    $stmt_bearers->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal Dashboard (DEMO)</title>
    <style>
        /* Base Styles */
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #333; 
            margin: 0; 
            padding: 0; 
            position: relative; 
            background-color: #f4f6f9; /* Fallback color */
        }
        
        /* FIX: Pseudo-Element for Blurred Background (Apply to ALL Pages) */
        body::before {
            content: '';
            position: fixed; 
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            
            /* CRITICAL FIX: Use relative path. Assumes image is in the parent directory (Civic_Portal/anna_univ.jpg) */
            background-image: url('../anna_univ.jpg'); 
            
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            
            /* The Blur Effect */
            filter: blur(2px) grayscale(50%);
            opacity: 0.8; 
            z-index: -1; 
        }

        /* NEW: Header Bar Styling */
        .page-header {
            background-color: #007bff; 
            color: white;
            padding: 15px 20px;
            text-align: left; 
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 500;
        }
        .welcome-text {
            font-size: 1rem;
            font-weight: 400;
        }

        /* Main Content Wrappers (Need white background over blur) */
        .main-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 20px; } 
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
            margin-top: 20px; 
            z-index: 1; /* Keep container above the blurred background */
        }
        
        /* Rest of existing styles */
        h2 { color: #007bff; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; font-size: 1.5rem;}
        h3 { color: #28a745; margin-top: 25px; padding-bottom: 5px; border-bottom: 1px dashed #c8e6c9; }
        
        .selector-box { margin-bottom: 20px; padding: 15px; background: #e6f3ff; border-left: 5px solid #007bff; border-radius: 4px; }
        .selector-box select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; }
        .metric-card { padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 1.1rem; font-weight: bold; margin-bottom: 20px; }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .action-card {
            background: #ffffff;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: transform 0.2s;
            text-align: center;
        }
        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .action-card p {
            margin: 0;
            font-weight: 600;
            color: #1b5e20;
        }
        
        .content-box {
            border: 1px solid #eee;
            border-radius: 4px;
            margin-top: 20px;
        }
        .content-box h3 {
            background-color: #f1f1f1;
            padding: 10px 15px;
            margin: 0;
            border-bottom: 1px solid #dee2e6;
            color: #333;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .content-box > div {
            padding: 15px;
        }
        .toggle-content {
            display: none; /* Initially hidden */
        }

        .roster-table, .events-table, .bearers-table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 0.95rem; }
        .roster-table th, .events-table th, .roster-table td, .events-table td, .bearers-table th, .bearers-table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        .roster-table th, .events-table th, .bearers-table th { background-color: #f1f1f1; }
        .event-desc { font-size: 0.85rem; color: #6c757d; font-style: italic; }
        .logout a { color: #dc3545; text-decoration: none; font-weight: bold; }
        .alert-info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin-top: 20px;}
    </style>
</head>
<body>
    <header class="page-header">
        <h1>CEG CIVIC PORTAL | STUDENT CONSOLE</h1>
        <div class="welcome-text">
            Welcome, **<?php echo htmlspecialchars($username); ?>**!
        </div>
    </header>
    
    <div class="main-wrapper">
        <h2 style="color: #007bff; margin-top: 0; padding-top: 20px;">Student Dashboard</h2>

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
            
            <div class="action-grid">
                <div class="action-card" onclick="toggleSection('rosterContent')">
                    <p>👥 View Unit Roster (<?php echo count($unit_roster); ?>)</p>
                </div>
                <div class="action-card" onclick="toggleSection('eventsContent')">
                    <p>📅 Upcoming Activities (<?php echo count($unit_activities); ?>)</p>
                </div>
                <div class="action-card" onclick="toggleSection('bearersContent')">
                    <p>🛡️ View Office Bearers (<?php echo count($unit_bearers); ?>)</p>
                </div>
                <a href="../logout.php" style="text-decoration: none;"><div class="action-card" style="background: #f8d7da;"><p style="color: #dc3545;">🛑 Log Out</p></div></a>
            </div>

            <div class="container">
                <div class="metric-card">
                    Your Current Attendance Percentage (Overall): **<?php echo htmlspecialchars($attendance_percentage); ?>%**
                </div>

                <div class="content-box">
                    <div class="toggle-content" id="rosterContent">
                        <h3>Unit Roster</h3>
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
                    </div>

                    <div class="toggle-content" id="eventsContent">
                        <h3>Upcoming Unit Activities</h3>
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
                    </div>

                    <div class="toggle-content" id="bearersContent">
                        <h3>Office Bearer Details</h3>
                        <?php if (!empty($unit_bearers)): ?>
                            <table class="bearers-table">
                                <thead>
                                    <tr>
                                        <th>Designation</th>
                                        <th>Name</th>
                                        <th>Roll Number</th>
                                        <th>Contact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unit_bearers as $bearer): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($bearer['designation']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bearer['name']); ?></td>
                                            <td><?php echo htmlspecialchars($bearer['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($bearer['contact_number']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No office bearers found for this unit.</p>
                        <?php endif; ?>
                    </div>
                </div> </div> <?php else: ?>
            <div class="container alert alert-info">Please select a unit from the dropdown above to view the roster, attendance, and event details.</div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleSection(contentId) {
            const content = document.getElementById(contentId);
            
            // Optional: Close all other open sections
            const allContents = document.querySelectorAll('.toggle-content');
            allContents.forEach(c => {
                if (c.id !== contentId) {
                    c.style.display = 'none';
                }
            });

            // Toggle the clicked section
            if (content.style.display === "block") {
                content.style.display = "none";
            } else {
                content.style.display = "block";
            }
        }
    </script>
</body>
</html>
