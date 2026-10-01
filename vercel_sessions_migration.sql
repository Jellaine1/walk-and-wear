CREATE TABLE IF NOT EXISTS php_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    INDEX idx_php_sessions_expires (expires_at)
);