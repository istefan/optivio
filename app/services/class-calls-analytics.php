<?php
/**
 * OptiVio — Deterministic call analytics: rejected calls & revenue by keyword, hour, channel.
 *
 * Produces the inputs for Ad Scheduling and negative keyword decisions without AI.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use DateTimeImmutable;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Calls analytics.
 */
final class Calls_Analytics {

	/**
	 * Minimum calls before a keyword/hour is judged.
	 */
	public const MIN_SAMPLE = 3;

	/**
	 * Rejected/missed share that triggers a recommendation.
	 */
	public const BAD_SHARE = 0.6;

	/**
	 * Scope.
	 *
	 * @var array<int>|null
	 */
	private ?array $ids;

	/**
	 * Period (days).
	 *
	 * @var int
	 */
	private int $days;

	/**
	 * Constructor.
	 *
	 * @param array<int>|null $ids  Business ids (null = all).
	 * @param int             $days Period.
	 */
	public function __construct( ?array $ids, int $days ) {
		$this->ids  = $ids;
		$this->days = max( 1, min( 365, $days ) );
	}

	/**
	 * Totals.
	 *
	 * @return array<string,float|int>
	 */
	public function totals(): array {
		$row = $this->one(
			"SELECT COUNT(*) AS total,
				SUM(c.outcome = 'accepted') AS accepted,
				SUM(c.outcome LIKE 'rejected\\_%') AS rejected,
				SUM(c.outcome = 'missed') AS missed,
				COALESCE(SUM(CASE WHEN c.outcome = 'accepted' THEN c.revenue_amount END), 0) AS revenue,
				SUM(c.phone_click_id IS NOT NULL) AS linked"
		);
		$total = (int) ( $row['total'] ?? 0 );
		return array(
			'total'         => $total,
			'accepted'      => (int) ( $row['accepted'] ?? 0 ),
			'rejected'      => (int) ( $row['rejected'] ?? 0 ),
			'missed'        => (int) ( $row['missed'] ?? 0 ),
			'revenue'       => (float) ( $row['revenue'] ?? 0 ),
			'linked'        => (int) ( $row['linked'] ?? 0 ),
			'accept_rate'   => $total > 0 ? round( 100 * (int) $row['accepted'] / $total, 1 ) : 0.0,
			'revenue_per'   => (int) ( $row['accepted'] ?? 0 ) > 0 ? round( (float) $row['revenue'] / (int) $row['accepted'], 2 ) : 0.0,
		);
	}

	/**
	 * Breakdown by keyword (call keyword, else the linked click's utm_term).
	 *
	 * @param int $limit Limit.
	 * @return array<int,array>
	 */
	public function by_keyword( int $limit = 15 ): array {
		$rows = $this->all(
			"SELECT COALESCE(NULLIF(c.keyword_text, ''), NULLIF(pc.utm_term, ''), '(fără cuvânt cheie)') AS label,
				COUNT(*) AS total, SUM(c.outcome = 'accepted') AS accepted, SUM(c.outcome LIKE 'rejected\\_%') AS rejected,
				SUM(c.outcome = 'missed') AS missed,
				COALESCE(SUM(CASE WHEN c.outcome = 'accepted' THEN c.revenue_amount END), 0) AS revenue",
			'GROUP BY label ORDER BY total DESC LIMIT ' . max( 1, $limit )
		);
		return array_map( array( $this, 'decorate' ), $rows );
	}

	/**
	 * Breakdown by channel.
	 *
	 * @return array<int,array>
	 */
	public function by_channel(): array {
		$rows = $this->all(
			"SELECT c.channel AS label, COUNT(*) AS total, SUM(c.outcome = 'accepted') AS accepted, SUM(c.outcome LIKE 'rejected\\_%') AS rejected,
				SUM(c.outcome = 'missed') AS missed, COALESCE(SUM(CASE WHEN c.outcome = 'accepted' THEN c.revenue_amount END), 0) AS revenue",
			'GROUP BY c.channel ORDER BY total DESC'
		);
		return array_map( array( $this, 'decorate' ), $rows );
	}

	/**
	 * Rejection reasons.
	 *
	 * @return array<string,int>
	 */
	public function by_outcome(): array {
		$result = array();
		foreach ( $this->all( 'SELECT c.outcome AS label, COUNT(*) AS total', 'GROUP BY c.outcome ORDER BY total DESC' ) as $row ) {
			$result[ (string) $row['label'] ] = (int) $row['total'];
		}
		return $result;
	}

	/**
	 * Hour of day (local time) distribution: 24 rows.
	 *
	 * @return array<int,array{hour:int,accepted:int,rejected:int,missed:int,total:int}>
	 */
	public function by_hour(): array {
		$offset = ( new DateTimeImmutable( 'now', ov_timezone() ) )->getOffset();
		$rows   = $this->all(
			"SELECT HOUR(c.call_time + INTERVAL {$offset} SECOND) AS label, COUNT(*) AS total, SUM(c.outcome = 'accepted') AS accepted,
				SUM(c.outcome LIKE 'rejected\\_%') AS rejected, SUM(c.outcome = 'missed') AS missed",
			'GROUP BY label'
		);
		$hours  = array();
		for ( $h = 0; $h < 24; $h++ ) {
			$hours[ $h ] = array(
				'hour'     => $h,
				'accepted' => 0,
				'rejected' => 0,
				'missed'   => 0,
				'total'    => 0,
			);
		}
		foreach ( $rows as $row ) {
			$h           = (int) $row['label'];
			$hours[ $h ] = array(
				'hour'     => $h,
				'accepted' => (int) $row['accepted'],
				'rejected' => (int) $row['rejected'],
				'missed'   => (int) $row['missed'],
				'total'    => (int) $row['total'],
			);
		}
		return array_values( $hours );
	}

	/**
	 * Hours where most calls are rejected or missed (Ad Scheduling candidates).
	 *
	 * @return array<int,array{hour:int,total:int,bad_share:float}>
	 */
	public function schedule_suggestions(): array {
		$result = array();
		foreach ( $this->by_hour() as $row ) {
			if ( $row['total'] >= self::MIN_SAMPLE ) {
				$bad = ( $row['rejected'] + $row['missed'] ) / $row['total'];
				if ( $bad >= self::BAD_SHARE ) {
					$result[] = array(
						'hour'      => $row['hour'],
						'total'     => $row['total'],
						'bad_share' => round( $bad * 100, 1 ),
					);
				}
			}
		}
		return $result;
	}

	/**
	 * Adds derived metrics and the "bad keyword" flag.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function decorate( array $row ): array {
		$total             = max( 1, (int) $row['total'] );
		$bad               = ( (int) $row['rejected'] + (int) $row['missed'] ) / $total;
		$row['bad_share']  = round( $bad * 100, 1 );
		$row['accept_pct'] = round( 100 * (int) $row['accepted'] / $total, 1 );
		$row['flag']       = (int) $row['total'] >= self::MIN_SAMPLE && $bad >= self::BAD_SHARE;
		return $row;
	}

	/**
	 * Runs a grouped query over the scoped calls.
	 *
	 * @param string $select SELECT ... (columns on alias c / pc).
	 * @param string $tail   GROUP/ORDER/LIMIT (trusted, built internally).
	 * @return array<int,array>
	 */
	private function all( string $select, string $tail ): array {
		[ $where, $params ] = $this->where();
		if ( null === $where ) {
			return array();
		}
		return Database::instance()->get_results( $select . ' FROM calls c LEFT JOIN phone_clicks pc ON pc.id = c.phone_click_id WHERE ' . $where . ' ' . $tail, $params );
	}

	/**
	 * Single-row query.
	 *
	 * @param string $select SELECT.
	 * @return array
	 */
	private function one( string $select ): array {
		[ $where, $params ] = $this->where();
		if ( null === $where ) {
			return array();
		}
		return (array) Database::instance()->get_row( $select . ' FROM calls c WHERE ' . $where, $params );
	}

	/**
	 * Scope + period WHERE.
	 *
	 * @return array{0:?string,1:array}
	 */
	private function where(): array {
		if ( is_array( $this->ids ) && empty( $this->ids ) ) {
			return array( null, array() );
		}
		$where  = 'c.call_time >= UTC_TIMESTAMP() - INTERVAL ? DAY';
		$params = array( $this->days );
		if ( is_array( $this->ids ) ) {
			$where .= ' AND c.business_id IN (' . Database::in_placeholders( $this->ids ) . ')';
			$params = array_merge( $params, $this->ids );
		}
		return array( $where, $params );
	}
}
