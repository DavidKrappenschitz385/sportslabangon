<?php
// league/browse_leagues.php - Browse and Discover Leagues
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Handle quick actions
if (isset($_POST['action'])) {
    $league_id = $_POST['league_id'];
    $action = $_POST['action'];
    
    if ($action == 'quick_create_team') {
        $team_name = trim($_POST['team_name']);
        
        if (empty($team_name)) {
            showMessage("Team name is required!", "error");
        } else {
            // Check if user already owns a team in this league
            $check_query = "SELECT id FROM teams WHERE league_id = :league_id AND owner_id = :owner_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':league_id', $league_id);
            $check_stmt->bindParam(':owner_id', $current_user['id']);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                showMessage("You already own a team in this league!", "error");
            } else {
                // Create team
                $create_query = "INSERT INTO teams (name, league_id, owner_id, description) 
                                VALUES (:name, :league_id, :owner_id, 'Quick created team')";
                $create_stmt = $db->prepare($create_query);
                $create_stmt->bindParam(':name', $team_name);
                $create_stmt->bindParam(':league_id', $league_id);
                $create_stmt->bindParam(':owner_id', $current_user['id']);
                
                if ($create_stmt->execute()) {
                    $team_id = $db->lastInsertId();
                    
                    // Add owner as team member
                    $member_query = "INSERT INTO team_members (team_id, player_id, position) 
                                    VALUES (:team_id, :player_id, 'Captain')";
                    $member_stmt = $db->prepare($member_query);
                    $member_stmt->bindParam(':team_id', $team_id);
                    $member_stmt->bindParam(':player_id', $current_user['id']);
                    $member_stmt->execute();
                    
                    showMessage("Team created successfully! Welcome to the league!", "success");
                    redirect('view_league.php?id=' . $league_id);
                } else {
                    showMessage("Failed to create team!", "error");
                }
            }
        }
    }
}

// Get filter parameters
$sport_filter = $_GET['sport'] ?? '';
$status_filter = $_GET['status'] ?? 'open'; // Default to open leagues
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query conditions
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(l.name LIKE :search OR l.season LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($sport_filter) {
    $where_conditions[] = "l.sport_id = :sport_id";
    $params[':sport_id'] = $sport_filter;
}

if ($status_filter) {
    $where_conditions[] = "l.status = :status";
    $params[':status'] = $status_filter;
}

// Don't show draft leagues to non-admins
if ($current_user['role'] != 'admin') {
    $where_conditions[] = "l.status != 'draft'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Determine sort order
$order_clause = "ORDER BY ";
switch ($sort) {
    case 'newest':
        $order_clause .= "l.created_at DESC";
        break;
    case 'oldest':
        $order_clause .= "l.created_at ASC";
        break;
    case 'name':
        $order_clause .= "l.name ASC";
        break;
    case 'deadline':
        $order_clause .= "l.registration_deadline ASC";
        break;
    case 'popular':
        $order_clause .= "team_count DESC, l.created_at DESC";
        break;
    default:
        $order_clause .= "l.created_at DESC";
}

// Get leagues with comprehensive information
$leagues_query = "SELECT l.*, s.name as sport_name, s.description as sport_description, s.max_players_per_team,
                         u.first_name as creator_first_name, u.last_name as creator_last_name,
                         (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as team_count,
                         (SELECT COUNT(*) FROM matches WHERE league_id = l.id) as total_matches,
                         (SELECT COUNT(*) FROM matches WHERE league_id = l.id AND status = 'completed') as completed_matches,
                         (SELECT COUNT(*) FROM registration_requests WHERE league_id = l.id AND status = 'pending') as pending_requests
                  FROM leagues l 
                  JOIN sports s ON l.sport_id = s.id 
                  JOIN users u ON l.created_by = u.id
                  $where_clause 
                  $order_clause";

$leagues_stmt = $db->prepare($leagues_query);
foreach ($params as $key => $value) {
    $leagues_stmt->bindValue($key, $value);
}
$leagues_stmt->execute();
$leagues = $leagues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's teams for checking participation
$user_teams_query = "SELECT league_id FROM teams 
                     WHERE owner_id = :user_id 
                     UNION 
                     SELECT t.league_id FROM teams t 
                     JOIN team_members tm ON t.id = tm.team_id 
                     WHERE tm.player_id = :user_id AND tm.status = 'active'";
$user_teams_stmt = $db->prepare($user_teams_query);
$user_teams_stmt->bindParam(':user_id', $current_user['id']);
$user_teams_stmt->execute();
$user_league_participations = array_column($user_teams_stmt->fetchAll(PDO::FETCH_ASSOC), 'league_id');

// Get sports for filter dropdown
$sports_query = "SELECT * FROM sports ORDER BY name";
$sports_stmt = $db->prepare($sports_query);
$sports_stmt->execute();
$sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured/recommended leagues
$featured_query = "SELECT l.*, s.name as sport_name,
                          (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as team_count
                   FROM leagues l 
                   JOIN sports s ON l.sport_id = s.id 
                   WHERE l.status = 'open' 
                   AND l.registration_deadline >= CURDATE()
                   ORDER BY team_count DESC, l.created_at DESC 
                   LIMIT 3";
$featured_stmt = $db->prepare($featured_query);
$featured_stmt->execute();
$featured_leagues = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Leagues - Sports League Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, purple);
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
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
            margin-bottom: 2rem;
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
        
        .featured-section {
            margin-bottom: 3rem;
        }
        
        .featured-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .featured-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .featured-card:hover {
            transform: translateY(-5px);
        }
        
        .featured-card::before {
            content: '⭐';
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            opacity: 0.7;
        }
        
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .filter-select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            background: white;
            min-width: 120px;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
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
        
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.8rem; }
        
        .leagues-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
        }
        
        .league-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .league-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        
        .league-header {
            background: linear-gradient(135deg, #343a40, #495057);
            color: white;
            padding: 1.5rem;
            position: relative;
        }
        
        .participation-badge {
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
        
        .league-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .league-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-open { background: #28a745; color: white; }
        .status-active { background: #007bff; color: white; }
        .status-closed { background: #ffc107; color: black; }
        .status-completed { background: #6c757d; color: white; }
        
        .league-body {
            padding: 1.5rem;
        }
        
        .league-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stat-number {
            font-size: 1.4rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .league-info {
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
        
        .progress-section {
            margin-bottom: 1.5rem;
        }
        
        .progress-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .deadline-warning {
            background: #fff3cd;
            color: #856404;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            border-left: 4px solid #ffc107;
        }
        
        .deadline-urgent {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        
        .league-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .quick-join-form {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 1rem;
            border: 2px dashed #dee2e6;
        }
        
        .quick-join-form input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 2rem;
        }
        
        .filter-tags {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-tag {
            background: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-tag.active {
            background: #007bff;
            color: white;
        }
        
        .filter-tag .remove {
            cursor: pointer;
            font-weight: bold;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
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
        
        @media (max-width: 768px) {
            .header { padding: 1.5rem 1rem; }
            .header h1 { font-size: 2rem; }
            .container { padding: 0 1rem; }
            .leagues-grid { grid-template-columns: 1fr; }
            .featured-grid { grid-template-columns: 1fr; }
            .filters-row { flex-direction: column; align-items: stretch; }
            .search-box { min-width: auto; }
            .league-stats { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>🏆 Discover Sports Leagues in Labangon</h1>
            <p>Find and join exciting sports leagues in your area</p>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <div class="nav-breadcrumb">
        <div class="breadcrumb">
            <a href="../dashboard.php">Dashboard</a>
            <span>›</span>
            <span>Browse Leagues</span>
        </div>
    </div>
    
    <div class="container">
        <!-- Display messages -->
        <?php displayMessage(); ?>
        
        <!-- Featured Leagues -->
        <?php if (count($featured_leagues) > 0 && empty($search) && empty($sport_filter) && $status_filter == 'open'): ?>
        <div class="featured-section">
            <h2 class="featured-title">⭐ Featured Leagues</h2>
            <div class="featured-grid">
                <?php foreach ($featured_leagues as $featured): ?>
                <div class="featured-card">
                    <h3><?php echo htmlspecialchars($featured['name']); ?></h3>
                    <p><?php echo htmlspecialchars($featured['sport_name']); ?> • <?php echo htmlspecialchars($featured['season'] ?? 'Current Season'); ?></p>
                    <p style="margin-top: 1rem; opacity: 0.9;">
                        <strong><?php echo $featured['team_count']; ?></strong> teams registered
                    </p>
                    <div style="margin-top: 1.5rem;">
                        <a href="view_league.php?id=<?php echo $featured['id']; ?>" class="btn btn-primary">
                            🎯 Join This League
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" id="filterForm">
                <div class="filters-row">
                    <input type="text" 
                           name="search" 
                           class="search-box" 
                           placeholder="🔍 Search leagues by name, season, or sport..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           onkeyup="debounceSearch(this.value)">
                    
                    <select name="sport" class="filter-select" onchange="applyFilters()">
                        <option value="">🏃 All Sports</option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo $sport['id']; ?>" <?php echo $sport_filter == $sport['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sport['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="filter-select" onchange="applyFilters()">
                        <option value="">📊 All Statuses</option>
                        <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>🟢 Open for Registration</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>🔵 Currently Active</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                        <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>🔒 Closed</option>
                    </select>
                    
                    <select name="sort" class="filter-select" onchange="applyFilters()">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>📅 Newest First</option>
                        <option value="deadline" <?php echo $sort == 'deadline' ? 'selected' : ''; ?>>⏰ By Deadline</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>🔥 Most Popular</option>
                        <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>📝 Alphabetical</option>
                    </select>
                    
                    <button type="button" onclick="clearFilters()" class="btn btn-secondary">
                        🔄 Clear
                    </button>
                </div>
            </form>
            
            <!-- Active Filters Display -->
            <?php if ($search || $sport_filter || $status_filter != 'open' || $sort != 'newest'): ?>
            <div class="filter-tags">
                <span style="font-weight: 600; color: #666;">Active Filters:</span>
                <?php if ($search): ?>
                    <span class="filter-tag active">
                        🔍 "<?php echo htmlspecialchars($search); ?>"
                        <span class="remove" onclick="removeFilter('search')">&times;</span>
                    </span>
                <?php endif; ?>
                <?php if ($sport_filter): ?>
                    <span class="filter-tag active">
                        🏃 <?php echo htmlspecialchars(array_filter($sports, function($s) use ($sport_filter) { return $s['id'] == $sport_filter; })[0]['name'] ?? 'Unknown'); ?>
                        <span class="remove" onclick="removeFilter('sport')">&times;</span>
                    </span>
                <?php endif; ?>
                <?php if ($status_filter && $status_filter != 'open'): ?>
                    <span class="filter-tag active">
                        📊 <?php echo ucfirst($status_filter); ?>
                        <span class="remove" onclick="removeFilter('status')">&times;</span>
                    </span>
                <?php endif; ?>
                <?php if ($sort != 'newest'): ?>
                    <span class="filter-tag active">
                        📈 <?php echo ucfirst($sort); ?>
                        <span class="remove" onclick="removeFilter('sort')">&times;</span>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Summary -->
        <div style="margin-bottom: 1.5rem; color: #666;">
            <strong><?php echo count($leagues); ?></strong> leagues found
            <?php if ($search): ?>
                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
            <?php endif; ?>
        </div>
        
        <!-- Leagues Grid -->
        <?php if (count($leagues) > 0): ?>
        <div class="leagues-grid">
            <?php foreach ($leagues as $league): ?>
            <div class="league-card">
                <?php if (in_array($league['id'], $user_league_participations)): ?>
                    <div class="participation-badge">✅ Joined</div>
                <?php endif; ?>
                
                <div class="league-header">
                    <div class="league-title"><?php echo htmlspecialchars($league['name']); ?></div>
                    <div class="league-subtitle">
                        <span><?php echo htmlspecialchars($league['sport_name']); ?></span>

                          </div>
                    <div class="status-indicator status-<?php echo htmlspecialchars($league['status']); ?>">
                        <?php echo ucfirst($league['status']); ?>
                    </div>
                </div>
                <div class="league-body">
                    <!-- League Stats -->
                    <div class="league-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $league['team_count']; ?></div>
                            <div class="stat-label">Teams</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $league['total_matches']; ?></div>
                            <div class="stat-label">Matches</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $league['completed_matches']; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                    
                    <!-- League Info -->
                    <div class="league-info">
                        <div class="info-row">
                            <div class="info-label">Created by:</div>
                            <div class="info-value"><?php echo htmlspecialchars($league['creator_first_name'] . ' ' . $league['creator_last_name']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Registration Deadline:</div>
                            <div class="info-value"><?php echo htmlspecialchars($league['registration_deadline']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Season:</div>
                            <div class="info-value"><?php echo htmlspecialchars($league['season']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value"><?php echo ucfirst($league['status']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Progress & Actions -->
                    <div class="progress-section">
                        <div class="progress-label">
                            <span>Registration Progress</span>
                            <span><?php echo $league['team_count']; ?> / <?php echo $league['max_teams']; ?> teams</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($league['team_count'] / $league['max_teams']) * 100); ?>%;"></div>
                        </div>
                    </div>
                    
                    <div class="league-actions">
                        <a href="view_league.php?id=<?php echo $league['id']; ?>" class="btn btn-info">Details</a>
                        <?php if ($league['status'] == 'open'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="league_id" value="<?php echo $league['id']; ?>">
                                <input type="hidden" name="action" value="quick_create_team">
                                <input type="text" name="team_name" placeholder="Your Team Name" required>
                                <button type="submit" class="btn btn-success btn-sm">Create Team</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!in_array($league['id'], $user_league_participations) && $league['status'] == 'open'): ?>
                            <a href="join_league.php?id=<?php echo $league['id']; ?>" class="btn btn-primary">Join League</a>
                        <?php elseif (in_array($league['id'], $user_league_participations)): ?>
                            <span class="btn btn-secondary disabled">Joined</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No leagues found</h3>
                <p>Try adjusting your filters or search to find leagues that interest you.</p>
                <a href="browse_leagues.php" class="btn btn-primary">Reset Filters</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Scripts for filters and interactions -->
    <script>
        function applyFilters() {
            document.getElementById('filterForm').submit();
        }

        function clearFilters() {
            window.location.href = 'browse_leagues.php';
        }

        function removeFilter(filterType) {
            const url = new URL(window.location.href);
            if (filterType === 'search') {
                url.searchParams.delete('search');
            } else if (filterType === 'sport') {
                url.searchParams.delete('sport');
            } else if (filterType === 'status') {
                url.searchParams.delete('status');
            } else if (filterType === 'sort') {
                url.searchParams.delete('sort');
            }
            window.location.href = url.toString();
        }

        // Debounce search input
        let debounceTimer;
        function debounceSearch(value) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                document.querySelector('input[name="search"]').value = value;
                applyFilters();
            }, 500);
        }
    </script>
</body>
</html>