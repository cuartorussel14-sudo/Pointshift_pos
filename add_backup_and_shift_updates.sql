-- Add backup and restore logging tables
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(1024) DEFAULT NULL,
    checksum VARCHAR(128) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    encrypted TINYINT(1) DEFAULT 0,
    size BIGINT DEFAULT NULL,
    duration_seconds INT DEFAULT NULL,
    status ENUM('success','failed') DEFAULT 'success',
    notes TEXT,
    INDEX (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS restore_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_id INT DEFAULT NULL,
    filename VARCHAR(255) NOT NULL,
    restored_at DATETIME NOT NULL,
    restored_by INT DEFAULT NULL,
    duration_seconds INT DEFAULT NULL,
    status ENUM('success','failed') DEFAULT 'success',
    notes TEXT,
    FOREIGN KEY (backup_id) REFERENCES backup_logs(id),
    FOREIGN KEY (restored_by) REFERENCES users(id),
    INDEX (restored_at)
);

-- Optional: retention table to track cleanup policies
CREATE TABLE IF NOT EXISTS backup_retention (
    id INT AUTO_INCREMENT PRIMARY KEY,
    days_to_keep INT NOT NULL DEFAULT 30,
    last_run DATETIME DEFAULT NULL
);