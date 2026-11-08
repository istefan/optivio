-- =============================================================================
-- OptiVio — Upgrade 1.5.0 -> 1.6.0 (cron web fără cPanel, backup Google Drive)
-- @author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
-- Se aplică AUTOMAT la primul request după deploy (Core/Migrator).
-- =============================================================================
SET NAMES utf8mb4;

ALTER TABLE `cron_runs`
	MODIFY `trigger_source` ENUM('cli','http','ajax','web') NOT NULL DEFAULT 'web';

-- -----------------------------------------------------------------------------
-- 12. Backup (bază de date + fișiere) în Google Drive
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `backups` (
	`id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`kind`           ENUM('db','files') NOT NULL,
	`trigger_source` ENUM('manual','scheduled') NOT NULL DEFAULT 'manual',
	`file_name`      VARCHAR(190) NULL,
	`size_bytes`     BIGINT UNSIGNED NULL,
	`sha256`         CHAR(64) CHARACTER SET ascii NULL,
	`drive_file_id`  VARCHAR(128) NULL COMMENT 'ID-ul fișierului în Google Drive (permisiune drive.file)',
	`local_name`     VARCHAR(190) NULL COMMENT 'Copie locală în storage/backups (opțional)',
	`status`         ENUM('pending','running','ok','failed','deleted') NOT NULL DEFAULT 'pending',
	`error`          VARCHAR(500) NULL,
	`created_by`     INT UNSIGNED NULL,
	`created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`finished_at`    DATETIME NULL,
	PRIMARY KEY (`id`),
	KEY `idx_bk_kind` (`kind`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_migrations` (`version`, `notes`) VALUES ('1.6.0', 'Cron web fără cPanel, backup Google Drive');
