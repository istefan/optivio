<?php
/**
 * OptiVio — Ingests events sent by the OptiVio Connector plugin (phone clicks, form entries).
 *
 * Deterministic only: attribution, device detection, spam scoring. Never calls AI (RULES R5).
 * Visitor IPs and contact details are never stored in clear (HMAC / AES-256-GCM).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use OptiVio\Core\Crypto;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Event ingestor.
 */
final class Event_Ingestor {

	/**
	 * Max events per request.
	 */
	public const MAX_EVENTS = 100;

	/**
	 * Ingests a batch for an authenticated site.
	 *
	 * @param array $site   Site row (id, business_id, ...).
	 * @param array $events Decoded events.
	 * @return array{stored:int,duplicates:int,invalid:int,results:array<int,array>}
	 */
	public static function ingest( array $site, array $events ): array {
		$summary = array(
			'stored'     => 0,
			'duplicates' => 0,
			'invalid'    => 0,
			'results'    => array(),
		);
		$host    = self::site_host( (int) $site['id'] );

		foreach ( array_slice( array_values( $events ), 0, self::MAX_EVENTS ) as $index => $event ) {
			if ( ! is_array( $event ) ) {
				$result = array( 'status' => 'invalid', 'error' => 'not_an_object' );
			} else {
				$type   = (string) ( $event['type'] ?? '' );
				$result = match ( $type ) {
					'phone_click' => self::phone_click( $site, $event, $host ),
					'form_entry'  => self::form_entry( $site, $event, $host ),
					default       => array( 'status' => 'invalid', 'error' => 'unknown_type' ),
				};
			}

			$key = 'stored' === $result['status'] ? 'stored' : ( 'duplicate' === $result['status'] ? 'duplicates' : 'invalid' );
			++$summary[ $key ];
			$summary['results'][] = array_merge( array( 'index' => $index ), $result );
		}
		return $summary;
	}

	/**
	 * Stores a phone click.
	 *
	 * @param array  $site  Site.
	 * @param array  $e     Event.
	 * @param string $host  Site host.
	 * @return array
	 */
	private static function phone_click( array $site, array $e, string $host ): array {
		$uuid = strtolower( (string) ( $e['uuid'] ?? '' ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid ) ) {
			return array( 'status' => 'invalid', 'error' => 'uuid' );
		}
		$phone = mb_substr( (string) preg_replace( '/[^0-9+]/', '', (string) ( $e['phone'] ?? '' ) ), 0, 40 );
		if ( strlen( $phone ) < 3 ) {
			return array( 'status' => 'invalid', 'error' => 'phone' );
		}
		$time = self::time( $e['occurred_at'] ?? null );
		if ( null === $time ) {
			return array( 'status' => 'invalid', 'error' => 'occurred_at' );
		}

		$common = self::common( $e, $host );
		$stmt   = Database::instance()->query(
			'INSERT IGNORE INTO phone_clicks (site_id, event_uuid, phone_number, page_url, referrer, landing_page, gclid, utm_source, utm_medium, utm_campaign, utm_term, utm_content, channel, device_type, ip_hash, occurred_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array(
				(int) $site['id'],
				$uuid,
				$phone,
				$common['page_url'],
				$common['referrer'],
				$common['landing_page'],
				$common['gclid'],
				$common['utm_source'],
				$common['utm_medium'],
				$common['utm_campaign'],
				$common['utm_term'],
				$common['utm_content'],
				$common['channel'],
				$common['device_type'],
				$common['ip_hash'],
				$time,
			)
		);
		return 0 === $stmt->rowCount() ? array( 'status' => 'duplicate' ) : array( 'status' => 'stored' );
	}

	/**
	 * Stores a form entry with deterministic spam scoring.
	 *
	 * @param array  $site Site.
	 * @param array  $e    Event.
	 * @param string $host Site host.
	 * @return array
	 */
	private static function form_entry( array $site, array $e, string $host ): array {
		$entry_id = absint( $e['entry_id'] ?? 0 );
		$form_id  = absint( $e['form_id'] ?? 0 );
		$time     = self::time( $e['occurred_at'] ?? null );
		if ( 0 === $entry_id || 0 === $form_id || null === $time ) {
			return array( 'status' => 'invalid', 'error' => 'ids_or_time' );
		}

		$fields = array();
		foreach ( array_slice( (array) ( $e['fields'] ?? array() ), 0, 50, true ) as $label => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
			}
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			$label            = mb_substr( sanitize_text_field( (string) $label ), 0, 120 );
			$fields[ $label ] = mb_substr( sanitize_textarea_field( (string) $value ), 0, 5000 );
		}

		$site_id = (int) $site['id'];
		$common  = self::common( $e, $host );
		$contact = self::contact_hash( $fields );
		$flags   = array(
			'honeypot'         => ! empty( $e['flags']['honeypot'] ),
			'fill_seconds'     => isset( $e['flags']['fill_seconds'] ) && is_numeric( $e['flags']['fill_seconds'] ) ? (float) $e['flags']['fill_seconds'] : null,
			'blocked_patterns' => absint( $e['flags']['blocked_patterns'] ?? 0 ),
		);
		$spam    = Spam_Scorer::score( $fields, $flags, $site_id, $common['ip_hash'], $contact );

		$stmt = Database::instance()->query(
			'INSERT IGNORE INTO form_entries (site_id, external_entry_id, form_id, form_title, fields_cipher, contact_hash, page_url, landing_page, referrer,
				gclid, utm_source, utm_medium, utm_campaign, utm_term, channel, device_type, ip_hash, user_agent_hash, plugin_spam_flags, spam_score, spam_status, spam_reasons, occurred_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			array(
				$site_id,
				$entry_id,
				$form_id,
				mb_substr( sanitize_text_field( (string) ( $e['form_title'] ?? '' ) ), 0, 190 ),
				Crypto::encrypt( (string) json_encode( $fields, JSON_UNESCAPED_UNICODE ), self::fields_context( $site_id ) ),
				$contact,
				$common['page_url'],
				$common['landing_page'],
				$common['referrer'],
				$common['gclid'],
				$common['utm_source'],
				$common['utm_medium'],
				$common['utm_campaign'],
				$common['utm_term'],
				$common['channel'],
				$common['device_type'],
				$common['ip_hash'],
				'' !== $common['user_agent'] ? Crypto::keyed_hash( $common['user_agent'], 'visitor_ua' ) : null,
				json_encode( $flags ),
				$spam['score'],
				$spam['status'],
				json_encode( $spam['reasons'] ),
				$time,
			)
		);

		if ( 0 === $stmt->rowCount() ) {
			return array( 'status' => 'duplicate' );
		}
		return array(
			'status'      => 'stored',
			'spam_status' => $spam['status'],
		);
	}

	/**
	 * Decrypts the fields of a stored entry (authorized callers only).
	 *
	 * @param array $entry form_entries row.
	 * @return array<string,string>
	 */
	public static function decrypt_fields( array $entry ): array {
		$json = Crypto::decrypt( (string) $entry['fields_cipher'], self::fields_context( (int) $entry['site_id'] ) );
		$data = null === $json ? null : json_decode( $json, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * AAD context for form fields.
	 *
	 * @param int $site_id Site id.
	 * @return string
	 */
	public static function fields_context( int $site_id ): string {
		return 'form_entries:fields:' . $site_id;
	}

	/**
	 * Common attribution columns.
	 *
	 * @param array  $e    Event.
	 * @param string $host Site host.
	 * @return array
	 */
	private static function common( array $e, string $host ): array {
		$a    = is_array( $e['attribution'] ?? null ) ? $e['attribution'] : array();
		$text = static function ( $value, int $max ): ?string {
			$value = mb_substr( sanitize_text_field( (string) $value ), 0, $max );
			return '' === $value ? null : $value;
		};
		$url  = static function ( $value ): ?string {
			$value = trim( (string) $value );
			return '' !== $value && preg_match( '#^https?://#i', $value ) && false !== filter_var( $value, FILTER_VALIDATE_URL ) ? mb_substr( $value, 0, 500 ) : null;
		};
		$ua   = mb_substr( sanitize_text_field( (string) ( $e['user_agent'] ?? '' ) ), 0, 400 );
		$ip   = (string) ( $e['ip'] ?? '' );

		$attr = array(
			'gclid'        => $text( $a['gclid'] ?? '', 255 ),
			'gbraid'       => $text( $a['gbraid'] ?? '', 255 ),
			'wbraid'       => $text( $a['wbraid'] ?? '', 255 ),
			'utm_source'   => $text( $a['utm_source'] ?? '', 120 ),
			'utm_medium'   => $text( $a['utm_medium'] ?? '', 120 ),
			'utm_campaign' => $text( $a['utm_campaign'] ?? '', 190 ),
			'utm_term'     => $text( $a['utm_term'] ?? '', 190 ),
			'utm_content'  => $text( $a['utm_content'] ?? '', 190 ),
			'referrer'     => $url( $a['referrer'] ?? '' ),
			'landing_page' => $url( $a['landing_page'] ?? '' ),
		);

		return array_merge(
			$attr,
			array(
				'page_url'    => $url( $e['page_url'] ?? '' ),
				'channel'     => Attribution::channel( $attr, $host ),
				'device_type' => Attribution::device( (string) ( $e['device'] ?? '' ), $ua ),
				'ip_hash'     => false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? Crypto::keyed_hash( $ip, 'visitor_ip' ) : null,
				'user_agent'  => $ua,
			)
		);
	}

	/**
	 * Hash of the contact (email preferred, else last 9 phone digits).
	 *
	 * @param array $fields Fields.
	 * @return string|null
	 */
	private static function contact_hash( array $fields ): ?string {
		$email = Spam_Scorer::find_email( $fields );
		if ( null !== $email && false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return Crypto::keyed_hash( $email, 'contact' );
		}
		$phone  = Spam_Scorer::find_phone( $fields );
		$digits = null !== $phone ? (string) preg_replace( '/\D/', '', $phone ) : '';
		return strlen( $digits ) >= 9 ? Crypto::keyed_hash( substr( $digits, -9 ), 'contact' ) : null;
	}

	/**
	 * Parses an event time (ISO 8601 or unix), rejecting the far past and the future.
	 *
	 * @param mixed $value Value.
	 * @return string|null UTC datetime.
	 */
	private static function time( $value ): ?string {
		try {
			if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
				$date = ( new DateTimeImmutable( '@' . (int) $value ) );
			} elseif ( is_string( $value ) && '' !== $value ) {
				$date = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
			} else {
				return null;
			}
		} catch ( Exception $e ) {
			return null;
		}
		$ts = $date->getTimestamp();
		if ( $ts > time() + 300 || $ts < time() - 30 * 86400 ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	/**
	 * Host of a site.
	 *
	 * @param int $site_id Site id.
	 * @return string
	 */
	private static function site_host( int $site_id ): string {
		return (string) Database::instance()->get_var( 'SELECT domain FROM sites WHERE id = ?', array( $site_id ) );
	}
}
