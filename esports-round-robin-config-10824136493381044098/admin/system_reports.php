<?php
// admin/system_reports.php - System Reports and Analytics
require_once '../config/database.php';
requireRole('admin');

$database = new Database();
$db = $database->connect();

// Get system statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'player') as total_players,
    (SELECT COUNT(*) FROM users WHERE role = 'team_owner') as total_owners,
    (SELECT COUNT(*) FROM leagues) as total_leagues,
    (SELECT COUNT(*) FROM leagues WHERE status = 'active') as active_leagues,
    (SELECT COUNT(*) FROM teams) as total_teams,
    (SELECT COUNT(*) FROM matches) as total_matches,
    (SELECT COUNT(*) FROM matches WHERE status = 'completed') as completed_matches,
    (SELECT COUNT(*) FROM registration_requests WHERE status = 'pending') as pending_requests";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get league statistics
$league_stats_query = "SELECT l.name as league_name, l.season, s.name as sport_name,
                       COUNT(t.id) as team_count,
                       COUNT(CASE WHEN m.status = 'completed' THEN 1 END) as completed_matches,
                       COUNT(CASE WHEN m.status = 'scheduled' THEN 1 END) as scheduled_matches
                       FROM leagues l
                       JOIN sports s ON l.sport_id = s.id
                       LEFT JOIN teams t ON l.id = t.league_id
                       LEFT JOIN matches m ON l.id = m.league_id
                       GROUP BY l.id
                       ORDER BY l.created_at DESC";

$league_stats_stmt = $db->prepare($league_stats_query);
$league_stats_stmt->execute();
$league_stats = $league_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user registration trends (last 30 days)
$registration_trends_query = "SELECT DATE(created_at) as reg_date, COUNT(*) as registrations
                             FROM users 
                             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             GROUP BY DATE(created_at)
                             ORDER BY reg_date DESC";

$trends_stmt = $db->prepare($registration_trends_query);
$trends_stmt->execute();
$registration_trends = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top performing teams
$top_teams_query = "SELECT t.name as team_name, l.name as league_name, l.season,
                    t.wins, t.draws, t.losses, t.points,
                    (t.wins + t.draws + t.losses) as games_played,
                    CASE WHEN (t.wins + t.draws + t.losses) > 0 
                         THEN ROUND((t.wins / (t.wins + t.draws + t.losses)) * 100, 1) 
                         ELSE 0 END as win_percentage
                    FROM teams t
                    JOIN leagues l ON t.league_id = l.id
                    WHERE (t.wins + t.draws + t.losses) > 0
                    ORDER BY t.points DESC, win_percentage DESC
                    LIMIT 10";

$top_teams_stmt = $db->prepare($top_teams_query);
$top_teams_stmt->execute();
$top_teams = $top_teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get most active players
$active_players_query = "SELECT u.first_name, u.last_name, u.username,
                         COUNT(DISTINCT tm.team_id) as teams_count,
                         COUNT(DISTINCT ps.league_id) as leagues_participated,
                         SUM(ps.matches_played) as total_matches,
                         SUM(ps.goals) as total_goals
                         FROM users u
                         JOIN team_members tm ON u.id = tm.player_id
                         LEFT JOIN player_stats ps ON u.id = ps.player_id
                         WHERE tm.status = 'active'
                         GROUP BY u.id
                         ORDER BY total_matches DESC, teams_count DESC
                         LIMIT 10";

$active_players_stmt = $db->prepare($active_players_query);
$active_players_stmt->execute();
$active_players = $active_players_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle report generation requests
if (isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    
    // This would generate different types of reports
    // For now, we'll just show a success message
    showMessage("Report generated successfully! (Feature would export to CSV/PDF)", "success");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>System Reports - Sports League Admin</title>
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2.5rem; font-weight: bold; margin-bottom: 10px; }
        .stat-label { color: #666; font-size: 0.9rem; }
        .primary { color: #007bff; }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .danger { color: #dc3545; }
        .info { color: #17a2b8; }
        
        .report-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        
        .form-group { margin-bottom: 15px; display: inline-block; margin-right: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; }
        
        .chart-placeholder { background: #f8f9fa; border: 2px dashed #ddd; padding: 40px; text-align: center; color: #666; border-radius: 8px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <h1>System Reports & Analytics</h1>
        
        <?php displayMessage(); ?>
        
        <!-- System Overview Stats -->
        <h2>System Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number primary"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number success"><?php echo $stats['total_players']; ?></div>
                <div class="stat-label">Players</div>
            </div>
            <div class="stat-card">
                <div class="stat-number warning"><?php echo $stats['total_owners']; ?></div>
                <div class="stat-label">Team Owners</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info"><?php echo $stats['active_leagues']; ?></div>
                <div class="stat-label">Active Leagues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number primary"><?php echo $stats['total_teams']; ?></div>
                <div class="stat-label">Total Teams</div>
            </div>
            <div class="stat-card">
                <div class="stat-number success"><?php echo $stats['completed_matches']; ?></div>
                <div class="stat-label">Completed Matches</div>
            </div>
            <div class="stat-card">
                <div class="stat-number warning"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info"><?php echo $stats['total_matches'] - $stats['completed_matches']; ?></div>
                <div class="stat-label">Upcoming Matches</div>
            </div>
        </div>
        
        <!-- Report Generation -->
        <div class="report-section">
            <h3>Generate Custom Report</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Report Type:</label>
                    <select name="report_type" required>
                        <option value="">Select Report Type</option>
                        <option value="user_activity">User Activity Report</option>
                        <option value="league_performance">League Performance Report</option>
                        <option value="team_statistics">Team Statistics Report</option>
                        <option value="match_results">Match Results Report</option>
                        <option value="registration_trends">Registration Trends</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>From Date:</label>
                    <input type="date" name="date_from" value="<?php echo date('Y-m-01'); ?>">
                </div>
                
                <div class="form-group">
                    <label>To Date:</label>
                    <input type="date" name="date_to" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <br>
                <button type="submit" name="generate_report" class="btn btn-success">Generate Report</button>
            </form>
        </div>
        
        <!-- Detailed Reports -->
        <div class="report-grid">
            <!-- League Statistics -->
            <div class="report-section">
                <h3>League Statistics</h3>
                <table>
                    <thead>
                        <tr>
                            <th>League</th>
                            <th>Sport</th>
                            <th>Teams</th>
                            <th>Completed</th>
                            <th>Scheduled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($league_stats as $league): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($league['league_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($league['season']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($league['sport_name']); ?></td>
                            <td><?php echo $league['team_count']; ?></td>
                            <td><?php echo $league['completed_matches']; ?></td>
                            <td><?php echo $league['scheduled_matches']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Top Performing Teams -->
            <div class="report-section">
                <h3>Top Performing Teams</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Team</th>
                            <th>League</th>
                            <th>Record</th>
                            <th>Points</th>
                            <th>Win %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_teams as $team): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($team['league_name']); ?><br>
                                <small><?php echo htmlspecialchars($team['season']); ?></small>
                            </td>
                            <td><?php echo $team['wins']; ?>W-<?php echo $team['draws']; ?>D-<?php echo $team['losses']; ?>L</td>
                            <td><strong><?php echo $team['points']; ?></strong></td>
                            <td><?php echo $team['win_percentage']; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- More detailed reports -->
        <div class="report-grid">
            <!-- Most Active Players -->
            <div class="report-section">
                <h3>Most Active Players</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Player</th>
                            <th>Teams</th>
                            <th>Leagues</th>
                            <th>Matches</th>
                            <th>Goals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_players as $player): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?></strong><br>
                                <small>@<?php echo htmlspecialchars($player['username']); ?></small>
                            </td>
                            <td><?php echo $player['teams_count']; ?></td>
                            <td><?php echo $player['leagues_participated'] ?? 0; ?></td>
                            <td><?php echo $player['total_matches'] ?? 0; ?></td>
                            <td><?php echo $player['total_goals'] ?? 0; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Registration Trends -->
            <div class="report-section">
                <?php if (count($registration_trends) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>New Registrations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registration_trends as $trend): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($trend['reg_date'])); ?></td>
                            <td><?php echo $trend['registrations']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No new registrations in the last 30 days.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chart Placeholder -->
        <div class="report-section">
            <h3>Performance Analytics</h3>
            <div class="chart-placeholder">
                <h4>Chart Visualization Area</h4>
                <p>This area would contain interactive charts showing:</p>
                <ul style="text-align: left; display: inline-block;">
                    <li>User registration trends over time</li>
                    <li>League activity and participation rates</li>
                    <li>Match completion statistics</li>
                    <li>Team performance comparisons</li>
                    <li>Player engagement metrics</li>
                </ul>
                <p><em>Charts would be implemented using Chart.js or similar library</em></p>
            </div>
        </div>
        
        <div class="alert alert-info">
            <strong>Export Options:</strong> In a full implementation, reports could be exported to PDF, CSV, or Excel formats. 
            Advanced features might include scheduled reports, email notifications, and interactive dashboards.
        </div>
        
        <p><a href="./dashboard.php" class="btn">← Back to Dashboard</a></p>
    </div>
</body>
</html>

<?php
// admin/manage_users.php - User Management for Admins
if (!isset($_POST['generate_report'])) {
    $database = new Database();
    $db = $database->connect();
    
    // Handle user actions
    if (isset($_POST['action'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'activate':
                $update_query = "UPDATE users SET status = 'active' WHERE id = :id";
                break;
            case 'deactivate':
                $update_query = "UPDATE users SET status = 'inactive' WHERE id = :id";
                break;
            case 'promote_to_owner':
                $update_query = "UPDATE users SET role = 'team_owner' WHERE id = :id";
                break;
            case 'demote_to_player':
                $update_query = "UPDATE users SET role = 'player' WHERE id = :id";
                break;
            default:
                $update_query = null;
        }
        
        if ($update_query) {
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':id', $user_id);
            
            if ($update_stmt->execute()) {
                showMessage("User updated successfully!", "success");
            } else {
                showMessage("Failed to update user!", "error");
            }
        }
    }
    
    // Get users with pagination
    $page = $_GET['page'] ?? 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR username LIKE :search OR email LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if ($role_filter) {
        $where_conditions[] = "role = :role";
        $params[':role'] = $role_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM users $where_clause";
    $count_stmt = $db->prepare($count_query);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_users / $per_page);
    
    // Get users
    $users_query = "SELECT u.*, 
                           (SELECT COUNT(*) FROM teams WHERE owner_id = u.id) as owned_teams,
                           (SELECT COUNT(*) FROM team_members WHERE player_id = u.id AND status = 'active') as team_memberships
                    FROM users u 
                    $where_clause 
                    ORDER BY u.created_at DESC 
                    LIMIT $per_page OFFSET $offset";
    
    $users_stmt = $db->prepare($users_query);
    foreach ($params as $key => $value) {
        $users_stmt->bindValue($key, $value);
    }
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Sports League Admin</title>
    <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .search-bar { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .search-bar form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .search-bar input, .search-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .search-bar input[type="text"] { flex: 1; min-width: 200px; }
        
        .users-table { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f8f9fa; }
        
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .user-details h4 { margin: 0; }
        .user-details small { color: #666; }
        
        .role-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .role-admin { background: #dc3545; color: white; }
        .role-team_owner { background: #ffc107; color: black; }
        .role-player { background: #28a745; color: white; }
        
        .action-buttons { display: flex; gap: 5px; }
        .btn-sm { padding: 4px 8px; font-size: 12px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #007bff; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; }
        .pagination .current { background: #007bff; color: white; border-color: #007bff; }
        
        .stats-summary { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; display: flex; justify-content: space-around; text-align: center; }
        .stat-item h3 { margin: 0; color: #007bff; }
        .stat-item p { margin: 5px 0 0 0; color: #666; }
    </style>
    <script>
        function confirmAction(action, userName) {
            return confirm(`Are you sure you want to ${action} user "${userName}"?`);
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Manage Users</h1>
        
        <?php displayMessage(); ?>
        
        <!-- Stats Summary -->
        <div class="stats-summary">
            <div class="stat-item">
                <h3><?php echo $total_users; ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-item">
                <h3><?php 
                $admin_count = 0;
                foreach ($users as $user) {
                    if ($user['role'] == 'admin') $admin_count++;
                }
                echo $admin_count;
                ?></h3>
                <p>Administrators</p>
            </div>
            <div class="stat-item">
                <h3><?php 
                $owner_count = 0;
                foreach ($users as $user) {
                    if ($user['role'] == 'team_owner') $owner_count++;
                }
                echo $owner_count;
                ?></h3>
                <p>Team Owners</p>
            </div>
            <div class="stat-item">
                <h3><?php 
                $player_count = 0;
                foreach ($users as $user) {
                    if ($user['role'] == 'player') $player_count++;
                }
                echo $player_count;
                ?></h3>
                <p>Players</p>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="search-bar">
            <form method="GET">
                <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="player" <?php echo $role_filter == 'player' ? 'selected' : ''; ?>>Players</option>
                    <option value="team_owner" <?php echo $role_filter == 'team_owner' ? 'selected' : ''; ?>>Team Owners</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Administrators</option>
                </select>
                <button type="submit" class="btn-primary" style="padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Filter</button>
                <a href="manage_users.php" class="btn-sm btn-primary">Clear</a>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Teams Owned</th>
                        <th>Team Memberships</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                    <small>@<?php echo htmlspecialchars($user['username']); ?> • <?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($user['role'])); ?>
                            </span>
                        </td>
                        <td><?php echo $user['owned_teams']; ?></td>
                        <td><?php echo $user['team_memberships']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($user['role'] != 'admin'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('promote to admin', '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="promote_to_admin">
                                        <button type="submit" class="btn-sm btn-danger">Make Admin</button>
                                    </form>
                                    
                                    <?php if ($user['role'] == 'player'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmAction('promote to team owner', '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="promote_to_owner">
                                            <button type="submit" class="btn-sm btn-warning">Make Owner</button>
                                        </form>
                                    <?php elseif ($user['role'] == 'team_owner'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmAction('demote to player', '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="demote_to_player">
                                            <button type="submit" class="btn-sm btn-success">Make Player</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-sm btn-primary">View</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <p><a href="../dashboard.php" class="btn-primary" style="padding: 10px 20px; text-decoration: none; border-radius: 4px;">← Back to Dashboard</a></p>
    </div>
</body>
</html>

<?php
}
?>