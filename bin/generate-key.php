<?php
/**
 * OptiVio — Generates master_key, install_token and cron_token values for config.php.
 *
 * Usage: php bin/generate-key.php
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$ov_token = static function ( int $bytes ): string {
	return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
};

fwrite( STDOUT, "'master_key'    => '" . base64_encode( random_bytes( 32 ) ) . "',\n" ); // phpcs:ignore
fwrite( STDOUT, "'install_token' => '" . $ov_token( 24 ) . "',\n" ); // phpcs:ignore
fwrite( STDOUT, "'cron_token'    => '" . $ov_token( 32 ) . "',\n" ); // phpcs:ignore
