<?php
/**
 * Plugin bootstrap: installation, cron schedules, module wiring.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class OVC_Plugin {

	/**
	 * Boots all modules.
	 *
	 * @return void
	 */
	public static function boot() {
		load_plugin_textdomain( 'optivio-connector', false, dirname( plugin_basename( OVC_FILE ) ) . '/languages' );

		if ( get_option( 'ovc_db_version' ) !== OVC_DB_VERSION ) {
			self::install_tables();
		}

		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );

		OVC_Queue::init();
		OVC_Tracking::init();
		OVC_Forms::init();
		OVC_Content::init();
		OVC_Remote::init();
		OVC_Heartbeat::init();

		if ( is_admin() ) {
			OVC_Admin::init();
		}
	}

	/**
	 * Activation: tables, cron events, first heartbeat + full content sync.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install_tables();
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );

		if ( ! wp_next_scheduled( 'ovc_heartbeat' ) ) {
			wp_schedule_event( time() + 30, 'hourly', 'ovc_heartbeat' );
		}
		if ( ! wp_next_scheduled( 'ovc_flush_queue' ) ) {
			wp_schedule_event( time() + 60, 'ovc_five_minutes', 'ovc_flush_queue' );
		}
		if ( ! wp_next_scheduled( 'ovc_content_full_sync' ) ) {
			wp_schedule_event( time() + 120, 'daily', 'ovc_content_full_sync' );
		}
		update_option( 'ovc_full_sync_pending', 1, false );
	}

	/**
	 * Deactivation: unschedule cron events (data is kept until uninstall).
	 *
	 * @return void
	 */
	public static function deactivate() {
		foreach ( array( 'ovc_heartbeat', 'ovc_flush_queue', 'ovc_content_full_sync', 'ovc_content_sync_batch' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Adds the 5 minutes schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['ovc_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'OptiVio — 5 minute',
		);
		return $schedules;
	}

	/**
	 * Creates / updates the plugin tables.
	 *
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$queue   = $wpdb->prefix . 'ovc_queue';
		$snaps   = $wpdb->prefix . 'ovc_snapshots';

		dbDelta(
			"CREATE TABLE {$queue} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				channel varchar(20) NOT NULL DEFAULT 'events',
				payload longtext NOT NULL,
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				next_attempt_gmt datetime NOT NULL,
				created_gmt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY channel_due (channel, next_attempt_gmt)
			) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$snaps} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				operation varchar(40) NOT NULL,
				object_type varchar(20) NOT NULL,
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				taxonomy varchar(60) NOT NULL DEFAULT '',
				data longtext NOT NULL,
				created_gmt datetime NOT NULL,
				rolled_back_gmt datetime NULL,
				PRIMARY KEY  (id),
				KEY object (object_type, object_id)
			) {$charset};"
		);

		update_option( 'ovc_db_version', OVC_DB_VERSION, false );
	}
}
