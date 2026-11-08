-- =============================================================================
-- OptiVio — Upgrade 1.2.0 -> 1.3.0 (Etapa 6)
-- @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- Doar pentru baze existente. Instalările noi folosesc schema.sql.
-- =============================================================================
SET NAMES utf8mb4;

DELETE FROM `seo_audit_issues`;
ALTER TABLE `seo_audit_issues`
	ADD COLUMN `issue_hash` CHAR(64) CHARACTER SET ascii NOT NULL COMMENT 'sha256(cod|obiect) — upsert la fiecare audit' AFTER `issue_code`,
	ADD UNIQUE KEY `uq_sai_hash` (`site_id`, `issue_hash`);

DELETE FROM `keyword_insights`;
ALTER TABLE `keyword_insights`
	MODIFY COLUMN `insight_type` ENUM('cannibalization','ads_converting_no_landing','paid_while_top_organic','rejected_calls_keyword','striking_distance','low_ctr_high_position','ads_wasted_term','ads_bad_hours','other') NOT NULL,
	ADD COLUMN `insight_hash` CHAR(64) CHARACTER SET ascii NOT NULL COMMENT 'sha256(tip|cheie) — upsert, păstrează respingerile' AFTER `insight_type`,
	ADD UNIQUE KEY `uq_ki_hash` (`business_id`, `insight_hash`);

ALTER TABLE `keywords`
	MODIFY COLUMN `source` ENUM('gsc','ads','serp','manual','ai','competitor','generated') NOT NULL DEFAULT 'manual',
	ADD COLUMN `gsc_clicks`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ultimele 28 de zile (recalculat de motor)' AFTER `notes`,
	ADD COLUMN `gsc_impressions`   INT UNSIGNED NOT NULL DEFAULT 0,
	ADD COLUMN `gsc_position`      DECIMAL(6,2) NULL,
	ADD COLUMN `ads_clicks`        INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ultimele 30 de zile',
	ADD COLUMN `ads_cost_micros`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
	ADD COLUMN `ads_conversions`   DECIMAL(12,2) NOT NULL DEFAULT 0,
	ADD COLUMN `calls_total`       INT UNSIGNED NOT NULL DEFAULT 0,
	ADD COLUMN `calls_rejected`    INT UNSIGNED NOT NULL DEFAULT 0,
	ADD COLUMN `priority_score`    DECIMAL(8,2) NOT NULL DEFAULT 0,
	ADD COLUMN `metrics_at`        DATETIME NULL,
	ADD KEY `idx_kw_priority` (`business_id`, `status`, `priority_score`);

ALTER TABLE `competitors`
	ADD COLUMN `last_scanned_at` DATETIME NULL AFTER `notes`;

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES ('1.3.0', 'Etapa 6: motor determinist (audit SEO, insight-uri, cuvinte cheie, concurență)');
