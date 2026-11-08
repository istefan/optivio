-- =============================================================================
-- OptiVio — Schema completă a bazei de date (MySQL 8.0+ / MariaDB 10.6+)
--
-- @package   OptiVio
-- @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- @copyright 2026 Ahoi Digital SRL
--
-- Reguli:
--  * Toate datele temporale sunt stocate în UTC (conexiunea setează time_zone = '+00:00').
--  * Toate secretele (chei API, tokeni OAuth, secrete conector) sunt stocate EXCLUSIV
--    criptat (AES-256-GCM) în coloane *_cipher. Niciodată în clar.
--  * Hash-urile de căutare (URL, cuvinte cheie, IP) sunt SHA-256 hex (CHAR(64) ascii).
--  * Acest fișier se rescrie INTEGRAL la orice modificare de structură (vezi RULES.md).
--
-- Versiune schemă: 1.4.0 (Etapa 7 — action_cards.card_hash/applied_note/publish_status, ai_requests.analysis_id/action_card_id/estimated_cost_usd)
-- Instalare nouă: importați acest fișier. Bază existentă: rulați în ordine database/upgrades/*.sql.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 0. Meta
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `schema_migrations`;
CREATE TABLE `schema_migrations` (
	`version`    VARCHAR(20)  NOT NULL,
	`applied_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`notes`      VARCHAR(255) NULL,
	PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 1. Utilizatori, acces, securitate
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
	`id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`email`                VARCHAR(190) NOT NULL,
	`password_hash`        VARCHAR(255) NOT NULL,
	`display_name`         VARCHAR(120) NOT NULL,
	`role`                 ENUM('super_admin','operator') NOT NULL DEFAULT 'operator',
	`status`               ENUM('active','disabled') NOT NULL DEFAULT 'active',
	`session_version`      INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Incrementat la schimbare parolă/dezactivare => invalidează sesiunile existente',
	`must_change_password` TINYINT(1)   NOT NULL DEFAULT 0,
	`password_changed_at`  DATETIME     NULL,
	`last_login_at`        DATETIME     NULL,
	`last_login_ip`        VARBINARY(16) NULL,
	`created_by`           INT UNSIGNED NULL,
	`created_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at`           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_users_email` (`email`),
	KEY `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_business_access`;
CREATE TABLE `user_business_access` (
	`user_id`     INT UNSIGNED NOT NULL,
	`business_id` INT UNSIGNED NOT NULL,
	`permission`  ENUM('view','manage') NOT NULL DEFAULT 'manage',
	`granted_by`  INT UNSIGNED NULL,
	`granted_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`user_id`, `business_id`),
	KEY `idx_uba_business` (`business_id`),
	CONSTRAINT `fk_uba_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_uba_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
	`bucket_hash`   CHAR(64) CHARACTER SET ascii NOT NULL,
	`hits`          INT UNSIGNED NOT NULL DEFAULT 0,
	`window_start`  DATETIME NOT NULL,
	`blocked_until` DATETIME NULL,
	PRIMARY KEY (`bucket_hash`),
	KEY `idx_rl_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
	`id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`email_hash`   CHAR(64) CHARACTER SET ascii NOT NULL,
	`ip`           VARBINARY(16) NULL,
	`user_id`      INT UNSIGNED NULL,
	`success`      TINYINT(1) NOT NULL DEFAULT 0,
	`reason`       VARCHAR(40) NULL,
	`attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_la_email_time` (`email_hash`, `attempted_at`),
	KEY `idx_la_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
	`id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id`    INT UNSIGNED NOT NULL,
	`token_hash` CHAR(64) CHARACTER SET ascii NOT NULL,
	`expires_at` DATETIME NOT NULL,
	`used_at`    DATETIME NULL,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_pr_token` (`token_hash`),
	KEY `idx_pr_user` (`user_id`),
	CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
	`id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id`     INT UNSIGNED NULL,
	`business_id` INT UNSIGNED NULL,
	`action`      VARCHAR(80)  NOT NULL COMMENT 'ex: auth.login, user.create, settings.update',
	`entity_type` VARCHAR(40)  NULL,
	`entity_id`   BIGINT UNSIGNED NULL,
	`ip`          VARBINARY(16) NULL,
	`user_agent`  VARCHAR(255) NULL,
	`meta`        JSON NULL COMMENT 'Fără secrete sau parole — doar chei schimbate / context',
	`created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_al_user` (`user_id`, `created_at`),
	KEY `idx_al_business` (`business_id`, `created_at`),
	KEY `idx_al_action` (`action`, `created_at`),
	KEY `idx_al_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
	`id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`user_id`     INT UNSIGNED NULL COMMENT 'NULL = pentru toți cei cu acces la business',
	`business_id` INT UNSIGNED NULL,
	`type`        VARCHAR(40)  NOT NULL,
	`severity`    ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
	`title`       VARCHAR(190) NOT NULL,
	`body`        VARCHAR(500) NULL,
	`url`         VARCHAR(255) NULL,
	`is_read`     TINYINT(1) NOT NULL DEFAULT 0,
	`created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_n_user_read` (`user_id`, `is_read`, `created_at`),
	KEY `idx_n_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Setări globale și credențiale criptate
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
	`setting_key`   VARCHAR(100) NOT NULL,
	`setting_value` MEDIUMTEXT NULL COMMENT 'JSON pentru valori normale; blob AES-256-GCM dacă is_encrypted = 1',
	`is_encrypted`  TINYINT(1) NOT NULL DEFAULT 0,
	`updated_by`    INT UNSIGNED NULL,
	`updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `api_credentials`;
CREATE TABLE `api_credentials` (
	`id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`scope`         ENUM('global','business','site') NOT NULL DEFAULT 'global',
	`business_id`   INT UNSIGNED NULL,
	`site_id`       INT UNSIGNED NULL,
	`provider`      ENUM('openrouter','serper','google_oauth','pagespeed','repuvio','other') NOT NULL,
	`label`         VARCHAR(120) NULL,
	`account_email` VARCHAR(190) NULL COMMENT 'Ex. contul Google conectat (nu este secret)',
	`secret_cipher` TEXT NOT NULL COMMENT 'AES-256-GCM (cheie API / refresh token)',
	`secret_hint`   VARCHAR(12) NULL COMMENT 'Ultimele 4 caractere, pentru afișare mascată',
	`meta`          JSON NULL COMMENT 'Scopes, expires_at access token etc. (fără secrete)',
	`status`        ENUM('active','invalid','revoked') NOT NULL DEFAULT 'active',
	`last_used_at`  DATETIME NULL,
	`created_by`    INT UNSIGNED NULL,
	`created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_ac_scope` (`scope`, `provider`),
	KEY `idx_ac_business` (`business_id`, `provider`),
	KEY `idx_ac_site` (`site_id`, `provider`),
	CONSTRAINT `fk_ac_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_ac_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Afaceri (clienți)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `businesses`;
CREATE TABLE `businesses` (
	`id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name`               VARCHAR(160) NOT NULL,
	`slug`               VARCHAR(180) NOT NULL,
	`status`             ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
	`legal_name`         VARCHAR(200) NULL,
	`cui`                VARCHAR(20)  NULL,
	`reg_com`            VARCHAR(40)  NULL,
	`vat_payer`          TINYINT(1)   NOT NULL DEFAULT 0,
	`billing_address`    VARCHAR(255) NULL,
	`billing_city`       VARCHAR(100) NULL,
	`billing_county`     VARCHAR(100) NULL,
	`billing_country`    CHAR(2)      NOT NULL DEFAULT 'RO',
	`billing_email`      VARCHAR(190) NULL,
	`phone`              VARCHAR(40)  NULL,
	`iban`               VARCHAR(40)  NULL,
	`bank_name`          VARCHAR(100) NULL,
	`business_model`     ENUM('emergency_service','recurring_service','local_service','b2b','ecommerce','lead_generation','publisher','other') NOT NULL DEFAULT 'other',
	`service_area`       VARCHAR(255) NULL COMMENT 'Localități / raza de deservire',
	`description`        TEXT NULL,
	`target_audience`    TEXT NULL,
	`usp_text`           TEXT NULL,
	`usp_source`         ENUM('manual','computed','ai') NULL,
	`usp_updated_at`     DATETIME NULL,
	`avg_job_value`      DECIMAL(12,2) NULL COMMENT 'Valoarea medie a unei lucrări/comenzi',
	`currency`           CHAR(3) NOT NULL DEFAULT 'RON',
	`timezone`           VARCHAR(64) NOT NULL DEFAULT 'Europe/Bucharest',
	`working_hours`      JSON NULL COMMENT 'Program de lucru — folosit în corelarea Ad Scheduling',
	`daily_capacity`     SMALLINT UNSIGNED NULL COMMENT 'Lucrări/zi — corelare apeluri refuzate',
	`repuvio_account_id` VARCHAR(64) NULL COMMENT 'Rezervat integrare RepuVio (repuvio.ro)',
	`repuvio_status`     ENUM('not_connected','pending','connected','error') NOT NULL DEFAULT 'not_connected',
	`notes`              TEXT NULL,
	`created_by`         INT UNSIGNED NULL,
	`created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_businesses_slug` (`slug`),
	KEY `idx_businesses_status` (`status`),
	KEY `idx_businesses_cui` (`cui`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `business_contacts`;
CREATE TABLE `business_contacts` (
	`id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id` INT UNSIGNED NOT NULL,
	`name`        VARCHAR(120) NOT NULL,
	`job_title`   VARCHAR(120) NULL,
	`email`       VARCHAR(190) NULL,
	`phone`       VARCHAR(40)  NULL,
	`is_primary`  TINYINT(1) NOT NULL DEFAULT 0,
	`notes`       VARCHAR(255) NULL,
	`created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_bc_business` (`business_id`),
	CONSTRAINT `fk_bc_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `business_offerings`;
CREATE TABLE `business_offerings` (
	`id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`      INT UNSIGNED NOT NULL,
	`type`             ENUM('service','product') NOT NULL DEFAULT 'service',
	`name`             VARCHAR(160) NOT NULL,
	`description`      TEXT NULL,
	`is_emergency`     TINYINT(1) NOT NULL DEFAULT 0,
	`is_recurring`     TINYINT(1) NOT NULL DEFAULT 0,
	`avg_ticket_value` DECIMAL(12,2) NULL,
	`margin_pct`       DECIMAL(5,2) NULL,
	`priority`         TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 = cel mai important',
	`is_active`        TINYINT(1) NOT NULL DEFAULT 1,
	`created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_bo_business` (`business_id`, `is_active`),
	CONSTRAINT `fk_bo_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `business_rules`;
CREATE TABLE `business_rules` (
	`id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`    INT UNSIGNED NOT NULL,
	`source`         ENUM('rejection','manual') NOT NULL DEFAULT 'manual',
	`action_card_id` BIGINT UNSIGNED NULL,
	`category`       VARCHAR(40) NULL,
	`rule_text`      TEXT NOT NULL COMMENT 'Regulă permanentă respectată de toate analizele viitoare',
	`is_active`      TINYINT(1) NOT NULL DEFAULT 1,
	`created_by`     INT UNSIGNED NULL,
	`created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_br_business` (`business_id`, `is_active`),
	CONSTRAINT `fk_br_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `business_ai_profiles`;
CREATE TABLE `business_ai_profiles` (
	`business_id`   INT UNSIGNED NOT NULL,
	`profile_key`   ENUM('fast','reasoning','copy') NOT NULL,
	`model`         VARCHAR(120) NOT NULL,
	`temperature`   DECIMAL(3,2) NULL,
	`max_tokens`    INT UNSIGNED NULL,
	`credential_id` INT UNSIGNED NULL COMMENT 'Cheia OpenRouter a clientului; NULL = cheia globală',
	`updated_by`    INT UNSIGNED NULL,
	`updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`business_id`, `profile_key`),
	CONSTRAINT `fk_bap_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_bap_credential` FOREIGN KEY (`credential_id`) REFERENCES `api_credentials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Site-uri web și integrări
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sites`;
CREATE TABLE `sites` (
	`id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`            INT UNSIGNED NOT NULL,
	`name`                   VARCHAR(160) NOT NULL,
	`url`                    VARCHAR(255) NOT NULL,
	`domain`                 VARCHAR(190) NOT NULL,
	`cms`                    ENUM('wordpress','other') NOT NULL DEFAULT 'wordpress',
	`status`                 ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
	`connector_key_id`       CHAR(24) CHARACTER SET ascii NULL COMMENT 'Identificator public al cheii (header X-OptiVio-Key)',
	`connector_secret_cipher` TEXT NULL COMMENT 'Secret HMAC-SHA256 unic per site, criptat AES-256-GCM',
	`connector_status`       ENUM('not_installed','pending','connected','error','revoked') NOT NULL DEFAULT 'not_installed',
	`connector_last_seen_at` DATETIME NULL,
	`connector_remote_url`  VARCHAR(500) NULL COMMENT 'Endpoint REST al pluginului (raportat la heartbeat, validat pe domeniul site-ului)',
	`plugin_version`         VARCHAR(20) NULL,
	`wp_version`             VARCHAR(20) NULL,
	`has_yoast`              TINYINT(1) NOT NULL DEFAULT 0,
	`has_gravity_forms`      TINYINT(1) NOT NULL DEFAULT 0,
	`content_synced_at`      DATETIME NULL,
	`created_by`             INT UNSIGNED NULL,
	`created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sites_connector_key` (`connector_key_id`),
	KEY `idx_sites_business` (`business_id`, `status`),
	KEY `idx_sites_domain` (`domain`),
	CONSTRAINT `fk_sites_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `site_integrations`;
CREATE TABLE `site_integrations` (
	`id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`       INT UNSIGNED NOT NULL,
	`provider`      ENUM('gsc','ga4','psi','gtm','ads','adsense','gbp') NOT NULL,
	`external_id`   VARCHAR(190) NULL COMMENT 'sc-domain:/URL GSC, properties/123 GA4, GTM-XXXX, customer id Ads, pub-xxx, locations/xxx',
	`credential_id` INT UNSIGNED NULL COMMENT 'Contul Google OAuth folosit',
	`status`        ENUM('not_configured','active','error','paused') NOT NULL DEFAULT 'not_configured',
	`settings`      JSON NULL,
	`last_sync_at`  DATETIME NULL,
	`last_error`    VARCHAR(500) NULL,
	`created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_si_site_provider` (`site_id`, `provider`),
	CONSTRAINT `fk_si_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_si_credential` FOREIGN KEY (`credential_id`) REFERENCES `api_credentials` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `connector_nonces`;
CREATE TABLE `connector_nonces` (
	`site_id`    INT UNSIGNED NOT NULL,
	`nonce`      CHAR(32) CHARACTER SET ascii NOT NULL,
	`expires_at` DATETIME NOT NULL,
	PRIMARY KEY (`site_id`, `nonce`),
	KEY `idx_cn_expires` (`expires_at`),
	CONSTRAINT `fk_cn_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Coadă de joburi (MySQL + Cron / AJAX batching)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
	`id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`queue`        VARCHAR(40) NOT NULL DEFAULT 'default',
	`type`         VARCHAR(80) NOT NULL COMMENT 'ex: gsc.sync, psi.audit, competitor.scan',
	`business_id`  INT UNSIGNED NULL,
	`site_id`      INT UNSIGNED NULL,
	`payload`      JSON NULL,
	`status`       ENUM('pending','running','done','failed','cancelled') NOT NULL DEFAULT 'pending',
	`priority`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
	`attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
	`max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
	`progress`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
	`cursor_state` JSON NULL COMMENT 'Stare pentru procesare incrementală (batch-uri)',
	`unique_key`   CHAR(64) CHARACTER SET ascii NULL COMMENT 'Deduplicare joburi identice active',
	`available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`reserved_by`  CHAR(32) CHARACTER SET ascii NULL,
	`reserved_at`  DATETIME NULL,
	`finished_at`  DATETIME NULL,
	`last_error`   VARCHAR(1000) NULL,
	`created_by`   INT UNSIGNED NULL,
	`created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_jobs_pick` (`status`, `queue`, `available_at`, `priority`),
	KEY `idx_jobs_reserved` (`reserved_by`),
	KEY `idx_jobs_unique` (`unique_key`, `status`),
	KEY `idx_jobs_business` (`business_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cron_runs`;
CREATE TABLE `cron_runs` (
	`id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`trigger_source` ENUM('cli','http','ajax') NOT NULL DEFAULT 'cli',
	`started_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`finished_at`    DATETIME NULL,
	`jobs_processed` INT UNSIGNED NOT NULL DEFAULT 0,
	`jobs_failed`    INT UNSIGNED NOT NULL DEFAULT 0,
	`status`         ENUM('running','ok','error') NOT NULL DEFAULT 'running',
	`message`        VARCHAR(500) NULL,
	PRIMARY KEY (`id`),
	KEY `idx_cr_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Evenimente primite din pluginul WordPress (atribuire, telefon, formulare)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `phone_clicks`;
CREATE TABLE `phone_clicks` (
	`id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`      INT UNSIGNED NOT NULL,
	`event_uuid`   CHAR(36) CHARACTER SET ascii NOT NULL,
	`phone_number` VARCHAR(40) NOT NULL,
	`page_url`     VARCHAR(500) NULL,
	`referrer`     VARCHAR(500) NULL,
	`landing_page` VARCHAR(500) NULL,
	`gclid`        VARCHAR(255) NULL,
	`utm_source`   VARCHAR(120) NULL,
	`utm_medium`   VARCHAR(120) NULL,
	`utm_campaign` VARCHAR(190) NULL,
	`utm_term`     VARCHAR(190) NULL,
	`utm_content`  VARCHAR(190) NULL,
	`channel`      ENUM('google_ads','organic','gbp','social','referral','direct','email','other') NOT NULL DEFAULT 'other' COMMENT 'Calculat determinist din atribuire',
	`device_type`  ENUM('mobile','tablet','desktop','unknown') NOT NULL DEFAULT 'unknown',
	`ip_hash`      CHAR(64) CHARACTER SET ascii NULL COMMENT 'HMAC(IP) — nu stocăm IP-ul vizitatorului',
	`occurred_at`  DATETIME NOT NULL,
	`received_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_pc_event` (`site_id`, `event_uuid`),
	KEY `idx_pc_site_time` (`site_id`, `occurred_at`),
	KEY `idx_pc_campaign` (`site_id`, `utm_campaign`, `occurred_at`),
	CONSTRAINT `fk_pc_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `form_entries`;
CREATE TABLE `form_entries` (
	`id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`          INT UNSIGNED NOT NULL,
	`external_entry_id` BIGINT UNSIGNED NOT NULL COMMENT 'ID intrare Gravity Forms',
	`form_id`          INT UNSIGNED NOT NULL,
	`form_title`       VARCHAR(190) NULL,
	`fields_cipher`    MEDIUMTEXT NULL COMMENT 'Câmpurile formularului (PII) criptate AES-256-GCM',
	`contact_hash`     CHAR(64) CHARACTER SET ascii NULL COMMENT 'HMAC(email/telefon) pentru deduplicare fără PII',
	`page_url`         VARCHAR(500) NULL,
	`landing_page`     VARCHAR(500) NULL,
	`referrer`         VARCHAR(500) NULL,
	`gclid`            VARCHAR(255) NULL,
	`utm_source`       VARCHAR(120) NULL,
	`utm_medium`       VARCHAR(120) NULL,
	`utm_campaign`     VARCHAR(190) NULL,
	`utm_term`         VARCHAR(190) NULL,
	`channel`          ENUM('google_ads','organic','gbp','social','referral','direct','email','other') NOT NULL DEFAULT 'other',
	`device_type`      ENUM('mobile','tablet','desktop','unknown') NOT NULL DEFAULT 'unknown',
	`ip_hash`          CHAR(64) CHARACTER SET ascii NULL COMMENT 'HMAC(IP vizitator) — rate/duplicat spam, fără IP în clar',
	`user_agent_hash`  CHAR(64) CHARACTER SET ascii NULL,
	`plugin_spam_flags` JSON NULL COMMENT 'Honeypot, timp completare, pattern-uri — de la plugin',
	`spam_score`       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100, calculat determinist în OptiVio',
	`spam_status`      ENUM('clean','suspect','spam') NOT NULL DEFAULT 'clean',
	`spam_reasons`     JSON NULL,
	`spam_override`    ENUM('none','marked_clean','marked_spam') NOT NULL DEFAULT 'none',
	`occurred_at`      DATETIME NOT NULL,
	`received_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_fe_entry` (`site_id`, `external_entry_id`),
	KEY `idx_fe_site_time` (`site_id`, `occurred_at`),
	KEY `idx_fe_spam` (`site_id`, `spam_status`, `occurred_at`),
	KEY `idx_fe_ip` (`site_id`, `ip_hash`, `received_at`),
	KEY `idx_fe_contact` (`site_id`, `contact_hash`, `received_at`),
	CONSTRAINT `fk_fe_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `call_import_batches`;
CREATE TABLE `call_import_batches` (
	`id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`   INT UNSIGNED NOT NULL,
	`original_name` VARCHAR(190) NOT NULL,
	`rows_total`    INT UNSIGNED NOT NULL DEFAULT 0,
	`rows_imported` INT UNSIGNED NOT NULL DEFAULT 0,
	`rows_skipped`  INT UNSIGNED NOT NULL DEFAULT 0,
	`rows_matched`  INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Apeluri corelate automat cu click-uri tel:',
	`created_by`    INT UNSIGNED NULL,
	`created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_cib_business` (`business_id`),
	CONSTRAINT `fk_cib_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `calls`;
CREATE TABLE `calls` (
	`id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`       INT UNSIGNED NOT NULL,
	`site_id`           INT UNSIGNED NULL,
	`phone_click_id`    BIGINT UNSIGNED NULL COMMENT 'Corelare cu click-ul tel: (dacă există)',
	`import_batch_id`   INT UNSIGNED NULL,
	`call_time`         DATETIME NOT NULL,
	`caller_hash`       CHAR(64) CHARACTER SET ascii NULL COMMENT 'HMAC(număr apelant)',
	`caller_last3`      CHAR(3) CHARACTER SET ascii NULL COMMENT 'Ultimele 3 cifre, doar pentru afișare',
	`duration_sec`      INT UNSIGNED NULL,
	`outcome`           ENUM('accepted','rejected_capacity','rejected_area','rejected_job_type','rejected_price','missed','spam','other') NOT NULL,
	`outcome_note`      VARCHAR(500) NULL,
	`revenue_amount`    DECIMAL(12,2) NULL,
	`currency`          CHAR(3) NOT NULL DEFAULT 'RON',
	`ads_campaign_id`   BIGINT UNSIGNED NULL,
	`keyword_text`      VARCHAR(190) NULL,
	`channel`           ENUM('google_ads','organic','gbp','social','referral','direct','email','other') NOT NULL DEFAULT 'other',
	`source`            ENUM('manual','import','tracking') NOT NULL DEFAULT 'manual',
	`created_by`        INT UNSIGNED NULL,
	`created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_calls_business_time` (`business_id`, `call_time`),
	KEY `idx_calls_outcome` (`business_id`, `outcome`, `call_time`),
	KEY `idx_calls_campaign` (`ads_campaign_id`),
	CONSTRAINT `fk_calls_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_calls_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
	CONSTRAINT `fk_calls_click` FOREIGN KEY (`phone_click_id`) REFERENCES `phone_clicks` (`id`) ON DELETE SET NULL,
	CONSTRAINT `fk_calls_batch` FOREIGN KEY (`import_batch_id`) REFERENCES `call_import_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Oglinda conținutului WordPress (Yoast + structură)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `wp_content`;
CREATE TABLE `wp_content` (
	`id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`          INT UNSIGNED NOT NULL,
	`object_type`      ENUM('post','term') NOT NULL,
	`wp_id`            BIGINT UNSIGNED NOT NULL,
	`subtype`          VARCHAR(60) NOT NULL COMMENT 'post_type sau taxonomy',
	`status`           VARCHAR(20) NOT NULL DEFAULT 'publish',
	`url`              VARCHAR(500) NOT NULL,
	`url_hash`         CHAR(64) CHARACTER SET ascii NOT NULL,
	`title`            VARCHAR(500) NULL,
	`slug`             VARCHAR(200) NULL,
	`parent_wp_id`     BIGINT UNSIGNED NULL,
	`word_count`       INT UNSIGNED NOT NULL DEFAULT 0,
	`h1_count`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`headings`         JSON NULL COMMENT '[{level, text}]',
	`internal_links_out` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`internal_links_in`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`images_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`images_missing_alt` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`content_hash`     CHAR(64) CHARACTER SET ascii NULL,
	`featured_image`   JSON NULL COMMENT '{id, src, alt}',
	`modified_gmt`     DATETIME NULL,
	`synced_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_wpc_object` (`site_id`, `object_type`, `wp_id`),
	KEY `idx_wpc_url` (`site_id`, `url_hash`),
	KEY `idx_wpc_subtype` (`site_id`, `subtype`, `status`),
	CONSTRAINT `fk_wpc_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wp_content_bodies`;
CREATE TABLE `wp_content_bodies` (
	`content_id` BIGINT UNSIGNED NOT NULL,
	`body_html`  MEDIUMTEXT NULL,
	`excerpt`    TEXT NULL,
	PRIMARY KEY (`content_id`),
	CONSTRAINT `fk_wcb_content` FOREIGN KEY (`content_id`) REFERENCES `wp_content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wp_seo_meta`;
CREATE TABLE `wp_seo_meta` (
	`content_id`       BIGINT UNSIGNED NOT NULL,
	`seo_title`        VARCHAR(500) NULL COMMENT 'Valoarea brută Yoast (poate conține %%variabile%%)',
	`seo_title_resolved` VARCHAR(500) NULL COMMENT 'Titlul efectiv afișat (Yoast)',
	`meta_description` VARCHAR(1000) NULL,
	`meta_description_resolved` VARCHAR(1000) NULL,
	`focus_keyword`    VARCHAR(190) NULL,
	`canonical`        VARCHAR(500) NULL,
	`robots_noindex`   TINYINT(1) NOT NULL DEFAULT 0,
	`robots_nofollow`  TINYINT(1) NOT NULL DEFAULT 0,
	`og_title`         VARCHAR(500) NULL,
	`og_description`   VARCHAR(1000) NULL,
	`og_image`         VARCHAR(500) NULL,
	`schema_types`     JSON NULL,
	`yoast_seo_score`  TINYINT UNSIGNED NULL,
	`yoast_read_score` TINYINT UNSIGNED NULL,
	PRIMARY KEY (`content_id`),
	CONSTRAINT `fk_wsm_content` FOREIGN KEY (`content_id`) REFERENCES `wp_content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `internal_links`;
CREATE TABLE `internal_links` (
	`id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`           INT UNSIGNED NOT NULL,
	`source_content_id` BIGINT UNSIGNED NOT NULL,
	`target_url`        VARCHAR(500) NOT NULL,
	`target_url_hash`   CHAR(64) CHARACTER SET ascii NOT NULL,
	`target_content_id` BIGINT UNSIGNED NULL,
	`anchor_text`       VARCHAR(500) NULL,
	`is_nofollow`       TINYINT(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `idx_il_source` (`source_content_id`),
	KEY `idx_il_target` (`site_id`, `target_url_hash`),
	CONSTRAINT `fk_il_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_il_source` FOREIGN KEY (`source_content_id`) REFERENCES `wp_content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wp_images`;
CREATE TABLE `wp_images` (
	`id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`       INT UNSIGNED NOT NULL,
	`content_id`    BIGINT UNSIGNED NULL,
	`attachment_id` BIGINT UNSIGNED NULL,
	`src`           VARCHAR(500) NOT NULL,
	`alt`           VARCHAR(500) NULL,
	`width`         SMALLINT UNSIGNED NULL,
	`height`        SMALLINT UNSIGNED NULL,
	`filesize`      INT UNSIGNED NULL,
	PRIMARY KEY (`id`),
	KEY `idx_wi_content` (`content_id`),
	KEY `idx_wi_site` (`site_id`, `attachment_id`),
	CONSTRAINT `fk_wi_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_wi_content` FOREIGN KEY (`content_id`) REFERENCES `wp_content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `seo_audit_issues`;
CREATE TABLE `seo_audit_issues` (
	`id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`     INT UNSIGNED NOT NULL,
	`content_id`  BIGINT UNSIGNED NULL,
	`issue_code`  VARCHAR(60) NOT NULL COMMENT 'ex: title_too_long, missing_h1, missing_alt, cannibalization',
	`issue_hash`  CHAR(64) CHARACTER SET ascii NOT NULL COMMENT 'sha256(cod|obiect) — upsert la fiecare audit',
	`severity`    ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
	`details`     JSON NULL,
	`detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`resolved_at` DATETIME NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sai_hash` (`site_id`, `issue_hash`),
	KEY `idx_sai_site_open` (`site_id`, `resolved_at`, `severity`),
	KEY `idx_sai_content` (`content_id`),
	CONSTRAINT `fk_sai_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_sai_content` FOREIGN KEY (`content_id`) REFERENCES `wp_content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Date Google (GSC, GA4, PSI, GTM, Ads, AdSense, GBP)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `gsc_daily`;
CREATE TABLE `gsc_daily` (
	`site_id`     INT UNSIGNED NOT NULL,
	`stat_date`   DATE NOT NULL,
	`query_hash`  CHAR(64) CHARACTER SET ascii NOT NULL,
	`page_hash`   CHAR(64) CHARACTER SET ascii NOT NULL,
	`device`      ENUM('mobile','desktop','tablet','all') NOT NULL DEFAULT 'all',
	`query`       VARCHAR(500) NOT NULL,
	`page`        VARCHAR(500) NOT NULL,
	`clicks`      INT UNSIGNED NOT NULL DEFAULT 0,
	`impressions` INT UNSIGNED NOT NULL DEFAULT 0,
	`position`    DECIMAL(6,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`site_id`, `stat_date`, `query_hash`, `page_hash`, `device`),
	KEY `idx_gsc_query` (`site_id`, `query_hash`, `stat_date`),
	KEY `idx_gsc_page` (`site_id`, `page_hash`, `stat_date`),
	CONSTRAINT `fk_gsc_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ga4_daily`;
CREATE TABLE `ga4_daily` (
	`site_id`          INT UNSIGNED NOT NULL,
	`stat_date`        DATE NOT NULL,
	`page_hash`        CHAR(64) CHARACTER SET ascii NOT NULL,
	`source_medium_hash` CHAR(64) CHARACTER SET ascii NOT NULL,
	`page_path`        VARCHAR(500) NOT NULL,
	`source`           VARCHAR(120) NULL,
	`medium`           VARCHAR(120) NULL,
	`sessions`         INT UNSIGNED NOT NULL DEFAULT 0,
	`users`            INT UNSIGNED NOT NULL DEFAULT 0,
	`engaged_sessions` INT UNSIGNED NOT NULL DEFAULT 0,
	`avg_engagement_sec` DECIMAL(10,2) NOT NULL DEFAULT 0,
	`conversions`      DECIMAL(12,2) NOT NULL DEFAULT 0,
	`revenue`          DECIMAL(12,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`site_id`, `stat_date`, `page_hash`, `source_medium_hash`),
	CONSTRAINT `fk_ga4_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `psi_audits`;
CREATE TABLE `psi_audits` (
	`id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`           INT UNSIGNED NOT NULL,
	`url`               VARCHAR(500) NOT NULL,
	`strategy`          ENUM('mobile','desktop') NOT NULL,
	`performance_score` TINYINT UNSIGNED NULL,
	`seo_score`         TINYINT UNSIGNED NULL,
	`accessibility_score` TINYINT UNSIGNED NULL,
	`best_practices_score` TINYINT UNSIGNED NULL,
	`lcp_ms`            INT UNSIGNED NULL,
	`inp_ms`            INT UNSIGNED NULL,
	`cls`               DECIMAL(6,3) NULL,
	`fcp_ms`            INT UNSIGNED NULL,
	`ttfb_ms`           INT UNSIGNED NULL,
	`tbt_ms`            INT UNSIGNED NULL,
	`opportunities`     JSON NULL,
	`fetched_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_psi_site_time` (`site_id`, `fetched_at`),
	CONSTRAINT `fk_psi_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gtm_snapshots`;
CREATE TABLE `gtm_snapshots` (
	`id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`      INT UNSIGNED NOT NULL,
	`container_id` VARCHAR(40) NOT NULL,
	`version_id`   VARCHAR(40) NULL,
	`tags_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`summary`      JSON NULL,
	`fetched_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_gtm_site` (`site_id`, `fetched_at`),
	CONSTRAINT `fk_gtm_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ads_campaigns`;
CREATE TABLE `ads_campaigns` (
	`id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`          INT UNSIGNED NOT NULL,
	`external_id`      VARCHAR(40) NOT NULL,
	`name`             VARCHAR(255) NOT NULL,
	`status`           VARCHAR(20) NOT NULL,
	`channel_type`     VARCHAR(40) NULL,
	`bidding_strategy` VARCHAR(60) NULL,
	`daily_budget_micros` BIGINT UNSIGNED NULL,
	`ad_schedule`      JSON NULL,
	`synced_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_adc_external` (`site_id`, `external_id`),
	CONSTRAINT `fk_adc_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ads_keywords`;
CREATE TABLE `ads_keywords` (
	`id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`campaign_id`   BIGINT UNSIGNED NOT NULL,
	`ad_group_id`   VARCHAR(40) NULL,
	`ad_group_name` VARCHAR(255) NULL,
	`external_id`   VARCHAR(40) NULL,
	`keyword_text`  VARCHAR(190) NOT NULL,
	`match_type`    ENUM('exact','phrase','broad') NOT NULL DEFAULT 'phrase',
	`is_negative`   TINYINT(1) NOT NULL DEFAULT 0,
	`status`        VARCHAR(20) NOT NULL DEFAULT 'ENABLED',
	`cpc_bid_micros` BIGINT UNSIGNED NULL,
	`quality_score` TINYINT UNSIGNED NULL,
	PRIMARY KEY (`id`),
	KEY `idx_adk_campaign` (`campaign_id`, `is_negative`),
	KEY `idx_adk_text` (`keyword_text`),
	CONSTRAINT `fk_adk_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ads_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ads_hourly`;
CREATE TABLE `ads_hourly` (
	`campaign_id`  BIGINT UNSIGNED NOT NULL,
	`stat_date`    DATE NOT NULL,
	`stat_hour`    TINYINT UNSIGNED NOT NULL,
	`impressions`  INT UNSIGNED NOT NULL DEFAULT 0,
	`clicks`       INT UNSIGNED NOT NULL DEFAULT 0,
	`cost_micros`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
	`conversions`  DECIMAL(12,2) NOT NULL DEFAULT 0,
	`conv_value`   DECIMAL(12,2) NOT NULL DEFAULT 0,
	`phone_calls`  INT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`campaign_id`, `stat_date`, `stat_hour`),
	CONSTRAINT `fk_adh_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ads_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ads_search_terms`;
CREATE TABLE `ads_search_terms` (
	`campaign_id`  BIGINT UNSIGNED NOT NULL,
	`stat_date`    DATE NOT NULL,
	`term_hash`    CHAR(64) CHARACTER SET ascii NOT NULL,
	`search_term`  VARCHAR(500) NOT NULL,
	`keyword_id`   BIGINT UNSIGNED NULL,
	`impressions`  INT UNSIGNED NOT NULL DEFAULT 0,
	`clicks`       INT UNSIGNED NOT NULL DEFAULT 0,
	`cost_micros`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
	`conversions`  DECIMAL(12,2) NOT NULL DEFAULT 0,
	PRIMARY KEY (`campaign_id`, `stat_date`, `term_hash`),
	KEY `idx_ast_term` (`term_hash`),
	CONSTRAINT `fk_ast_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `ads_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `adsense_daily`;
CREATE TABLE `adsense_daily` (
	`site_id`         INT UNSIGNED NOT NULL,
	`stat_date`       DATE NOT NULL,
	`page_hash`       CHAR(64) CHARACTER SET ascii NOT NULL,
	`page_url`        VARCHAR(500) NOT NULL,
	`page_views`      INT UNSIGNED NOT NULL DEFAULT 0,
	`impressions`     INT UNSIGNED NOT NULL DEFAULT 0,
	`clicks`          INT UNSIGNED NOT NULL DEFAULT 0,
	`earnings_micros` BIGINT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`site_id`, `stat_date`, `page_hash`),
	CONSTRAINT `fk_ads_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gbp_daily`;
CREATE TABLE `gbp_daily` (
	`site_id`            INT UNSIGNED NOT NULL,
	`location_id`        VARCHAR(80) NOT NULL,
	`stat_date`          DATE NOT NULL,
	`impressions_maps`   INT UNSIGNED NOT NULL DEFAULT 0,
	`impressions_search` INT UNSIGNED NOT NULL DEFAULT 0,
	`call_clicks`        INT UNSIGNED NOT NULL DEFAULT 0,
	`website_clicks`     INT UNSIGNED NOT NULL DEFAULT 0,
	`direction_requests` INT UNSIGNED NOT NULL DEFAULT 0,
	`conversations`      INT UNSIGNED NOT NULL DEFAULT 0,
	`bookings`           INT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`site_id`, `location_id`, `stat_date`),
	CONSTRAINT `fk_gbp_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Cuvinte cheie, concurență, insight-uri deterministe
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `keywords`;
CREATE TABLE `keywords` (
	`id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`       INT UNSIGNED NOT NULL,
	`keyword`           VARCHAR(190) NOT NULL,
	`keyword_hash`      CHAR(64) CHARACTER SET ascii NOT NULL,
	`channel`           ENUM('seo','ads','both') NOT NULL DEFAULT 'both',
	`intent`            ENUM('emergency','transactional','commercial','informational','navigational','local','unknown') NOT NULL DEFAULT 'unknown',
	`source`            ENUM('gsc','ads','serp','manual','ai','competitor','generated') NOT NULL DEFAULT 'manual',
	`status`            ENUM('proposed','tracked','excluded') NOT NULL DEFAULT 'proposed',
	`target_content_id` BIGINT UNSIGNED NULL,
	`notes`             VARCHAR(500) NULL,
	`gsc_clicks`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ultimele 28 de zile (recalculat de motor)',
	`gsc_impressions`   INT UNSIGNED NOT NULL DEFAULT 0,
	`gsc_position`      DECIMAL(6,2) NULL,
	`ads_clicks`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ultimele 30 de zile',
	`ads_cost_micros`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
	`ads_conversions`   DECIMAL(12,2) NOT NULL DEFAULT 0,
	`calls_total`       INT UNSIGNED NOT NULL DEFAULT 0,
	`calls_rejected`    INT UNSIGNED NOT NULL DEFAULT 0,
	`priority_score`    DECIMAL(8,2) NOT NULL DEFAULT 0,
	`metrics_at`        DATETIME NULL,
	`created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_kw_priority` (`business_id`, `status`, `priority_score`),
	UNIQUE KEY `uq_kw_business` (`business_id`, `keyword_hash`),
	CONSTRAINT `fk_kw_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_kw_content` FOREIGN KEY (`target_content_id`) REFERENCES `wp_content` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `keyword_insights`;
CREATE TABLE `keyword_insights` (
	`id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`  INT UNSIGNED NOT NULL,
	`site_id`      INT UNSIGNED NULL,
	`insight_type` ENUM('cannibalization','ads_converting_no_landing','paid_while_top_organic','rejected_calls_keyword','striking_distance','low_ctr_high_position','ads_wasted_term','ads_bad_hours','other') NOT NULL,
	`insight_hash` CHAR(64) CHARACTER SET ascii NOT NULL COMMENT 'sha256(tip|cheie) — upsert, păstrează respingerile',
	`keyword`      VARCHAR(190) NULL,
	`score`        DECIMAL(8,2) NOT NULL DEFAULT 0,
	`data`         JSON NULL,
	`computed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`dismissed_at` DATETIME NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_ki_hash` (`business_id`, `insight_hash`),
	KEY `idx_ki_business_type` (`business_id`, `insight_type`, `computed_at`),
	CONSTRAINT `fk_ki_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `serp_queries`;
CREATE TABLE `serp_queries` (
	`id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id` INT UNSIGNED NOT NULL,
	`keyword`     VARCHAR(190) NOT NULL,
	`location`    VARCHAR(120) NULL,
	`device`      ENUM('mobile','desktop') NOT NULL DEFAULT 'mobile',
	`results`     JSON NULL,
	`fetched_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_sq_business` (`business_id`, `fetched_at`),
	CONSTRAINT `fk_sq_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `competitors`;
CREATE TABLE `competitors` (
	`id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id` INT UNSIGNED NOT NULL,
	`domain`      VARCHAR(190) NOT NULL,
	`name`        VARCHAR(160) NULL,
	`source`      ENUM('serp','manual') NOT NULL DEFAULT 'manual',
	`is_active`   TINYINT(1) NOT NULL DEFAULT 1,
	`notes`       VARCHAR(500) NULL,
	`last_scanned_at` DATETIME NULL,
	`created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_comp_domain` (`business_id`, `domain`),
	CONSTRAINT `fk_comp_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `competitor_pages`;
CREATE TABLE `competitor_pages` (
	`id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`competitor_id`    INT UNSIGNED NOT NULL,
	`url`              VARCHAR(500) NOT NULL,
	`url_hash`         CHAR(64) CHARACTER SET ascii NOT NULL,
	`keyword`          VARCHAR(190) NULL,
	`serp_position`    TINYINT UNSIGNED NULL,
	`http_status`      SMALLINT UNSIGNED NULL,
	`title`            VARCHAR(500) NULL,
	`meta_description` VARCHAR(1000) NULL,
	`h1`               VARCHAR(500) NULL,
	`headings`         JSON NULL,
	`word_count`       INT UNSIGNED NULL,
	`term_vector`      JSON NULL COMMENT 'Top termeni (TF) — comparație acoperire subiecte',
	`schema_types`     JSON NULL,
	`content_hash`     CHAR(64) CHARACTER SET ascii NULL,
	`last_fetched_at`  DATETIME NULL,
	`created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_cp_url` (`competitor_id`, `url_hash`),
	CONSTRAINT `fk_cp_competitor` FOREIGN KEY (`competitor_id`) REFERENCES `competitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. AI on-demand, analize, Action Cards, operații la distanță
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ai_requests`;
CREATE TABLE `ai_requests` (
	`id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`       INT UNSIGNED NULL,
	`user_id`           INT UNSIGNED NOT NULL COMMENT 'Mereu un utilizator — AI doar la cerere explicită',
	`profile_key`       ENUM('fast','reasoning','copy') NOT NULL,
	`model`             VARCHAR(120) NOT NULL,
	`purpose`           VARCHAR(60) NOT NULL,
	`analysis_id`       BIGINT UNSIGNED NULL,
	`action_card_id`    BIGINT UNSIGNED NULL,
	`estimated_cost_usd` DECIMAL(10,6) NULL COMMENT 'Estimare afișată utilizatorului înainte de confirmare',
	`credential_scope`  ENUM('global','business') NOT NULL DEFAULT 'global',
	`prompt_tokens`     INT UNSIGNED NULL,
	`completion_tokens` INT UNSIGNED NULL,
	`cost_usd`          DECIMAL(10,6) NULL,
	`duration_ms`       INT UNSIGNED NULL,
	`status`            ENUM('pending','ok','error') NOT NULL DEFAULT 'pending',
	`error`             VARCHAR(500) NULL,
	`created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_air_business` (`business_id`, `created_at`),
	KEY `idx_air_user` (`user_id`, `created_at`),
	KEY `idx_air_month` (`created_at`, `credential_scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `analyses`;
CREATE TABLE `analyses` (
	`id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`    INT UNSIGNED NOT NULL,
	`site_id`        INT UNSIGNED NULL,
	`type`           ENUM('deep','keywords','ads_strategy','competitors','content','usp') NOT NULL,
	`status`         ENUM('collecting','ready','running','done','error') NOT NULL DEFAULT 'collecting',
	`input_snapshot` JSON NULL COMMENT 'Agregarea deterministă trimisă către AI (audit/reproducibilitate)',
	`output_text`    MEDIUMTEXT NULL,
	`ai_request_id`  BIGINT UNSIGNED NULL,
	`requested_by`   INT UNSIGNED NOT NULL,
	`created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`completed_at`   DATETIME NULL,
	PRIMARY KEY (`id`),
	KEY `idx_an_business` (`business_id`, `created_at`),
	CONSTRAINT `fk_an_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_an_ai` FOREIGN KEY (`ai_request_id`) REFERENCES `ai_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `action_cards`;
CREATE TABLE `action_cards` (
	`id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`      INT UNSIGNED NOT NULL,
	`site_id`          INT UNSIGNED NULL,
	`analysis_id`      BIGINT UNSIGNED NULL,
	`origin`           ENUM('deterministic','ai') NOT NULL DEFAULT 'deterministic',
	`category`         ENUM('ads_campaign','ads_new_campaign','ads_negative','ads_schedule','seo_meta','seo_content','internal_link','new_article','new_landing','technical','gbp','other') NOT NULL,
	`title`            VARCHAR(255) NOT NULL,
	`rationale`        TEXT NULL,
	`payload`          JSON NOT NULL COMMENT 'Modificarea propusă (structurată, aplicabilă de plugin)',
	`edited_payload`   JSON NULL COMMENT 'Varianta editată de utilizator înainte de aplicare',
	`priority`         TINYINT UNSIGNED NOT NULL DEFAULT 5,
	`impact_score`     DECIMAL(6,2) NULL,
	`status`           ENUM('proposed','approved','applying','applied','failed','rejected','rolled_back') NOT NULL DEFAULT 'proposed',
	`rejection_reason` TEXT NULL,
	`card_hash`        CHAR(64) CHARACTER SET ascii NULL COMMENT 'Deduplicare: cardurile respinse/aplicate nu se regenerează',
	`applied_note`     VARCHAR(500) NULL COMMENT 'Pentru carduri aplicate manual (ex. Google Ads)',
	`publish_status`   ENUM('draft','publish') NOT NULL DEFAULT 'draft' COMMENT 'Pentru conținut nou',
	`decided_by`       INT UNSIGNED NULL,
	`decided_at`       DATETIME NULL,
	`applied_at`       DATETIME NULL,
	`created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_acd_hash` (`business_id`, `card_hash`),
	KEY `idx_acd_business_status` (`business_id`, `status`, `priority`),
	CONSTRAINT `fk_acd_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_acd_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
	CONSTRAINT `fk_acd_analysis` FOREIGN KEY (`analysis_id`) REFERENCES `analyses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `uploads`;
CREATE TABLE `uploads` (
	`id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`   INT UNSIGNED NOT NULL,
	`original_name` VARCHAR(190) NOT NULL,
	`stored_name`   CHAR(68) CHARACTER SET ascii NOT NULL COMMENT 'Nume aleator în storage/uploads (în afara webroot)',
	`mime`          VARCHAR(60) NOT NULL,
	`size_bytes`    INT UNSIGNED NOT NULL,
	`sha256`        CHAR(64) CHARACTER SET ascii NOT NULL,
	`width`         SMALLINT UNSIGNED NULL,
	`height`        SMALLINT UNSIGNED NULL,
	`uploaded_by`   INT UNSIGNED NULL,
	`created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_up_stored` (`stored_name`),
	KEY `idx_up_business` (`business_id`),
	CONSTRAINT `fk_up_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `action_card_images`;
CREATE TABLE `action_card_images` (
	`id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`action_card_id` BIGINT UNSIGNED NOT NULL,
	`placement`      VARCHAR(60) NOT NULL DEFAULT 'featured' COMMENT 'featured, inline_1, ...',
	`prompt_text`    TEXT NULL COMMENT 'Prompt propus pentru generatoare AI de imagini',
	`alt_text`       VARCHAR(255) NULL,
	`upload_id`      BIGINT UNSIGNED NULL,
	`wp_attachment_id` BIGINT UNSIGNED NULL,
	PRIMARY KEY (`id`),
	KEY `idx_aci_card` (`action_card_id`),
	CONSTRAINT `fk_aci_card` FOREIGN KEY (`action_card_id`) REFERENCES `action_cards` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_aci_upload` FOREIGN KEY (`upload_id`) REFERENCES `uploads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `remote_operations`;
CREATE TABLE `remote_operations` (
	`id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`site_id`         INT UNSIGNED NOT NULL,
	`action_card_id`  BIGINT UNSIGNED NULL,
	`operation`       ENUM('ping','update_seo_meta','update_post','create_post','update_term','upload_media','update_links','rollback','resync') NOT NULL,
	`content_id`      BIGINT UNSIGNED NULL COMMENT 'Obiectul oglindit afectat (wp_content)',
	`object_type`     ENUM('post','term','attachment','site') NOT NULL DEFAULT 'post',
	`taxonomy`        VARCHAR(60) NULL,
	`request_payload` JSON NOT NULL,
	`status`          ENUM('queued','sent','applied','failed','rolled_back') NOT NULL DEFAULT 'queued',
	`remote_snapshot_id` VARCHAR(64) NULL COMMENT 'ID-ul snapshot-ului salvat de plugin înainte de modificare',
	`remote_object_id`   BIGINT UNSIGNED NULL,
	`response`        JSON NULL,
	`attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
	`error`           VARCHAR(500) NULL,
	`requested_by`    INT UNSIGNED NOT NULL,
	`created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`completed_at`    DATETIME NULL,
	`rolled_back_at`  DATETIME NULL,
	PRIMARY KEY (`id`),
	KEY `idx_ro_site` (`site_id`, `created_at`),
	KEY `idx_ro_card` (`action_card_id`),
	KEY `idx_ro_content` (`content_id`, `created_at`),
	CONSTRAINT `fk_ro_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_ro_card` FOREIGN KEY (`action_card_id`) REFERENCES `action_cards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES
	('1.0.0', 'Schema inițială completă (Etapa 1)'),
	('1.1.0', 'Etapa 3: ingest conector, spam scoring, apeluri'),
	('1.2.0', 'Etapa 4: OptiVio Connector, oglindă conținut, operații remote'),
	('1.3.0', 'Etapa 6: motor determinist (audit SEO, insight-uri, cuvinte cheie, concurență)'),
	('1.4.0', 'Etapa 7: AI on-demand, analize, Action Cards');
