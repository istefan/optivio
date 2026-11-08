<?php
/**
 * OptiVio — CSRF protection (synchronizer token + Origin check).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Per-session CSRF token.
 */
final class Csrf {

	/**
	 * Session key.
	 */
	private const SESSION_KEY = '_csrf_token';

	/**
	 * Returns (and lazily creates) the session CSRF token.
	 *
	 * @return string
	 */
	public static function token(): string {
		$token = Session::get( self::SESSION_KEY );
		if ( ! is_string( $token ) || 64 !== strlen( $token ) ) {
			$token = bin2hex( random_bytes( 32 ) );
			Session::set( self::SESSION_KEY, $token );
		}
		return $token;
	}

	/**
	 * Rotates the token (on login/logout).
	 *
	 * @return void
	 */
	public static function rotate(): void {
		Session::set( self::SESSION_KEY, bin2hex( random_bytes( 32 ) ) );
	}

	/**
	 * Verifies a state-changing request: token + same-origin.
	 *
	 * @param Request $request Request.
	 * @return bool
	 */
	public static function verify_request( Request $request ): bool {
		$submitted = $request->input( '_ov_csrf' );
		if ( ! is_string( $submitted ) || '' === $submitted ) {
			$submitted = $request->header( 'X-CSRF-Token' );
		}

		$expected = Session::get( self::SESSION_KEY );
		if ( ! is_string( $expected ) || ! is_string( $submitted ) || '' === $submitted ) {
			return false;
		}
		if ( ! hash_equals( $expected, $submitted ) ) {
			return false;
		}

		return self::is_same_origin( $request );
	}

	/**
	 * Checks Origin (or Referer as fallback) against the configured app URL.
	 *
	 * @param Request $request Request.
	 * @return bool True when same origin or when the browser sent neither header.
	 */
	public static function is_same_origin( Request $request ): bool {
		$app_origin = self::origin_of( (string) Config::get( 'app_url', '' ) );
		$origin     = $request->header( 'Origin' );

		if ( '' !== $origin && 'null' !== $origin ) {
			return hash_equals( $app_origin, self::origin_of( $origin ) );
		}

		$referer = $request->header( 'Referer' );
		if ( '' !== $referer ) {
			return hash_equals( $app_origin, self::origin_of( $referer ) );
		}

		// No Origin/Referer (privacy settings) — the token check alone applies.
		return true;
	}

	/**
	 * Normalises scheme://host[:port] of a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function origin_of( string $url ): string {
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$origin = strtolower( $parts['scheme'] . '://' . $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
		return $origin;
	}
}
