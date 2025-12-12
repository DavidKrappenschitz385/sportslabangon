<?php
// league/create_league.php - Create New League
require_once '../config/database.php';
requireRole('admin');

if ($_POST) {
    $database = new Database();
    $db = $database->connect();

    $name = trim($_POST['name']);
    $sport_id = $_POST['sport_id'];
    $season = trim($_POST['season']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $registration_deadline = $_POST['registration_deadline'];
    $max_teams = $_POST['max_teams'];
    $rules = trim($_POST['rules']);
    $created_by = $_SESSION['user_id'];

    // New Fields
    $league_type = $_POST['league_type'] ?? 'round_robin';
    $round_robin_rounds = $_POST['round_robin_rounds'] ?? 1;
    $knockout_teams = $_POST['knockout_teams'] ?? 0;

    $query = "INSERT INTO leagues (name, sport_id, season, start_date, end_date, registration_deadline, max_teams, rules, created_by, league_type, round_robin_rounds, knockout_teams)
              VALUES (:name, :sport_id, :season, :start_date, :end_date, :registration_deadline, :max_teams, :rules, :created_by, :league_type, :round_robin_rounds, :knockout_teams)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':sport_id', $sport_id);
    $stmt->bindParam(':season', $season);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':registration_deadline', $registration_deadline);
    $stmt->bindParam(':max_teams', $max_teams);
    $stmt->bindParam(':rules', $rules);
    $stmt->bindParam(':created_by', $created_by);
    $stmt->bindParam(':league_type', $league_type);
    $stmt->bindParam(':round_robin_rounds', $round_robin_rounds);
    $stmt->bindParam(':knockout_teams', $knockout_teams);

    if ($stmt->execute()) {
        showMessage("League created successfully!", "success");
        redirect('manage_leagues.php');
    } else {
        $error = "Failed to create league!";
    }
}

// Get sports for dropdown
$database = new Database();
$db = $database->connect();
$sports_query = "SELECT * FROM sports ORDER BY name";
$sports_stmt = $db->prepare($sports_query);
$sports_stmt->execute();
$sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create League - Sports League</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; }
        textarea { height: 100px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create New League</h2>

        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <div class="form-group">
                <label>League Name:</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>Sport:</label>
                <select name="sport_id" required>
                    <option value="">Select Sport</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>"><?php echo $sport['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Season:</label>
                <input type="text" name="season" placeholder="e.g., Spring 2024" required>
            </div>

            <div class="form-group">
                <label>Start Date:</label>
                <input type="date" name="start_date" required>
            </div>

            <div class="form-group">
                <label>End Date:</label>
                <input type="date" name="end_date" required>
            </div>

            <div class="form-group">
                <label>Registration Deadline:</label>
                <input type="date" name="registration_deadline" required>
            </div>

            <div class="form-group">
                <label>Maximum Teams:</label>
                <input type="number" name="max_teams" value="16" min="2" max="32">
            </div>

            <div class="form-group">
                <label>League Format:</label>
                <select name="league_type">
                    <option value="round_robin">Round Robin</option>
                    <option value="single_elimination">Single Elimination</option>
                </select>
            </div>

            <div class="form-group">
                <label>Round Robin Schedule:</label>
                <select name="round_robin_rounds">
                    <option value="1">Single Round Robin (Every team plays once)</option>
                    <option value="2">Double Round Robin (Home & Away)</option>
                    <option value="3">Triple Round Robin</option>
                    <option value="4">Quadruple Round Robin</option>
                </select>
            </div>

            <div class="form-group">
                <label>Teams advancing to Playoffs (0 for none):</label>
                <select name="knockout_teams">
                    <option value="0">None</option>
                    <option value="4">Top 4 (Semi-Finals)</option>
                    <option value="8">Top 8 (Quarter-Finals)</option>
                </select>
            </div>

            <div class="form-group">
                <label>League Rules:</label>
                <textarea name="rules" placeholder="Enter league rules and regulations..."></textarea>
            </div>

            <button type="submit" class="btn">Create League</button>
            <a href="../admin/manage_leagues.php" class="btn" style="background: #6c757d; text-decoration: none;">Cancel</a>
        </form>
    </div>
</body>
</html>
