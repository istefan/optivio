<?php
/**
 * Hourly heartbeat: reports versions/capabilities and the remote API URL, receives the spam config.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Heartbeat module.
 */
final class OVC_Heartbeat {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'ovc_heartbeat', array( __CLASS__, 'send' ) );
	}

	/**
	 * Sends the heartbeat.
	 *
	 * @return array Result from OVC_Client::post().
	 */
	public static function send() {
		$result = OVC_Client::post(
			'/api/connector/v1/heartbeat',
			array(
				'plugin_version'    => OVC_VERSION,
				'wp_version'        => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
				'has_yoast'         => defined( 'WPSEO_VERSION' ),
				'has_gravity_forms' => class_exists( 'GFForms' ),
				'home_url'          => home_url( '/' ),
				'remote_url'        => rest_url( 'optivio/v1/remote' ),
			)
		);

		update_option(
			'ovc_status',
			array(
				'time'  => time(),
				'code'  => $result['code'],
				'error' => $result['error'],
			),
			false
		);
		if ( 200 === $result['code'] && isset( $result['body']['config']['spam'] ) ) {
			update_option(
				'ovc_remote_config',
				array(
					'min_fill_seconds'       => absint( $result['body']['config']['spam']['min_fill_seconds'] ?? 3 ),
					'blocked_words'          => array_map( 'sanitize_text_field', (array) ( $result['body']['config']['spam']['blocked_words'] ?? array() ) ),
					'max_events_per_request' => absint( $result['body']['config']['max_events_per_request'] ?? 100 ),
				),
				false
			);
		}
		return $result;
	}
}
