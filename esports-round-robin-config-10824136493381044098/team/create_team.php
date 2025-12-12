<?php
// team/create_team.php - Create New Team
require_once '../config/database.php';
requireLogin();

if ($_POST) {
    $database = new Database();
    $db = $database->connect();
    
    $name = trim($_POST['name']);
    $league_id = $_POST['league_id'];
    $description = trim($_POST['description']);
    $owner_id = $_SESSION['user_id'];
    
    // Check if user already owns a team in this league
    $check_query = "SELECT id FROM teams WHERE league_id = :league_id AND owner_id = :owner_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':league_id', $league_id);
    $check_stmt->bindParam(':owner_id', $owner_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        $error = "You already own a team in this league!";
    } else {
        // Check if league has space for more teams
        $league_query = "SELECT l.max_teams, COUNT(t.id) as current_teams 
                         FROM leagues l 
                         LEFT JOIN teams t ON l.id = t.league_id 
                         WHERE l.id = :league_id 
                         GROUP BY l.id";
        $league_stmt = $db->prepare($league_query);
        $league_stmt->bindParam(':league_id', $league_id);
        $league_stmt->execute();
        $league_info = $league_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($league_info['current_teams'] >= $league_info['max_teams']) {
            $error = "This league is full!";
        } else {
            $query = "INSERT INTO teams (name, league_id, owner_id, description) 
                      VALUES (:name, :league_id, :owner_id, :description)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':league_id', $league_id);
            $stmt->bindParam(':owner_id', $owner_id);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $team_id = $db->lastInsertId();
                
                // Add owner as team member
                $member_query = "INSERT INTO team_members (team_id, player_id, position) 
                                VALUES (:team_id, :player_id, 'Captain')";
                $member_stmt = $db->prepare($member_query);
                $member_stmt->bindParam(':team_id', $team_id);
                $member_stmt->bindParam(':player_id', $owner_id);
                $member_stmt->execute();
                
                showMessage("Team created successfully!", "success");
                redirect('manage_team.php?id=' . $team_id);
            } else {
                $error = "Failed to create team!";
            }
        }
    }
}

// Get available leagues
$database = new Database();
$db = $database->connect();
$leagues_query = "SELECT l.*, s.name as sport_name 
                  FROM leagues l 
                  JOIN sports s ON l.sport_id = s.id 
                  WHERE l.status IN ('open', 'draft') 
                  AND l.registration_deadline >= CURDATE()
                  ORDER BY l.name";
$leagues_stmt = $db->prepare($leagues_query);
$leagues_stmt->execute();
$leagues = $leagues_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Team - Sports League</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; }
        textarea { height: 100px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create New Team</h2>
        
        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Team Name:</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>League:</label>
                <select name="league_id" required>
                    <option value="">Select League</option>
                    <?php foreach ($leagues as $league): ?>
                        <option value="<?php echo $league['id']; ?>">
                            <?php echo htmlspecialchars($league['name']); ?> - <?php echo htmlspecialchars($league['sport_name']); ?> (<?php echo htmlspecialchars($league['season']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Team Description:</label>
                <textarea name="description" placeholder="Describe your team..."></textarea>
            </div>
            
            <button type="submit" class="btn">Create Team</button>
            <a href="dashboard.php" class="btn" style="background: #6c757d;">Cancel</a>
        </form>
    </div>
</body>
</html>

<?php
// team/manage_team.php - Manage Team
if (isset($_GET['id']) && !isset($_POST['name'])) {
    require_once '../config/database.php';
    requireLogin();
    
    $team_id = $_GET['id'];
    $database = new Database();
    $db = $database->connect();
    
    // Get team details
    $query = "SELECT t.*, l.name as league_name, l.id as league_id, s.name as sport_name, u.username as owner_username
              FROM teams t 
              JOIN leagues l ON t.league_id = l.id 
              JOIN sports s ON l.sport_id = s.id
              JOIN users u ON t.owner_id = u.id
              WHERE t.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $team_id);
    $stmt->execute();
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$team) {
        showMessage("Team not found!", "error");
        redirect('dashboard.php');
    }
    
    // Check if user is owner or admin
    $user = getCurrentUser();
    if ($team['owner_id'] != $user['id'] && $user['role'] != 'admin') {
        showMessage("Access denied!", "error");
        redirect('dashboard.php');
    }
    
    // Get team members
    $members_query = "SELECT tm.*, u.first_name, u.last_name, u.username, u.email
                      FROM team_members tm
                      JOIN users u ON tm.player_id = u.id
                      WHERE tm.team_id = :team_id AND tm.status = 'active'
                      ORDER BY tm.position, u.first_name";
    $members_stmt = $db->prepare($members_query);
    $members_stmt->bindParam(':team_id', $team_id);
    $members_stmt->execute();
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending registration requests
    $requests_query = "SELECT rr.*, u.first_name, u.last_name, u.username
                       FROM registration_requests rr
                       JOIN users u ON rr.player_id = u.id
                       WHERE rr.team_id = :team_id AND rr.status = 'pending'
                       ORDER BY rr.created_at DESC";
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->bindParam(':team_id', $team_id);
    $requests_stmt->execute();
    $requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage <?php echo htmlspecialchars($team['name']); ?> - Sports League</title>
    <style>
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; }
        .team-header { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #e9ecef; margin-right: 5px; cursor: pointer; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
        .btn { background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; margin-right: 5px; border: none; cursor: pointer; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .request-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 10px; }
    </style>
    <script>
        function showTab(tabName) {
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function processRequest(requestId, action) {
            if (confirm('Are you sure?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_request.php';
                
                var requestIdInput = document.createElement('input');
                requestIdInput.type = 'hidden';
                requestIdInput.name = 'request_id';
                requestIdInput.value = requestId;
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = action;
                
                form.appendChild(requestIdInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="team-header">
            <h1><?php echo htmlspecialchars($team['name']); ?></h1>
            <p><strong>League:</strong> <?php echo htmlspecialchars($team['league_name']); ?> (<?php echo htmlspecialchars($team['sport_name']); ?>)</p>
            <p><strong>Owner:</strong> <?php echo htmlspecialchars($team['owner_username']); ?></p>
            <p><strong>Record:</strong> <?php echo $team['wins']; ?>W - <?php echo $team['draws']; ?>D - <?php echo $team['losses']; ?>L (<?php echo $team['points']; ?> points)</p>
            <?php if ($team['description']): ?>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($team['description']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="showTab('roster')">Roster</div>
            <div class="tab" onclick="showTab('requests')">Requests (<?php echo count($requests); ?>)</div>
            <div class="tab" onclick="showTab('schedule')">Schedule</div>
            <div class="tab" onclick="showTab('stats')">Statistics</div>
        </div>
        
        <div id="roster" class="tab-content active">
            <h3>Team Roster</h3>
            <table>
                <thead>
                    <tr>
                        <th>Player Name</th>
                        <th>Username</th>
                        <th>Position</th>
                        <th>Jersey #</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['username']); ?></td>
                        <td><?php echo htmlspecialchars($member['position'] ?? 'N/A'); ?></td>
                        <td><?php echo $member['jersey_number'] ?? 'N/A'; ?></td>
                        <td><?php echo date('M j, Y', strtotime($member['joined_at'])); ?></td>
                        <td>
                            <?php if ($member['player_id'] != $team['owner_id']): ?>
                                <button onclick="removeMember(<?php echo $member['id']; ?>)" class="btn btn-danger">Remove</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="requests" class="tab-content">
            <h3>Registration Requests</h3>
            <?php if (count($requests) > 0): ?>
                <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <h4><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?> (@<?php echo htmlspecialchars($request['username']); ?>)</h4>
                        <p><strong>Preferred Position:</strong> <?php echo htmlspecialchars($request['preferred_position'] ?? 'Not specified'); ?></p>
                        <?php if ($request['message']): ?>
                            <p><strong>Message:</strong> <?php echo htmlspecialchars($request['message']); ?></p>
                        <?php endif; ?>
                        <p><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></p>
                        <button onclick="processRequest(<?php echo $request['id']; ?>, 'approve')" class="btn btn-success">Approve</button>
                        <button onclick="processRequest(<?php echo $request['id']; ?>, 'reject')" class="btn btn-danger">Reject</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No pending registration requests.</p>
            <?php endif; ?>
        </div>
        
        <div id="schedule" class="tab-content">
            <h3>Team Schedule</h3>
            <?php
            // Get team matches
            $matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name
                              FROM matches m
                              JOIN teams ht ON m.home_team_id = ht.id
                              JOIN teams at ON m.away_team_id = at.id
                              LEFT JOIN venues v ON m.venue_id = v.id
                              WHERE (m.home_team_id = :team_id OR m.away_team_id = :team_id)
                              ORDER BY m.match_date ASC";
            $matches_stmt = $db->prepare($matches_query);
            $matches_stmt->bindParam(':team_id', $team_id);
            $matches_stmt->execute();
            $matches = $matches_stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Opponent</th>
                        <th>Home/Away</th>
                        <th>Venue</th>
                        <th>Result</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($match['match_date'])); ?></td>
                        <td>
                            <?php 
                            $opponent = ($match['home_team_id'] == $team_id) ? $match['away_team'] : $match['home_team'];
                            echo htmlspecialchars($opponent);
                            ?>
                        </td>
                        <td><?php echo ($match['home_team_id'] == $team_id) ? 'Home' : 'Away'; ?></td>
                        <td><?php echo htmlspecialchars($match['venue_name'] ?? 'TBD'); ?></td>
                        <td>
                            <?php if ($match['status'] == 'completed'): ?>
                                <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo ucfirst($match['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="stats" class="tab-content">
            <h3>Team Statistics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; text-align: center;">
                    <h4>Matches Played</h4>
                    <h2><?php echo $team['wins'] + $team['draws'] + $team['losses']; ?></h2>
                </div>
                <div style="background: #d4edda; padding: 20px; border-radius: 5px; text-align: center;">
                    <h4>Wins</h4>
                    <h2><?php echo $team['wins']; ?></h2>
                </div>
                <div style="background: #fff3cd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h4>Draws</h4>
                    <h2><?php echo $team['draws']; ?></h2>
                </div>
                <div style="background: #f8d7da; padding: 20px; border-radius: 5px; text-align: center;">
                    <h4>Losses</h4>
                    <h2><?php echo $team['losses']; ?></h2>
                </div>
                <div style="background: #cce7ff; padding: 20px; border-radius: 5px; text-align: center;">
                    <h4>Points</h4>
                    <h2><?php echo $team['points']; ?></h2>
                </div>
            </div>
        </div>
        
        <p><a href="dashboard.php">← Back to Dashboard</a></p>
    </div>
</body>
</html>

<?php
}
?>