-- Migration: Add tournament_results and tournament_team_members tables
-- Run this if auto-schema in config.php does not apply

-- ============================================================
-- 1. tournament_results: stores final results per entity
-- ============================================================
CREATE TABLE IF NOT EXISTS tournament_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    result_scope ENUM('team','player') NOT NULL DEFAULT 'player',
    placement INT NULL,
    points INT NULL,
    points_earned INT DEFAULT 0,
    kills INT NULL,
    score DECIMAL(10,2) NULL,
    result_label VARCHAR(255) NULL,
    prize_amount DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT NULL,
    result_note TEXT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tournament_team (tournament_id, team_id),
    UNIQUE KEY uniq_tournament_player (tournament_id, user_id),
    INDEX idx_tournament_result (tournament_id),
    INDEX idx_user_result (user_id),
    INDEX idx_result_tournament_scope (tournament_id, result_scope),
    INDEX idx_result_user_tournament (user_id, tournament_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. tournament_leaderboard: aggregated points per player
-- ============================================================
CREATE TABLE IF NOT EXISTS tournament_leaderboard (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    total_points INT DEFAULT 0,
    total_prize DECIMAL(10,2) DEFAULT 0.00,
    tournaments_played INT DEFAULT 0,
    best_rank INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_player_leaderboard (tournament_id, user_id),
    INDEX idx_leaderboard_points (tournament_id, total_points DESC),
    INDEX idx_user_leaderboard (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Migration ALTER TABLE for existing installations
-- ============================================================
ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS result_scope ENUM('team','player') NOT NULL DEFAULT 'player' AFTER user_id;
ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS result_label VARCHAR(255) NULL AFTER score;
ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS prize_amount DECIMAL(10,2) DEFAULT 0.00 AFTER result_label;
ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS points_earned INT DEFAULT 0 AFTER points;
ALTER TABLE tournament_results ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER prize_amount;
ALTER TABLE tournament_results ADD INDEX IF NOT EXISTS idx_result_tournament_scope (tournament_id, result_scope);
ALTER TABLE tournament_results ADD INDEX IF NOT EXISTS idx_result_user_tournament (user_id, tournament_id);
