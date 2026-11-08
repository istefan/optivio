<?php
/**
 * OptiVio — Google Drive client for backups (scope drive.file only).
 *
 * Uses the Google OAuth client already configured in Settings → Integrations and the SAME
 * redirect URI (/oauth/google/callback): nothing new to register in Google Cloud except
 * enabling the Drive API. With `drive.file` the app sees only the folder and files it created.
 * Tokens are stored encrypted (settings `backup.drive_tokens`). Uploads are resumable (8 MB chunks).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Google\Google_Http;
use OptiVio\Google\Google_Oauth;
use OptiVio\Repositories\Settings_Repository;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Google Drive for backups.
 */
final class Backup_Drive {

	public const SCOPES = array( 'openid', 'email', 'https://www.googleapis.com/auth/drive.file' );

	private const API    = 'https://www.googleapis.com/drive/v3/files';
	private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';
	private const CHUNK  = 8388608;

	/**
	 * Stored tokens (decrypted) or null.
	 *
	 * @return array|null
	 */
	private static function tokens(): ?array {
		$raw  = Settings_Repository::get_secret( 'backup.drive_tokens' );
		$data = null !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $data ) && ! empty( $data['refresh_token'] ) ? $data : null;
	}

	/**
	 * Whether a Drive account is connected.
	 *
	 * @return bool
	 */
	public static function is_connected(): bool {
		return null !== self::tokens();
	}

	/**
	 * Connected account email.
	 *
	 * @return string
	 */
	public static function email(): string {
		return (string) Settings_Repository::get( 'backup.drive_email', '' );
	}

	/**
	 * Saves the tokens after the OAuth callback.
	 *
	 * @param array  $tokens  Token response.
	 * @param string $email   Account email.
	 * @param int    $user_id Acting user.
	 * @return void
	 */
	public static function store( array $tokens, string $email, int $user_id ): void {
		Settings_Repository::set_secret(
			'backup.drive_tokens',
			(string) json_encode(
				array(
					'refresh_token' => (string) $tokens['refresh_token'],
					'access_token'  => (string) $tokens['access_token'],
					'expires_at'    => time() + (int) ( $tokens['expires_in'] ?? 3600 ) - 60,
				)
			),
			$user_id
		);
		Settings_Repository::set( 'backup.drive_email', $email, $user_id );
		Settings_Repository::set( 'backup.drive_folder_id', '', $user_id );
	}

	/**
	 * Disconnects (revokes the token at Google, forgets it locally).
	 *
	 * @return void
	 */
	public static function disconnect(): void {
		$tokens = self::tokens();
		if ( null !== $tokens ) {
			Google_Oauth::revoke( (string) $tokens['refresh_token'] );
		}
		Settings_Repository::delete( 'backup.drive_tokens' );
		Settings_Repository::delete( 'backup.drive_email' );
		Settings_Repository::delete( 'backup.drive_folder_id' );
	}

	/**
	 * Valid access token.
	 *
	 * @return string
	 * @throws RuntimeException When not connected or the refresh fails.
	 */
	private static function access_token(): string {
		$tokens = self::tokens();
		if ( null === $tokens ) {
			throw new RuntimeException( 'Google Drive nu este conectat.' );
		}
		if ( ! empty( $tokens['access_token'] ) && (int) $tokens['expires_at'] > time() ) {
			return (string) $tokens['access_token'];
		}
		$result = Google_Oauth::refresh( (string) $tokens['refresh_token'] );
		if ( ! $result['ok'] ) {
			throw new RuntimeException( 'Autorizarea Google Drive a expirat (' . $result['error'] . '). Reconectați Google Drive.' );
		}
		$tokens['access_token'] = $result['access_token'];
		$tokens['expires_at']   = time() + (int) $result['expires_in'] - 60;
		Settings_Repository::set_secret( 'backup.drive_tokens', (string) json_encode( $tokens ) );
		return (string) $tokens['access_token'];
	}

	/**
	 * Drive API call (JSON).
	 *
	 * @param string     $method Method.
	 * @param string     $url    URL.
	 * @param array      $query  Query.
	 * @param array|null $json   Body.
	 * @return array
	 * @throws RuntimeException On API errors.
	 */
	private static function api( string $method, string $url, array $query = array(), ?array $json = null ): array {
		$res = Google_Http::request( $method, $url, $query, $json, array( 'Authorization' => 'Bearer ' . self::access_token() ) );
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			$msg = (string) ( $res['body']['error']['message'] ?? $res['error'] ?: 'HTTP ' . $res['code'] );
			throw new RuntimeException( mb_substr( 'Google Drive ' . $res['code'] . ': ' . $msg, 0, 400 ) );
		}
		return (array) $res['body'];
	}

	/**
	 * Destination folder name.
	 *
	 * @return string
	 */
	public static function folder_name(): string {
		$name = trim( (string) Settings_Repository::get( 'backup.folder_name', 'optivio-backups' ) );
		return '' !== $name ? $name : 'optivio-backups';
	}

	/**
	 * Destination folder id (verified; recreated when missing or trashed).
	 *
	 * @return string
	 */
	public static function folder_id(): string {
		$id = (string) Settings_Repository::get( 'backup.drive_folder_id', '' );
		if ( '' !== $id ) {
			try {
				$f = self::api( 'GET', self::API . '/' . rawurlencode( $id ), array( 'fields' => 'id,trashed' ) );
				if ( empty( $f['trashed'] ) ) {
					return $id;
				}
			} catch ( RuntimeException $e ) {
				unset( $e );
			}
		}
		$name  = self::folder_name();
		$found = self::api(
			'GET',
			self::API,
			array(
				'q'      => "name = '" . str_replace( "'", "\\'", $name ) . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
				'fields' => 'files(id,name)',
			)
		);
		$id = (string) ( $found['files'][0]['id'] ?? '' );
		if ( '' === $id ) {
			$created = self::api(
				'POST',
				self::API,
				array( 'fields' => 'id' ),
				array(
					'name'     => $name,
					'mimeType' => 'application/vnd.google-apps.folder',
				)
			);
			$id = (string) $created['id'];
		}
		Settings_Repository::set( 'backup.drive_folder_id', $id );
		return $id;
	}

	/**
	 * Uploads a file (resumable, chunked).
	 *
	 * @param string $path Local path.
	 * @param string $name Name in Drive.
	 * @param string $mime MIME type.
	 * @return string Drive file id.
	 * @throws RuntimeException On errors.
	 */
	public static function upload( string $path, string $name, string $mime ): string {
		$folder = self::folder_id();
		$size   = (int) filesize( $path );
		if ( Google_Http::is_mock() ) {
			return 'mock-' . substr( sha1( $name . $size ), 0, 20 );
		}
		$token = self::access_token();

		// 1. Start the resumable session.
		[ $code, $headers, $body ] = self::curl(
			'POST',
			self::UPLOAD . '?uploadType=resumable&fields=id',
			array( 'Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Type: ' . $mime, 'X-Upload-Content-Length: ' . $size ),
			(string) json_encode(
				array(
					'name'    => $name,
					'parents' => array( $folder ),
				)
			)
		);
		$session = (string) ( $headers['location'] ?? '' );
		if ( 200 !== $code || 0 !== strpos( $session, 'https://www.googleapis.com/' ) ) {
			throw new RuntimeException( 'Google Drive: sesiunea de upload nu a putut fi creată (HTTP ' . $code . ') ' . mb_substr( $body, 0, 200 ) );
		}

		// 2. Send the chunks.
		$fh     = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$offset = 0;
		$result = '';
		while ( $offset < $size || 0 === $size ) {
			$chunk = 0 === $size ? '' : (string) fread( $fh, self::CHUNK ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$end   = $offset + strlen( $chunk ) - 1;
			$range = 0 === $size ? 'bytes */0' : 'bytes ' . $offset . '-' . $end . '/' . $size;
			[ $code, , $body ] = self::curl( 'PUT', $session, array( 'Content-Range: ' . $range, 'Content-Type: ' . $mime ), $chunk );
			if ( 200 === $code || 201 === $code ) {
				$result = $body;
				break;
			}
			if ( 308 !== $code ) {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				throw new RuntimeException( 'Google Drive: upload întrerupt (HTTP ' . $code . ') ' . mb_substr( $body, 0, 200 ) );
			}
			$offset = $end + 1;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$id = (string) ( json_decode( $result, true )['id'] ?? '' );
		if ( '' === $id ) {
			throw new RuntimeException( 'Google Drive: răspuns fără ID de fișier.' );
		}
		return $id;
	}

	/**
	 * Deletes a file (missing files are ignored).
	 *
	 * @param string $id Drive file id.
	 * @return void
	 */
	public static function delete( string $id ): void {
		if ( '' === $id || Google_Http::is_mock() ) {
			return;
		}
		try {
			self::api( 'DELETE', self::API . '/' . rawurlencode( $id ) );
		} catch ( RuntimeException $e ) {
			if ( false === strpos( $e->getMessage(), '404' ) ) {
				throw $e;
			}
		}
	}

	/**
	 * Link to a file / the folder.
	 *
	 * @param string $id File id ('' = folder).
	 * @return string
	 */
	public static function link( string $id = '' ): string {
		if ( '' === $id ) {
			$folder = (string) Settings_Repository::get( 'backup.drive_folder_id', '' );
			return '' !== $folder ? 'https://drive.google.com/drive/folders/' . rawurlencode( $folder ) : 'https://drive.google.com/';
		}
		return 'https://drive.google.com/file/d/' . rawurlencode( $id ) . '/view';
	}

	/**
	 * Connection test (account + folder access).
	 *
	 * @return array{0:bool,1:string}
	 */
	public static function test(): array {
		if ( ! self::is_connected() ) {
			return array( false, 'Google Drive nu este conectat (Administrare → Backup).' );
		}
		$id = self::folder_id();
		return array( true, 'Conectat ca ' . self::email() . '; folderul „' . self::folder_name() . '” este accesibil (' . $id . ').' );
	}

	/**
	 * Raw HTTPS request returning headers (upload sessions).
	 *
	 * @param string $method  Method.
	 * @param string $url     URL.
	 * @param array  $headers Header lines.
	 * @param string $body    Body.
	 * @return array{0:int,1:array<string,string>,2:string}
	 */
	private static function curl( string $method, string $url, array $headers, string $body ): array {
		$out = array();
		$ch  = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POSTFIELDS     => $body,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 300,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
				CURLOPT_HEADERFUNCTION => static function ( $handle, string $line ) use ( &$out ): int {
					unset( $handle );
					$parts = explode( ':', $line, 2 );
					if ( 2 === count( $parts ) ) {
						$out[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
					}
					return strlen( $line );
				},
			)
		);
		$raw  = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		return array( $code, $out, false === $raw ? (string) curl_error( $ch ) : (string) $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
	}
}
