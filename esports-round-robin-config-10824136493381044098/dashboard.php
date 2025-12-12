<?php
// dashboard.php - Main Dashboard
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();

// Get user-specific data based on role
if ($user['role'] == 'admin') {
    // Admin dashboard data
    $stats_query = "SELECT 
                        (SELECT COUNT(*) FROM users) as total_users,
                        (SELECT COUNT(*) FROM leagues) as total_leagues,
                        (SELECT COUNT(*) FROM teams) as total_teams,
                        (SELECT COUNT(*) FROM matches WHERE status = 'scheduled') as upcoming_matches";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activity
    $activity_query = "SELECT 'user' as type, CONCAT(first_name, ' ', last_name, ' registered') as activity, created_at 
                       FROM users 
                       UNION ALL
                       SELECT 'team' as type, CONCAT(name, ' team created') as activity, created_at 
                       FROM teams
                       UNION ALL
                       SELECT 'league' as type, CONCAT(name, ' league created') as activity, created_at 
                       FROM leagues
                       ORDER BY created_at DESC LIMIT 10";
    $activity_stmt = $db->prepare($activity_query);
    $activity_stmt->execute();
    $activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Player/Team Owner dashboard
    
    // Get user's teams
    $teams_query = "SELECT t.*, l.name as league_name, l.season, 
                           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
                    FROM teams t 
                    JOIN leagues l ON t.league_id = l.id 
                    WHERE t.owner_id = :user_id OR t.id IN (
                        SELECT team_id FROM team_members WHERE player_id = :user_id AND status = 'active'
                    )";
    $teams_stmt = $db->prepare($teams_query);
    $teams_stmt->bindParam(':user_id', $user['id']);
    $teams_stmt->execute();
    $user_teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming matches for user's teams
    $team_ids = array_column($user_teams, 'id');
    if (!empty($team_ids)) {
        $team_ids_str = implode(',', $team_ids);
        $matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name, l.name as league_name
                          FROM matches m
                          JOIN teams ht ON m.home_team_id = ht.id
                          JOIN teams at ON m.away_team_id = at.id
                          LEFT JOIN venues v ON m.venue_id = v.id
                          JOIN leagues l ON m.league_id = l.id
                          WHERE (m.home_team_id IN ($team_ids_str) OR m.away_team_id IN ($team_ids_str))
                          AND m.status = 'scheduled'
                          AND m.match_date >= NOW()
                          ORDER BY m.match_date ASC
                          LIMIT 5";
        $matches_stmt = $db->prepare($matches_query);
        $matches_stmt->execute();
        $upcoming_matches = $matches_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $upcoming_matches = [];
    }
    
    // Get recent match results
    if (!empty($team_ids)) {
        $recent_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name, l.name as league_name
                         FROM matches m
                         JOIN teams ht ON m.home_team_id = ht.id
                         JOIN teams at ON m.away_team_id = at.id
                         LEFT JOIN venues v ON m.venue_id = v.id
                         JOIN leagues l ON m.league_id = l.id
                         WHERE (m.home_team_id IN ($team_ids_str) OR m.away_team_id IN ($team_ids_str))
                         AND m.status = 'completed'
                         ORDER BY m.match_date DESC
                         LIMIT 5";
        $recent_stmt = $db->prepare($recent_query);
        $recent_stmt->execute();
        $recent_matches = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $recent_matches = [];
    }
    
    // Get available leagues for joining
    $available_leagues_query = "SELECT l.*, s.name as sport_name,
                                       (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as team_count
                                FROM leagues l 
                                JOIN sports s ON l.sport_id = s.id 
                                WHERE l.status IN ('open', 'draft') 
                                AND l.registration_deadline >= CURDATE()
                                ORDER BY l.registration_deadline ASC";
    $available_stmt = $db->prepare($available_leagues_query);
    $available_stmt->execute();
    $available_leagues = $available_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard - Sports League</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-image: url('bgsport.png'); 
             background-size: cover; 
             background-repeat: no-repeat;
  background-position: center;
  background-attachment: fixed;
           
             }
        .header { background: linear-gradient(black, maroon); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav { display: flex; gap: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; }
        .nav a:hover { background: maroon;  transition: background-color 0.7s ease;  }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .card { background-image: url('woods.jpg'); border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h3 { margin-bottom: 1rem; color: #333; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 0.5rem; }
        .btn { background: green; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; }
        .match-item { background: #f8f9fa; padding: 1rem; border-radius: 4px; margin-bottom: 0.5rem; }
        .match-date { font-weight: bold; color: #007bff; }
        .team-item { background: linear-gradient(to right, whitesmoke, whitesmoke); padding: 1rem; border-radius: 4px; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .activity-item { padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .activity-item:last-child { border-bottom: none; }
        .welcome { background: linear-gradient(to right, blue , white); color: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sports League System - Labangon</h1>
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <?php if ($user['role'] == 'admin'): ?>
                <a href="league/manage_leagues.php">Manage Leagues</a>
                <a href="admin/manage_users.php">Manage Users</a>
                <a href="admin/system_reports.php">Reports</a>
            <?php else: ?>
                <a href="team/my_teams.php">My Teams</a>
                <a href="league/browse_leagues.php">Browse Leagues</a>
                <a href="team/create_team.php">Create Team</a>
            <?php endif; ?>
            <a href="profile.php">Profile</a>
            <a href="auth/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php displayMessage(); ?>
        
        <div class="welcome">
            <h2>👥 Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>!</h2>
            <p>Role: <?php echo ucfirst($user['role']); ?> | Last login: <?php echo date('M j, Y g:i A'); ?></p>
        </div>
        
        <?php if ($user['role'] == 'admin'): ?>
        <!-- Admin Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_leagues']; ?></div>
                <div class="stat-label">Active Leagues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_teams']; ?></div>
                <div class="stat-label">Registered Teams</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['upcoming_matches']; ?></div>
                <div class="stat-label">Scheduled Matches</div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>Quick Actions</h3>
                <a href="league/create_league.php" class="btn btn-success">Create New League</a>
                <a href="admin/manage_users.php" class="btn">Manage Users</a>
                <a href="venue/manage_venues.php" class="btn">Manage Venues</a>
                <a href="admin/system_reports.php" class="btn btn-warning">View Reports</a>
            </div>
            
            <div class="card">
                <h3>Recent System Activity</h3>
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div><?php echo htmlspecialchars($activity['activity']); ?></div>
                        <small style="color: #666;"><?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Player/Team Owner Dashboard -->
        
        <?php if (count($user_teams) == 0): ?>
        <div class="alert alert-info">
            <strong>Get Started!</strong> You're not part of any teams yet. 
            <a href="team/create_team.php">Create a team</a> or 
            <a href="team/join_team.php">request to join an existing team</a>.
        </div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <div class="card">
                <h3>My Teams (<?php echo count($user_teams); ?>)</h3>
                <?php if (count($user_teams) > 0): ?>
                    <?php foreach ($user_teams as $team): ?>
                        <div class="team-item">
                            <div>
                                <strong><?php echo htmlspecialchars($team['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($team['league_name']); ?> - <?php echo htmlspecialchars($team['season']); ?></small><br>
                                <small>Record: <?php echo $team['wins']; ?>W-<?php echo $team['draws']; ?>D-<?php echo $team['losses']; ?>L (<?php echo $team['points']; ?> pts)</small>
                            </div>
                            <div>
                                <a href="team/manage_team.php?id=<?php echo $team['id']; ?>" class="btn">Manage</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No teams yet.</p>
                    <a href="team/create_team.php" class="btn btn-success">Create Your First Team</a>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Available Leagues</h3>
                <?php if (count($available_leagues) > 0): ?>
                    <?php foreach ($available_leagues as $league): ?>
                        <div class="team-item">
                            <div>
                                <strong><?php echo htmlspecialchars($league['name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($league['sport_name']); ?> - <?php echo htmlspecialchars($league['season']); ?></small><br>
                                <small>Teams: <?php echo $league['team_count']; ?>/<?php echo $league['max_teams']; ?> | Deadline: <?php echo date('M j', strtotime($league['registration_deadline'])); ?></small>
                            </div>
                            <div>
                                <a href="league/view_league.php?id=<?php echo $league['id']; ?>" class="btn">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No leagues currently accepting registrations.</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>Upcoming Matches</h3>
                <?php if (count($upcoming_matches) > 0): ?>
                    <?php foreach ($upcoming_matches as $match): ?>
                        <div class="match-item">
                            <div class="match-date"><?php echo date('M j, Y g:i A', strtotime($match['match_date'])); ?></div>
                            <div><strong><?php echo htmlspecialchars($match['home_team']); ?></strong> vs <strong><?php echo htmlspecialchars($match['away_team']); ?></strong></div>
                            <div><small><?php echo htmlspecialchars($match['venue_name'] ?? 'Venue TBD'); ?> - <?php echo htmlspecialchars($match['league_name']); ?></small></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No upcoming matches.</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>Recent Results</h3>
                <?php if (count($recent_matches) > 0): ?>
                    <?php foreach ($recent_matches as $match): ?>
                        <div class="match-item">
                            <div class="match-date"><?php echo date('M j, Y', strtotime($match['match_date'])); ?></div>
                            <div><strong><?php echo htmlspecialchars($match['home_team']); ?></strong> <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?> <strong><?php echo htmlspecialchars($match['away_team']); ?></strong></div>
                            <div><small><?php echo htmlspecialchars($match['league_name']); ?></small></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent matches.</p>
                <?php endif; ?>
            </div>
            
            
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// team/join_team.php - Request to Join Team
if (!isset($user_teams)) {
    require_once 'config/database.php';
    requireLogin();
    
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    // Handle join request submission
    if ($_POST) {
        $team_id = $_POST['team_id'];
        $preferred_position = trim($_POST['preferred_position']);
        $message = trim($_POST['message']);
        
        // Check if user already has a pending request for this team
        $check_query = "SELECT id FROM registration_requests 
                        WHERE player_id = :player_id AND team_id = :team_id AND status = 'pending'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':player_id', $user['id']);
        $check_stmt->bindParam(':team_id', $team_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "You already have a pending request for this team!";
        } else {
            // Get league_id for the team
            $team_query = "SELECT league_id FROM teams WHERE id = :team_id";
            $team_stmt = $db->prepare($team_query);
            $team_stmt->bindParam(':team_id', $team_id);
            $team_stmt->execute();
            $team = $team_stmt->fetch(PDO::FETCH_ASSOC);
            
            $insert_query = "INSERT INTO registration_requests (player_id, team_id, league_id, preferred_position, message) 
                            VALUES (:player_id, :team_id, :league_id, :preferred_position, :message)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':player_id', $user['id']);
            $insert_stmt->bindParam(':team_id', $team_id);
            $insert_stmt->bindParam(':league_id', $team['league_id']);
            $insert_stmt->bindParam(':preferred_position', $preferred_position);
            $insert_stmt->bindParam(':message', $message);
            
            if ($insert_stmt->execute()) {
                showMessage("Join request sent successfully!", "success");
                redirect('dashboard.php');
            } else {
                $error = "Failed to send join request!";
            }
        }
    }
    
    // Get available teams to join
    $teams_query = "SELECT t.*, l.name as league_name, l.season, s.name as sport_name, u.username as owner_username,
                           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
                    FROM teams t
                    JOIN leagues l ON t.league_id = l.id
                    JOIN sports s ON l.sport_id = s.id
                    JOIN users u ON t.owner_id = u.id
                    WHERE l.status IN ('open', 'active')
                    AND t.id NOT IN (
                        SELECT team_id FROM team_members WHERE player_id = :user_id AND status = 'active'
                    )
                    ORDER BY l.registration_deadline DESC, t.name";
    $teams_stmt = $db->prepare($teams_query);
    $teams_stmt->bindParam(':user_id', $user['id']);
    $teams_stmt->execute();
    $available_teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Join Team - Sports League</title>
    <style>
        .container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .team-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .team-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .team-info { color: #666; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea, select { width: 100%; padding: 8px; }
        .btn { background: #007bff; color: white; padding: 8px 16px; border: none; cursor: pointer; text-decoration: none; border-radius: 4px; }
        .btn-success { background: #28a745; }
        .error { color: red; margin-bottom: 15px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 500px; }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    </style>
    <script>
        function showJoinModal(teamId, teamName) {
            document.getElementById('join_team_id').value = teamId;
            document.getElementById('team_name_display').textContent = teamName;
            document.getElementById('joinModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('joinModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('joinModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Join a Team</h2>
        
        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        
        <?php if (count($available_teams) > 0): ?>
            <?php foreach ($available_teams as $team): ?>
                <div class="team-card">
                    <div class="team-header">
                        <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                        <button onclick="showJoinModal(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name']); ?>')" class="btn btn-success">Request to Join</button>
                    </div>
                    <div class="team-info">
                        <p><strong>League:</strong> <?php echo htmlspecialchars($team['league_name']); ?> (<?php echo htmlspecialchars($team['sport_name']); ?>)</p>
                        <p><strong>Season:</strong> <?php echo htmlspecialchars($team['season']); ?></p>
                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($team['owner_username']); ?></p>
                        <p><strong>Members:</strong> <?php echo $team['member_count']; ?></p>
                        <p><strong>Record:</strong> <?php echo $team['wins']; ?>W-<?php echo $team['draws']; ?>D-<?php echo $team['losses']; ?>L</p>
                        <?php if ($team['description']): ?>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($team['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <p>No teams are currently accepting new members.</p>
                <p><a href="team/create_team.php" class="btn">Create Your Own Team</a></p>
            </div>
        <?php endif; ?>
        
        <p><a href="dashboard.php">← Back to Dashboard</a></p>
    </div>
    
    <!-- Join Team Modal -->
    <div id="joinModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Request to Join <span id="team_name_display"></span></h3>
            
            <form method="POST">
                <input type="hidden" id="join_team_id" name="team_id">
                
                <div class="form-group">
                    <label>Preferred Position:</label>
                    <input type="text" name="preferred_position" placeholder="e.g., Forward, Defender, Midfielder">
                </div>
                
                <div class="form-group">
                    <label>Message to Team Owner:</label>
                    <textarea name="message" rows="4" placeholder="Tell them why you'd like to join their team..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">Send Request</button>
                <button type="button" onclick="closeModal()" class="btn" style="background: #6c757d;">Cancel</button>
            </form>
        </div>
    </div>
</body>
</html>

<?php
}
?>

<?php
// team/process_request.php - Process Team Join Requests
if (isset($_POST['request_id'])) {
    require_once 'database.php';
    requireLogin();
    
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    
    // Get request details and verify ownership
    $request_query = "SELECT rr.*, t.owner_id, t.name as team_name, u.first_name, u.last_name
                      FROM registration_requests rr
                      JOIN teams t ON rr.team_id = t.id
                      JOIN users u ON rr.player_id = u.id
                      WHERE rr.id = :id AND rr.status = 'pending'";
    $request_stmt = $db->prepare($request_query);
    $request_stmt->bindParam(':id', $request_id);
    $request_stmt->execute();
    $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        showMessage("Request not found!", "error");
        redirect('../dashboard.php');
    }
    
    // Check if user is team owner or admin
    if ($request['owner_id'] != $user['id'] && $user['role'] != 'admin') {
        showMessage("Access denied!", "error");
        redirect('../dashboard.php');
    }
    
    if ($action == 'approve') {
        // Add player to team
        $add_member_query = "INSERT INTO team_members (team_id, player_id, position, joined_at) 
                            VALUES (:team_id, :player_id, :position, NOW())";
        $add_member_stmt = $db->prepare($add_member_query);
        $add_member_stmt->bindParam(':team_id', $request['team_id']);
        $add_member_stmt->bindParam(':player_id', $request['player_id']);
        $add_member_stmt->bindParam(':position', $request['preferred_position']);
        
        if ($add_member_stmt->execute()) {
            // Update request status
            $update_query = "UPDATE registration_requests 
                            SET status = 'approved', processed_at = NOW(), processed_by = :processed_by 
                            WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':processed_by', $user['id']);
            $update_stmt->bindParam(':id', $request_id);
            $update_stmt->execute();
            
            // Create notification for player
            $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                                  VALUES (:user_id, :title, :message, 'success')";
            $notification_stmt = $db->prepare($notification_query);
            $notification_stmt->bindParam(':user_id', $request['player_id']);
            $title = "Request Approved";
            $message = "Your request to join " . $request['team_name'] . " has been approved!";
            $notification_stmt->bindParam(':title', $title);
            $notification_stmt->bindParam(':message', $message);
            $notification_stmt->execute();
            
            showMessage("Player " . $request['first_name'] . " " . $request['last_name'] . " has been added to the team!", "success");
        } else {
            showMessage("Failed to add player to team!", "error");
        }
    } else { // reject
        // Update request status
        $update_query = "UPDATE registration_requests 
                        SET status = 'rejected', processed_at = NOW(), processed_by = :processed_by 
                        WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':processed_by', $user['id']);
        $update_stmt->bindParam(':id', $request_id);
        $update_stmt->execute();
        
        // Create notification for player
        $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                              VALUES (:user_id, :title, :message, 'info')";
        $notification_stmt = $db->prepare($notification_query);
        $notification_stmt->bindParam(':user_id', $request['player_id']);
        $title = "Request Declined";
        $message = "Your request to join " . $request['team_name'] . " has been declined.";
        $notification_stmt->bindParam(':title', $title);
        $notification_stmt->bindParam(':message', $message);
        $notification_stmt->execute();
    }
}