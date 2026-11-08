<?php
/**
 * OptiVio — Deterministic dashboard metrics (pure SQL/PHP, zero AI).
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
 * Computes KPIs, time series and distributions for a business scope.
 *
 * Scope: array of business ids, or null for "all businesses" (super admin only —
 * the caller obtains the scope from Gate::scope_business_ids()).
 */
final class Dashboard_Metrics {

	/**
	 * Clean form condition (spam excluded, manual overrides respected).
	 */
	private const CLEAN_FORM = "((fe.spam_status = 'clean' AND fe.spam_override <> 'marked_spam') OR fe.spam_override = 'marked_clean')";

	/**
	 * Spam form condition (manual overrides respected).
	 */
	private const SPAM_FORM = "((fe.spam_status = 'spam' AND fe.spam_override <> 'marked_clean') OR fe.spam_override = 'marked_spam')";

	/**
	 * Business scope.
	 *
	 * @var array<int>|null
	 */
	private ?array $ids;

	/**
	 * Database.
	 *
	 * @var Database
	 */
	private Database $db;

	/**
	 * Constructor.
	 *
	 * @param array<int>|null $ids Business ids (null = all).
	 */
	public function __construct( ?array $ids ) {
		$this->ids = null === $ids ? null : array_values( array_map( 'intval', $ids ) );
		$this->db  = Database::instance();
	}

	/**
	 * Whether the scope is empty (operator without businesses).
	 *
	 * @return bool
	 */
	public function is_empty_scope(): bool {
		return is_array( $this->ids ) && empty( $this->ids );
	}

	/**
	 * KPI cards for the last $days compared with the previous $days.
	 *
	 * @param int $days Period length.
	 * @return array<string,array{value:float,previous:float,delta:float|null}>
	 */
	public function kpis( int $days ): array {
		[ $from, $to, $prev_from ] = $this->ranges( $days );

		$phone       = $this->count_phone( $from, $to );
		$phone_prev  = $this->count_phone( $prev_from, $from );
		$forms       = $this->count_forms( $from, $to );
		$forms_prev  = $this->count_forms( $prev_from, $from );
		$revenue     = $this->sum_revenue( $from, $to );
		$revenue_pre = $this->sum_revenue( $prev_from, $from );

		return array(
			'conversions' => $this->kpi( $phone + $forms, $phone_prev + $forms_prev ),
			'phone'       => $this->kpi( $phone, $phone_prev ),
			'revenue'     => $this->kpi( $revenue, $revenue_pre ),
		);
	}

	/**
	 * Daily series (current and previous period) for the main chart.
	 *
	 * @param int    $days   Period length (7..365).
	 * @param string $metric conversions|phone|forms|revenue.
	 * @return array{labels:array,current:array,previous:array,metric:string,total:float}
	 */
	public function series( int $days, string $metric ): array {
		[ $from, $to, $prev_from ] = $this->ranges( $days );

		$current  = $this->daily( $metric, $from, $to );
		$previous = $this->daily( $metric, $prev_from, $from );

		$labels      = array();
		$cur_vals    = array();
		$prev_vals   = array();
		$local_start = $this->local_date( $from );
		$local_prev  = $this->local_date( $prev_from );

		for ( $i = 0; $i < $days; $i++ ) {
			$day         = $local_start->modify( '+' . $i . ' day' );
			$pday        = $local_prev->modify( '+' . $i . ' day' )->format( 'Y-m-d' );
			$labels[]    = $day->format( 'd.m' );
			$cur_vals[]  = round( (float) ( $current[ $day->format( 'Y-m-d' ) ] ?? 0 ), 2 );
			$prev_vals[] = round( (float) ( $previous[ $pday ] ?? 0 ), 2 );
		}

		return array(
			'labels'   => $labels,
			'current'  => $cur_vals,
			'previous' => $prev_vals,
			'metric'   => $metric,
			'total'    => array_sum( $cur_vals ),
		);
	}

	/**
	 * Conversions per weekday (Mon..Sun) over the period.
	 *
	 * @param int $days Period.
	 * @return array<int,int> 7 values, Monday first.
	 */
	public function weekday_distribution( int $days ): array {
		[ $from, $to ] = $this->ranges( $days );
		$offset        = $this->tz_offset();
		$values        = array_fill( 0, 7, 0 );

		foreach ( array( 'phone', 'forms' ) as $kind ) {
			[ $table_sql, $params ] = $this->event_source( $kind, $from, $to );
			$rows                   = $this->db->get_results(
				'SELECT WEEKDAY(t.occurred_at + INTERVAL ' . $offset . ' SECOND) AS wd, COUNT(*) AS total FROM ' . $table_sql . ' GROUP BY wd',
				$params
			);
			foreach ( $rows as $row ) {
				$values[ (int) $row['wd'] ] += (int) $row['total'];
			}
		}
		return $values;
	}

	/**
	 * Latest conversions (phone clicks + clean or suspect form entries).
	 *
	 * @param int $limit  Limit.
	 * @param int $offset Offset.
	 * @param string $kind all|phone|forms|spam.
	 * @return array<int,array>
	 */
	public function recent_conversions( int $limit = 6, int $offset = 0, string $kind = 'all' ): array {
		if ( $this->is_empty_scope() ) {
			return array();
		}
		[ $scope_sql, $scope_params ] = $this->scope_sql( 's.business_id' );

		$parts  = array();
		$params = array();

		if ( 'forms' !== $kind && 'spam' !== $kind ) {
			$parts[] = "SELECT 'phone' AS kind, pc.id, pc.occurred_at, pc.channel, pc.utm_source, pc.utm_campaign, pc.utm_term, pc.page_url, pc.device_type,
					pc.phone_number AS detail, NULL AS spam_status, s.name AS site_name, b.name AS business_name
				FROM phone_clicks pc JOIN sites s ON s.id = pc.site_id JOIN businesses b ON b.id = s.business_id
				WHERE " . $scope_sql;
			$params  = array_merge( $params, $scope_params );
		}
		if ( 'phone' !== $kind ) {
			$parts[] = "SELECT 'form' AS kind, fe.id, fe.occurred_at, fe.channel, fe.utm_source, fe.utm_campaign, fe.utm_term, fe.page_url, fe.device_type,
					fe.form_title AS detail, fe.spam_status, s.name AS site_name, b.name AS business_name
				FROM form_entries fe JOIN sites s ON s.id = fe.site_id JOIN businesses b ON b.id = s.business_id
				WHERE " . $scope_sql . ' AND ' . ( 'spam' === $kind ? '' : 'NOT ' ) . self::SPAM_FORM;
			$params  = array_merge( $params, $scope_params );
		}

		$params[] = $limit;
		$params[] = $offset;

		return $this->db->get_results( '(' . implode( ') UNION ALL (', $parts ) . ') ORDER BY occurred_at DESC LIMIT ? OFFSET ?', $params );
	}

	/**
	 * Total conversions count for pagination.
	 *
	 * @param string $kind all|phone|forms.
	 * @return int
	 */
	public function count_conversions( string $kind = 'all' ): int {
		if ( $this->is_empty_scope() ) {
			return 0;
		}
		[ $scope_sql, $params ] = $this->scope_sql( 's.business_id' );
		$total                  = 0;
		if ( 'forms' !== $kind && 'spam' !== $kind ) {
			$total += (int) $this->db->get_var( 'SELECT COUNT(*) FROM phone_clicks pc JOIN sites s ON s.id = pc.site_id WHERE ' . $scope_sql, $params );
		}
		if ( 'phone' !== $kind ) {
			$total += (int) $this->db->get_var( 'SELECT COUNT(*) FROM form_entries fe JOIN sites s ON s.id = fe.site_id WHERE ' . ( 'spam' === $kind ? '' : 'NOT ' ) . self::SPAM_FORM . ' AND ' . $scope_sql, $params );
		}
		return $total;
	}

	/**
	 * Returns [from, to, previous_from] UTC datetimes aligned to local midnight.
	 *
	 * @param int $days Days.
	 * @return array{0:string,1:string,2:string}
	 */
	private function ranges( int $days ): array {
		$days     = max( 1, min( 365, $days ) );
		$tz       = ov_timezone();
		$tomorrow = ( new DateTimeImmutable( 'tomorrow', $tz ) )->setTimezone( new \DateTimeZone( 'UTC' ) );
		$from     = $tomorrow->modify( '-' . $days . ' day' );
		$prev     = $from->modify( '-' . $days . ' day' );
		return array( $from->format( 'Y-m-d H:i:s' ), $tomorrow->format( 'Y-m-d H:i:s' ), $prev->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Current offset (seconds) of the app timezone vs UTC.
	 *
	 * @return int
	 */
	private function tz_offset(): int {
		return ( new DateTimeImmutable( 'now', ov_timezone() ) )->getOffset();
	}

	/**
	 * Builds a KPI tuple.
	 *
	 * @param float|int $value    Current.
	 * @param float|int $previous Previous.
	 * @return array{value:float,previous:float,delta:float|null}
	 */
	private function kpi( $value, $previous ): array {
		$value    = (float) $value;
		$previous = (float) $previous;
		$delta    = $previous > 0 ? round( ( ( $value - $previous ) / $previous ) * 100, 1 ) : ( $value > 0 ? null : 0.0 );
		return array(
			'value'    => $value,
			'previous' => $previous,
			'delta'    => $delta,
		);
	}

	/**
	 * Returns the SQL condition limiting a column to the scope.
	 *
	 * @param string $column Column (trusted, hardcoded by callers).
	 * @return array{0:string,1:array}
	 */
	private function scope_sql( string $column ): array {
		if ( null === $this->ids ) {
			return array( '1 = 1', array() );
		}
		if ( empty( $this->ids ) ) {
			return array( '1 = 0', array() );
		}
		return array( $column . ' IN (' . Database::in_placeholders( $this->ids ) . ')', $this->ids );
	}

	/**
	 * Returns a FROM fragment (aliased "t" with occurred_at) for events of a kind in a range.
	 *
	 * @param string $kind phone|forms.
	 * @param string $from From UTC.
	 * @param string $to   To UTC.
	 * @return array{0:string,1:array}
	 */
	private function event_source( string $kind, string $from, string $to ): array {
		[ $scope_sql, $params ] = $this->scope_sql( 's.business_id' );
		if ( 'phone' === $kind ) {
			$sql = '(SELECT pc.occurred_at FROM phone_clicks pc JOIN sites s ON s.id = pc.site_id WHERE ' . $scope_sql . ' AND pc.occurred_at >= ? AND pc.occurred_at < ?) t';
		} else {
			$sql = '(SELECT fe.occurred_at FROM form_entries fe JOIN sites s ON s.id = fe.site_id WHERE ' . $scope_sql . ' AND ' . self::CLEAN_FORM . ' AND fe.occurred_at >= ? AND fe.occurred_at < ?) t';
		}
		return array( $sql, array_merge( $params, array( $from, $to ) ) );
	}

	/**
	 * Counts phone clicks in range.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return int
	 */
	private function count_phone( string $from, string $to ): int {
		[ $sql, $params ] = $this->event_source( 'phone', $from, $to );
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . $sql, $params );
	}

	/**
	 * Counts clean form entries in range.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return int
	 */
	private function count_forms( string $from, string $to ): int {
		[ $sql, $params ] = $this->event_source( 'forms', $from, $to );
		return (int) $this->db->get_var( 'SELECT COUNT(*) FROM ' . $sql, $params );
	}

	/**
	 * Sums call revenue in range.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return float
	 */
	private function sum_revenue( string $from, string $to ): float {
		[ $scope_sql, $params ] = $this->scope_sql( 'c.business_id' );
		return (float) $this->db->get_var(
			'SELECT COALESCE(SUM(c.revenue_amount), 0) FROM calls c WHERE ' . $scope_sql . " AND c.outcome = 'accepted' AND c.call_time >= ? AND c.call_time < ?",
			array_merge( $params, array( $from, $to ) )
		);
	}

	/**
	 * Daily values for a metric, keyed by local date (Y-m-d).
	 *
	 * @param string $metric Metric.
	 * @param string $from   From.
	 * @param string $to     To.
	 * @return array<string,float>
	 */
	private function daily( string $metric, string $from, string $to ): array {
		$offset = $this->tz_offset();
		$result = array();
		$shift  = 'INTERVAL ' . $offset . ' SECOND';

		if ( 'revenue' === $metric ) {
			[ $scope_sql, $params ] = $this->scope_sql( 'c.business_id' );
			$rows                   = $this->db->get_results(
				'SELECT DATE(c.call_time + ' . $shift . ') AS d, SUM(c.revenue_amount) AS v FROM calls c WHERE ' . $scope_sql . " AND c.outcome = 'accepted' AND c.call_time >= ? AND c.call_time < ? GROUP BY d",
				array_merge( $params, array( $from, $to ) )
			);
		} else {
			$kinds = 'conversions' === $metric ? array( 'phone', 'forms' ) : array( 'phone' === $metric ? 'phone' : 'forms' );
			$rows  = array();
			foreach ( $kinds as $kind ) {
				[ $sql, $params ] = $this->event_source( $kind, $from, $to );
				$rows             = array_merge( $rows, $this->db->get_results( 'SELECT DATE(t.occurred_at + ' . $shift . ') AS d, COUNT(*) AS v FROM ' . $sql . ' GROUP BY d', $params ) );
			}
		}

		foreach ( $rows as $row ) {
			$key            = (string) $row['d'];
			$result[ $key ] = ( $result[ $key ] ?? 0 ) + (float) $row['v'];
		}
		return $result;
	}

	/**
	 * Converts a UTC datetime (local midnight) to a local DateTimeImmutable date.
	 *
	 * @param string $utc UTC datetime.
	 * @return DateTimeImmutable
	 */
	private function local_date( string $utc ): DateTimeImmutable {
		return new DateTimeImmutable( ( new DateTimeImmutable( $utc . ' UTC' ) )->setTimezone( ov_timezone() )->format( 'Y-m-d' ) );
	}
}
