<?php
// team/view_team.php - View Team Details
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();

// Get team ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    showMessage("Team ID is required!", "error");
    redirect('../dashboard.php');
}

$team_id = $_GET['id'];

// Get team details with league and owner information
$team_query = "SELECT t.*, l.name as league_name, l.season, l.status as league_status, 
                      s.name as sport_name, u.username as owner_username, u.first_name as owner_first, 
                      u.last_name as owner_last, u.email as owner_email,
                      (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count
               FROM teams t
               JOIN leagues l ON t.league_id = l.id
               JOIN sports s ON l.sport_id = s.id
               JOIN users u ON t.owner_id = u.id
               WHERE t.id = :team_id";
$team_stmt = $db->prepare($team_query);
$team_stmt->bindParam(':team_id', $team_id);
$team_stmt->execute();
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    showMessage("Team not found!", "error");
    redirect('../dashboard.php');
}

// Check if user is team owner or member
$is_owner = ($team['owner_id'] == $user['id']);
$is_admin = ($user['role'] == 'admin');

// Check if user is a team member
$member_query = "SELECT * FROM team_members WHERE team_id = :team_id AND player_id = :player_id AND status = 'active'";
$member_stmt = $db->prepare($member_query);
$member_stmt->bindParam(':team_id', $team_id);
$member_stmt->bindParam(':player_id', $user['id']);
$member_stmt->execute();
$is_member = ($member_stmt->rowCount() > 0);

// Get team members (roster)
$members_query = "SELECT tm.*, u.username, u.first_name, u.last_name, u.email
                  FROM team_members tm
                  JOIN users u ON tm.player_id = u.id
                  WHERE tm.team_id = :team_id AND tm.status = 'active'
                  ORDER BY tm.joined_at ASC";
$members_stmt = $db->prepare($members_query);
$members_stmt->bindParam(':team_id', $team_id);
$members_stmt->execute();
$members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming matches
$upcoming_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name, v.address as venue_address
                   FROM matches m
                   JOIN teams ht ON m.home_team_id = ht.id
                   JOIN teams at ON m.away_team_id = at.id
                   LEFT JOIN venues v ON m.venue_id = v.id
                   WHERE (m.home_team_id = :team_id OR m.away_team_id = :team_id2)
                   AND m.status = 'scheduled'
                   AND m.match_date >= NOW()
                   ORDER BY m.match_date ASC
                   LIMIT 5";
$upcoming_stmt = $db->prepare($upcoming_query);
$upcoming_stmt->bindParam(':team_id', $team_id);
$upcoming_stmt->bindParam(':team_id2', $team_id);
$upcoming_stmt->execute();
$upcoming_matches = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent match results
$recent_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name
                 FROM matches m
                 JOIN teams ht ON m.home_team_id = ht.id
                 JOIN teams at ON m.away_team_id = at.id
                 LEFT JOIN venues v ON m.venue_id = v.id
                 WHERE (m.home_team_id = :team_id OR m.away_team_id = :team_id2)
                 AND m.status = 'completed'
                 ORDER BY m.match_date DESC
                 LIMIT 5";
$recent_stmt = $db->prepare($recent_query);
$recent_stmt->bindParam(':team_id', $team_id);
$recent_stmt->bindParam(':team_id2', $team_id);
$recent_stmt->execute();
$recent_matches = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get league standings
$standings_query = "SELECT t.id, t.name, t.wins, t.draws, t.losses, t.points, t.goals_for, t.goals_against,
                           (t.goals_for - t.goals_against) as goal_difference
                    FROM teams t
                    WHERE t.league_id = :league_id
                    ORDER BY t.points DESC, goal_difference DESC, t.goals_for DESC";
$standings_stmt = $db->prepare($standings_query);
$standings_stmt->bindParam(':league_id', $team['league_id']);
$standings_stmt->execute();
$standings = $standings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Find team's position
$team_position = 0;
foreach ($standings as $index => $standing) {
    if ($standing['id'] == $team_id) {
        $team_position = $index + 1;
        break;
    }
}

// Calculate win percentage
$total_matches = $team['wins'] + $team['draws'] + $team['losses'];
$win_percentage = $total_matches > 0 ? round(($team['wins'] / $total_matches) * 100, 1) : 0;

// Check for pending join requests (if owner)
$pending_requests = 0;
if ($is_owner || $is_admin) {
    $requests_query = "SELECT COUNT(*) FROM registration_requests 
                       WHERE team_id = :team_id AND status = 'pending'";
    $requests_stmt = $db->prepare($requests_query);
    $requests_stmt->bindParam(':team_id', $team_id);
    $requests_stmt->execute();
    $pending_requests = $requests_stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($team['name']); ?> - Team Details</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: #343a40; color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .nav { display: flex; gap: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 4px; }
        .nav a:hover { background: #495057; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        
        .team-header { background: white; border-radius: 8px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .team-header-content { display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 2rem; }
        .team-info h1 { color: #007bff; margin-bottom: 0.5rem; }
        .team-meta { color: #666; margin-bottom: 1rem; }
        .team-meta p { margin: 0.3rem 0; }
        .team-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-start; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 0.5rem; font-size: 0.9rem; }
        
        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .card { background: white; border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card h3 { margin-bottom: 1rem; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem; }
        
        .member-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8f9fa; border-radius: 4px; margin-bottom: 0.5rem; }
        .member-info { flex: 1; }
        .member-name { font-weight: bold; color: #333; }
        .member-details { color: #666; font-size: 0.9rem; margin-top: 0.3rem; }
        
        .match-item { background: #f8f9fa; padding: 1rem; border-radius: 4px; margin-bottom: 0.5rem; }
        .match-date { font-weight: bold; color: #007bff; margin-bottom: 0.5rem; }
        .match-teams { font-size: 1.1rem; margin-bottom: 0.3rem; }
        .match-venue { color: #666; font-size: 0.9rem; }
        .match-result { font-size: 1.2rem; font-weight: bold; text-align: center; padding: 0.5rem; border-radius: 4px; margin-bottom: 0.5rem; }
        .win { background: #d4edda; color: #155724; }
        .loss { background: #f8d7da; color: #721c24; }
        .draw { background: #fff3cd; color: #856404; }
        
        .btn { background: #007bff; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 4px; display: inline-block; border: none; cursor: pointer; font-size: 0.9rem; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        
        .empty-state { text-align: center; padding: 2rem; color: #666; }
        .empty-state-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
        
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; font-weight: bold; margin-left: 0.5rem; }
        .badge-success { background: #28a745; color: white; }
        .badge-warning { background: #ffc107; color: black; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        
        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
            .team-header-content { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Sports League Management</h1>
        <div class="nav">
            <a href="../dashboard.php">Dashboard</a>
            <?php if ($user['role'] == 'admin'): ?>
                <a href="../league/manage_leagues.php">Manage Leagues</a>
                <a href="../admin/manage_users.php">Manage Users</a>
            <?php else: ?>
                <a href="my_teams.php">My Teams</a>
                <a href="../league/browse_leagues.php">Browse Leagues</a>
            <?php endif; ?>
            <a href="../profile.php">Profile</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php displayMessage(); ?>
        
        <!-- Team Header -->
        <div class="team-header">
            <div class="team-header-content">
                <div class="team-info">
                    <h1><?php echo htmlspecialchars($team['name']); ?></h1>
                    <div class="team-meta">
                        <p><strong>League:</strong> <?php echo htmlspecialchars($team['league_name']); ?> (<?php echo htmlspecialchars($team['sport_name']); ?>)</p>
                        <p><strong>Season:</strong> <?php echo htmlspecialchars($team['season']); ?></p>
                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($team['owner_first'] . ' ' . $team['owner_last']); ?> (@<?php echo htmlspecialchars($team['owner_username']); ?>)</p>
                        <p><strong>League Standing:</strong> <?php echo $team_position > 0 ? "#" . $team_position : "N/A"; ?> of <?php echo count($standings); ?> teams</p>
                        <p><strong>Team Members:</strong> <?php echo $team['member_count']; ?> players</p>
                    </div>
                    <?php if ($team['description']): ?>
                        <p style="margin-top: 1rem; color: #666;"><?php echo nl2br(htmlspecialchars($team['description'])); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="team-actions">
                    <?php if ($is_owner || $is_admin): ?>
                        <a href="manage_team.php?id=<?php echo $team_id; ?>" class="btn btn-success">Manage Team</a>
                        <?php if ($pending_requests > 0): ?>
                            <a href="manage_team.php?id=<?php echo $team_id; ?>#requests" class="btn btn-warning">
                                Join Requests (<?php echo $pending_requests; ?>)
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!$is_member && !$is_owner && $team['league_status'] != 'completed'): ?>
                        <a href="join_team.php?team_id=<?php echo $team_id; ?>" class="btn btn-success">Request to Join</a>
                    <?php endif; ?>
                    
                    <a href="../league/view_league.php?id=<?php echo $team['league_id']; ?>" class="btn btn-secondary">View League</a>
                </div>
            </div>
        </div>
        
        <!-- Team Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $team['wins']; ?>-<?php echo $team['draws']; ?>-<?php echo $team['losses']; ?></div>
                <div class="stat-label">W-D-L Record</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $team['points']; ?></div>
                <div class="stat-label">Points</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $team['goals_for']; ?></div>
                <div class="stat-label">Goals For</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $team['goals_against']; ?></div>
                <div class="stat-label">Goals Against</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo ($team['goals_for'] - $team['goals_against']); ?></div>
                <div class="stat-label">Goal Difference</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $win_percentage; ?>%</div>
                <div class="stat-label">Win Rate</div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Team Roster -->
                <div class="card">
                    <h3>Team Roster (<?php echo count($members); ?> Players)</h3>
                    <?php if (count($members) > 0): ?>
                        <?php foreach ($members as $member): ?>
                            <div class="member-item">
                                <div class="member-info">
                                    <div class="member-name">
                                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                                        <?php if ($member['player_id'] == $team['owner_id']): ?>
                                            <span class="badge badge-warning">Owner</span>
                                        <?php endif; ?>
                                        <?php if ($member['jersey_number']): ?>
                                            <span class="badge badge-info">#<?php echo $member['jersey_number']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="member-details">
                                        @<?php echo htmlspecialchars($member['username']); ?>
                                        <?php if ($member['position']): ?>
                                            | <?php echo htmlspecialchars($member['position']); ?>
                                        <?php endif; ?>
                                        | Joined: <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">👥</div>
                            <p>No players on the roster yet.</p>
                            <?php if ($is_owner || $is_admin): ?>
                                <a href="manage_team.php?id=<?php echo $team_id; ?>" class="btn btn-success" style="margin-top: 1rem;">Add Players</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Results -->
                <div class="card">
                    <h3>Recent Results</h3>
                    <?php if (count($recent_matches) > 0): ?>
                        <?php foreach ($recent_matches as $match): 
                            $is_home = ($match['home_team_id'] == $team_id);
                            $team_score = $is_home ? $match['home_score'] : $match['away_score'];
                            $opponent_score = $is_home ? $match['away_score'] : $match['home_score'];
                            
                            if ($team_score > $opponent_score) {
                                $result_class = 'win';
                                $result_text = 'WIN';
                            } elseif ($team_score < $opponent_score) {
                                $result_class = 'loss';
                                $result_text = 'LOSS';
                            } else {
                                $result_class = 'draw';
                                $result_text = 'DRAW';
                            }
                        ?>
                            <div class="match-item">
                                <div class="match-result <?php echo $result_class; ?>"><?php echo $result_text; ?></div>
                                <div class="match-date"><?php echo date('M j, Y', strtotime($match['match_date'])); ?></div>
                                <div class="match-teams">
                                    <?php echo htmlspecialchars($match['home_team']); ?> 
                                    <strong><?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?></strong> 
                                    <?php echo htmlspecialchars($match['away_team']); ?>
                                </div>
                                <?php if ($match['venue_name']): ?>
                                    <div class="match-venue">@ <?php echo htmlspecialchars($match['venue_name']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No match results yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column -->
            <div>
                <!-- Upcoming Matches -->
                <div class="card">
                    <h3>Upcoming Matches</h3>
                    <?php if (count($upcoming_matches) > 0): ?>
                        <?php foreach ($upcoming_matches as $match): ?>
                            <div class="match-item">
                                <div class="match-date"><?php echo date('M j, Y - g:i A', strtotime($match['match_date'])); ?></div>
                                <div class="match-teams">
                                    <?php echo htmlspecialchars($match['home_team']); ?> vs <?php echo htmlspecialchars($match['away_team']); ?>
                                </div>
                                <?php if ($match['venue_name']): ?>
                                    <div class="match-venue">
                                        @ <?php echo htmlspecialchars($match['venue_name']); ?>
                                        <?php if ($match['venue_address']): ?>
                                            <br><small><?php echo htmlspecialchars($match['venue_address']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No upcoming matches scheduled.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <?php if ($is_owner || $is_admin || $is_member): ?>
                <div class="card">
                    <h3>Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php if ($is_owner || $is_admin): ?>
                            <a href="manage_team.php?id=<?php echo $team_id; ?>" class="btn">Manage Roster</a>
                            <?php if ($pending_requests > 0): ?>
                                <a href="manage_team.php?id=<?php echo $team_id; ?>#requests" class="btn btn-warning">
                                    View Join Requests (<?php echo $pending_requests; ?>)
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="../league/view_league.php?id=<?php echo $team['league_id']; ?>" class="btn btn-secondary">View League Standings</a>
                        <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <p><a href="../dashboard.php">← Back to Dashboard</a></p>
    </div>
</body>
</html>