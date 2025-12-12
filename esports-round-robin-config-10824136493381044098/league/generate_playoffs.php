<?php
require_once '../config/database.php';
require_once '../includes/LeagueManager.php';
requireLogin();

$league_id = $_GET['id'] ?? null;
if (!$league_id) {
    showMessage("League ID is required!", "error");
    redirect('browse_leagues.php');
}

$database = new Database();
$db = $database->connect();
$current_user = getCurrentUser();

// Get league details
$league_query = "SELECT * FROM leagues WHERE id = :league_id";
$league_stmt = $db->prepare($league_query);
$league_stmt->bindParam(':league_id', $league_id);
$league_stmt->execute();
$league = $league_stmt->fetch(PDO::FETCH_ASSOC);

if (!$league || ($league['created_by'] != $current_user['id'] && $current_user['role'] != 'admin')) {
    showMessage("You are not authorized to manage this league.", "error");
    redirect('view_league.php?id=' . $league_id);
}

// Check if playoffs already generated
$check_query = "SELECT COUNT(*) FROM matches WHERE league_id = :league_id AND match_type != 'round_robin'";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':league_id', $league_id);
$check_stmt->execute();
if ($check_stmt->fetchColumn() > 0) {
    showMessage("Playoffs already generated.", "warning");
    redirect('view_league.php?id=' . $league_id);
}

$leagueManager = new LeagueManager();
$matches = $leagueManager->generatePlayoffs($league_id);

if (empty($matches)) {
    showMessage("Could not generate playoffs. Check league settings or standings.", "error");
    redirect('view_league.php?id=' . $league_id);
}

// Save matches
try {
    $db->beginTransaction();

    $insert_query = "INSERT INTO matches (league_id, home_team_id, away_team_id, round, match_type, bracket_pos, match_date, status)
                     VALUES (:league_id, :home_team_id, :away_team_id, :round, :match_type, :bracket_pos, :match_date, 'scheduled')";
    $insert_stmt = $db->prepare($insert_query);

    $match_date = new DateTime();
    $match_date->add(new DateInterval('P1D')); // Start tomorrow
    $match_date->setTime(14, 0);

    foreach ($matches as $match) {
        $insert_stmt->bindParam(':league_id', $league_id);
        $insert_stmt->bindParam(':home_team_id', $match['home_team_id']);
        $insert_stmt->bindParam(':away_team_id', $match['away_team_id']);
        $insert_stmt->bindParam(':round', $match['round']);
        $insert_stmt->bindParam(':match_type', $match['match_type']);
        $insert_stmt->bindParam(':bracket_pos', $match['bracket_pos']);
        $insert_stmt->bindParam(':match_date', $match_date->format('Y-m-d H:i:s'));
        $insert_stmt->execute();

        $match_date->add(new DateInterval('PT3H'));
    }

    $db->commit();
    showMessage("Playoff bracket generated successfully!", "success");
} catch (Exception $e) {
    $db->rollBack();
    showMessage("Error generating playoffs: " . $e->getMessage(), "error");
}

redirect('view_league.php?id=' . $league_id);
?>
