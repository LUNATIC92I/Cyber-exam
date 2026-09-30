-- ============================================================================
--  CYBER EXAM — Database schema
--  MySQL 8.0+ (also compatible with MariaDB 10.6+)
--  Engine: InnoDB · Charset: utf8mb4 · All timestamps stored in UTC
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS webhook_events;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS certificate_verifications;
DROP TABLE IF EXISTS certificates;
DROP TABLE IF EXISTS answers;
DROP TABLE IF EXISTS exam_questions;
DROP TABLE IF EXISTS exam_attempts;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS certifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Candidates
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid              CHAR(36)        NOT NULL,
    first_name        VARCHAR(80)     NOT NULL,
    last_name         VARCHAR(80)     NOT NULL,
    email             VARCHAR(191)    NOT NULL,
    country           CHAR(2)         NOT NULL,
    password_hash     VARCHAR(255)    NOT NULL,
    email_verified_at DATETIME        NULL,
    status            ENUM('active','suspended') NOT NULL DEFAULT 'active',
    failed_logins     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until      DATETIME        NULL,
    last_login_at     DATETIME        NULL,
    last_login_ip     VARCHAR(45)     NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_uuid (uuid),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_created (created_at),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Back-office accounts (separate from candidates) with RBAC role
-- ----------------------------------------------------------------------------
CREATE TABLE admins (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36)        NOT NULL,
    display_name    VARCHAR(120)    NOT NULL,
    email           VARCHAR(191)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('super_admin','admin','support') NOT NULL DEFAULT 'admin',
    status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
    failed_logins   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME        NULL,
    last_login_at   DATETIME        NULL,
    last_login_ip   VARCHAR(45)     NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_uuid (uuid),
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Certification catalogue
-- ----------------------------------------------------------------------------
CREATE TABLE certifications (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code              VARCHAR(10)     NOT NULL,             -- used in certificate IDs, e.g. SOC
    slug              VARCHAR(80)     NOT NULL,
    name              VARCHAR(120)    NOT NULL,
    tagline           VARCHAR(255)    NOT NULL DEFAULT '',
    description       TEXT            NULL,
    topics            JSON            NULL,                 -- ["SIEM","Threat hunting",...]
    accent            VARCHAR(20)     NOT NULL DEFAULT 'blue',
    question_count    SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    duration_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 120,
    passing_score     TINYINT UNSIGNED  NOT NULL DEFAULT 70,
    price_cents       INT UNSIGNED    NOT NULL DEFAULT 1000,
    currency          CHAR(3)         NOT NULL DEFAULT 'EUR',
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    sort_order        SMALLINT        NOT NULL DEFAULT 0,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cert_code (code),
    UNIQUE KEY uq_cert_slug (slug),
    KEY idx_cert_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Question bank
-- ----------------------------------------------------------------------------
CREATE TABLE questions (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certification_id  BIGINT UNSIGNED NOT NULL,
    category          VARCHAR(80)     NOT NULL,
    difficulty        ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'intermediate',
    question          TEXT            NOT NULL,
    option_a          VARCHAR(500)    NOT NULL,
    option_b          VARCHAR(500)    NOT NULL,
    option_c          VARCHAR(500)    NOT NULL,
    option_d          VARCHAR(500)    NOT NULL,
    correct_option    CHAR(1)         NOT NULL,
    explanation       TEXT            NOT NULL,
    is_active         TINYINT(1)      NOT NULL DEFAULT 1,
    content_hash      CHAR(64)        NOT NULL,             -- idempotent seeding / duplicate detection
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_question_hash (certification_id, content_hash),
    KEY idx_q_cert_active (certification_id, is_active),
    KEY idx_q_category (certification_id, category),
    KEY idx_q_difficulty (certification_id, difficulty),
    CONSTRAINT fk_q_cert FOREIGN KEY (certification_id) REFERENCES certifications (id) ON DELETE CASCADE,
    CONSTRAINT chk_q_correct CHECK (correct_option IN ('A','B','C','D'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Payments (one confirmed payment = one exam attempt)
-- ----------------------------------------------------------------------------
CREATE TABLE payments (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid               CHAR(36)        NOT NULL,
    user_id            BIGINT UNSIGNED NOT NULL,
    certification_id   BIGINT UNSIGNED NOT NULL,
    amount             INT UNSIGNED    NOT NULL,             -- minor units (cents)
    currency           CHAR(3)         NOT NULL,
    transaction_id     VARCHAR(191)    NULL,                 -- provider checkout/session id
    provider_reference VARCHAR(191)    NULL,                 -- provider payment id (e.g. PaymentIntent)
    provider           VARCHAR(30)     NOT NULL,             -- stripe | sandbox | paypal | mobile_money ...
    status             ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    failure_reason     VARCHAR(255)    NULL,
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at            DATETIME        NULL,
    refunded_at        DATETIME        NULL,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pay_uuid (uuid),
    UNIQUE KEY uq_pay_provider_tx (provider, transaction_id),
    KEY idx_pay_user_cert_status (user_id, certification_id, status),
    KEY idx_pay_status_paid (status, paid_at),
    KEY idx_pay_reference (provider_reference),
    CONSTRAINT fk_pay_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_pay_cert FOREIGN KEY (certification_id) REFERENCES certifications (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency log for inbound payment webhooks
CREATE TABLE webhook_events (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider     VARCHAR(30)     NOT NULL,
    event_id     VARCHAR(191)    NOT NULL,
    event_type   VARCHAR(100)    NOT NULL,
    payment_id   BIGINT UNSIGNED NULL,
    processed_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_event (provider, event_id),
    KEY idx_webhook_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Exam attempts
-- ----------------------------------------------------------------------------
CREATE TABLE exam_attempts (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid               CHAR(36)        NOT NULL,
    user_id            BIGINT UNSIGNED NOT NULL,
    certification_id   BIGINT UNSIGNED NOT NULL,
    payment_id         BIGINT UNSIGNED NOT NULL,
    status             ENUM('in_progress','submitted','expired') NOT NULL DEFAULT 'in_progress',
    total_questions    SMALLINT UNSIGNED NOT NULL,
    duration_minutes   SMALLINT UNSIGNED NOT NULL,
    passing_score      TINYINT UNSIGNED  NOT NULL,
    started_at         DATETIME        NOT NULL,
    expires_at         DATETIME        NOT NULL,
    submitted_at       DATETIME        NULL,
    time_used_seconds  INT UNSIGNED    NULL,
    correct_count      SMALLINT UNSIGNED NULL,
    score_percent      DECIMAL(5,2)    NULL,
    passed             TINYINT(1)      NULL,
    ip_address         VARCHAR(45)     NULL,
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attempt_uuid (uuid),
    UNIQUE KEY uq_attempt_payment (payment_id),            -- a payment can only be consumed once
    KEY idx_attempt_user (user_id, status),
    KEY idx_attempt_cert (certification_id, status),
    KEY idx_attempt_expiry (status, expires_at),
    CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_attempt_cert FOREIGN KEY (certification_id) REFERENCES certifications (id) ON DELETE RESTRICT,
    CONSTRAINT fk_attempt_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions drawn for an attempt, with their randomised position and option order
CREATE TABLE exam_questions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id      BIGINT UNSIGNED NOT NULL,
    question_id     BIGINT UNSIGNED NOT NULL,
    position        SMALLINT UNSIGNED NOT NULL,             -- 1..100
    option_order    CHAR(4)         NOT NULL,               -- e.g. "CADB": displayed slot -> original option
    PRIMARY KEY (id),
    UNIQUE KEY uq_eq_position (attempt_id, position),
    UNIQUE KEY uq_eq_question (attempt_id, question_id),
    KEY idx_eq_question (question_id),
    CONSTRAINT fk_eq_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts (id) ON DELETE CASCADE,
    CONSTRAINT fk_eq_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidate answers (stored as the ORIGINAL option letter)
CREATE TABLE answers (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id        BIGINT UNSIGNED NOT NULL,
    exam_question_id  BIGINT UNSIGNED NOT NULL,
    selected_option   CHAR(1)         NOT NULL,
    is_correct        TINYINT(1)      NULL,                 -- filled in at scoring time
    answered_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_answer_eq (exam_question_id),
    KEY idx_answer_attempt (attempt_id),
    CONSTRAINT fk_answer_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts (id) ON DELETE CASCADE,
    CONSTRAINT fk_answer_eq FOREIGN KEY (exam_question_id) REFERENCES exam_questions (id) ON DELETE CASCADE,
    CONSTRAINT chk_answer_option CHECK (selected_option IN ('A','B','C','D'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Certificates
-- ----------------------------------------------------------------------------
CREATE TABLE certificates (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certificate_code    VARCHAR(40)     NOT NULL,           -- CEX-SOC-2026-7KQ2M9XA
    user_id             BIGINT UNSIGNED NOT NULL,
    attempt_id          BIGINT UNSIGNED NOT NULL,
    certification_id    BIGINT UNSIGNED NOT NULL,
    candidate_name      VARCHAR(170)    NOT NULL,           -- snapshot at issue time
    certification_name  VARCHAR(120)    NOT NULL,           -- snapshot at issue time
    score_percent       DECIMAL(5,2)    NOT NULL,
    issued_at           DATETIME        NOT NULL,
    status              ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
    revoked_at          DATETIME        NULL,
    revoked_reason      VARCHAR(255)    NULL,
    signature           CHAR(64)        NOT NULL,           -- HMAC-SHA256 of the certificate data (tamper evidence)
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cert_code_value (certificate_code),
    UNIQUE KEY uq_cert_attempt (attempt_id),
    KEY idx_cert_user (user_id),
    KEY idx_cert_status (status, issued_at),
    KEY idx_cert_name (candidate_name),
    CONSTRAINT fk_certificate_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_certificate_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts (id) ON DELETE RESTRICT,
    CONSTRAINT fk_certificate_cert FOREIGN KEY (certification_id) REFERENCES certifications (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public verification log
CREATE TABLE certificate_verifications (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certificate_id   BIGINT UNSIGNED NULL,
    queried_code     VARCHAR(64)     NOT NULL,
    result           ENUM('verified','revoked','not_found','invalid') NOT NULL,
    ip_hash          CHAR(64)        NOT NULL,              -- SHA-256 of IP + app key (no raw IP stored)
    user_agent       VARCHAR(255)    NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cv_certificate (certificate_id),
    KEY idx_cv_created (created_at),
    CONSTRAINT fk_cv_certificate FOREIGN KEY (certificate_id) REFERENCES certificates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Security & account lifecycle
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type   ENUM('user','admin','system','guest') NOT NULL,
    actor_id     BIGINT UNSIGNED NULL,
    action       VARCHAR(80)     NOT NULL,
    entity_type  VARCHAR(40)     NULL,
    entity_id    VARCHAR(64)     NULL,
    details      JSON            NULL,
    ip_address   VARCHAR(45)     NULL,
    user_agent   VARCHAR(255)    NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_actor (actor_type, actor_id),
    KEY idx_audit_action (action),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)        NOT NULL,                   -- SHA-256 of the emailed token
    expires_at  DATETIME        NOT NULL,
    used_at     DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ev_token (token_hash),
    KEY idx_ev_user (user_id),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)        NOT NULL,
    expires_at  DATETIME        NOT NULL,
    used_at     DATETIME        NULL,
    ip_address  VARCHAR(45)     NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pr_token (token_hash),
    KEY idx_pr_user (user_id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    rl_key      CHAR(64)        NOT NULL,                   -- SHA-256 of bucket name + identifier
    hits        INT UNSIGNED    NOT NULL DEFAULT 0,
    reset_at    DATETIME        NOT NULL,
    PRIMARY KEY (rl_key),
    KEY idx_rl_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key    VARCHAR(80)   NOT NULL,
    setting_value  TEXT          NOT NULL,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
    ('default_duration_minutes', '120'),
    ('default_price_cents', '1000'),
    ('default_currency', 'EUR'),
    ('passing_score', '70'),
    ('questions_per_exam', '100'),
    ('require_email_verification', '1'),
    ('registration_open', '1'),
    ('support_email', 'support@cyber-exam.example');
