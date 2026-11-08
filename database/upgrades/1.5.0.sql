-- =============================================================================
-- OptiVio — Upgrade 1.4.0 -> 1.5.0 (Etapa 8: onboarding și percepția AI)
-- @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- Doar pentru baze existente. Instalările noi folosesc schema.sql.
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE `businesses`
	ADD COLUMN `onboarding_state` JSON NULL COMMENT 'Wizard: {step, skipped[], completed_at, draft_id}; NULL = creată manual' AFTER `notes`;

ALTER TABLE `ai_requests`
	MODIFY `profile_key` ENUM('fast','reasoning','copy','perception') NOT NULL;

ALTER TABLE `business_ai_profiles`
	MODIFY `profile_key` ENUM('fast','reasoning','copy','perception') NOT NULL;

-- -- 11. Onboarding și percepția AI (Etapa 8)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `onboarding_drafts` (
	`id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`created_by`     INT UNSIGNED NOT NULL,
	`business_id`    INT UNSIGNED NULL COMMENT 'Setat la pasul 3 (crearea afacerii)',
	`label`          VARCHAR(190) NOT NULL COMMENT 'Domeniul principal, pentru listă',
	`status`         ENUM('draft','fetching','fetched','analyzed','converted','abandoned') NOT NULL DEFAULT 'draft',
	`state`          JSON NULL COMMENT '{sites:[{url,host}], robots:{host:[disallow]}, perception_id}',
	`robots_override` TINYINT(1) NOT NULL DEFAULT 0,
	`created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_od_status` (`status`, `updated_at`),
	CONSTRAINT `fk_od_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_od_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `onboarding_pages` (
	`id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`draft_id`     INT UNSIGNED NOT NULL,
	`site_url`     VARCHAR(255) NOT NULL COMMENT 'Rădăcina site-ului căruia îi aparține pagina',
	`url`          VARCHAR(700) NOT NULL,
	`url_hash`     CHAR(64) CHARACTER SET ascii NOT NULL,
	`page_type`    ENUM('home','service','pricing','about','contact','blog','legal','other') NOT NULL DEFAULT 'other',
	`title_hint`   VARCHAR(300) NULL COMMENT 'Textul linkului / titlul din sitemap',
	`source`       ENUM('sitemap','menu','manual','home') NOT NULL DEFAULT 'sitemap',
	`selected`     TINYINT(1) NOT NULL DEFAULT 0,
	`fetch_status` ENUM('pending','fetching','ok','error','skipped') NOT NULL DEFAULT 'pending',
	`http_status`  SMALLINT UNSIGNED NULL,
	`extracted`    JSON NULL COMMENT 'Extragere deterministă: title, meta, headings, telefoane, emailuri, CUI, JSON-LD, prețuri',
	`body_text`    MEDIUMTEXT NULL COMMENT 'Text vizibil curățat (după eliminarea boilerplate)',
	`error`        VARCHAR(300) NULL,
	`fetched_at`   DATETIME NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_op_url` (`draft_id`, `url_hash`),
	KEY `idx_op_fetch` (`draft_id`, `selected`, `fetch_status`),
	CONSTRAINT `fk_op_draft` FOREIGN KEY (`draft_id`) REFERENCES `onboarding_drafts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `business_perceptions` (
	`id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`   INT UNSIGNED NULL COMMENT 'NULL cât timp e doar ciornă de onboarding',
	`draft_id`      INT UNSIGNED NULL,
	`site_id`       INT UNSIGNED NULL COMMENT 'Doar pentru percepția din interior (per site)',
	`source`        ENUM('external','internal') NOT NULL DEFAULT 'external',
	`is_baseline`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Amprenta inițială (prima percepție externă confirmată)',
	`model`         VARCHAR(120) NOT NULL,
	`pages_count`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	`input_chars`   INT UNSIGNED NOT NULL DEFAULT 0,
	`truncated`     TINYINT(1) NOT NULL DEFAULT 0,
	`pages`         JSON NULL COMMENT '[{url, page_type, chars, truncated}] — ce s-a trimis',
	`result`        JSON NOT NULL COMMENT 'Răspunsul AI validat — IMUABIL',
	`clarity_score` TINYINT UNSIGNED NULL COMMENT '0–100, calculat determinist după confirmare',
	`ai_request_id` BIGINT UNSIGNED NULL,
	`created_by`    INT UNSIGNED NOT NULL,
	`created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_bp_business` (`business_id`, `source`, `created_at`),
	KEY `idx_bp_draft` (`draft_id`),
	CONSTRAINT `fk_bp_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_bp_draft` FOREIGN KEY (`draft_id`) REFERENCES `onboarding_drafts` (`id`) ON DELETE SET NULL,
	CONSTRAINT `fk_bp_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_bp_ai` FOREIGN KEY (`ai_request_id`) REFERENCES `ai_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `perception_gaps` (
	`id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`business_id`     INT UNSIGNED NOT NULL,
	`perception_id`   BIGINT UNSIGNED NOT NULL,
	`field`           VARCHAR(40) NOT NULL COMMENT 'business_model, service, area, pillar, phone, email, audience, summary, unclear…',
	`item_key`        VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'Text::key() al elementului (servicii, zone, piloni)',
	`ai_value`        TEXT NULL,
	`confirmed_value` TEXT NULL,
	`gap_type`        ENUM('confirmed','corrected','missing_on_site','hallucinated','not_applicable','unclear') NOT NULL,
	`severity`        TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1 = cel mai grav',
	`evidence`        VARCHAR(300) NULL,
	`source_url`      VARCHAR(700) NULL,
	`action_card_id`  BIGINT UNSIGNED NULL,
	`created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_pg_item` (`perception_id`, `field`, `item_key`),
	KEY `idx_pg_business` (`business_id`, `gap_type`),
	CONSTRAINT `fk_pg_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_pg_perception` FOREIGN KEY (`perception_id`) REFERENCES `business_perceptions` (`id`) ON DELETE CASCADE,
	CONSTRAINT `fk_pg_card` FOREIGN KEY (`action_card_id`) REFERENCES `action_cards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES ('1.5.0', 'Etapa 8: wizard onboarding, percepția AI, diferențe percepție/realitate');
