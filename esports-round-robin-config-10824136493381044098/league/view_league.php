<?php
// league/view_league.php - Complete League Viewing System
require_once '../config/database.php';
requireLogin();

$league_id = $_GET['id'] ?? null;
if (!$league_id) {
    showMessage("League ID is required!", "error");
    redirect('browse_leagues.php');
}

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Get comprehensive league details
$league_query = "SELECT l.*, s.name as sport_name, s.description as sport_description, s.max_players_per_team,
                        u.first_name as creator_first, u.last_name as creator_last, u.username as creator_username,
                        (SELECT COUNT(*) FROM teams WHERE league_id = l.id) as current_teams
                 FROM leagues l
                 JOIN sports s ON l.sport_id = s.id
                 JOIN users u ON l.created_by = u.id
                 WHERE l.id = :league_id";
$league_stmt = $db->prepare($league_query);
$league_stmt->bindParam(':league_id', $league_id);
$league_stmt->execute();
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

if (!$league) {
    showMessage("League not found!", "error");
    redirect('browse_leagues.php');
}

// Check if user already has a team in this league
$existing_team_query = "SELECT * FROM teams WHERE league_id = :league_id AND owner_id = :owner_id";
$existing_stmt = $db->prepare($existing_team_query);
$existing_stmt->bindParam(':league_id', $league_id);
$existing_stmt->bindParam(':owner_id', $current_user['id']);
$existing_stmt->execute();
$user_team = $existing_stmt->fetch(PDO::FETCH_ASSOC);

// Check for pending registration request
$pending_request_query = "SELECT * FROM team_registration_requests
                          WHERE league_id = :league_id AND team_owner_id = :owner_id AND status = 'pending'";
$pending_stmt = $db->prepare($pending_request_query);
$pending_stmt->bindParam(':league_id', $league_id);
$pending_stmt->bindParam(':owner_id', $current_user['id']);
$pending_stmt->execute();
$pending_request = $pending_stmt->fetch(PDO::FETCH_ASSOC);

// Get all user's teams in this league
$user_teams_in_league = [];
if ($current_user['role'] != 'admin') {
    $user_teams_query = "SELECT t.* FROM teams t
                        WHERE t.league_id = :league_id
                        AND (t.owner_id = :user_id OR t.id IN (
                            SELECT team_id FROM team_members WHERE player_id = :user_id AND status = 'active'
                        ))";
    $user_teams_stmt = $db->prepare($user_teams_query);
    $user_teams_stmt->bindParam(':league_id', $league_id);
    $user_teams_stmt->bindParam(':user_id', $current_user['id']);
    $user_teams_stmt->execute();
    $user_teams_in_league = $user_teams_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get teams with detailed information
$teams_query = "SELECT t.*, u.first_name, u.last_name, u.username,
                       (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND status = 'active') as member_count,
                       (SELECT COUNT(*) FROM matches WHERE (home_team_id = t.id OR away_team_id = t.id) AND status = 'completed') as matches_played
                FROM teams t
                JOIN users u ON t.owner_id = u.id
                WHERE t.league_id = :league_id
                ORDER BY t.points DESC, t.wins DESC, t.name ASC";
$teams_stmt = $db->prepare($teams_query);
$teams_stmt->bindParam(':league_id', $league_id);
$teams_stmt->execute();
$teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all matches for this league
$matches_query = "SELECT m.*, ht.name as home_team, at.name as away_team,
                         v.name as venue_name, v.address as venue_address,
                         m.home_team_id, m.away_team_id
                  FROM matches m
                  JOIN teams ht ON m.home_team_id = ht.id
                  JOIN teams at ON m.away_team_id = at.id
                  LEFT JOIN venues v ON m.venue_id = v.id
                  WHERE m.league_id = :league_id
                  ORDER BY m.match_date DESC";
$matches_stmt = $db->prepare($matches_query);
$matches_stmt->bindParam(':league_id', $league_id);
$matches_stmt->execute();
$matches = $matches_stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate matches by status
$upcoming_matches = array_filter($matches, function($match) {
    return $match['status'] == 'scheduled' && strtotime($match['match_date']) >= time();
});

$recent_matches = array_filter($matches, function($match) {
    return $match['status'] == 'completed';
});

// Get top scorers
$top_scorers_query = "SELECT ps.*, u.first_name, u.last_name, u.username, t.name as team_name
                      FROM player_stats ps
                      JOIN users u ON ps.player_id = u.id
                      JOIN teams t ON ps.team_id = t.id
                      WHERE ps.league_id = :league_id AND ps.goals > 0
                      ORDER BY ps.goals DESC, ps.assists DESC
                      LIMIT 10";
$top_scorers_stmt = $db->prepare($top_scorers_query);
$top_scorers_stmt->bindParam(':league_id', $league_id);
$top_scorers_stmt->execute();
$top_scorers = $top_scorers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_matches = count($matches);
$completed_matches = count($recent_matches);
$completion_percentage = $total_matches > 0 ? round(($completed_matches / $total_matches) * 100, 1) : 0;
$total_goals = array_sum(array_column($recent_matches, 'home_score')) + array_sum(array_column($recent_matches, 'away_score'));
$avg_goals_per_match = $completed_matches > 0 ? round($total_goals / $completed_matches, 1) : 0;

// Check playoff eligibility
$round_robin_rounds = $league['round_robin_rounds'] ?? 1;
$teams_count = count($teams);
$matches_per_round = ($teams_count >= 2) ? ($teams_count * ($teams_count - 1) / 2) : 0;
$total_expected_rr_matches = $matches_per_round * $round_robin_rounds;

$completed_rr_matches = 0;
foreach ($matches as $match) {
    if ($match['status'] == 'completed' && ($match['match_type'] == 'round_robin' || empty($match['match_type']))) {
        $completed_rr_matches++;
    }
}
$is_round_robin_done = ($total_expected_rr_matches > 0 && $completed_rr_matches >= $total_expected_rr_matches);

// Check permissions
$can_manage = ($current_user['role'] == 'admin' || $current_user['id'] == $league['created_by']);
$is_full = $league['current_teams'] >= $league['max_teams'];
$deadline_passed = strtotime($league['registration_deadline']) < time();
$can_register = !$user_team && !$pending_request && !$is_full && !$deadline_passed && in_array($league['status'], ['open', 'active']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($league['name']); ?> - League Details</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Arial', sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .hero-section {
            background: linear-gradient(135deg, black, #0056b3);
            color: white;
            padding: 3rem 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            opacity: 0.3;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .league-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .league-title h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .league-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .league-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 0.8rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: bold;
            margin-top: 0.25rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin: 0.25rem;
        }

        .status-draft { background: #6c757d; color: white; }
        .status-open { background: #28a745; color: white; }
        .status-active { background: #007bff; color: white; }
        .status-closed { background: #dc3545; color: white; }
        .status-completed { background: #17a2b8; color: white; }

        .quick-stats {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .quick-stat {
            text-align: center;
        }

        .quick-stat .number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .quick-stat .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .container {
            max-width: 1200px;
            margin: -3rem auto 2rem;
            padding: 0 2rem;
            position: relative;
            z-index: 10;
        }

        .registration-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .content-tabs {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .tab-nav {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            overflow-x: auto;
        }

        .tab-nav button {
            flex: 1;
            min-width: 120px;
            padding: 1rem;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab-nav button.active {
            background: white;
            color: #007bff;
            border-bottom: 3px solid #007bff;
        }

        .tab-nav button:hover:not(.active) {
            background: #e9ecef;
            color: #333;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .standings-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            overflow-x: auto;
        }

        .standings-table th,
        .standings-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .standings-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .standings-table tr:hover {
            background: #f8f9fa;
        }

        .position {
            width: 50px;
            text-align: center;
            font-weight: bold;
            color: #007bff;
        }

        .team-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .team-logo {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .team-details h4 {
            margin: 0;
            font-size: 1rem;
        }

        .team-details small {
            color: #666;
            display: block;
        }

        .matches-grid {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }

        .match-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .match-card:hover {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .match-date {
            font-weight: bold;
            color: #007bff;
        }

        .match-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .match-scheduled { background: #fff3cd; color: #856404; }
        .match-completed { background: #d4edda; color: #155724; }
        .match-in_progress { background: #cce7ff; color: #004085; }
        .match-cancelled { background: #f8d7da; color: #721c24; }

        .match-teams {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .team-side {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .team-side.away {
            flex-direction: row-reverse;
            text-align: right;
        }

        .score-display {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            min-width: 80px;
            text-align: center;
        }

        .match-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #666;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .players-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .player-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .player-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .player-rank {
            font-size: 1.2rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }

        .player-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .player-team {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .player-stats {
            display: flex;
            justify-content: center;
            gap: 1rem;
            font-size: 0.9rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; transform: translateY(-1px); }

        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; transform: translateY(-1px); }

        .btn-warning { background: #ffc107; color: black; }
        .btn-warning:hover { background: #e0a800; transform: translateY(-1px); }

        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; transform: translateY(-1px); }

        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; transform: translateY(-1px); }

        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #999;
        }

        @media (max-width: 768px) {
            .hero-section { padding: 2rem 1rem; }
            .league-header { flex-direction: column; }
            .league-title h1 { font-size: 1.8rem; }
            .quick-stats { grid-template-columns: repeat(2, 1fr); }
            .container { padding: 0 1rem; margin-top: -2rem; }
            .tab-nav { overflow-x: auto; }
            .match-teams { flex-direction: column; gap: 1rem; }
            .team-side.away { flex-direction: row; text-align: left; }
            .players-grid { grid-template-columns: 1fr; }
            .standings-table { font-size: 0.85rem; display: block; overflow-x: auto; }
            .standings-table th, .standings-table td { padding: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="hero-content">
            <div class="league-header">
                <div class="league-title">
                    <h1><?php echo htmlspecialchars($league['name']); ?></h1>
                    <div class="league-subtitle">
                        <?php echo htmlspecialchars($league['sport_name']); ?> • <?php echo htmlspecialchars($league['season']); ?>
                    </div>

                    <div class="league-meta">
                        <div class="meta-item">
                            <span class="meta-label">Duration</span>
                            <span class="meta-value">
                                <?php echo date('M j', strtotime($league['start_date'])); ?> -
                                <?php echo date('M j, Y', strtotime($league['end_date'])); ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Registration</span>
                            <span class="meta-value">
                                Until <?php echo date('M j, Y', strtotime($league['registration_deadline'])); ?>
                            </span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Created by</span>
                            <span class="meta-value">
                                <?php echo htmlspecialchars($league['creator_first'] . ' ' . $league['creator_last']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="league-status">
                    <div class="status-badge status-<?php echo $league['status']; ?>">
                        <?php echo ucfirst($league['status']); ?>
                    </div>
                    <?php if ($league['approval_required']): ?>
                        <div class="status-badge" style="background: #ffc107; color: black;">
                            Requires Approval
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="number"><?php echo count($teams); ?>/<?php echo $league['max_teams']; ?></div>
                    <div class="label">Teams</div>
                </div>
                <div class="quick-stat">
                    <div class="number"><?php echo $total_matches; ?></div>
                    <div class="label">Total Matches</div>
                </div>
                <div class="quick-stat">
                    <div class="number"><?php echo $completed_matches; ?></div>
                    <div class="label">Completed</div>
                </div>
                <div class="quick-stat">
                    <div class="number"><?php echo $completion_percentage; ?>%</div>
                    <div class="label">Progress</div>
                </div>
                <div class="quick-stat">
                    <div class="number"><?php echo $total_goals; ?></div>
                    <div class="label">Total Goals</div>
                </div>
                <div class="quick-stat">
                    <div class="number"><?php echo $avg_goals_per_match; ?></div>
                    <div class="label">Avg Goals/Match</div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php displayMessage(); ?>

        <div class="registration-card">
            <?php if ($user_team): ?>
                <div class="alert alert-success">
                    <strong>You're registered!</strong> Your team "<?php echo htmlspecialchars($user_team['name']); ?>" is in this league.
                    <br><br>
                    <a href="../team/manage_team.php?id=<?php echo $user_team['id']; ?>" class="btn btn-primary">
                        Manage My Team
                    </a>
                </div>

            <?php elseif ($pending_request): ?>
                <div class="alert alert-warning">
                    <strong>Registration Pending</strong>
                    <p>You have submitted a registration request for team "<?php echo htmlspecialchars($pending_request['team_name']); ?>".</p>
                    <p>Requested on: <?php echo date('M j, Y g:i A', strtotime($pending_request['created_at'])); ?></p>
                    <?php if ($pending_request['request_message']): ?>
                        <p><strong>Your message:</strong> <?php echo htmlspecialchars($pending_request['request_message']); ?></p>
                    <?php endif; ?>
                    <p style="margin-top: 1rem;">
                        <em>Please wait for admin approval. You'll be notified once your request is reviewed.</em>
                    </p>
                </div>

            <?php elseif ($can_register): ?>
                <h4 style="margin-bottom: 1rem;">Ready to join this league?</h4>
                <?php if ($league['approval_required']): ?>
                    <p style="color: #666; margin-bottom: 1rem;">
                        This league requires admin approval. Submit your registration request and the admin will review it.
                    </p>
                <?php else: ?>
                    <p style="color: #666; margin-bottom: 1rem;">
                        Register your team now and start competing!
                    </p>
                <?php endif; ?>

                <a href="../team/register_team.php?league_id=<?php echo $league_id; ?>" class="btn btn-success">
                    <?php echo $league['approval_required'] ? 'Submit Registration Request' : 'Register Team Now'; ?>
                </a>

            <?php else: ?>
                <?php if ($is_full): ?>
                    <div class="alert alert-error">
                        <strong>League Full</strong> - This league has reached maximum capacity.
                    </div>
                <?php elseif ($deadline_passed): ?>
                    <div class="alert alert-error">
                        <strong>Registration Closed</strong> - The registration deadline has passed.
                    </div>
                <?php elseif (!in_array($league['status'], ['open', 'active'])): ?>
                    <div class="alert alert-info">
                        <strong>Not Accepting Registrations</strong> - This league is currently <?php echo $league['status']; ?>.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
                <a href="browse_leagues.php" class="btn btn-secondary">Back to Leagues</a>

                <?php if ($can_manage): ?>
                    <a href="../match/schedule_matches.php?league_id=<?php echo $league['id']; ?>" class="btn btn-info">
                        Schedule Matches
                    </a>
                    <a href="edit_league.php?id=<?php echo $league['id']; ?>" class="btn btn-warning">
                        Edit League
                    </a>
                    <a href="../admin/manage_leagues.php" class="btn btn-primary">
                        Admin Panel
                    </a>
                    <?php if (($league['status'] == 'active' || $league['status'] == 'completed') && $is_round_robin_done): ?>
                        <a href="generate_playoffs.php?id=<?php echo $league['id']; ?>" class="btn btn-danger" onclick="return confirm('Generate playoffs? This will create new matches based on current standings.')">
                            Generate Playoffs
                        </a>
                    <?php elseif (($league['status'] == 'active' || $league['status'] == 'completed') && !$is_round_robin_done): ?>
                        <button class="btn btn-secondary" disabled title="Complete all round robin matches first">
                            Generate Playoffs (Round Robin Incomplete)
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-tabs">
            <div class="tab-nav">
                <button class="tab-button active" onclick="showTab('standings', this)">Standings</button>
                <button class="tab-button" onclick="showTab('matches', this)">Matches</button>
                <button class="tab-button" onclick="showTab('teams', this)">Teams</button>
                <button class="tab-button" onclick="showTab('players', this)">Top Players</button>
                <button class="tab-button" onclick="showTab('info', this)">League Info</button>
            </div>

            <div id="standings" class="tab-content active">
                <h3>League Standings</h3>
                <?php if (count($teams) > 0): ?>
                <div style="overflow-x: auto;">
                <table class="standings-table">
                    <thead>
                        <tr>
                            <th>Pos</th>
                            <th>Team</th>
                            <th>MP</th>
                            <th>W</th>
                            <th>D</th>
                            <th>L</th>
                            <th>Pts</th>
                            <th>Form</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $position = 1; foreach ($teams as $team): ?>
                        <tr>
                            <td class="position"><?php echo $position++; ?></td>
                            <td>
                                <div class="team-info">
                                    <div class="team-logo">
                                        <?php echo strtoupper(substr($team['name'], 0, 2)); ?>
                                    </div>
                                    <div class="team-details">
                                        <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                                        <small>Owner: <?php echo htmlspecialchars($team['first_name'] . ' ' . $team['last_name']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $team['matches_played']; ?></td>
                            <td><?php echo $team['wins']; ?></td>
                            <td><?php echo $team['draws']; ?></td>
                            <td><?php echo $team['losses']; ?></td>
                            <td><strong><?php echo $team['points']; ?></strong></td>
                            <td>
                                <?php
                                $form = '';
                                $recent_team_matches = array_filter($matches, function($match) use ($team) {
                                    return ($match['home_team_id'] == $team['id'] || $match['away_team_id'] == $team['id'])
                                           && $match['status'] == 'completed';
                                });
                                $recent_team_matches = array_slice($recent_team_matches, 0, 5);

                                foreach ($recent_team_matches as $match) {
                                    if ($match['home_team_id'] == $team['id']) {
                                        if ($match['home_score'] > $match['away_score']) $form .= '<span style="color: #28a745;">W</span>';
                                        elseif ($match['home_score'] < $match['away_score']) $form .= '<span style="color: #dc3545;">L</span>';
                                        else $form .= '<span style="color: #ffc107;">D</span>';
                                    } else {
                                        if ($match['away_score'] > $match['home_score']) $form .= '<span style="color: #28a745;">W</span>';
                                        elseif ($match['away_score'] < $match['home_score']) $form .= '<span style="color: #dc3545;">L</span>';
                                        else $form .= '<span style="color: #ffc107;">D</span>';
                                    }
                                }
                                echo $form ?: '-';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No teams registered yet</h3>
                    <p>Teams will appear here once they register for this league.</p>
                    <?php if ($can_register): ?>
                        <a href="../team/register_team.php?league_id=<?php echo $league_id; ?>" class="btn btn-success">
                            Be the First Team!
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div id="matches" class="tab-content">
                <h3>All Matches</h3>

                <?php if (count($upcoming_matches) > 0): ?>
                <h4 style="margin-top: 2rem; margin-bottom: 1rem; color: #28a745;">Upcoming Matches</h4>
                <div class="matches-grid">
                    <?php foreach (array_slice($upcoming_matches, 0, 6) as $match): ?>
                    <div class="match-card">
                        <div class="match-header">
                            <div class="match-date">
                                <?php echo date('M j, Y - g:i A', strtotime($match['match_date'])); ?>
                            </div>
                            <div class="match-status match-<?php echo $match['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $match['status'])); ?>
                            </div>
                        </div>

                        <div class="match-teams">
                            <div class="team-side">
                                <div class="team-logo">
                                    <?php echo strtoupper(substr($match['home_team'], 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($match['home_team']); ?></strong>
                                    <small>Home</small>
                                </div>
                            </div>

                            <div class="score-display">VS</div>

                            <div class="team-side away">
                                <div class="team-logo">
                                    <?php echo strtoupper(substr($match['away_team'], 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($match['away_team']); ?></strong>
                                    <small>Away</small>
                                </div>
                            </div>
                        </div>

                        <div class="match-details">
                            <span><?php echo htmlspecialchars($match['venue_name'] ?? 'Venue TBD'); ?></span>
                            <?php if ($can_manage): ?>
                                <a href="../match/record_result.php?id=<?php echo $match['id']; ?>" class="btn btn-info" style="font-size: 0.75rem; padding: 0.5rem 1rem;">
                                    Record Result
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (count($recent_matches) > 0): ?>
                <h4 style="margin-top: 2rem; margin-bottom: 1rem; color: #007bff;">Recent Results</h4>
                <div class="matches-grid">
                    <?php foreach (array_slice($recent_matches, 0, 8) as $match): ?>
                    <div class="match-card">
                        <div class="match-header">
                            <div class="match-date">
                                <?php echo date('M j, Y', strtotime($match['match_date'])); ?>
                            </div>
                            <div class="match-status match-completed">
                                Final
                            </div>
                        </div>

                        <div class="match-teams">
                            <div class="team-side">
                                <div class="team-logo">
                                    <?php echo strtoupper(substr($match['home_team'], 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($match['home_team']); ?></strong>
                                    <small>Home</small>
                                </div>
                            </div>

                            <div class="score-display">
                                <?php echo $match['home_score']; ?> - <?php echo $match['away_score']; ?>
                            </div>

                            <div class="team-side away">
                                <div class="team-logo">
                                    <?php echo strtoupper(substr($match['away_team'], 0, 2)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($match['away_team']); ?></strong>
                                    <small>Away</small>
                                </div>
                            </div>
                        </div>

                        <div class="match-details">
                            <span><?php echo htmlspecialchars($match['venue_name'] ?? 'Unknown Venue'); ?></span>
                            <?php if (!empty($match['notes'])): ?>
                                <span title="<?php echo htmlspecialchars($match['notes']); ?>">Notes</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (count($matches) == 0): ?>
                <div class="empty-state">
                    <h3>No matches scheduled yet</h3>
                    <p>Matches will appear here once they are scheduled.</p>
                    <?php if ($can_manage): ?>
                        <a href="../match/schedule_matches.php?league_id=<?php echo $league['id']; ?>" class="btn btn-success">
                            Schedule Matches
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div id="teams" class="tab-content">
                <h3>Participating Teams (<?php echo count($teams); ?>/<?php echo $league['max_teams']; ?>)</h3>

                <?php if (count($teams) > 0): ?>
                <div class="players-grid">
                    <?php foreach ($teams as $team): ?>
                    <div class="player-card" style="text-align: left;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div class="team-logo" style="width: 50px; height: 50px; font-size: 1rem;">
                                <?php echo strtoupper(substr($team['name'], 0, 2)); ?>
                            </div>
                            <div>
                                <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                                <small>Owner: <?php echo htmlspecialchars($team['first_name'] . ' ' . $team['last_name']); ?></small>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <strong>Record</strong><br>
                                <span style="color: #28a745;"><?php echo $team['wins']; ?>W</span> -
                                <span style="color: #ffc107;"><?php echo $team['draws']; ?>D</span> -
                                <span style="color: #dc3545;"><?php echo $team['losses']; ?>L</span>
                            </div>
                            <div>
                                <strong>Points</strong><br>
                                <span style="font-size: 1.2rem; color: #007bff; font-weight: bold;"><?php echo $team['points']; ?></span>
                            </div>
                            <div>
                                <strong>Members</strong><br>
                                <?php echo $team['member_count']; ?> players
                            </div>
                            <div>
                                <strong>Matches</strong><br>
                                <?php echo $team['matches_played']; ?> played
                            </div>
                        </div>

                        <?php if (!empty($team['description'])): ?>
                        <div style="background: white; padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem;">
                            <small><?php echo htmlspecialchars($team['description']); ?></small>
                        </div>
                        <?php endif; ?>

                        <div style="text-align: center;">
                            <a href="../team/view_team.php?id=<?php echo $team['id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.5rem 1rem;">
                                View Team
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No teams registered yet</h3>
                    <p>Teams will appear here once they register for this league.</p>
                    <?php if ($league['status'] == 'open' && $can_register): ?>
                        <a href="../team/register_team.php?league_id=<?php echo $league_id; ?>" class="btn btn-success">
                            Be the First Team!
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div id="players" class="tab-content">
                <h3>Top Scorers</h3>

                <?php if (count($top_scorers) > 0): ?>
                <div class="players-grid">
                    <?php $rank = 1; foreach ($top_scorers as $player): ?>
                    <div class="player-card">
                        <div class="player-rank">#<?php echo $rank++; ?></div>
                        <div class="player-name">
                            <?php echo htmlspecialchars($player['first_name'] . ' ' . $player['last_name']); ?>
                        </div>
                        <div class="player-team">
                            <?php echo htmlspecialchars($player['team_name']); ?>
                        </div>
                        <div class="player-stats">
                            <div><strong><?php echo $player['goals']; ?></strong> Goals</div>
                            <div><strong><?php echo $player['assists']; ?></strong> Assists</div>
                            <div><strong><?php echo $player['matches_played']; ?></strong> Matches</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No player statistics yet</h3>
                    <p>Player statistics will appear here after matches are completed and recorded.</p>
                </div>
                <?php endif; ?>
            </div>

            <div id="info" class="tab-content">
                <h3>League Information</h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 1.5rem;">
                    <div>
                        <h4 style="color: #007bff; margin-bottom: 1rem;">League Details</h4>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                            <p style="margin-bottom: 0.5rem;"><strong>Sport:</strong> <?php echo htmlspecialchars($league['sport_name']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Season:</strong> <?php echo htmlspecialchars($league['season']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Duration:</strong> <?php echo date('M j, Y', strtotime($league['start_date'])); ?> - <?php echo date('M j, Y', strtotime($league['end_date'])); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Registration Deadline:</strong> <?php echo date('M j, Y', strtotime($league['registration_deadline'])); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Maximum Teams:</strong> <?php echo $league['max_teams']; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Max Players per Team:</strong> <?php echo $league['max_players_per_team']; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Created by:</strong> <?php echo htmlspecialchars($league['creator_first'] . ' ' . $league['creator_last']); ?></p>
                            <p><strong>Created on:</strong> <?php echo date('M j, Y g:i A', strtotime($league['created_at'])); ?></p>
                        </div>
                    </div>

                    <div>
                        <h4 style="color: #28a745; margin-bottom: 1rem;">League Statistics</h4>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                            <p style="margin-bottom: 0.5rem;"><strong>Teams Registered:</strong> <?php echo count($teams); ?>/<?php echo $league['max_teams']; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Total Matches:</strong> <?php echo $total_matches; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Completed Matches:</strong> <?php echo $completed_matches; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Scheduled Matches:</strong> <?php echo count($upcoming_matches); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>League Progress:</strong> <?php echo $completion_percentage; ?>%</p>
                            <p style="margin-bottom: 0.5rem;"><strong>Total Goals Scored:</strong> <?php echo $total_goals; ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>Average Goals per Match:</strong> <?php echo $avg_goals_per_match; ?></p>
                            <p><strong>Total Players:</strong> <?php echo array_sum(array_column($teams, 'member_count')); ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($league['rules'])): ?>
                <div style="margin-top: 2rem;">
                    <h4 style="color: #dc3545; margin-bottom: 1rem;">League Rules & Regulations</h4>
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #dc3545;">
                        <?php echo nl2br(htmlspecialchars($league['rules'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($league['sport_description'])): ?>
                <div style="margin-top: 2rem;">
                    <h4 style="color: #17a2b8; margin-bottom: 1rem;">About <?php echo htmlspecialchars($league['sport_name']); ?></h4>
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #17a2b8;">
                        <?php echo nl2br(htmlspecialchars($league['sport_description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName, element) {
            const contents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }

            const buttons = document.getElementsByClassName('tab-button');
            for (let i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('active');
            }

            document.getElementById(tabName).classList.add('active');
            element.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.standings-table tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });

            const positions = document.querySelectorAll('.position');
            positions.forEach((pos) => {
                const position = parseInt(pos.textContent);
                if (position === 1) {
                    pos.style.background = 'linear-gradient(135deg, #ffd700, #ffed4e)';
                    pos.style.color = '#333';
                    pos.style.borderRadius = '50%';
                    pos.style.width = '30px';
                    pos.style.height = '30px';
                    pos.style.display = 'flex';
                    pos.style.alignItems = 'center';
                    pos.style.justifyContent = 'center';
                    pos.style.margin = '0 auto';
                } else if (position === 2) {
                    pos.style.background = 'linear-gradient(135deg, #c0c0c0, #e8e8e8)';
                    pos.style.color = '#333';
                    pos.style.borderRadius = '50%';
                    pos.style.width = '30px';
                    pos.style.height = '30px';
                    pos.style.display = 'flex';
                    pos.style.alignItems = 'center';
                    pos.style.justifyContent = 'center';
                    pos.style.margin = '0 auto';
                } else if (position === 3) {
                    pos.style.background = 'linear-gradient(135deg, #cd7f32, #deb887)';
                    pos.style.color = '#333';
                    pos.style.borderRadius = '50%';
                    pos.style.width = '30px';
                    pos.style.height = '30px';
                    pos.style.display = 'flex';
                    pos.style.alignItems = 'center';
                    pos.style.justifyContent = 'center';
                    pos.style.margin = '0 auto';
                }
            });
        });
    </script>
</body>
</html>