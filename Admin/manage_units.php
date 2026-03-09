<?php
session_start();
include "../db.php"; 

// 1. AUTHORIZATION CHECK (Admin only)
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$selected_unit_id = $_GET['unit_id'] ?? null;
$all_units = [];
$office_bearers = [];
$message = '';
$show_modal_on_load = false; // Flag to reopen modal on error

// --- 2. HANDLE OFFICE BEARER UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_bearer'])) {
    $b_student_id = trim($_POST['student_id']);
    $b_name = trim($_POST['name']);
    $b_contact = trim($_POST['contact_number']);
    $b_designation = trim($_POST['designation']);
    $b_year_of_post = intval($_POST['year_of_post']);
    $b_unit_id = trim($_POST['unit_id']);
    
    // Basic validation
    if (empty($b_name) || empty($b_contact) || empty($b_designation) || $b_year_of_post < 1) {
        $message = "<div class='alert alert-danger'>❌ Error: All fields must be filled correctly.</div>";
        $show_modal_on_load = true; // Reopen modal if error occurs
    } else {
        $stmt = $conn->prepare("
            UPDATE office_bearers 
            SET name = ?, contact_number = ?, designation = ?, year_of_post = ? 
            WHERE student_id = ? AND unit_id = ?
        ");
        $stmt->bind_param("sssiss", $b_name, $b_contact, $b_designation, $b_year_of_post, $b_student_id, $b_unit_id);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Details for " . htmlspecialchars($b_name) . " updated successfully!</div>";
            $selected_unit_id = $b_unit_id;
        } else {
            $message = "<div class='alert alert-danger'>❌ DB Error: " . htmlspecialchars($stmt->error) . "</div>";
            $show_modal_on_load = true; // Reopen modal if error occurs
        }
        $stmt->close();
    }
}

// --- 3. FETCH ALL UNITS for Selection Dropdown (ORDERED BY UNIT ID) ---
$units_result = $conn->query("SELECT unit_id, unit_name FROM units ORDER BY unit_id ASC"); 
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $all_units[] = $row;
    }
}

// --- 4. FETCH BEARER DATA for Selected Unit ---
if ($selected_unit_id) {
    $stmt = $conn->prepare("
        SELECT student_id, name, contact_number, designation, year_of_post, unit_id
        FROM office_bearers 
        WHERE unit_id = ? 
        ORDER BY year_of_post DESC, designation
    ");
    $stmt->bind_param("s", $selected_unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $office_bearers[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Units & Office Bearers</title>
    <style>
        /* BASE STYLES */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        
        /* THEME COLOR CHANGES */
        h2 { 
            color: #28a645; /* Darker Green Header */
            border-bottom: 2px solid #e9ecef; 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }
        
        .select-unit { margin-bottom: 25px; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; background: #f8f9fa; display: flex; align-items: center; gap: 15px; }
        select, input[type="text"], input[type="number"] { padding: 10px; border: 1px solid #ced4da; border-radius: 4px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }

        /* BEARER CARD GRID STYLING */
        .bearer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .bearer-card {
            background-color: #ffffff;
            border: 1px solid #a5d6a7; /* Light green border */
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            padding: 20px;
            cursor: pointer; 
            transition: transform 0.2s, background-color 0.2s;
        }
        
        .bearer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            background-color: #e6f7e6; /* Very light green hover */
        }

        .card-designation {
            font-size: 1.4rem;
            font-weight: 700;
            color: #34a754; /* Medium Green for Role Text */
            margin-bottom: 5px;
        }
        
        .card-name {
            font-size: 1rem;
            color: #555;
            margin-bottom: 10px;
        }
        
        .card-info-line {
            font-size: 0.9rem;
            color: #6c757d;
            padding-top: 5px;
            border-top: 1px dashed #eee;
        }

        /* MODAL STYLING */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.6); 
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 30px;
            border-radius: 8px;
            width: 90%; 
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }

        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }

        .close-btn:hover, .close-btn:focus {
            color: #dc3545;
            text-decoration: none;
            cursor: pointer;
        }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { font-weight: bold; display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;}
        
        /* Button Color Change */
        .btn-save { 
            background-color: #28a745; /* Dark Green Button */
            color: white; 
            padding: 10px 15px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
        }
        .btn-save:hover { 
            background-color: #218838; /* Darker Green Hover */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Unit Management & Office Bearer Roles</h2>
        <a href="dashboard.php" style="
    display:inline-block;
    margin-bottom:15px;
    padding:8px 14px;
    background:#007bff;
    color:white;
    text-decoration:none;
    border-radius:5px;
    font-size:14px;">
    ⬅ Back to Dashboard
</a>
        
        <?php echo $message; ?>

        <div class="select-unit">
            <form method="GET" action="manage_units.php" style="margin: 0;">
                <label for="unit_select">Select Unit to Manage Bearers:</label>
                <select id="unit_select" name="unit_id" onchange="this.form.submit()">
                    <option value="">-- Select Unit --</option>
                    <?php foreach ($all_units as $unit): ?>
                        <option value="<?php echo $unit['unit_id']; ?>" 
                            <?php echo ($unit['unit_id'] == $selected_unit_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($unit['unit_id']) . " - " . htmlspecialchars($unit['unit_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_unit_id && empty($office_bearers)): ?>
            <div class="alert alert-info">ℹ️ No office bearers found for this unit.</div>
        <?php elseif (!empty($office_bearers)): ?>
            
            <h3>Office Bearers for Unit: <?php echo htmlspecialchars($selected_unit_id); ?></h3>
            
            <div class="bearer-grid">
                <?php foreach ($office_bearers as $bearer): ?>
                    <div class="bearer-card" 
                         onclick="openModal(
                             '<?php echo htmlspecialchars($bearer['student_id']); ?>',
                             '<?php echo htmlspecialchars(addslashes($bearer['name'])); ?>',
                             '<?php echo htmlspecialchars(addslashes($bearer['designation'])); ?>',
                             '<?php echo htmlspecialchars($bearer['contact_number']); ?>',
                             '<?php echo htmlspecialchars($bearer['year_of_post']); ?>',
                             '<?php echo htmlspecialchars($bearer['unit_id']); ?>'
                         )">
                        
                        <div class="card-designation"><?php echo htmlspecialchars($bearer['designation']); ?></div>
                        <div class="card-name">**<?php echo htmlspecialchars($bearer['name']); ?>**</div>
                        <div class="card-info-line">Roll No: <?php echo htmlspecialchars($bearer['student_id']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
    
    <div id="editBearerModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3>Edit Bearer Details (<span id="modalDesignation"></span>)</h3>
            
            <form method="POST" action="manage_units.php" id="bearerEditForm">
                
                <input type="hidden" name="unit_id" id="modalUnitId">
                <input type="hidden" name="student_id" id="modalStudentId">
                
                <div class="form-group">
                    <label for="modalName">Name</label>
                    <input type="text" name="name" id="modalName" required>
                </div>
                
                <div class="form-group">
                    <label for="modalDesignationInput">Designation</label>
                    <input type="text" name="designation" id="modalDesignationInput" required>
                </div>

                <div class="form-group">
                    <label for="modalContact">Contact Number</label>
                    <input type="text" name="contact_number" id="modalContact" required>
                </div>
                
                <div class="form-group">
                    <label for="modalYear">Year (Seniority)</label>
                    <input type="number" name="year_of_post" id="modalYear" required min="1" max="4">
                </div>
                
                <button type="submit" name="update_bearer" class="btn-save">💾 Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        var modal = document.getElementById("editBearerModal");

        function openModal(studentId, name, designation, contact, year, unitId) {
            // Populate hidden fields and visible inputs
            document.getElementById('modalStudentId').value = studentId;
            document.getElementById('modalUnitId').value = unitId;
            document.getElementById('modalDesignation').textContent = designation;
            document.getElementById('modalName').value = name;
            document.getElementById('modalDesignationInput').value = designation;
            document.getElementById('modalContact').value = contact;
            document.getElementById('modalYear').value = year;
            
            modal.style.display = "block";
        }

        function closeModal() {
            modal.style.display = "none";
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Reopen modal if there was a server-side error (based on PHP flag)
        <?php if ($show_modal_on_load): ?>
            // Use the data from the last POST request to repopulate and show the modal
            document.getElementById('modalStudentId').value = '<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>';
            document.getElementById('modalUnitId').value = '<?php echo htmlspecialchars($_POST['unit_id'] ?? ''); ?>';
            document.getElementById('modalDesignation').textContent = '<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>';
            document.getElementById('modalName').value = '<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>';
            document.getElementById('modalDesignationInput').value = '<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>';
            document.getElementById('modalContact').value = '<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>';
            document.getElementById('modalYear').value = '<?php echo htmlspecialchars($_POST['year_of_post'] ?? ''); ?>';
            modal.style.display = "block";
        <?php endif; ?>
        
    </script>
</body>
</html>