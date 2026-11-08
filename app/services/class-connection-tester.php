<?php
/**
 * OptiVio — "Testează conexiunea" for every key / integration in Settings.
 *
 * Each test makes the cheapest call that proves the credential works and returns a safe
 * message (never the secret). Tests run only on an explicit Super Admin click.
 * The OpenRouter test reads the key status (/api/v1/key): it does NOT call a model.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Ai\Ai_Client;
use OptiVio\Google\Google_Client;
use OptiVio\Google\Google_Http;
use OptiVio\Google\Google_Oauth;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Connection tester.
 */
final class Connection_Tester {

	/**
	 * Testable providers => label.
	 */
	public const PROVIDERS = array(
		'openrouter'   => 'OpenRouter',
		'serper'       => 'Serper.dev',
		'pagespeed'    => 'PageSpeed Insights',
		'google_oauth' => 'Google OAuth (client + conturi conectate)',
		'ads'          => 'Google Ads API (developer token)',
		'gdrive'       => 'Google Drive (backup)',
	);

	/**
	 * Runs a test.
	 *
	 * @param string $provider Provider key.
	 * @return array{ok:bool,message:string,ms:int}
	 */
	public static function test( string $provider ): array {
		$start = microtime( true );
		try {
			switch ( $provider ) {
				case 'openrouter':
					[ $ok, $message ] = self::openrouter();
					break;
				case 'serper':
					[ $ok, $message ] = self::serper();
					break;
				case 'pagespeed':
					[ $ok, $message ] = self::pagespeed();
					break;
				case 'google_oauth':
					[ $ok, $message ] = self::google_oauth();
					break;
				case 'ads':
					[ $ok, $message ] = self::ads();
					break;
				case 'gdrive':
					[ $ok, $message ] = Backup_Drive::test();
					break;
				default:
					[ $ok, $message ] = array( false, 'Integrare necunoscută.' );
			}
		} catch ( Throwable $e ) {
			$ok      = false;
			$message = mb_substr( $e->getMessage(), 0, 300 );
		}
		return array(
			'ok'      => $ok,
			'message' => $message,
			'ms'      => (int) round( ( microtime( true ) - $start ) * 1000 ),
		);
	}

	/**
	 * OpenRouter: key status (no model call, no cost).
	 *
	 * @return array{0:bool,1:string}
	 */
	private static function openrouter(): array {
		$key = Credentials_Repository::reveal_global( 'openrouter' );
		if ( null === $key ) {
			return array( false, 'Nu este salvată nicio cheie OpenRouter globală.' );
		}
		if ( Ai_Client::is_mock() ) {
			return array( true, 'Mod de test (ai_mock): cheia nu este verificată la OpenRouter.' );
		}
		[ $code, $body, $error ] = self::http( 'GET', 'https://openrouter.ai/api/v1/key', array( 'Authorization: Bearer ' . $key ) );
		if ( 200 !== $code ) {
			return array( false, 401 === $code ? 'Cheie invalidă sau revocată (HTTP 401).' : self::error( $code, $body, $error ) );
		}
		$data  = (array) ( $body['data'] ?? array() );
		$usage = isset( $data['usage'] ) ? number_format( (float) $data['usage'], 2 ) . ' USD consumat' : '';
		$limit = isset( $data['limit'] ) && null !== $data['limit'] ? ' din limita de ' . number_format( (float) $data['limit'], 2 ) . ' USD' : ' (fără limită pe cheie)';
		return array( true, trim( 'Cheie validă. ' . $usage . $limit ) );
	}

	/**
	 * Serper: one search with one result (uses one credit).
	 *
	 * @return array{0:bool,1:string}
	 */
	private static function serper(): array {
		$key = Credentials_Repository::reveal_global( 'serper' );
		if ( null === $key ) {
			return array( false, 'Nu este salvată nicio cheie Serper.' );
		}
		if ( Google_Http::is_mock() ) {
			return array( true, 'Mod de test (google_mock): cheia nu este verificată la Serper.' );
		}
		[ $code, $body, $error ] = self::http( 'POST', 'https://google.serper.dev/search', array( 'X-API-KEY: ' . $key, 'Content-Type: application/json' ), (string) json_encode( array( 'q' => 'test', 'num' => 1, 'gl' => 'ro' ) ) );
		if ( 200 === $code ) {
			return array( true, 'Cheie validă (testul a folosit 1 credit Serper).' );
		}
		return array( false, in_array( $code, array( 401, 403 ), true ) ? 'Cheie invalidă sau fără credite (HTTP ' . $code . ').' : self::error( $code, $body, $error ) );
	}

	/**
	 * PageSpeed: the key is validated by Google before the URL, so an invalid URL gives a fast answer.
	 *
	 * @return array{0:bool,1:string}
	 */
	private static function pagespeed(): array {
		$key = Credentials_Repository::reveal_global( 'pagespeed' );
		if ( null === $key ) {
			return array( true, 'Fără cheie: PageSpeed funcționează cu cota publică (limitată).' );
		}
		if ( Google_Http::is_mock() ) {
			return array( true, 'Mod de test (google_mock): cheia nu este verificată la Google.' );
		}
		$res = Google_Http::request(
			'GET',
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
			array(
				'url' => 'invalid',
				'key' => $key,
			)
		);
		$msg = (string) ( $res['body']['error']['message'] ?? $res['error'] );
		if ( preg_match( '/API key not valid|API_KEY_INVALID|has not been used|is disabled|PERMISSION_DENIED|blocked/i', $msg . ' ' . $res['raw'] ) ) {
			return array( false, 'Cheie respinsă de Google: ' . mb_substr( $msg, 0, 200 ) );
		}
		if ( 0 === $res['code'] ) {
			return array( false, 'Google nu a putut fi contactat: ' . $res['error'] );
		}
		return array( true, 'Cheie validă și PageSpeed Insights API activat.' );
	}

	/**
	 * Google OAuth: client id/secret (invalid_client vs invalid_grant) + every connected account.
	 *
	 * @return array{0:bool,1:string}
	 */
	private static function google_oauth(): array {
		if ( ! Google_Oauth::is_configured() ) {
			return array( false, 'Client ID sau Client Secret lipsesc.' );
		}
		if ( ! Google_Http::is_mock() ) {
			$res   = Google_Http::request(
				'POST',
				'https://oauth2.googleapis.com/token',
				array(),
				null,
				array(),
				array(
					'client_id'     => (string) Settings_Repository::get( 'integrations.google_client_id' ),
					'client_secret' => (string) Settings_Repository::get_secret( 'integrations.google_client_secret' ),
					'refresh_token' => 'optivio-connection-test',
					'grant_type'    => 'refresh_token',
				)
			);
			$error = (string) ( $res['body']['error'] ?? '' );
			if ( 'invalid_client' === $error || 'unauthorized_client' === $error ) {
				return array( false, 'Client ID sau Client Secret greșit (Google: ' . $error . ').' );
			}
			if ( 0 === $res['code'] ) {
				return array( false, 'Google nu a putut fi contactat: ' . $res['error'] );
			}
		}
		$parts = array( 'Client OAuth valid.' );
		$ok    = true;
		foreach ( Google_Client::accounts() as $account ) {
			try {
				( new Google_Client( (int) $account['id'] ) )->request( 'GET', 'https://oauth2.googleapis.com/tokeninfo' );
				$parts[] = $account['account_email'] . ': conectat';
			} catch ( Throwable $e ) {
				$ok      = false;
				$parts[] = $account['account_email'] . ': ' . mb_substr( $e->getMessage(), 0, 120 ) . ' — reconectați contul din Integrări Google';
			}
		}
		if ( 1 === count( $parts ) ) {
			$parts[] = 'Niciun cont Google conectat încă.';
		}
		return array( $ok, implode( ' · ', $parts ) );
	}

	/**
	 * Google Ads: developer token through the first connected account.
	 *
	 * @return array{0:bool,1:string}
	 */
	private static function ads(): array {
		if ( null === Settings_Repository::get_secret( 'integrations.ads_developer_token' ) ) {
			return array( false, 'Developer token-ul Google Ads nu este salvat.' );
		}
		$accounts = Google_Client::accounts();
		if ( empty( $accounts ) ) {
			return array( false, 'Conectați întâi un cont Google (Integrări Google) — testul folosește contul conectat.' );
		}
		$version = (string) Settings_Repository::get( 'integrations.ads_api_version', 'v21' );
		$res     = ( new Google_Client( (int) $accounts[0]['id'] ) )->request( 'GET', 'https://googleads.googleapis.com/' . $version . '/customers:listAccessibleCustomers', array(), null, true );
		$count   = count( (array) ( $res['body']['resourceNames'] ?? array() ) );
		return array( true, sprintf( 'Developer token acceptat (%s). Conturi Ads accesibile prin %s: %d.', $version, $accounts[0]['account_email'], $count ) );
	}

	/**
	 * Minimal HTTPS request for non-Google providers.
	 *
	 * @param string      $method  Method.
	 * @param string      $url     URL (https only).
	 * @param array       $headers Header lines.
	 * @param string|null $body    Body.
	 * @return array{0:int,1:array,2:string}
	 */
	private static function http( string $method, string $url, array $headers, ?string $body = null ): array {
		$ch = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_HTTPHEADER     => array_merge( array( 'Accept: application/json', 'User-Agent: OptiVio/' . OPTIVIO_VERSION ), $headers ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_CONNECTTIMEOUT => 8,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			)
		);
		if ( null !== $body ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
		$raw   = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$error = false === $raw ? curl_error( $ch ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		$json  = json_decode( (string) $raw, true );
		return array( $code, is_array( $json ) ? $json : array(), $error );
	}

	/**
	 * Generic error message.
	 *
	 * @param int    $code  HTTP code.
	 * @param array  $body  Body.
	 * @param string $error Transport error.
	 * @return string
	 */
	private static function error( int $code, array $body, string $error ): string {
		if ( 0 === $code ) {
			return 'Serviciul nu a putut fi contactat: ' . $error;
		}
		$msg = $body['error']['message'] ?? $body['message'] ?? '';
		return mb_substr( 'HTTP ' . $code . ( '' !== (string) $msg ? ': ' . (string) $msg : '' ), 0, 300 );
	}
}
