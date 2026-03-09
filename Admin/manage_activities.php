<?php
session_start();
include "../db.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$search_unit_id = $_GET['unit_id'] ?? null;
$all_units = [];
$activities = [];

// Fetch all units
$units_result = $conn->query("SELECT unit_id, unit_name FROM units ORDER BY unit_id ASC");
if ($units_result) {
    while ($row = $units_result->fetch_assoc()) {
        $all_units[] = $row;
    }
}

// Fetch activities
if ($search_unit_id) {
    $stmt = $conn->prepare("
        SELECT a.activity_id, a.activity_name, a.activity_date, a.activity_time, a.description, u.unit_name
        FROM activities a
        JOIN units u ON a.unit_id = u.unit_id
        WHERE a.unit_id = ?
        ORDER BY a.activity_date DESC, a.activity_time ASC
    ");
    $stmt->bind_param("s", $search_unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Activities (Admin)</title>
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; margin: 0; padding: 20px; color: #333; }
    .container { max-width: 1100px; margin: 0 auto; }
    h2 { color: #007bff; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; margin-bottom: 20px; }

    .filter { margin-bottom: 25px; }
    select { padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 1rem; }

    .cards { display: flex; flex-wrap: wrap; gap: 20px; }
    .card { background: #fff; flex: 1 1 calc(33% - 20px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 10px; padding: 20px; transition: transform 0.2s; }
    .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
    
    .card h3 { margin: 0 0 10px; color: #28a745; font-size: 1.2rem; }
    .card .unit { font-weight: bold; color: #555; margin-bottom: 10px; }
    .card .details { font-size: 0.9rem; color: #666; margin-bottom: 10px; }
    .card .details strong { width: 80px; display: inline-block; color: #333; }
    .card .description { font-style: italic; color: #555; border-top: 1px dashed #eee; padding-top: 8px; margin-top: 10px; }
    
    @media(max-width: 900px) { .card { flex: 1 1 calc(50% - 20px); } }
    @media(max-width: 600px) { .card { flex: 1 1 100%; } }
</style>
</head>
<body>
<div class="container">
    <h2>Manage Activities</h2>
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

    <div class="filter">
        <form method="GET" action="manage_activities.php">
            <label for="unit_select">Filter by Unit: </label>
            <select id="unit_select" name="unit_id" onchange="this.form.submit()">
                <option value="">-- All Units --</option>
                <?php foreach ($all_units as $unit): ?>
                    <option value="<?php echo $unit['unit_id']; ?>" <?php echo ($unit['unit_id'] == $search_unit_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($unit['unit_id'] . " - " . $unit['unit_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($search_unit_id && empty($activities)): ?>
        <div class="alert">ℹ️ No activities found for Unit <?php echo htmlspecialchars($search_unit_id); ?>.</div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($activities as $activity): ?>
                <div class="card">
                    <h3><?php echo htmlspecialchars($activity['activity_name']); ?></h3>
                    <div class="unit">Unit: <?php echo htmlspecialchars($activity['unit_name']); ?></div>
                    <div class="details">
                        <div><strong>Date:</strong> <?php echo htmlspecialchars($activity['activity_date']); ?></div>
                        <div><strong>Time:</strong> <?php echo htmlspecialchars(substr($activity['activity_time'], 0, 5)); ?></div>
                        <div><strong>Type:</strong> 
                            <?php 
                                $name = strtolower($activity['activity_name']);
                                if (strpos($name, 'camp') !== false) echo 'Annual Camp';
                                elseif (strpos($name, 'meet') !== false || strpos($name, 'classroom') !== false) echo 'Classroom Meet';
                                elseif (strpos($name, 'orphanage') !== false) echo 'Orphanage Visit';
                                else echo 'General Event';
                            ?>
                        </div>
                    </div>
                    <div class="description"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
