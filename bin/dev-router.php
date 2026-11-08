<?php
/**
 * OptiVio — Router for PHP's built-in web server (local development only).
 *
 * Usage: php -S 127.0.0.1:8090 -t public bin/dev-router.php
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

if ( 'cli-server' !== PHP_SAPI ) {
	exit( 1 );
}

$ov_path = (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ); // phpcs:ignore
$ov_file = realpath( dirname( __DIR__ ) . '/public' . $ov_path );

if ( false !== $ov_file && is_file( $ov_file ) && 0 === strpos( $ov_file, realpath( dirname( __DIR__ ) . '/public' ) ) && ! preg_match( '/\.php$/', $ov_file ) ) {
	return false;
}

require dirname( __DIR__ ) . '/public/index.php';
