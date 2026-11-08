<?php
/**
 * OptiVio — Incremental, resumable sync jobs for Google data (zero AI).
 *
 * One job type "google.sync" with payload {site_id, provider}. Each run performs ONE API page
 * and returns a cursor while more pages remain (Job_Queue re-queues it), so long backfills
 * never exceed the shared-hosting time budget.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use DateTimeImmutable;
use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;
use RuntimeException;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Google sync handlers.
 */
final class Google_Sync {

	/**
	 * Providers needing an OAuth account.
	 */
	public const OAUTH_PROVIDERS = array( 'gsc', 'ga4', 'gtm', 'ads', 'adsense', 'gbp' );

	/**
	 * Days re-fetched on incremental runs (late data corrections).
	 */
	private const REFRESH_DAYS = array(
		'gsc'     => 5,
		'ga4'     => 3,
		'ads'     => 7,
		'adsense' => 3,
		'gbp'     => 7,
	);

	/**
	 * Enqueues due sync jobs (daily; PSI and GTM weekly). Called from the cron tick.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		$rows = Database::instance()->get_results(
			"SELECT si.site_id, si.provider, s.business_id FROM site_integrations si JOIN sites s ON s.id = si.site_id
			 WHERE si.status = 'active' AND s.status = 'active' AND si.external_id IS NOT NULL
				AND (si.provider = 'psi' OR si.credential_id IS NOT NULL)"
		);
		foreach ( $rows as $row ) {
			$weekly = in_array( $row['provider'], array( 'psi', 'gtm' ), true );
			self::dispatch( (int) $row['site_id'], (string) $row['provider'], (int) $row['business_id'], $weekly ? gmdate( 'o-W' ) : gmdate( 'Y-m-d' ) );
		}
	}

	/**
	 * Dispatches one sync job.
	 *
	 * @param int      $site_id     Site.
	 * @param string   $provider    Provider.
	 * @param int      $business_id Business.
	 * @param string   $period      Dedup period key.
	 * @param int|null $user_id     User (manual run).
	 * @return int Job id.
	 */
	public static function dispatch( int $site_id, string $provider, int $business_id, string $period, ?int $user_id = null ): int {
		return Job_Queue::dispatch(
			'google.sync',
			array(
				'site_id'  => $site_id,
				'provider' => $provider,
			),
			array(
				'unique'       => $provider . ':' . $site_id . ':' . $period,
				'business_id'  => $business_id,
				'site_id'      => $site_id,
				'priority'     => null !== $user_id ? 2 : 6,
				'max_attempts' => 4,
				'created_by'   => $user_id,
			)
		);
	}

	/**
	 * Job handler.
	 *
	 * @param array     $job   Job (payload, cursor_state).
	 * @param Job_Queue $queue Queue.
	 * @return array|null Cursor or null when finished.
	 * @throws Throwable Re-thrown after recording last_error.
	 */
	public static function handle( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$site_id     = (int) ( $job['payload']['site_id'] ?? 0 );
		$provider    = (string) ( $job['payload']['provider'] ?? '' );
		$integration = Database::instance()->get_row(
			'SELECT si.*, s.url AS site_url, s.domain FROM site_integrations si JOIN sites s ON s.id = si.site_id WHERE si.site_id = ? AND si.provider = ?',
			array( $site_id, $provider )
		);
		if ( null === $integration || 'active' !== $integration['status'] || null === $integration['external_id'] ) {
			return null;
		}

		try {
			$client = null;
			if ( in_array( $provider, self::OAUTH_PROVIDERS, true ) ) {
				if ( null === $integration['credential_id'] ) {
					throw new RuntimeException( 'Nu este asociat niciun cont Google.' );
				}
				$client = new Google_Client( (int) $integration['credential_id'] );
			}
			$cursor = (array) $job['cursor_state'];
			$result = match ( $provider ) {
				'gsc'     => self::gsc( $integration, $client, $cursor ),
				'ga4'     => self::ga4( $integration, $client, $cursor ),
				'psi'     => self::psi( $integration ),
				'gtm'     => self::gtm( $integration, $client ),
				'ads'     => self::ads( $integration, $client, $cursor ),
				'adsense' => self::adsense( $integration, $client, $cursor ),
				'gbp'     => self::gbp( $integration, $client, $cursor ),
				default   => null,
			};
			if ( null === $result ) {
				Database::instance()->query(
					'UPDATE site_integrations SET last_sync_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = ?',
					array( (int) $integration['id'] )
				);
			}
			return $result;
		} catch ( Throwable $e ) {
			Database::instance()->query(
				'UPDATE site_integrations SET last_error = ? WHERE id = ?',
				array( mb_substr( $e->getMessage(), 0, 500 ), (int) $integration['id'] )
			);
			throw $e;
		}
	}

	/**
	 * Search Console: date × query × page.
	 *
	 * @param array         $i      Integration.
	 * @param Google_Client $c      Client.
	 * @param array         $cursor Cursor.
	 * @return array|null
	 */
	private static function gsc( array $i, Google_Client $c, array $cursor ): ?array {
		[ $start, $end ] = $cursor ? array( $cursor['start'], $cursor['end'] ) : self::range( $i, 'gsc', (int) Settings_Repository::get( 'integrations.gsc_backfill_days', 90 ) );
		$row             = (int) ( $cursor['row'] ?? 0 );
		$limit           = 25000;
		$body            = $c->request(
			'POST',
			'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( (string) $i['external_id'] ) . '/searchAnalytics/query',
			array(),
			array(
				'startDate'  => $start,
				'endDate'    => $end,
				'dimensions' => array( 'date', 'query', 'page' ),
				'rowLimit'   => $limit,
				'startRow'   => $row,
				'dataState'  => 'all',
			)
		)['body'];

		$rows = array();
		foreach ( (array) ( $body['rows'] ?? array() ) as $r ) {
			[ $date, $query, $page ] = array_pad( (array) $r['keys'], 3, '' );
			$rows[]                  = array(
				(int) $i['site_id'],
				$date,
				hash( 'sha256', mb_strtolower( (string) $query ) ),
				hash( 'sha256', (string) $page ),
				'all',
				mb_substr( (string) $query, 0, 500 ),
				mb_substr( (string) $page, 0, 500 ),
				(int) $r['clicks'],
				(int) $r['impressions'],
				round( (float) $r['position'], 2 ),
			);
		}
		Database::instance()->upsert_many(
			'gsc_daily',
			array( 'site_id', 'stat_date', 'query_hash', 'page_hash', 'device', 'query', 'page', 'clicks', 'impressions', 'position' ),
			$rows,
			array( 'clicks', 'impressions', 'position' )
		);

		return count( $rows ) >= $limit ? array(
			'start'    => $start,
			'end'      => $end,
			'row'      => $row + $limit,
			'progress' => 50,
		) : null;
	}

	/**
	 * GA4: date × page × source/medium.
	 *
	 * @param array         $i      Integration.
	 * @param Google_Client $c      Client.
	 * @param array         $cursor Cursor.
	 * @return array|null
	 */
	private static function ga4( array $i, Google_Client $c, array $cursor ): ?array {
		[ $start, $end ] = $cursor ? array( $cursor['start'], $cursor['end'] ) : self::range( $i, 'ga4', 90 );
		$offset          = (int) ( $cursor['offset'] ?? 0 );
		$limit           = 10000;
		$metrics         = array( 'sessions', 'totalUsers', 'engagedSessions', 'userEngagementDuration', 'keyEvents', 'totalRevenue' );
		$body            = $c->request(
			'POST',
			'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( (string) $i['external_id'] ) . ':runReport',
			array(),
			array(
				'dateRanges' => array(
					array(
						'startDate' => $start,
						'endDate'   => $end,
					),
				),
				'dimensions' => array_map(
					static function ( string $n ): array {
						return array( 'name' => $n );
					},
					array( 'date', 'pagePath', 'sessionSource', 'sessionMedium' )
				),
				'metrics'    => array_map(
					static function ( string $n ): array {
						return array( 'name' => $n );
					},
					$metrics
				),
				'limit'      => $limit,
				'offset'     => $offset,
			)
		)['body'];

		$rows = array();
		foreach ( (array) ( $body['rows'] ?? array() ) as $r ) {
			$d        = array_column( (array) $r['dimensionValues'], 'value' );
			$m        = array_map( 'floatval', array_column( (array) $r['metricValues'], 'value' ) );
			$date     = substr( (string) $d[0], 0, 4 ) . '-' . substr( (string) $d[0], 4, 2 ) . '-' . substr( (string) $d[0], 6, 2 );
			$sessions = (int) $m[0];
			$rows[]   = array(
				(int) $i['site_id'],
				$date,
				hash( 'sha256', (string) $d[1] ),
				hash( 'sha256', $d[2] . '|' . $d[3] ),
				mb_substr( (string) $d[1], 0, 500 ),
				mb_substr( (string) $d[2], 0, 120 ),
				mb_substr( (string) $d[3], 0, 120 ),
				$sessions,
				(int) $m[1],
				(int) $m[2],
				$sessions > 0 ? round( $m[3] / $sessions, 2 ) : 0,
				round( $m[4], 2 ),
				round( $m[5], 2 ),
			);
		}
		Database::instance()->upsert_many(
			'ga4_daily',
			array( 'site_id', 'stat_date', 'page_hash', 'source_medium_hash', 'page_path', 'source', 'medium', 'sessions', 'users', 'engaged_sessions', 'avg_engagement_sec', 'conversions', 'revenue' ),
			$rows,
			array( 'sessions', 'users', 'engaged_sessions', 'avg_engagement_sec', 'conversions', 'revenue' )
		);

		$total = (int) ( $body['rowCount'] ?? 0 );
		return $offset + $limit < $total ? array(
			'start'    => $start,
			'end'      => $end,
			'offset'   => $offset + $limit,
			'progress' => (int) min( 99, 100 * ( $offset + $limit ) / max( 1, $total ) ),
		) : null;
	}

	/**
	 * PageSpeed Insights (no OAuth; optional API key).
	 *
	 * @param array $i Integration.
	 * @return null
	 * @throws RuntimeException On API error.
	 */
	private static function psi( array $i ) {
		$strategy = (string) Settings_Repository::get( 'integrations.psi_strategy', 'mobile' );
		$query    = array(
			'url'      => (string) $i['external_id'],
			'strategy' => strtoupper( $strategy ),
			'category' => array( 'PERFORMANCE', 'SEO', 'ACCESSIBILITY', 'BEST_PRACTICES' ),
		);
		$key      = Credentials_Repository::reveal_global( 'pagespeed' );
		if ( null !== $key ) {
			$query['key'] = $key;
		}
		$response = Google_Http::request( 'GET', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed', $query );
		if ( 200 !== $response['code'] || ! is_array( $response['body'] ) ) {
			throw new RuntimeException( 'PageSpeed ' . $response['code'] . ': ' . (string) ( $response['body']['error']['message'] ?? $response['error'] ) );
		}
		$lh     = (array) ( $response['body']['lighthouseResult'] ?? array() );
		$cats   = (array) ( $lh['categories'] ?? array() );
		$audits = (array) ( $lh['audits'] ?? array() );
		$score  = static function ( string $key ) use ( $cats ): ?int {
			return isset( $cats[ $key ]['score'] ) ? (int) round( 100 * (float) $cats[ $key ]['score'] ) : null;
		};
		$num    = static function ( string $key ) use ( $audits ): ?float {
			return isset( $audits[ $key ]['numericValue'] ) ? (float) $audits[ $key ]['numericValue'] : null;
		};

		$opps = array();
		foreach ( $audits as $id => $audit ) {
			if ( 'opportunity' === ( $audit['details']['type'] ?? '' ) && isset( $audit['score'] ) && (float) $audit['score'] < 0.9 ) {
				$opps[] = array(
					'id'         => (string) $id,
					'title'      => mb_substr( (string) ( $audit['title'] ?? $id ), 0, 200 ),
					'savings_ms' => (int) ( $audit['details']['overallSavingsMs'] ?? 0 ),
				);
			}
		}
		usort(
			$opps,
			static function ( array $a, array $b ): int {
				return $b['savings_ms'] <=> $a['savings_ms'];
			}
		);
		$inp = $response['body']['loadingExperience']['metrics']['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null;

		Database::instance()->insert(
			'psi_audits',
			array(
				'site_id'              => (int) $i['site_id'],
				'url'                  => mb_substr( (string) $i['external_id'], 0, 500 ),
				'strategy'             => 'desktop' === $strategy ? 'desktop' : 'mobile',
				'performance_score'    => $score( 'performance' ),
				'seo_score'            => $score( 'seo' ),
				'accessibility_score'  => $score( 'accessibility' ),
				'best_practices_score' => $score( 'best-practices' ),
				'lcp_ms'               => null !== $num( 'largest-contentful-paint' ) ? (int) $num( 'largest-contentful-paint' ) : null,
				'inp_ms'               => null !== $inp ? (int) $inp : null,
				'cls'                  => null !== $num( 'cumulative-layout-shift' ) ? round( (float) $num( 'cumulative-layout-shift' ), 3 ) : null,
				'fcp_ms'               => null !== $num( 'first-contentful-paint' ) ? (int) $num( 'first-contentful-paint' ) : null,
				'ttfb_ms'              => null !== $num( 'server-response-time' ) ? (int) $num( 'server-response-time' ) : null,
				'tbt_ms'               => null !== $num( 'total-blocking-time' ) ? (int) $num( 'total-blocking-time' ) : null,
				'opportunities'        => json_encode( array_slice( $opps, 0, 8 ), JSON_UNESCAPED_UNICODE ),
			)
		);
		return null;
	}

	/**
	 * GTM: live version snapshot.
	 *
	 * @param array         $i Integration.
	 * @param Google_Client $c Client.
	 * @return null
	 * @throws RuntimeException When the container is not accessible.
	 */
	private static function gtm( array $i, Google_Client $c ) {
		$settings = (array) ( json_decode( (string) $i['settings'], true ) ?: array() );
		$path     = (string) ( $settings['container_path'] ?? '' );
		if ( '' === $path ) {
			$accounts = $c->request( 'GET', 'https://tagmanager.googleapis.com/tagmanager/v2/accounts' )['body'];
			foreach ( (array) ( $accounts['account'] ?? array() ) as $a ) {
				$containers = $c->request( 'GET', 'https://tagmanager.googleapis.com/tagmanager/v2/' . $a['path'] . '/containers' )['body'];
				foreach ( (array) ( $containers['container'] ?? array() ) as $ct ) {
					if ( strtoupper( (string) $ct['publicId'] ) === strtoupper( (string) $i['external_id'] ) ) {
						$path = (string) $ct['path'];
						break 2;
					}
				}
			}
			if ( '' === $path ) {
				throw new RuntimeException( 'Containerul ' . $i['external_id'] . ' nu este accesibil contului Google.' );
			}
			$settings['container_path'] = $path;
			Database::instance()->update( 'site_integrations', array( 'settings' => json_encode( $settings ) ), array( 'id' => (int) $i['id'] ) );
		}

		$version = (array) $c->request( 'GET', 'https://tagmanager.googleapis.com/tagmanager/v2/' . $path . '/versions:live' )['body'];
		$types   = array();
		foreach ( (array) ( $version['tag'] ?? array() ) as $tag ) {
			$type           = (string) ( $tag['type'] ?? 'unknown' );
			$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
		}
		Database::instance()->insert(
			'gtm_snapshots',
			array(
				'site_id'      => (int) $i['site_id'],
				'container_id' => (string) $i['external_id'],
				'version_id'   => (string) ( $version['containerVersionId'] ?? '' ),
				'tags_count'   => count( (array) ( $version['tag'] ?? array() ) ),
				'summary'      => json_encode(
					array(
						'tag_types'      => $types,
						'triggers'       => count( (array) ( $version['trigger'] ?? array() ) ),
						'variables'      => count( (array) ( $version['variable'] ?? array() ) ),
						'has_ga4'        => isset( $types['gaawc'] ) || isset( $types['googtag'] ),
						'has_ads_conv'   => isset( $types['awct'] ),
						'has_ads_remark' => isset( $types['sp'] ),
					)
				),
			)
		);
		return null;
	}

	/**
	 * Google Ads: campaigns → keywords → negatives → hourly → search terms (one GAQL page per run).
	 *
	 * @param array         $i      Integration.
	 * @param Google_Client $c      Client.
	 * @param array         $cursor Cursor.
	 * @return array|null
	 */
	private static function ads( array $i, Google_Client $c, array $cursor ): ?array {
		$phases = array( 'campaigns', 'keywords', 'negatives', 'hourly', 'terms' );
		if ( empty( $cursor ) ) {
			[ $start, $end ] = self::range( $i, 'ads', 90 );
			$cursor          = array(
				'phase' => 'campaigns',
				'start' => $start,
				'end'   => $end,
				'token' => null,
			);
		}
		$site_id = (int) $i['site_id'];
		$db      = Database::instance();
		$range   = "segments.date BETWEEN '" . $cursor['start'] . "' AND '" . $cursor['end'] . "'";
		$gaql    = array(
			'campaigns' => "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign.bidding_strategy_type, campaign_budget.amount_micros FROM campaign WHERE campaign.status != 'REMOVED'",
			'keywords'  => "SELECT campaign.id, ad_group.id, ad_group.name, ad_group_criterion.criterion_id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, ad_group_criterion.negative, ad_group_criterion.status, ad_group_criterion.quality_info.quality_score, ad_group_criterion.cpc_bid_micros FROM keyword_view WHERE ad_group_criterion.status != 'REMOVED'",
			'negatives' => "SELECT campaign.id, campaign_criterion.criterion_id, campaign_criterion.keyword.text, campaign_criterion.keyword.match_type FROM campaign_criterion WHERE campaign_criterion.type = 'KEYWORD' AND campaign_criterion.negative = TRUE",
			'hourly'    => 'SELECT campaign.id, segments.date, segments.hour, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM campaign WHERE ' . $range,
			'terms'     => 'SELECT campaign.id, segments.date, search_term_view.search_term, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions FROM search_term_view WHERE ' . $range,
		);
		$phase   = (string) $cursor['phase'];
		$request = array( 'query' => $gaql[ $phase ] );
		if ( ! empty( $cursor['token'] ) ) {
			$request['pageToken'] = $cursor['token'];
		}
		$version = (string) Settings_Repository::get( 'integrations.ads_api_version', 'v21' );
		$body    = (array) $c->request( 'POST', 'https://googleads.googleapis.com/' . $version . '/customers/' . $i['external_id'] . '/googleAds:search', array(), $request, true )['body'];
		$results = (array) ( $body['results'] ?? array() );

		$campaign_map = static function () use ( $db, $site_id ): array {
			$map = array();
			foreach ( $db->get_results( 'SELECT id, external_id FROM ads_campaigns WHERE site_id = ?', array( $site_id ) ) as $row ) {
				$map[ (string) $row['external_id'] ] = (int) $row['id'];
			}
			return $map;
		};
		$match        = static function ( string $type ): string {
			return in_array( strtolower( $type ), array( 'exact', 'phrase', 'broad' ), true ) ? strtolower( $type ) : 'broad';
		};

		if ( 'campaigns' === $phase ) {
			$rows = array();
			foreach ( $results as $r ) {
				$rows[] = array(
					$site_id,
					(string) $r['campaign']['id'],
					mb_substr( (string) $r['campaign']['name'], 0, 255 ),
					(string) $r['campaign']['status'],
					(string) ( $r['campaign']['advertisingChannelType'] ?? '' ),
					(string) ( $r['campaign']['biddingStrategyType'] ?? '' ),
					isset( $r['campaignBudget']['amountMicros'] ) ? (int) $r['campaignBudget']['amountMicros'] : null,
					gmdate( 'Y-m-d H:i:s' ),
				);
			}
			$db->upsert_many( 'ads_campaigns', array( 'site_id', 'external_id', 'name', 'status', 'channel_type', 'bidding_strategy', 'daily_budget_micros', 'synced_at' ), $rows, array( 'name', 'status', 'channel_type', 'bidding_strategy', 'daily_budget_micros', 'synced_at' ) );
		} elseif ( 'keywords' === $phase || 'negatives' === $phase ) {
			$map = $campaign_map();
			if ( 'keywords' === $phase && empty( $cursor['token'] ) && ! empty( $map ) ) {
				$db->query( 'DELETE FROM ads_keywords WHERE campaign_id IN (' . Database::in_placeholders( array_values( $map ) ) . ')', array_values( $map ) );
			}
			foreach ( $results as $r ) {
				$cid = $map[ (string) $r['campaign']['id'] ] ?? null;
				if ( null === $cid ) {
					continue;
				}
				$crit = 'keywords' === $phase ? (array) $r['adGroupCriterion'] : (array) $r['campaignCriterion'];
				$db->insert(
					'ads_keywords',
					array(
						'campaign_id'    => $cid,
						'ad_group_id'    => isset( $r['adGroup']['id'] ) ? (string) $r['adGroup']['id'] : null,
						'ad_group_name'  => isset( $r['adGroup']['name'] ) ? mb_substr( (string) $r['adGroup']['name'], 0, 255 ) : null,
						'external_id'    => (string) ( $crit['criterionId'] ?? '' ),
						'keyword_text'   => mb_substr( (string) ( $crit['keyword']['text'] ?? '' ), 0, 190 ),
						'match_type'     => $match( (string) ( $crit['keyword']['matchType'] ?? 'BROAD' ) ),
						'is_negative'    => 'negatives' === $phase || ! empty( $crit['negative'] ) ? 1 : 0,
						'status'         => mb_substr( (string) ( $crit['status'] ?? 'ENABLED' ), 0, 20 ),
						'cpc_bid_micros' => isset( $crit['cpcBidMicros'] ) ? (int) $crit['cpcBidMicros'] : null,
						'quality_score'  => isset( $crit['qualityInfo']['qualityScore'] ) ? (int) $crit['qualityInfo']['qualityScore'] : null,
					)
				);
			}
		} elseif ( 'hourly' === $phase ) {
			$map  = $campaign_map();
			$rows = array();
			foreach ( $results as $r ) {
				$cid = $map[ (string) $r['campaign']['id'] ] ?? null;
				if ( null !== $cid ) {
					$m      = (array) ( $r['metrics'] ?? array() );
					$rows[] = array( $cid, (string) $r['segments']['date'], (int) $r['segments']['hour'], (int) ( $m['impressions'] ?? 0 ), (int) ( $m['clicks'] ?? 0 ), (int) ( $m['costMicros'] ?? 0 ), round( (float) ( $m['conversions'] ?? 0 ), 2 ), round( (float) ( $m['conversionsValue'] ?? 0 ), 2 ) );
				}
			}
			$db->upsert_many( 'ads_hourly', array( 'campaign_id', 'stat_date', 'stat_hour', 'impressions', 'clicks', 'cost_micros', 'conversions', 'conv_value' ), $rows, array( 'impressions', 'clicks', 'cost_micros', 'conversions', 'conv_value' ) );
		} else {
			$map  = $campaign_map();
			$rows = array();
			foreach ( $results as $r ) {
				$cid = $map[ (string) $r['campaign']['id'] ] ?? null;
				if ( null !== $cid ) {
					$term   = mb_substr( (string) $r['searchTermView']['searchTerm'], 0, 500 );
					$m      = (array) ( $r['metrics'] ?? array() );
					$rows[] = array( $cid, (string) $r['segments']['date'], hash( 'sha256', mb_strtolower( $term ) ), $term, (int) ( $m['impressions'] ?? 0 ), (int) ( $m['clicks'] ?? 0 ), (int) ( $m['costMicros'] ?? 0 ), round( (float) ( $m['conversions'] ?? 0 ), 2 ) );
				}
			}
			$db->upsert_many( 'ads_search_terms', array( 'campaign_id', 'stat_date', 'term_hash', 'search_term', 'impressions', 'clicks', 'cost_micros', 'conversions' ), $rows, array( 'impressions', 'clicks', 'cost_micros', 'conversions' ) );
		}

		if ( ! empty( $body['nextPageToken'] ) ) {
			$cursor['token'] = (string) $body['nextPageToken'];
			return $cursor;
		}
		$next = array_search( $phase, $phases, true ) + 1;
		if ( $next >= count( $phases ) ) {
			return null;
		}
		$cursor['phase']    = $phases[ $next ];
		$cursor['token']    = null;
		$cursor['progress'] = (int) ( 100 * $next / count( $phases ) );
		return $cursor;
	}

	/**
	 * AdSense: date × domain (filtered on the site domain).
	 *
	 * @param array         $i      Integration.
	 * @param Google_Client $c      Client.
	 * @param array         $cursor Cursor.
	 * @return null
	 */
	private static function adsense( array $i, Google_Client $c, array $cursor ) {
		[ $start, $end ] = $cursor ? array( $cursor['start'], $cursor['end'] ) : self::range( $i, 'adsense', 90 );
		[ $sy, $sm, $sd ] = array_map( 'intval', explode( '-', $start ) );
		[ $ey, $em, $ed ] = array_map( 'intval', explode( '-', $end ) );
		$body             = (array) $c->request(
			'GET',
			'https://adsense.googleapis.com/v2/accounts/' . rawurlencode( (string) $i['external_id'] ) . '/reports:generate',
			array(
				'dateRange'       => 'CUSTOM',
				'startDate.year'  => $sy,
				'startDate.month' => $sm,
				'startDate.day'   => $sd,
				'endDate.year'    => $ey,
				'endDate.month'   => $em,
				'endDate.day'     => $ed,
				'dimensions'      => array( 'DATE', 'DOMAIN_NAME' ),
				'metrics'         => array( 'PAGE_VIEWS', 'IMPRESSIONS', 'CLICKS', 'ESTIMATED_EARNINGS' ),
				'filters'         => 'DOMAIN_NAME==' . preg_replace( '/^www\./', '', (string) $i['domain'] ),
			)
		)['body'];

		$rows = array();
		foreach ( (array) ( $body['rows'] ?? array() ) as $r ) {
			$v      = array_column( (array) $r['cells'], 'value' );
			$rows[] = array( (int) $i['site_id'], (string) $v[0], hash( 'sha256', (string) $v[1] ), mb_substr( (string) $v[1], 0, 500 ), (int) $v[2], (int) $v[3], (int) $v[4], (int) round( (float) $v[5] * 1000000 ) );
		}
		Database::instance()->upsert_many( 'adsense_daily', array( 'site_id', 'stat_date', 'page_hash', 'page_url', 'page_views', 'impressions', 'clicks', 'earnings_micros' ), $rows, array( 'page_views', 'impressions', 'clicks', 'earnings_micros' ) );
		return null;
	}

	/**
	 * Business Profile Performance: daily metrics.
	 *
	 * @param array         $i      Integration.
	 * @param Google_Client $c      Client.
	 * @param array         $cursor Cursor.
	 * @return null
	 */
	private static function gbp( array $i, Google_Client $c, array $cursor ) {
		[ $start, $end ] = $cursor ? array( $cursor['start'], $cursor['end'] ) : self::range( $i, 'gbp', 90 );
		[ $sy, $sm, $sd ] = array_map( 'intval', explode( '-', $start ) );
		[ $ey, $em, $ed ] = array_map( 'intval', explode( '-', $end ) );
		$location         = (string) preg_replace( '#^accounts/[^/]+/#', '', (string) $i['external_id'] );
		$map              = array(
			'CALL_CLICKS'                        => 'call_clicks',
			'WEBSITE_CLICKS'                     => 'website_clicks',
			'BUSINESS_DIRECTION_REQUESTS'        => 'direction_requests',
			'BUSINESS_IMPRESSIONS_DESKTOP_MAPS'  => 'impressions_maps',
			'BUSINESS_IMPRESSIONS_MOBILE_MAPS'   => 'impressions_maps',
			'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => 'impressions_search',
			'BUSINESS_IMPRESSIONS_MOBILE_SEARCH' => 'impressions_search',
			'BUSINESS_CONVERSATIONS'             => 'conversations',
			'BUSINESS_BOOKINGS'                  => 'bookings',
		);
		$body             = (array) $c->request(
			'GET',
			'https://businessprofileperformance.googleapis.com/v1/' . $location . ':fetchMultiDailyMetricsTimeSeries',
			array(
				'dailyMetrics'               => array_keys( $map ),
				'dailyRange.start_date.year'  => $sy,
				'dailyRange.start_date.month' => $sm,
				'dailyRange.start_date.day'   => $sd,
				'dailyRange.end_date.year'    => $ey,
				'dailyRange.end_date.month'   => $em,
				'dailyRange.end_date.day'     => $ed,
			)
		)['body'];

		$days = array();
		foreach ( (array) ( $body['multiDailyMetricTimeSeries'] ?? array() ) as $group ) {
			foreach ( (array) ( $group['dailyMetricTimeSeries'] ?? array() ) as $series ) {
				$column = $map[ (string) ( $series['dailyMetric'] ?? '' ) ] ?? null;
				if ( null === $column ) {
					continue;
				}
				foreach ( (array) ( $series['timeSeries']['datedValues'] ?? array() ) as $dv ) {
					$date                      = sprintf( '%04d-%02d-%02d', $dv['date']['year'], $dv['date']['month'], $dv['date']['day'] );
					$days[ $date ][ $column ]  = ( $days[ $date ][ $column ] ?? 0 ) + (int) ( $dv['value'] ?? 0 );
				}
			}
		}
		$rows = array();
		foreach ( $days as $date => $v ) {
			$rows[] = array( (int) $i['site_id'], $location, $date, $v['impressions_maps'] ?? 0, $v['impressions_search'] ?? 0, $v['call_clicks'] ?? 0, $v['website_clicks'] ?? 0, $v['direction_requests'] ?? 0, $v['conversations'] ?? 0, $v['bookings'] ?? 0 );
		}
		$cols = array( 'impressions_maps', 'impressions_search', 'call_clicks', 'website_clicks', 'direction_requests', 'conversations', 'bookings' );
		Database::instance()->upsert_many( 'gbp_daily', array_merge( array( 'site_id', 'location_id', 'stat_date' ), $cols ), $rows, $cols );
		return null;
	}

	/**
	 * Date range: backfill on first sync, short refresh window afterwards (local dates, until yesterday).
	 *
	 * @param array  $i        Integration.
	 * @param string $provider Provider.
	 * @param int    $backfill Backfill days.
	 * @return array{0:string,1:string}
	 */
	private static function range( array $i, string $provider, int $backfill ): array {
		$today = new DateTimeImmutable( 'today', ov_timezone() );
		$days  = null === $i['last_sync_at'] ? max( 7, $backfill ) : ( self::REFRESH_DAYS[ $provider ] ?? 3 );
		return array( $today->modify( '-' . $days . ' day' )->format( 'Y-m-d' ), $today->modify( '-1 day' )->format( 'Y-m-d' ) );
	}
}
