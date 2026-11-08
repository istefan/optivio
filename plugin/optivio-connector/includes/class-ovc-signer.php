<?php
/**
 * HMAC-SHA256 signing — identical canonical form to OptiVio\Security\Connector_Signature.
 *
 * Canonical: METHOD \n PATH \n TIMESTAMP \n NONCE \n hex(sha256(BODY))
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signer / verifier.
 */
final class OVC_Signer {

	const WINDOW = 300;

	/**
	 * Signature.
	 *
	 * @param string $secret    Secret.
	 * @param string $method    Method.
	 * @param string $path      Path (+ query when present).
	 * @param int    $timestamp Timestamp.
	 * @param string $nonce     Nonce.
	 * @param string $body      Body.
	 * @return string
	 */
	public static function sign( $secret, $method, $path, $timestamp, $nonce, $body ) {
		$canonical = strtoupper( $method ) . "\n" . $path . "\n" . (int) $timestamp . "\n" . $nonce . "\n" . hash( 'sha256', $body );
		return hash_hmac( 'sha256', $canonical, $secret );
	}

	/**
	 * Signed headers for an outgoing request.
	 *
	 * @param string $method Method.
	 * @param string $path   Path relative to the OptiVio app URL.
	 * @param string $body   Body.
	 * @return array<string,string>
	 */
	public static function headers( $method, $path, $body ) {
		$c         = OVC_Settings::connection();
		$timestamp = time();
		$nonce     = bin2hex( random_bytes( 16 ) );
		return array(
			'X-OptiVio-Key'       => $c['key_id'],
			'X-OptiVio-Timestamp' => (string) $timestamp,
			'X-OptiVio-Nonce'     => $nonce,
			'X-OptiVio-Signature' => self::sign( $c['secret'], $method, $path, $timestamp, $nonce, $body ),
		);
	}

	/**
	 * Verifies a request coming from OptiVio (remote operations).
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $path    Canonical path of this endpoint.
	 * @return true|WP_Error
	 */
	public static function verify_request( $request, $path ) {
		if ( ! OVC_Settings::is_configured() ) {
			return new WP_Error( 'ovc_not_configured', 'Not configured.', array( 'status' => 503 ) );
		}
		$c         = OVC_Settings::connection();
		$key       = (string) $request->get_header( 'x_optivio_key' );
		$timestamp = (string) $request->get_header( 'x_optivio_timestamp' );
		$nonce     = strtolower( (string) $request->get_header( 'x_optivio_nonce' ) );
		$signature = strtolower( (string) $request->get_header( 'x_optivio_signature' ) );

		if ( ! hash_equals( $c['key_id'], $key ) ) {
			return self::deny();
		}
		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > self::WINDOW ) {
			return self::deny();
		}
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $nonce ) || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return self::deny();
		}

		$expected = self::sign( $c['secret'], 'POST', $path, (int) $timestamp, $nonce, (string) $request->get_body() );
		if ( ! hash_equals( $expected, $signature ) ) {
			return self::deny();
		}

		// Anti-replay: remember the nonce for twice the window.
		$transient = 'ovc_nonce_' . $nonce;
		if ( false !== get_transient( $transient ) ) {
			return self::deny();
		}
		set_transient( $transient, 1, 2 * self::WINDOW );
		return true;
	}

	/**
	 * Generic denial (no detail leaked).
	 *
	 * @return WP_Error
	 */
	private static function deny() {
		return new WP_Error( 'ovc_unauthorized', 'Unauthorized.', array( 'status' => 401 ) );
	}
}
