-- Samapiece — schéma complet (tables vides)
-- Utilisé après recréation de la base par scripts/reset_database.php
-- (pas de DROP ici : la base est supprimée puis recréée)

SET NAMES utf8mb4;

CREATE TABLE users (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    nom VARCHAR(255) NOT NULL DEFAULT '',
    prenom VARCHAR(255) NOT NULL DEFAULT '',
    email VARCHAR(191) NULL,
    telephone VARCHAR(64) NOT NULL DEFAULT '',
    code_pays VARCHAR(16) NOT NULL DEFAULT '+221',
    password_hash VARCHAR(255) NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token VARCHAR(64) NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registration_type VARCHAR(32) NOT NULL DEFAULT 'password_email',
    date_naissance DATE NULL,
    email_otp_hash VARCHAR(64) NULL,
    email_otp_expires DATETIME NULL,
    UNIQUE KEY uk_users_email (email),
    UNIQUE KEY uk_users_telephone (telephone),
    KEY idx_users_reg_type (registration_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lost_items (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NULL,
    nom VARCHAR(255) NOT NULL DEFAULT '',
    prenom VARCHAR(255) NOT NULL DEFAULT '',
    date_naissance DATE NULL,
    lieu_naissance VARCHAR(255) NOT NULL DEFAULT '',
    categorie VARCHAR(64) NOT NULL DEFAULT '',
    telephone VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    photo1 VARCHAR(512) NULL,
    photo2 VARCHAR(512) NULL,
    recovery_status VARCHAR(32) NOT NULL DEFAULT 'en_attente',
    recovery_requested_at DATETIME NULL,
    recovery_requester_id VARCHAR(64) NULL,
    recovery_handover_code VARCHAR(16) NULL,
    recovery_handover_at DATETIME NULL,
    date_declared DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lost_user (user_id),
    KEY idx_lost_declared (date_declared),
    KEY idx_lost_recovery (recovery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alerts (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    title VARCHAR(255) NULL,
    message TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_alerts_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admins (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_admins_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE security_logs (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    user_id VARCHAR(64) NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sec_user (user_id),
    KEY idx_sec_time (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_reminders (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    notify_email VARCHAR(255) NOT NULL,
    nom VARCHAR(255) NOT NULL DEFAULT '',
    prenom VARCHAR(255) NOT NULL DEFAULT '',
    date_naissance DATE NULL,
    lieu_naissance VARCHAR(255) NOT NULL DEFAULT '',
    categorie VARCHAR(64) NOT NULL DEFAULT '',
    user_id VARCHAR(64) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_reminder_hits (
    reminder_id VARCHAR(64) NOT NULL,
    lost_item_id VARCHAR(64) NOT NULL,
    notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reminder_id, lost_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
