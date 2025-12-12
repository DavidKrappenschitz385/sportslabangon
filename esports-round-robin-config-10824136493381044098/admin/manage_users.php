<?php
// admin/manage_users.php - Complete User Management for Admins
require_once '../config/database.php';
requireRole('admin');

$database = new Database();
$db = $database->connect();

// Handle user actions
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    $current_user = getCurrentUser();
    
    // Prevent admin from modifying their own account
    if ($user_id == $current_user['id'] && in_array($action, ['delete', 'deactivate'])) {
        showMessage("You cannot modify your own admin account!", "error");
    } else {
        switch ($action) {
            case 'promote_to_owner':
                $update_query = "UPDATE users SET role = 'team_owner' WHERE id = :id";
                $success_msg = "User promoted to Team Owner successfully!";
                break;
                
            case 'demote_to_player':
                $update_query = "UPDATE users SET role = 'player' WHERE id = :id";
                $success_msg = "User demoted to Player successfully!";
                break;
                
            case 'promote_to_admin':
                $update_query = "UPDATE users SET role = 'admin' WHERE id = :id";
                $success_msg = "User promoted to Administrator successfully!";
                break;
                
            case 'delete_user':
                // Check if user has teams or is in teams
                $check_query = "SELECT 
                                (SELECT COUNT(*) FROM teams WHERE owner_id = :id) as owned_teams,
                                (SELECT COUNT(*) FROM team_members WHERE player_id = :id) as team_memberships";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':id', $user_id);
                $check_stmt->execute();
                $user_activity = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user_activity['owned_teams'] > 0 || $user_activity['team_memberships'] > 0) {
                    showMessage("Cannot delete user - they have active teams or memberships!", "error");
                    $update_query = null;
                } else {
                    $update_query = "DELETE FROM users WHERE id = :id";
                    $success_msg = "User deleted successfully!";
                }
                break;
                
            default:
                $update_query = null;
        }
        
        if ($update_query) {
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':id', $user_id);
            
            if ($update_stmt->execute()) {
                showMessage($success_msg, "success");
                
                // Create notification for the user (if not deleted)
                if ($action != 'delete_user') {
                    $notification_query = "INSERT INTO notifications (user_id, title, message, type) 
                                          VALUES (:user_id, 'Account Updated', 'Your account role has been updated by an administrator.', 'info')";
                    $notification_stmt = $db->prepare($notification_query);
                    $notification_stmt->bindParam(':user_id', $user_id);
                    $notification_stmt->execute();
                }
            } else {
                showMessage("Failed to update user!", "error");
            }
        }
    }
}

// Handle user creation
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    
    // Check if username or email already exists
    $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':username', $username);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        showMessage("Username or email already exists!", "error");
    } else {
        $create_query = "INSERT INTO users (username, email, password, first_name, last_name, phone, role) 
                        VALUES (:username, :email, :password, :first_name, :last_name, :phone, :role)";
        $create_stmt = $db->prepare($create_query);
        $create_stmt->bindParam(':username', $username);
        $create_stmt->bindParam(':email', $email);
        $create_stmt->bindParam(':password', $password);
        $create_stmt->bindParam(':first_name', $first_name);
        $create_stmt->bindParam(':last_name', $last_name);
        $create_stmt->bindParam(':phone', $phone);
        $create_stmt->bindParam(':role', $role);
        
        if ($create_stmt->execute()) {
            showMessage("User created successfully!", "success");
        } else {
            showMessage("Failed to create user!", "error");
        }
    }
}

// Get users with pagination and search
$page = $_GET['page'] ?? 1;
$per_page = 15;
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

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $per_page);

// Get users with activity data
$users_query = "SELECT u.*, 
                       (SELECT COUNT(*) FROM teams WHERE owner_id = u.id) as owned_teams,
                       (SELECT COUNT(*) FROM team_members WHERE player_id = u.id AND status = 'active') as team_memberships,
                       (SELECT COUNT(DISTINCT league_id) FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.player_id = u.id) as leagues_participated
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

// Get system statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM users WHERE role = 'player') as total_players,
                (SELECT COUNT(*) FROM users WHERE role = 'team_owner') as total_owners,
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
                (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_users_month";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Sports League Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #343a40, #007bff);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 { font-size: 1.8rem; }
        
        .nav-links { display: flex; gap: 1rem; }
        .nav-links a { 
            color: white; 
            text-decoration: none; 
            padding: 0.5rem 1rem; 
            border-radius: 4px; 
            transition: background 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.1); }
        
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
        
        .search-box {
            flex: 1;
            min-width: 200px;
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
        
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
        
        .users-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #28a745);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .user-details h4 {
            margin: 0;
            font-size: 1rem;
            color: #333;
        }
        
        .user-details small {
            color: #666;
            display: block;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-team_owner { background: #ffc107; color: black; }
        .role-player { background: #28a745; color: white; }
        
        .activity-stats {
            font-size: 0.85rem;
            color: #666;
        }
        
        .activity-stats strong {
            color: #007bff;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
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
            color: #007bff;
        }
        
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
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
            margin: 5% auto;
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
            margin-bottom: 1rem;
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
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
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
            .container { padding: 0 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .controls-row { flex-direction: column; align-items: stretch; }
            .search-box { min-width: auto; }
            .users-table { overflow-x: auto; }
            table { min-width: 800px; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>👥 User Management</h1>
        <div class="nav-links">
            <a href="./dashboard.php">Dashboard</a>
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
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_players']; ?></div>
                <div class="stat-label">Players</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_owners']; ?></div>
                <div class="stat-label">Team Owners</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['new_users_month']; ?></div>
                <div class="stat-label">New This Month</div>
            </div>
        </div>
        
        <!-- Controls Section -->
        <div class="controls-section">
            <div class="controls-row">
                <input type="text" 
                       class="search-box" 
                       placeholder="Search users by name, username, or email..." 
                       value="<?php echo htmlspecialchars($search); ?>"
                       onkeyup="searchUsers(this.value)">
                
                <select class="filter-select" onchange="filterByRole(this.value)">
                    <option value="">All Roles</option>
                    <option value="player" <?php echo $role_filter == 'player' ? 'selected' : ''; ?>>Players</option>
                    <option value="team_owner" <?php echo $role_filter == 'team_owner' ? 'selected' : ''; ?>>Team Owners</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Administrators</option>
                </select>
                
                <button class="btn btn-success" onclick="showCreateModal()">
                    ➕ Add New User
                </button>
                
                <a href="?search=&role=" class="btn btn-secondary">
                    🔄 Clear Filters
                </a>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Activity</th>
                        <th>Member Since</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                                    <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo str_replace('_', ' ', ucfirst($user['role'])); ?>
                            </span>
                        </td>
                        
                        <td>
                            <div class="activity-stats">
                                <div>Teams Owned: <strong><?php echo $user['owned_teams']; ?></strong></div>
                                <div>Memberships: <strong><?php echo $user['team_memberships']; ?></strong></div>
                                <div>Leagues: <strong><?php echo $user['leagues_participated']; ?></strong></div>
                            </div>
                        </td>
                        
                        <td>
                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?><br>
                            <small><?php echo date('g:i A', strtotime($user['created_at'])); ?></small>
                        </td>
                        
                        <td>
                            <div class="action-buttons">
                                <?php $current_user = getCurrentUser(); ?>
                                <?php if ($user['id'] != $current_user['id']): ?>
                                    
                                    <?php if ($user['role'] == 'player'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Promote <?php echo htmlspecialchars($user['username']); ?> to Team Owner?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="promote_to_owner">
                                            <button type="submit" class="btn btn-warning btn-sm">Make Owner</button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Promote <?php echo htmlspecialchars($user['username']); ?> to Administrator?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="promote_to_admin">
                                            <button type="submit" class="btn btn-danger btn-sm">Make Admin</button>
                                        </form>
                                        
                                    <?php elseif ($user['role'] == 'team_owner'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Demote <?php echo htmlspecialchars($user['username']); ?> to Player?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="demote_to_player">
                                            <button type="submit" class="btn btn-success btn-sm">Make Player</button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Promote <?php echo htmlspecialchars($user['username']); ?> to Administrator?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="promote_to_admin">
                                            <button type="submit" class="btn btn-danger btn-sm">Make Admin</button>
                                        </form>
                                    
                                    <?php elseif ($user['role'] == 'admin'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Demote <?php echo htmlspecialchars($user['username']); ?> to Team Owner?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="demote_to_player">
                                            <button type="submit" class="btn btn-warning btn-sm">Demote</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($user['username']); ?>? This action cannot be undone!')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                    
                                <?php else: ?>
                                    <span class="badge" style="padding: 0.4rem 0.8rem; background: #17a2b8; color: white; border-radius: 4px; font-size: 0.75rem;">YOU</span>
                                <?php endif; ?>
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
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">‹ Previous</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">Next ›</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Create User Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Create New User</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" class="form-control" required>
                            <option value="player">Player</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" class="form-control" placeholder="Optional">
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                    <small style="color: #666;">Minimum 6 characters</small>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="create_user" class="btn btn-success">Create User</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Search functionality
        let searchTimeout;
        function searchUsers(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const currentRole = new URLSearchParams(window.location.search).get('role') || '';
                window.location.href = `?search=${encodeURIComponent(query)}&role=${currentRole}&page=1`;
            }, 500);
        }
        
        // Role filter functionality
        function filterByRole(role) {
            const currentSearch = new URLSearchParams(window.location.search).get('search') || '';
            window.location.href = `?search=${encodeURIComponent(currentSearch)}&role=${role}&page=1`;
        }
        
        // Modal functions
        function showCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close modal with escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        
        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
        
        // Confirm dangerous actions
        function confirmAction(message) {
            return confirm(message);
        }
        
        // Real-time search suggestions (future enhancement)
        document.querySelector('.search-box').addEventListener('input', function(e) {
            // Could implement live search suggestions here
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-box').focus();
            }
            
            // Ctrl/Cmd + N to create new user
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                showCreateModal();
            }
        });
        
        // Enhanced form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const username = document.querySelector('input[name="username"]').value;
            const email = document.querySelector('input[name="email"]').value;
            
            // Basic validation
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                e.preventDefault();
                return;
            }
            
            if (username.length < 3) {
                alert('Username must be at least 3 characters long!');
                e.preventDefault();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address!');
                e.preventDefault();
                return;
            }
        });
        
        // Auto-refresh page every 5 minutes to show new users
        setInterval(function() {
            // Only refresh if no forms are being filled out
            const activeElement = document.activeElement;
            if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                // Uncomment the line below if you want auto-refresh
                // location.reload();
            }
        }, 300000); // 5 minutes
        
        // Highlight recently created users (if timestamp is within last hour)
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            const oneHourAgo = new Date(Date.now() - 60 * 60 * 1000);
            
            rows.forEach(row => {
                const dateCell = row.querySelector('td:nth-child(4)');
                const dateText = dateCell.textContent.trim();
                // This would need proper date parsing for full implementation
                // For now, we'll just add a visual indicator for demo purposes
            });
        });
        
        // Export functionality (placeholder)
        function exportUsers() {
            // This would implement CSV/Excel export functionality
            alert('Export functionality would be implemented here!');
        }
        
        // Bulk actions (placeholder for future enhancement)
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = !cb.checked);
        }
        
        // Print page functionality
        function printPage() {
            window.print();
        }
        
        // Add some visual feedback for actions
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
        
        // Add CSS for ripple animation
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
    </script>
</body>
</html>