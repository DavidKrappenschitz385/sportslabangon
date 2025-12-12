ALTER TABLE matches ADD COLUMN match_type enum('round_robin','quarter_final','semi_final','final','playoff') DEFAULT 'round_robin';
ALTER TABLE matches ADD COLUMN round int(11) DEFAULT NULL;
