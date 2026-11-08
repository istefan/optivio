<?php
/**
 * OptiVio — Competitor discovery (Serper.dev SERP API or manual URLs), deterministic page
 * scanning and comparison with the client's pages.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

use DOMDocument;
use DOMXPath;
use OptiVio\Core\Database;
use OptiVio\Google\Google_Http;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Services\Content_Ingestor;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Competitors engine.
 */
final class Competitors {

	/**
	 * Domains never treated as competitors (directories, marketplaces, social, maps).
	 */
	private const EXCLUDED = '/(^|\.)(google\.[a-z.]+|youtube\.com|facebook\.com|instagram\.com|tiktok\.com|linkedin\.com|wikipedia\.org|olx\.ro|emag\.ro|publi24\.ro|lajumate\.ro|storia\.ro|imobiliare\.ro|reddit\.com|pinterest\.[a-z.]+|twitter\.com|x\.com|waze\.com|tripadvisor\.[a-z.]+|paginiaurii\.ro|listafirme\.ro|termene\.ro|risco\.ro|cylex\.ro)$/';

	/**
	 * SERP discovery for a keyword via Serper.dev. Stores the query, competitors and their ranking pages.
	 *
	 * @param int    $business_id Business.
	 * @param string $keyword     Keyword.
	 * @return int Competitor pages added/updated.
	 * @throws RuntimeException When no Serper key is configured or the API fails.
	 */
	public static function discover( int $business_id, string $keyword ): int {
		$key = Credentials_Repository::reveal_for_business( $business_id, 'serper' ) ?? Credentials_Repository::reveal_global( 'serper' );
		if ( null === $key && ! Google_Http::is_mock() ) {
			throw new RuntimeException( 'Nu este configurată o cheie Serper.dev (Setări → Integrări). Adăugați concurenții manual.' );
		}
		$payload = array(
			'q'        => $keyword,
			'gl'       => (string) Settings_Repository::get( 'integrations.serper_gl', 'ro' ),
			'hl'       => (string) Settings_Repository::get( 'integrations.serper_hl', 'ro' ),
			'location' => (string) Settings_Repository::get( 'integrations.serper_location', 'Romania' ),
			'num'      => 10,
		);
		$results = Google_Http::is_mock() ? self::mock_serp( $keyword ) : self::serper( (string) $key, $payload );

		$db  = Database::instance();
		$db->insert(
			'serp_queries',
			array(
				'business_id' => $business_id,
				'keyword'     => mb_substr( $keyword, 0, 190 ),
				'location'    => $payload['location'],
				'device'      => 'mobile',
				'results'     => json_encode( $results, JSON_UNESCAPED_UNICODE ),
			)
		);

		$own   = array_map( array( self::class, 'domain' ), $db->get_col( 'SELECT url FROM sites WHERE business_id = ?', array( $business_id ) ) );
		$added = 0;
		foreach ( $results as $r ) {
			$domain = self::domain( (string) $r['link'] );
			if ( '' === $domain || in_array( $domain, $own, true ) || preg_match( self::EXCLUDED, $domain ) ) {
				continue;
			}
			$competitor_id = self::upsert_competitor( $business_id, $domain, 'serp', (string) ( $r['title'] ?? '' ) );
			self::upsert_page( $competitor_id, (string) $r['link'], $keyword, (int) $r['position'] );
			++$added;
		}
		return $added;
	}

	/**
	 * Adds a manual competitor URL (optionally tied to a keyword).
	 *
	 * @param int    $business_id Business.
	 * @param string $url         URL.
	 * @param string $keyword     Keyword (optional).
	 * @return int Competitor id.
	 * @throws RuntimeException On invalid URL.
	 */
	public static function add_manual( int $business_id, string $url, string $keyword ): int {
		if ( ! preg_match( '#^https?://#i', $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new RuntimeException( 'URL invalid.' );
		}
		$domain = self::domain( $url );
		$id     = self::upsert_competitor( $business_id, $domain, 'manual', '' );
		self::upsert_page( $id, $url, '' !== $keyword ? $keyword : null, null );
		return $id;
	}

	/**
	 * Scans one competitor page (fetch + parse) and stores the extracted structure.
	 *
	 * @param int $page_id competitor_pages id.
	 * @return array{ok:bool,error?:string}
	 */
	public static function scan_page( int $page_id ): array {
		$db   = Database::instance();
		$page = $db->get_row( 'SELECT * FROM competitor_pages WHERE id = ?', array( $page_id ) );
		if ( null === $page ) {
			return array( 'ok' => false, 'error' => 'not_found' );
		}
		$res = Google_Http::is_mock() && str_ends_with( self::domain( (string) $page['url'] ), '.example' ) ? self::mock_page( (string) $page['url'] ) : Safe_Fetcher::get( (string) $page['url'] );
		if ( ! $res['ok'] ) {
			$db->update( 'competitor_pages', array( 'http_status' => $res['status'] ?: null, 'last_fetched_at' => Database::now() ), array( 'id' => $page_id ) );
			return array( 'ok' => false, 'error' => $res['error'] );
		}

		$parsed = self::parse( (string) $res['html'], (string) $res['url'] );
		$db->update(
			'competitor_pages',
			array(
				'http_status'      => $res['status'],
				'title'            => $parsed['title'],
				'meta_description' => $parsed['meta'],
				'h1'               => $parsed['h1'],
				'headings'         => json_encode( $parsed['headings'], JSON_UNESCAPED_UNICODE ),
				'word_count'       => $parsed['word_count'],
				'term_vector'      => json_encode( $parsed['terms'], JSON_UNESCAPED_UNICODE ),
				'schema_types'     => json_encode( $parsed['schema'], JSON_UNESCAPED_UNICODE ),
				'content_hash'     => hash( 'sha256', $parsed['text'] ),
				'last_fetched_at'  => Database::now(),
			),
			array( 'id' => $page_id )
		);
		$db->query( 'UPDATE competitors SET last_scanned_at = UTC_TIMESTAMP() WHERE id = ?', array( (int) $page['competitor_id'] ) );
		return array( 'ok' => true );
	}

	/**
	 * Parses HTML: title, meta description, H1, headings, words, term vector, schema.org types.
	 *
	 * @param string $html HTML.
	 * @param string $url  URL.
	 * @return array
	 */
	public static function parse( string $html, string $url ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		$xp = new DOMXPath( $dom );

		$schema = array();
		foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $node ) {
			$json = json_decode( (string) $node->textContent, true );
			if ( ! is_array( $json ) ) {
				continue;
			}
			array_walk_recursive(
				$json,
				static function ( $value, $key ) use ( &$schema ): void {
					if ( '@type' === $key && is_string( $value ) ) {
						$schema[ $value ] = true;
					}
				}
			);
		}
		$title = trim( (string) ( $xp->query( '//title' )->item( 0 )->textContent ?? '' ) );
		$meta  = '';
		foreach ( $xp->query( '//meta[@name="description"]' ) as $m ) {
			$meta = trim( (string) $m->getAttribute( 'content' ) );
		}

		$body     = $xp->query( '//body' )->item( 0 );
		$body_doc = null !== $body ? $dom->saveHTML( $body ) : $html;
		foreach ( array( 'header', 'footer', 'nav', 'aside' ) as $tag ) {
			$body_doc = (string) preg_replace( '#<' . $tag . '\b.*?</' . $tag . '>#is', ' ', (string) $body_doc );
		}
		$analysis = Content_Ingestor::analyze( (string) $body_doc, (string) parse_url( $url, PHP_URL_HOST ), $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$text     = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) preg_replace( '#<(script|style|noscript)\b.*?</\1>#is', ' ', (string) $body_doc ) ) ) );
		$h1       = '';
		foreach ( $analysis['headings'] as $h ) {
			if ( 1 === (int) $h['level'] ) {
				$h1 = $h['text'];
				break;
			}
		}

		return array(
			'title'      => mb_substr( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ), 0, 500 ),
			'meta'       => mb_substr( $meta, 0, 1000 ),
			'h1'         => mb_substr( $h1, 0, 500 ),
			'headings'   => $analysis['headings'],
			'word_count' => $analysis['word_count'],
			'terms'      => Text::term_vector( $title . ' ' . $h1 . ' ' . $text, 60 ),
			'schema'     => array_keys( $schema ),
			'text'       => $text,
		);
	}

	/**
	 * Compares a client page with the competitor pages ranking for a keyword.
	 *
	 * @param int    $business_id Business.
	 * @param string $keyword     Keyword.
	 * @param int    $content_id  Client wp_content id (0 = none).
	 * @return array
	 */
	public static function compare( int $business_id, string $keyword, int $content_id ): array {
		$db    = Database::instance();
		$pages = $db->get_results(
			'SELECT cp.*, c.domain FROM competitor_pages cp JOIN competitors c ON c.id = cp.competitor_id
			 WHERE c.business_id = ? AND c.is_active = 1 AND cp.keyword = ? AND cp.word_count IS NOT NULL ORDER BY cp.serp_position IS NULL, cp.serp_position, cp.id',
			array( $business_id, $keyword )
		);

		$client = null;
		if ( $content_id > 0 ) {
			$client = $db->get_row(
				'SELECT c.id, c.title, c.url, c.word_count, c.h1_count, c.headings, b.body_html, m.seo_title_resolved, m.meta_description_resolved
				 FROM wp_content c JOIN sites s ON s.id = c.site_id LEFT JOIN wp_content_bodies b ON b.content_id = c.id LEFT JOIN wp_seo_meta m ON m.content_id = c.id
				 WHERE c.id = ? AND s.business_id = ?',
				array( $content_id, $business_id )
			);
		}

		$df = array();
		foreach ( $pages as &$p ) {
			$p['terms']    = (array) ( json_decode( (string) $p['term_vector'], true ) ?: array() );
			$p['headings'] = (array) ( json_decode( (string) $p['headings'], true ) ?: array() );
			$p['h2_count'] = count(
				array_filter(
					$p['headings'],
					static function ( array $h ): bool {
						return 2 === (int) $h['level'];
					}
				)
			);
			foreach ( array_keys( $p['terms'] ) as $term ) {
				$df[ (string) $term ] = ( $df[ (string) $term ] ?? 0 ) + 1;
			}
		}
		unset( $p );

		$n         = count( $pages );
		$client_tx = null !== $client ? Text::normalize( strip_tags( (string) $client['body_html'] ) . ' ' . $client['title'] . ' ' . $client['seo_title_resolved'] ) : '';
		$gaps      = array();
		foreach ( $df as $term => $count ) {
			if ( $n >= 2 && $count >= max( 2, (int) ceil( $n / 2 ) ) && ! Text::contains_phrase( (string) $term, $client_tx ) ) {
				$gaps[ (string) $term ] = $count;
			}
		}
		arsort( $gaps );

		$heading_gaps = array();
		foreach ( $pages as $p ) {
			foreach ( $p['headings'] as $h ) {
				if ( 2 === (int) $h['level'] && '' !== $h['text'] && ! Text::contains_phrase( (string) $h['text'], $client_tx ) ) {
					$heading_gaps[ Text::key( (string) $h['text'] ) ] = array(
						'text'   => (string) $h['text'],
						'domain' => (string) $p['domain'],
					);
				}
			}
		}

		$words  = array_map( 'intval', array_column( $pages, 'word_count' ) );
		sort( $words );
		$median = $n > 0 ? $words[ intdiv( $n, 2 ) ] : 0;

		return array(
			'keyword'        => $keyword,
			'pages'          => $pages,
			'client'         => $client,
			'median_words'   => $median,
			'avg_h2'         => $n > 0 ? round( array_sum( array_column( $pages, 'h2_count' ) ) / $n, 1 ) : 0,
			'term_gaps'      => array_slice( $gaps, 0, 25, true ),
			'heading_gaps'   => array_slice( array_values( $heading_gaps ), 0, 20 ),
			'client_has_kw'  => null !== $client && Text::contains_phrase( $keyword, (string) $client['seo_title_resolved'] . ' ' . $client['title'] ),
			'schema_counts'  => self::schema_counts( $pages ),
		);
	}

	/**
	 * Schema types used by competitors.
	 *
	 * @param array $pages Pages.
	 * @return array<string,int>
	 */
	private static function schema_counts( array $pages ): array {
		$out = array();
		foreach ( $pages as $p ) {
			foreach ( (array) json_decode( (string) $p['schema_types'], true ) as $type ) {
				$out[ (string) $type ] = ( $out[ (string) $type ] ?? 0 ) + 1;
			}
		}
		arsort( $out );
		return $out;
	}

	/**
	 * Serper.dev request.
	 *
	 * @param string $key     API key.
	 * @param array  $payload Payload.
	 * @return array<int,array{link:string,title:string,position:int}>
	 * @throws RuntimeException On API error.
	 */
	private static function serper( string $key, array $payload ): array {
		$ch = curl_init( 'https://google.serper.dev/search' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => json_encode( $payload ),
				CURLOPT_HTTPHEADER     => array( 'X-API-KEY: ' . $key, 'Content-Type: application/json' ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 20,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			)
		);
		$raw  = (string) curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$body = json_decode( $raw, true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			throw new RuntimeException( 'Serper ' . $code . ': ' . mb_substr( (string) ( $body['message'] ?? $raw ), 0, 200 ) );
		}
		$out = array();
		foreach ( (array) ( $body['organic'] ?? array() ) as $r ) {
			$out[] = array(
				'link'     => (string) ( $r['link'] ?? '' ),
				'title'    => (string) ( $r['title'] ?? '' ),
				'position' => (int) ( $r['position'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Creates or reactivates a competitor.
	 *
	 * @param int    $business_id Business.
	 * @param string $domain      Domain.
	 * @param string $source      serp|manual.
	 * @param string $name        Name.
	 * @return int
	 */
	private static function upsert_competitor( int $business_id, string $domain, string $source, string $name ): int {
		$db = Database::instance();
		$db->query(
			'INSERT INTO competitors (business_id, domain, name, source) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = COALESCE(name, VALUES(name))',
			array( $business_id, $domain, '' !== $name ? mb_substr( $name, 0, 160 ) : null, $source )
		);
		return (int) $db->get_var( 'SELECT id FROM competitors WHERE business_id = ? AND domain = ?', array( $business_id, $domain ) );
	}

	/**
	 * Creates or updates a competitor page.
	 *
	 * @param int         $competitor_id Competitor.
	 * @param string      $url           URL.
	 * @param string|null $keyword       Keyword.
	 * @param int|null    $position      SERP position.
	 * @return void
	 */
	private static function upsert_page( int $competitor_id, string $url, ?string $keyword, ?int $position ): void {
		Database::instance()->query(
			'INSERT INTO competitor_pages (competitor_id, url, url_hash, keyword, serp_position) VALUES (?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE keyword = COALESCE(VALUES(keyword), keyword), serp_position = COALESCE(VALUES(serp_position), serp_position)',
			array( $competitor_id, mb_substr( $url, 0, 500 ), hash( 'sha256', Content_Ingestor::normalize_url( $url ) ), null !== $keyword ? mb_substr( $keyword, 0, 190 ) : null, $position )
		);
	}

	/**
	 * Registrable-ish domain (host without www).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function domain( string $url ): string {
		return (string) preg_replace( '/^www\./', '', strtolower( (string) parse_url( $url, PHP_URL_HOST ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}

	/**
	 * Mock SERP (development only).
	 *
	 * @param string $keyword Keyword.
	 * @return array
	 */
	private static function mock_serp( string $keyword ): array {
		unset( $keyword );
		return array(
			array( 'link' => 'https://www.instalator-rapid.example/urgente/', 'title' => 'Instalator Rapid', 'position' => 1 ),
			array( 'link' => 'https://www.facebook.com/instalatori', 'title' => 'Facebook', 'position' => 2 ),
			array( 'link' => 'https://www.super-instalatii.example/', 'title' => 'Super Instalații', 'position' => 3 ),
			array( 'link' => 'https://www.instalatori-non-stop.ro/', 'title' => 'Client', 'position' => 4 ),
			array( 'link' => 'https://www.mester-acasa.example/instalator/', 'title' => 'Meșter Acasă', 'position' => 5 ),
		);
	}

	/**
	 * Mock competitor page (development only, *.example domains).
	 *
	 * @param string $url URL.
	 * @return array
	 */
	private static function mock_page( string $url ): array {
		$n    = strlen( $url ) % 3;
		$h2   = array( 'Prețuri instalator non stop', 'Zone deservite în București', 'Intervenție în 30 de minute', 'Garanție 12 luni', 'Întrebări frecvente' );
		$html = '<html><head><title>Instalator non stop București – intervenție rapidă</title><meta name="description" content="Instalatori autorizați non stop."><script type="application/ld+json">{"@context":"https://schema.org","@type":"Plumber","aggregateRating":{"@type":"AggregateRating"}}</script></head><body><header>meniu</header><h1>Instalator non stop în București</h1>';
		foreach ( array_slice( $h2, 0, 3 + $n ) as $h ) {
			$html .= '<h2>' . $h . '</h2><p>' . str_repeat( 'Instalator autorizat cu garanție, preț fix, deplasare gratuită, intervenție rapidă în toate sectoarele. ', 12 + 4 * $n ) . '</p>';
		}
		return array(
			'ok'     => true,
			'status' => 200,
			'url'    => $url,
			'html'   => $html . '<footer>contact</footer></body></html>',
			'error'  => '',
		);
	}
}
