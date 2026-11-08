<?php
/**
 * OptiVio — Signed remote operations OptiVio → WordPress (via OptiVio Connector), with
 * snapshot ids for 1-click rollback. Only ever triggered by an explicit, authorized user action.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;
use OptiVio\Core\Logger;
use OptiVio\Repositories\Sites_Repository;
use OptiVio\Security\Connector_Signature;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Remote operations service.
 */
final class Remote_Operations {

	/**
	 * Operation labels.
	 */
	public const LABELS = array(
		'ping'            => 'Test conexiune',
		'update_seo_meta' => 'Actualizare SEO (Yoast)',
		'update_post'     => 'Actualizare conținut',
		'create_post'     => 'Publicare conținut nou',
		'update_term'     => 'Actualizare categorie',
		'upload_media'    => 'Încărcare imagine',
		'update_links'    => 'Actualizare linkuri',
		'rollback'        => 'Rollback',
		'resync'          => 'Resincronizare',
	);

	/**
	 * Operations that produce a snapshot (and can be rolled back).
	 */
	public const REVERSIBLE = array( 'update_seo_meta', 'update_post', 'create_post', 'update_term', 'upload_media' );

	/**
	 * Executes an operation synchronously.
	 *
	 * @param int   $site_id   Site (authorized by the caller with "manage").
	 * @param string $operation Operation.
	 * @param array $params    Params.
	 * @param int   $user_id   Acting user.
	 * @param array $context   content_id, object_type, taxonomy, action_card_id.
	 * @return array{ok:bool,operation_id:int,error?:string,response?:array}
	 */
	public static function execute( int $site_id, string $operation, array $params, int $user_id, array $context = array() ): array {
		$db     = Database::instance();
		$op_id  = $db->insert(
			'remote_operations',
			array(
				'site_id'         => $site_id,
				'action_card_id'  => $context['action_card_id'] ?? null,
				'operation'       => $operation,
				'content_id'      => $context['content_id'] ?? null,
				'object_type'     => $context['object_type'] ?? 'post',
				'taxonomy'        => $context['taxonomy'] ?? null,
				'request_payload' => json_encode( self::redact( $params ), JSON_UNESCAPED_UNICODE ),
				'status'          => 'sent',
				'attempts'        => 1,
				'requested_by'    => $user_id,
			)
		);

		$response = self::send( $site_id, $operation, $params );
		$body     = $response['body'];
		$ok       = 200 === $response['code'] && is_array( $body ) && ! empty( $body['ok'] );

		$db->update(
			'remote_operations',
			array(
				'status'             => $ok ? 'applied' : 'failed',
				'remote_snapshot_id' => $ok && ! empty( $body['snapshot_id'] ) ? (string) $body['snapshot_id'] : null,
				'remote_object_id'   => $ok && ! empty( $body['object_id'] ) ? (int) $body['object_id'] : null,
				'response'           => json_encode( self::redact( is_array( $body ) ? array_diff_key( $body, array( 'object' => 1 ) ) : array( 'raw' => mb_substr( (string) $response['raw'], 0, 500 ) ) ), JSON_UNESCAPED_UNICODE ),
				'error'              => $ok ? null : mb_substr( (string) ( $body['message'] ?? $response['error'] ?: ( 'HTTP ' . $response['code'] ) ), 0, 500 ),
				'completed_at'       => Database::now(),
			),
			array( 'id' => $op_id )
		);

		// Keep the mirror in sync immediately with the object returned by the plugin.
		if ( $ok && is_array( $body['object'] ?? null ) ) {
			Content_Ingestor::ingest( array( 'id' => $site_id ), array( $body['object'] ) );
		}
		if ( $ok && 'create_post' === $operation && null === ( $context['content_id'] ?? null ) && ! empty( $body['object_id'] ) ) {
			$db->query(
				"UPDATE remote_operations SET content_id = (SELECT id FROM wp_content WHERE site_id = ? AND object_type = 'post' AND wp_id = ?) WHERE id = ?",
				array( $site_id, (int) $body['object_id'], $op_id )
			);
		}

		return array(
			'ok'           => $ok,
			'operation_id' => $op_id,
			'error'        => $ok ? null : (string) ( $body['message'] ?? $response['error'] ?: ( 'HTTP ' . $response['code'] ) ),
			'response'     => is_array( $body ) ? $body : array(),
		);
	}

	/**
	 * Rolls back an applied operation using its snapshot.
	 *
	 * @param array $operation remote_operations row.
	 * @param int   $user_id   Acting user.
	 * @return array{ok:bool,error?:string}
	 */
	public static function rollback( array $operation, int $user_id ): array {
		if ( 'applied' !== $operation['status'] || empty( $operation['remote_snapshot_id'] ) ) {
			return array(
				'ok'    => false,
				'error' => 'Operația nu poate fi anulată (nu are snapshot sau nu este aplicată).',
			);
		}
		$result = self::execute(
			(int) $operation['site_id'],
			'rollback',
			array( 'snapshot_id' => (int) $operation['remote_snapshot_id'] ),
			$user_id,
			array(
				'content_id'  => $operation['content_id'],
				'object_type' => $operation['object_type'],
				'taxonomy'    => $operation['taxonomy'],
			)
		);
		if ( $result['ok'] ) {
			Database::instance()->query(
				"UPDATE remote_operations SET status = 'rolled_back', rolled_back_at = UTC_TIMESTAMP() WHERE id = ?",
				array( (int) $operation['id'] )
			);
			$object = $result['response']['object'] ?? null;
			if ( is_array( $object ) ) {
				Content_Ingestor::ingest( array( 'id' => (int) $operation['site_id'] ), array( $object ) );
			}
		}
		return array(
			'ok'    => $result['ok'],
			'error' => $result['error'] ?? null,
		);
	}

	/**
	 * Operation history of a site (optionally of one content item).
	 *
	 * @param int      $site_id    Site.
	 * @param int|null $content_id Content.
	 * @param int      $limit      Limit.
	 * @return array<int,array>
	 */
	public static function history( int $site_id, ?int $content_id = null, int $limit = 30 ): array {
		$sql    = 'SELECT ro.*, u.display_name, c.title AS content_title FROM remote_operations ro
			LEFT JOIN users u ON u.id = ro.requested_by LEFT JOIN wp_content c ON c.id = ro.content_id
			WHERE ro.site_id = ?';
		$params = array( $site_id );
		if ( null !== $content_id ) {
			$sql     .= ' AND ro.content_id = ?';
			$params[] = $content_id;
		}
		$params[] = $limit;
		return Database::instance()->get_results( $sql . ' ORDER BY ro.id DESC LIMIT ?', $params );
	}

	/**
	 * Sends the signed request to the plugin.
	 *
	 * @param int    $site_id   Site.
	 * @param string $operation Operation.
	 * @param array  $params    Params.
	 * @return array{code:int,body:?array,raw:string,error:string}
	 */
	private static function send( int $site_id, string $operation, array $params ): array {
		$site = Database::instance()->get_row( 'SELECT id, url, connector_key_id, connector_remote_url, connector_status FROM sites WHERE id = ?', array( $site_id ) );
		$url  = (string) ( $site['connector_remote_url'] ?? '' );
		if ( null === $site || '' === $url || ! self::url_allowed( $url, (string) $site['url'] ) ) {
			return array(
				'code'  => 0,
				'body'  => null,
				'raw'   => '',
				'error' => 'Pluginul nu a raportat încă un endpoint valid (rulați „Testează conexiunea" din WordPress).',
			);
		}
		$creds = Sites_Repository::find_by_connector_key( (string) $site['connector_key_id'] );
		if ( null === $creds ) {
			return array(
				'code'  => 0,
				'body'  => null,
				'raw'   => '',
				'error' => 'Cheia conectorului lipsește sau este revocată.',
			);
		}

		$parts   = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path    = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
		$body    = (string) json_encode(
			array(
				'operation' => $operation,
				'params'    => $params,
			),
			JSON_UNESCAPED_UNICODE
		);
		$headers = array( 'Content-Type: application/json', 'User-Agent: OptiVio/' . OPTIVIO_VERSION );
		foreach ( Connector_Signature::headers( (string) $creds['connector_key_id'], (string) $creds['secret'], 'POST', $path, $body ) as $name => $value ) {
			$headers[] = $name . ': ' . $value;
		}

		$ch = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $body,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_TIMEOUT        => 45,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			)
		);
		$raw   = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$error = false === $raw ? curl_error( $ch ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error

		if ( '' !== $error ) {
			Logger::error( 'Remote operation transport error', array( 'site_id' => $site_id, 'operation' => $operation, 'error' => $error ) );
		}
		$decoded = json_decode( (string) $raw, true );
		return array(
			'code'  => $code,
			'body'  => is_array( $decoded ) ? $decoded : null,
			'raw'   => (string) $raw,
			'error' => $error,
		);
	}

	/**
	 * SSRF guard: the endpoint must be http(s) on the site's own host (www-insensitive).
	 *
	 * @param string $url      Endpoint.
	 * @param string $site_url Site URL.
	 * @return bool
	 */
	public static function url_allowed( string $url, string $site_url ): bool {
		$p = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $p ) || empty( $p['host'] ) || ! in_array( strtolower( (string) ( $p['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) || isset( $p['user'] ) ) {
			return false;
		}
		$strip = static function ( string $h ): string {
			return (string) preg_replace( '/^www\./', '', strtolower( $h ) );
		};
		return $strip( (string) $p['host'] ) === $strip( (string) parse_url( $site_url, PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}

	/**
	 * Strips large / binary values from logged payloads.
	 *
	 * @param array $data Data.
	 * @return array
	 */
	private static function redact( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( 'data_base64' === $key ) {
				$data[ $key ] = '[' . strlen( (string) $value ) . ' bytes base64]';
			} elseif ( is_string( $value ) && strlen( $value ) > 4000 ) {
				$data[ $key ] = mb_substr( $value, 0, 4000 ) . '…';
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = self::redact( $value );
			}
		}
		return $data;
	}
}
