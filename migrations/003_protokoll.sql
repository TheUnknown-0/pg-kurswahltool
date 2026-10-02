-- Protokoll der Admin-Aktionen (wer hat wann was geändert, exportiert oder gelöscht)
CREATE TABLE admin_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_login VARCHAR(120) NOT NULL,
    action VARCHAR(60) NOT NULL,
    details VARCHAR(1000) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
