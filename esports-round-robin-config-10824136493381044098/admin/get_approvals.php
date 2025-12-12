<?php
// admin/get_approvals.php - Fetch team registration approval requests
require_once '../config/database.php';
requireRole('admin');

$database = new Database();
$db = $database->connect();

$league_id = $_GET['league_id'] ?? null;

// Build query based on filters
$where_clause = "WHERE trr.status = 'pending'";
$params = [];

if ($league_id) {
    $where_clause .= " AND trr.league_id = :league_id";
    $params[':league_id'] = $league_id;
}

$requests_query = "SELECT trr.*, l.name as league_name, l.season, l.max_teams, s.name as sport_name, u.id as owner_id, u.username, u.email, u.first_name, u.last_name, u.phone, u.created_at as user_joined, (SELECT COUNT(*) FROM teams WHERE owner_id = u.id) as teams_owned, (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as current_teams FROM team_registration_requests trr JOIN leagues l ON trr.league_id = l.id JOIN sports s ON l.sport_id = s.id JOIN users u ON trr.team_owner_id = u.id $where_clause ORDER BY trr.created_at ASC";

$requests_stmt = $db->prepare($requests_query);
foreach ($params as $key => $value) {
    $requests_stmt->bindValue($key, $value);
}
$requests_stmt->execute();
$requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($requests)) {
    echo '<div style="text-align: center; padding: 2rem; color: #666;">
            <h4>No pending approval requests</h4>
            <p>All team registration requests have been processed.</p>
          </div>';
    exit;
}

// Display each request
foreach ($requests as $request):
    $is_full = $request['current_teams'] >= $request['max_teams'];

$members_query = "SELECT tm.*, u.username, u.email, u.first_name, u.last_name, u.phone 
                  FROM team_members tm 
                  JOIN users u ON tm.player_id = u.id 
                  JOIN teams t ON tm.team_id = t.id 
                  WHERE t.owner_id = :owner_id 
                  ORDER BY tm.joined_at DESC 
                  LIMIT 10";
                  ?>
<div class="approval-request-card">
    <div class="request-header">
        <div class="request-info">
            <h4><?php echo htmlspecialchars($request['team_name']); ?></h4>
            <div class="request-meta">
                <strong><?php echo htmlspecialchars($request['league_name']); ?></strong> 
                (<?php echo htmlspecialchars($request['sport_name']); ?>) • 
                <?php echo htmlspecialchars($request['season']); ?>
                <br>
                <small>Submitted: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></small>
                <?php if ($is_full): ?>
                    <br><span style="color: #dc3545; font-weight: 600;">⚠️ League is at maximum capacity (<?php echo $request['current_teams']; ?>/<?php echo $request['max_teams']; ?>)</span>
                <?php else: ?>
                    <br><span style="color: #666;">Current teams: <?php echo $request['current_teams']; ?>/<?php echo $request['max_teams']; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($request['request_message']): ?>
    <div style="background: #f8f9fa; padding: 1rem; border-radius: 4px; margin: 1rem 0;">
        <strong>Team Owner's Message:</strong>
        <p style="margin-top: 0.5rem; white-space: pre-wrap;"><?php echo htmlspecialchars($request['request_message']); ?></p>
    </div>
    <?php endif; ?>

    <!-- Team Owner Information -->
    <div class="team-owner-info">
        <h5 style="margin-bottom: 1rem;">👤 Team Owner Information</h5>
        <div class="owner-details">
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Username</span>
                <span class="detail-value"><?php echo htmlspecialchars($request['username']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">
                    <a href="mailto:<?php echo htmlspecialchars($request['email']); ?>" style="color: #007bff; text-decoration: none;">
                        <?php echo htmlspecialchars($request['email']); ?>
                    </a>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo $request['phone'] ? htmlspecialchars($request['phone']) : 'N/A'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Member Since</span>
                <span class="detail-value"><?php echo date('M j, Y', strtotime($request['user_joined'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Teams Owned</span>
                <span class="detail-value"><?php echo $request['teams_owned']; ?> team(s)</span>
            </div>
        </div>
    </div>

    <!-- Recent Team Members from Other Teams -->
    <?php if (!empty($recent_members)): ?>
    <div class="team-members-section">
        <h5 style="margin-bottom: 0.5rem;">👥 Recent Team Members (From Owner's Other Teams)</h5>
        <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
            These are members from other teams owned by <?php echo htmlspecialchars($request['first_name']); ?>
        </p>
        <div class="members-grid">
            <?php foreach ($recent_members as $member): ?>
            <div class="member-card">
                <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                <div class="member-role" style="margin-bottom: 0.25rem;"><?php echo htmlspecialchars($member['username']); ?></div>
                <div style="font-size: 0.8rem; color: #999;"><?php echo htmlspecialchars($member['email']); ?></div>
                <div style="font-size: 0.75rem; color: #999; margin-top: 0.25rem;">
                    Joined: <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approval Actions -->
    <form method="POST" class="approval-actions" onsubmit="return confirmApproval(event, '<?php echo $request['approval_action'] ?? 'approve'; ?>')">
        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
        <div class="form-group" style="margin-bottom: 1rem;">
            <label>Admin Notes (Optional)</label>
            <textarea name="admin_notes" class="form-control" placeholder="Add any notes about this approval/rejection..." rows="2"></textarea>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" name="approval_action" value="approve" class="btn btn-success btn-sm" <?php echo $is_full ? 'disabled title="League is at maximum capacity"' : ''; ?>>
                ✓ Approve Registration
            </button>
            <button type="submit" name="approval_action" value="reject" class="btn btn-danger btn-sm">✗ Reject Registration</button>
            <a href="../user/view_profile.php?id=<?php echo $request['owner_id']; ?>" class="btn btn-info btn-sm" target="_blank">👁️ View Full Profile</a>
        </div>
    </form>
</div>

<?php endforeach; ?>

<script>
function confirmApproval(event, action) {
    event.preventDefault();
    const form = event.target;
    const teamName = form.closest('.approval-request-card').querySelector('h4').textContent;
    let message = '';
    if (action === 'approve') {
        message = `Are you sure you want to APPROVE "${teamName}" to join this league?\n\nThis will create the team and allow them to participate.`;
    } else {
        message = `Are you sure you want to REJECT "${teamName}"?\n\nThe team owner will need to submit a new request to join.`;
    }
    if (confirm(message)) {
        form.submit();
    }
    return false;
}
</script>

<style>
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
</style>
