<?php
/**
 * OptiVio — Deterministic search insights: GSC (cannibalization, striking distance, low CTR)
 * and GSC ↔ Google Ads ↔ calls correlations.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

use OptiVio\Core\Database;
use OptiVio\Services\Calls_Analytics;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Search insights.
 */
final class Search_Insights {

	/**
	 * Expected organic CTR by rounded position (conservative industry curve).
	 */
	private const CTR_CURVE = array(
		1 => 0.28,
		2 => 0.15,
		3 => 0.10,
		4 => 0.07,
		5 => 0.05,
	);

	/**
	 * GSC-only insights for a business (last 28 days).
	 *
	 * @param int $business_id Business.
	 * @return array<string,int> Count per type.
	 */
	public static function gsc( int $business_id ): array {
		$db    = Database::instance();
		$from  = gmdate( 'Y-m-d', time() - 28 * 86400 );
		$sites = array_map( 'intval', $db->get_col( 'SELECT id FROM sites WHERE business_id = ?', array( $business_id ) ) );
		$count = array(
			'cannibalization'       => 0,
			'striking_distance'     => 0,
			'low_ctr_high_position' => 0,
		);
		if ( empty( $sites ) ) {
			return $count;
		}
		$in = Database::in_placeholders( $sites );

		// Cannibalization: one query, ≥ 2 pages each with ≥ 10% of the query impressions.
		$pages = $db->get_results(
			"SELECT site_id, query_hash, MAX(query) query, page, SUM(clicks) clicks, SUM(impressions) impressions,
				SUM(position * impressions) / NULLIF(SUM(impressions), 0) position
			 FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY site_id, query_hash, page_hash, page",
			array_merge( $sites, array( $from ) )
		);
		$by_query = array();
		foreach ( $pages as $p ) {
			$by_query[ $p['site_id'] . '|' . $p['query_hash'] ][] = $p;
		}
		foreach ( $by_query as $group ) {
			$total = array_sum( array_column( $group, 'impressions' ) );
			if ( $total < 30 ) {
				continue;
			}
			$strong = array_values(
				array_filter(
					$group,
					static function ( array $p ) use ( $total ): bool {
						return (int) $p['impressions'] >= 0.1 * $total;
					}
				)
			);
			if ( count( $strong ) >= 2 ) {
				usort(
					$strong,
					static function ( array $a, array $b ): int {
						return (int) $b['impressions'] <=> (int) $a['impressions'];
					}
				);
				Insight_Store::put(
					$business_id,
					(int) $group[0]['site_id'],
					'cannibalization',
					$group[0]['site_id'] . '|' . $group[0]['query_hash'],
					(string) $group[0]['query'],
					(float) $total,
					array(
						'impressions' => (int) $total,
						'pages'       => array_map(
							static function ( array $p ): array {
								return array(
									'page'        => (string) $p['page'],
									'impressions' => (int) $p['impressions'],
									'clicks'      => (int) $p['clicks'],
									'position'    => round( (float) $p['position'], 1 ),
								);
							},
							array_slice( $strong, 0, 5 )
						),
						'action'      => 'Alegeți o pagină principală pentru interogare; diferențiați sau consolidați celelalte și legați-le intern către ea.',
					)
				);
				++$count['cannibalization'];
			}
		}

		// Query level: striking distance + low CTR at good position.
		$queries = $db->get_results(
			"SELECT site_id, query_hash, MAX(query) query, SUM(clicks) clicks, SUM(impressions) impressions,
				SUM(position * impressions) / NULLIF(SUM(impressions), 0) position
			 FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY site_id, query_hash HAVING impressions >= 50",
			array_merge( $sites, array( $from ) )
		);
		foreach ( $queries as $q ) {
			$pos  = (float) $q['position'];
			$impr = (int) $q['impressions'];
			$ctr  = $impr > 0 ? (int) $q['clicks'] / $impr : 0;
			if ( $pos >= 4 && $pos <= 20 ) {
				$target_ctr = self::CTR_CURVE[3];
				Insight_Store::put(
					$business_id,
					(int) $q['site_id'],
					'striking_distance',
					$q['site_id'] . '|' . $q['query_hash'],
					(string) $q['query'],
					round( $impr * max( 0, $target_ctr - $ctr ), 1 ),
					array(
						'position'          => round( $pos, 1 ),
						'impressions'       => $impr,
						'clicks'            => (int) $q['clicks'],
						'potential_clicks'  => (int) round( $impr * $target_ctr ) - (int) $q['clicks'],
						'action'            => 'Optimizați pagina care rankează (titlu, H2 dedicat, conținut, linkuri interne) pentru a urca în top 3.',
					)
				);
				++$count['striking_distance'];
			}
			$rounded = (int) round( $pos );
			if ( $pos > 0 && $rounded <= 5 && $impr >= 100 && $ctr < 0.5 * self::CTR_CURVE[ max( 1, $rounded ) ] ) {
				Insight_Store::put(
					$business_id,
					(int) $q['site_id'],
					'low_ctr_high_position',
					$q['site_id'] . '|' . $q['query_hash'],
					(string) $q['query'],
					round( $impr * ( self::CTR_CURVE[ max( 1, $rounded ) ] - $ctr ), 1 ),
					array(
						'position'     => round( $pos, 1 ),
						'impressions'  => $impr,
						'ctr'          => round( 100 * $ctr, 2 ),
						'expected_ctr' => round( 100 * self::CTR_CURVE[ max( 1, $rounded ) ], 1 ),
						'action'       => 'Rescrieți titlul și meta descrierea (beneficiu, preț, urgență) — poziția e bună, dar fragmentul nu atrage click-uri.',
					)
				);
				++$count['low_ctr_high_position'];
			}
		}
		return $count;
	}

	/**
	 * Ads correlations (last 30 days): paid vs organic, converting terms without SEO page,
	 * wasted terms, keywords with rejected calls, bad hours.
	 *
	 * @param int $business_id Business.
	 * @return array<string,int>
	 */
	public static function ads( int $business_id ): array {
		$db    = Database::instance();
		$from  = gmdate( 'Y-m-d', time() - 30 * 86400 );
		$count = array_fill_keys( array( 'paid_while_top_organic', 'ads_converting_no_landing', 'ads_wasted_term', 'rejected_calls_keyword', 'ads_bad_hours' ), 0 );
		$sites = array_map( 'intval', $db->get_col( 'SELECT id FROM sites WHERE business_id = ?', array( $business_id ) ) );

		if ( ! empty( $sites ) ) {
			$in    = Database::in_placeholders( $sites );
			$terms = $db->get_results(
				"SELECT c.site_id, MAX(t.search_term) term, SUM(t.clicks) clicks, SUM(t.cost_micros) cost, SUM(t.conversions) conversions
				 FROM ads_search_terms t JOIN ads_campaigns c ON c.id = t.campaign_id
				 WHERE c.site_id IN ({$in}) AND t.stat_date >= ? GROUP BY c.site_id, t.term_hash",
				array_merge( $sites, array( $from ) )
			);
			$organic = array();
			foreach ( $db->get_results(
				"SELECT MAX(query) query, SUM(clicks) clicks, SUM(impressions) impressions, SUM(position * impressions) / NULLIF(SUM(impressions), 0) position
				 FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY query_hash",
				array_merge( $sites, array( $from ) )
			) as $q ) {
				$organic[ Text::key( (string) $q['query'] ) ] = $q;
			}
			$total_clicks = array_sum( array_column( $terms, 'clicks' ) );
			$total_cost   = array_sum( array_column( $terms, 'cost' ) );
			$avg_cpc      = $total_clicks > 0 ? $total_cost / $total_clicks : 0;

			foreach ( $terms as $t ) {
				$key  = Text::key( (string) $t['term'] );
				$cost = (int) $t['cost'] / 1000000;
				$org  = $organic[ $key ] ?? null;
				if ( null !== $org && (float) $org['position'] > 0 && (float) $org['position'] <= 3 && (int) $org['clicks'] > 0 && $cost > 0 ) {
					Insight_Store::put(
						$business_id,
						(int) $t['site_id'],
						'paid_while_top_organic',
						$key,
						(string) $t['term'],
						$cost,
						array(
							'ads_cost'         => round( $cost, 2 ),
							'ads_clicks'       => (int) $t['clicks'],
							'ads_conversions'  => (float) $t['conversions'],
							'organic_position' => round( (float) $org['position'], 1 ),
							'organic_clicks'   => (int) $org['clicks'],
							'action'           => 'Testați reducerea bid-ului sau excluderea termenului: rankați deja în top 3 organic. Mențineți-l doar dacă aduce apeluri acceptate suplimentare.',
						)
					);
					++$count['paid_while_top_organic'];
				}
				if ( (float) $t['conversions'] >= 1 && ( null === $org || (float) $org['position'] > 20 ) ) {
					Insight_Store::put(
						$business_id,
						(int) $t['site_id'],
						'ads_converting_no_landing',
						$key,
						(string) $t['term'],
						(float) $t['conversions'] * 10 + $cost,
						array(
							'ads_conversions'  => (float) $t['conversions'],
							'ads_cost'         => round( $cost, 2 ),
							'organic_position' => null !== $org ? round( (float) $org['position'], 1 ) : null,
							'action'           => 'Creați o pagină / landing page SEO dedicată acestui termen — convertește plătit, dar nu aveți prezență organică.',
						)
					);
					++$count['ads_converting_no_landing'];
				}
				if ( (float) $t['conversions'] <= 0 && $cost > 0 && ( $cost >= 50 || ( $avg_cpc > 0 && $cost >= 3 * $avg_cpc ) ) ) {
					Insight_Store::put(
						$business_id,
						(int) $t['site_id'],
						'ads_wasted_term',
						$key,
						(string) $t['term'],
						$cost,
						array(
							'ads_cost'   => round( $cost, 2 ),
							'ads_clicks' => (int) $t['clicks'],
							'action'     => 'Candidat pentru cuvânt cheie negativ (verificați că nu a adus apeluri acceptate).',
						)
					);
					++$count['ads_wasted_term'];
				}
			}

			// Bad hours enriched with Ads spend in those hours.
			$stats = new Calls_Analytics( array( $business_id ), 30 );
			foreach ( $stats->schedule_suggestions() as $h ) {
				$spend = (int) $db->get_var(
					"SELECT COALESCE(SUM(h.cost_micros), 0) FROM ads_hourly h JOIN ads_campaigns c ON c.id = h.campaign_id
					 WHERE c.site_id IN ({$in}) AND h.stat_date >= ? AND h.stat_hour = ?",
					array_merge( $sites, array( $from, (int) $h['hour'] ) )
				) / 1000000;
				Insight_Store::put(
					$business_id,
					null,
					'ads_bad_hours',
					(string) $h['hour'],
					sprintf( '%02d:00–%02d:59', $h['hour'], $h['hour'] ),
					$spend + $h['total'],
					array(
						'hour'      => (int) $h['hour'],
						'calls'     => (int) $h['total'],
						'bad_share' => (float) $h['bad_share'],
						'ads_spend' => round( $spend, 2 ),
						'action'    => 'Reduceți bid-ul (ajustare -50…-100%) sau opriți reclamele în acest interval din Ad Schedule.',
					)
				);
				++$count['ads_bad_hours'];
			}
		}

		foreach ( ( new Calls_Analytics( array( $business_id ), 90 ) )->by_keyword( 100 ) as $k ) {
			if ( ! empty( $k['flag'] ) && '(fără cuvânt cheie)' !== $k['label'] ) {
				Insight_Store::put(
					$business_id,
					null,
					'rejected_calls_keyword',
					Text::key( (string) $k['label'] ),
					(string) $k['label'],
					(float) $k['total'] * (float) $k['bad_share'] / 100,
					array(
						'calls'     => (int) $k['total'],
						'accepted'  => (int) $k['accepted'],
						'bad_share' => (float) $k['bad_share'],
						'revenue'   => (float) $k['revenue'],
						'action'    => 'Apelurile din acest cuvânt sunt majoritar refuzate/pierdute: adăugați-l ca negativ sau restrângeți potrivirea / zona.',
					)
				);
				++$count['rejected_calls_keyword'];
			}
		}
		return $count;
	}
}
