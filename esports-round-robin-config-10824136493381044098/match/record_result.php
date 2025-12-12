<?php
// match/record_result.php - Record Match Results
require_once '../config/database.php';
require_once '../includes/LeagueManager.php';
requireRole('admin');

$database = new Database();
$db = $database->connect();

if (isset($_GET['id'])) {
    $match_id = $_GET['id'];

    // Handle form submission
    if ($_POST && isset($_POST['record_result'])) {
        $home_score = (int)$_POST['home_score'];
        $away_score = (int)$_POST['away_score'];
        $notes = trim($_POST['notes']);

        // 1. Update notes manually first (LeagueManager doesn't handle notes yet)
        $update_notes_query = "UPDATE matches SET notes = :notes WHERE id = :id";
        $update_stmt = $db->prepare($update_notes_query);
        $update_stmt->bindParam(':notes', $notes);
        $update_stmt->bindParam(':id', $match_id);
        $update_stmt->execute();

        // 2. Use LeagueManager to record result and update standings
        $leagueManager = new LeagueManager();
        try {
            if ($leagueManager->recordMatchResult($match_id, $home_score, $away_score)) {
                // Get league_id to redirect back
                $match_query = "SELECT league_id FROM matches WHERE id = :id";
                $match_stmt = $db->prepare($match_query);
                $match_stmt->bindParam(':id', $match_id);
                $match_stmt->execute();
                $match_data = $match_stmt->fetch(PDO::FETCH_ASSOC);

                showMessage("Match result recorded successfully!", "success");
                redirect('view_league.php?id=' . $match_data['league_id']);
            } else {
                 $error = "Failed to record result via LeagueManager.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }

    // Get match details
    $match_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name, l.name as league_name
                    FROM matches m
                    JOIN teams ht ON m.home_team_id = ht.id
                    JOIN teams at ON m.away_team_id = at.id
                    LEFT JOIN venues v ON m.venue_id = v.id
                    JOIN leagues l ON m.league_id = l.id
                    WHERE m.id = :id";
    $match_stmt = $db->prepare($match_query);
    $match_stmt->bindParam(':id', $match_id);
    $match_stmt->execute();
    $match = $match_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match) {
        showMessage("Match not found!", "error");
        redirect('manage_leagues.php');
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Record Match Result - Sports League</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .match-info { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; }
        .score-inputs { display: flex; align-items: center; gap: 20px; }
        .score-inputs input { width: 80px; text-align: center; font-size: 18px; }
        .vs { font-size: 24px; font-weight: bold; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Record Match Result</h2>

        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <div class="match-info">
            <h3><?php echo htmlspecialchars($match['league_name']); ?></h3>
            <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($match['match_date'])); ?></p>
            <p><strong>Venue:</strong> <?php echo htmlspecialchars($match['venue_name'] ?? 'TBD'); ?></p>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Final Score:</label>
                <div class="score-inputs">
                    <div style="text-align: center;">
                        <strong><?php echo htmlspecialchars($match['home_team']); ?></strong><br>
                        <input type="number" name="home_score" min="0" required>
                    </div>
                    <div class="vs">VS</div>
                    <div style="text-align: center;">
                        <strong><?php echo htmlspecialchars($match['away_team']); ?></strong><br>
                        <input type="number" name="away_score" min="0" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Match Notes (Optional):</label>
                <textarea name="notes" rows="4" placeholder="Any additional notes about the match..."><?php echo htmlspecialchars($match['notes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" name="record_result" class="btn">Record Result</button>
            <a href="view_league.php?id=<?php echo $match['league_id']; ?>" class="btn" style="background: #6c757d;">Cancel</a>
        </form>
    </div>
</body>
</html>

<?php
} else {
    showMessage("Invalid request.", "error");
    redirect('manage_leagues.php');
}
?>
