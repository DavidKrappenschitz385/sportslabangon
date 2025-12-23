<?php
// match/update_score.php - Endpoint to update scores via AJAX
require_once '../config/database.php';
require_once '../includes/LeagueManager.php';
requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $match_id = $_POST['match_id'] ?? null;
    $home_score = $_POST['home_score'] ?? null;
    $away_score = $_POST['away_score'] ?? null;

    if (!$match_id || $home_score === null || $away_score === null) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    try {
        $leagueManager = new LeagueManager();
        if ($leagueManager->recordMatchResult($match_id, (int)$home_score, (int)$away_score)) {
            echo json_encode(['success' => true, 'message' => 'Score updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update score.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
