-- Grundschema des Kurswahl-Planers

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NULL,
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    display_name VARCHAR(160) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    UNIQUE KEY uq_users_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kurswahl-PDFs des Schulservers. Pro Schüler gilt die zuletzt importierte (active = 1).
CREATE TABLE school_pdfs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    login_key VARCHAR(120) NOT NULL,
    name VARCHAR(160) NOT NULL,
    klasse VARCHAR(40) NOT NULL DEFAULT '',
    jahrgang VARCHAR(40) NOT NULL DEFAULT '',
    schueler_id VARCHAR(20) NOT NULL DEFAULT '',
    abitur_jahrgang_id VARCHAR(20) NOT NULL DEFAULT '',
    pdf_created_at DATETIME NULL,
    filename VARCHAR(255) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    pdf MEDIUMBLOB NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pdf_hash (sha256),
    KEY idx_pdf_user (user_id, active),
    KEY idx_pdf_login (login_key),
    CONSTRAINT fk_pdf_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aktuelle Wahl eines Schülers (Zustand des Planers als JSON)
CREATE TABLE selections (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    state JSON NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sel_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Protokoll des Hintergrund-Imports (Upload und Import-Ordner)
CREATE TABLE import_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source ENUM('upload', 'ordner') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    status ENUM('importiert', 'aktualisiert', 'doppelt', 'fehler') NOT NULL,
    message VARCHAR(500) NOT NULL DEFAULT '',
    pdf_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_import_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(120) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_user (username, attempted_at),
    KEY idx_attempts_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kleine Schlüssel-Wert-Tabelle, z. B. für das Lebenszeichen des Import-Workers
CREATE TABLE settings (
    name VARCHAR(80) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
