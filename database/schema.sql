-- AstroSampark Leads Marketplace Schema
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS astrosampark
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE astrosampark;

-- Users (astrologers, admins, lead-owners)
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150)        NOT NULL,
    phone          VARCHAR(20)         NOT NULL UNIQUE,
    email          VARCHAR(255)                 UNIQUE,
    password_hash  VARCHAR(255)        NOT NULL,
    role           ENUM('admin','astrologer','user') NOT NULL DEFAULT 'astrologer',
    wallet_balance DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
    rating         DECIMAL(3,2)        NOT NULL DEFAULT 0.00,
    created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status         ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
    INDEX idx_users_role     (role),
    INDEX idx_users_status   (status),
    INDEX idx_users_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leads (incoming enquiries)
CREATE TABLE IF NOT EXISTS leads (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source         ENUM('csv','meta_ads','manual','api') NOT NULL DEFAULT 'manual',
    category       VARCHAR(100)        NOT NULL,
    name           VARCHAR(150)        NOT NULL,
    phone          VARCHAR(20)         NOT NULL,
    email          VARCHAR(255),
    city           VARCHAR(100),
    state          VARCHAR(100),
    language       VARCHAR(50),
    budget_range   VARCHAR(50),
    notes          TEXT,
    consent_proof  VARCHAR(500),
    status         ENUM('pending','approved','rejected','sold','expired') NOT NULL DEFAULT 'pending',
    created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at    DATETIME,
    expires_at     DATETIME,
    quality_score  TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    duplicate_hash VARCHAR(64)         NOT NULL,
    INDEX idx_leads_duplicate_hash (duplicate_hash),
    INDEX idx_leads_status         (status),
    INDEX idx_leads_category       (category),
    INDEX idx_leads_city           (city),
    INDEX idx_leads_created        (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead listings (marketplace pricing)
CREATE TABLE IF NOT EXISTS lead_listings (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id           INT UNSIGNED        NOT NULL,
    price             DECIMAL(10,2)       NOT NULL,
    visibility        ENUM('public','private') NOT NULL DEFAULT 'public',
    min_rating_required DECIMAL(3,2)      NOT NULL DEFAULT 0.00,
    created_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active            TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT fk_ll_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    INDEX idx_ll_lead_id  (lead_id),
    INDEX idx_ll_active   (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead purchases
CREATE TABLE IF NOT EXISTS lead_purchases (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id         INT UNSIGNED        NOT NULL,
    astrologer_id   INT UNSIGNED        NOT NULL,
    purchase_price  DECIMAL(10,2)       NOT NULL,
    purchased_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reveal_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lead_state      ENUM('new','contacted','converted','not_interested') NOT NULL DEFAULT 'new',
    refund_status   ENUM('none','requested','approved','rejected') NOT NULL DEFAULT 'none',
    refund_reason   VARCHAR(255),
    refunded_at     DATETIME,
    CONSTRAINT fk_lp_lead        FOREIGN KEY (lead_id)       REFERENCES leads(id) ON DELETE RESTRICT,
    CONSTRAINT fk_lp_astrologer  FOREIGN KEY (astrologer_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_lp_lead_id       (lead_id),
    INDEX idx_lp_astrologer_id (astrologer_id),
    INDEX idx_lp_purchased_at  (purchased_at),
    UNIQUE KEY uq_lp_lead_astrologer (lead_id, astrologer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallets (one row per user, denormalized balance mirror)
CREATE TABLE IF NOT EXISTS wallets (
    user_id   INT UNSIGNED   NOT NULL PRIMARY KEY,
    balance   DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallet transactions
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED        NOT NULL,
    amount     DECIMAL(10,2)       NOT NULL,
    type       ENUM('credit','debit','refund') NOT NULL,
    ref_type   VARCHAR(50),
    ref_id     INT UNSIGNED,
    created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_wt_user_id   (user_id),
    INDEX idx_wt_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Escrow holds
CREATE TABLE IF NOT EXISTS escrow_holds (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT UNSIGNED        NOT NULL,
    amount      DECIMAL(10,2)       NOT NULL,
    status      ENUM('hold','released','refunded') NOT NULL DEFAULT 'hold',
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_eh_purchase FOREIGN KEY (purchase_id) REFERENCES lead_purchases(id) ON DELETE CASCADE,
    INDEX idx_eh_purchase_id (purchase_id),
    INDEX idx_eh_status      (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead activity logs
CREATE TABLE IF NOT EXISTS lead_activity_logs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id    INT UNSIGNED        NOT NULL,
    actor_type ENUM('admin','astrologer','system','webhook') NOT NULL,
    actor_id   INT UNSIGNED,
    action     VARCHAR(100)        NOT NULL,
    meta_json  JSON,
    created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lal_lead_id   (lead_id),
    INDEX idx_lal_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw Meta Lead Ads payloads
CREATE TABLE IF NOT EXISTS meta_lead_raw (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id        VARCHAR(50),
    form_id        VARCHAR(50),
    lead_id_meta   VARCHAR(50)         NOT NULL UNIQUE,
    raw_payload    JSON                NOT NULL,
    received_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mapped_lead_id INT UNSIGNED,
    CONSTRAINT fk_mlr_lead FOREIGN KEY (mapped_lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    INDEX idx_mlr_lead_id_meta  (lead_id_meta),
    INDEX idx_mlr_received_at   (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
