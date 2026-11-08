<?php
/**
 * OptiVio — HTTP response helpers and security headers.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Static response helpers.
 */
final class Response {

	/**
	 * Sends the hardened security headers.
	 *
	 * @param bool $https Whether the request is over HTTPS.
	 * @return void
	 */
	public static function security_headers( bool $https ): void {
		if ( headers_sent() ) {
			return;
		}

		$csp = implode(
			'; ',
			array(
				"default-src 'self'",
				"script-src 'self'",
				"style-src 'self' 'unsafe-inline'",
				"img-src 'self' data:",
				"font-src 'self'",
				"connect-src 'self'",
				"form-action 'self'",
				"frame-ancestors 'none'",
				"base-uri 'self'",
				"object-src 'none'",
			)
		);

		header( 'Content-Security-Policy: ' . $csp );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()' );
		header( 'Cross-Origin-Opener-Policy: same-origin' );
		header( 'Cross-Origin-Resource-Policy: same-origin' );
		header_remove( 'X-Powered-By' );

		if ( $https ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}

	/**
	 * Disables caching for authenticated pages.
	 *
	 * @return void
	 */
	public static function no_cache(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
		header( 'Pragma: no-cache' );
	}

	/**
	 * Redirects to an internal path and terminates.
	 *
	 * @param string $path  Application path ("/dashboard") or absolute app URL.
	 * @param int    $status HTTP status.
	 * @return never
	 */
	public static function redirect( string $path, int $status = 302 ): never {
		$url = ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) ) ? $path : ov_url( $path );

		// Only allow redirects to our own origin (open redirect protection).
		$app = (string) Config::get( 'app_url', '' );
		if ( 0 !== strpos( $url, $app ) ) {
			$url = ov_url( '/' );
		}

		header( 'Location: ' . $url, true, $status );
		exit;
	}

	/**
	 * Sends a JSON response and terminates.
	 *
	 * @param mixed $data   Data.
	 * @param int   $status HTTP status.
	 * @return never
	 */
	public static function json( $data, int $status = 200 ): never {
		http_response_code( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		self::no_cache();
		echo json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response with proper content type.
		exit;
	}

	/**
	 * Sends plain text and terminates.
	 *
	 * @param string $text   Text.
	 * @param int    $status HTTP status.
	 * @return never
	 */
	public static function text( string $text, int $status = 200 ): never {
		http_response_code( $status );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain response.
		exit;
	}
}
