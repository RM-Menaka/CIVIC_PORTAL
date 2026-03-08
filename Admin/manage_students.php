<?php
session_start();

// 1. AUTHORIZATION CHECK
// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

// 2. DATABASE CONNECTION
include "../db.php"; 

// 3. INITIALIZATION
$search_id = null;
$student_data = null;
$attendance_percentage = null;
$all_units = [];
$message = '';

// --- Fetch All Units for Dropdown ---
// Order units numerically (Unit 1 → Unit 12 in correct order)
$units_result = $conn->query("
    SELECT unit_id, unit_name 
    FROM units 
    ORDER BY CAST(SUBSTRING_INDEX(unit_name, ' ', -1) AS UNSIGNED)
");
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $all_units[] = $row;
    }
}

// 4. HANDLE STUDENT UPDATE 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    $update_id = trim($_POST['student_id']); 
    $new_unit_id = trim($_POST['new_unit_id']);
    $new_name = trim($_POST['new_name']);
    
    if (!empty($update_id) && is_numeric($update_id) && !empty($new_unit_id) && !empty($new_name)) {
        $stmt = $conn->prepare("UPDATE students SET name = ?, unit_id = ? WHERE student_id = ?");
        $stmt->bind_param("sss", $new_name, $new_unit_id, $update_id); 
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Student details for <strong>" . htmlspecialchars($new_name) . "</strong> updated successfully!</div>";
            $search_id = $update_id; 
        } else {
            $message = "<div class='alert alert-danger'>❌ Error updating record: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
         $message = "<div class='alert alert-warning'>⚠️ Error: All fields are required and Student ID must be valid.</div>";
    }
}

// 5. HANDLE STUDENT SEARCH 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_student']) || (isset($search_id) && is_numeric($search_id))) {
    $search_id = isset($_POST['roll_number']) ? trim($_POST['roll_number']) : $search_id;

    if (!empty($search_id) && is_numeric($search_id)) {
        $sql_student = "SELECT s.student_id, s.name, s.year, s.department, u.unit_name, u.unit_id 
                        FROM students s 
                        JOIN units u ON s.unit_id = u.unit_id 
                        WHERE s.student_id = ?";
        $stmt_student = $conn->prepare($sql_student);
        $stmt_student->bind_param("s", $search_id);
        $stmt_student->execute();
        $result_student = $stmt_student->get_result();

        if ($result_student->num_rows > 0) {
            $student_data = $result_student->fetch_assoc();

            // Attendance Percentage
            $sql_attendance = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count 
                               FROM attendance 
                               WHERE student_id = ?";
            $stmt_att = $conn->prepare($sql_attendance);
            $stmt_att->bind_param("s", $search_id);
            $stmt_att->execute();
            $result_att = $stmt_att->get_result();
            $att_counts = $result_att->fetch_assoc();
            
            $total_meetings = $att_counts['total'];
            $present_meetings = $att_counts['present_count'];
            
            if ($total_meetings > 0) {
                $attendance_percentage = round(($present_meetings / $total_meetings) * 100, 2);
            } else {
                $attendance_percentage = "N/A (0)";
            }
            $stmt_att->close();

        } else {
            $message = "<div class='alert alert-info'>ℹ️ No student found with Roll Number: <strong>" . htmlspecialchars($search_id) . "</strong></div>";
            $student_data = null;
        }
        $stmt_student->close();
    } else if (!empty($search_id)) {
         $message = "<div class='alert alert-warning'>⚠️ Invalid Roll Number provided.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students - Admin Panel</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f8f9fa; }
        .header { background-color: #28a745; color: white; padding: 15px 0; text-align: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .container { max-width: 960px; margin: 30px auto; padding: 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); padding: 25px; margin-bottom: 25px; }
        .card-header { font-size: 1.5rem; color: #343a40; margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; }
        .alert-warning { background-color: #fff3cd; color: #856404; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 1rem; }
        .search-container { display: flex; gap: 10px; }
        .search-container .form-control { width: 300px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-success:hover { background-color: #218838; }
        .table { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .table th, .table td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
        .table th { background-color: #f1f1f1; color: #495057; font-weight: 600; }
        .attendance-high { color: #28a745; font-weight: bold; }
        .attendance-low { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Student Management Portal</h1>
        <p>Manage Enrollment, Attendance, and Unit Transfers</p>
    </div>

    <div class="container">
        
        <?php echo $message; ?>

        <div class="card">
            <div class="card-header">🔍 Find Student by Roll Number</div>
            <form method="POST" action="manage_students.php">
                <div class="form-group search-container">
                    <input type="number" id="roll_number" name="roll_number" class="form-control" 
                           required value="<?php echo htmlspecialchars($search_id ?? ''); ?>" placeholder="Enter Student Roll Number (ID)">
                    <button type="submit" name="search_student" class="btn btn-success">Search</button>
                </div>
            </form>
        </div>

        <?php if ($student_data): ?>
        <div class="card">
            <div class="card-header">✏️ Edit Details for: <?php echo htmlspecialchars($student_data['name']); ?> (#<?php echo htmlspecialchars($student_data['student_id']); ?>)</div>
            
            <form method="POST" action="manage_students.php">
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_data['student_id']); ?>">

                <table class="table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Current Status</th>
                            <th>Edit/Update Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Student Name</td>
                            <td><?php echo htmlspecialchars($student_data['name']); ?></td>
                            <td><input type="text" name="new_name" class="form-control" value="<?php echo htmlspecialchars($student_data['name']); ?>" required></td>
                        </tr>
                        <tr>
                            <td>Department</td>
                            <td colspan="2"><strong><?php echo htmlspecialchars($student_data['department']); ?></strong></td>
                        </tr>
                        <tr>
                            <td>Academic Year</td>
                            <td colspan="2">Year <?php echo htmlspecialchars($student_data['year']); ?></td>
                        </tr>
                        <tr>
                            <td>NSS Unit</td>
                            <td><?php echo htmlspecialchars($student_data['unit_name']); ?></td>
                            <td>
                                <select name="new_unit_id" class="form-control" required>
                                    <?php foreach ($all_units as $unit): ?>
                                        <option value="<?php echo $unit['unit_id']; ?>" 
                                            <?php echo ($unit['unit_id'] == $student_data['unit_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit['unit_name'] . " (" . $unit['unit_id'] . ")"); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Change Unit to transfer student.</small>
                            </td>
                        </tr>
                        <tr>
                            <td>Attendance %</td>
                            <?php $att_class = ($attendance_percentage >= 75) ? 'attendance-high' : 'attendance-low'; ?>
                            <td colspan="2">
                                <span class="<?php echo $att_class; ?>">
                                    <?php echo htmlspecialchars($attendance_percentage); ?>%
                                </span> 
                                <br><small>(Total meetings: <?php echo $total_meetings; ?>)</small>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <button type="submit" name="update_student" class="btn btn-primary mt-3">💾 Save Changes (Name & Unit)</button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
