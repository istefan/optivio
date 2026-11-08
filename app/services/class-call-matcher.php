<?php
/**
 * OptiVio — Deterministic correlation of calls with tel: clicks.
 *
 * A call is linked to the closest unlinked phone click of the same business that happened
 * between 30 minutes before and 2 minutes after the call. Attribution (channel, keyword,
 * site) is then inherited from the click when the call has none.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Call ↔ click matcher.
 */
final class Call_Matcher {

	private const BEFORE_SECONDS = 1800;
	private const AFTER_SECONDS  = 120;

	/**
	 * Matches one call. Returns true when linked.
	 *
	 * @param int $call_id Call id.
	 * @return bool
	 */
	public static function match_call( int $call_id ): bool {
		$db   = Database::instance();
		$call = $db->get_row( 'SELECT id, business_id, call_time, phone_click_id, channel, keyword_text, site_id FROM calls WHERE id = ?', array( $call_id ) );
		if ( null === $call || null !== $call['phone_click_id'] ) {
			return false;
		}

		$click = $db->get_row(
			'SELECT pc.id, pc.site_id, pc.channel, pc.utm_term
			 FROM phone_clicks pc JOIN sites s ON s.id = pc.site_id
			 WHERE s.business_id = ?
				AND pc.occurred_at BETWEEN ? - INTERVAL ? SECOND AND ? + INTERVAL ? SECOND
				AND NOT EXISTS (SELECT 1 FROM calls c2 WHERE c2.phone_click_id = pc.id)
			 ORDER BY ABS(TIMESTAMPDIFF(SECOND, pc.occurred_at, ?)) ASC LIMIT 1',
			array( (int) $call['business_id'], $call['call_time'], self::BEFORE_SECONDS, $call['call_time'], self::AFTER_SECONDS, $call['call_time'] )
		);
		if ( null === $click ) {
			return false;
		}

		$db->update(
			'calls',
			array(
				'phone_click_id' => (int) $click['id'],
				'site_id'        => $call['site_id'] ?? (int) $click['site_id'],
				'channel'        => 'other' === $call['channel'] ? (string) $click['channel'] : (string) $call['channel'],
				'keyword_text'   => $call['keyword_text'] ?? $click['utm_term'],
			),
			array( 'id' => $call_id )
		);
		return true;
	}

	/**
	 * Matches all unlinked calls of a business (optionally of one import batch).
	 *
	 * @param int      $business_id Business id.
	 * @param int|null $batch_id    Import batch.
	 * @return int Linked calls.
	 */
	public static function match_unlinked( int $business_id, ?int $batch_id = null ): int {
		$sql    = 'SELECT id FROM calls WHERE business_id = ? AND phone_click_id IS NULL AND call_time > UTC_TIMESTAMP() - INTERVAL 400 DAY';
		$params = array( $business_id );
		if ( null !== $batch_id ) {
			$sql     .= ' AND import_batch_id = ?';
			$params[] = $batch_id;
		}
		$linked = 0;
		foreach ( Database::instance()->get_col( $sql . ' ORDER BY call_time', $params ) as $id ) {
			$linked += self::match_call( (int) $id ) ? 1 : 0;
		}
		return $linked;
	}
}
