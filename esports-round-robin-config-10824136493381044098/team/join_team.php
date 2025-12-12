<?php
// team/join_team.php - Unified Join Team System with Full Registration Details
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Handle join request submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['send_request'])) {
        $team_id = $_POST['team_id'];
        $full_name = trim($_POST['full_name']);
        $current_address = trim($_POST['current_address']);
        $sitio = trim($_POST['sitio']);
        $age = intval($_POST['age']);
        $birthday = $_POST['birthday'];
        $preferred_position = trim($_POST['preferred_position']);
        $message = trim($_POST['message']);
        
        // Handle file upload for PSA/NSO
        $document_path = null;
        if (isset($_FILES['psa_document']) && $_FILES['psa_document']['error'] == 0) {
            $upload_dir = '../uploads/documents/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['psa_document']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $new_filename = 'psa_' . $current_user['id'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['psa_document']['tmp_name'], $upload_path)) {
                    $document_path = 'uploads/documents/' . $new_filename;
                } else {
                    $error = "Failed to upload document!";
                }
            } else {
                $error = "Invalid file type. Only PDF, JPG, JPEG, and PNG are allowed!";
            }
        } else {
            $error = "PSA/NSO document is required!";
        }
        
        if (!isset($error)) {
            // Check if user already has a pending request for this team
            $check_query = "SELECT id FROM registration_requests 
                            WHERE player_id = :player_id AND team_id = :team_id AND status = 'pending'";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':player_id', $current_user['id']);
            $check_stmt->bindParam(':team_id', $team_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "You already have a pending request for this team!";
            } else {
                // Check if user is already a member of this team
                $member_check = "SELECT id FROM team_members 
                                WHERE player_id = :player_id AND team_id = :team_id AND status = 'active'";
                $member_stmt = $db->prepare($member_check);
                $member_stmt->bindParam(':player_id', $current_user['id']);
                $member_stmt->bindParam(':team_id', $team_id);
                $member_stmt->execute();
                
                if ($member_stmt->rowCount() > 0) {
                    $error = "You are already a member of this team!";
                } else {
                    // Get league_id for the team
                    $team_query = "SELECT league_id FROM teams WHERE id = :team_id";
                    $team_stmt = $db->prepare($team_query);
                    $team_stmt->bindParam(':team_id', $team_id);
                    $team_stmt->execute();
                    $team = $team_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $insert_query = "INSERT INTO registration_requests 
                                    (player_id, team_id, league_id, full_name, current_address, sitio, 
                                     age, birthday, preferred_position, message, document_path, created_at) 
                                    VALUES (:player_id, :team_id, :league_id, :full_name, :current_address, 
                                            :sitio, :age, :birthday, :preferred_position, :message, :document_path, NOW())";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':player_id', $current_user['id']);
                    $insert_stmt->bindParam(':team_id', $team_id);
                    $insert_stmt->bindParam(':league_id', $team['league_id']);
                    $insert_stmt->bindParam(':full_name', $full_name);
                    $insert_stmt->bindParam(':current_address', $current_address);
                    $insert_stmt->bindParam(':sitio', $sitio);
                    $insert_stmt->bindParam(':age', $age);
                    $insert_stmt->bindParam(':birthday', $birthday);
                    $insert_stmt->bindParam(':preferred_position', $preferred_position);
                    $insert_stmt->bindParam(':message', $message);
                    $insert_stmt->bindParam(':document_path', $document_path);
                    
                    if ($insert_stmt->execute()) {
                        // Create notification for team owner
                        $owner_query = "SELECT owner_id FROM teams WHERE id = :team_id";
                        $owner_stmt = $db->prepare($owner_query);
                        $owner_stmt->bindParam(':team_id', $team_id);
                        $owner_stmt->execute();
                        $owner = $owner_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($owner) {
                            $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                                                  VALUES (:user_id, 'New Team Join Request', 
                                                  :message, 'info')";
                            $notification_stmt = $db->prepare($notification_query);
                            $notification_stmt->bindParam(':user_id', $owner['owner_id']);
                            $notif_message = $current_user['first_name'] . ' ' . $current_user['last_name'] . ' wants to join your team!';
                            $notification_stmt->bindParam(':message', $notif_message);
                            $notification_stmt->execute();
                        }
                        
                        showMessage("Join request sent successfully! The team owner will review your request.", "success");
                        redirect('join_team.php');
                    } else {
                        $error = "Failed to send join request!";
                    }
                }
            }
        }
    }
    
    if (isset($_POST['cancel_request'])) {
        $request_id = $_POST['request_id'];
        
        // Delete the pending request
        $cancel_query = "DELETE FROM registration_requests WHERE id = :id AND player_id = :player_id";
        $cancel_stmt = $db->prepare($cancel_query);
        $cancel_stmt->bindParam(':id', $request_id);
        $cancel_stmt->bindParam(':player_id', $current_user['id']);
        
        if ($cancel_stmt->execute()) {
            showMessage("Join request cancelled successfully!", "success");
        } else {
            showMessage("Failed to cancel request!", "error");
        }
    }
}

// Get filter parameters
$league_filter = $_GET['league'] ?? '';
$sport_filter = $_GET['sport'] ?? '';
$search = $_GET['search'] ?? '';

// Build query conditions
$where_conditions = ["l.status IN ('open', 'active')"];
$params = [];

if ($search) {
    $where_conditions[] = "(t.name LIKE :search OR t.description LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($league_filter) {
    $where_conditions[] = "t.league_id = :league_id";
    $params[':league_id'] = $league_filter;
}

if ($sport_filter) {
    $where_conditions[] = "s.id = :sport_id";
    $params[':sport_id'] = $sport_filter;
}

// Exclude teams user already owns or is member of
$where_conditions[] = "t.owner_id != :user_id";
$where_conditions[] = "t.id NOT IN (
    SELECT team_id FROM team_members WHERE player_id = :user_id AND status = 'active'
)";
$params[':user_id'] = $current_user['id'];

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get available teams
$teams_query = "SELECT t.*, l.name as league_name, l.season, l.status as league_status, 
                       l.registration_deadline, l.max_teams,
                       s.name as sport_name, s.max_players_per_team,
                       u.first_name as owner_first_name, u.last_name as owner_last_name, u.username as owner_username,
                       (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count,
                       (SELECT COUNT(*) FROM registration_requests WHERE team_id = t.id AND status = 'pending') as pending_requests,
                       (SELECT COUNT(*) FROM registration_requests WHERE team_id = t.id AND player_id = :user_id AND status = 'pending') as user_has_pending
                FROM teams t
                JOIN leagues l ON t.league_id = l.id
                JOIN sports s ON l.sport_id = s.id
                JOIN users u ON t.owner_id = u.id
                $where_clause
                ORDER BY l.status DESC, t.created_at DESC";

$teams_stmt = $db->prepare($teams_query);
foreach ($params as $key => $value) {
    $teams_stmt->bindValue($key, $value);
}
$teams_stmt->execute();
$available_teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's pending requests for display
$pending_requests_query = "SELECT rr.*, t.name as team_name, l.name as league_name, l.season, s.name as sport_name
                           FROM registration_requests rr
                           JOIN teams t ON rr.team_id = t.id
                           JOIN leagues l ON rr.league_id = l.id
                           JOIN sports s ON l.sport_id = s.id
                           WHERE rr.player_id = :user_id AND rr.status = 'pending'
                           ORDER BY rr.created_at DESC";
$pending_requests_stmt = $db->prepare($pending_requests_query);
$pending_requests_stmt->bindParam(':user_id', $current_user['id']);
$pending_requests_stmt->execute();
$user_pending_requests = $pending_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get leagues and sports for filters
$leagues_query = "SELECT l.*, s.name as sport_name FROM leagues l 
                  JOIN sports s ON l.sport_id = s.id 
                  WHERE l.status IN ('open', 'active') 
                  ORDER BY l.name";
$leagues_stmt = $db->prepare($leagues_query);
$leagues_stmt->execute();
$leagues = $leagues_stmt->fetchAll(PDO::FETCH_ASSOC);

$sports_query = "SELECT * FROM sports ORDER BY name";
$sports_stmt = $db->prepare($sports_query);
$sports_stmt->execute();
$sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's current teams count
$user_teams_query = "SELECT COUNT(*) FROM team_members WHERE player_id = :user_id AND status = 'active'";
$user_teams_stmt = $db->prepare($user_teams_query);
$user_teams_stmt->bindParam(':user_id', $current_user['id']);
$user_teams_stmt->execute();
$user_teams_count = $user_teams_stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join a Team - Sports League Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; text-align: center; }
        .header h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; }
        
        .nav-breadcrumb { background: white; padding: 1rem 2rem; border-bottom: 1px solid #e0e0e0; }
        .breadcrumb { color: #666; font-size: 0.9rem; }
        .breadcrumb a { color: #667eea; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        
        .user-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .user-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .stat-item { background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; margin-top: 0.5rem; }
        
        .tips-section { background: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .tips-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .tip-item { text-align: center; }
        .tip-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .tip-item h4 { color: #333; margin-bottom: 0.5rem; }
        .tip-item p { color: #666; font-size: 0.9rem; }
        
        .pending-requests-section { background: #fff3cd; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; border-left: 4px solid #ffc107; }
        .request-card { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .request-header { display: flex; justify-content: space-between; align-items: center; }
        .request-team { font-weight: bold; color: #333; }
        .request-team small { display: block; font-weight: normal; color: #666; margin-top: 0.25rem; }
        
        .filters-section { background: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filters-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        
        .teams-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .teams-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 1rem; }
        .team-card { background: white; border: 1px solid #e0e0e0; border-radius: 12px; padding: 1.5rem; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .team-card:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .team-header { margin-bottom: 1rem; }
        .team-name { color: #333; font-size: 1.25rem; margin-bottom: 0.5rem; }
        .team-league { color: #666; font-size: 0.9rem; }
        .team-sport { background: #667eea; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; display: inline-block; }
        .team-description { color: #555; font-size: 0.9rem; margin: 1rem 0; line-height: 1.5; }
        .team-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin: 1rem 0; padding: 1rem 0; border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; }
        .team-stat { text-align: center; }
        .team-stat-number { font-size: 1.5rem; font-weight: bold; color: #667eea; }
        .team-stat-label { font-size: 0.8rem; color: #666; margin-top: 0.25rem; }
        .team-owner { color: #666; font-size: 0.9rem; margin: 1rem 0; }
        
        .join-btn { width: 100%; padding: 0.75rem; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; transition: all 0.3s; }
        .join-btn:hover:not(:disabled) { background: #218838; transform: translateY(-2px); }
        .join-btn:disabled { background: #6c757d; cursor: not-allowed; opacity: 0.6; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; font-weight: 500; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); overflow-y: auto; }
        .modal-content { background: white; margin: 2% auto; padding: 2rem; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #999; }
        .close:hover { color: #333; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333; }
        .required { color: #dc3545; }
        .form-group input, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        
        .file-input-wrapper { position: relative; }
        .file-input-label { display: block; padding: 1rem; background: #f8f9fa; border: 2px dashed #ddd; border-radius: 6px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .file-input-label:hover { background: #e9ecef; border-color: #667eea; }
        .file-input-label.has-file { background: #d4edda; border-color: #28a745; border-style: solid; }
        input[type="file"] { display: none; }
        .file-info { font-size: 0.85rem; color: #666; margin-top: 0.5rem; }
        
        .empty-state { text-align: center; padding: 3rem; color: #666; }
        .empty-state-icon { font-size: 4rem; margin-bottom: 1rem; }
        .empty-state h3 { color: #333; margin-bottom: 0.5rem; }
        
        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        @media (max-width: 768px) {
            .teams-grid { grid-template-columns: 1fr; }
            .filters-form { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Join a Team</h1>
        <p>Find the perfect team in Barangay Labangon, Cebu City to showcase your skills and passion for sports.</p>

    </div>
    
    <!-- Breadcrumb -->
    <div class="nav-breadcrumb">
        <div class="breadcrumb">
            <a href="../dashboard.php">Dashboard</a>
            <span> › </span>
            <a href="my_teams.php">My Teams</a>
            <span> › </span>
            <span>Join Team</span>
        </div>
    </div>
    
    <div class="container">
        <!-- Display messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php displayMessage(); ?>
        
        <!-- User Info Section -->
        <div class="user-info">
            <h3 style="margin-bottom: 1rem;">
                Welcome, <?php echo htmlspecialchars($current_user['first_name']); ?>!
            </h3>
            <p style="margin-bottom: 1rem;">
                Ready to join a team? Browse available teams below and send join requests to team owners.
            </p>
            
            <div class="user-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $user_teams_count; ?></div>
                    <div class="stat-label">Current Teams</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($user_pending_requests); ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($available_teams); ?></div>
                    <div class="stat-label">Available Teams</div>
                </div>
            </div>
        </div>
        
        <!-- Tips Section -->
        <div class="tips-section">
            <h3 style="margin-bottom: 1rem;">Tips for Joining Teams</h3>
            <div class="tips-grid">
                <div class="tip-item">
                    <div class="tip-icon">🎯</div>
                    <h4>Be Specific</h4>
                    <p>Mention your preferred position and playing experience in your message.</p>
                </div>
                <div class="tip-item">
                    <div class="tip-icon">🤝</div>
                    <h4>Be Respectful</h4>
                    <p>Write a polite message explaining why you want to join their team.</p>
                </div>
                <div class="tip-item">
                    <div class="tip-icon">⚡</div>
                    <h4>Act Fast</h4>
                    <p>Popular teams fill up quickly, so send your requests promptly.</p>
                </div>
                <div class="tip-item">
                    <div class="tip-icon">📈</div>
                    <h4>Show Commitment</h4>
                    <p>Demonstrate your dedication to the sport and team success.</p>
                </div>
            </div>
        </div>
        
        <!-- Pending Requests Section -->
        <?php if (count($user_pending_requests) > 0): ?>
        <div class="pending-requests-section">
            <h3 style="margin-bottom: 1rem; color: #856404;">Your Pending Requests (<?php echo count($user_pending_requests); ?>)</h3>
            
            <?php foreach ($user_pending_requests as $request): ?>
            <div class="request-card">
                <div class="request-header">
                    <div class="request-team">
                        <?php echo htmlspecialchars($request['team_name']); ?>
                        <small><?php echo htmlspecialchars($request['league_name']); ?> - <?php echo htmlspecialchars($request['season']); ?></small>
                    </div>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <button type="submit" name="cancel_request" class="btn btn-danger btn-sm" 
                                onclick="return confirm('Are you sure you want to cancel this request?')">Cancel</button>
                    </form>
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #555;">
                    <strong>Position:</strong> <?php echo htmlspecialchars($request['preferred_position']); ?><br>
                    <?php if ($request['message']): ?>
                    <strong>Message:</strong> <?php echo htmlspecialchars($request['message']); ?><br>
                    <?php endif; ?>
                    <strong>Requested on:</strong> <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <h3 style="margin-bottom: 1rem;">Search & Filter Teams</h3>
            <form method="get" class="filters-form">
                <div class="form-group">
                    <label for="search">Search Teams</label>
                    <input type="text" id="search" name="search" placeholder="Team name, description, or owner..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label for="league">League</label>
                    <select id="league" name="league">
                        <option value="">All Leagues</option>
                        <?php foreach ($leagues as $league): ?>
                        <option value="<?php echo $league['id']; ?>" <?php echo ($league_filter == $league['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($league['name']); ?> (<?php echo htmlspecialchars($league['sport_name']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sport">Sport</label>
                    <select id="sport" name="sport">
                        <option value="">All Sports</option>
                        <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>" <?php echo ($sport_filter == $sport['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sport['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="join_team.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Clear</a>
                </div>
            </form>
        </div>

        <!-- Available Teams Section -->
        <div class="teams-section">
            <h3 style="margin-bottom: 1rem;">Available Teams (<?php echo count($available_teams); ?>)</h3>
            
            <?php if (count($available_teams) > 0): ?>
            <div class="teams-grid">
                <?php foreach ($available_teams as $team): ?>
                <div class="team-card">
                    <?php 
                    // Determine team status
                    $is_full = $team['member_count'] >= $team['max_players_per_team'];
                    $has_pending = $team['user_has_pending'] > 0;
                    $deadline_passed = strtotime($team['registration_deadline']) < time();
                    ?>
                    
                    <div class="team-header">
                        <div>
                            <h4 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h4>
                            <div class="team-league">
                                <?php echo htmlspecialchars($team['league_name']); ?> - <?php echo htmlspecialchars($team['season']); ?>
                            </div>
                        </div>
                        <div class="team-sport">
                            <?php echo htmlspecialchars($team['sport_name']); ?>
                        </div>
                    </div>
                    
                    <?php if ($team['description']): ?>
                    <div class="team-description">
                        <?php echo htmlspecialchars(substr($team['description'], 0, 150)); ?>
                        <?php if (strlen($team['description']) > 150): ?>...<?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="team-stats">
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['member_count']; ?>/<?php echo $team['max_players_per_team']; ?></div>
                            <div class="team-stat-label">Players</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-number"><?php echo $team['pending_requests']; ?></div>
                            <div class="team-stat-label">Pending</div>
                        </div>
                        <div class="team-stat">
                            <div class="team-stat-number">
                                <?php echo date('M j', strtotime($team['registration_deadline'])); ?>
                            </div>
                            <div class="team-stat-label">Deadline</div>
                        </div>
                    </div>
                    
                    <div class="team-owner">
                        <strong>Owner:</strong> <?php echo htmlspecialchars($team['owner_first_name'] . ' ' . $team['owner_last_name']); ?>
                        <small>(@<?php echo htmlspecialchars($team['owner_username']); ?>)</small>
                    </div>
                    
                    <?php if ($has_pending): ?>
                        <button class="join-btn" disabled>
                            Request Pending
                        </button>
                    <?php elseif ($is_full): ?>
                        <button class="join-btn" disabled>
                            Team Full
                        </button>
                    <?php elseif ($deadline_passed): ?>
                        <button class="join-btn" disabled>
                            Registration Closed
                        </button>
                    <?php else: ?>
                        <button class="join-btn" onclick="openJoinModal(
                            <?php echo $team['id']; ?>, 
                            '<?php echo addslashes(htmlspecialchars($team['name'])); ?>', 
                            '<?php echo addslashes(htmlspecialchars($team['league_name'])); ?>', 
                            '<?php echo addslashes(htmlspecialchars($team['sport_name'])); ?>'
                        )">
                            Send Join Request
                        </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <h3>No Teams Found</h3>
                <p>
                    <?php if ($search || $league_filter || $sport_filter): ?>
                        No teams match your search criteria. Try adjusting your filters or <a href="join_team.php">clear all filters</a>.
                    <?php else: ?>
                        There are no teams available for you to join at the moment. Check back later or <a href="create_team.php">create your own team</a>!
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Join Request Modal -->
    <div id="joinModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeJoinModal()">&times;</span>
            <h3 style="margin-bottom: 1.5rem;">Send Join Request</h3>
            
            <div id="teamInfo" style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
                <!-- Team info will be populated by JavaScript -->
            </div>
            
            <form method="post" id="joinForm" enctype="multipart/form-data">
                <input type="hidden" id="team_id" name="team_id" value="">
                
                <div class="form-group">
                    <label>Full Name: <span class="required">*</span></label>
                    <input type="text" name="full_name" required placeholder="Complete name as per birth certificate">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Birthday: <span class="required">*</span></label>
                        <input type="date" id="birthday" name="birthday" required onchange="calculateAge()">
                    </div>
                    
                    <div class="form-group">
                        <label>Age: <span class="required">*</span></label>
                        <input type="number" id="age" name="age" required readonly style="background: #f8f9fa;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Current Address: <span class="required">*</span></label>
                    <input type="text" name="current_address" required placeholder="House No., Street, Barangay, City">
                </div>
                
                <div class="form-group">
                    <label>Sitio/Purok: <span class="required">*</span></label>
                    <input type="text" name="sitio" required placeholder="Your sitio or purok">
                </div>
                
                <div class="form-group">
                    <label>Preferred Position: <span class="required">*</span></label>
                    <input type="text" name="preferred_position" required placeholder="e.g., Forward, Defender, Midfielder">
                </div>
                
                <div class="form-group">
                    <label>PSA/NSO Birth Certificate: <span class="required">*</span></label>
                    <div class="file-input-wrapper">
                        <label id="file-label" for="psa_document" class="file-input-label">
                            Click to upload PSA/NSO Birth Certificate (PDF, JPG, PNG)
                        </label>
                        <input type="file" id="psa_document" name="psa_document" accept=".pdf,.jpg,.jpeg,.png" required onchange="updateFileLabel()">
                        <div id="file-info" class="file-info">Maximum file size: 5MB</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Message to Team Owner:</label>
                    <textarea name="message" rows="4" placeholder="Tell them why you'd like to join their team..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeJoinModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" name="send_request" class="btn btn-primary">
                        Send Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        function openJoinModal(teamId, teamName, leagueName, sportName) {
            document.getElementById('team_id').value = teamId;
            document.getElementById('teamInfo').innerHTML = `
                <strong>Team:</strong> ${teamName}<br>
                <strong>League:</strong> ${leagueName}<br>
                <strong>Sport:</strong> ${sportName}
            `;
            document.getElementById('joinModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeJoinModal() {
            document.getElementById('joinModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('joinForm').reset();
            updateFileLabel();
        }
        
        function updateFileLabel() {
            const fileInput = document.getElementById('psa_document');
            const label = document.getElementById('file-label');
            const fileInfo = document.getElementById('file-info');
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                label.textContent = '✓ ' + file.name;
                label.classList.add('has-file');
                fileInfo.textContent = 'File size: ' + (file.size / 1024).toFixed(2) + ' KB';
            } else {
                label.textContent = 'Click to upload PSA/NSO Birth Certificate (PDF, JPG, PNG)';
                label.classList.remove('has-file');
                fileInfo.textContent = 'Maximum file size: 5MB';
            }
        }
        
        function calculateAge() {
            const birthday = document.getElementById('birthday').value;
            if (birthday) {
                const today = new Date();
                const birthDate = new Date(birthday);
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                document.getElementById('age').value = age;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('joinModal');
            if (event.target == modal) {
                closeJoinModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeJoinModal();
            }
        });
        
        // Form validation
        document.getElementById('joinForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('psa_document');
            
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // Convert to MB
                if (fileSize > 5) {
                    alert('File size exceeds 5MB. Please choose a smaller file.');
                    e.preventDefault();
                    return;
                }
            }
        });
        
        // Auto-hide success/error messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>