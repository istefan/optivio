<?php
/**
 * OptiVio — HMAC-SHA256 request signing between OptiVio and the WordPress plugin.
 *
 * Canonical string (both directions):
 *   METHOD \n PATH \n TIMESTAMP \n NONCE \n hex(sha256(BODY))
 * Headers: X-OptiVio-Key (public key id), X-OptiVio-Timestamp (unix), X-OptiVio-Nonce (32 hex),
 *          X-OptiVio-Signature (hex HMAC-SHA256 with the per-site secret).
 * Anti-replay: timestamp must be within ±300 s and the nonce is stored per site until it expires.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Security;

use OptiVio\Core\Database;
use OptiVio\Repositories\Sites_Repository;
use PDOException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Connector signature helper.
 */
final class Connector_Signature {

	/**
	 * Allowed clock skew in seconds.
	 */
	public const WINDOW = 300;

	/**
	 * Builds the canonical string.
	 *
	 * @param string $method    HTTP method.
	 * @param string $path      Request path (no query string).
	 * @param int    $timestamp Unix timestamp.
	 * @param string $nonce     Nonce.
	 * @param string $body      Raw body.
	 * @return string
	 */
	public static function canonical( string $method, string $path, int $timestamp, string $nonce, string $body ): string {
		return strtoupper( $method ) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . hash( 'sha256', $body );
	}

	/**
	 * Signs a request.
	 *
	 * @param string $secret    Per-site secret.
	 * @param string $method    Method.
	 * @param string $path      Path.
	 * @param int    $timestamp Timestamp.
	 * @param string $nonce     Nonce.
	 * @param string $body      Body.
	 * @return string Hex signature.
	 */
	public static function sign( string $secret, string $method, string $path, int $timestamp, string $nonce, string $body ): string {
		return hash_hmac( 'sha256', self::canonical( $method, $path, $timestamp, $nonce, $body ), $secret );
	}

	/**
	 * Builds signed headers for an outgoing request (OptiVio → plugin).
	 *
	 * @param string $key_id Public key id.
	 * @param string $secret Secret.
	 * @param string $method Method.
	 * @param string $path   Path.
	 * @param string $body   Body.
	 * @return array<string,string>
	 */
	public static function headers( string $key_id, string $secret, string $method, string $path, string $body ): array {
		$timestamp = time();
		$nonce     = bin2hex( random_bytes( 16 ) );
		return array(
			'X-OptiVio-Key'       => $key_id,
			'X-OptiVio-Timestamp' => (string) $timestamp,
			'X-OptiVio-Nonce'     => $nonce,
			'X-OptiVio-Signature' => self::sign( $secret, $method, $path, $timestamp, $nonce, $body ),
		);
	}

	/**
	 * Verifies an incoming request (plugin → OptiVio).
	 *
	 * @param string $key_id    X-OptiVio-Key.
	 * @param string $timestamp X-OptiVio-Timestamp.
	 * @param string $nonce     X-OptiVio-Nonce.
	 * @param string $signature X-OptiVio-Signature.
	 * @param string $method    Method.
	 * @param string $path      Path.
	 * @param string $body      Raw body.
	 * @return array{ok:bool,site?:array,error?:string} The site row (without secret) on success.
	 */
	public static function verify( string $key_id, string $timestamp, string $nonce, string $signature, string $method, string $path, string $body ): array {
		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::WINDOW ) {
			return self::fail( 'stale_timestamp' );
		}
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return self::fail( 'malformed' );
		}

		$site = Sites_Repository::find_by_connector_key( $key_id );
		if ( null === $site ) {
			return self::fail( 'unknown_key' );
		}

		$expected = self::sign( (string) $site['secret'], $method, $path, (int) $timestamp, $nonce, $body );
		unset( $site['secret'] );
		if ( ! hash_equals( $expected, $signature ) ) {
			return self::fail( 'bad_signature' );
		}

		// Store the nonce only after the signature is valid (no table flooding by attackers).
		try {
			Database::instance()->insert(
				'connector_nonces',
				array(
					'site_id'    => (int) $site['id'],
					'nonce'      => $nonce,
					'expires_at' => Database::now( 2 * self::WINDOW ),
				)
			);
		} catch ( PDOException $e ) {
			return self::fail( 'replay' );
		}

		return array(
			'ok'   => true,
			'site' => $site,
		);
	}

	/**
	 * Failure tuple.
	 *
	 * @param string $error Error code.
	 * @return array{ok:bool,error:string}
	 */
	private static function fail( string $error ): array {
		return array(
			'ok'    => false,
			'error' => $error,
		);
	}
}
