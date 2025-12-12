<?php
// team/manage_team.php - Manage Team with Enhanced Request Approval
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();

// Get team ID
$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get team details and verify ownership
$team_query = "SELECT t.*, l.name as league_name, l.season, s.name as sport_name, u.username as owner_username
               FROM teams t
               JOIN leagues l ON t.league_id = l.id
               JOIN sports s ON l.sport_id = s.id
               JOIN users u ON t.owner_id = u.id
               WHERE t.id = :team_id AND (t.owner_id = :user_id OR :is_admin = 1)";
$team_stmt = $db->prepare($team_query);
$team_stmt->bindParam(':team_id', $team_id);
$team_stmt->bindParam(':user_id', $user['id']);
$is_admin = ($user['role'] == 'admin') ? 1 : 0;
$team_stmt->bindParam(':is_admin', $is_admin);
$team_stmt->execute();
$team = $team_stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    showMessage("Team not found or access denied!", "error");
    redirect('../dashboard.php');
}

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    
    // Get request details
    $request_query = "SELECT rr.*, u.first_name, u.last_name, u.email
                      FROM registration_requests rr
                      JOIN users u ON rr.player_id = u.id
                      WHERE rr.id = :id AND rr.team_id = :team_id AND rr.status = 'pending'";
    $request_stmt = $db->prepare($request_query);
    $request_stmt->bindParam(':id', $request_id);
    $request_stmt->bindParam(':team_id', $team_id);
    $request_stmt->execute();
    $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($request) {
        if ($action == 'approve') {
            try {
                $db->beginTransaction();
                
                // Add player to team
                $add_member_query = "INSERT INTO team_members (team_id, player_id, position, joined_at, status) 
                                    VALUES (:team_id, :player_id, :position, NOW(), 'active')";
                $add_member_stmt = $db->prepare($add_member_query);
                $add_member_stmt->bindParam(':team_id', $team_id);
                $add_member_stmt->bindParam(':player_id', $request['player_id']);
                $add_member_stmt->bindParam(':position', $request['preferred_position']);
                $add_member_stmt->execute();
                
                // Update request status
                $update_query = "UPDATE registration_requests 
                                SET status = 'approved', processed_at = NOW(), processed_by = :processed_by 
                                WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':processed_by', $user['id']);
                $update_stmt->bindParam(':id', $request_id);
                $update_stmt->execute();
                
                // Create notification for player
                $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                      VALUES (:user_id, :title, :message, 'success', NOW())";
                $notification_stmt = $db->prepare($notification_query);
                $notification_stmt->bindParam(':user_id', $request['player_id']);
                $title = "Request Approved";
                $message = "Your request to join " . $team['name'] . " has been approved! Welcome to the team!";
                $notification_stmt->bindParam(':title', $title);
                $notification_stmt->bindParam(':message', $message);
                $notification_stmt->execute();
                
                $db->commit();
                showMessage("Player " . $request['first_name'] . " " . $request['last_name'] . " has been added to the team!", "success");
                
            } catch (Exception $e) {
                $db->rollBack();
                showMessage("Failed to approve request: " . $e->getMessage(), "error");
            }
            
        } else if ($action == 'reject') {
            try {
                // Update request status
                $update_query = "UPDATE registration_requests 
                                SET status = 'rejected', processed_at = NOW(), processed_by = :processed_by 
                                WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':processed_by', $user['id']);
                $update_stmt->bindParam(':id', $request_id);
                $update_stmt->execute();
                
                // Create notification for player
                $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                                      VALUES (:user_id, :title, :message, 'info', NOW())";
                $notification_stmt = $db->prepare($notification_query);
                $notification_stmt->bindParam(':user_id', $request['player_id']);
                $title = "Request Declined";
                $message = "Your request to join " . $team['name'] . " has been declined. You may try again later.";
                $notification_stmt->bindParam(':title', $title);
                $notification_stmt->bindParam(':message', $message);
                $notification_stmt->execute();
                
                showMessage("Request has been declined.", "info");
                
            } catch (Exception $e) {
                showMessage("Failed to reject request: " . $e->getMessage(), "error");
            }
        }
        
        // Refresh page
        header("Location: manage_team.php?id=$team_id");
        exit();
    }
}

// Get team members
$members_query = "SELECT tm.*, u.username, u.first_name, u.last_name, u.email
                  FROM team_members tm
                  JOIN users u ON tm.player_id = u.id
                  WHERE tm.team_id = :team_id AND tm.status = 'active'
                  ORDER BY tm.joined_at ASC";
$members_stmt = $db->prepare($members_query);
$members_stmt->bindParam(':team_id', $team_id);
$members_stmt->execute();
$members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests
$requests_query = "SELECT rr.*, u.username, u.first_name, u.last_name, u.email
                   FROM registration_requests rr
                   JOIN users u ON rr.player_id = u.id
                   WHERE rr.team_id = :team_id AND rr.status = 'pending'
                   ORDER BY rr.created_at DESC";
$requests_stmt = $db->prepare($requests_query);
$requests_stmt->bindParam(':team_id', $team_id);
$requests_stmt->execute();
$pending_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Team - <?php echo htmlspecialchars($team['name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .header { background: #343a40; color: white; padding: 1rem 2rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .team-header { background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .team-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .info-item { background: #f8f9fa; padding: 1rem; border-radius: 4px; }
        .info-label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .info-value { font-size: 18px; font-weight: bold; color: #333; }
        .section { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section-title { font-size: 1.5rem; margin-bottom: 1rem; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem; }
        .request-card { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
        .request-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .request-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .detail-item { padding: 0.5rem 0; border-bottom: 1px solid #e9ecef; }
        .detail-label { font-size: 12px; color: #666; margin-bottom: 3px; }
        .detail-value { font-size: 14px; color: #333; }
        .document-preview { margin-top: 1rem; }
        .document-link { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; }
        .document-link:hover { background: #0056b3; }
        .btn { padding: 8px 16px; border: none; cursor: pointer; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 0.5rem; font-size: 14px; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn:hover { opacity: 0.9; }
        .member-item { background: #f8f9fa; padding: 1rem; border-radius: 4px; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-success { background: #d4edda; color: #155724; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background: white; margin: 2% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 700px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; color: #666; }
        .close:hover { color: #000; }
    </style>
    <script>
        function viewRequest(requestId) {
            document.getElementById('requestModal_' + requestId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function confirmAction(requestId, action, playerName) {
            const actionText = action === 'approve' ? 'approve' : 'reject';
            const message = `Are you sure you want to ${actionText} the request from ${playerName}?`;
            
            if (confirm(message)) {
                document.getElementById('action_' + requestId).value = action;
                document.getElementById('form_' + requestId).submit();
            }
        }
        
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <h1>Manage Team: <?php echo htmlspecialchars($team['name']); ?></h1>
    </div>
    
    <div class="container">
        <?php displayMessage(); ?>
        
        <p style="margin-bottom: 20px;">
            <a href="../dashboard.php" style="color: #007bff; text-decoration: none;">← Back to Dashboard</a>
        </p>
        
        <!-- Team Overview -->
        <div class="team-header">
            <h2><?php echo htmlspecialchars($team['name']); ?></h2>
            <div class="team-info">
                <div class="info-item">
                    <div class="info-label">League</div>
                    <div class="info-value"><?php echo htmlspecialchars($team['league_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Sport</div>
                    <div class="info-value"><?php echo htmlspecialchars($team['sport_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Season</div>
                    <div class="info-value"><?php echo htmlspecialchars($team['season']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Record</div>
                    <div class="info-value"><?php echo $team['wins']; ?>W-<?php echo $team['draws']; ?>D-<?php echo $team['losses']; ?>L</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Points</div>
                    <div class="info-value"><?php echo $team['points']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Members</div>
                    <div class="info-value"><?php echo count($members); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Pending Requests Section -->
        <div class="section">
            <div class="section-title">
                Pending Join Requests 
                <?php if (count($pending_requests) > 0): ?>
                    <span class="badge badge-warning"><?php echo count($pending_requests); ?> pending</span>
                <?php endif; ?>
            </div>
            
            <?php if (count($pending_requests) > 0): ?>
                <?php foreach ($pending_requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div>
                                <h3><?php echo htmlspecialchars($request['full_name']); ?></h3>
                                <p style="color: #666; font-size: 14px;">
                                    Username: <?php echo htmlspecialchars($request['username']); ?> | 
                                    Email: <?php echo htmlspecialchars($request['email']); ?>
                                </p>
                                <p style="color: #666; font-size: 12px; margin-top: 5px;">
                                    Requested on: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                </p>
                            </div>
                            <div>
                                <button onclick="viewRequest(<?php echo $request['id']; ?>)" class="btn btn-info">View Details</button>
                            </div>
                        </div>
                        
                        <div class="request-details">
                            <div class="detail-item">
                                <div class="detail-label">Age</div>
                                <div class="detail-value"><?php echo $request['age']; ?> years old</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Birthday</div>
                                <div class="detail-value"><?php echo date('F j, Y', strtotime($request['birthday'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Preferred Position</div>
                                <div class="detail-value"><?php echo htmlspecialchars($request['preferred_position']); ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <form id="form_<?php echo $request['id']; ?>" method="POST" style="display: inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" id="action_<?php echo $request['id']; ?>" name="action" value="">
                                
                                <button type="button" onclick="confirmAction(<?php echo $request['id']; ?>, 'approve', '<?php echo htmlspecialchars($request['full_name'], ENT_QUOTES); ?>')" class="btn btn-success">
                                    Approve Request
                                </button>
                                <button type="button" onclick="confirmAction(<?php echo $request['id']; ?>, 'reject', '<?php echo htmlspecialchars($request['full_name'], ENT_QUOTES); ?>')" class="btn btn-danger">
                                    Decline Request
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Request Details Modal -->
                    <div id="requestModal_<?php echo $request['id']; ?>" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3>Request Details: <?php echo htmlspecialchars($request['full_name']); ?></h3>
                                <span class="close" onclick="closeModal('requestModal_<?php echo $request['id']; ?>')">&times;</span>
                            </div>
                            
                            <div style="margin-bottom: 1.5rem;">
                                <div class="detail-item">
                                    <div class="detail-label">Full Name</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['full_name']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Username</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['username']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Email</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['email']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Birthday</div>
                                    <div class="detail-value"><?php echo date('F j, Y', strtotime($request['birthday'])); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Age</div>
                                    <div class="detail-value"><?php echo $request['age']; ?> years old</div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Current Address</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['current_address']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Sitio/Purok</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['sitio']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Preferred Position</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['preferred_position']); ?></div>
                                </div>
                                
                                <?php if ($request['message']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Message</div>
                                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($request['message'])); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($request['document_path']): ?>
                                <div class="document-preview">
                                    <div class="detail-label">PSA/NSO Birth Certificate</div>
                                    <a href="../<?php echo htmlspecialchars($request['document_path']); ?>" target="_blank" class="document-link">
                                        View Document
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="border-top: 1px solid #e9ecef; padding-top: 1rem;">
                                <p style="font-size: 12px; color: #666; margin-bottom: 1rem;">
                                    Requested on: <?php echo date('F j, Y g:i A', strtotime($request['created_at'])); ?>
                                </p>
                                
                                <button type="button" onclick="confirmAction(<?php echo $request['id']; ?>, 'approve', '<?php echo htmlspecialchars($request['full_name'], ENT_QUOTES); ?>')" class="btn btn-success">
                                    Approve Request
                                </button>
                                <button type="button" onclick="confirmAction(<?php echo $request['id']; ?>, 'reject', '<?php echo htmlspecialchars($request['full_name'], ENT_QUOTES); ?>')" class="btn btn-danger">
                                    Decline Request
                                </button>
                                <button type="button" onclick="closeModal('requestModal_<?php echo $request['id']; ?>')" class="btn" style="background: #6c757d; color: white;">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    No pending join requests at this time.
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Team Members Section -->
        <div class="section">
            <div class="section-title">
                Team Members 
                <span class="badge badge-success"><?php echo count($members); ?> members</span>
            </div>
            
            <?php if (count($members) > 0): ?>
                <?php foreach ($members as $member): ?>
                    <div class="member-item">
                        <div>
                            <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong><br>
                            <small style="color: #666;">
                                <?php echo htmlspecialchars($member['username']); ?> | 
                                Position: <?php echo htmlspecialchars($member['position']); ?> | 
                                Joined: <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                            </small>
                        </div>
                        <div>
                            <span class="badge badge-success">Active</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    No team members yet. Approve join requests to build your team!
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>