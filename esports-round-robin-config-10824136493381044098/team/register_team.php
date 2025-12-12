<?php
// team/register_team.php - Team Registration Request Form
require_once '../config/database.php';
requireRole('team_owner');

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();
$league_id = $_GET['league_id'] ?? null;

if (!$league_id) {
    header('Location: ../league/browse_leagues.php');
    exit;
}

// Get league information
$league_query = "SELECT l.*, s.name as sport_name, s.max_players_per_team,
                        (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as current_teams,
                        (SELECT COUNT(*) FROM team_registration_requests 
                         WHERE league_id = l.id AND team_owner_id = :user_id 
                         AND status = 'pending') as pending_requests
                 FROM leagues l
                 JOIN sports s ON l.sport_id = s.id
                 WHERE l.id = :league_id";
$league_stmt = $db->prepare($league_query);
$league_stmt->bindParam(':league_id', $league_id);
$league_stmt->bindParam(':user_id', $current_user['id']);
$league_stmt->execute();
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

if (!$league) {
    showMessage("League not found!", "error");
    header('Location: ../league/browse_leagues.php');
    exit;
}

// Check if user already has a team in this league
$existing_team_query = "SELECT * FROM teams WHERE league_id = :league_id AND owner_id = :owner_id";
$existing_stmt = $db->prepare($existing_team_query);
$existing_stmt->bindParam(':league_id', $league_id);
$existing_stmt->bindParam(':owner_id', $current_user['id']);
$existing_stmt->execute();
$existing_team = $existing_stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_team) {
    showMessage("You already have a team registered in this league!", "error");
    header("Location: ../team/view_team.php?id=" . $existing_team['id']);
    exit;
}

// Check if league is full
$is_full = $league['current_teams'] >= $league['max_teams'];

// Check if registration deadline has passed
$deadline_passed = strtotime($league['registration_deadline']) < time();

// Handle form submission
if (isset($_POST['submit_request'])) {
    $team_name = trim($_POST['team_name']);
    $request_message = trim($_POST['request_message']);
    
    // Validation
    if (empty($team_name)) {
        showMessage("Team name is required!", "error");
    } elseif ($league['pending_requests'] > 0) {
        showMessage("You already have a pending registration request for this league!", "error");
    } elseif ($is_full) {
        showMessage("This league is already at maximum capacity!", "error");
    } elseif ($deadline_passed) {
        showMessage("Registration deadline has passed for this league!", "error");
    } elseif ($league['status'] != 'open' && $league['status'] != 'active') {
        showMessage("This league is not accepting registrations!", "error");
    } else {
        // Check if approval is required
        if ($league['approval_required']) {
            // Create registration request
            $insert_query = "INSERT INTO team_registration_requests 
                            (league_id, team_name, team_owner_id, request_message, status, created_at)
                            VALUES (:league_id, :team_name, :owner_id, :message, 'pending', NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':league_id', $league_id);
            $insert_stmt->bindParam(':team_name', $team_name);
            $insert_stmt->bindParam(':owner_id', $current_user['id']);
            $insert_stmt->bindParam(':message', $request_message);
            
            if ($insert_stmt->execute()) {
                showMessage("Registration request submitted successfully! Please wait for admin approval.", "success");
                // Redirect after a delay
                echo '<script>setTimeout(function(){ window.location.href = "../league/view_league.php?id=' . $league_id . '"; }, 3000);</script>';
            } else {
                showMessage("Failed to submit registration request!", "error");
            }
        } else {
            // Direct registration (no approval required)
            $insert_team = "INSERT INTO teams (name, league_id, owner_id, status, created_at)
                           VALUES (:name, :league_id, :owner_id, 'active', NOW())";
            $team_stmt = $db->prepare($insert_team);
            $team_stmt->bindParam(':name', $team_name);
            $team_stmt->bindParam(':league_id', $league_id);
            $team_stmt->bindParam(':owner_id', $current_user['id']);
            
            if ($team_stmt->execute()) {
                $team_id = $db->lastInsertId();
                showMessage("Team registered successfully!", "success");
                echo '<script>setTimeout(function(){ window.location.href = "../team/view_team.php?id=' . $team_id . '"; }, 2000);</script>';
            } else {
                showMessage("Failed to register team!", "error");
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Team - <?php echo htmlspecialchars($league['name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 { font-size: 1.8rem; }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .league-info-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border-left: 4px solid #007bff;
        }
        
        .league-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .league-meta {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .registration-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
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
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-open { background: #28a745; color: white; }
        .status-active { background: #007bff; color: white; }
        .status-closed { background: #dc3545; color: white; }
        .status-full { background: #ffc107; color: black; }
        
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .registration-form { padding: 1.5rem; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Register Your Team</h1>
    </div>
    
    <div class="container">
        <?php displayMessage(); ?>
        
        <!-- League Information -->
        <div class="league-info-card">
            <div class="league-title">
                <?php echo htmlspecialchars($league['name']); ?>
                <span class="status-badge status-<?php echo $league['status']; ?>">
                    <?php echo ucfirst($league['status']); ?>
                </span>
                <?php if ($is_full): ?>
                    <span class="status-badge status-full">FULL</span>
                <?php endif; ?>
            </div>
            <div class="league-meta">
                <?php echo htmlspecialchars($league['sport_name']); ?> • 
                <?php echo htmlspecialchars($league['season']); ?>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Registration Deadline</span>
                    <span class="info-value">
                        <?php echo date('F j, Y', strtotime($league['registration_deadline'])); ?>
                        <?php if ($deadline_passed): ?>
                            <span style="color: #dc3545; font-weight: normal; font-size: 0.9rem;">(Expired)</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">League Duration</span>
                    <span class="info-value">
                        <?php echo date('M j', strtotime($league['start_date'])); ?> - 
                        <?php echo date('M j, Y', strtotime($league['end_date'])); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teams</span>
                    <span class="info-value">
                        <?php echo $league['current_teams']; ?> / <?php echo $league['max_teams']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Max Players per Team</span>
                    <span class="info-value"><?php echo $league['max_players_per_team']; ?></span>
                </div>
            </div>
            
            <?php if ($league['approval_required']): ?>
            <div class="alert alert-info" style="margin-top: 1rem;">
                <strong>ℹ️ Approval Required:</strong> This league requires admin approval before teams can join. 
                Your registration request will be reviewed by the league administrators.
            </div>
            <?php endif; ?>
            
            <?php if ($league['rules']): ?>
            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ddd;">
                <strong>League Rules:</strong>
                <p style="margin-top: 0.5rem; white-space: pre-wrap; color: #666;">
                    <?php echo htmlspecialchars($league['rules']); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Registration Form -->
        <?php if ($league['pending_requests'] > 0): ?>
            <div class="alert alert-warning">
                <strong>⏳ Pending Request:</strong> You already have a pending registration request for this league. 
                Please wait for the admin to review your request.
                <br><br>
                <a href="../league/view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary">
                    Back to League
                </a>
            </div>
        <?php elseif ($is_full): ?>
            <div class="alert alert-error">
                <strong>❌ League Full:</strong> This league has reached its maximum capacity. 
                No new teams can be registered at this time.
                <br><br>
                <a href="../league/view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary">
                    Back to League
                </a>
            </div>
        <?php elseif ($deadline_passed): ?>
            <div class="alert alert-error">
                <strong>❌ Registration Closed:</strong> The registration deadline for this league has passed.
                <br><br>
                <a href="../league/view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary">
                    Back to League
                </a>
            </div>
        <?php elseif ($league['status'] != 'open' && $league['status'] != 'active'): ?>
            <div class="alert alert-error">
                <strong>❌ Not Accepting Registrations:</strong> This league is not currently accepting team registrations.
                <br><br>
                <a href="../league/view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary">
                    Back to League
                </a>
            </div>
        <?php else: ?>
            <div class="registration-form">
                <h3 style="margin-bottom: 1.5rem;">
                    <?php echo $league['approval_required'] ? '📝 Submit Registration Request' : '✅ Register Your Team'; ?>
                </h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="team_name">Team Name *</label>
                        <input type="text" 
                               id="team_name" 
                               name="team_name" 
                               class="form-control" 
                               required
                               maxlength="100"
                               placeholder="Enter your team name">
                        <div class="form-help">
                            Choose a unique and appropriate name for your team
                        </div>
                    </div>
                    
                    <?php if ($league['approval_required']): ?>
                    <div class="form-group">
                        <label for="request_message">Message to Admin (Optional)</label>
                        <textarea id="request_message" 
                                  name="request_message" 
                                  class="form-control"
                                  placeholder="Tell the admin why you'd like to join this league, your team's background, or any other relevant information..."></textarea>
                        <div class="form-help">
                            This message will be reviewed by the league administrators along with your request
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <strong>Next Steps:</strong>
                        <?php if ($league['approval_required']): ?>
                            <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                                <li>Your registration request will be submitted to the league administrators</li>
                                <li>Admin will review your profile, team name, and message</li>
                                <li>You'll be notified once your request is approved or rejected</li>
                                <li>After approval, you can add players to your team</li>
                            </ul>
                        <?php else: ?>
                            <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                                <li>Your team will be immediately registered in this league</li>
                                <li>You can start adding players to your team right away</li>
                                <li>Make sure to review the league rules and schedule</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="submit_request" class="btn btn-primary">
                            <?php echo $league['approval_required'] ? 'Submit Request' : 'Register Team'; ?>
                        </button>
                        <a href="../league/view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>