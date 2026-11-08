-- =============================================================================
-- OptiVio — Upgrade 1.3.0 -> 1.4.0 (Etapa 7)
-- @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- Doar pentru baze existente. Instalările noi folosesc schema.sql.
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE `ai_requests`
	ADD COLUMN `analysis_id` BIGINT UNSIGNED NULL AFTER `purpose`,
	ADD COLUMN `action_card_id` BIGINT UNSIGNED NULL AFTER `analysis_id`,
	ADD COLUMN `estimated_cost_usd` DECIMAL(10,6) NULL COMMENT 'Estimare afișată utilizatorului înainte de confirmare' AFTER `action_card_id`,
	ADD KEY `idx_air_month` (`created_at`, `credential_scope`);

ALTER TABLE `action_cards`
	ADD COLUMN `card_hash` CHAR(64) CHARACTER SET ascii NULL COMMENT 'Deduplicare: cardurile respinse/aplicate nu se regenerează' AFTER `rejection_reason`,
	ADD COLUMN `applied_note` VARCHAR(500) NULL COMMENT 'Pentru carduri aplicate manual (ex. Google Ads)' AFTER `card_hash`,
	ADD COLUMN `publish_status` ENUM('draft','publish') NOT NULL DEFAULT 'draft' COMMENT 'Pentru conținut nou' AFTER `applied_note`,
	ADD UNIQUE KEY `uq_acd_hash` (`business_id`, `card_hash`);

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES ('1.4.0', 'Etapa 7: AI on-demand, analize, Action Cards');
