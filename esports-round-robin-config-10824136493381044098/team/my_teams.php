<?php
// team/my_teams.php - User's Team Management Dashboard
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Handle team actions
if (isset($_POST['action'])) {
    $team_id = $_POST['team_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'leave_team':
            // Remove user from team members
            $leave_query = "DELETE FROM team_members WHERE team_id = :team_id AND player_id = :player_id";
            $leave_stmt = $db->prepare($leave_query);
            $leave_stmt->bindParam(':team_id', $team_id);
            $leave_stmt->bindParam(':player_id', $current_user['id']);
            
            if ($leave_stmt->execute()) {
                showMessage("You have left the team successfully!", "success");
            } else {
                showMessage("Failed to leave team!", "error");
            }
            break;
            
      case 'delete_team':
    // Check if user is owner
    $owner_check = "SELECT owner_id FROM teams WHERE id = :team_id";
    $owner_stmt = $db->prepare($owner_check);
    $owner_stmt->bindParam(':team_id', $team_id);
    $owner_stmt->execute();
    $team_owner = $owner_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($team_owner && $team_owner['owner_id'] == $current_user['id']) {
        // Check if team has matches
        $matches_check = "SELECT COUNT(*) FROM matches WHERE home_team_id = :team_id OR away_team_id = :team_id";
        $matches_stmt = $db->prepare($matches_check);
        $matches_stmt->bindParam(':team_id', $team_id);
        $matches_stmt->execute();
        $match_count = $matches_stmt->fetchColumn();
        
        if ($match_count > 0) {
            showMessage("Cannot delete team - it has scheduled or completed matches!", "error");
        } else {
            try {
                $db->beginTransaction();
                
                // Get league_id first
                $get_league = "SELECT league_id FROM teams WHERE id = :team_id";
                $get_league_stmt = $db->prepare($get_league);
                $get_league_stmt->bindParam(':team_id', $team_id);
                $get_league_stmt->execute();
                $team_data = $get_league_stmt->fetch(PDO::FETCH_ASSOC);
                $league_id = $team_data['league_id'];
                
                // Delete player stats
                $delete_stats = "DELETE FROM player_stats WHERE team_id = :team_id";
                $delete_stats_stmt = $db->prepare($delete_stats);
                $delete_stats_stmt->bindParam(':team_id', $team_id);
                $delete_stats_stmt->execute();
                
                // Delete registration requests (players requesting to join this team)
                $delete_requests = "DELETE FROM registration_requests WHERE team_id = :team_id";
                $delete_requests_stmt = $db->prepare($delete_requests);
                $delete_requests_stmt->bindParam(':team_id', $team_id);
                $delete_requests_stmt->execute();
                
                // Delete team registration requests (this team's registration to league)
                $delete_team_requests = "DELETE FROM team_registration_requests 
                                        WHERE league_id = :league_id AND team_owner_id = :owner_id";
                $delete_team_requests_stmt = $db->prepare($delete_team_requests);
                $delete_team_requests_stmt->bindParam(':league_id', $league_id);
                $delete_team_requests_stmt->bindParam(':owner_id', $current_user['id']);
                $delete_team_requests_stmt->execute();
                
                // Delete team members
                $delete_members = "DELETE FROM team_members WHERE team_id = :team_id";
                $delete_members_stmt = $db->prepare($delete_members);
                $delete_members_stmt->bindParam(':team_id', $team_id);
                $delete_members_stmt->execute();
                
                // Delete the team
                $delete_team = "DELETE FROM teams WHERE id = :team_id";
                $delete_team_stmt = $db->prepare($delete_team);
                $delete_team_stmt->bindParam(':team_id', $team_id);
                $delete_team_stmt->execute();
                
                $db->commit();
                
                showMessage("Team deleted successfully!", "success");
            } catch (Exception $e) {
                $db->rollBack();
                showMessage("Failed to delete team: " . $e->getMessage(), "error");
            }
        }
    } else {
        showMessage("Only team owners can delete teams!", "error");
    }
    break;
    }
}

// Get user's owned teams
$owned_teams_query = "SELECT t.*, l.name as league_name, l.season, l.status as league_status, s.name as sport_name,
                             (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count,
                             (SELECT COUNT(*) FROM matches WHERE (home_team_id = t.id OR away_team_id = t.id) AND status = 'scheduled' AND match_date >= NOW()) as upcoming_matches,
                             (SELECT COUNT(*) FROM matches WHERE (home_team_id = t.id OR away_team_id = t.id) AND status = 'completed') as completed_matches
                      FROM teams t 
                      JOIN leagues l ON t.league_id = l.id 
                      JOIN sports s ON l.sport_id = s.id
                      WHERE t.owner_id = :user_id
                      ORDER BY l.status DESC, t.created_at DESC";
$owned_teams_stmt = $db->prepare($owned_teams_query);
$owned_teams_stmt->bindParam(':user_id', $current_user['id']);
$owned_teams_stmt->execute();
$owned_teams = $owned_teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get teams user is a member of (but not owner)
$member_teams_query = "SELECT t.*, l.name as league_name, l.season, l.status as league_status, s.name as sport_name,
                              tm.position, tm.jersey_number, tm.joined_at,
                              u.first_name as owner_first_name, u.last_name as owner_last_name,
                              (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count,
                              (SELECT COUNT(*) FROM matches WHERE (home_team_id = t.id OR away_team_id = t.id) AND status = 'scheduled' AND match_date >= NOW()) as upcoming_matches
                       FROM teams t 
                       JOIN leagues l ON t.league_id = l.id 
                       JOIN sports s ON l.sport_id = s.id
                       JOIN team_members tm ON t.id = tm.team_id
                       JOIN users u ON t.owner_id = u.id
                       WHERE tm.player_id = :user_id AND tm.status = 'active' AND t.owner_id != :user_id
                       ORDER BY l.status DESC, tm.joined_at DESC";
$member_teams_stmt = $db->prepare($member_teams_query);
$member_teams_stmt->bindParam(':user_id', $current_user['id']);
$member_teams_stmt->execute();
$member_teams = $member_teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending join requests
$pending_requests_query = "SELECT rr.*, t.name as team_name, l.name as league_name, l.season, s.name as sport_name,
                                  u.first_name as owner_first_name, u.last_name as owner_last_name
                           FROM registration_requests rr
                           JOIN teams t ON rr.team_id = t.id
                           JOIN leagues l ON rr.league_id = l.id
                           JOIN sports s ON l.sport_id = s.id
                           JOIN users u ON t.owner_id = u.id
                           WHERE rr.player_id = :user_id AND rr.status = 'pending'
                           ORDER BY rr.created_at DESC";
$pending_requests_stmt = $db->prepare($pending_requests_query);
$pending_requests_stmt->bindParam(':user_id', $current_user['id']);
$pending_requests_stmt->execute();
$pending_requests = $pending_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's overall statistics
$user_stats_query = "SELECT 
                    COUNT(DISTINCT CASE WHEN t.owner_id = :user_id THEN t.id END) as teams_owned,
                    COUNT(DISTINCT CASE WHEN tm.player_id = :user_id THEN tm.team_id END) as teams_joined,
                    COUNT(DISTINCT CASE WHEN tm.player_id = :user_id THEN l.id END) as leagues_participated,
                    COALESCE(SUM(ps.matches_played), 0) as total_matches_played,
                    COALESCE(SUM(ps.goals), 0) as total_goals,
                    COALESCE(SUM(ps.assists), 0) as total_assists
                    FROM users u
                    LEFT JOIN teams t ON u.id = t.owner_id
                    LEFT JOIN team_members tm ON u.id = tm.player_id AND tm.status = 'active'
                    LEFT JOIN leagues l ON t.league_id = l.id OR tm.team_id IN (SELECT id FROM teams WHERE league_id = l.id)
                    LEFT JOIN player_stats ps ON u.id = ps.player_id
                    WHERE u.id = :user_id";
$user_stats_stmt = $db->prepare($user_stats_query);
$user_stats_stmt->bindParam(':user_id', $current_user['id']);
$user_stats_stmt->execute();
$user_stats = $user_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get upcoming matches for user's teams
$all_team_ids = array_merge(
    array_column($owned_teams, 'id'),
    array_column($member_teams, 'id')
);

$upcoming_matches = [];
if (!empty($all_team_ids)) {
    $team_ids_str = implode(',', $all_team_ids);
    $upcoming_matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team, v.name as venue_name, v.address as venue_address,
                                      l.name as league_name
                               FROM matches m
                               JOIN teams ht ON m.home_team_id = ht.id
                               JOIN teams at ON m.away_team_id = at.id
                               JOIN leagues l ON m.league_id = l.id
                               LEFT JOIN venues v ON m.venue_id = v.id
                               WHERE (m.home_team_id IN ($team_ids_str) OR m.away_team_id IN ($team_ids_str))
                               AND m.status = 'scheduled'
                               AND m.match_date >= NOW()
                               ORDER BY m.match_date ASC
                               LIMIT 5";
    $upcoming_matches_stmt = $db->prepare($upcoming_matches_query);
    $upcoming_matches_stmt->execute();
    $upcoming_matches = $upcoming_matches_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Teams - Sports League Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, blue, purple);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><polygon points="0,0 100,0 80,100 0,80" fill="rgba(255,255,255,0.05)"/></svg>') no-repeat;
            background-size: cover;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .nav-breadcrumb {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .breadcrumb {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .user-stats {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .stat-card {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; transform: translateY(-1px); }
        
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
        
        .btn-warning { background: #ffc107; color: black; }
        .btn-warning:hover { background: #e0a800; }
        
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.8rem; }
        
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .team-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        
        .team-header {
             background: linear-gradient(135deg, purple, greenyellow);
            color: white;
            padding: 1.5rem;
            position: relative;
        }
        
        .owner-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #ffc107;
            color: black;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .member-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #28a745;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .team-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #6f42c1);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .team-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .team-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .team-body {
            padding: 1.5rem;
        }
        
        .team-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .team-stat {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .team-stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.25rem;
        }
        
        .team-stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
        }
        
        .team-info {
            margin-bottom: 1.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .info-label {
            color: #666;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .team-record {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .record-display {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .wins { color: #28a745; }
        .draws { color: #ffc107; }
        .losses { color: #dc3545; }
        .points { color: #007bff; font-size: 1.3rem; }
        
        .team-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .upcoming-matches {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .match-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .match-item:hover {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .match-date {
            font-weight: bold;
            color: #007bff;
        }
        
        .match-teams {
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .match-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
        }
        
        .requests-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .request-card {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .request-team {
            font-weight: bold;
            color: #856404;
        }
        
        .request-date {
            font-size: 0.9rem;
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            color: #666;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #999;
        }
        
        .empty-state p {
            margin-bottom: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .quick-actions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .league-status-indicator {
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .league-open { background: #d4edda; color: #155724; }
        .league-active { background: #cce7ff; color: #004085; }
        .league-closed { background: #fff3cd; color: #856404; }
        .league-completed { background: #e2e3e5; color: #383d41; }
        
        @media (max-width: 768px) {
            .header { padding: 1.5rem 1rem; }
            .header h1 { font-size: 2rem; }
            .container { padding: 0 1rem; }
            .teams-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .team-stats { grid-template-columns: repeat(2, 1fr); }
            .section-header { flex-direction: column; gap: 1rem; align-items: stretch; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>⚽ My Teams</h1>
            <p>Manage your teams and track your sports journey</p>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <div class="nav-breadcrumb">
        <div class="breadcrumb">
            <a href="../dashboard.php">Dashboard</a>
            <span>›</span>
            <span>My Teams</span>
        </div>
    </div>
    
    <div class="container">
        <!-- Display messages -->
        <?php displayMessage(); ?>
        
        <!-- User Statistics -->
        <div class="user-stats">
            <h3 style="margin-bottom: 1rem; color: #333;">📊 Your Sports Statistics</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['teams_owned']; ?></div>
                    <div class="stat-label">Teams Owned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['teams_joined']; ?></div>
                    <div class="stat-label">Teams Joined</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['leagues_participated']; ?></div>
                    <div class="stat-label">Leagues</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['total_matches_played']; ?></div>
                    <div class="stat-label">Matches Played</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['total_goals']; ?></div>
                    <div class="stat-label">Goals Scored</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $user_stats['total_assists']; ?></div>
                    <div class="stat-label">Assists</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3 style="margin-bottom: 1rem; color: #333;">🚀 Quick Actions</h3>
            <a href="create_team.php" class="btn btn-success">
                ➕ Create New Team
            </a>
            <a href="../league/browse_leagues.php" class="btn btn-info">
                🔍 Browse Leagues
            </a>
            <a href="join_team.php" class="btn btn-primary">
                👥 Join Existing Team
            </a>
        </div>
        
        <!-- Teams I Own -->
        <?php if (count($owned_teams) > 0): ?>
        <div class="section-header">
            <h2 class="section-title">👑 Teams I Own (<?php echo count($owned_teams); ?>)</h2>
        </div>
        
        <div class="teams-grid">
            <?php foreach ($owned_teams as $team): ?>
            <div class="team-card">
                <div class="owner-badge">👑 Owner</div>
                
                <div class="team-header">
                    <div class="team-logo">
                        <?php echo strtoupper(substr($team['name'], 0, 2)); ?>
                    </div>
                    <div class="team-title"><?php echo htmlspecialchars($team['name']); ?></div>
                    <div class="team-subtitle">
                        <?php echo htmlspecialchars($team['sport_name']); ?> • <?php echo htmlspecialchars($team['league_name']); ?>
                        <span class="league-status-indicator league-<?php echo $team['league_status']; ?>">
                            <?php echo ucfirst($team['league_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="team-body">
                    <div class="team-stats">
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['member_count']; ?></div>
                            <div class="team-stat-label">Members</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['upcoming_matches']; ?></div>
                            <div class="team-stat-label">Upcoming</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['completed_matches']; ?></div>
                            <div class="team-stat-label">Played</div>
                        </div>
                    </div>
                    
                    <div class="team-record">
                        <div class="record-display">
                            <span class="wins"><?php echo $team['wins']; ?>W</span> - 
                            <span class="draws"><?php echo $team['draws']; ?>D</span> - 
                            <span class="losses"><?php echo $team['losses']; ?>L</span>
                        </div>
                        <div class="points">Points: <?php echo $team['points']; ?></div>
                    </div>
                    
                    <div class="team-info">
                        <div class="info-row">
                            <span class="info-label">📅 Season:</span>
                            <span class="info-value"><?php echo htmlspecialchars($team['season']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">📈 Created:</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($team['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="team-actions">
                        <a href="manage_team.php?id=<?php echo $team['id']; ?>" class="btn btn-primary btn-sm">
                            ⚙️ Manage Team
                        </a>
                        <a href="../league/view_league.php?id=<?php echo $team['league_id']; ?>" class="btn btn-info btn-sm">
                            🏆 View League
                        </a>
                        <?php if ($team['completed_matches'] == 0): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this team? This action cannot be undone!')">
                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            <input type="hidden" name="action" value="delete_team">
                            <button type="submit" class="btn btn-danger btn-sm">
                                🗑️ Delete Team
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Teams I'm Member Of -->
        <?php if (count($member_teams) > 0): ?>
        <div class="section-header">
            <h2 class="section-title">👥 Teams I'm Member Of (<?php echo count($member_teams); ?>)</h2>
        </div>
        
        <div class="teams-grid">
            <?php foreach ($member_teams as $team): ?>
            <div class="team-card">
                <div class="member-badge">👤 Member</div>
                
                <div class="team-header">
                    <div class="team-logo">
                        <?php echo strtoupper(substr($team['name'], 0, 2)); ?>
                    </div>
                    <div class="team-title"><?php echo htmlspecialchars($team['name']); ?></div>
                    <div class="team-subtitle">
                        <?php echo htmlspecialchars($team['sport_name']); ?> • <?php echo htmlspecialchars($team['league_name']); ?>
                        <span class="league-status-indicator league-<?php echo $team['league_status']; ?>">
                            <?php echo ucfirst($team['league_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="team-body">
                    <div class="team-stats">
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['member_count']; ?></div>
                            <div class="team-stat-label">Members</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['upcoming_matches']; ?></div>
                            <div class="team-stat-label">Upcoming</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['wins'] + $team['draws'] + $team['losses']; ?></div>
                            <div class="team-stat-label">Played</div>
                        </div>
                    </div>
                    
                    <div class="team-record">
                        <div class="record-display">
                            <span class="wins"><?php echo $team['wins']; ?>W</span> - 
                            <span class="draws"><?php echo $team['draws']; ?>D</span> - 
                            <span class="losses"><?php echo $team['losses']; ?>L</span>
                        </div>
                        <div class="points">Points: <?php echo $team['points']; ?></div>
                    </div>
                    
                    <div class="team-info">
                        <div class="info-row">
                            <span class="info-label">👤 Owner:</span>
                            <span class="info-value"><?php echo htmlspecialchars($team['owner_first_name'] . ' ' . $team['owner_last_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">🎯 My Position:</span>
                            <span class="info-value"><?php echo htmlspecialchars($team['position'] ?? 'Not Set'); ?></span>
                        </div>
                        <?php if ($team['jersey_number']): ?>
                        <div class="info-row">
                            <span class="info-label">👕 Jersey #:</span>
                            <span class="info-value"><?php echo $team['jersey_number']; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">📅 Joined:</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($team['joined_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="team-actions">
                        <a href="view_team.php?id=<?php echo $team['id']; ?>" class="btn btn-primary btn-sm">
                            👁️ View Team
                        </a>
                        <a href="../league/view_league.php?id=<?php echo $team['league_id']; ?>" class="btn btn-info btn-sm">
                            🏆 View League
                        </a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to leave this team?')">
                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            <input type="hidden" name="action" value="leave_team">
                            <button type="submit" class="btn btn-warning btn-sm">
                                🚪 Leave Team
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Upcoming Matches -->
        <?php if (count($upcoming_matches) > 0): ?>
        <div class="upcoming-matches">
            <div class="section-header">
                <h2 class="section-title">⚽ Upcoming Matches (<?php echo count($upcoming_matches); ?>)</h2>
                <a href="../match/my_matches.php" class="btn btn-secondary btn-sm">View All Matches</a>
            </div>
            
            <?php foreach ($upcoming_matches as $match): ?>
            <div class="match-item">
                <div class="match-header">
                    <div class="match-date">
                        <?php echo date('M j, Y • g:i A', strtotime($match['match_date'])); ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">
                        <?php echo htmlspecialchars($match['league_name']); ?>
                    </div>
                </div>
                
                <div class="match-teams">
                    <?php echo htmlspecialchars($match['home_team']); ?> 
                    <span style="color: #666; font-weight: normal;">vs</span> 
                    <?php echo htmlspecialchars($match['away_team']); ?>
                </div>
                
                <div class="match-details">
                    <span>📍 <?php echo htmlspecialchars($match['venue_name'] ?? 'Venue TBD'); ?></span>
                    <span><?php echo ucfirst($match['status']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Pending Join Requests -->
        <?php if (count($pending_requests) > 0): ?>
        <div class="requests-section">
            <div class="section-header">
                <h2 class="section-title">⏳ Pending Join Requests (<?php echo count($pending_requests); ?>)</h2>
            </div>
            
            <?php foreach ($pending_requests as $request): ?>
            <div class="request-card">
                <div class="request-header">
                    <div class="request-team">
                        <?php echo htmlspecialchars($request['team_name']); ?> 
                        <small>(<?php echo htmlspecialchars($request['league_name']); ?>)</small>
                    </div>
                    <div class="request-date">
                        <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 0.5rem;">
                    <strong>Sport:</strong> <?php echo htmlspecialchars($request['sport_name']); ?> • 
                    <strong>Season:</strong> <?php echo htmlspecialchars($request['season']); ?>
                </div>
                
                <div style="margin-bottom: 0.5rem;">
                    <strong>Team Owner:</strong> <?php echo htmlspecialchars($request['owner_first_name'] . ' ' . $request['owner_last_name']); ?>
                </div>
                
                <?php if ($request['preferred_position']): ?>
                <div style="margin-bottom: 0.5rem;">
                    <strong>Requested Position:</strong> <?php echo htmlspecialchars($request['preferred_position']); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($request['message']): ?>
                <div style="background: rgba(255,255,255,0.7); padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem;">
                    <strong>Message:</strong> <?php echo htmlspecialchars($request['message']); ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 1rem; font-size: 0.9rem; color: #856404;">
                    ⏳ Waiting for team owner's response...
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Empty States -->
        <?php if (count($owned_teams) == 0 && count($member_teams) == 0): ?>
        <div class="empty-state">
            <h3>🎯 Ready to Start Your Sports Journey?</h3>
            <p>You haven't joined any teams yet. Create your own team or join an existing one to get started!</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="create_team.php" class="btn btn-success">
                    ➕ Create Your First Team
                </a>
                <a href="../league/browse_leagues.php" class="btn btn-info">
                    🔍 Browse Available Leagues
                </a>
                <a href="join_team.php" class="btn btn-primary">
                    👥 Join Existing Team
                </a>
            </div>
        </div>
        <?php elseif (count($upcoming_matches) == 0): ?>
        <div class="alert alert-info">
            <strong>📅 No upcoming matches</strong> - Matches will appear here when they are scheduled for your teams.
        </div>
        <?php endif; ?>
        
        <!-- Performance Insights -->
        <?php if (count($owned_teams) > 0 || count($member_teams) > 0): ?>
        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 2rem; margin-top: 2rem;">
            <h3 style="margin-bottom: 1rem; color: #333;">📊 Performance Insights</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div>
                    <h4 style="color: #28a745; margin-bottom: 1rem;">🏆 Team Performance</h4>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
                        <?php
                        $total_wins = array_sum(array_column($owned_teams, 'wins')) + array_sum(array_column($member_teams, 'wins'));
                        $total_matches = array_sum(array_map(function($t) { return $t['wins'] + $t['draws'] + $t['losses']; }, array_merge($owned_teams, $member_teams)));
                        $win_rate = $total_matches > 0 ? round(($total_wins / $total_matches) * 100, 1) : 0;
                        ?>
                        <p><strong>Win Rate:</strong> <?php echo $win_rate; ?>% (<?php echo $total_wins; ?>/<?php echo $total_matches; ?>)</p>
                        <p><strong>Total Points:</strong> <?php echo array_sum(array_column($owned_teams, 'points')) + array_sum(array_column($member_teams, 'points')); ?></p>
                        <p><strong>Active Teams:</strong> <?php echo count(array_filter(array_merge($owned_teams, $member_teams), function($t) { return $t['league_status'] == 'active'; })); ?></p>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: #007bff; margin-bottom: 1rem;">⚽ Personal Stats</h4>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
                        <p><strong>Goals:</strong> <?php echo $user_stats['total_goals']; ?></p>
                        <p><strong>Assists:</strong> <?php echo $user_stats['total_assists']; ?></p>
                        <p><strong>Matches Played:</strong> <?php echo $user_stats['total_matches_played']; ?></p>
                        <?php if ($user_stats['total_matches_played'] > 0): ?>
                            <p><strong>Goals per Match:</strong> <?php echo round($user_stats['total_goals'] / $user_stats['total_matches_played'], 2); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <h4 style="color: #ffc107; margin-bottom: 1rem;">🎯 Achievements</h4>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
                        <?php
                        $achievements = [];
                        if ($user_stats['teams_owned'] >= 1) $achievements[] = "🏅 Team Owner";
                        if ($user_stats['total_goals'] >= 10) $achievements[] = "⚽ Goal Scorer";
                        if ($user_stats['total_assists'] >= 5) $achievements[] = "🎯 Playmaker";
                        if ($user_stats['leagues_participated'] >= 3) $achievements[] = "🏆 League Veteran";
                        if ($total_matches > 0 && $win_rate >= 70) $achievements[] = "🔥 Winner";
                        
                        if (empty($achievements)) {
                            echo "<p>Keep playing to unlock achievements! 🌟</p>";
                        } else {
                            foreach ($achievements as $achievement) {
                                echo "<p>$achievement</p>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Animate team cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.team-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Add hover effects to team cards
        document.querySelectorAll('.team-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-5px) scale(1)';
            });
        });
        
        // Confirm dangerous actions
        function confirmAction(message) {
            return confirm(message);
        }
        
        // Auto-refresh for live data (every 2 minutes)
        setInterval(function() {
            // Only refresh if user is actively viewing the page
            if (document.hasFocus() && document.visibilityState === 'visible') {
                // In a full implementation, this would update specific sections via AJAX
                const activeLeagues = document.querySelectorAll('.league-active');
                if (activeLeagues.length > 0) {
                    console.log('Checking for updates...');
                }
            }
        }, 120000); // 2 minutes
        
        // Enhanced visual feedback
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                // Add ripple effect
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255,255,255,0.5);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                this.style.position = 'relative';
                this.style.overflow = 'hidden';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Show toast notifications for actions
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                z-index: 1000;
                animation: slideInRight 0.3s ease;
            `;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Add toast animations
        const toastStyle = document.createElement('style');
        toastStyle.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(toastStyle);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N to create new team
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'create_team.php';
            }
            
            // Ctrl/Cmd + B to browse leagues
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                window.location.href = '../league/browse_leagues.php';
            }
        });
        
        // Team performance indicators
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight high-performing teams
            document.querySelectorAll('.team-card').forEach(card => {
                const pointsElement = card.querySelector('.points');
                if (pointsElement) {
                    const points = parseInt(pointsElement.textContent.replace('Points: ', ''));
                    if (points >= 20) {
                        card.style.borderLeft = '4px solid #28a745';
                    } else if (points >= 10) {
                        card.style.borderLeft = '4px solid #ffc107';
                    }
                }
            });
        });
        
        // Quick statistics calculation
        function calculateTeamStats() {
            const ownedTeams = document.querySelectorAll('[data-team-type="owned"]').length;
            const memberTeams = document.querySelectorAll('[data-team-type="member"]').length;
            
            console.log(`Teams owned: ${ownedTeams}, Teams joined: ${memberTeams}`);
        }
        
        calculateTeamStats();
    </script>
</body>
</html>

<?php
// End of my_teams.php
?>