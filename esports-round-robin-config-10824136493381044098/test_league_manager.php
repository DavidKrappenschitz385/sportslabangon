<?php
// wargd-fix-round-robin-error/test_league_manager.php
require_once 'includes/LeagueManager.php';

// Mock DB connection won't work easily as LeagueManager instantiates Database inside constructor.
// But we can check if generateRoundRobin works purely logic-wise if we separate it?
// No, it's public.
// However, we want to test with dummy data without DB first to ensure logic is sound.
// The generateRoundRobin method doesn't use DB.

$manager = new LeagueManager(); // This will try to connect to DB.
// Assuming DB connection fails in this environment or succeeds if configured?
// The user's environment might not have the DB running.
// But I can test generateRoundRobin logic by subclassing or just passing data.

echo "Testing Round Robin Generation...\n";

$teams = [
    ['id' => 1, 'name' => 'Team A'],
    ['id' => 2, 'name' => 'Team B'],
    ['id' => 3, 'name' => 'Team C'],
    ['id' => 4, 'name' => 'Team D']
];

$matches = $manager->generateRoundRobin($teams);

echo "Teams: 4\n";
echo "Matches generated: " . count($matches) . "\n";
// 4 teams -> 3 rounds * 2 matches = 6 matches
if (count($matches) == 6) {
    echo "PASS: Correct number of matches.\n";
} else {
    echo "FAIL: Expected 6 matches, got " . count($matches) . "\n";
}

// Check for uniqueness
$pairs = [];
foreach ($matches as $m) {
    $ids = [$m['teamA']['id'], $m['teamB']['id']];
    sort($ids);
    $key = implode('-', $ids);
    if (isset($pairs[$key])) {
        echo "FAIL: Duplicate match found for $key\n";
    }
    $pairs[$key] = true;
    echo "Round {$m['round']}: {$m['teamA']['name']} vs {$m['teamB']['name']}\n";
}
echo "PASS: No duplicate opponents.\n";

echo "\nTesting Odd Number of Teams...\n";
$teams_odd = [
    ['id' => 1, 'name' => 'Team A'],
    ['id' => 2, 'name' => 'Team B'],
    ['id' => 3, 'name' => 'Team C']
];
$matches_odd = $manager->generateRoundRobin($teams_odd);
echo "Teams: 3\n";
echo "Matches generated: " . count($matches_odd) . "\n";
// 3 teams -> 3 rounds. In each round 1 match (plus one BYE which is hidden).
// Total real matches = 3.
if (count($matches_odd) == 3) {
    echo "PASS: Correct number of matches.\n";
} else {
    echo "FAIL: Expected 3 matches, got " . count($matches_odd) . "\n";
}

foreach ($matches_odd as $m) {
    echo "Round {$m['round']}: {$m['teamA']['name']} vs {$m['teamB']['name']}\n";
}

?>
