<?php
// File: Civic_Portal/bearer/mark_attendance.php
session_start();
include "../db.php"; 

// --- TEMPORARY LOGIN BYPASS (Must match bearer/dashboard.php) ---
$_SESSION['role'] = 'office_bearer';
$_SESSION['username'] = 'Menaka-General Secretary';
$_SESSION['user_id'] = '2023242011';
$_SESSION['unit_id'] = 'Unit_105';
// --- END TEMPORARY LOGIN BYPASS ---


// Authorization Check (Disabled for demo)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'office_bearer') {
    // header("Location: ../index.php?error=unauthorized"); exit;
}

$unit_id = $_SESSION['unit_id'] ?? null;
$message = '';
$selected_activity_id = $_GET['activity_id'] ?? null;
$unit_students = [];
$unit_activities = [];
$activity_details = null;
$total_students = 0; // New variable for metric display

// Check if Unit ID is available
if (!$unit_id) {
    $message = "<div class='alert alert-danger'>❌ Error: Unit ID not found in session. Please return to dashboard.</div>";
    goto render_page; 
}


// --- A. HANDLE ATTENDANCE SUBMISSION (INSERT into ATTENDANCE table) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $activity_id = $_POST['activity_id'];
    $attendance_data = $_POST['attendance'];
    $recorded_date = date('Y-m-d'); // Change to Y-m-d for SQL consistency
    $records_processed = 0;
    
    $conn->begin_transaction();
    $all_success = true;

    $stmt = $conn->prepare("
        INSERT INTO attendance (student_id, activity_id, status, recorded_date) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_date = VALUES(recorded_date)
    ");
    
    foreach ($attendance_data as $student_id => $status) {
        if (!in_array($status, ['Present', 'Absent'])) continue; 

        // Bind parameters: (student_id BIGINT, activity_id BIGINT, status ENUM, recorded_date DATE)
        $stmt->bind_param("siss", $student_id, $activity_id, $status, $recorded_date); 
        
        if ($stmt->execute()) {
            $records_processed++;
        } else {
            $all_success = false;
            $conn->rollback();
            $message = "<div class='alert alert-danger'>❌ Error inserting attendance for student {$student_id}. DB Error: " . htmlspecialchars($stmt->error) . "</div>";
            break; 
        }
    }
    
    $stmt->close();

    if ($all_success) {
        $conn->commit();
        $message = "<div class='alert alert-success'>✅ Attendance successfully marked for {$records_processed} students for Activity ID: {$activity_id}.</div>";
        header("Location: mark_attendance.php?activity_id={$activity_id}");
        exit;
    }
}


// --- B. FETCH ACTIVITIES (for selection dropdown) ---
$stmt_act = $conn->prepare("
    SELECT activity_id, activity_name, activity_date, activity_time 
    FROM activities 
    WHERE unit_id = ? 
    ORDER BY activity_date DESC
");
$stmt_act->bind_param("s", $unit_id);
$stmt_act->execute();
$result_act = $stmt_act->get_result();
while ($row = $result_act->fetch_assoc()) {
    $unit_activities[] = $row;
}
$stmt_act->close();

// Set the first activity ID as default if none selected
if (!$selected_activity_id && !empty($unit_activities)) {
    $selected_activity_id = $unit_activities[0]['activity_id'];
}

// --- C. FETCH ROSTER & PRE-LOAD STATUS ---
if ($selected_activity_id) {
    // 1. Get Activity Details (Name, Date, Time)
    $stmt_details = $conn->prepare("SELECT activity_name, activity_date, activity_time FROM activities WHERE activity_id = ?");
    $stmt_details->bind_param("i", $selected_activity_id);
    $stmt_details->execute();
    $activity_details = $stmt_details->get_result()->fetch_assoc();
    $stmt_details->close();
    
    // 2. Fetch Roster and current attendance status for the selected activity
    $stmt_roster = $conn->prepare("
        SELECT 
            s.student_id, s.name, s.department, 
            a.status AS attendance_status
        FROM students s 
        LEFT JOIN attendance a 
            ON s.student_id = a.student_id AND a.activity_id = ?
        WHERE s.unit_id = ? 
        ORDER BY s.name ASC
    ");
    $stmt_roster->bind_param("is", $selected_activity_id, $unit_id);
    $stmt_roster->execute();
    $result_roster = $stmt_roster->get_result();
    while ($row = $result_roster->fetch_assoc()) {
        $unit_students[] = $row;
    }
    $stmt_roster->close();
    
    $total_students = count($unit_students); // Set the student count
}

render_page:
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance | Unit <?php echo htmlspecialchars($unit_id); ?></title>
    <style>
        /* Formal, Clean Design */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1); }
        h2 { color: #1b5e20; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 10px; font-weight: 600; }
        
        /* Activity Header & Metrics */
        .header-section { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            background-color: #e8f5e9; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 25px; 
            border: 1px solid #c8e6c9;
        }
        .activity-info h3 { margin: 0; font-size: 1.2rem; color: #1b5e20; font-weight: 600; }
        .activity-info p { margin: 5px 0 0; font-size: 0.9rem; color: #555; }
        .metric-count { font-size: 2rem; font-weight: 700; color: #007bff; }
        
        /* Select Box Styling */
        .select-box { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; }
        
        /* Alerts */
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        
        /* Roster Table & Search */
        #searchBox {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .roster-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .roster-table th, .roster-table td { border: 1px solid #dee2e6; padding: 12px 10px; text-align: left; }
        .roster-table th { background-color: #f1f1f1; font-weight: 600; }
        .roster-table tr:nth-child(even) { background-color: #f9f9f9; } /* Zebra striping */
        
        /* Radio Button Styling */
        .status-radio label { 
            display: inline-block; 
            margin-right: 25px; 
            padding: 5px 10px; 
            border-radius: 3px; 
            font-size: 0.95rem;
            cursor: pointer;
        }
        /* Color the labels based on status for a better visual cue */
        .status-radio input[value="Present"]:checked + label { background-color: #d4edda; color: #155724; }
        .status-radio input[value="Absent"]:checked + label { background-color: #f8d7da; color: #721c24; }
        
        /* Hide default radios visually */
        .status-radio input[type="radio"] { opacity: 0; position: fixed; width: 0; }

        /* Submit Button */
        .submit-box { text-align: right; margin-top: 30px; }
        .btn-submit { background-color: #007bff; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 1rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn-submit:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h2>👥 Mark Attendance for Unit <?php echo htmlspecialchars($unit_id); ?></h2>
        
        <?php echo $message; ?>

        <div class="header-section">
            <div class="select-box">
                <label for="activity_select" style="font-size: 1.1rem; font-weight: 600;">Select Activity:</label>
                <form method="GET" action="mark_attendance.php" style="margin: 0;">
                    <select id="activity_select" name="activity_id" onchange="this.form.submit()">
                        <?php if (empty($unit_activities)): ?>
                            <option value="">-- No Activities Found --</option>
                        <?php else: ?>
                            <?php foreach ($unit_activities as $act): ?>
                                <option value="<?php echo $act['activity_id']; ?>" 
                                    <?php echo ($act['activity_id'] == $selected_activity_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($act['activity_name'] . " (" . (new DateTime($act['activity_date']))->format('d-m-Y') . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($selected_activity_id): ?>
            <div class="activity-info" style="min-width: 250px; text-align: right;">
                <div class="metric-count"><?php echo $total_students; ?></div>
                <p>Total Students in Roster</p>
                <a href="dashboard.php" style="font-size: 0.9rem; color: #007bff; text-decoration: none;">&larr; Back to Dashboard</a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($activity_details): ?>
            <div style="margin-bottom: 20px;">
                <h3 style="color: #007bff; border-bottom: 1px dashed #eee; font-weight: 500;">Activity: <?php echo htmlspecialchars($activity_details['activity_name']); ?></h3>
                <p style="font-size: 0.95rem;">Date: **<?php echo htmlspecialchars((new DateTime($activity_details['activity_date']))->format('d-m-Y')); ?>** | Time: **<?php echo htmlspecialchars(substr($activity_details['activity_time'], 0, 5)); ?>**</p>
                <p style="color: #dc3545; font-weight: 600;">(Attendance recorded will update status for this activity.)</p>
            </div>
        <?php endif; ?>


        <?php if ($selected_activity_id && !empty($unit_students)): ?>
            
            <input type="text" id="searchBox" onkeyup="filterRoster()" placeholder="Filter by Name or Roll Number..." style="width: 300px;">
            <h3>Roster to Mark:</h3>

            <form method="POST" action="mark_attendance.php">
                <input type="hidden" name="activity_id" value="<?php echo htmlspecialchars($selected_activity_id); ?>">

                <table class="roster-table" id="rosterTable">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th style="width: 30%;">Mark Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unit_students as $student): ?>
                            <tr data-roll="<?php echo htmlspecialchars($student['student_id']); ?>" data-name="<?php echo htmlspecialchars(strtolower($student['name'])); ?>">
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['department']); ?></td>
                                <td class="status-radio">
                                    <?php 
                                    $field_name = 'attendance[' . $student['student_id'] . ']'; 
                                    $status = $student['attendance_status'];
                                    
                                    // Set default selection: Present if not marked, or match DB status
                                    $default_present = ($status === 'Present' || $status === null) ? 'checked' : '';
                                    $default_absent = ($status === 'Absent') ? 'checked' : '';
                                    
                                    // Default to Present if not explicitly marked Absent
                                    if ($default_present === '' && $default_absent === '') {
                                         $default_present = 'checked'; 
                                    }
                                    ?>
                                    
                                    <input type="radio" id="p_<?php echo $student['student_id']; ?>" name="<?php echo $field_name; ?>" value="Present" <?php echo $default_present; ?> required>
                                    <label for="p_<?php echo $student['student_id']; ?>">Present</label>
                                    
                                    <input type="radio" id="a_<?php echo $student['student_id']; ?>" name="<?php echo $field_name; ?>" value="Absent" <?php echo $default_absent; ?> required>
                                    <label for="a_<?php echo $student['student_id']; ?>">Absent</label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="submit-box">
                    <button type="submit" name="mark_attendance" class="btn-submit">✅ Save Attendance (<?php echo $total_students; ?> Records)</button>
                </div>
            </form>

        <?php elseif ($selected_activity_id && empty($unit_students)): ?>
            <div class="alert alert-danger">❌ No students found in your unit roster.</div>
        <?php elseif (empty($unit_activities)): ?>
            <div class="alert alert-info">ℹ️ Please create an activity on the dashboard first.</div>
        <?php endif; ?>

    </div>
    
    <script>
        function filterRoster() {
            const input = document.getElementById('searchBox');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('rosterTable');
            const rows = table.getElementsByTagName('tr');

            // Start from index 1 to skip the table header row
            for (let i = 1; i < rows.length; i++) {
                const rollNumber = rows[i].getAttribute('data-roll').toLowerCase();
                const studentName = rows[i].getAttribute('data-name');
                
                if (rollNumber.includes(filter) || studentName.includes(filter)) {
                    rows[i].style.display = ""; // Show row
                } else {
                    rows[i].style.display = "none"; // Hide row
                }
            }
        }
    </script>
</body>
</html>