<?php
// match/edit_match.php - Edit Match Details (Date/Venue)
require_once '../config/database.php';
requireRole('admin');

$match_id = $_GET['id'] ?? null;
if (!$match_id) {
    showMessage("Match ID required!", "error");
    redirect('../admin/dashboard.php');
}

$database = new Database();
$db = $database->connect();

// Fetch match details
$query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name
          FROM matches m
          JOIN teams ht ON m.home_team_id = ht.id
          JOIN teams at ON m.away_team_id = at.id
          LEFT JOIN venues v ON m.venue_id = v.id
          WHERE m.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $match_id);
$stmt->execute();
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    showMessage("Match not found!", "error");
    redirect('../admin/dashboard.php');
}

// Get venues for dropdown
$venues_query = "SELECT * FROM venues ORDER BY name";
$venues_stmt = $db->prepare($venues_query);
$venues_stmt->execute();
$venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Update
if ($_POST && isset($_POST['update_match'])) {
    // Handle T separator from datetime-local input if present
    $match_date = str_replace('T', ' ', $_POST['match_date']);
    $venue_id = !empty($_POST['venue_id']) ? $_POST['venue_id'] : null;
    $notes = $_POST['notes'] ?? '';

    $update_query = "UPDATE matches SET match_date = :match_date, venue_id = :venue_id, notes = :notes WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':match_date', $match_date);
    $update_stmt->bindParam(':venue_id', $venue_id);
    $update_stmt->bindParam(':notes', $notes);
    $update_stmt->bindParam(':id', $match_id);

    if ($update_stmt->execute()) {
        showMessage("Match updated successfully!", "success");
        redirect("schedule_matches.php?league_id=" . $match['league_id']);
    } else {
        $error = "Failed to update match.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Match - <?php echo htmlspecialchars($match['home_team'] . ' vs ' . $match['away_team']); ?></title>
    <style>
        /* Base styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }

        /* Navigation (simulated based on dashboard.php style) */
        .header { background: #343a40; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; margin-left: 10px; }
        .nav a:hover { background: #495057; }

        /* Page content */
        .container { max-width: 800px; margin: 0 auto 50px; padding: 0 20px; }
        .form-section { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        input, select, textarea { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 4px; display: inline-block; font-size: 16px; margin-right: 10px; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sports League Management</h1>
        <div class="nav">
            <a href="../admin/dashboard.php">Dashboard</a>
            <a href="../admin/manage_leagues.php">Manage Leagues</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 20px;">Edit Match Details</h2>
        <h3 style="text-align: center; color: #555;">
            <?php echo htmlspecialchars($match['home_team']); ?> vs <?php echo htmlspecialchars($match['away_team']); ?>
        </h3>

        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <div class="form-section">
            <form method="POST">
                <div class="form-group">
                    <label>Match Date & Time:</label>
                    <input type="datetime-local" name="match_date" value="<?php echo date('Y-m-d\TH:i', strtotime($match['match_date'])); ?>" required>
                </div>

                <div class="form-group">
                    <label>Venue:</label>
                    <select name="venue_id">
                        <option value="">-- No Venue --</option>
                        <?php foreach ($venues as $v): ?>
                        <option value="<?php echo $v['id']; ?>" <?php echo ($match['venue_id'] == $v['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($v['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes (Optional):</label>
                    <textarea name="notes" rows="3"><?php echo htmlspecialchars($match['notes'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" name="update_match" class="btn">Update Match</button>
                    <a href="schedule_matches.php?league_id=<?php echo $match['league_id']; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
