<?php
/**
 * OptiVio — Upsert store for keyword_insights (keeps user dismissals across recalculations).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Insight store.
 */
final class Insight_Store {

	/**
	 * Labels.
	 */
	public const LABELS = array(
		'cannibalization'           => 'Canibalizare',
		'striking_distance'         => 'Aproape de prima pagină',
		'low_ctr_high_position'     => 'CTR mic la poziție bună',
		'paid_while_top_organic'    => 'Plătim deși suntem top organic',
		'ads_converting_no_landing' => 'Convertește în Ads, fără pagină SEO',
		'ads_wasted_term'           => 'Termen plătit fără rezultat',
		'rejected_calls_keyword'    => 'Cuvânt cu apeluri refuzate',
		'ads_bad_hours'             => 'Ore cu apeluri refuzate',
		'other'                     => 'Altele',
	);

	/**
	 * Saves an insight (insert or refresh). Dismissed insights stay dismissed.
	 *
	 * @param int      $business_id Business.
	 * @param int|null $site_id     Site.
	 * @param string   $type        Type.
	 * @param string   $key         Identity key within the type (e.g. normalized query).
	 * @param string   $keyword     Display keyword.
	 * @param float    $score       Score (sort).
	 * @param array    $data        Details.
	 * @return void
	 */
	public static function put( int $business_id, ?int $site_id, string $type, string $key, string $keyword, float $score, array $data ): void {
		Database::instance()->query(
			'INSERT INTO keyword_insights (business_id, site_id, insight_type, insight_hash, keyword, score, data, computed_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
			 ON DUPLICATE KEY UPDATE site_id = VALUES(site_id), keyword = VALUES(keyword), score = VALUES(score), data = VALUES(data), computed_at = UTC_TIMESTAMP()',
			array( $business_id, $site_id, $type, hash( 'sha256', $type . '|' . $key ), mb_substr( $keyword, 0, 190 ), round( $score, 2 ), json_encode( $data, JSON_UNESCAPED_UNICODE ) )
		);
	}

	/**
	 * Removes insights of the given types that were not refreshed since $since (no longer true).
	 *
	 * @param int    $business_id Business.
	 * @param array  $types       Types recomputed in this run.
	 * @param string $since       Run start (UTC).
	 * @return void
	 */
	public static function prune( int $business_id, array $types, string $since ): void {
		if ( empty( $types ) ) {
			return;
		}
		Database::instance()->query(
			'DELETE FROM keyword_insights WHERE business_id = ? AND computed_at < ? AND insight_type IN (' . Database::in_placeholders( $types ) . ')',
			array_merge( array( $business_id, $since ), $types )
		);
	}

	/**
	 * Active (not dismissed) insights of a business.
	 *
	 * @param int   $business_id Business.
	 * @param array $types       Types.
	 * @param int   $limit       Limit per call.
	 * @return array<int,array>
	 */
	public static function active( int $business_id, array $types, int $limit = 50 ): array {
		$rows = Database::instance()->get_results(
			'SELECT * FROM keyword_insights WHERE business_id = ? AND dismissed_at IS NULL AND insight_type IN (' . Database::in_placeholders( $types ) . ') ORDER BY score DESC LIMIT ?',
			array_merge( array( $business_id ), $types, array( $limit ) )
		);
		foreach ( $rows as &$row ) {
			$row['data'] = (array) ( json_decode( (string) $row['data'], true ) ?: array() );
		}
		unset( $row );
		return $rows;
	}
}
