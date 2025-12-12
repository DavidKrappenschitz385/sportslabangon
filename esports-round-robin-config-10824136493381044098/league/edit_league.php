<?php
// league/edit_league.php - Complete League Editing System
require_once '../config/database.php';
requireLogin();

$league_id = $_GET['id'] ?? null;
if (!$league_id) {
    showMessage("League ID required!", "error");
    redirect('browse_leagues.php');
}

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Get league details
$league_query = "SELECT l.*, s.name as sport_name
                 FROM leagues l
                 JOIN sports s ON l.sport_id = s.id
                 WHERE l.id = :id";
$league_stmt = $db->prepare($league_query);
$league_stmt->bindParam(':id', $league_id);
$league_stmt->execute();
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

if (!$league) {
    showMessage("League not found!", "error");
    redirect('browse_leagues.php');
}

// Check permissions - only admin or league creator can edit
if ($current_user['role'] != 'admin' && $current_user['id'] != $league['created_by']) {
    showMessage("You don't have permission to edit this league!", "error");
    redirect('view_league.php?id=' . $league_id);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_league'])) {
    $name = trim($_POST['name']);
    $sport_id = $_POST['sport_id'];
    $season = trim($_POST['season']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $registration_deadline = $_POST['registration_deadline'];
    $max_teams = $_POST['max_teams'];
    $rules = trim($_POST['rules']);
    $status = $_POST['status'];
    $league_type = $_POST['league_type'];
    $round_robin_rounds = $_POST['round_robin_rounds'];
    $knockout_teams = $_POST['knockout_teams'];

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "League name is required!";
    }

    if (strtotime($end_date) <= strtotime($start_date)) {
        $errors[] = "End date must be after start date!";
    }

    if (strtotime($registration_deadline) >= strtotime($start_date)) {
        $errors[] = "Registration deadline must be before the league start date!";
    }

    if ($max_teams < 2 || $max_teams > 32) {
        $errors[] = "Maximum teams must be between 2 and 32!";
    }

    // Check if reducing max_teams would affect existing teams
    $team_count_query = "SELECT COUNT(*) as count FROM teams WHERE league_id = :league_id";
    $team_count_stmt = $db->prepare($team_count_query);
    $team_count_stmt->bindParam(':league_id', $league_id);
    $team_count_stmt->execute();
    $current_teams = $team_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($max_teams < $current_teams) {
        $errors[] = "Cannot reduce max teams below current team count ($current_teams teams)!";
    }

    if (empty($errors)) {
        $update_query = "UPDATE leagues
                        SET name = :name,
                            sport_id = :sport_id,
                            season = :season,
                            start_date = :start_date,
                            end_date = :end_date,
                            registration_deadline = :registration_deadline,
                            max_teams = :max_teams,
                            rules = :rules,
                            status = :status,
                            league_type = :league_type,
                            round_robin_rounds = :round_robin_rounds,
                            knockout_teams = :knockout_teams,
                            updated_at = NOW()
                        WHERE id = :id";

        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':name', $name);
        $update_stmt->bindParam(':sport_id', $sport_id);
        $update_stmt->bindParam(':season', $season);
        $update_stmt->bindParam(':start_date', $start_date);
        $update_stmt->bindParam(':end_date', $end_date);
        $update_stmt->bindParam(':registration_deadline', $registration_deadline);
        $update_stmt->bindParam(':max_teams', $max_teams);
        $update_stmt->bindParam(':rules', $rules);
        $update_stmt->bindParam(':status', $status);
        $update_stmt->bindParam(':league_type', $league_type);
        $update_stmt->bindParam(':round_robin_rounds', $round_robin_rounds);
        $update_stmt->bindParam(':knockout_teams', $knockout_teams);
        $update_stmt->bindParam(':id', $league_id);

        if ($update_stmt->execute()) {
            showMessage("League updated successfully!", "success");
            redirect('view_league.php?id=' . $league_id);
        } else {
            $errors[] = "Failed to update league!";
        }
    }
}

// Get sports for dropdown
$sports_query = "SELECT * FROM sports ORDER BY name";
$sports_stmt = $db->prepare($sports_query);
$sports_stmt->execute();
$sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get league statistics
$stats_query = "SELECT
                (SELECT COUNT(*) FROM teams WHERE league_id = :league_id) as team_count,
                (SELECT COUNT(*) FROM matches WHERE league_id = :league_id) as total_matches,
                (SELECT COUNT(*) FROM matches WHERE league_id = :league_id AND status = 'completed') as completed_matches,
                (SELECT COUNT(*) FROM registration_requests WHERE league_id = :league_id AND status = 'pending') as pending_requests";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':league_id', $league_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit League - <?php echo htmlspecialchars($league['name']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .nav-breadcrumb {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .breadcrumb {
            max-width: 1200px;
            margin: 0 auto;
            color: #666;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: #28a745;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 2rem;
        }

        .layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .info-card h3 {
            color: #28a745;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
            color: #28a745;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-draft { background: #6c757d; color: white; }
        .status-open { background: #28a745; color: white; }
        .status-active { background: #007bff; color: white; }
        .status-closed { background: #ffc107; color: black; }
        .status-completed { background: #17a2b8; color: white; }

        .warning-card {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .warning-card h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }

        .warning-card ul {
            margin-left: 1.5rem;
            color: #856404;
        }

        .main-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #28a745;
        }

        .form-header h2 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            color: #28a745;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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

        .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40,167,69,0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
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
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40,167,69,0.3);
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

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e0e0e0;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-error ul {
            margin: 0.5rem 0 0 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .date-info {
            background: #e7f3ff;
            padding: 0.75rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #004085;
        }

        @media (max-width: 968px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .sidebar {
                order: 2;
            }

            .main-content {
                order: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Edit League</h1>
        <p><?php echo htmlspecialchars($league['name']); ?></p>
    </div>

    <!-- Breadcrumb -->
    <div class="nav-breadcrumb">
        <div class="breadcrumb">
            <a href="../dashboard.php">Dashboard</a>
            <span> › </span>
            <?php if ($current_user['role'] == 'admin'): ?>
                <a href="../admin/manage_leagues.php">Manage Leagues</a>
            <?php else: ?>
                <a href="browse_leagues.php">Browse Leagues</a>
            <?php endif; ?>
            <span> › </span>
            <a href="view_league.php?id=<?php echo $league_id; ?>"><?php echo htmlspecialchars($league['name']); ?></a>
            <span> › </span>
            <span>Edit</span>
        </div>
    </div>

    <div class="container">
        <?php displayMessage(); ?>

        <?php if (isset($errors) && count($errors) > 0): ?>
        <div class="alert alert-error">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="layout">
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Current Status Card -->
                <div class="info-card">
                    <h3>Current Status</h3>
                    <div class="stat-item">
                        <span class="stat-label">Status:</span>
                        <span class="status-badge status-<?php echo $league['status']; ?>">
                            <?php echo ucfirst($league['status']); ?>
                        </span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Created:</span>
                        <span class="stat-value"><?php echo date('M j, Y', strtotime($league['created_at'])); ?></span>
                    </div>
  <?php if (isset($league['updated_at']) && $league['updated_at']): ?>
<div class="stat-item">
    <span class="stat-label">Last Updated:</span>
    <span class="stat-value"><?php echo date('M j, Y', strtotime($league['updated_at'])); ?></span>
</div>
<?php endif; ?>

                </div>

                <!-- League Statistics -->
                <div class="info-card">
                    <h3>League Statistics</h3>
                    <div class="stat-item">
                        <span class="stat-label">Teams:</span>
                        <span class="stat-value"><?php echo $stats['team_count']; ?>/<?php echo $league['max_teams']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Matches:</span>
                        <span class="stat-value"><?php echo $stats['total_matches']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Completed:</span>
                        <span class="stat-value"><?php echo $stats['completed_matches']; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Pending Requests:</span>
                        <span class="stat-value"><?php echo $stats['pending_requests']; ?></span>
                    </div>
                </div>

                <!-- Warning Card -->
                <?php if ($stats['team_count'] > 0 || $stats['total_matches'] > 0): ?>
                <div class="warning-card">
                    <h4>⚠️ Important</h4>
                    <ul>
                        <?php if ($stats['team_count'] > 0): ?>
                        <li>League has <?php echo $stats['team_count']; ?> registered teams</li>
                        <?php endif; ?>
                        <?php if ($stats['total_matches'] > 0): ?>
                        <li><?php echo $stats['total_matches']; ?> matches scheduled</li>
                        <?php endif; ?>
                        <li>Be careful when changing dates or team limits</li>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="info-card">
                    <h3>Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary" style="text-align: center;">
                            View League
                        </a>
                        <?php if ($current_user['role'] == 'admin'): ?>
                        <a href="../match/schedule_matches.php?league_id=<?php echo $league_id; ?>" class="btn btn-primary" style="text-align: center;">
                            Schedule Matches
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="form-header">
                    <h2>Edit League Details</h2>
                    <p>Update the information for this league. Fields marked with <span class="required">*</span> are required.</p>
                </div>

                <form method="POST" id="editLeagueForm">
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">Basic Information</h3>

                        <div class="form-group">
                            <label>League Name <span class="required">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?php echo htmlspecialchars($league['name']); ?>" required>
                            <div class="help-text">A clear, descriptive name for your league</div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Sport <span class="required">*</span></label>
                                <select name="sport_id" class="form-control" required>
                                    <?php foreach ($sports as $sport): ?>
                                        <option value="<?php echo $sport['id']; ?>"
                                                <?php echo $league['sport_id'] == $sport['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sport['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">Cannot be changed if teams are registered</div>
                            </div>

                            <div class="form-group">
                                <label>Season <span class="required">*</span></label>
                                <input type="text" name="season" class="form-control"
                                       value="<?php echo htmlspecialchars($league['season']); ?>"
                                       placeholder="e.g., Spring 2024" required>
                                <div class="help-text">Identify the season or year</div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Section -->
                    <div class="form-section">
                        <h3 class="section-title">Schedule & Dates</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Start Date <span class="required">*</span></label>
                                <input type="date" name="start_date" class="form-control"
                                       value="<?php echo $league['start_date']; ?>" required>
                                <div class="help-text">When the league begins</div>
                            </div>

                            <div class="form-group">
                                <label>End Date <span class="required">*</span></label>
                                <input type="date" name="end_date" class="form-control"
                                       value="<?php echo $league['end_date']; ?>" required>
                                <div class="help-text">When the league ends</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Registration Deadline <span class="required">*</span></label>
                            <input type="date" name="registration_deadline" class="form-control"
                                   value="<?php echo $league['registration_deadline']; ?>" required>
                            <div class="help-text">Last date for teams to register</div>
                            <div class="date-info">
                                <strong>Current Duration:</strong>
                                <?php
                                $duration = (strtotime($league['end_date']) - strtotime($league['start_date'])) / (60 * 60 * 24);
                                echo round($duration) . ' days';
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Section -->
                    <div class="form-section">
                        <h3 class="section-title">League Settings</h3>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Maximum Teams <span class="required">*</span></label>
                                <input type="number" name="max_teams" class="form-control"
                                       value="<?php echo $league['max_teams']; ?>"
                                       min="<?php echo max(2, $stats['team_count']); ?>" max="32" required>
                                <div class="help-text">Between 2 and 32 teams (Current: <?php echo $stats['team_count']; ?>)</div>
                            </div>

                            <div class="form-group">
                                <label>Status <span class="required">*</span></label>
                                <select name="status" class="form-control" required>
                                    <option value="draft" <?php echo $league['status'] == 'draft' ? 'selected' : ''; ?>>
                                        Draft (Hidden from public)
                                    </option>
                                    <option value="open" <?php echo $league['status'] == 'open' ? 'selected' : ''; ?>>
                                        Open (Accepting registrations)
                                    </option>
                                    <option value="active" <?php echo $league['status'] == 'active' ? 'selected' : ''; ?>>
                                        Active (In progress)
                                    </option>
                                    <option value="closed" <?php echo $league['status'] == 'closed' ? 'selected' : ''; ?>>
                                        Closed (Registration ended)
                                    </option>
                                    <option value="completed" <?php echo $league['status'] == 'completed' ? 'selected' : ''; ?>>
                                        Completed (Finished)
                                    </option>
                                </select>
                                <div class="help-text">Current status of the league</div>
                            </div>

                            <div class="form-group">
                                <label>League Format</label>
                                <select name="league_type" class="form-control">
                                    <option value="round_robin" <?php echo ($league['league_type'] ?? 'round_robin') == 'round_robin' ? 'selected' : ''; ?>>Round Robin</option>
                                    <option value="single_elimination" <?php echo ($league['league_type'] ?? '') == 'single_elimination' ? 'selected' : ''; ?>>Single Elimination</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Round Robin Rounds</label>
                                <input type="number" name="round_robin_rounds" class="form-control" value="<?php echo $league['round_robin_rounds'] ?? 1; ?>" min="1" max="4">
                            </div>

                            <div class="form-group">
                                <label>Teams advancing to Playoffs</label>
                                <select name="knockout_teams" class="form-control">
                                    <option value="0" <?php echo ($league['knockout_teams'] ?? 0) == 0 ? 'selected' : ''; ?>>None</option>
                                    <option value="4" <?php echo ($league['knockout_teams'] ?? 0) == 4 ? 'selected' : ''; ?>>Top 4 (Semi-Finals)</option>
                                    <option value="8" <?php echo ($league['knockout_teams'] ?? 0) == 8 ? 'selected' : ''; ?>>Top 8 (Quarter-Finals)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Rules Section -->
                    <div class="form-section">
                        <h3 class="section-title">Rules & Regulations</h3>

                        <div class="form-group full-width">
                            <label>League Rules</label>
                            <textarea name="rules" class="form-control"
                                      placeholder="Enter the rules, regulations, and guidelines for this league..."><?php echo htmlspecialchars($league['rules'] ?? ''); ?></textarea>
                            <div class="help-text">Define the rules that teams and players must follow</div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="update_league" class="btn btn-primary">
                            💾 Update League
                        </button>
                        <a href="view_league.php?id=<?php echo $league_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                        <?php if ($current_user['role'] == 'admin' && $stats['team_count'] == 0 && $stats['total_matches'] == 0): ?>
                        <button type="button" onclick="confirmDelete()" class="btn btn-danger" style="margin-left: auto;">
                            🗑️ Delete League
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('editLeagueForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.querySelector('input[name="start_date"]').value);
            const endDate = new Date(document.querySelector('input[name="end_date"]').value);
            const regDeadline = new Date(document.querySelector('input[name="registration_deadline"]').value);
            const maxTeams = parseInt(document.querySelector('input[name="max_teams"]').value);

            // Validate dates
            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date!');
                return false;
            }

            if (regDeadline >= startDate) {
                e.preventDefault();
                alert('Registration deadline must be before the league start date!');
                return false;
            }

            // Validate max teams
            if (maxTeams < <?php echo max(2, $stats['team_count']); ?>) {
                e.preventDefault();
                alert('Cannot reduce max teams below current team count (<?php echo $stats['team_count']; ?>)!');
                return false;
            }

            // Confirm if changing sport with existing teams
            <?php if ($stats['team_count'] > 0): ?>
            const originalSport = '<?php echo $league['sport_id']; ?>';
            const newSport = document.querySelector('select[name="sport_id"]').value;
            if (originalSport !== newSport) {
                if (!confirm('Warning: You have <?php echo $stats['team_count']; ?> registered teams. Changing the sport may affect team registrations. Continue?')) {
                    e.preventDefault();
                    return false;
                }
            }
            <?php endif; ?>
        });

        // Date change handlers to show duration
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');

        function updateDuration() {
            const start = new Date(startDateInput.value);
            const end = new Date(endDateInput.value);
            const duration = (end - start) / (1000 * 60 * 60 * 24);

            if (duration > 0) {
                const durationInfo = document.querySelector('.date-info');
                durationInfo.innerHTML = `<strong>Duration:</strong> ${Math.round(duration)} days`;
            }
        }

        startDateInput.addEventListener('change', updateDuration);
        endDateInput.addEventListener('change', updateDuration);

        // Confirm delete action
        function confirmDelete() {
            if (confirm('Are you sure you want to delete this league? This action cannot be undone!')) {
                if (confirm('This will permanently delete the league and all associated data. Are you absolutely sure?')) {
                    window.location.href = '../admin/manage_leagues.php?action=delete&id=<?php echo $league_id; ?>';
                }
            }
        }

        // Auto-save draft functionality
        let autoSaveTimeout;
        const formInputs = document.querySelectorAll('input, select, textarea');

        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    // Could implement auto-save to localStorage here
                    console.log('Auto-save triggered');
                }, 3000);
            });
        });

        // Warn before leaving page with unsaved changes
        let formChanged = false;
        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // Reset formChanged when form is submitted
        document.getElementById('editLeagueForm').addEventListener('submit', () => {
            formChanged = false;
        });

        // Character counter for rules textarea
        const rulesTextarea = document.querySelector('textarea[name="rules"]');
        if (rulesTextarea) {
            const charCounter = document.createElement('div');
            charCounter.className = 'help-text';
            charCounter.style.textAlign = 'right';
            rulesTextarea.parentNode.appendChild(charCounter);

            function updateCharCount() {
                const count = rulesTextarea.value.length;
                charCounter.textContent = `${count} characters`;
                if (count > 5000) {
                    charCounter.style.color = '#dc3545';
                } else {
                    charCounter.style.color = '#666';
                }
            }

            rulesTextarea.addEventListener('input', updateCharCount);
            updateCharCount();
        }

        // Enhanced date validation with visual feedback
        function validateDates() {
            const startDate = document.querySelector('input[name="start_date"]');
            const endDate = document.querySelector('input[name="end_date"]');
            const regDeadline = document.querySelector('input[name="registration_deadline"]');

            // Clear previous validation states
            [startDate, endDate, regDeadline].forEach(input => {
                input.style.borderColor = '#ddd';
            });

            if (startDate.value && endDate.value) {
                if (new Date(endDate.value) <= new Date(startDate.value)) {
                    endDate.style.borderColor = '#dc3545';
                } else {
                    endDate.style.borderColor = '#28a745';
                }
            }

            if (regDeadline.value && startDate.value) {
                if (new Date(regDeadline.value) >= new Date(startDate.value)) {
                    regDeadline.style.borderColor = '#dc3545';
                } else {
                    regDeadline.style.borderColor = '#28a745';
                }
            }
        }

        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', validateDates);
        });

        // Initialize validation
        validateDates();

        // Status change confirmation
        const statusSelect = document.querySelector('select[name="status"]');
        const originalStatus = statusSelect.value;

        statusSelect.addEventListener('change', function() {
            const newStatus = this.value;
            let confirmMsg = '';

            if (originalStatus === 'open' && newStatus === 'active') {
                confirmMsg = 'Changing status to "Active" will close registration. Teams can no longer join. Continue?';
            } else if (originalStatus === 'active' && newStatus === 'completed') {
                confirmMsg = 'Marking league as "Completed" will finalize all results. Continue?';
            } else if (newStatus === 'draft') {
                confirmMsg = 'Changing to "Draft" will hide this league from public view. Continue?';
            }

            if (confirmMsg && !confirm(confirmMsg)) {
                this.value = originalStatus;
            }
        });

        // Real-time validation feedback
        document.querySelector('input[name="name"]').addEventListener('input', function() {
            if (this.value.length < 3) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#28a745';
            }
        });

        document.querySelector('input[name="max_teams"]').addEventListener('input', function() {
            const min = parseInt(this.getAttribute('min'));
            const max = parseInt(this.getAttribute('max'));
            const val = parseInt(this.value);

            if (val < min || val > max) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#28a745';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('editLeagueForm').submit();
            }

            // Escape to go back
            if (e.key === 'Escape') {
                if (confirm('Discard changes and go back?')) {
                    window.location.href = 'view_league.php?id=<?php echo $league_id; ?>';
                }
            }
        });

        // Form submission loading state
        document.getElementById('editLeagueForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span style="opacity: 0.7;">Updating...</span>';
        });

        // Auto-hide success/error messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });

        // Smooth scroll to errors
        if (document.querySelector('.alert-error')) {
            document.querySelector('.alert-error').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }

        // Dynamic help text based on selections
        statusSelect.addEventListener('change', function() {
            const helpText = this.parentElement.querySelector('.help-text');
            const statusDescriptions = {
                'draft': 'League is hidden and only visible to you',
                'open': 'Teams can register and join the league',
                'active': 'League is in progress, registration closed',
                'closed': 'League registration is closed',
                'completed': 'League has finished, results are final'
            };
            helpText.textContent = statusDescriptions[this.value] || 'Current status of the league';
        });

        // Add visual feedback for required fields
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#28a745';
                }
            });
        });
    </script>
</body>
</html>
