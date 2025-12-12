<?php
// match/schedule_matches.php - Schedule Matches for League
require_once '../config/database.php';
requireRole('admin');

$league_id = $_GET['league_id'] ?? null;
if (!$league_id) {
    showMessage("League ID required!", "error");
    redirect('manage_leagues.php');
}

$database = new Database();
$db = $database->connect();

// Get league details
$league_query = "SELECT l.*, s.name as sport_name FROM leagues l
                 JOIN sports s ON l.sport_id = s.id
                 WHERE l.id = :id";
$league_stmt = $db->prepare($league_query);
$league_stmt->bindParam(':id', $league_id);
$league_stmt->execute();
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

if (!$league) {
    showMessage("League not found!", "error");
    redirect('manage_leagues.php');
}

// Get teams in league
$teams_query = "SELECT * FROM teams WHERE league_id = :league_id ORDER BY name";
$teams_stmt = $db->prepare($teams_query);
$teams_stmt->bindParam(':league_id', $league_id);
$teams_stmt->execute();
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get venues
$venues_query = "SELECT * FROM venues ORDER BY name";
$venues_stmt = $db->prepare($venues_query);
$venues_stmt->execute();
$venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for creating matches
if ($_POST && isset($_POST['create_schedule'])) {
    $start_date = $_POST['start_date'];
    $match_interval = $_POST['match_interval']; // days between matches
    $matches_per_day = $_POST['matches_per_day'];

    $team_count = count($teams);
    if ($team_count < 2) {
        $error = "Need at least 2 teams to create a schedule!";
    } else {
        // Check if schedule already exists
        $matches_query = "SELECT COUNT(*) FROM matches WHERE league_id = :league_id AND match_type = 'round_robin'";
        $matches_stmt = $db->prepare($matches_query);
        $matches_stmt->bindParam(':league_id', $league_id);
        $matches_stmt->execute();
        $current_match_count = $matches_stmt->fetchColumn();

        $round_robin_rounds = $league['round_robin_rounds'] ?? 1;
        $matches_per_round = $team_count * ($team_count - 1) / 2;
        $total_expected_matches = $matches_per_round * $round_robin_rounds;

        if ($current_match_count >= $total_expected_matches) {
            showMessage("Round Robin schedule is already full ($current_match_count matches). Cannot schedule more matches.", "error");
        } else {
            // Create round-robin schedule
            $match_date = new DateTime($start_date);
            $match_counter = 0;
            $matches_generated = 0;

            // Generate matches for each round
            for ($round = 1; $round <= $round_robin_rounds; $round++) {

                // Check if matches for this round already exist
                $round_check_query = "SELECT COUNT(*) FROM matches WHERE league_id = :league_id AND round = :round AND match_type = 'round_robin'";
                $round_check_stmt = $db->prepare($round_check_query);
                $round_check_stmt->bindParam(':league_id', $league_id);
                $round_check_stmt->bindParam(':round', $round);
                $round_check_stmt->execute();

                if ($round_check_stmt->fetchColumn() > 0) {
                    // Skip existing round
                    continue;
                }

                // For each round, generate all pairings
                for ($i = 0; $i < $team_count; $i++) {
                    for ($j = $i + 1; $j < $team_count; $j++) {
                        // Alternate home/away based on round number
                        // Odd rounds: i vs j
                        // Even rounds: j vs i
                        if ($round % 2 != 0) {
                            $home_team = $teams[$i];
                            $away_team = $teams[$j];
                        } else {
                            $home_team = $teams[$j];
                            $away_team = $teams[$i];
                        }

                        // Assign venue (cycle through available venues)
                        $venue_id = null;
                        if (!empty($venues)) {
                            // Use total matches + generated so far to cycle venues, so we don't always start with venue 1
                            $venue = $venues[($current_match_count + $matches_generated) % count($venues)];
                            $venue_id = $venue['id'];
                        }

                        $insert_query = "INSERT INTO matches (league_id, home_team_id, away_team_id, venue_id, match_date, round, match_type)
                                        VALUES (:league_id, :home_team_id, :away_team_id, :venue_id, :match_date, :round, 'round_robin')";
                        $insert_stmt = $db->prepare($insert_query);
                        $insert_stmt->bindParam(':league_id', $league_id);
                        $insert_stmt->bindParam(':home_team_id', $home_team['id']);
                        $insert_stmt->bindParam(':away_team_id', $away_team['id']);
                        $insert_stmt->bindParam(':venue_id', $venue_id);
                        $insert_stmt->bindParam(':match_date', $match_date->format('Y-m-d H:i:s'));
                        $insert_stmt->bindParam(':round', $round);
                        $insert_stmt->execute();

                        $match_counter++;
                        $matches_generated++;

                        // Move to next date if we've reached matches per day limit
                        if ($match_counter % $matches_per_day == 0) {
                            $match_date->add(new DateInterval('P' . $match_interval . 'D'));
                        }
                    }
                }
            }

            if ($matches_generated > 0) {
                showMessage("Schedule updated successfully! $matches_generated new matches scheduled.", "success");
            } else {
                showMessage("No new matches were scheduled. All rounds appear to be complete.", "warning");
            }
            redirect('view_league.php?id=' . $league_id);
        }
    }
}

// Get existing matches for this league
$matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name
                  FROM matches m
                  JOIN teams ht ON m.home_team_id = ht.id
                  JOIN teams at ON m.away_team_id = at.id
                  LEFT JOIN venues v ON m.venue_id = v.id
                  WHERE m.league_id = :league_id
                  ORDER BY m.match_date ASC";
$matches_stmt = $db->prepare($matches_query);
$matches_stmt->bindParam(':league_id', $league_id);
$matches_stmt->execute();
$existing_matches = $matches_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate expected matches
$team_count = count($teams);
$round_robin_rounds = $league['round_robin_rounds'] ?? 1;
$matches_per_round = ($team_count >= 2) ? ($team_count * ($team_count - 1) / 2) : 0;
$total_expected_matches = $matches_per_round * $round_robin_rounds;

$current_rr_match_count = 0;
foreach ($existing_matches as $m) {
    if (($m['match_type'] ?? 'round_robin') == 'round_robin') {
        $current_rr_match_count++;
    }
}

$schedule_full = ($current_rr_match_count >= $total_expected_matches && $total_expected_matches > 0);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Schedule Matches - <?php echo htmlspecialchars($league['name']); ?></title>
    <style>
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; }
        .form-section { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 20px; }
        label { display: block; margin-bottom: 5px; }
        input, select { padding: 8px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; }
        .btn-danger { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .error { color: red; margin-bottom: 15px; }
        .info-box { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Schedule Matches - <?php echo htmlspecialchars($league['name']); ?></h2>

        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <div class="info-box">
            <p><strong>League:</strong> <?php echo htmlspecialchars($league['name']); ?> (<?php echo htmlspecialchars($league['sport_name']); ?>)</p>
            <p><strong>Teams:</strong> <?php echo count($teams); ?></p>
            <p><strong>Rounds:</strong> <?php echo $round_robin_rounds; ?> (<?php echo $round_robin_rounds == 1 ? 'Single' : ($round_robin_rounds == 2 ? 'Double' : $round_robin_rounds . ' Rounds'); ?>)</p>
            <p><strong>Potential Matches:</strong> <?php echo $total_expected_matches; ?></p>
            <p><strong>Current Matches:</strong> <?php echo $current_rr_match_count; ?></p>
        </div>

        <?php if ($schedule_full): ?>
            <div class="info-box" style="background: #d4edda; border-color: #c3e6cb; color: #155724;">
                <p><strong>Schedule Complete:</strong> All round-robin matches (<?php echo $total_expected_matches; ?> matches) have been scheduled.</p>
            </div>
        <?php elseif (count($teams) >= 2): ?>
        <div class="form-section">
            <h3>Create Round-Robin Schedule</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d', strtotime('+1 week')); ?>" required>
                </div>

                <div class="form-group">
                    <label>Days Between Match Days:</label>
                    <select name="match_interval">
                        <option value="1">Daily</option>
                        <option value="3">Every 3 days</option>
                        <option value="7" selected>Weekly</option>
                        <option value="14">Bi-weekly</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Matches Per Day:</label>
                    <select name="matches_per_day">
                        <option value="1">1 match</option>
                        <option value="2" selected>2 matches</option>
                        <option value="3">3 matches</option>
                        <option value="4">4 matches</option>
                    </select>
                </div>

                <br>
                <button type="submit" name="create_schedule" class="btn">Generate Schedule</button>
            </form>
        </div>
        <?php else: ?>
        <div class="info-box" style="background: #f8d7da; border-color: #f5c6cb;">
            <p>Need at least 2 teams to create a schedule. Current teams: <?php echo count($teams); ?></p>
        </div>
        <?php endif; ?>

        <?php if (count($existing_matches) > 0): ?>
        <h3>Current Match Schedule</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Home Team</th>
                    <th>Away Team</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Result</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($existing_matches as $match): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($match['match_date'])); ?></td>
                    <td><?php echo htmlspecialchars($match['home_team']); ?></td>
                    <td><?php echo htmlspecialchars($match['away_team']); ?></td>
                    <td><?php echo htmlspecialchars($match['venue_name'] ?? 'TBD'); ?></td>
                    <td><?php echo ucfirst($match['status']); ?></td>
                    <td>
                        <?php if ($match['status'] == 'completed'): ?>
                            <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="edit_match.php?id=<?php echo $match['id']; ?>" class="btn">Edit</a>
                        <?php if ($match['status'] == 'scheduled'): ?>
                            <a href="record_result.php?id=<?php echo $match['id']; ?>" class="btn" style="background: #28a745;">Record Result</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <p><a href="view_league.php?id=<?php echo $league_id; ?>">← Back to League</a></p>
    </div>
</body>
</html>
