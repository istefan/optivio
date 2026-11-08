-- =============================================================================
-- OptiVio — Upgrade 1.0.0 -> 1.1.0 (Etapa 3)
-- @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- Doar pentru baze existente. Instalările noi folosesc schema.sql.
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE `form_entries`
	ADD COLUMN `ip_hash` CHAR(64) CHARACTER SET ascii NULL COMMENT 'HMAC(IP vizitator) — rate/duplicat spam, fără IP în clar' AFTER `device_type`,
	ADD COLUMN `user_agent_hash` CHAR(64) CHARACTER SET ascii NULL AFTER `ip_hash`,
	ADD KEY `idx_fe_ip` (`site_id`, `ip_hash`, `received_at`),
	ADD KEY `idx_fe_contact` (`site_id`, `contact_hash`, `received_at`);

ALTER TABLE `call_import_batches`
	ADD COLUMN `rows_matched` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Apeluri corelate automat cu click-uri tel:' AFTER `rows_skipped`;

ALTER TABLE `calls`
	ADD COLUMN `caller_last3` CHAR(3) CHARACTER SET ascii NULL COMMENT 'Ultimele 3 cifre, doar pentru afișare' AFTER `caller_hash`;

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES ('1.1.0', 'Etapa 3: ingest conector, spam scoring, apeluri');
