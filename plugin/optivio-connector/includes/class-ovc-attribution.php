<?php
/**
 * First-party attribution cookie (written by ovc-tracking.js, read server side).
 *
 * Cookie "ovc_attr": base64(JSON) with gclid, gbraid, wbraid, utm_*, referrer, landing_page, first_seen.
 * Last non-direct touch wins; direct visits never overwrite an existing source.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attribution reader.
 */
final class OVC_Attribution {

	const COOKIE = 'ovc_attr';
	const KEYS   = array( 'gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'referrer', 'landing_page', 'first_seen' );

	/**
	 * Reads and sanitizes the attribution cookie (or a client supplied array).
	 *
	 * @param array|null $client Attribution sent by the browser (same shape), used when the cookie is absent.
	 * @return array<string,string>
	 */
	public static function current( $client = null ) {
		$data = array();
		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$raw     = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			$decoded = json_decode( (string) base64_decode( strtr( $raw, '-_', '+/' ), true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$data    = is_array( $decoded ) ? $decoded : array();
		}
		if ( empty( $data ) && is_array( $client ) ) {
			$data = $client;
		}
		return self::sanitize( $data );
	}

	/**
	 * Sanitizes an attribution array.
	 *
	 * @param array $data Data.
	 * @return array<string,string>
	 */
	public static function sanitize( array $data ) {
		$clean = array();
		foreach ( self::KEYS as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
				continue;
			}
			$value = (string) $data[ $key ];
			if ( in_array( $key, array( 'referrer', 'landing_page' ), true ) ) {
				$value = esc_url_raw( $value, array( 'http', 'https' ) );
			} else {
				$value = substr( sanitize_text_field( $value ), 0, 255 );
			}
			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}
		return $clean;
	}

	/**
	 * Device class from the user agent.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	public static function device( $ua ) {
		if ( '' === $ua ) {
			return 'unknown';
		}
		if ( preg_match( '/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $ua ) ) {
			return 'tablet';
		}
		return preg_match( '/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua ) ? 'mobile' : 'desktop';
	}
}
