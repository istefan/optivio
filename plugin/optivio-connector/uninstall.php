<?php
/**
 * Uninstall: removes plugin tables and options (content in WordPress is untouched).
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ovc_queue" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ovc_snapshots" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

foreach ( array( 'ovc_db_version', 'ovc_connection', 'ovc_status', 'ovc_remote_config', 'ovc_sync_cursor', 'ovc_last_full_sync', 'ovc_full_sync_pending', 'ovc_last_error', 'ovc_author_id' ) as $ovc_option ) {
	delete_option( $ovc_option );
}
