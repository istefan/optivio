<?php
/**
 * OptiVio — Deterministic Google API fixtures for local development and QA.
 *
 * Active only when app_env = development AND config google_mock = true (see Google_Http::is_mock()).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Mock responses.
 */
final class Google_Mock {

	/**
	 * Returns [http_code, body] for a request.
	 *
	 * @param string $method Method.
	 * @param string $url    Full URL (with query).
	 * @param array  $body   Request body.
	 * @return array{0:int,1:array}
	 */
	public static function respond( string $method, string $url, array $body ): array {
		$path  = (string) parse_url( $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$host  = (string) parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$query = self::query( (string) parse_url( $url, PHP_URL_QUERY ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

		if ( 'oauth2.googleapis.com' === $host && '/token' === $path ) {
			$claims = rtrim( strtr( base64_encode( (string) json_encode( array( 'email' => 'agentie.demo@gmail.com' ) ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			return array(
				200,
				array(
					'access_token'  => 'mock-access-' . bin2hex( random_bytes( 6 ) ),
					'expires_in'    => 3599,
					'refresh_token' => 'mock-refresh-token',
					'scope'         => implode( ' ', Google_Oauth::SCOPES ),
					'id_token'      => 'eyJhbGciOiJub25lIn0.' . $claims . '.',
				),
			);
		}
		if ( '/revoke' === $path ) {
			return array( 200, array() );
		}
		if ( '/tokeninfo' === $path ) {
			return array(
				200,
				array(
					'email'      => 'agentie.demo@gmail.com',
					'expires_in' => 3500,
				)
			);
		}
		if ( 0 === strpos( $path, '/drive/v3/files' ) ) {
			if ( '/drive/v3/files' === $path && 'GET' === strtoupper( (string) $method ) ) {
				return array( 200, array( 'files' => array() ) );
			}
			if ( '/drive/v3/files' === $path ) {
				return array( 200, array( 'id' => 'mock-folder-' . substr( sha1( (string) json_encode( $body ) ), 0, 10 ) ) );
			}
			return array(
				200,
				array(
					'id'      => basename( $path ),
					'trashed' => false,
				)
			);
		}
		if ( str_ends_with( $path, '/webmasters/v3/sites' ) ) {
			return array( 200, array( 'siteEntry' => array( array( 'siteUrl' => 'sc-domain:instalatori-non-stop.ro', 'permissionLevel' => 'siteOwner' ), array( 'siteUrl' => 'https://www.clinica-zambet.ro/', 'permissionLevel' => 'siteFullUser' ) ) ) );
		}
		if ( str_contains( $path, '/searchAnalytics/query' ) ) {
			return array( 200, array( 'rows' => self::gsc_rows( (string) $body['startDate'], (string) $body['endDate'] ) ) );
		}
		if ( str_ends_with( $path, '/accountSummaries' ) ) {
			return array( 200, array( 'accountSummaries' => array( array( 'displayName' => 'Instalatori Non-Stop', 'propertySummaries' => array( array( 'property' => 'properties/312345678', 'displayName' => 'instalatori-non-stop.ro' ) ) ) ) ) );
		}
		if ( str_ends_with( $path, ':runReport' ) ) {
			return array( 200, self::ga4_report( (string) $body['dateRanges'][0]['startDate'], (string) $body['dateRanges'][0]['endDate'] ) );
		}
		if ( str_ends_with( $path, '/runPagespeed' ) ) {
			return array( 200, self::psi() );
		}
		if ( str_ends_with( $path, '/dataStreams' ) ) {
			return array( 200, array( 'dataStreams' => array( array( 'type' => 'WEB_DATA_STREAM', 'webStreamData' => array( 'defaultUri' => 'https://www.instalatori-non-stop.ro' ) ) ) ) );
		}
		if ( str_ends_with( $path, '/sites' ) && 'adsense.googleapis.com' === $host ) {
			return array( 200, array( 'sites' => array( array( 'domain' => 'instalatori-non-stop.ro' ) ) ) );
		}
		if ( str_ends_with( $path, '/tagmanager/v2/accounts' ) ) {
			return array( 200, array( 'account' => array( array( 'path' => 'accounts/600', 'name' => 'Agenție' ) ) ) );
		}
		if ( str_ends_with( $path, '/containers' ) ) {
			return array( 200, array( 'container' => array( array( 'path' => 'accounts/600/containers/700', 'publicId' => 'GTM-ABC1234', 'name' => 'instalatori-non-stop.ro', 'domainName' => array( 'instalatori-non-stop.ro' ) ) ) ) );
		}
		if ( str_ends_with( $path, '/versions:live' ) ) {
			return array( 200, array( 'containerVersionId' => '42', 'tag' => array( array( 'type' => 'googtag' ), array( 'type' => 'gaawc' ), array( 'type' => 'awct' ), array( 'type' => 'html' ) ), 'trigger' => array( array(), array() ), 'variable' => array( array() ) ) );
		}
		if ( str_ends_with( $path, 'customers:listAccessibleCustomers' ) ) {
			return array( 200, array( 'resourceNames' => array( 'customers/1234567890' ) ) );
		}
		if ( str_ends_with( $path, '/googleAds:search' ) ) {
			return array( 200, self::ads( (string) $body['query'], isset( $body['pageToken'] ) ) );
		}
		if ( str_ends_with( $path, '/v2/accounts' ) && 'adsense.googleapis.com' === $host ) {
			return array( 200, array( 'accounts' => array( array( 'name' => 'accounts/pub-1234567890123456', 'displayName' => 'AdSense Agenție' ) ) ) );
		}
		if ( str_ends_with( $path, '/reports:generate' ) ) {
			$rows = array();
			foreach ( self::days( sprintf( '%04d-%02d-%02d', $query['startDate.year'], $query['startDate.month'], $query['startDate.day'] ), sprintf( '%04d-%02d-%02d', $query['endDate.year'], $query['endDate.month'], $query['endDate.day'] ) ) as $i => $d ) {
				$rows[] = array( 'cells' => array( array( 'value' => $d ), array( 'value' => 'instalatori-non-stop.ro' ), array( 'value' => (string) ( 300 + $i % 40 ) ), array( 'value' => (string) ( 900 + $i % 70 ) ), array( 'value' => (string) ( 4 + $i % 5 ) ), array( 'value' => number_format( 1.5 + ( $i % 7 ) * 0.3, 2, '.', '' ) ) ) );
			}
			return array( 200, array( 'rows' => $rows ) );
		}
		if ( str_ends_with( $path, '/v1/accounts' ) && str_starts_with( $host, 'mybusinessaccount' ) ) {
			return array( 200, array( 'accounts' => array( array( 'name' => 'accounts/111', 'accountName' => 'Agenție' ) ) ) );
		}
		if ( str_ends_with( $path, '/locations' ) ) {
			return array( 200, array( 'locations' => array( array( 'name' => 'locations/9876543210', 'title' => 'Instalatori Non-Stop București', 'websiteUri' => 'https://www.instalatori-non-stop.ro/' ) ) ) );
		}
		if ( str_ends_with( $path, ':fetchMultiDailyMetricsTimeSeries' ) ) {
			$start  = sprintf( '%04d-%02d-%02d', $query['dailyRange.start_date.year'], $query['dailyRange.start_date.month'], $query['dailyRange.start_date.day'] );
			$end    = sprintf( '%04d-%02d-%02d', $query['dailyRange.end_date.year'], $query['dailyRange.end_date.month'], $query['dailyRange.end_date.day'] );
			$series = array();
			foreach ( (array) $query['dailyMetrics'] as $k => $metric ) {
				$values = array();
				foreach ( self::days( $start, $end ) as $i => $d ) {
					[ $y, $m, $day ] = array_map( 'intval', explode( '-', $d ) );
					$values[]        = array( 'date' => array( 'year' => $y, 'month' => $m, 'day' => $day ), 'value' => (string) ( ( $k + 1 ) * 3 + ( $i * 7 + $k ) % 11 ) );
				}
				$series[] = array( 'dailyMetric' => $metric, 'timeSeries' => array( 'datedValues' => $values ) );
			}
			return array( 200, array( 'multiDailyMetricTimeSeries' => array( array( 'dailyMetricTimeSeries' => $series ) ) ) );
		}
		return array( 404, array( 'error' => array( 'message' => 'Mock: endpoint necunoscut ' . $path ) ) );
	}

	/**
	 * GSC rows.
	 *
	 * @param string $start Start.
	 * @param string $end   End.
	 * @return array
	 */
	private static function gsc_rows( string $start, string $end ): array {
		$queries = array(
			array( 'instalator non stop', '/', 3.1 ),
			array( 'instalator non stop', '/urgente/', 4.8 ),
			array( 'instalator bucuresti', '/', 6.2 ),
			array( 'teava sparta', '/urgente/', 2.4 ),
			array( 'desfundare canalizare', '/desfundari/', 11.5 ),
			array( 'instalator sector 3', '/', 14.2 ),
			array( 'pret instalator', '/preturi/', 8.9 ),
		);
		$rows    = array();
		foreach ( self::days( $start, $end ) as $i => $d ) {
			foreach ( $queries as $k => $q ) {
				$impr   = 40 + ( ( $i * 13 + $k * 7 ) % 60 );
				$rows[] = array(
					'keys'        => array( $d, $q[0], 'https://www.instalatori-non-stop.ro' . $q[1] ),
					'clicks'      => (int) round( $impr * max( 0.01, 0.3 - $q[2] / 50 ) ),
					'impressions' => $impr,
					'ctr'         => 0.1,
					'position'    => $q[2] + ( $i % 3 ) * 0.2,
				);
			}
		}
		return $rows;
	}

	/**
	 * GA4 report.
	 *
	 * @param string $start Start.
	 * @param string $end   End.
	 * @return array
	 */
	private static function ga4_report( string $start, string $end ): array {
		$rows = array();
		foreach ( self::days( $start, $end ) as $i => $d ) {
			foreach ( array( array( '/', 'google', 'organic' ), array( '/urgente/', 'google', 'cpc' ), array( '/', '(direct)', '(none)' ) ) as $k => $dim ) {
				$sessions = 20 + ( $i * 5 + $k * 11 ) % 40;
				$rows[]   = array(
					'dimensionValues' => array( array( 'value' => str_replace( '-', '', $d ) ), array( 'value' => $dim[0] ), array( 'value' => $dim[1] ), array( 'value' => $dim[2] ) ),
					'metricValues'    => array( array( 'value' => (string) $sessions ), array( 'value' => (string) ( $sessions - 3 ) ), array( 'value' => (string) (int) ( $sessions * 0.6 ) ), array( 'value' => (string) ( $sessions * 48 ) ), array( 'value' => (string) ( $k + $i % 3 ) ), array( 'value' => '0' ) ),
				);
			}
		}
		return array(
			'rows'     => $rows,
			'rowCount' => count( $rows ),
		);
	}

	/**
	 * PSI result.
	 *
	 * @return array
	 */
	private static function psi(): array {
		return array(
			'lighthouseResult'  => array(
				'categories' => array( 'performance' => array( 'score' => 0.62 ), 'seo' => array( 'score' => 0.92 ), 'accessibility' => array( 'score' => 0.85 ), 'best-practices' => array( 'score' => 0.96 ) ),
				'audits'     => array(
					'largest-contentful-paint' => array( 'numericValue' => 3850.4 ),
					'cumulative-layout-shift'  => array( 'numericValue' => 0.083 ),
					'first-contentful-paint'   => array( 'numericValue' => 1900 ),
					'server-response-time'     => array( 'numericValue' => 620 ),
					'total-blocking-time'      => array( 'numericValue' => 410 ),
					'render-blocking-resources' => array( 'title' => 'Eliminați resursele care blochează redarea', 'score' => 0.3, 'details' => array( 'type' => 'opportunity', 'overallSavingsMs' => 1250 ) ),
					'modern-image-formats'     => array( 'title' => 'Imagini în formate moderne', 'score' => 0.5, 'details' => array( 'type' => 'opportunity', 'overallSavingsMs' => 900 ) ),
				),
			),
			'loadingExperience' => array( 'metrics' => array( 'INTERACTION_TO_NEXT_PAINT' => array( 'percentile' => 240 ) ) ),
		);
	}

	/**
	 * Google Ads GAQL results (second page served when a pageToken is sent for keywords).
	 *
	 * @param string $gaql      Query.
	 * @param bool   $has_token Whether a page token was sent.
	 * @return array
	 */
	private static function ads( string $gaql, bool $has_token ): array {
		$campaigns = array( array( 'id' => '11', 'name' => 'Urgente-Instalatii-Search' ), array( 'id' => '12', 'name' => 'Desfundari-Bucuresti' ) );
		if ( str_contains( $gaql, 'FROM campaign WHERE campaign.status' ) ) {
			$res = array();
			foreach ( $campaigns as $c ) {
				$res[] = array( 'campaign' => $c + array( 'status' => 'ENABLED', 'advertisingChannelType' => 'SEARCH', 'biddingStrategyType' => 'MAXIMIZE_CONVERSIONS' ), 'campaignBudget' => array( 'amountMicros' => '150000000' ) );
			}
			return array( 'results' => $res );
		}
		if ( str_contains( $gaql, 'FROM keyword_view' ) ) {
			$words = $has_token ? array( 'teava sparta', 'instalator urgent' ) : array( 'instalator non stop', 'desfundare canalizare' );
			$res   = array();
			foreach ( $words as $k => $w ) {
				$res[] = array( 'campaign' => array( 'id' => '1' . ( 1 + $k % 2 ) ), 'adGroup' => array( 'id' => '50' . $k, 'name' => 'Grup ' . $k ), 'adGroupCriterion' => array( 'criterionId' => (string) ( 900 + $k ), 'keyword' => array( 'text' => $w, 'matchType' => 'PHRASE' ), 'negative' => false, 'status' => 'ENABLED', 'qualityInfo' => array( 'qualityScore' => 6 + $k ), 'cpcBidMicros' => '2500000' ) );
			}
			return $has_token ? array( 'results' => $res ) : array( 'results' => $res, 'nextPageToken' => 'page2' );
		}
		if ( str_contains( $gaql, 'FROM campaign_criterion' ) ) {
			return array( 'results' => array( array( 'campaign' => array( 'id' => '11' ), 'campaignCriterion' => array( 'criterionId' => '77', 'keyword' => array( 'text' => 'gratuit', 'matchType' => 'BROAD' ) ) ) ) );
		}
		preg_match( "/BETWEEN '([0-9-]+)' AND '([0-9-]+)'/", $gaql, $m );
		$res = array();
		foreach ( self::days( $m[1], $m[2] ) as $i => $d ) {
			if ( str_contains( $gaql, 'segments.hour' ) ) {
				foreach ( array( 9, 14, 21 ) as $h ) {
					$res[] = array( 'campaign' => array( 'id' => '11' ), 'segments' => array( 'date' => $d, 'hour' => $h ), 'metrics' => array( 'impressions' => (string) ( 100 + $i % 30 ), 'clicks' => (string) ( 8 + $h % 5 ), 'costMicros' => (string) ( ( 15 + $h ) * 1000000 ), 'conversions' => 1.0, 'conversionsValue' => 0 ) );
				}
			} elseif ( 0 === $i % 3 ) {
				$res[] = array( 'campaign' => array( 'id' => '11' ), 'segments' => array( 'date' => $d ), 'searchTermView' => array( 'searchTerm' => 0 === $i % 2 ? 'instalator non stop sector 2' : 'curs instalator gratuit' ), 'metrics' => array( 'impressions' => '30', 'clicks' => '3', 'costMicros' => '7500000', 'conversions' => 0 === $i % 2 ? 1 : 0 ) );
			}
		}
		return array( 'results' => $res );
	}

	/**
	 * Days in range.
	 *
	 * @param string $start Start (Y-m-d).
	 * @param string $end   End (Y-m-d).
	 * @return array<int,string>
	 */
	private static function days( string $start, string $end ): array {
		$out = array();
		foreach ( new DatePeriod( new DateTimeImmutable( $start ), new DateInterval( 'P1D' ), ( new DateTimeImmutable( $end ) )->modify( '+1 day' ) ) as $d ) {
			$out[] = $d->format( 'Y-m-d' );
		}
		return $out;
	}

	/**
	 * Parses a query string keeping repeated keys as arrays.
	 *
	 * @param string $query Query.
	 * @return array
	 */
	private static function query( string $query ): array {
		$out = array();
		foreach ( '' === $query ? array() : explode( '&', $query ) as $pair ) {
			[ $k, $v ] = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$k         = rawurldecode( $k );
			$v         = rawurldecode( $v );
			if ( isset( $out[ $k ] ) ) {
				$out[ $k ] = array_merge( (array) $out[ $k ], array( $v ) );
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}
}
