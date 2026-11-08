<?php
/**
 * OptiVio — Google OAuth 2.0 (authorization code + PKCE S256, offline access).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use OptiVio\Repositories\Settings_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * OAuth helper.
 */
final class Google_Oauth {

	/**
	 * Read-only scopes except Ads (Google offers no read-only Ads scope) and GBP (business.manage).
	 */
	public const SCOPES = array(
		'openid',
		'email',
		'https://www.googleapis.com/auth/webmasters.readonly',
		'https://www.googleapis.com/auth/analytics.readonly',
		'https://www.googleapis.com/auth/tagmanager.readonly',
		'https://www.googleapis.com/auth/adwords',
		'https://www.googleapis.com/auth/adsense.readonly',
		'https://www.googleapis.com/auth/business.manage',
	);

	private const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
	private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

	/**
	 * Whether client id + secret are configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== (string) Settings_Repository::get( 'integrations.google_client_id', '' ) && null !== Settings_Repository::get_secret( 'integrations.google_client_secret' );
	}

	/**
	 * Redirect URI registered in Google Cloud Console.
	 *
	 * @return string
	 */
	public static function redirect_uri(): string {
		return ov_url( '/oauth/google/callback' );
	}

	/**
	 * Authorization URL.
	 *
	 * @param string $state     Anti-CSRF state.
	 * @param string $challenge PKCE S256 challenge.
	 * @param array  $scopes    Scopes (default: the analytics/ads set; backups use drive.file only).
	 * @return string
	 */
	public static function authorization_url( string $state, string $challenge, array $scopes = self::SCOPES ): string {
		return self::AUTH_URL . '?' . http_build_query(
			array(
				'client_id'              => (string) Settings_Repository::get( 'integrations.google_client_id' ),
				'redirect_uri'           => self::redirect_uri(),
				'response_type'          => 'code',
				'scope'                  => implode( ' ', $scopes ),
				'access_type'            => 'offline',
				'prompt'                 => 'consent',
				'include_granted_scopes' => self::SCOPES === $scopes ? 'true' : 'false',
				'state'                  => $state,
				'code_challenge'         => $challenge,
				'code_challenge_method'  => 'S256',
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * PKCE pair.
	 *
	 * @return array{verifier:string,challenge:string}
	 */
	public static function pkce(): array {
		$verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return array(
			'verifier'  => $verifier,
			'challenge' => rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
	}

	/**
	 * Exchanges the authorization code.
	 *
	 * @param string $code     Code.
	 * @param string $verifier PKCE verifier.
	 * @return array{ok:bool,tokens?:array,email?:string,error?:string}
	 */
	public static function exchange( string $code, string $verifier ): array {
		$response = Google_Http::request(
			'POST',
			self::TOKEN_URL,
			array(),
			null,
			array(),
			array(
				'code'          => $code,
				'client_id'     => (string) Settings_Repository::get( 'integrations.google_client_id' ),
				'client_secret' => (string) Settings_Repository::get_secret( 'integrations.google_client_secret' ),
				'redirect_uri'  => self::redirect_uri(),
				'grant_type'    => 'authorization_code',
				'code_verifier' => $verifier,
			)
		);
		$body = $response['body'];
		if ( 200 !== $response['code'] || empty( $body['access_token'] ) || empty( $body['refresh_token'] ) ) {
			return array(
				'ok'    => false,
				'error' => (string) ( $body['error_description'] ?? $body['error'] ?? 'Schimbul codului a eșuat.' ),
			);
		}
		return array(
			'ok'     => true,
			'tokens' => $body,
			'email'  => self::email_from_id_token( (string) ( $body['id_token'] ?? '' ) ),
		);
	}

	/**
	 * Refreshes an access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array{ok:bool,access_token?:string,expires_in?:int,error?:string,invalid_grant?:bool}
	 */
	public static function refresh( string $refresh_token ): array {
		$response = Google_Http::request(
			'POST',
			self::TOKEN_URL,
			array(),
			null,
			array(),
			array(
				'client_id'     => (string) Settings_Repository::get( 'integrations.google_client_id' ),
				'client_secret' => (string) Settings_Repository::get_secret( 'integrations.google_client_secret' ),
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			)
		);
		$body = $response['body'];
		if ( 200 !== $response['code'] || empty( $body['access_token'] ) ) {
			return array(
				'ok'            => false,
				'error'         => (string) ( $body['error_description'] ?? $body['error'] ?? 'refresh_failed' ),
				'invalid_grant' => 'invalid_grant' === ( $body['error'] ?? '' ),
			);
		}
		return array(
			'ok'           => true,
			'access_token' => (string) $body['access_token'],
			'expires_in'   => (int) ( $body['expires_in'] ?? 3600 ),
		);
	}

	/**
	 * Revokes a token at Google.
	 *
	 * @param string $token Refresh or access token.
	 * @return void
	 */
	public static function revoke( string $token ): void {
		Google_Http::request( 'POST', self::REVOKE_URL, array(), null, array(), array( 'token' => $token ) );
	}

	/**
	 * Email claim of the id_token (received directly from Google's token endpoint over TLS).
	 *
	 * @param string $id_token JWT.
	 * @return string
	 */
	private static function email_from_id_token( string $id_token ): string {
		$parts = explode( '.', $id_token );
		if ( count( $parts ) < 2 ) {
			return '';
		}
		$payload = json_decode( (string) base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return is_array( $payload ) ? sanitize_email( (string) ( $payload['email'] ?? '' ) ) : '';
	}
}
