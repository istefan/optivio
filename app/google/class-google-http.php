<?php
/**
 * OptiVio — HTTP transport for Google APIs (curl). In development with google_mock = true,
 * requests are answered by Google_Mock fixtures (never in production).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use OptiVio\Core\Config;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Google HTTP transport.
 */
final class Google_Http {

	/**
	 * Allowed Google hosts (no other host is ever contacted with a token).
	 */
	private const HOSTS = '/^(accounts\.google\.com|oauth2\.googleapis\.com|[a-z0-9\-]+\.googleapis\.com)$/';

	/**
	 * Whether the mock transport is active.
	 *
	 * @return bool
	 */
	public static function is_mock(): bool {
		return Config::is_dev() && true === Config::get( 'google_mock', false );
	}

	/**
	 * Performs a request.
	 *
	 * @param string      $method  GET|POST.
	 * @param string      $url     URL (Google host only).
	 * @param array       $query   Query args (arrays become repeated params).
	 * @param array|null  $json    JSON body.
	 * @param array       $headers Extra headers (name => value).
	 * @param array|null  $form    Form body (x-www-form-urlencoded).
	 * @return array{code:int,body:?array,raw:string,error:string}
	 */
	public static function request( string $method, string $url, array $query = array(), ?array $json = null, array $headers = array(), ?array $form = null ): array {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( 0 !== strpos( $url, 'https://' ) || ! preg_match( self::HOSTS, $host ) ) {
			return self::result( 0, '', 'host_not_allowed' );
		}
		if ( ! empty( $query ) ) {
			$url .= ( str_contains( $url, '?' ) ? '&' : '?' ) . self::build_query( $query );
		}

		if ( self::is_mock() ) {
			[ $code, $body ] = Google_Mock::respond( $method, $url, $json ?? $form ?? array() );
			return self::result( $code, (string) json_encode( $body ), '' );
		}

		$lines = array( 'Accept: application/json', 'User-Agent: OptiVio/' . OPTIVIO_VERSION );
		foreach ( $headers as $name => $value ) {
			$lines[] = $name . ': ' . $value;
		}
		$payload = null;
		if ( null !== $json ) {
			$payload = (string) json_encode( $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$lines[] = 'Content-Type: application/json';
		} elseif ( null !== $form ) {
			$payload = http_build_query( $form, '', '&', PHP_QUERY_RFC3986 );
			$lines[] = 'Content-Type: application/x-www-form-urlencoded';
		}

		$ch = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_HTTPHEADER     => $lines,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			)
		);
		if ( null !== $payload ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
		$raw   = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$error = false === $raw ? curl_error( $ch ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		return self::result( $code, (string) $raw, $error );
	}

	/**
	 * Builds a query string with repeated keys for list values (Google style).
	 *
	 * @param array $query Query.
	 * @return string
	 */
	private static function build_query( array $query ): string {
		$parts = array();
		foreach ( $query as $key => $value ) {
			foreach ( (array) $value as $item ) {
				$parts[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $item );
			}
		}
		return implode( '&', $parts );
	}

	/**
	 * Result tuple.
	 *
	 * @param int    $code  HTTP code.
	 * @param string $raw   Raw body.
	 * @param string $error Transport error.
	 * @return array
	 */
	private static function result( int $code, string $raw, string $error ): array {
		$body = json_decode( $raw, true );
		return array(
			'code'  => $code,
			'body'  => is_array( $body ) ? $body : null,
			'raw'   => mb_substr( $raw, 0, 2000 ),
			'error' => $error,
		);
	}
}
