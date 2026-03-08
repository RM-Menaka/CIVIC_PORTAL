<?php
// File: Civic_Portal/bearer/view_activities.php
session_start();
include "../db.php"; 

// --- TEMPORARY LOGIN BYPASS (FOR DEMO ONLY) ---
// !!! WARNING: REMOVE THIS BLOCK AFTER REVIEW. !!!
$_SESSION['role'] = 'office_bearer';
$_SESSION['username'] = 'Sasikumar';
$_SESSION['user_id'] = '1023141011';
$_SESSION['unit_id'] = 'Unit_105';
// --- END TEMPORARY LOGIN BYPASS ---


// 1. Authorization Check (DISABLED FOR DEMO)
/*
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'office_bearer') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}
*/

$unit_id = $_SESSION['unit_id'] ?? null;
$message = '';
$unit_activities = [];

// --- A. HANDLE ACTIVITY UPDATE LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_activity'])) {
    $activity_id = $_POST['activity_id'];
    $name = trim($_POST['activity_name']);
    $date = trim($_POST['activity_date']);
    $start_time = trim($_POST['activity_time']);
    $end_time = trim($_POST['end_time']); 
    $description = trim($_POST['description']);
    $current_unit = trim($_POST['unit_id']);
    
    if (empty($name) || empty($date) || empty($start_time) || empty($description)) {
        $message = "<div class='alert alert-danger'>❌ Error: All required fields must be filled.</div>";
    } else {
        // NOTE: The end_time column must be present in the activities table
        $stmt = $conn->prepare("
            UPDATE activities 
            SET activity_name = ?, activity_date = ?, activity_time = ?, end_time = ?, description = ?
            WHERE activity_id = ? AND unit_id = ?
        ");
        // 'sssssis' bind types: name, date, start_time, end_time, description, activity_id (int), unit_id (string)
        $stmt->bind_param("sssssis", $name, $date, $start_time, $end_time, $description, $activity_id, $current_unit);
        
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>✅ Activity **" . htmlspecialchars($name) . "** updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ DB Error: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    }
}

// --- B. FETCH ALL ACTIVITIES for Unit ---
if ($unit_id) {
    $stmt = $conn->prepare("
        SELECT 
            activity_id, activity_name, activity_date, activity_time, end_time, description 
        FROM activities 
        WHERE unit_id = ? 
        ORDER BY activity_date DESC
    ");
    $stmt->bind_param("s", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $unit_activities[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View & Edit Unit Activities</title>
    <style>
        /* Base Styling */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1); }
        h2 { color: #1b5e20; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        
        /* Search Box Styling */
        #searchBox {
            width: 100%;
            padding: 10px 15px;
            margin-bottom: 20px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1rem;
        }

        /* Activity Grid Styles */
        .activity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .activity-card {
            background-color: #ffffff;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s; 
        }
        
        /* Pale green background for even-numbered cards */
        .activity-card:nth-child(even) {
            background-color: #f0fff0; 
        }

        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .card-name {
            font-size: 1.25rem;
            color: #1b5e20;
            margin-bottom: 5px;
        }
        
        .card-details {
            font-size: 0.9rem;
            color: #555;
        }
        .card-details strong {
            font-weight: 600;
            color: #333;
        }
        .card-details span {
            display: block;
        }
        .card-description {
            font-size: 0.85rem;
            margin-top: 10px;
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
            background-color: rgba(0,0,0,0.5); 
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto; 
            padding: 30px;
            border-radius: 8px;
            width: 90%; 
            max-width: 600px;
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

        .close-btn:hover { color: #dc3545; cursor: pointer; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-group input, .form-group textarea { 
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; 
        }
        .time-group { display: flex; gap: 10px; }
        .time-group input { width: 100%; }

        .btn-save { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-save:hover { background-color: #388E3C; }
        
        .back-link { margin-bottom: 15px; display: block; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
        <h2>📅 Edit Activities for Unit <?php echo htmlspecialchars($unit_id); ?></h2>
        
        <?php echo $message; ?>
        
        <input type="text" id="searchBox" onkeyup="filterActivities()" placeholder="Search activities by name or details...">

        <?php if (empty($unit_activities)): ?>
            <div class="alert alert-info">ℹ️ No activities found for your unit. Please create one on the dashboard.</div>
        <?php else: ?>
            
            <div class="activity-grid" id="activityGrid">
                <?php foreach ($unit_activities as $activity): 
                    // Format dates and times for display
                    $date_display = (new DateTime($activity['activity_date']))->format('d-m-Y');
                    $time_display = substr($activity['activity_time'], 0, 5);
                    $end_time_display = $activity['end_time'] ? ' - ' . substr($activity['end_time'], 0, 5) : '';
                ?>
                    <div class="activity-card" 
                         data-search-term="<?php echo htmlspecialchars(strtolower($activity['activity_name'] . ' ' . $activity['description'])); ?>"
                         onclick="openModal(
                             '<?php echo htmlspecialchars($activity['activity_id']); ?>',
                             '<?php echo htmlspecialchars(addslashes($activity['activity_name'])); ?>',
                             '<?php echo htmlspecialchars($activity['activity_date']); ?>', /* ISO format for input value */
                             '<?php echo htmlspecialchars(substr($activity['activity_time'], 0, 5)); ?>',
                             '<?php echo htmlspecialchars(substr($activity['end_time'], 0, 5)); ?>',
                             '<?php echo htmlspecialchars(addslashes($activity['description'])); ?>',
                             '<?php echo htmlspecialchars($unit_id); ?>'
                         )">
                        
                        <div class="card-name"><?php echo htmlspecialchars($activity['activity_name']); ?></div>
                        <div class="card-details">
                            <span>**ID:** <?php echo htmlspecialchars($activity['activity_id']); ?></span>
                            <span>**Date:** <strong><?php echo $date_display; ?></strong></span>
                            <span>**Time:** <?php echo $time_display . $end_time_display; ?></span>
                        </div>
                        <p class="card-description"><?php echo htmlspecialchars(substr($activity['description'], 0, 50)) . (strlen($activity['description']) > 50 ? '...' : ''); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>
    
    <div id="editActivityModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2>Edit Activity: <span id="modalActivityNameDisplay"></span></h2>
            
            <form method="POST" action="view_activities.php" id="activityEditForm">
                
                <input type="hidden" name="activity_id" id="modalActivityId">
                <input type="hidden" name="unit_id" id="modalUnitId">
                
                <div class="form-group">
                    <label for="modalName">Activity Name</label>
                    <input type="text" name="activity_name" id="modalName" required>
                </div>
                
                <div class="form-group">
                    <label for="modalDate">Date</label>
                    <input type="date" name="activity_date" id="modalDate" required>
                </div>

                <div class="form-group">
                    <label>Time Range (Start & End)</label>
                    <div class="time-group">
                        <input type="time" name="activity_time" id="modalStartTime" required placeholder="Start Time">
                        <input type="time" name="end_time" id="modalEndTime" placeholder="End Time (Optional)">
                    </div>
                </div>

                <div class="form-group">
                    <label for="modalDescription">Description</label>
                    <textarea name="description" id="modalDescription" rows="4" required></textarea>
                </div>
                
                <button type="submit" name="update_activity" class="btn-save">💾 Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        var modal = document.getElementById("editActivityModal");

        function openModal(activityId, name, date, startTime, endTime, description, unitId) {
            // Populate form fields
            document.getElementById('modalActivityId').value = activityId;
            document.getElementById('modalUnitId').value = unitId;
            document.getElementById('modalActivityNameDisplay').textContent = name;
            
            document.getElementById('modalName').value = name;
            document.getElementById('modalDate').value = date; // Date needs YYYY-MM-DD format
            document.getElementById('modalStartTime').value = startTime;
            document.getElementById('modalEndTime').value = endTime;
            document.getElementById('modalDescription').value = description;
            
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
        
        /* --- ACTIVITY FILTERING LOGIC --- */
        function filterActivities() {
            const input = document.getElementById('searchBox');
            const filter = input.value.toLowerCase();
            const grid = document.getElementById('activityGrid');
            const cards = grid.getElementsByClassName('activity-card');

            for (let i = 0; i < cards.length; i++) {
                // Check against the stored data-search-term attribute (name + description)
                const searchTerm = cards[i].getAttribute('data-search-term');
                
                if (searchTerm.includes(filter)) {
                    cards[i].style.display = ""; // Show card
                } else {
                    cards[i].style.display = "none"; // Hide card
                }
            }
        }
        
    </script>
</body>
</html>