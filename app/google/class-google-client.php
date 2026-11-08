<?php
/**
 * OptiVio — Authenticated Google API client bound to one connected Google account.
 *
 * Tokens live encrypted in api_credentials (provider google_oauth):
 * secret_cipher = AES-256-GCM( {"refresh_token","access_token","expires_at"} ).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use OptiVio\Core\Crypto;
use OptiVio\Core\Database;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Google client.
 */
final class Google_Client {

	/**
	 * Credential id.
	 *
	 * @var int
	 */
	private int $credential_id;

	/**
	 * Decrypted token set.
	 *
	 * @var array
	 */
	private array $tokens;

	/**
	 * Constructor.
	 *
	 * @param int $credential_id api_credentials id (provider google_oauth).
	 * @throws RuntimeException When the credential is missing or unreadable.
	 */
	public function __construct( int $credential_id ) {
		$row = Database::instance()->get_row(
			"SELECT secret_cipher, status FROM api_credentials WHERE id = ? AND provider = 'google_oauth'",
			array( $credential_id )
		);
		if ( null === $row || 'active' !== $row['status'] ) {
			throw new RuntimeException( 'Contul Google nu este conectat sau a fost revocat.' );
		}
		$plain = Crypto::decrypt( (string) $row['secret_cipher'], self::context() );
		$data  = null === $plain ? null : json_decode( $plain, true );
		if ( ! is_array( $data ) || empty( $data['refresh_token'] ) ) {
			throw new RuntimeException( 'Tokenul Google nu poate fi decriptat.' );
		}
		$this->credential_id = $credential_id;
		$this->tokens        = $data;
	}

	/**
	 * AAD context of Google token sets.
	 *
	 * @return string
	 */
	public static function context(): string {
		return Credentials_Repository::context( 'global', 'google_oauth', null, null );
	}

	/**
	 * Saves (or updates by email) a connected Google account.
	 *
	 * @param string $email   Account email.
	 * @param array  $tokens  Token response.
	 * @param int    $user_id Acting user.
	 * @return int Credential id.
	 */
	public static function store_account( string $email, array $tokens, int $user_id ): int {
		$db      = Database::instance();
		$payload = Crypto::encrypt(
			(string) json_encode(
				array(
					'refresh_token' => (string) $tokens['refresh_token'],
					'access_token'  => (string) $tokens['access_token'],
					'expires_at'    => time() + (int) ( $tokens['expires_in'] ?? 3600 ) - 60,
				)
			),
			self::context()
		);
		$meta    = json_encode(
			array(
				'scopes'       => explode( ' ', (string) ( $tokens['scope'] ?? '' ) ),
				'connected_at' => gmdate( 'c' ),
			)
		);
		$id      = (int) $db->get_var( "SELECT id FROM api_credentials WHERE scope = 'global' AND provider = 'google_oauth' AND account_email = ?", array( $email ) );
		if ( $id > 0 ) {
			$db->update(
				'api_credentials',
				array(
					'secret_cipher' => $payload,
					'meta'          => $meta,
					'status'        => 'active',
				),
				array( 'id' => $id )
			);
			return $id;
		}
		return $db->insert(
			'api_credentials',
			array(
				'scope'         => 'global',
				'provider'      => 'google_oauth',
				'label'         => 'Google · ' . $email,
				'account_email' => $email,
				'secret_cipher' => $payload,
				'secret_hint'   => null,
				'meta'          => $meta,
				'status'        => 'active',
				'created_by'    => $user_id,
			)
		);
	}

	/**
	 * Connected Google accounts.
	 *
	 * @return array<int,array>
	 */
	public static function accounts(): array {
		return Database::instance()->get_results(
			"SELECT id, account_email, status, meta, last_used_at, created_at, updated_at FROM api_credentials WHERE scope = 'global' AND provider = 'google_oauth' ORDER BY account_email"
		);
	}

	/**
	 * Performs an authenticated request (refreshing the access token when needed).
	 *
	 * @param string     $method  Method.
	 * @param string     $url     URL.
	 * @param array      $query   Query.
	 * @param array|null $json    JSON body.
	 * @param bool       $ads     Adds Google Ads headers.
	 * @return array{code:int,body:?array,raw:string,error:string}
	 * @throws RuntimeException On auth or API error (message safe to store in last_error).
	 */
	public function request( string $method, string $url, array $query = array(), ?array $json = null, bool $ads = false ): array {
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$headers = array( 'Authorization' => 'Bearer ' . $this->access_token( 1 === $attempt ) );
			if ( $ads ) {
				$headers = array_merge( $headers, $this->ads_headers() );
			}
			$response = Google_Http::request( $method, $url, $query, $json, $headers );
			if ( 401 !== $response['code'] ) {
				break;
			}
		}
		Database::instance()->query( 'UPDATE api_credentials SET last_used_at = UTC_TIMESTAMP() WHERE id = ?', array( $this->credential_id ) );

		if ( $response['code'] < 200 || $response['code'] >= 300 ) {
			$message = (string) ( $response['body']['error']['message'] ?? $response['body'][0]['error']['message'] ?? $response['error'] ?: 'HTTP ' . $response['code'] );
			throw new RuntimeException( mb_substr( 'Google API ' . $response['code'] . ': ' . $message, 0, 480 ) );
		}
		return $response;
	}

	/**
	 * Returns a valid access token.
	 *
	 * @param bool $force Force refresh.
	 * @return string
	 * @throws RuntimeException When refresh fails.
	 */
	private function access_token( bool $force ): string {
		if ( ! $force && ! empty( $this->tokens['access_token'] ) && (int) ( $this->tokens['expires_at'] ?? 0 ) > time() ) {
			return (string) $this->tokens['access_token'];
		}
		$result = Google_Oauth::refresh( (string) $this->tokens['refresh_token'] );
		if ( ! $result['ok'] ) {
			if ( ! empty( $result['invalid_grant'] ) ) {
				Database::instance()->update( 'api_credentials', array( 'status' => 'invalid' ), array( 'id' => $this->credential_id ) );
			}
			throw new RuntimeException( 'Reîmprospătarea tokenului Google a eșuat: ' . $result['error'] );
		}
		$this->tokens['access_token'] = $result['access_token'];
		$this->tokens['expires_at']   = time() + (int) $result['expires_in'] - 60;
		Database::instance()->update(
			'api_credentials',
			array( 'secret_cipher' => Crypto::encrypt( (string) json_encode( $this->tokens ), self::context() ) ),
			array( 'id' => $this->credential_id )
		);
		return (string) $result['access_token'];
	}

	/**
	 * Google Ads headers.
	 *
	 * @return array<string,string>
	 * @throws RuntimeException When the developer token is missing.
	 */
	private function ads_headers(): array {
		$token = Settings_Repository::get_secret( 'integrations.ads_developer_token' );
		if ( null === $token || '' === $token ) {
			throw new RuntimeException( 'Lipsește developer token-ul Google Ads (Setări → Integrări).' );
		}
		$headers = array( 'developer-token' => $token );
		$login   = preg_replace( '/\D/', '', (string) Settings_Repository::get( 'integrations.ads_login_customer_id', '' ) );
		if ( '' !== $login ) {
			$headers['login-customer-id'] = $login;
		}
		return $headers;
	}

	/**
	 * Revokes the account at Google and deletes it locally.
	 *
	 * @param int $credential_id Credential.
	 * @return void
	 */
	public static function disconnect( int $credential_id ): void {
		$db  = Database::instance();
		$row = $db->get_row( "SELECT secret_cipher FROM api_credentials WHERE id = ? AND provider = 'google_oauth'", array( $credential_id ) );
		if ( null !== $row ) {
			$plain = Crypto::decrypt( (string) $row['secret_cipher'], self::context() );
			$data  = null === $plain ? null : json_decode( $plain, true );
			if ( is_array( $data ) && ! empty( $data['refresh_token'] ) ) {
				Google_Oauth::revoke( (string) $data['refresh_token'] );
			}
		}
		$db->query( "UPDATE site_integrations SET credential_id = NULL, status = 'paused', last_error = 'Contul Google a fost deconectat.' WHERE credential_id = ?", array( $credential_id ) );
		$db->delete( 'api_credentials', array( 'id' => $credential_id ) );
	}
}
