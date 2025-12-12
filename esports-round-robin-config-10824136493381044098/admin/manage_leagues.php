<?php
// admin/manage_leagues.php - Complete League Management with Team Approval System
require_once '../config/database.php';
requireRole('admin');

$database = new Database();
$db = $database->connect();

// Handle approval actions
if (isset($_POST['approval_action']) && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['approval_action'];
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    $current_user = getCurrentUser();
    
    if ($action === 'approve') {
    // Get request details
    $req_query = "SELECT * FROM team_registration_requests WHERE id = :id AND status = 'pending'";
    $req_stmt = $db->prepare($req_query);
    $req_stmt->bindParam(':id', $request_id);
    $req_stmt->execute();
    $request = $req_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        try {
            $db->beginTransaction();
            
            // Create the team
           $create_team = "INSERT INTO teams (name, league_id, owner_id, created_at) 
               VALUES (:name, :league_id, :owner_id, NOW())";
            $team_stmt = $db->prepare($create_team);
            $team_stmt->bindParam(':name', $request['team_name']);
            $team_stmt->bindParam(':league_id', $request['league_id']);
            $team_stmt->bindParam(':owner_id', $request['team_owner_id']);
            $team_stmt->execute();
            
            // Get the new team ID
            $new_team_id = $db->lastInsertId();
            
            // Add owner as team member
           // Add owner as team member
$member_query = "INSERT INTO team_members (team_id, player_id, position, joined_at, status) 
                VALUES (:team_id, :player_id, 'Owner', NOW(), 'active')";
$member_stmt = $db->prepare($member_query);
$member_stmt->bindParam(':team_id', $new_team_id);
$member_stmt->bindParam(':player_id', $request['team_owner_id']);
$member_stmt->execute();
            
            // Update request status
            $update_req = "UPDATE team_registration_requests 
                          SET status = 'approved', reviewed_at = NOW(), 
                              reviewed_by = :admin_id, admin_notes = :notes 
                          WHERE id = :id";
            $update_stmt = $db->prepare($update_req);
            $update_stmt->bindParam(':admin_id', $current_user['id']);
            $update_stmt->bindParam(':notes', $admin_notes);
            $update_stmt->bindParam(':id', $request_id);
            $update_stmt->execute();
            
            $db->commit();
            showMessage("Team registration approved successfully! Team has been created.", "success");
        } catch (Exception $e) {
            $db->rollBack();
            showMessage("Failed to approve registration: " . $e->getMessage(), "error");
        }
    }
}elseif ($action === 'reject') {
        $update_req = "UPDATE team_registration_requests 
                      SET status = 'rejected', reviewed_at = NOW(), 
                          reviewed_by = :admin_id, admin_notes = :notes 
                      WHERE id = :id";
        $update_stmt = $db->prepare($update_req);
        $update_stmt->bindParam(':admin_id', $current_user['id']);
        $update_stmt->bindParam(':notes', $admin_notes);
        $update_stmt->bindParam(':id', $request_id);
        
        if ($update_stmt->execute()) {
            showMessage("Team registration rejected.", "success");
        } else {
            showMessage("Failed to reject registration!", "error");
        }
    }
}

// Handle league actions
if (isset($_POST['action']) && isset($_POST['league_id'])) {
    $league_id = $_POST['league_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'activate':
            $update_query = "UPDATE leagues SET status = 'active' WHERE id = :id";
            $success_msg = "League activated successfully!";
            break;
            
        case 'close':
            $update_query = "UPDATE leagues SET status = 'closed' WHERE id = :id";
            $success_msg = "League closed successfully!";
            break;
            
        case 'complete':
            $update_query = "UPDATE leagues SET status = 'completed' WHERE id = :id";
            $success_msg = "League marked as completed!";
            break;
            
        case 'reopen':
            $update_query = "UPDATE leagues SET status = 'open' WHERE id = :id";
            $success_msg = "League reopened for registration!";
            break;
            
        case 'delete_league':
            $check_query = "SELECT 
                            (SELECT COUNT(*) FROM teams WHERE league_id = :id) as team_count,
                            (SELECT COUNT(*) FROM matches WHERE league_id = :id) as match_count";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':id', $league_id);
            $check_stmt->execute();
            $league_activity = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($league_activity['team_count'] > 0 || $league_activity['match_count'] > 0) {
                showMessage("Cannot delete league - it has active teams or scheduled matches!", "error");
                $update_query = null;
            } else {
                $update_query = "DELETE FROM leagues WHERE id = :id";
                $success_msg = "League deleted successfully!";
            }
            break;
            
        default:
            $update_query = null;
    }
    
    if ($update_query) {
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $league_id);
        
        if ($update_stmt->execute()) {
            showMessage($success_msg, "success");
        } else {
            showMessage("Failed to update league!", "error");
        }
    }
}

// Handle league creation
if (isset($_POST['create_league'])) {
    $name = trim($_POST['name']);
    $sport_id = $_POST['sport_id'];
    $season = trim($_POST['season']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $registration_deadline = $_POST['registration_deadline'];
    $max_teams = $_POST['max_teams'];
    $rules = trim($_POST['rules']);
    $status = $_POST['status'] ?? 'draft';
    $approval_required = isset($_POST['approval_required']) ? 1 : 0;
    $created_by = getCurrentUser()['id'];
    
    $create_query = "INSERT INTO leagues (name, sport_id, season, start_date, end_date, registration_deadline, 
                    max_teams, rules, status, approval_required, created_by) 
                    VALUES (:name, :sport_id, :season, :start_date, :end_date, :registration_deadline, 
                    :max_teams, :rules, :status, :approval_required, :created_by)";
    $create_stmt = $db->prepare($create_query);
    $create_stmt->bindParam(':name', $name);
    $create_stmt->bindParam(':sport_id', $sport_id);
    $create_stmt->bindParam(':season', $season);
    $create_stmt->bindParam(':start_date', $start_date);
    $create_stmt->bindParam(':end_date', $end_date);
    $create_stmt->bindParam(':registration_deadline', $registration_deadline);
    $create_stmt->bindParam(':max_teams', $max_teams);
    $create_stmt->bindParam(':rules', $rules);
    $create_stmt->bindParam(':status', $status);
    $create_stmt->bindParam(':approval_required', $approval_required);
    $create_stmt->bindParam(':created_by', $created_by);
    
    if ($create_stmt->execute()) {
        showMessage("League created successfully!", "success");
    } else {
        showMessage("Failed to create league!", "error");
    }
}

// Handle league editing
if (isset($_POST['edit_league']) && isset($_POST['edit_league_id'])) {
    $league_id = $_POST['edit_league_id'];
    $name = trim($_POST['edit_name']);
    $sport_id = $_POST['edit_sport_id'];
    $season = trim($_POST['edit_season']);
    $start_date = $_POST['edit_start_date'];
    $end_date = $_POST['edit_end_date'];
    $registration_deadline = $_POST['edit_registration_deadline'];
    $max_teams = $_POST['edit_max_teams'];
    $rules = trim($_POST['edit_rules']);
    $status = $_POST['edit_status'];
    $approval_required = isset($_POST['edit_approval_required']) ? 1 : 0;
    
    $edit_query = "UPDATE leagues SET name = :name, sport_id = :sport_id, season = :season, 
                   start_date = :start_date, end_date = :end_date, registration_deadline = :registration_deadline, 
                   max_teams = :max_teams, rules = :rules, status = :status, approval_required = :approval_required 
                   WHERE id = :id";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->bindParam(':name', $name);
    $edit_stmt->bindParam(':sport_id', $sport_id);
    $edit_stmt->bindParam(':season', $season);
    $edit_stmt->bindParam(':start_date', $start_date);
    $edit_stmt->bindParam(':end_date', $end_date);
    $edit_stmt->bindParam(':registration_deadline', $registration_deadline);
    $edit_stmt->bindParam(':max_teams', $max_teams);
    $edit_stmt->bindParam(':rules', $rules);
    $edit_stmt->bindParam(':status', $status);
    $edit_stmt->bindParam(':approval_required', $approval_required);
    $edit_stmt->bindParam(':id', $league_id);
    
    if ($edit_stmt->execute()) {
        showMessage("League updated successfully!", "success");
    } else {
        showMessage("Failed to update league!", "error");
    }
}

// Get pending approval count
$pending_query = "SELECT COUNT(*) as count FROM team_registration_requests WHERE status = 'pending'";
$pending_stmt = $db->prepare($pending_query);
$pending_stmt->execute();
$pending_count = $pending_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get leagues with comprehensive data
$page = $_GET['page'] ?? 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$sport_filter = $_GET['sport'] ?? '';
$status_filter = $_GET['status'] ?? '';

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

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM leagues l 
                JOIN sports s ON l.sport_id = s.id $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_leagues = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_leagues / $per_page);

// Get leagues with detailed information
$leagues_query = "SELECT l.*, s.name as sport_name, s.max_players_per_team,
                         u.first_name, u.last_name, u.username as created_by_username,
                         (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as team_count,
                         (SELECT COUNT(*) FROM matches WHERE league_id = l.id) as total_matches,
                         (SELECT COUNT(*) FROM matches WHERE league_id = l.id AND status = 'completed') as completed_matches,
                         (SELECT COUNT(*) FROM matches WHERE league_id = l.id AND status = 'scheduled') as scheduled_matches,
                         (SELECT COUNT(*) FROM team_registration_requests WHERE league_id = l.id AND status = 'pending') as pending_requests
                  FROM leagues l 
                  JOIN sports s ON l.sport_id = s.id 
                  JOIN users u ON l.created_by = u.id
                  $where_clause 
                  ORDER BY l.created_at DESC 
                  LIMIT $per_page OFFSET $offset";

$leagues_stmt = $db->prepare($leagues_query);
foreach ($params as $key => $value) {
    $leagues_stmt->bindValue($key, $value);
}
$leagues_stmt->execute();
$leagues = $leagues_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sports for dropdowns
$sports_query = "SELECT * FROM sports ORDER BY name";
$sports_stmt = $db->prepare($sports_query);
$sports_stmt->execute();
$sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM leagues) as total_leagues,
                (SELECT COUNT(*) FROM leagues WHERE status = 'active') as active_leagues,
                (SELECT COUNT(*) FROM leagues WHERE status = 'open') as open_leagues,
                (SELECT COUNT(*) FROM leagues WHERE status = 'completed') as completed_leagues,
                (SELECT COUNT(*) FROM teams) as total_teams,
                (SELECT COUNT(*) FROM matches) as total_matches";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leagues - Sports League Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 { font-size: 1.8rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-links a { 
            color: white; 
            text-decoration: none; 
            padding: 0.5rem 1rem; 
            border-radius: 4px; 
            transition: background 0.3s;
            position: relative;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.1); }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #28a745;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover { transform: translateY(-2px); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .controls-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .controls-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box, .filter-select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-select {
            background: white;
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
        
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        
        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }
        
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
        
        .leagues-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .league-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .league-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .league-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            position: relative;
        }
        
        .league-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .league-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .approval-badge {
            position: absolute;
            top: 3rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.7rem;
            background: #ffc107;
            color: black;
            font-weight: bold;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-open { background: #28a745; color: white; }
        .status-active { background: #007bff; color: white; }
        .status-closed { background: #ffc107; color: black; }
        .status-completed { background: #17a2b8; color: white; }
        
        .league-body {
            padding: 1.5rem;
        }
        
        .league-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .league-stats {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .stats-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .stat-item .label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .league-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .progress-bar {
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            height: 6px;
            margin: 0.5rem 0;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            transition: width 0.3s ease;
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
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-height: 90vh;
            overflow-y: auto;
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
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 2px rgba(40,167,69,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            text-decoration: none;
            border-radius: 4px;
            color: #28a745;
        }
        
        .pagination .current {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
        }
        
        .approval-request-card {
            background: #fff;
            border-left: 4px solid #ffc107;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .request-info h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .request-meta {
            font-size: 0.9rem;
            color: #666;
        }
        
        .team-owner-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .owner-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
        }
        
        .detail-value {
            font-weight: 600;
            color: #333;
        }
        
        .team-members-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .member-card {
            background: white;
            padding: 1rem;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .member-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .member-role {
            font-size: 0.85rem;
            color: #666;
        }
        
        .approval-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .leagues-grid { grid-template-columns: 1fr; }
            .controls-row { flex-direction: column; align-items: stretch; }
            .search-box { min-width: auto; }
            .league-info { grid-template-columns: 1fr; }
            .modal-content { margin: 5% auto; padding: 1.5rem; }
            .owner-details { grid-template-columns: 1fr; }
            .members-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>🏆 Labangon - League Management</h1>
        <div class="nav-links">
            <a href="./dashboard.php">Dashboard</a>
            <a href="manage_users.php">Users</a>
            <a href="#" onclick="showApprovalsModal(); return false;">
                Approvals
                <?php if ($pending_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="system_reports.php">Reports</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Display messages -->
        <?php displayMessage(); ?>
        
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_leagues']; ?></div>
                <div class="stat-label">Total Leagues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_leagues']; ?></div>
                <div class="stat-label">Active Leagues</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['open_leagues']; ?></div>
                <div class="stat-label">Open for Registration</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['completed_leagues']; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_teams']; ?></div>
                <div class="stat-label">Total Teams</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_matches']; ?></div>
                <div class="stat-label">Total Matches</div>
            </div>
        </div>
        
        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-row">
                <input type="text" 
                       class="search-box" 
                       placeholder="Search leagues by name, season, or sport..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       onkeyup="searchLeagues(this.value)">
                
                <select class="filter-select" onchange="filterBySport(this.value)">
                    <option value="">All Sports</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>" <?php echo $sport_filter == $sport['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sport['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select class="filter-select" onchange="filterByStatus(this.value)">
                    <option value="">All Statuses</option>
                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
                
                <button class="btn btn-success" onclick="showCreateModal()">
                    + Create League
                </button>
                
                <a href="?search=&sport=&status=" class="btn btn-secondary">
                    Clear Filters
                </a>
            </div>
        </div>
        
        <!-- Leagues Grid -->
        <div class="leagues-grid">
            <?php foreach ($leagues as $league): ?>
            <div class="league-card">
                <div class="league-header">
                    <div class="status-badge status-<?php echo $league['status']; ?>">
                        <?php echo ucfirst($league['status']); ?>
                    </div>
                    <?php if ($league['approval_required'] && $league['pending_requests'] > 0): ?>
                        <div class="approval-badge">
                            <?php echo $league['pending_requests']; ?> Pending
                        </div>
                    <?php endif; ?>
                    <div class="league-title"><?php echo htmlspecialchars($league['name']); ?></div>
                    <div class="league-subtitle">
                        <?php echo htmlspecialchars($league['sport_name']); ?> • <?php echo htmlspecialchars($league['season']); ?>
                        <?php if ($league['approval_required']): ?>
                            <br><small>🔒 Requires Approval</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="league-body">
                    <div class="league-info">
                        <div class="info-item">
                            <span class="info-label">Duration</span>
                            <span class="info-value">
                                <?php echo date('M j', strtotime($league['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($league['end_date'])); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registration</span>
                            <span class="info-value">
                                Until <?php echo date('M j, Y', strtotime($league['registration_deadline'])); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Teams</span>
                            <span class="info-value">
                                <?php echo $league['team_count']; ?>/<?php echo $league['max_teams']; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Created by</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($league['first_name'] . ' ' . $league['last_name']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Team Registration Progress -->
                    <div class="progress-bar">
                        <div class="progress-fill" 
                             style="width: <?php echo ($league['team_count'] / $league['max_teams']) * 100; ?>%"></div>
                    </div>
                    
                    <div class="league-stats">
                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="number"><?php echo $league['total_matches']; ?></div>
                                <div class="label">Total Matches</div>
                            </div>
                            <div class="stat-item">
                                <div class="number"><?php echo $league['completed_matches']; ?></div>
                                <div class="label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="number"><?php echo $league['scheduled_matches']; ?></div>
                                <div class="label">Scheduled</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="league-actions">
                        <a href="../league/view_league.php?id=<?php echo $league['id']; ?>" class="btn btn-primary btn-sm">
                            View
                        </a>
                        
                        <button onclick="editLeague(<?php echo htmlspecialchars(json_encode($league)); ?>)" class="btn btn-info btn-sm">
                            Edit
                        </button>
                        
                        <?php if ($league['approval_required'] && $league['pending_requests'] > 0): ?>
                            <button onclick="viewLeagueApprovals(<?php echo $league['id']; ?>)" class="btn btn-warning btn-sm">
                                Approvals (<?php echo $league['pending_requests']; ?>)
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($league['status'] == 'draft' || $league['status'] == 'open'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="league_id" value="<?php echo $league['id']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Activate this league?')">
                                    Activate
                                </button>
                            </form>
                        <?php elseif ($league['status'] == 'active'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="league_id" value="<?php echo $league['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Mark league as completed?')">
                                    Complete
                                </button>
                            </form>
                        <?php elseif ($league['status'] == 'closed'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="league_id" value="<?php echo $league['id']; ?>">
                                <input type="hidden" name="action" value="reopen">
                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Reopen league for registration?')">
                                    Reopen
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <a href="../match/schedule_matches.php?league_id=<?php echo $league['id']; ?>" class="btn btn-info btn-sm">
                            Schedule
                        </a>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="league_id" value="<?php echo $league['id']; ?>">
                            <input type="hidden" name="action" value="delete_league">
                            <button type="submit" class="btn btn-danger btn-sm" 
                                    onclick="return confirm('Are you sure you want to delete this league? This action cannot be undone!')">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($leagues)): ?>
        <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="color: #666; margin-bottom: 1rem;">No leagues found</h3>
            <p style="color: #999; margin-bottom: 2rem;">Get started by creating your first league!</p>
            <button class="btn btn-success" onclick="showCreateModal()">
                + Create Your First League
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&status=<?php echo urlencode($status_filter); ?>">‹ Previous</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Create League Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🏆 Create New League</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>League Name *</label>
                    <input type="text" name="name" class="form-control" required 
                           placeholder="e.g., Summer Football Championship 2024">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Sport *</label>
                        <select name="sport_id" class="form-control" required>
                            <option value="">Select Sport</option>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?php echo $sport['id']; ?>">
                                    <?php echo htmlspecialchars($sport['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Season *</label>
                        <input type="text" name="season" class="form-control" required 
                               placeholder="e.g., Summer 2024">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Registration Deadline *</label>
                        <input type="date" name="registration_deadline" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Maximum Teams *</label>
                        <input type="number" name="max_teams" class="form-control" 
                               min="2" max="32" value="16" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Initial Status</label>
                    <select name="status" class="form-control">
                        <option value="draft">Draft (Hidden from public)</option>
                        <option value="open" selected>Open (Accepting registrations)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="approval_required" id="approval_required" checked>
                        <label for="approval_required">Require admin approval for team registration</label>
                    </div>
                    <small style="color: #666; display: block; margin-top: 0.25rem;">
                        When enabled, team owners must wait for admin approval before joining this league.
                    </small>
                </div>
                
                <div class="form-group">
                    <label>League Rules & Regulations</label>
                    <textarea name="rules" class="form-control" 
                              placeholder="Enter the rules and regulations for this league..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="create_league" class="btn btn-success">
                        🏆 Create League
                    </button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit League Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit League</h3>
                <button class="close" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_league_id" id="edit_league_id">
                
                <div class="form-group">
                    <label>League Name *</label>
                    <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Sport *</label>
                        <select name="edit_sport_id" id="edit_sport_id" class="form-control" required>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?php echo $sport['id']; ?>">
                                    <?php echo htmlspecialchars($sport['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Season *</label>
                        <input type="text" name="edit_season" id="edit_season" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="edit_start_date" id="edit_start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" name="edit_end_date" id="edit_end_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Registration Deadline *</label>
                        <input type="date" name="edit_registration_deadline" id="edit_registration_deadline" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Maximum Teams *</label>
                        <input type="number" name="edit_max_teams" id="edit_max_teams" class="form-control" 
                               min="2" max="32" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="edit_status" id="edit_status" class="form-control">
                        <option value="draft">Draft</option>
                        <option value="open">Open</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="edit_approval_required" id="edit_approval_required">
                        <label for="edit_approval_required">Require admin approval for team registration</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>League Rules & Regulations</label>
                    <textarea name="edit_rules" id="edit_rules" class="form-control"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="edit_league" class="btn btn-success">
                        💾 Update League
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Team Approvals Modal -->
    <div id="approvalsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Team Registration Approvals</h3>
                <button class="close" onclick="closeApprovalsModal()">&times;</button>
            </div>
            <div id="approvalsContent">
                <p style="text-align: center; color: #666;">Loading...</p>
            </div>
        </div>
    </div>
    
    <script>
        // Search functionality
        let searchTimeout;
        function searchLeagues(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const currentSport = new URLSearchParams(window.location.search).get('sport') || '';
                const currentStatus = new URLSearchParams(window.location.search).get('status') || '';
                window.location.href = `?search=${encodeURIComponent(query)}&sport=${currentSport}&status=${currentStatus}&page=1`;
            }, 500);
        }
        
        // Filter functions
        function filterBySport(sportId) {
            const currentSearch = new URLSearchParams(window.location.search).get('search') || '';
            const currentStatus = new URLSearchParams(window.location.search).get('status') || '';
            window.location.href = `?search=${encodeURIComponent(currentSearch)}&sport=${sportId}&status=${currentStatus}&page=1`;
        }
        
        function filterByStatus(status) {
            const currentSearch = new URLSearchParams(window.location.search).get('search') || '';
            const currentSport = new URLSearchParams(window.location.search).get('sport') || '';
            window.location.href = `?search=${encodeURIComponent(currentSearch)}&sport=${currentSport}&status=${status}&page=1`;
        }
        
        // Modal functions
        function showCreateModal() {
            document.getElementById('createModal').style.display = 'block';
            const today = new Date();
            const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
            const nextMonth = new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000);
            
            document.querySelector('input[name="registration_deadline"]').value = nextWeek.toISOString().split('T')[0];
            document.querySelector('input[name="start_date"]').value = nextMonth.toISOString().split('T')[0];
            document.querySelector('input[name="end_date"]').value = new Date(nextMonth.getTime() + 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        }
        
        function closeModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        function editLeague(league) {
            document.getElementById('edit_league_id').value = league.id;
            document.getElementById('edit_name').value = league.name;
            document.getElementById('edit_sport_id').value = league.sport_id;
            document.getElementById('edit_season').value = league.season;
            document.getElementById('edit_start_date').value = league.start_date;
            document.getElementById('edit_end_date').value = league.end_date;
            document.getElementById('edit_registration_deadline').value = league.registration_deadline;
            document.getElementById('edit_max_teams').value = league.max_teams;
            document.getElementById('edit_status').value = league.status;
            document.getElementById('edit_rules').value = league.rules || '';
            document.getElementById('edit_approval_required').checked = league.approval_required == 1;
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function showApprovalsModal() {
            document.getElementById('approvalsModal').style.display = 'block';
            loadAllApprovals();
        }
        
        function closeApprovalsModal() {
            document.getElementById('approvalsModal').style.display = 'none';
        }
        
        function viewLeagueApprovals(leagueId) {
            document.getElementById('approvalsModal').style.display = 'block';
            loadLeagueApprovals(leagueId);
        }
        
        function loadAllApprovals() {
            fetch('get_approvals.php')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('approvalsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('approvalsContent').innerHTML = '<p style="color: red;">Error loading approvals</p>';
                });
        }
        
        function loadLeagueApprovals(leagueId) {
            fetch(`get_approvals.php?league_id=${leagueId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('approvalsContent').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('approvalsContent').innerHTML = '<p style="color: red;">Error loading approvals</p>';
                });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const editModal = document.getElementById('editModal');
            const approvalsModal = document.getElementById('approvalsModal');
            if (event.target == createModal) {
                createModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == approvalsModal) {
                approvalsModal.style.display = 'none';
            }
        }
        
        // Close modals with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeEditModal();
                closeApprovalsModal();
            }
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const startDate = form.querySelector('input[name*="start_date"]');
                    const endDate = form.querySelector('input[name*="end_date"]');
                    const regDeadline = form.querySelector('input[name*="registration_deadline"]');
                    
                    if (startDate && endDate && regDeadline) {
                        const start = new Date(startDate.value);
                        const end = new Date(endDate.value);
                        const deadline = new Date(regDeadline.value);
                        
                        if (end <= start) {
                            alert('End date must be after start date!');
                            e.preventDefault();
                            return false;
                        }
                        
                        if (deadline >= start) {
                            alert('Registration deadline must be before the league start date!');
                            e.preventDefault();
                            return false;
                        }
                    }
                });
            });
        });
        
        // Auto-hide success messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>