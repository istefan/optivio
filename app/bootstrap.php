<?php
/**
 * OptiVio — Application bootstrap (shared by HTTP and CLI entry points).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

defined( 'OPTIVIO_ROOT' ) || exit;

define( 'OPTIVIO_VERSION', '1.4.0' );
define( 'OPTIVIO_SCHEMA_VERSION', '1.4.0' );
define( 'OPTIVIO_APP', OPTIVIO_ROOT . '/app' );
define( 'OPTIVIO_STORAGE', OPTIVIO_ROOT . '/storage' );

if ( version_compare( PHP_VERSION, '8.2.0', '<' ) ) {
	http_response_code( 500 );
	exit( 'OptiVio requires PHP 8.2 or newer.' );
}

/**
 * PSR-4-like autoloader mapped to WordPress file naming.
 *
 * OptiVio\Core\Http_Exception => app/core/class-http-exception.php
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'OptiVio\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$dirs     = array_map( 'strtolower', $parts );
		$file     = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
		$path     = OPTIVIO_APP . '/' . ( $dirs ? implode( '/', $dirs ) . '/' : '' ) . $file;

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

require OPTIVIO_APP . '/helpers.php';

OptiVio\Core\Config::load( OPTIVIO_ROOT . '/config/config.php' );

$ov_is_dev = ( 'development' === OptiVio\Core\Config::get( 'app_env', 'production' ) );

error_reporting( E_ALL );
ini_set( 'display_errors', $ov_is_dev ? '1' : '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- Controlled by environment.
ini_set( 'log_errors', '1' );
ini_set( 'error_log', OPTIVIO_STORAGE . '/logs/php-error.log' );

// All dates are handled in UTC internally; conversion happens only for display.
date_default_timezone_set( 'UTC' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set -- Plain PHP app, UTC is the storage standard.

mb_internal_encoding( 'UTF-8' );

unset( $ov_is_dev );
