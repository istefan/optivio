<?php
/**
 * OptiVio — Calls (accepted / rejected / missed) and their revenue.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Repositories;

use OptiVio\Core\Crypto;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Calls repository.
 */
final class Calls_Repository {

	/**
	 * Outcomes and labels.
	 */
	public const OUTCOMES = array(
		'accepted'          => 'Acceptat',
		'rejected_capacity' => 'Refuzat — lipsă capacitate',
		'rejected_area'     => 'Refuzat — în afara zonei',
		'rejected_job_type' => 'Refuzat — lucrare nepotrivită',
		'rejected_price'    => 'Refuzat — preț',
		'missed'            => 'Pierdut / nepreluat',
		'spam'              => 'Spam / greșit',
		'other'             => 'Altul',
	);

	/**
	 * Editable columns.
	 */
	private const COLUMNS = array( 'site_id', 'call_time', 'duration_sec', 'outcome', 'outcome_note', 'revenue_amount', 'currency', 'keyword_text', 'channel' );

	/**
	 * Paginated list in a scope.
	 *
	 * @param array<int>|null $ids     Business ids (null = all).
	 * @param array           $filters outcome, days.
	 * @param int             $page    Page.
	 * @param int             $per     Per page.
	 * @return array{rows:array,total:int}
	 */
	public static function paginate( ?array $ids, array $filters, int $page, int $per = 25 ): array {
		if ( is_array( $ids ) && empty( $ids ) ) {
			return array(
				'rows'  => array(),
				'total' => 0,
			);
		}
		$where  = array( 'c.call_time >= UTC_TIMESTAMP() - INTERVAL ? DAY' );
		$params = array( max( 1, (int) ( $filters['days'] ?? 30 ) ) );
		if ( is_array( $ids ) ) {
			$where[] = 'c.business_id IN (' . Database::in_placeholders( $ids ) . ')';
			$params  = array_merge( $params, $ids );
		}
		if ( ! empty( $filters['outcome'] ) ) {
			if ( 'rejected' === $filters['outcome'] ) {
				$where[] = "c.outcome LIKE 'rejected\\_%'";
			} else {
				$where[]  = 'c.outcome = ?';
				$params[] = (string) $filters['outcome'];
			}
		}
		$sql_where = implode( ' AND ', $where );
		$db        = Database::instance();
		$total     = (int) $db->get_var( 'SELECT COUNT(*) FROM calls c WHERE ' . $sql_where, $params );
		$rows      = $db->get_results(
			'SELECT c.*, b.name AS business_name, s.name AS site_name, pc.utm_campaign, pc.utm_term AS click_term, pc.page_url AS click_page
			 FROM calls c JOIN businesses b ON b.id = c.business_id
			 LEFT JOIN sites s ON s.id = c.site_id
			 LEFT JOIN phone_clicks pc ON pc.id = c.phone_click_id
			 WHERE ' . $sql_where . ' ORDER BY c.call_time DESC LIMIT ? OFFSET ?',
			array_merge( $params, array( $per, max( 0, ( $page - 1 ) * $per ) ) )
		);
		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Finds a call (authorization via its business_id is the caller's job).
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function find( int $id ): ?array {
		return Database::instance()->get_row( 'SELECT * FROM calls WHERE id = ?', array( $id ) );
	}

	/**
	 * Creates a call.
	 *
	 * @param int         $business_id Business id.
	 * @param array       $data        Sanitized data.
	 * @param string|null $caller      Caller number (only HMAC + last 3 digits are stored).
	 * @param string      $source      manual|import|tracking.
	 * @param int|null    $user_id     Creator.
	 * @param int|null    $batch_id    Import batch.
	 * @return int
	 */
	public static function create( int $business_id, array $data, ?string $caller, string $source, ?int $user_id, ?int $batch_id = null ): int {
		$row                    = array_intersect_key( $data, array_flip( self::COLUMNS ) );
		$row['business_id']     = $business_id;
		$row['source']          = $source;
		$row['created_by']      = $user_id;
		$row['import_batch_id'] = $batch_id;
		$row                    = array_merge( $row, self::caller_columns( $caller ) );
		return Database::instance()->insert( 'calls', $row );
	}

	/**
	 * Updates a call of a business.
	 *
	 * @param int   $id          Id.
	 * @param int   $business_id Business id.
	 * @param array $data        Data.
	 * @return void
	 */
	public static function update( int $id, int $business_id, array $data ): void {
		$row = array_intersect_key( $data, array_flip( self::COLUMNS ) );
		if ( ! empty( $row ) ) {
			Database::instance()->update(
				'calls',
				$row,
				array(
					'id'          => $id,
					'business_id' => $business_id,
				)
			);
		}
	}

	/**
	 * Deletes a call of a business.
	 *
	 * @param int $id          Id.
	 * @param int $business_id Business id.
	 * @return int
	 */
	public static function delete( int $id, int $business_id ): int {
		return Database::instance()->delete(
			'calls',
			array(
				'id'          => $id,
				'business_id' => $business_id,
			)
		);
	}

	/**
	 * Caller hash + last 3 digits.
	 *
	 * @param string|null $caller Caller number.
	 * @return array{caller_hash:?string,caller_last3:?string}
	 */
	private static function caller_columns( ?string $caller ): array {
		$digits = (string) preg_replace( '/\D/', '', (string) $caller );
		if ( strlen( $digits ) < 6 ) {
			return array(
				'caller_hash'  => null,
				'caller_last3' => null,
			);
		}
		return array(
			'caller_hash'  => Crypto::keyed_hash( substr( $digits, -9 ), 'caller' ),
			'caller_last3' => substr( $digits, -3 ),
		);
	}
}
