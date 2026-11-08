<?php
/**
 * OptiVio — Shared CLI bootstrap.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit( 1 );
}

define( 'OPTIVIO_ROOT', dirname( __DIR__ ) );

require OPTIVIO_ROOT . '/app/bootstrap.php';

if ( ! OptiVio\Core\Config::is_loaded() ) {
	fwrite( STDERR, "config/config.php lipsește. Copiați config/config.sample.php.\n" ); // phpcs:ignore
	exit( 1 );
}

// Apply pending database upgrades (same as the first web request after a deploy).
OptiVio\Core\Migrator::ensure();

/**
 * Parses --key=value CLI options.
 *
 * @param array $argv Arguments.
 * @return array<string,string>
 */
function ov_cli_options( array $argv ): array {
	$options = array();
	foreach ( array_slice( $argv, 1 ) as $arg ) {
		if ( preg_match( '/^--([a-z_\-]+)(?:=(.*))?$/', $arg, $m ) ) {
			$options[ $m[1] ] = $m[2] ?? '1';
		}
	}
	return $options;
}

/**
 * Reads a line from STDIN without echo when possible (passwords).
 *
 * @param string $prompt Prompt.
 * @return string
 */
function ov_cli_secret( string $prompt ): string {
	fwrite( STDOUT, $prompt ); // phpcs:ignore
	if ( DIRECTORY_SEPARATOR === '/' ) {
		shell_exec( 'stty -echo' ); // phpcs:ignore
		$value = trim( (string) fgets( STDIN ) );
		shell_exec( 'stty echo' ); // phpcs:ignore
		fwrite( STDOUT, "\n" ); // phpcs:ignore
		return $value;
	}
	return trim( (string) fgets( STDIN ) );
}
