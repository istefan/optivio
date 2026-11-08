<?php
/**
 * Connection settings: values embedded at download (connection.php) or overridden in the admin.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings accessor.
 */
final class OVC_Settings {

	/**
	 * Cached connection.
	 *
	 * @var array|null
	 */
	private static $connection = null;

	/**
	 * Returns the connection: api_base, key_id, secret.
	 *
	 * @return array{api_base:string,key_id:string,secret:string}
	 */
	public static function connection() {
		if ( null !== self::$connection ) {
			return self::$connection;
		}

		$file = array();
		if ( is_readable( OVC_DIR . 'connection.php' ) ) {
			$loaded = include OVC_DIR . 'connection.php';
			$file   = is_array( $loaded ) ? $loaded : array();
		}
		$override = get_option( 'ovc_connection', array() );
		$override = is_array( $override ) ? array_filter( $override ) : array();

		self::$connection = array(
			'api_base' => untrailingslashit( (string) ( $override['api_base'] ?? $file['api_base'] ?? '' ) ),
			'key_id'   => (string) ( $override['key_id'] ?? $file['key_id'] ?? '' ),
			'secret'   => (string) ( $override['secret'] ?? $file['secret'] ?? '' ),
		);
		return self::$connection;
	}

	/**
	 * Whether the connection is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$c = self::connection();
		return '' !== $c['api_base'] && 1 === preg_match( '/^ovk_[a-f0-9]{20}$/', $c['key_id'] ) && strlen( $c['secret'] ) >= 32;
	}

	/**
	 * Clears the cache after an override.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$connection = null;
	}

	/**
	 * Remote configuration received at heartbeat (spam settings).
	 *
	 * @return array
	 */
	public static function remote_config() {
		$config = get_option( 'ovc_remote_config', array() );
		return wp_parse_args(
			is_array( $config ) ? $config : array(),
			array(
				'min_fill_seconds'       => 3,
				'blocked_words'          => array(),
				'max_events_per_request' => 100,
			)
		);
	}

	/**
	 * HMAC of the visitor IP is computed by OptiVio; here we only read a trustworthy IP.
	 *
	 * @return string
	 */
	public static function visitor_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Visitor user agent (truncated).
	 *
	 * @return string
	 */
	public static function user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 400 ) : '';
	}
}
