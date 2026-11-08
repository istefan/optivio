<?php
/**
 * OptiVio — Deterministic multi-source snapshot of a business, sent to the AI for deep analysis.
 *
 * Bounded in size (top-N everywhere), contains no PII (no form fields, no caller numbers,
 * no visitor IPs) and no secrets. Stored verbatim in analyses.input_snapshot for audit.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Ai;

use OptiVio\Core\Database;
use OptiVio\Engine\Ads_Strategy;
use OptiVio\Engine\Competitors;
use OptiVio\Engine\Insight_Store;
use OptiVio\Repositories\Business_Children_Repository as Children;
use OptiVio\Services\Calls_Analytics;
use OptiVio\Services\Repuvio_Connector;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Snapshot builder.
 */
final class Snapshot_Builder {

	/**
	 * Builds the snapshot.
	 *
	 * @param int $business_id Business.
	 * @return array
	 */
	public static function build( int $business_id ): array {
		$db    = Database::instance();
		$b     = (array) $db->get_row( 'SELECT * FROM businesses WHERE id = ?', array( $business_id ) );
		$sites = $db->get_results( 'SELECT id, name, url, cms, connector_status FROM sites WHERE business_id = ? AND status = \'active\'', array( $business_id ) );
		$ids   = array_map( 'intval', array_column( $sites, 'id' ) );
		$in    = Database::in_placeholders( $ids );
		$d28   = gmdate( 'Y-m-d', time() - 28 * 86400 );
		$d30   = gmdate( 'Y-m-d', time() - 30 * 86400 );
		$calls = new Calls_Analytics( array( $business_id ), 30 );

		$snapshot = array(
			'generated_at' => gmdate( 'c' ),
			'business'     => array(
				'name'            => $b['name'],
				'model'           => $b['business_model'],
				'service_area'    => $b['service_area'],
				'description'     => mb_substr( (string) $b['description'], 0, 2000 ),
				'target_audience' => mb_substr( (string) $b['target_audience'], 0, 1000 ),
				'usp'             => $b['usp_text'],
				'avg_job_value'   => $b['avg_job_value'],
				'currency'        => $b['currency'],
				'daily_capacity'  => $b['daily_capacity'],
				'offerings'       => array_map(
					static function ( array $o ): array {
						return array(
							'name'         => $o['name'],
							'type'         => $o['type'],
							'emergency'    => (bool) $o['is_emergency'],
							'recurring'    => (bool) $o['is_recurring'],
							'avg_value'    => $o['avg_ticket_value'],
							'margin_pct'   => $o['margin_pct'],
							'priority'     => (int) $o['priority'],
							'description'  => mb_substr( (string) $o['description'], 0, 300 ),
						);
					},
					array_filter( Children::all( 'business_offerings', $business_id ), static fn( $o ) => (bool) $o['is_active'] )
				),
			),
			'permanent_rules' => Children::active_rules( $business_id ),
			'sites'           => array_map(
				static function ( array $s ): array {
					return array(
						'site_id'   => (int) $s['id'],
						'url'       => $s['url'],
						'connector' => $s['connector_status'],
					);
				},
				$sites
			),
			'conversions_30d' => array(
				'calls'               => $calls->totals(),
				'calls_by_outcome'    => $calls->by_outcome(),
				'calls_by_keyword'    => array_slice( $calls->by_keyword( 15 ), 0, 15 ),
				'bad_hours'           => $calls->schedule_suggestions(),
				'phone_clicks'        => empty( $ids ) ? 0 : (int) $db->get_var( "SELECT COUNT(*) FROM phone_clicks WHERE site_id IN ({$in}) AND occurred_at >= ?", array_merge( $ids, array( $d30 ) ) ),
				'forms_clean'         => empty( $ids ) ? 0 : (int) $db->get_var( "SELECT COUNT(*) FROM form_entries WHERE site_id IN ({$in}) AND occurred_at >= ? AND spam_status = 'clean'", array_merge( $ids, array( $d30 ) ) ),
				'forms_spam'          => empty( $ids ) ? 0 : (int) $db->get_var( "SELECT COUNT(*) FROM form_entries WHERE site_id IN ({$in}) AND occurred_at >= ? AND spam_status = 'spam'", array_merge( $ids, array( $d30 ) ) ),
			),
			'reputation'      => Repuvio_Connector::snapshot( $business_id ),
		);

		if ( ! empty( $ids ) ) {
			$snapshot['search_console_28d'] = array(
				'top_queries' => $db->get_results( "SELECT MAX(query) query, SUM(clicks) clicks, SUM(impressions) impressions, ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) position FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY query_hash ORDER BY impressions DESC LIMIT 30", array_merge( $ids, array( $d28 ) ) ),
				'top_pages'   => $db->get_results( "SELECT MAX(page) page, SUM(clicks) clicks, SUM(impressions) impressions FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY page_hash ORDER BY clicks DESC LIMIT 15", array_merge( $ids, array( $d28 ) ) ),
			);
			$snapshot['analytics_28d']      = $db->get_results( "SELECT source, medium, SUM(sessions) sessions, SUM(conversions) conversions FROM ga4_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY source_medium_hash, source, medium ORDER BY sessions DESC LIMIT 10", array_merge( $ids, array( $d28 ) ) );
			$snapshot['pagespeed']          = $db->get_results( "SELECT site_id, strategy, performance_score, seo_score, lcp_ms, inp_ms, cls, opportunities FROM psi_audits WHERE id IN (SELECT MAX(id) FROM psi_audits WHERE site_id IN ({$in}) GROUP BY site_id)", $ids );
			$snapshot['google_ads_30d']     = array(
				'strategy'     => Ads_Strategy::recommend( $business_id ),
				'search_terms' => $db->get_results( "SELECT MAX(t.search_term) term, SUM(t.clicks) clicks, ROUND(SUM(t.cost_micros) / 1000000, 2) cost, SUM(t.conversions) conversions FROM ads_search_terms t JOIN ads_campaigns c ON c.id = t.campaign_id WHERE c.site_id IN ({$in}) AND t.stat_date >= ? GROUP BY t.term_hash ORDER BY cost DESC LIMIT 20", array_merge( $ids, array( $d30 ) ) ),
				'negatives'    => $db->get_col( "SELECT DISTINCT k.keyword_text FROM ads_keywords k JOIN ads_campaigns c ON c.id = k.campaign_id WHERE c.site_id IN ({$in}) AND k.is_negative = 1 LIMIT 50", $ids ),
			);
			$snapshot['business_profile_28d'] = $db->get_row( "SELECT SUM(impressions_maps) maps, SUM(impressions_search) search, SUM(call_clicks) calls, SUM(website_clicks) website, SUM(direction_requests) directions FROM gbp_daily WHERE site_id IN ({$in}) AND stat_date >= ?", array_merge( $ids, array( $d28 ) ) );
			$snapshot['content']            = $db->get_results(
				"SELECT c.id content_id, c.site_id, c.subtype, c.title, c.url, c.word_count, c.h1_count, c.internal_links_in, c.images_missing_alt,
					m.seo_title_resolved seo_title, m.meta_description_resolved meta_description, m.focus_keyword
				 FROM wp_content c LEFT JOIN wp_seo_meta m ON m.content_id = c.id
				 WHERE c.site_id IN ({$in}) AND c.status = 'publish' AND c.object_type = 'post' ORDER BY c.internal_links_in DESC, c.word_count DESC LIMIT 60",
				$ids
			);
			$snapshot['seo_audit']          = $db->get_results(
				"SELECT issue_code, severity, content_id, details FROM seo_audit_issues WHERE site_id IN ({$in}) AND resolved_at IS NULL ORDER BY FIELD(severity, 'critical', 'warning', 'info') LIMIT 40",
				$ids
			);
		}

		$snapshot['insights'] = array_map(
			static function ( array $i ): array {
				return array(
					'type'    => $i['insight_type'],
					'keyword' => $i['keyword'],
					'data'    => array_diff_key( (array) $i['data'], array( 'action' => 1 ) ),
				);
			},
			Insight_Store::active( $business_id, array_keys( Insight_Store::LABELS ), 40 )
		);
		$snapshot['keywords'] = $db->get_results( "SELECT keyword, intent, channel, gsc_impressions, gsc_position, ads_conversions, calls_total, calls_rejected, priority_score FROM keywords WHERE business_id = ? AND status <> 'excluded' ORDER BY priority_score DESC LIMIT 40", array( $business_id ) );

		$top_kw = $db->get_var( 'SELECT p.keyword FROM competitor_pages p JOIN competitors c ON c.id = p.competitor_id WHERE c.business_id = ? AND p.word_count IS NOT NULL GROUP BY p.keyword ORDER BY COUNT(*) DESC LIMIT 1', array( $business_id ) );
		if ( null !== $top_kw ) {
			$cmp                     = Competitors::compare( $business_id, (string) $top_kw, 0 );
			$snapshot['competitors'] = array(
				'keyword'      => $top_kw,
				'median_words' => $cmp['median_words'],
				'avg_h2'       => $cmp['avg_h2'],
				'pages'        => array_map(
					static function ( array $p ): array {
						return array(
							'domain'   => $p['domain'],
							'position' => $p['serp_position'],
							'title'    => $p['title'],
							'words'    => (int) $p['word_count'],
							'h2'       => array_slice( array_column( array_filter( $p['headings'], static fn( $h ) => 2 === (int) $h['level'] ), 'text' ), 0, 10 ),
						);
					},
					array_slice( $cmp['pages'], 0, 6 )
				),
				'schema'       => $cmp['schema_counts'],
			);
		}

		$snapshot['existing_cards'] = $db->get_results(
			"SELECT category, title, status, rejection_reason FROM action_cards WHERE business_id = ? AND status IN ('proposed','approved','applied','rejected') ORDER BY id DESC LIMIT 60",
			array( $business_id )
		);
		return $snapshot;
	}
}
