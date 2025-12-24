<?php
// team/create_team.php - Create New Team
require_once '../config/database.php';
requireLogin();

if ($_POST) {
    $database = new Database();
    $db = $database->connect();

    $name = trim($_POST['name']);
    $league_id = !empty($_POST['league_id']) ? $_POST['league_id'] : null;
    $description = trim($_POST['description']);
    $owner_id = $_SESSION['user_id'];

    // Check if user already owns a team in this league (if league selected)
    if (!empty($league_id)) {
        $check_query = "SELECT id FROM teams WHERE league_id = :league_id AND owner_id = :owner_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':league_id', $league_id);
        $check_stmt->bindParam(':owner_id', $owner_id);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            $error = "You already own a team in this league!";
        } else {
            // Check if league has space for more teams
            $league_query = "SELECT l.max_teams, COUNT(t.id) as current_teams
                             FROM leagues l
                             LEFT JOIN teams t ON l.id = t.league_id
                             WHERE l.id = :league_id
                             GROUP BY l.id";
            $league_stmt = $db->prepare($league_query);
            $league_stmt->bindParam(':league_id', $league_id);
            $league_stmt->execute();
            $league_info = $league_stmt->fetch(PDO::FETCH_ASSOC);

            if ($league_info['current_teams'] >= $league_info['max_teams']) {
                $error = "This league is full!";
            }
        }
    }

    if (!isset($error)) {
        $query = "INSERT INTO teams (name, league_id, owner_id, description, recruitment_status)
                  VALUES (:name, :league_id, :owner_id, :description, 'open')";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        if (empty($league_id)) {
            $stmt->bindValue(':league_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindParam(':league_id', $league_id);
        }
        $stmt->bindParam(':owner_id', $owner_id);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            $team_id = $db->lastInsertId();

            // Add owner as team member
            $member_query = "INSERT INTO team_members (team_id, player_id, position)
                            VALUES (:team_id, :player_id, 'Captain')";
            $member_stmt = $db->prepare($member_query);
            $member_stmt->bindParam(':team_id', $team_id);
            $member_stmt->bindParam(':player_id', $owner_id);
            $member_stmt->execute();

            showMessage("Team created successfully!", "success");
            redirect('manage_team.php?id=' . $team_id);
        } else {
            $error = "Failed to create team!";
        }
    }
}

// Get available leagues
$database = new Database();
$db = $database->connect();
$leagues_query = "SELECT l.*, s.name as sport_name
                  FROM leagues l
                  JOIN sports s ON l.sport_id = s.id
                  WHERE l.status IN ('open', 'draft')
                  AND l.registration_deadline >= CURDATE()
                  ORDER BY l.name";
$leagues_stmt = $db->prepare($leagues_query);
$leagues_stmt->execute();
$leagues = $leagues_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Team - Sports League</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; margin-bottom: 10px; }
        textarea { height: 100px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; text-decoration: none; border-radius: 3px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Create New Team</h2>

        <?php displayMessage(); ?>
        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <div class="form-group">
                <label>Team Name:</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>League (Optional):</label>
                <select name="league_id">
                    <option value="">No League (Create Standalone Team)</option>
                    <?php foreach ($leagues as $league): ?>
                        <option value="<?php echo $league['id']; ?>">
                            <?php echo htmlspecialchars($league['name']); ?> - <?php echo htmlspecialchars($league['sport_name']); ?> (<?php echo htmlspecialchars($league['season']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Team Description:</label>
                <textarea name="description" placeholder="Describe your team..."></textarea>
            </div>

            <button type="submit" class="btn">Create Team</button>
            <a href="../dashboard.php" class="btn" style="background: #6c757d;">Cancel</a>
        </form>
    </div>
</body>
</html>
