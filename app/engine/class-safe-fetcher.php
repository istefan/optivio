<?php
/**
 * OptiVio — SSRF-safe HTTP GET for scanning third-party pages (competitors).
 *
 * - http/https only, standard ports, no credentials in URL;
 * - DNS resolved once, every IP must be public (no private / loopback / link-local / reserved),
 *   and the connection is pinned to the validated IP (CURLOPT_RESOLVE) against DNS rebinding;
 * - redirects followed manually (max 3), each hop re-validated;
 * - HTML only, 2 MB max, 15 s timeout.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Safe fetcher.
 */
final class Safe_Fetcher {

	public const MAX_BYTES = 2097152;

	/**
	 * Fetches a URL.
	 *
	 * @param string $url URL.
	 * @return array{ok:bool,status:int,url:string,html:string,error:string}
	 */
	public static function get( string $url ): array {
		for ( $hop = 0; $hop <= 3; $hop++ ) {
			$target = self::validate( $url );
			if ( null === $target ) {
				return self::fail( $url, 'URL nepermis (doar adrese publice http/https).' );
			}

			$body    = '';
			$too_big = false;
			$ch      = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
			curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
				$ch,
				array(
					CURLOPT_RESOLVE        => array( $target['host'] . ':' . $target['port'] . ':' . $target['ip'] ),
					CURLOPT_FOLLOWLOCATION => false,
					CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
					CURLOPT_TIMEOUT        => 15,
					CURLOPT_CONNECTTIMEOUT => 8,
					CURLOPT_HEADER         => false,
					CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OptiVioBot/1.0; +https://optivio.ahoi.ro)',
					CURLOPT_HTTPHEADER     => array( 'Accept: text/html,application/xhtml+xml', 'Accept-Language: ro-RO,ro;q=0.9,en;q=0.5' ),
					CURLOPT_ENCODING       => '',
					CURLOPT_WRITEFUNCTION  => static function ( $handle, string $chunk ) use ( &$body, &$too_big ): int {
						unset( $handle );
						$body .= $chunk;
						if ( strlen( $body ) > self::MAX_BYTES ) {
							$too_big = true;
							return 0;
						}
						return strlen( $chunk );
					},
				)
			);
			curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
			$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
			$type     = (string) curl_getinfo( $ch, CURLINFO_CONTENT_TYPE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
			$location = (string) curl_getinfo( $ch, CURLINFO_REDIRECT_URL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
			$error    = $too_big ? 'Pagina depășește 2 MB.' : (string) curl_error( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error

			if ( $status >= 300 && $status < 400 && '' !== $location ) {
				$url = $location;
				continue;
			}
			if ( '' !== $error && ! $too_big ) {
				return self::fail( $url, $error, $status );
			}
			if ( '' !== $type && ! preg_match( '#text/html|application/xhtml#i', $type ) ) {
				return self::fail( $url, 'Conținutul nu este HTML.', $status );
			}
			return array(
				'ok'     => $status >= 200 && $status < 300,
				'status' => $status,
				'url'    => $url,
				'html'   => $body,
				'error'  => $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status,
			);
		}
		return self::fail( $url, 'Prea multe redirecționări.' );
	}

	/**
	 * Validates a URL and resolves it to a public IP.
	 *
	 * @param string $url URL.
	 * @return array{host:string,port:int,ip:string}|null
	 */
	public static function validate( string $url ): ?array {
		$p = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $p ) || empty( $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) ) {
			return null;
		}
		$scheme = strtolower( (string) ( $p['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}
		$port = (int) ( $p['port'] ?? ( 'https' === $scheme ? 443 : 80 ) );
		if ( ! in_array( $port, array( 80, 443, 8080, 8443 ), true ) ) {
			return null;
		}
		$host = strtolower( trim( (string) $p['host'], '[]' ) );
		$ips  = false !== filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );
		if ( empty( $ips ) ) {
			return null;
		}
		foreach ( $ips as $ip ) {
			if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return null;
			}
		}
		return array(
			'host' => $host,
			'port' => $port,
			'ip'   => (string) $ips[0],
		);
	}

	/**
	 * Failure tuple.
	 *
	 * @param string $url    URL.
	 * @param string $error  Error.
	 * @param int    $status Status.
	 * @return array
	 */
	private static function fail( string $url, string $error, int $status = 0 ): array {
		return array(
			'ok'     => false,
			'status' => $status,
			'url'    => $url,
			'html'   => '',
			'error'  => $error,
		);
	}
}
