<?php
// profile.php - User Profile Management
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validation
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email is already taken by another user
    if (empty($errors)) {
        $check_email = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $check_stmt = $db->prepare($check_email);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->bindParam(':user_id', $user['id']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "Email is already taken by another user";
        }
    }
    
    // Update profile if no errors
    if (empty($errors)) {
        $update_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                        email = :email, phone = :phone, updated_at = NOW() 
                        WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':user_id', $user['id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            
            showMessage("Profile updated successfully!", "success");
            
            // Refresh user data
            $user = getCurrentUser();
        } else {
            $errors[] = "Failed to update profile";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current password hash
    $pass_query = "SELECT password FROM users WHERE id = :user_id";
    $pass_stmt = $db->prepare($pass_query);
    $pass_stmt->bindParam(':user_id', $user['id']);
    $pass_stmt->execute();
    $user_pass = $pass_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify current password
    if (!password_verify($current_password, $user_pass['password'])) {
        $errors[] = "Current password is incorrect";
    }
    
    // Validate new password
    if (strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "New password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "New password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $new_password)) {
        $errors[] = "New password must contain at least one special character";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Update password if no errors
    if (empty($errors)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_pass = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
        $update_pass_stmt = $db->prepare($update_pass);
        $update_pass_stmt->bindParam(':password', $password_hash);
        $update_pass_stmt->bindParam(':user_id', $user['id']);
        
        if ($update_pass_stmt->execute()) {
            showMessage("Password changed successfully!", "success");
        } else {
            $errors[] = "Failed to change password";
        }
    }
}

// Get user statistics
$stats_query = " SELECT (SELECT COUNT(*) FROM teams WHERE owner_id = :user_id) AS teams_owned, (SELECT COUNT(*) FROM team_members WHERE player_id = :user_id2 AND status = 'active') AS teams_joined, ( SELECT COUNT(DISTINCT league_id) FROM ( SELECT league_id FROM teams WHERE owner_id = :user_id3 UNION SELECT t.league_id FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.player_id = :user_id4 AND tm.status = 'active' ) AS combined ) AS leagues_count "; $stats_stmt = $db->prepare($stats_query); $stats_stmt->bindParam(':user_id', $user['id']); $stats_stmt->bindParam(':user_id2', $user['id']); $stats_stmt->bindParam(':user_id3', $user['id']); $stats_stmt->bindParam(':user_id4', $user['id']); $stats_stmt->execute(); $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$activity_query = "SELECT 'team_created' as type, t.name as description, t.created_at as date
                  FROM teams t WHERE t.owner_id = :user_id
                  UNION ALL
                  SELECT 'team_joined' as type, t.name as description, tm.joined_at as date
                  FROM team_members tm
                  JOIN teams t ON tm.team_id = t.id
                  WHERE tm.player_id = :user_id2 AND tm.status = 'active'
                  ORDER BY date DESC LIMIT 10";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->bindParam(':user_id', $user['id']);
$activity_stmt->bindParam(':user_id2', $user['id']);
$activity_stmt->execute();
$activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Sports League Registration System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, green , greenyellow);
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
        
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
            height: fit-content;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .profile-role {
            color: #666;
            margin-bottom: 1rem;
            text-transform: capitalize;
        }
        
        .profile-stats {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }
        
        .stat-item {
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #007bff;
        }
        
        .card-title {
            font-size: 1.5rem;
            color: #333;
            font-weight: bold;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
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
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .form-control:disabled {
            background: #f8f9fa;
            color: #666;
            cursor: not-allowed;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .error-list {
            list-style: none;
            padding: 0;
        }
        
        .error-list li {
            padding: 0.5rem 0;
        }
        
        .error-list li:before {
            content: "• ";
            color: #dc3545;
            font-weight: bold;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-description {
            font-weight: 600;
            color: #333;
        }
        
        .activity-date {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .password-requirements {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .requirement {
            padding: 0.25rem 0;
            color: #666;
        }
        
        .requirement.met {
            color: #28a745;
        }
        
        .requirement.met::before {
            content: '✓ ';
            font-weight: bold;
        }
        
        .requirement.unmet::before {
            content: '✗ ';
            color: #dc3545;
            font-weight: bold;
        }
        
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .tab {
            padding: 1rem 2rem;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .tab:hover {
            color: #007bff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-grid:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .header { padding: 1.5rem 1rem; }
            .header h1 { font-size: 2rem; }
            .container { padding: 0 1rem; }
            .card { padding: 1.5rem; }
            .tabs { overflow-x: auto; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>My Profile</h1>
            <p>Manage your account settings and preferences</p>
        </div>
    </div>
    
    <!-- Breadcrumb -->
    <div class="nav-breadcrumb">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span>›</span>
            <span>My Profile</span>
        </div>
    </div>
    
    <div class="container">
        <!-- Display messages -->
        <?php displayMessage(); ?>
        
        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please fix the following errors:</strong>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                
                <div class="profile-name">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </div>
                
                <div class="profile-role">
                    <?php echo htmlspecialchars($user['role']); ?>
                </div>
                
                <div style="color: #666; font-size: 0.9rem;">
                    @<?php echo htmlspecialchars($user['username']); ?>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['teams_owned']; ?></div>
                        <div class="stat-label">Teams Owned</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['teams_joined']; ?></div>
                        <div class="stat-label">Teams Joined</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Member Since</div>
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="dashboard.php" class="btn btn-secondary" style="width: 100%;">
                        Back to Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-main">
                <!-- Tabs -->
                <div class="card">
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('profile')">Profile Information</button>
                        <button class="tab" onclick="showTab('security')">Security</button>
                        <button class="tab" onclick="showTab('activity')">Activity</button>
                    </div>
                    
                    <!-- Profile Tab -->
                    <div id="profile-tab" class="tab-content active">
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" 
                                           id="first_name" 
                                           name="first_name" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" 
                                           id="last_name" 
                                           name="last_name" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" 
                                           id="username" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                                           disabled>
                                    <small style="color: #666; font-size: 0.85rem;">Username cannot be changed</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" 
                                           id="phone" 
                                           name="phone" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <input type="text" 
                                           id="role" 
                                           class="form-control" 
                                           value="<?php echo ucfirst($user['role']); ?>" 
                                           disabled>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem;">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Security Tab -->
                    <div id="security-tab" class="tab-content">
                        <div class="alert alert-info" style="margin-bottom: 2rem;">
                            <strong>Password Requirements:</strong>
                            <ul style="margin: 0.5rem 0 0 1.5rem;">
                                <li>At least 6 characters long</li>
                                <li>Contains uppercase and lowercase letters</li>
                                <li>Contains at least one special character</li>
                            </ul>
                        </div>
                        
                        <form method="POST" id="passwordForm">
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" 
                                       id="current_password" 
                                       name="current_password" 
                                       class="form-control" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       class="form-control" 
                                       required>
                                <div class="password-requirements">
                                    <div class="requirement unmet" id="req-length">At least 6 characters</div>
                                    <div class="requirement unmet" id="req-upper">One uppercase letter (A-Z)</div>
                                    <div class="requirement unmet" id="req-lower">One lowercase letter (a-z)</div>
                                    <div class="requirement unmet" id="req-symbol">One special character (!@#$%^&*...)</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       required>
                                <div class="password-strength" id="match-status"></div>
                            </div>
                            
                            <div style="margin-top: 2rem;">
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Activity Tab -->
                    <div id="activity-tab" class="tab-content">
                        <h3 style="margin-bottom: 1.5rem; color: #333;">Recent Activity</h3>
                        
                        <?php if (count($activities) > 0): ?>
                        <ul class="activity-list">
                            <?php foreach ($activities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <?php echo $activity['type'] == 'team_created' ? '🏆' : '👥'; ?>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-description">
                                        <?php 
                                        if ($activity['type'] == 'team_created') {
                                            echo 'Created team: ' . htmlspecialchars($activity['description']);
                                        } else {
                                            echo 'Joined team: ' . htmlspecialchars($activity['description']);
                                        }
                                        ?>
                                    </div>
                                    <div class="activity-date">
                                        <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <div class="alert alert-info">
                            No recent activity to display.
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 2rem;">
                            <h3 style="margin-bottom: 1rem; color: #333;">Account Information</h3>
                            <div class="info-grid">
                                <div class="info-label">Account Created:</div>
                              ```php
                                <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></div>
                            </div>
                            <div class="info-grid">
                                <div class="info-label">Last Updated:</div>
                                <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($user['updated_at'] ?? $user['created_at'])); ?></div>
                            </div>
                            <div class="info-grid">
                                <div class="info-label">Email:</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="info-grid">
                                <div class="info-label">Phone:</div>
                                <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-grid">
                                <div class="info-label">Role:</div>
                                <div class="info-value"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></div>
                            </div>
                        </div>
                    </div><!-- End Activity Tab -->
                </div><!-- End Card -->
            </div><!-- End profile-main -->
        </div><!-- End profile-grid -->
    </div><!-- End container -->

    <script>
        // Tab switching
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }

        // Password validation in real time
        const newPass = document.getElementById('new_password');
        const confirmPass = document.getElementById('confirm_password');
        const matchStatus = document.getElementById('match-status');

        if (newPass && confirmPass) {
            newPass.addEventListener('input', checkRequirements);
            confirmPass.addEventListener('input', checkMatch);
        }

        function checkRequirements() {
            const value = newPass.value;
            toggleRequirement('req-length', value.length >= 6);
            toggleRequirement('req-upper', /[A-Z]/.test(value));
            toggleRequirement('req-lower', /[a-z]/.test(value));
            toggleRequirement('req-symbol', /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value));
            checkMatch();
        }

        function toggleRequirement(id, met) {
            const el = document.getElementById(id);
            if (met) {
                el.classList.remove('unmet');
                el.classList.add('met');
            } else {
                el.classList.remove('met');
                el.classList.add('unmet');
            }
        }

        function checkMatch() {
            if (confirmPass.value.length > 0) {
                if (newPass.value === confirmPass.value) {
                    matchStatus.textContent = "Passwords match";
                    matchStatus.style.color = "green";
                } else {
                    matchStatus.textContent = "Passwords do not match";
                    matchStatus.style.color = "red";
                }
            } else {
                matchStatus.textContent = "";
            }
        }
    </script>
</body>
</html>

