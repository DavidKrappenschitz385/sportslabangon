<?php
// admin/profile.php - Admin Profile Management
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Check if email is already used by another user
    $check_email_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
    $check_stmt = $db->prepare($check_email_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->bindParam(':user_id', $user['id']);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        showMessage("Email is already in use by another account!", "error");
    } else {
        $update_query = "UPDATE users 
                        SET first_name = :first_name, 
                            last_name = :last_name, 
                            email = :email, 
                            phone = :phone, 
                            address = :address,
                            updated_at = NOW()
                        WHERE id = :user_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':address', $address);
        $update_stmt->bindParam(':user_id', $user['id']);
        
        if ($update_stmt->execute()) {
            // Update session data
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            showMessage("Profile updated successfully!", "success");
            
            // Refresh user data
            $user = getCurrentUser();
        } else {
            showMessage("Failed to update profile!", "error");
        }
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
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
        showMessage("Current password is incorrect!", "error");
    } elseif ($new_password !== $confirm_password) {
        showMessage("New passwords do not match!", "error");
    } elseif (strlen($new_password) < 6) {
        showMessage("New password must be at least 6 characters long!", "error");
    } else {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_pass_query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
        $update_pass_stmt = $db->prepare($update_pass_query);
        $update_pass_stmt->bindParam(':password', $new_password_hash);
        $update_pass_stmt->bindParam(':user_id', $user['id']);
        
        if ($update_pass_stmt->execute()) {
            showMessage("Password changed successfully!", "success");
        } else {
            showMessage("Failed to change password!", "error");
        }
    }
}

// Get user statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users) as users_total,
    (SELECT COUNT(*) FROM leagues) as leagues_total,
    (SELECT COUNT(*) FROM notifications WHERE user_id = :user_id) as total_notifications,
    (SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0) as unread_notifications";


$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user['id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity
$activity_query = "SELECT 'league' as type, CONCAT('League: ', name) as activity, created_at 
                   FROM leagues
                   UNION ALL
                   SELECT 'system' as type, message as activity, created_at 
                   FROM notifications 
                   WHERE user_id = :user_id
                   ORDER BY created_at DESC 
                   LIMIT 10";



$activity_stmt = $db->prepare($activity_query);
$activity_stmt->bindParam(':user_id', $user['id']);
$activity_stmt->execute();
$recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Refresh user data from database
$refresh_query = "SELECT * FROM users WHERE id = :user_id";
$refresh_stmt = $db->prepare($refresh_query);
$refresh_stmt->bindParam(':user_id', $user['id']);
$refresh_stmt->execute();
$user_data = $refresh_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Sports League Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .profile-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }
        
        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .profile-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
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
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .profile-role {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .profile-info {
            text-align: left;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .info-row strong {
            color: #333;
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .stat-value {
            font-weight: bold;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .content-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #667eea;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
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
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102,126,234,0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
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
            border-radius: 4px;
            margin-bottom: 1.5rem;
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
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 1rem;
            border-left: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 0.75rem;
            border-radius: 0 4px 4px 0;
        }
        
        .activity-text {
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            color: #666;
            font-size: 0.85rem;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .password-requirements ul {
            margin: 0.5rem 0 0 1.5rem;
            color: #666;
        }
        
        @media (max-width: 968px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>My Profile</h1>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <?php if ($user['role'] == 'admin'): ?>
                <a href="manage_leagues.php">Leagues</a>
                <a href="manage_users.php">Users</a>
                <a href="system_reports.php">Reports</a>
            <?php endif; ?>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php displayMessage(); ?>
        
        <div class="profile-layout">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)); ?>
                    </div>
                    
                    <div class="profile-name">
                        <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                    </div>
                    
                    <div class="profile-role">
                        <?php echo ucfirst(str_replace('_', ' ', $user_data['role'])); ?>
                    </div>
                    
                    <div class="profile-info">
                        <div class="info-row">
                            <strong>Username:</strong>
                            <?php echo htmlspecialchars($user_data['username']); ?>
                        </div>
                        <div class="info-row">
                            <strong>Email:</strong>
                            <?php echo htmlspecialchars($user_data['email']); ?>
                        </div>
                        <?php if ($user_data['phone']): ?>
                        <div class="info-row">
                            <strong>Phone:</strong>
                            <?php echo htmlspecialchars($user_data['phone']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <strong>Member Since:</strong>
                            <?php echo date('M j, Y', strtotime($user_data['created_at'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Card -->
                <div class="stats-card">
                    <div class="stats-title">Activity Statistics</div>
                    
                    <?php if ($user['role'] == 'admin'): ?>
                   <div class="stat-item">
    <span class="stat-label">Total Users</span>
    <span class="stat-value"><?php echo $stats['users_total'] ?? 0; ?></span>
</div>
<div class="stat-item">
    <span class="stat-label">Total Leagues</span>
    <span class="stat-value"><?php echo $stats['leagues_total'] ?? 0; ?></span>
</div>
                    <div class="stat-item">
                        <span class="stat-label">Leagues Created</span>
                        <span class="stat-value"><?php echo $stats['leagues_created'] ?? 0; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <span class="stat-label">Total Notifications</span>
                        <span class="stat-value"><?php echo $stats['total_notifications'] ?? 0; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Unread Messages</span>
                        <span class="stat-value"><?php echo $stats['unread_notifications'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="profile-content">
                <!-- Personal Information -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">Personal Information</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                                       placeholder="Optional">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" 
                                       placeholder="Optional">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Username (Cannot be changed)</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_data['username']); ?>" 
                                       disabled>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">Change Password</h2>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="password-requirements">
                            <strong>Password Requirements:</strong>
                            <ul>
                                <li>Minimum 6 characters long</li>
                                <li>Use a mix of letters, numbers, and symbols for better security</li>
                                <li>Avoid using common words or personal information</li>
                            </ul>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <button type="submit" name="change_password" class="btn btn-danger">
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Activity -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Activity</h2>
                    </div>
                    
                    <?php if (count($recent_activity) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach ($recent_activity as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-text">
                                <?php echo htmlspecialchars($activity['activity']); ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="alert alert-info">
                        No recent activity to display.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.querySelector('form[method="POST"]:has([name="change_password"])');
            
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = document.querySelector('[name="new_password"]').value;
                    const confirmPassword = document.querySelector('[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return false;
                    }
                    
                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }
                });
            }
            
            // Email validation
            const profileForm = document.querySelector('form[method="POST"]:has([name="update_profile"])');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const email = document.querySelector('[name="email"]').value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        alert('Please enter a valid email address!');
                        return false;
                    }
                });
            }
            
            // Auto-hide success messages
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.3s';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
        
        // Password strength indicator
        const newPasswordInput = document.querySelector('[name="new_password"]');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                // Could add visual indicator here
            });
        }
        
        function calculatePasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            return strength;
        }
    </script>
</body>
</html>