<?php
/**
 * OptiVio — Machine API for the OptiVio Connector WordPress plugin (/api/connector/v1/*).
 *
 * No session, no CSRF: every request is authenticated with HMAC-SHA256
 * (Connector_Signature) + timestamp + nonce, and rate limited per key and per IP.
 * Responses never contain secrets. No AI is ever called from here (RULES R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Logger;
use OptiVio\Core\Response;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Connector_Signature;
use OptiVio\Security\Rate_Limiter;
use OptiVio\Services\Content_Ingestor;
use OptiVio\Services\Event_Ingestor;
use OptiVio\Services\Remote_Operations;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Connector API controller.
 */
final class Connector_Api_Controller extends Controller {

	/**
	 * Requests per minute per key.
	 */
	private const KEY_RPM = 240;

	/**
	 * POST /api/connector/v1/heartbeat — plugin status and capabilities.
	 *
	 * @return void
	 */
	public function heartbeat(): void {
		[ $site, $data ] = $this->authenticate();

		$site_url   = (string) Database::instance()->get_var( 'SELECT url FROM sites WHERE id = ?', array( (int) $site['id'] ) );
		$remote_url = mb_substr( trim( (string) ( $data['remote_url'] ?? '' ) ), 0, 500 );
		$remote_ok  = '' !== $remote_url && false !== filter_var( $remote_url, FILTER_VALIDATE_URL ) && Remote_Operations::url_allowed( $remote_url, $site_url );

		Database::instance()->update(
			'sites',
			array(
				'connector_remote_url'   => $remote_ok ? $remote_url : null,
				'connector_status'       => 'connected',
				'connector_last_seen_at' => Database::now(),
				'plugin_version'         => mb_substr( sanitize_text_field( (string) ( $data['plugin_version'] ?? '' ) ), 0, 20 ) ?: null,
				'wp_version'             => mb_substr( sanitize_text_field( (string) ( $data['wp_version'] ?? '' ) ), 0, 20 ) ?: null,
				'has_yoast'              => ! empty( $data['has_yoast'] ) ? 1 : 0,
				'has_gravity_forms'      => ! empty( $data['has_gravity_forms'] ) ? 1 : 0,
			),
			array( 'id' => (int) $site['id'] )
		);

		Response::json(
			array(
				'ok'          => true,
				'server_time' => time(),
				'config'      => array(
					'spam' => array(
						'min_fill_seconds' => (int) Settings_Repository::get( 'spam.min_fill_seconds', 3 ),
						'blocked_words'    => array_values( (array) Settings_Repository::get( 'spam.blocked_words', array() ) ),
					),
					'max_events_per_request' => Event_Ingestor::MAX_EVENTS,
				),
			)
		);
	}

	/**
	 * POST /api/connector/v1/events — batch of phone clicks and form entries.
	 *
	 * @return void
	 */
	public function events(): void {
		[ $site, $data ] = $this->authenticate();

		$events = $data['events'] ?? null;
		if ( ! is_array( $events ) || empty( $events ) ) {
			Response::json( array( 'ok' => false, 'error' => 'no_events' ), 422 );
		}
		if ( count( $events ) > Event_Ingestor::MAX_EVENTS ) {
			Response::json( array( 'ok' => false, 'error' => 'too_many_events', 'max' => Event_Ingestor::MAX_EVENTS ), 413 );
		}

		$result = Event_Ingestor::ingest( $site, $events );
		Database::instance()->query(
			"UPDATE sites SET connector_last_seen_at = UTC_TIMESTAMP(), connector_status = 'connected' WHERE id = ?",
			array( (int) $site['id'] )
		);

		Response::json( array_merge( array( 'ok' => true ), $result ) );
	}

	/**
	 * POST /api/connector/v1/content — mirrored posts/terms with Yoast data.
	 *
	 * @return void
	 */
	public function content(): void {
		[ $site, $data ] = $this->authenticate();

		$items = $data['items'] ?? null;
		if ( ! is_array( $items ) || empty( $items ) ) {
			Response::json( array( 'ok' => false, 'error' => 'no_items' ), 422 );
		}
		if ( count( $items ) > Content_Ingestor::MAX_ITEMS ) {
			Response::json( array( 'ok' => false, 'error' => 'too_many_items', 'max' => Content_Ingestor::MAX_ITEMS ), 413 );
		}
		Response::json( array_merge( array( 'ok' => true ), Content_Ingestor::ingest( $site, $items ) ) );
	}

	/**
	 * Verifies signature, rate limits and decodes the JSON body.
	 *
	 * @return array{0:array,1:array} Site row and decoded body.
	 */
	private function authenticate(): array {
		$ip_bucket = 'connector:ip:' . $this->request->ip();
		if ( Rate_Limiter::blocked_for( $ip_bucket ) > 0 ) {
			Response::json( array( 'ok' => false, 'error' => 'rate_limited' ), 429 );
		}

		$body = $this->request->raw_body( 4194304 );
		if ( null === $body ) {
			Response::json( array( 'ok' => false, 'error' => 'payload_too_large' ), 413 );
		}

		$key    = $this->request->header( 'X-OptiVio-Key' );
		$result = Connector_Signature::verify(
			$key,
			$this->request->header( 'X-OptiVio-Timestamp' ),
			strtolower( $this->request->header( 'X-OptiVio-Nonce' ) ),
			strtolower( $this->request->header( 'X-OptiVio-Signature' ) ),
			'POST',
			$this->request->path(),
			$body
		);

		if ( ! $result['ok'] ) {
			// Failed authentications count against the IP (blocks key/secret guessing).
			Rate_Limiter::hit( $ip_bucket, 30, 600, 1800 );
			Logger::info( 'Connector auth failed', array( 'error' => $result['error'], 'path' => $this->request->path() ) );
			Response::json( array( 'ok' => false, 'error' => 'unauthorized' ), 401 );
		}

		$site = (array) $result['site'];
		$key_bucket = 'connector:key:' . $key;
		if ( Rate_Limiter::blocked_for( $key_bucket ) > 0 || Rate_Limiter::hit( $key_bucket, self::KEY_RPM, 60, 60 ) ) {
			Response::json( array( 'ok' => false, 'error' => 'rate_limited' ), 429 );
		}
		if ( 'archived' === $site['status'] || 'paused' === $site['status'] ) {
			Response::json( array( 'ok' => false, 'error' => 'site_inactive' ), 409 );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			Response::json( array( 'ok' => false, 'error' => 'invalid_json' ), 400 );
		}
		return array( $site, $data );
	}
}
