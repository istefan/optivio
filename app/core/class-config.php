<?php
/**
 * OptiVio — Static configuration store (config/config.php).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Read-only access to the file based configuration.
 */
final class Config {

	/**
	 * Loaded configuration.
	 *
	 * @var array<string,mixed>
	 */
	private static array $items = array();

	/**
	 * Whether a configuration file was found.
	 *
	 * @var bool
	 */
	private static bool $loaded = false;

	/**
	 * Loads the configuration file.
	 *
	 * @param string $path Absolute path to config.php.
	 * @return void
	 */
	public static function load( string $path ): void {
		if ( ! is_readable( $path ) ) {
			self::$items  = array();
			self::$loaded = false;
			return;
		}

		$config = require $path;
		if ( is_array( $config ) ) {
			self::$items  = $config;
			self::$loaded = true;
		}
	}

	/**
	 * Whether config.php exists and was loaded.
	 *
	 * @return bool
	 */
	public static function is_loaded(): bool {
		return self::$loaded;
	}

	/**
	 * Gets a configuration value using dot notation (e.g. "db.host").
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default when missing.
	 * @return mixed
	 */
	public static function get( string $key, $default_value = null ) {
		$value = self::$items;
		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default_value;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Whether the application runs in development mode.
	 *
	 * @return bool
	 */
	public static function is_dev(): bool {
		return 'development' === self::get( 'app_env', 'production' );
	}

	/**
	 * Returns the URL path component of app_url (e.g. "/optivio" or "").
	 *
	 * @return string
	 */
	public static function base_path(): string {
		$path = (string) parse_url( (string) self::get( 'app_url', '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Plain PHP.
		return rtrim( $path, '/' );
	}
}
