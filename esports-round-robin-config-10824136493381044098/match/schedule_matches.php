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

// Initialize round_robin_rounds from league data
$round_robin_rounds = $league['round_robin_rounds'] ?? 1;

$preview_matches = []; // Array to store matches for preview

// Handle "Clear Schedule"
if ($_POST && isset($_POST['clear_schedule'])) {
    // Only delete 'scheduled' matches to preserve history of completed games
    $delete_query = "DELETE FROM matches WHERE league_id = :league_id AND status = 'scheduled' AND match_type = 'round_robin'";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':league_id', $league_id);

    if ($delete_stmt->execute()) {
        $deleted_count = $delete_stmt->rowCount();
        showMessage("Schedule cleared! $deleted_count scheduled matches were removed. Completed matches were preserved.", "success");
    } else {
        showMessage("Error clearing schedule.", "error");
    }
    redirect("schedule_matches.php?league_id=$league_id");
}

// Handle "Confirm Schedule" (Insert from Preview)
if ($_POST && isset($_POST['confirm_schedule'])) {
    $matches_to_insert = $_POST['matches'] ?? [];
    $inserted_count = 0;

    foreach ($matches_to_insert as $m) {
        $insert_query = "INSERT INTO matches (league_id, home_team_id, away_team_id, venue_id, match_date, round, match_type, status)
                        VALUES (:league_id, :home_team_id, :away_team_id, :venue_id, :match_date, :round, 'round_robin', 'scheduled')";
        $insert_stmt = $db->prepare($insert_query);

        $match_date_fmt = date('Y-m-d H:i:s', strtotime($m['date']));
        $venue_id = !empty($m['venue_id']) ? $m['venue_id'] : null;

        $insert_stmt->bindParam(':league_id', $league_id);
        $insert_stmt->bindParam(':home_team_id', $m['home_team_id']);
        $insert_stmt->bindParam(':away_team_id', $m['away_team_id']);
        $insert_stmt->bindParam(':venue_id', $venue_id);
        $insert_stmt->bindParam(':match_date', $match_date_fmt);
        $insert_stmt->bindParam(':round', $m['round']);

        if ($insert_stmt->execute()) {
            $inserted_count++;
        }
    }

    if ($inserted_count > 0) {
        showMessage("Schedule confirmed! $inserted_count matches added.", "success");
    } else {
        showMessage("No matches were added.", "warning");
    }
    redirect('view_league.php?id=' . $league_id);
}

// Handle form submission for creating matches
if ($_POST && isset($_POST['preview_schedule'])) {
    $start_date = $_POST['start_date'];
    $match_interval = $_POST['match_interval']; // days between matches
    $matches_per_day = $_POST['matches_per_day'];

    // Determine rounds based on format
    $match_format = $_POST['match_format'];
    $rounds = 1; // Default
    if ($match_format == 'single') {
        $rounds = 1;
    } elseif ($match_format == 'double') {
        $rounds = 2;
    } elseif ($match_format == 'custom') {
        $rounds = (int)$_POST['custom_rounds'];
    }

    // Update league with new round count
    $update_query = "UPDATE leagues SET round_robin_rounds = :rounds WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([':rounds' => $rounds, ':id' => $league_id]);

    // Update local variable
    $round_robin_rounds = $rounds;
    $league['round_robin_rounds'] = $rounds;

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

                        // Add to Preview Array instead of DB
                        $preview_matches[] = [
                            'home_team_id' => $home_team['id'],
                            'away_team_id' => $away_team['id'],
                            'home_team_name' => $home_team['name'],
                            'away_team_name' => $away_team['name'],
                            'venue_id' => $venue_id,
                            'match_date' => $match_date->format('Y-m-d H:i'),
                            'round' => $round
                        ];

                        $match_counter++;
                        $matches_generated++;

                        // Move to next date if we've reached matches per day limit
                        if ($match_counter % $matches_per_day == 0) {
                            $match_date->add(new DateInterval('P' . $match_interval . 'D'));
                        }
                    }
                }
            }

            if ($matches_generated == 0) {
                showMessage("No new matches were scheduled. All rounds appear to be complete.", "warning");
            }
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
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; display: inline-block; margin-right: 10px; margin-bottom: 5px; }
        .btn-danger { background: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .error { color: red; margin-bottom: 15px; }
        .info-box { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-bottom: 20px; }

        /* Modal Styles */
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
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .score-input {
            width: 60px;
            text-align: center;
            font-size: 1.2em;
        }
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

        <?php if (!empty($preview_matches)): ?>
        <div class="form-section">
            <h3>Preview Schedule</h3>
            <div class="info-box">
                <p><strong>Preview Mode:</strong> Review the proposed schedule below. You can adjust dates and venues before saving.</p>
            </div>
            <form method="POST">
                <table>
                    <thead>
                        <tr>
                            <th>Round</th>
                            <th>Home Team</th>
                            <th>Away Team</th>
                            <th>Date</th>
                            <th>Venue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_matches as $k => $m): ?>
                        <tr>
                            <td><?php echo $m['round']; ?></td>
                            <td><?php echo htmlspecialchars($m['home_team_name']); ?></td>
                            <td><?php echo htmlspecialchars($m['away_team_name']); ?></td>
                            <td>
                                <input type="datetime-local" name="matches[<?php echo $k; ?>][date]" value="<?php echo date('Y-m-d\TH:i', strtotime($m['match_date'])); ?>" required>
                            </td>
                            <td>
                                <select name="matches[<?php echo $k; ?>][venue_id]">
                                    <option value="">-- No Venue --</option>
                                    <?php foreach ($venues as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo ($m['venue_id'] == $v['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="matches[<?php echo $k; ?>][home_team_id]" value="<?php echo $m['home_team_id']; ?>">
                                <input type="hidden" name="matches[<?php echo $k; ?>][away_team_id]" value="<?php echo $m['away_team_id']; ?>">
                                <input type="hidden" name="matches[<?php echo $k; ?>][round]" value="<?php echo $m['round']; ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br>
                <button type="submit" name="confirm_schedule" class="btn" style="background: #28a745;">Confirm & Save Schedule</button>
                <a href="schedule_matches.php?league_id=<?php echo $league_id; ?>" class="btn" style="background: #6c757d;">Cancel</a>
            </form>
        </div>

        <?php elseif ($schedule_full): ?>
            <div class="info-box" style="background: #d4edda; border-color: #c3e6cb; color: #155724;">
                <p><strong>Schedule Complete:</strong> All round-robin matches (<?php echo $total_expected_matches; ?> matches) have been scheduled.</p>
            </div>

            <div style="margin-top: 10px;">
                <form method="POST" onsubmit="return confirm('Are you sure you want to clear the schedule? This will delete all SCHEDULED matches. Completed matches will be preserved.');">
                    <button type="submit" name="clear_schedule" class="btn btn-danger">Clear Schedule</button>
                </form>
            </div>
        <?php elseif (count($teams) >= 2): ?>
        <div class="form-section">
            <h3><?php echo $current_rr_match_count > 0 ? 'Schedule Remaining Rounds' : 'Create Round-Robin Schedule'; ?></h3>
            <form method="POST" id="scheduleForm">
                <div class="form-group">
                    <label>Match Format:</label>
                    <select name="match_format" id="matchFormatSelect" onchange="toggleCustomRounds()">
                        <option value="single" <?php echo ($round_robin_rounds == 1) ? 'selected' : ''; ?>>Single Round Robin (1 Round)</option>
                        <option value="double" <?php echo ($round_robin_rounds == 2) ? 'selected' : ''; ?>>Double Round Robin (2 Rounds)</option>
                        <option value="custom" <?php echo ($round_robin_rounds > 2) ? 'selected' : ''; ?>>Custom Format</option>
                    </select>
                </div>

                <div class="form-group" id="customRoundsGroup" style="display: <?php echo ($round_robin_rounds > 2) ? 'inline-block' : 'none'; ?>;">
                    <label>Number of Rounds:</label>
                    <input type="number" name="custom_rounds" id="customRoundsInput" value="<?php echo ($round_robin_rounds > 2) ? $round_robin_rounds : 3; ?>" min="1" max="10">
                </div>

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
                <button type="submit" name="preview_schedule" class="btn"><?php echo $current_rr_match_count > 0 ? 'Preview Remaining Matches' : 'Preview Schedule'; ?></button>
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
                    <td id="status-<?php echo $match['id']; ?>"><?php echo ucfirst($match['status']); ?></td>
                    <td id="score-<?php echo $match['id']; ?>">
                        <?php if ($match['status'] == 'completed'): ?>
                            <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($match['status'] == 'completed'): ?>
                            <button onclick="openEditModal(<?php echo $match['id']; ?>, '<?php echo htmlspecialchars(addslashes($match['home_team'])); ?>', '<?php echo htmlspecialchars(addslashes($match['away_team'])); ?>', <?php echo $match['home_score']; ?>, <?php echo $match['away_score']; ?>)" class="btn">Edit</button>
                        <?php else: ?>
                            <a href="edit_match.php?id=<?php echo $match['id']; ?>" class="btn">Edit</a>
                        <?php endif; ?>

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

    <!-- Edit Score Modal -->
    <div id="editScoreModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>Edit Match Score</h3>
            <p id="modalTeams"></p>
            <form id="editScoreForm" onsubmit="submitScoreUpdate(event)">
                <input type="hidden" id="editMatchId" name="match_id">
                <div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin: 20px 0;">
                    <div style="text-align: center;">
                        <label id="homeTeamLabel"></label>
                        <input type="number" id="editHomeScore" name="home_score" class="score-input" required min="0">
                    </div>
                    <span style="font-weight: bold;">-</span>
                    <div style="text-align: center;">
                        <label id="awayTeamLabel"></label>
                        <input type="number" id="editAwayScore" name="away_score" class="score-input" required min="0">
                    </div>
                </div>
                <div style="text-align: center;">
                    <button type="submit" class="btn">Update Score</button>
                </div>
                <div id="updateMessage" style="text-align: center; margin-top: 10px;"></div>
            </form>
        </div>
    </div>

    <script>
        function toggleCustomRounds() {
            var formatSelect = document.getElementById('matchFormatSelect');
            var customGroup = document.getElementById('customRoundsGroup');
            var customInput = document.getElementById('customRoundsInput');

            if (formatSelect.value === 'custom') {
                customGroup.style.display = 'inline-block';
                customInput.setAttribute('required', 'required');
            } else {
                customGroup.style.display = 'none';
                customInput.removeAttribute('required');
            }
        }

        // Modal Functions
        var modal = document.getElementById("editScoreModal");

        function openEditModal(matchId, homeTeam, awayTeam, homeScore, awayScore) {
            document.getElementById('editMatchId').value = matchId;
            document.getElementById('modalTeams').innerText = homeTeam + " vs " + awayTeam;
            document.getElementById('homeTeamLabel').innerText = homeTeam;
            document.getElementById('awayTeamLabel').innerText = awayTeam;
            document.getElementById('editHomeScore').value = homeScore;
            document.getElementById('editAwayScore').value = awayScore;
            document.getElementById('updateMessage').innerHTML = '';
            modal.style.display = "block";
        }

        function closeEditModal() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeEditModal();
            }
        }

        function submitScoreUpdate(event) {
            event.preventDefault();

            var matchId = document.getElementById('editMatchId').value;
            var homeScore = document.getElementById('editHomeScore').value;
            var awayScore = document.getElementById('editAwayScore').value;
            var messageDiv = document.getElementById('updateMessage');

            messageDiv.innerHTML = 'Updating...';

            var formData = new FormData();
            formData.append('match_id', matchId);
            formData.append('home_score', homeScore);
            formData.append('away_score', awayScore);

            fetch('update_score.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<span style="color: green;">' + data.message + '</span>';

                    // Update the UI
                    document.getElementById('score-' + matchId).innerText = homeScore + ' - ' + awayScore;

                    // Update the onclick handler with new scores
                    var editBtn = document.querySelector('button[onclick*="openEditModal(' + matchId + ',"]');
                    if (editBtn) {
                        // Extract original team names from current onclick (since they are constant)
                        var onclickStr = editBtn.getAttribute('onclick');
                        // Simple regex to extract team names might be fragile, so we can store them in data attributes if preferred.
                        // But for now, since we have them in the closure of this function call if we wanted,
                        // or we can just reconstruct the string if we assume team names haven't changed.
                        // Actually, easier way: Just update the scores in the onclick attribute.

                        // We can just get the team names from the labels we populated
                        var homeTeam = document.getElementById('homeTeamLabel').innerText;
                        var awayTeam = document.getElementById('awayTeamLabel').innerText;

                        // Escape quotes for the function call
                        var homeTeamEscaped = homeTeam.replace(/'/g, "\\'");
                        var awayTeamEscaped = awayTeam.replace(/'/g, "\\'");

                        editBtn.setAttribute('onclick', "openEditModal(" + matchId + ", '" + homeTeamEscaped + "', '" + awayTeamEscaped + "', " + homeScore + ", " + awayScore + ")");
                    }

                    setTimeout(function() {
                        closeEditModal();
                    }, 1500);
                } else {
                    messageDiv.innerHTML = '<span style="color: red;">' + data.message + '</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.innerHTML = '<span style="color: red;">An error occurred.</span>';
            });
        }
    </script>
</body>
</html>
