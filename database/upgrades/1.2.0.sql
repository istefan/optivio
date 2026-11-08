-- =============================================================================
-- OptiVio — Upgrade 1.1.0 -> 1.2.0 (Etapa 4)
-- @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- Doar pentru baze existente. Instalările noi folosesc schema.sql.
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE `sites`
	ADD COLUMN `connector_remote_url` VARCHAR(500) NULL COMMENT 'Endpoint REST al pluginului (raportat la heartbeat, validat pe domeniul site-ului)' AFTER `connector_last_seen_at`;

ALTER TABLE `wp_seo_meta`
	ADD COLUMN `seo_title_resolved` VARCHAR(500) NULL COMMENT 'Titlul efectiv afișat (Yoast)' AFTER `seo_title`,
	ADD COLUMN `meta_description_resolved` VARCHAR(1000) NULL AFTER `meta_description`;

ALTER TABLE `wp_content`
	ADD COLUMN `featured_image` JSON NULL COMMENT '{id, src, alt}' AFTER `content_hash`;

ALTER TABLE `remote_operations`
	MODIFY COLUMN `operation` ENUM('ping','update_seo_meta','update_post','create_post','update_term','upload_media','update_links','rollback','resync') NOT NULL,
	ADD COLUMN `content_id` BIGINT UNSIGNED NULL COMMENT 'Obiectul oglindit afectat (wp_content)' AFTER `operation`,
	ADD COLUMN `object_type` ENUM('post','term','attachment','site') NOT NULL DEFAULT 'post' AFTER `content_id`,
	ADD COLUMN `taxonomy` VARCHAR(60) NULL AFTER `object_type`,
	ADD COLUMN `error` VARCHAR(500) NULL AFTER `attempts`,
	ADD KEY `idx_ro_content` (`content_id`, `created_at`);

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES ('1.2.0', 'Etapa 4: OptiVio Connector, oglindă conținut, operații remote');
