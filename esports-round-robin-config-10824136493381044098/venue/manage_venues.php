<?php
// venue/manage_venues.php - Venue Management
require_once '../config/database.php';
requireRole('admin');

$database = new Database();
$db = $database->connect();

// Handle venue actions
if ($_POST) {
    if (isset($_POST['add_venue'])) {
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $capacity = $_POST['capacity'];
        $facilities = trim($_POST['facilities']);
        
        $query = "INSERT INTO venues (name, address, capacity, facilities) 
                  VALUES (:name, :address, :capacity, :facilities)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindParam(':facilities', $facilities);
        
        if ($stmt->execute()) {
            showMessage("Venue added successfully!", "success");
        } else {
            showMessage("Failed to add venue!", "error");
        }
    } elseif (isset($_POST['update_venue'])) {
        $id = $_POST['venue_id'];
        $name = trim($_POST['name']);
        $address = trim($_POST['address']);
        $capacity = $_POST['capacity'];
        $facilities = trim($_POST['facilities']);
        
        $query = "UPDATE venues SET name = :name, address = :address, capacity = :capacity, 
                  facilities = :facilities WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':capacity', $capacity);
        $stmt->bindParam(':facilities', $facilities);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            showMessage("Venue updated successfully!", "success");
        } else {
            showMessage("Failed to update venue!", "error");
        }
    } elseif (isset($_POST['delete_venue'])) {
        $id = $_POST['venue_id'];
        
        // Check if venue is being used
        $check_query = "SELECT COUNT(*) FROM matches WHERE venue_id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $id);
        $check_stmt->execute();
        $usage_count = $check_stmt->fetchColumn();
        
        if ($usage_count > 0) {
            showMessage("Cannot delete venue - it's being used in $usage_count matches!", "error");
        } else {
            $query = "DELETE FROM venues WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                showMessage("Venue deleted successfully!", "success");
            } else {
                showMessage("Failed to delete venue!", "error");
            }
        }
    }
}

// Get all venues
$venues_query = "SELECT v.*, 
                 (SELECT COUNT(*) FROM matches WHERE venue_id = v.id) as match_count
                 FROM venues v ORDER BY v.name";
$venues_stmt = $db->prepare($venues_query);
$venues_stmt->execute();
$venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Venues - Sports League</title>
    <style>
        .container { max-width: 1000px; margin: 50px auto; padding: 20px; }
        .venue-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { height: 80px; resize: vertical; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: black; }
        
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f8f9fa; }
        
        .venue-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .venue-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .venue-details { color: #666; }
        .capacity-badge { background: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 500px; }
        .close { float: right; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .close:hover { color: black; }
    </style>
    <script>
        function showEditModal(venue) {
            document.getElementById('edit_venue_id').value = venue.id;
            document.getElementById('edit_name').value = venue.name;
            document.getElementById('edit_address').value = venue.address;
            document.getElementById('edit_capacity').value = venue.capacity;
            document.getElementById('edit_facilities').value = venue.facilities;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function confirmDelete(venueName) {
            return confirm(`Are you sure you want to delete "${venueName}"? This action cannot be undone.`);
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Manage Venues</h2>
        
        <?php displayMessage(); ?>
        
        <!-- Add New Venue Form -->
        <div class="venue-form">
            <h3>Add New Venue</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Venue Name:</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Capacity:</label>
                        <input type="number" name="capacity" min="10" max="100000">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address:</label>
                    <textarea name="address" required placeholder="Full address of the venue..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Facilities:</label>
                    <textarea name="facilities" placeholder="Available facilities (parking, restrooms, concessions, etc.)..."></textarea>
                </div>
                
                <button type="submit" name="add_venue" class="btn btn-success">Add Venue</button>
            </form>
        </div>
        
        <!-- Venues List -->
        <h3>Current Venues (<?php echo count($venues); ?>)</h3>
        
        <?php if (count($venues) > 0): ?>
            <?php foreach ($venues as $venue): ?>
                <div class="venue-card">
                    <div class="venue-header">
                        <div>
                            <h4><?php echo htmlspecialchars($venue['name']); ?></h4>
                            <span class="capacity-badge">Capacity: <?php echo number_format($venue['capacity']); ?></span>
                        </div>
                        <div>
                            <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($venue)); ?>)" class="btn btn-warning">Edit</button>
                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete('<?php echo htmlspecialchars($venue['name']); ?>')">
                                <input type="hidden" name="venue_id" value="<?php echo $venue['id']; ?>">
                                <button type="submit" name="delete_venue" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="venue-details">
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($venue['address']); ?></p>
                        <?php if ($venue['facilities']): ?>
                            <p><strong>Facilities:</strong> <?php echo htmlspecialchars($venue['facilities']); ?></p>
                        <?php endif; ?>
                        <p><strong>Matches Scheduled:</strong> <?php echo $venue['match_count']; ?></p>
                        <p><strong>Added:</strong> <?php echo date('M j, Y', strtotime($venue['created_at'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No venues added yet.</p>
        <?php endif; ?>
        
        <p><a href="../dashboard.php">← Back to Dashboard</a></p>
    </div>
    
    <!-- Edit Venue Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Edit Venue</h3>
            
            <form method="POST">
                <input type="hidden" id="edit_venue_id" name="venue_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Venue Name:</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Capacity:</label>
                        <input type="number" id="edit_capacity" name="capacity" min="10" max="100000">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address:</label>
                    <textarea id="edit_address" name="address" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Facilities:</label>
                    <textarea id="edit_facilities" name="facilities"></textarea>
                </div>
                
                <button type="submit" name="update_venue" class="btn btn-success">Update Venue</button>
                <button type="button" onclick="closeModal()" class="btn" style="background: #6c757d;">Cancel</button>
            </form>
        </div>
    </div>
</body>
</html>

<?php
// profile.php - User Profile Management
if (!isset($_POST['add_venue'])) {
    require_once 'config/database.php';
    requireLogin();
    
    $database = new Database();
    $db = $database->connect();
    $user = getCurrentUser();
    
    // Handle profile update
    if ($_POST && isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Check if email is already used by another user
        $check_query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->bindParam(':user_id', $user['id']);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $error = "Email is already in use by another user!";
        } else {
            $update_query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                            email = :email, phone = :phone WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':first_name', $first_name);
            $update_stmt->bindParam(':last_name', $last_name);
            $update_stmt->bindParam(':email', $email);
            $update_stmt->bindParam(':phone', $phone);
            $update_stmt->bindParam(':id', $user['id']);
            
            if ($update_stmt->execute()) {
                showMessage("Profile updated successfully!", "success");
                // Refresh user data
                $user = getCurrentUser();
            } else {
                $error = "Failed to update profile!";
            }
        }
    }
    
    // Handle password change
    if ($_POST && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $password_error = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $password_error = "New password must be at least 6 characters long!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = :password WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':id', $user['id']);
            
            if ($update_stmt->execute()) {
                showMessage("Password changed successfully!", "success");
            } else {
                $password_error = "Failed to change password!";
            }
        }
    }
    
    // Get user statistics
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM teams WHERE owner_id = :user_id) as owned_teams,
                    (SELECT COUNT(*) FROM team_members WHERE player_id = :user_id AND status = 'active') as team_memberships,
                    (SELECT COUNT(DISTINCT league_id) FROM team_members tm JOIN teams t ON tm.team_id = t.id WHERE tm.player_id = :user_id) as leagues_participated,
                    (SELECT SUM(matches_played) FROM player_stats WHERE player_id = :user_id) as total_matches";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->bindParam(':user_id', $user['id']);
    $stats_stmt->execute();
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - Sports League</title>
    <style>
        .container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .profile-header { background: linear-gradient(135deg, #007bff, #28a745); color: white; padding: 2rem; border-radius: 8px; text-align: center; margin-bottom: 30px; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.2); color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; margin: 0 auto 1rem; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 30px; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 0.5rem; }
        
        .form-section { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-row { display: flex; gap: 20px; }
        .form-row .form-group { flex: 1; }
        
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: black; }
        
        .error { color: red; margin-bottom: 15px; background: #f8d7da; padding: 10px; border-radius: 4px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #e9ecef; margin-right: 5px; cursor: pointer; border-radius: 4px 4px 0 0; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
    <script>
        function showTab(tabName) {
            var contents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            var tabs = document.getElementsByClassName('tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
            <p>@<?php echo htmlspecialchars($user['username']); ?> • <?php echo ucfirst($user['role']); ?></p>
            <p>Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
        </div>
        
        <!-- User Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['owned_teams'] ?? 0; ?></div>
                <div class="stat-label">Teams Owned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['team_memberships'] ?? 0; ?></div>
                <div class="stat-label">Team Memberships</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['leagues_participated'] ?? 0; ?></div>
                <div class="stat-label">Leagues Participated</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_stats['total_matches'] ?? 0; ?></div>
                <div class="stat-label">Matches Played</div>
            </div>
        </div>
        
        <?php displayMessage(); ?>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('profile')">Profile Information</div>
            <div class="tab" onclick="showTab('password')">Change Password</div>
            <div class="tab" onclick="showTab('notifications')">Notifications</div>
        </div>
        
        <!-- Profile Information Tab -->
        <div id="profile" class="tab-content active">
            <div class="form-section">
                <h3>Profile Information</h3>
                
                <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name:</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name:</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone:</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <small style="color: #666;">Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Role:</label>
                        <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" disabled>
                        <small style="color: #666;">Contact an administrator to change your role</small>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-success">Update Profile</button>
                </form>
            </div>
        </div>
        
        <!-- Change Password Tab -->
        <div id="password" class="tab-content">
            <div class="form-section">
                <h3>Change Password</h3>
                
                <?php if (isset($password_error)) echo "<div class='error'>$password_error</div>"; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password:</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password:</label>
                        <input type="password" name="new_password" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password:</label>
                        <input type="password" name="confirm_password" minlength="6" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                </form>
            </div>
        </div>
        
        <!-- Notifications Tab -->
        <div id="notifications" class="tab-content">
            <div class="form-section">
                <h3>Notification Preferences</h3>
                
                <div class="alert alert-info">
                    <strong>Coming Soon:</strong> Notification preferences will allow you to control what types of notifications you receive via email or in-app alerts.
                </div>
                
                <form>
                    <div class="form-group">
                        <label><input type="checkbox" checked disabled> Match reminders</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" checked disabled> Team invitations</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" checked disabled> League announcements</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" disabled> Weekly summary emails</label>
                    </div>
                    
                    <button type="button" class="btn" disabled>Save Preferences</button>
                </form>
            </div>
        </div>
        
        <p><a href="dashboard.php">← Back to Dashboard</a></p>
    </div>
</body>
</html>

<?php
}
?>