<?php
require_once __DIR__ . '/../config/database.php';

class LeagueManager {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }

    /**
     * Generate Round Robin Matches using the Circle Method.
     *
     * @param array $teams Array of teams (associative array with 'id', 'name', etc.)
     * @return array Array of matches
     */
    public function generateRoundRobin($teams) {
        $matches = [];
        // If odd number of teams, add a dummy "BYE" team
        if (count($teams) % 2 != 0) {
            $teams[] = ['id' => null, 'name' => 'BYE'];
        }

        $num_teams = count($teams);
        $num_rounds = $num_teams - 1;
        $matches_per_round = $num_teams / 2;

        // Create a copy of teams to manipulate for rotation
        $rotating_teams = $teams;

        for ($round = 0; $round < $num_rounds; $round++) {
            for ($match_num = 0; $match_num < $matches_per_round; $match_num++) {
                $home_team = $rotating_teams[$match_num];
                $away_team = $rotating_teams[$num_teams - 1 - $match_num];

                // If neither team is the dummy "BYE" team, record the match
                if ($home_team['id'] !== null && $away_team['id'] !== null) {
                    $matches[] = [
                        'round' => $round + 1,
                        'match_num' => $match_num + 1,
                        'teamA' => $home_team,
                        'teamB' => $away_team,
                    ];
                }
            }

            // Circle Method Rotation:
            // Keep the first team fixed (index 0).
            // Move the last team to the second position (index 1).
            // Shift everyone else down.

            // Extract the last team
            $last_team = array_pop($rotating_teams);
            // Insert it at index 1
            array_splice($rotating_teams, 1, 0, [$last_team]);
        }

        return $matches;
    }

    /**
     * Records a match result and updates league standings.
     *
     * @param int $match_id
     * @param int $home_score
     * @param int $away_score
     * @return bool True on success
     * @throws Exception
     */
    public function recordMatchResult($match_id, $home_score, $away_score) {
        try {
            $this->db->beginTransaction();

            // 1. Update the match status and score
            $query = "UPDATE matches
                      SET home_score = :home_score,
                          away_score = :away_score,
                          status = 'completed'
                      WHERE id = :match_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':home_score', $home_score);
            $stmt->bindParam(':away_score', $away_score);
            $stmt->bindParam(':match_id', $match_id);
            $stmt->execute();

            // 2. Get the league_id from the match to recalculate standings for that league
            $query = "SELECT league_id FROM matches WHERE id = :match_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':match_id', $match_id);
            $stmt->execute();
            $match = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$match) {
                throw new Exception("Match not found.");
            }

            $league_id = $match['league_id'];

            // 3. Recalculate standings for the entire league
            $this->recalculateLeagueStandings($league_id);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Recalculates stats for all teams in a league based on completed matches.
     *
     * @param int $league_id
     */
    public function recalculateLeagueStandings($league_id) {
        // Fetch League Rules
        $league_query = "SELECT * FROM leagues WHERE id = :league_id";
        $stmt = $this->db->prepare($league_query);
        $stmt->bindParam(':league_id', $league_id);
        $stmt->execute();
        $league = $stmt->fetch(PDO::FETCH_ASSOC);

        // Defaults if columns missing or null (backward compatibility)
        $pts_win = isset($league['points_per_win']) ? (float)$league['points_per_win'] : 1.0;
        $pts_draw = isset($league['points_per_draw']) ? (float)$league['points_per_draw'] : 0.5;
        $pts_loss = isset($league['points_per_loss']) ? (float)$league['points_per_loss'] : 0.0;

        // 1. Reset all teams in the league to 0 stats
        $reset_query = "UPDATE teams
                        SET wins = 0,
                            losses = 0,
                            draws = 0,
                            points = 0,
                            goals_for = 0,
                            goals_against = 0,
                            matches_played = 0,
                            score_difference = 0
                        WHERE league_id = :league_id";
        $stmt = $this->db->prepare($reset_query);
        $stmt->bindParam(':league_id', $league_id);
        $stmt->execute();

        // 2. Fetch all completed matches for this league
        $matches_query = "SELECT home_team_id, away_team_id, home_score, away_score
                          FROM matches
                          WHERE league_id = :league_id AND status = 'completed'";
        $stmt = $this->db->prepare($matches_query);
        $stmt->bindParam(':league_id', $league_id);
        $stmt->execute();
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Accumulate stats in memory
        $team_stats = [];

        foreach ($matches as $match) {
            $home_id = $match['home_team_id'];
            $away_id = $match['away_team_id'];
            $home_score = (int)$match['home_score'];
            $away_score = (int)$match['away_score'];

            // Initialize stats if not present
            if (!isset($team_stats[$home_id])) $team_stats[$home_id] = $this->initStats();
            if (!isset($team_stats[$away_id])) $team_stats[$away_id] = $this->initStats();

            // Increment Matches Played
            $team_stats[$home_id]['matches_played']++;
            $team_stats[$away_id]['matches_played']++;

            // Update Scores
            $team_stats[$home_id]['goals_for'] += $home_score;
            $team_stats[$home_id]['goals_against'] += $away_score;

            $team_stats[$away_id]['goals_for'] += $away_score;
            $team_stats[$away_id]['goals_against'] += $home_score;

            // Determine Winner/Draw
            if ($home_score > $away_score) {
                // Home Win
                $team_stats[$home_id]['wins']++;
                $team_stats[$home_id]['points'] += $pts_win;
                $team_stats[$away_id]['losses']++;
                $team_stats[$away_id]['points'] += $pts_loss;
            } elseif ($away_score > $home_score) {
                // Away Win
                $team_stats[$away_id]['wins']++;
                $team_stats[$away_id]['points'] += $pts_win;
                $team_stats[$home_id]['losses']++;
                $team_stats[$home_id]['points'] += $pts_loss;
            } else {
                // Draw
                $team_stats[$home_id]['draws']++;
                $team_stats[$away_id]['draws']++;
                $team_stats[$home_id]['points'] += $pts_draw;
                $team_stats[$away_id]['points'] += $pts_draw;
            }
        }

        // 4. Update the database
        $update_query = "UPDATE teams
                         SET wins = :wins,
                             losses = :losses,
                             draws = :draws,
                             points = :points,
                             goals_for = :goals_for,
                             goals_against = :goals_against,
                             matches_played = :matches_played,
                             score_difference = :score_difference
                         WHERE id = :team_id";

        $stmt = $this->db->prepare($update_query);

        foreach ($team_stats as $team_id => $stats) {
            $diff = $stats['goals_for'] - $stats['goals_against'];

            $stmt->bindValue(':wins', $stats['wins']);
            $stmt->bindValue(':losses', $stats['losses']);
            $stmt->bindValue(':draws', $stats['draws']);
            $stmt->bindValue(':points', $stats['points']);
            $stmt->bindValue(':goals_for', $stats['goals_for']);
            $stmt->bindValue(':goals_against', $stats['goals_against']);
            $stmt->bindValue(':matches_played', $stats['matches_played']);
            $stmt->bindValue(':score_difference', $diff);
            $stmt->bindValue(':team_id', $team_id);
            $stmt->execute();
        }
    }

    private function initStats() {
        return [
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'points' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'matches_played' => 0
        ];
    }

    /**
     * Get Standings sorted by Points, then Score Difference, then Head-to-Head (basic implementation).
     *
     * @param int $league_id
     * @return array
     */
    public function getStandings($league_id) {
        // Basic sort by SQL first
        $query = "SELECT * FROM teams
                  WHERE league_id = :league_id
                  ORDER BY points DESC, score_difference DESC, goals_for DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':league_id', $league_id);
        $stmt->execute();
        $standings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Implementing true Head-to-Head for ties requires more complex logic.
        // If two teams have same points and diff, check their match history.
        // This is a post-processing step.

        usort($standings, function($a, $b) use ($league_id) {
            if ($a['points'] != $b['points']) {
                return $b['points'] - $a['points']; // High to Low
            }
            if ($a['score_difference'] != $b['score_difference']) {
                return $b['score_difference'] - $a['score_difference']; // High to Low
            }

            // Head-to-Head Check
            return $this->checkHeadToHead($a['id'], $b['id'], $league_id);
        });

        return $standings;
    }

    private function checkHeadToHead($teamA_id, $teamB_id, $league_id) {
        // Find match between these two
        $query = "SELECT home_team_id, away_team_id, home_score, away_score
                  FROM matches
                  WHERE league_id = :league_id
                  AND status = 'completed'
                  AND ((home_team_id = :t1 AND away_team_id = :t2) OR (home_team_id = :t2 AND away_team_id = :t1))";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':league_id', $league_id);
        $stmt->bindParam(':t1', $teamA_id);
        $stmt->bindParam(':t2', $teamB_id);
        $stmt->execute();
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scoreA = 0;
        $scoreB = 0;

        foreach ($matches as $m) {
            if ($m['home_team_id'] == $teamA_id) {
                if ($m['home_score'] > $m['away_score']) $scoreA++; // Win
                elseif ($m['home_score'] < $m['away_score']) $scoreB++; // Loss
            } else {
                if ($m['away_score'] > $m['home_score']) $scoreA++; // Win
                elseif ($m['away_score'] < $m['home_score']) $scoreB++; // Loss
            }
        }

        return $scoreB - $scoreA; // If B > A, return positive (B comes first? No, usort expects positive if a > b? Wait.)
        // usort: $a, $b. return < 0 if $a < $b. return > 0 if $a > $b.
        // We want DESC order.
        // If $a better than $b, we want $a at index 0. So $a < $b in sorting terms?
        // No, PHP usort: "The comparison function must return an integer less than, equal to, or greater than zero if the first argument is considered to be respectively less than, equal to, or greater than the second."
        // For ascending: return $a - $b.
        // For descending: return $b - $a.

        // If $scoreA > $scoreB, A is better. We want A before B.
        // So return -1 (or negative).
        // return $scoreB - $scoreA.
        // If scoreA=1, scoreB=0. 0 - 1 = -1. Correct.
    }

    // --- Additional Formats Placeholders ---

    public function generatePlayoffs($league_id) {
        // Fetch qualified teams
        $league_query = "SELECT knockout_teams FROM leagues WHERE id = :league_id";
        $stmt = $this->db->prepare($league_query);
        $stmt->bindParam(':league_id', $league_id);
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        $num_qualified = $config['knockout_teams'] ?? 0;
        if ($num_qualified == 0) return []; // No playoffs configured

        $standings = $this->getStandings($league_id);
        $qualified_teams = array_slice($standings, 0, $num_qualified);

        // Seed teams: 1 vs 8, 2 vs 7, etc. (standard snake)
        // Or standard bracket: 1 vs 8, 4 vs 5, 2 vs 7, 3 vs 6
        // Let's implement standard bracket seeding for top 4 or 8.

        $matches = [];

        if ($num_qualified == 4) {
             // Semis: 1 vs 4, 2 vs 3
             $matches[] = [
                 'home_team_id' => $qualified_teams[0]['id'],
                 'away_team_id' => $qualified_teams[3]['id'],
                 'round' => 1, // Round 1 of playoffs
                 'match_type' => 'semi_final',
                 'bracket_pos' => 1
             ];
             $matches[] = [
                 'home_team_id' => $qualified_teams[1]['id'],
                 'away_team_id' => $qualified_teams[2]['id'],
                 'round' => 1,
                 'match_type' => 'semi_final',
                 'bracket_pos' => 2
             ];
        } elseif ($num_qualified == 8) {
            // Quarters
            // Match 1: 1 vs 8
            // Match 2: 4 vs 5
            // Match 3: 2 vs 7
            // Match 4: 3 vs 6
            $pairings = [[0, 7], [3, 4], [1, 6], [2, 5]];
            $pos = 1;
            foreach ($pairings as $pair) {
                $matches[] = [
                    'home_team_id' => $qualified_teams[$pair[0]]['id'],
                    'away_team_id' => $qualified_teams[$pair[1]]['id'],
                    'round' => 1,
                    'match_type' => 'quarter_final',
                    'bracket_pos' => $pos++
                ];
            }
        }

        return $matches;
    }

    public function generateSingleElimination($teams) {
        // Logic for bracket generation
        // 1. Determine power of 2 size (2, 4, 8, 16...)
        // 2. Seed teams (random or by rank)
        // 3. Create matches for Round 1
        return "Single Elimination Logic Here";
    }

    public function generateDoubleElimination($teams) {
        return "Double Elimination Logic Here";
    }
}
?>
