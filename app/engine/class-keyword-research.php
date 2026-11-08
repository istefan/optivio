<?php
/**
 * OptiVio — Deterministic keyword research: candidates from GSC, Google Ads, offerings × service
 * area, competitors; intent classification; metrics; priority score per business model.
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
 * Keyword research.
 */
final class Keyword_Research {

	/**
	 * Intent patterns (on normalized text), evaluated in order.
	 */
	private const INTENTS = array(
		'emergency'     => '/\b(urgent|urgenta|urgente|non ?stop|nonstop|24 ?(din|\/) ?24|24 ?7|acum|rapid|avarie|spart[ae]?|inundat|blocat|infundat|noaptea|weekend)\b/',
		'transactional' => '/\b(pret|preturi|tarif|tarife|cost|costuri|oferta|comanda|cumpar|cumpara|programare|rezervare|ieftin|reducere)\b/',
		'informational' => '/\b(cum|ce|de ce|cand|ghid|sfaturi|idei|tutorial|exemple|diferenta|care)\b/',
		'commercial'    => '/\b(cel mai bun|cea mai buna|recenzii|pareri|firma|firme|companie|top|vs|comparatie|autorizat)\b/',
	);

	/**
	 * Intent weights per business model (priority multiplier).
	 */
	private const WEIGHTS = array(
		'emergency_service' => array( 'emergency' => 3.0, 'local' => 2.2, 'transactional' => 1.8, 'commercial' => 1.3, 'informational' => 0.5, 'navigational' => 0.8, 'unknown' => 1.0 ),
		'ecommerce'         => array( 'emergency' => 1.0, 'local' => 1.0, 'transactional' => 3.0, 'commercial' => 2.2, 'informational' => 1.0, 'navigational' => 0.8, 'unknown' => 1.0 ),
		'publisher'         => array( 'emergency' => 0.8, 'local' => 0.8, 'transactional' => 0.8, 'commercial' => 1.5, 'informational' => 3.0, 'navigational' => 0.8, 'unknown' => 1.0 ),
		'default'           => array( 'emergency' => 1.8, 'local' => 2.0, 'transactional' => 2.2, 'commercial' => 1.8, 'informational' => 0.9, 'navigational' => 0.8, 'unknown' => 1.0 ),
	);

	public const INTENT_LABELS = array(
		'emergency'     => 'Urgență',
		'transactional' => 'Tranzacțional',
		'commercial'    => 'Comercial',
		'informational' => 'Informațional',
		'local'         => 'Local',
		'navigational'  => 'Brand',
		'unknown'       => 'Nedeterminat',
	);

	/**
	 * Runs research for a business. Returns candidates upserted.
	 *
	 * @param int $business_id Business.
	 * @return int
	 */
	public static function run( int $business_id ): int {
		$db       = Database::instance();
		$business = $db->get_row( 'SELECT name, business_model, service_area FROM businesses WHERE id = ?', array( $business_id ) );
		if ( null === $business ) {
			return 0;
		}
		$sites  = array_map( 'intval', $db->get_col( 'SELECT id FROM sites WHERE business_id = ?', array( $business_id ) ) );
		$areas  = self::areas( (string) $business['service_area'] );
		$brand  = Text::tokens( (string) $business['name'] );
		$cands  = array();
		$add    = static function ( string $kw, string $source ) use ( &$cands ): void {
			$norm = Text::normalize( $kw );
			if ( mb_strlen( $norm ) < 3 || mb_strlen( $norm ) > 120 || str_word_count( $norm ) > 8 ) {
				return;
			}
			$key = Text::key( $norm );
			if ( '' === $key ) {
				return;
			}
			if ( ! isset( $cands[ $key ] ) ) {
				$cands[ $key ] = array(
					'keyword' => $kw,
					'source'  => $source,
				);
			}
		};

		if ( ! empty( $sites ) ) {
			$in = Database::in_placeholders( $sites );
			foreach ( $db->get_col( "SELECT MAX(query) FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY query_hash HAVING SUM(impressions) >= 10 ORDER BY SUM(impressions) DESC LIMIT 300", array_merge( $sites, array( gmdate( 'Y-m-d', time() - 90 * 86400 ) ) ) ) as $q ) {
				$add( (string) $q, 'gsc' );
			}
			foreach ( $db->get_col( "SELECT k.keyword_text FROM ads_keywords k JOIN ads_campaigns c ON c.id = k.campaign_id WHERE c.site_id IN ({$in}) AND k.is_negative = 0", $sites ) as $q ) {
				$add( (string) $q, 'ads' );
			}
			foreach ( $db->get_col( "SELECT MAX(t.search_term) FROM ads_search_terms t JOIN ads_campaigns c ON c.id = t.campaign_id WHERE c.site_id IN ({$in}) GROUP BY t.term_hash HAVING SUM(t.conversions) > 0", $sites ) as $q ) {
				$add( (string) $q, 'ads' );
			}
		}

		$offerings = $db->get_results( 'SELECT name, is_emergency FROM business_offerings WHERE business_id = ? AND is_active = 1 ORDER BY priority LIMIT 15', array( $business_id ) );
		foreach ( $offerings as $o ) {
			$name = mb_strtolower( (string) $o['name'] );
			$add( $name, 'generated' );
			$add( $name . ' pret', 'generated' );
			if ( ! empty( $o['is_emergency'] ) ) {
				$add( $name . ' non stop', 'generated' );
				$add( $name . ' urgent', 'generated' );
			}
			foreach ( array_slice( $areas, 0, 5 ) as $area ) {
				$add( $name . ' ' . $area, 'generated' );
			}
		}

		foreach ( $db->get_col(
			'SELECT DISTINCT cp.keyword FROM competitor_pages cp JOIN competitors c ON c.id = cp.competitor_id WHERE c.business_id = ? AND cp.keyword IS NOT NULL',
			array( $business_id )
		) as $q ) {
			$add( (string) $q, 'serp' );
		}

		$model   = (string) $business['business_model'];
		$weights = self::WEIGHTS[ $model ] ?? self::WEIGHTS['default'];
		$metrics = self::metrics( $business_id, $sites );

		foreach ( $cands as $key => $c ) {
			$intent = self::intent( $c['keyword'], $areas, $brand );
			$m      = $metrics[ $key ] ?? array();
			$score  = $weights[ $intent ] * (
				log( 1 + (int) ( $m['gsc_impressions'] ?? 0 ) ) * 2
				+ log( 1 + (int) ( $m['gsc_clicks'] ?? 0 ) ) * 3
				+ (float) ( $m['ads_conversions'] ?? 0 ) * 5
				+ (int) ( $m['calls_total'] ?? 0 ) - 2 * (int) ( $m['calls_rejected'] ?? 0 )
				+ ( null !== ( $m['gsc_position'] ?? null ) && $m['gsc_position'] >= 4 && $m['gsc_position'] <= 20 ? 5 : 0 )
				+ ( 'generated' === $c['source'] ? 3 : 0 )
			);
			$channel = 'informational' === $intent || 'navigational' === $intent ? 'seo' : ( 'emergency' === $intent && 'emergency_service' === $model ? 'both' : 'both' );

			$db->query(
				'INSERT INTO keywords (business_id, keyword, keyword_hash, channel, intent, source, status, gsc_clicks, gsc_impressions, gsc_position, ads_clicks, ads_cost_micros,
					ads_conversions, calls_total, calls_rejected, priority_score, metrics_at)
				 VALUES (?, ?, ?, ?, ?, ?, \'proposed\', ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
				 ON DUPLICATE KEY UPDATE intent = VALUES(intent), gsc_clicks = VALUES(gsc_clicks), gsc_impressions = VALUES(gsc_impressions), gsc_position = VALUES(gsc_position),
					ads_clicks = VALUES(ads_clicks), ads_cost_micros = VALUES(ads_cost_micros), ads_conversions = VALUES(ads_conversions), calls_total = VALUES(calls_total),
					calls_rejected = VALUES(calls_rejected), priority_score = VALUES(priority_score), metrics_at = UTC_TIMESTAMP()',
				array(
					$business_id,
					mb_substr( $c['keyword'], 0, 190 ),
					hash( 'sha256', $key ),
					$channel,
					$intent,
					$c['source'],
					(int) ( $m['gsc_clicks'] ?? 0 ),
					(int) ( $m['gsc_impressions'] ?? 0 ),
					$m['gsc_position'] ?? null,
					(int) ( $m['ads_clicks'] ?? 0 ),
					(int) ( $m['ads_cost_micros'] ?? 0 ),
					(float) ( $m['ads_conversions'] ?? 0 ),
					(int) ( $m['calls_total'] ?? 0 ),
					(int) ( $m['calls_rejected'] ?? 0 ),
					round( max( 0, $score ), 2 ),
				)
			);
		}
		return count( $cands );
	}

	/**
	 * Adds a manual keyword.
	 *
	 * @param int    $business_id Business.
	 * @param string $keyword     Keyword.
	 * @param string $channel     seo|ads|both.
	 * @return bool
	 */
	public static function add_manual( int $business_id, string $keyword, string $channel ): bool {
		$key = Text::key( $keyword );
		if ( '' === $key ) {
			return false;
		}
		$business = Database::instance()->get_row( 'SELECT name, service_area FROM businesses WHERE id = ?', array( $business_id ) );
		Database::instance()->query(
			"INSERT INTO keywords (business_id, keyword, keyword_hash, channel, intent, source, status) VALUES (?, ?, ?, ?, ?, 'manual', 'tracked')
			 ON DUPLICATE KEY UPDATE status = 'tracked', channel = VALUES(channel)",
			array( $business_id, mb_substr( $keyword, 0, 190 ), hash( 'sha256', $key ), $channel, self::intent( $keyword, self::areas( (string) $business['service_area'] ), Text::tokens( (string) $business['name'] ) ) )
		);
		return true;
	}

	/**
	 * Classifies intent.
	 *
	 * @param string $keyword Keyword.
	 * @param array  $areas   Service area tokens.
	 * @param array  $brand   Brand tokens.
	 * @return string
	 */
	public static function intent( string $keyword, array $areas, array $brand ): string {
		$norm = Text::normalize( $keyword );
		foreach ( self::INTENTS as $intent => $pattern ) {
			if ( preg_match( $pattern, $norm ) ) {
				return $intent;
			}
		}
		$tokens = Text::tokens( $norm );
		if ( ! empty( $brand ) && count( array_intersect( $tokens, $brand ) ) >= max( 1, (int) ceil( count( $brand ) / 2 ) ) ) {
			return 'navigational';
		}
		foreach ( $areas as $area ) {
			if ( '' !== $area && str_contains( ' ' . $norm . ' ', ' ' . $area . ' ' ) ) {
				return 'local';
			}
		}
		if ( preg_match( '/\b(sector [1-6]|langa mine|aproape|zona)\b/', $norm ) ) {
			return 'local';
		}
		return 'unknown';
	}

	/**
	 * Service area tokens ("București, Ilfov (rază 30 km)" → ["bucuresti", "ilfov"]).
	 *
	 * @param string $area Area.
	 * @return array<int,string>
	 */
	private static function areas( string $area ): array {
		$out = array();
		foreach ( preg_split( '/[,;\/]|\bsi\b/', Text::normalize( (string) preg_replace( '/\(.*?\)/', '', $area ) ) ) as $part ) {
			$part = trim( (string) $part );
			if ( mb_strlen( $part ) >= 3 && ! ctype_digit( str_replace( ' ', '', $part ) ) ) {
				$out[] = $part;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Metrics per keyword key (GSC 28d, Ads 30d, calls 90d).
	 *
	 * @param int   $business_id Business.
	 * @param array $sites       Site ids.
	 * @return array<string,array>
	 */
	private static function metrics( int $business_id, array $sites ): array {
		$db  = Database::instance();
		$out = array();
		if ( ! empty( $sites ) ) {
			$in = Database::in_placeholders( $sites );
			foreach ( $db->get_results( "SELECT MAX(query) q, SUM(clicks) c, SUM(impressions) i, SUM(position * impressions) / NULLIF(SUM(impressions), 0) p FROM gsc_daily WHERE site_id IN ({$in}) AND stat_date >= ? GROUP BY query_hash", array_merge( $sites, array( gmdate( 'Y-m-d', time() - 28 * 86400 ) ) ) ) as $r ) {
				$k                          = Text::key( (string) $r['q'] );
				$out[ $k ]['gsc_clicks']      = ( $out[ $k ]['gsc_clicks'] ?? 0 ) + (int) $r['c'];
				$out[ $k ]['gsc_impressions'] = ( $out[ $k ]['gsc_impressions'] ?? 0 ) + (int) $r['i'];
				$out[ $k ]['gsc_position']    = null !== $r['p'] ? round( (float) $r['p'], 2 ) : null;
			}
			foreach ( $db->get_results( "SELECT MAX(t.search_term) q, SUM(t.clicks) c, SUM(t.cost_micros) m, SUM(t.conversions) v FROM ads_search_terms t JOIN ads_campaigns c ON c.id = t.campaign_id WHERE c.site_id IN ({$in}) AND t.stat_date >= ? GROUP BY t.term_hash", array_merge( $sites, array( gmdate( 'Y-m-d', time() - 30 * 86400 ) ) ) ) as $r ) {
				$k                          = Text::key( (string) $r['q'] );
				$out[ $k ]['ads_clicks']      = ( $out[ $k ]['ads_clicks'] ?? 0 ) + (int) $r['c'];
				$out[ $k ]['ads_cost_micros'] = ( $out[ $k ]['ads_cost_micros'] ?? 0 ) + (int) $r['m'];
				$out[ $k ]['ads_conversions'] = ( $out[ $k ]['ads_conversions'] ?? 0 ) + (float) $r['v'];
			}
		}
		foreach ( $db->get_results(
			"SELECT COALESCE(NULLIF(c.keyword_text, ''), pc.utm_term) q, COUNT(*) n, SUM(c.outcome LIKE 'rejected\\_%' OR c.outcome = 'missed') r
			 FROM calls c LEFT JOIN phone_clicks pc ON pc.id = c.phone_click_id WHERE c.business_id = ? AND c.call_time >= UTC_TIMESTAMP() - INTERVAL 90 DAY
			 GROUP BY q HAVING q IS NOT NULL",
			array( $business_id )
		) as $r ) {
			$k                         = Text::key( (string) $r['q'] );
			$out[ $k ]['calls_total']    = ( $out[ $k ]['calls_total'] ?? 0 ) + (int) $r['n'];
			$out[ $k ]['calls_rejected'] = ( $out[ $k ]['calls_rejected'] ?? 0 ) + (int) $r['r'];
		}
		return $out;
	}
}
