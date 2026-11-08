<?php
/**
 * OptiVio — Mirrors WordPress content sent by the connector and extracts deterministic
 * on-page metrics (headings, word count, images/ALT, internal link graph). Zero AI.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use DOMDocument;
use DOMElement;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Content ingestor.
 */
final class Content_Ingestor {

	public const MAX_ITEMS = 20;

	/**
	 * Ingests a batch of content items for a site.
	 *
	 * @param array $site  Site row (id, ...).
	 * @param array $items Items.
	 * @return array{stored:int,deleted:int,invalid:int}
	 */
	public static function ingest( array $site, array $items ): array {
		$site_id = (int) $site['id'];
		$host    = self::site_host( $site_id );
		$result  = array(
			'stored'  => 0,
			'deleted' => 0,
			'invalid' => 0,
		);

		foreach ( array_slice( array_values( $items ), 0, self::MAX_ITEMS ) as $item ) {
			if ( ! is_array( $item ) ) {
				++$result['invalid'];
				continue;
			}
			$type  = (string) ( $item['object_type'] ?? '' );
			$wp_id = absint( $item['wp_id'] ?? 0 );
			if ( ! in_array( $type, array( 'post', 'term' ), true ) || 0 === $wp_id ) {
				++$result['invalid'];
				continue;
			}
			if ( ! empty( $item['deleted'] ) ) {
				$result['deleted'] += Database::instance()->delete(
					'wp_content',
					array(
						'site_id'     => $site_id,
						'object_type' => $type,
						'wp_id'       => $wp_id,
					)
				);
				continue;
			}
			if ( self::store( $site_id, $host, $type, $wp_id, $item ) ) {
				++$result['stored'];
			} else {
				++$result['invalid'];
			}
		}

		self::recompute_links( $site_id );
		Database::instance()->query( 'UPDATE sites SET content_synced_at = UTC_TIMESTAMP() WHERE id = ?', array( $site_id ) );
		return $result;
	}

	/**
	 * Normalizes a URL for hashing: lower-case host, no www, no query/fragment, no trailing slash.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url( string $url ): string {
		$p = parse_url( trim( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $p ) || empty( $p['host'] ) ) {
			return '';
		}
		$host  = (string) preg_replace( '/^www\./', '', strtolower( $p['host'] ) );
		$port  = isset( $p['port'] ) ? ':' . (int) $p['port'] : '';
		$path  = rtrim( (string) ( $p['path'] ?? '' ), '/' );
		$query = '';
		if ( ! empty( $p['query'] ) ) {
			// Keep identifying params (?p=, ?page_id=, ?cat=...), drop tracking params, sort for stability.
			parse_str( (string) $p['query'], $args );
			foreach ( array_keys( $args ) as $key ) {
				if ( preg_match( '/^(utm_|gclid|gbraid|wbraid|fbclid|msclkid|_ga|ref$)/i', (string) $key ) ) {
					unset( $args[ $key ] );
				}
			}
			ksort( $args );
			$query = empty( $args ) ? '' : '?' . http_build_query( $args );
		}
		return $host . $port . ( '' === $path ? '/' : $path ) . $query;
	}

	/**
	 * Stores one item.
	 *
	 * @param int    $site_id Site.
	 * @param string $host    Site host.
	 * @param string $type    post|term.
	 * @param int    $wp_id   WP id.
	 * @param array  $item    Item.
	 * @return bool
	 */
	private static function store( int $site_id, string $host, string $type, int $wp_id, array $item ): bool {
		$url = (string) ( $item['url'] ?? '' );
		if ( ! preg_match( '#^https?://#i', $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$html     = (string) ( $item['content_html'] ?? '' );
		$metrics  = self::analyze( $html, $host, $url );
		$seo      = is_array( $item['seo'] ?? null ) ? $item['seo'] : array();
		$text     = static function ( $value, int $max ): ?string {
			$value = mb_substr( trim( (string) $value ), 0, $max );
			return '' === $value ? null : $value;
		};
		$modified = (string) ( $item['modified_gmt'] ?? '' );
		// WordPress drafts / auto-drafts report "0000-00-00 00:00:00", rejected by MySQL strict mode.
		$modified = 0 === strpos( $modified, '0000-00-00' ) ? '' : $modified;
		$featured = is_array( $item['featured_image'] ?? null ) ? array(
			'id'  => absint( $item['featured_image']['id'] ?? 0 ),
			'src' => mb_substr( (string) ( $item['featured_image']['src'] ?? '' ), 0, 500 ),
			'alt' => mb_substr( sanitize_text_field( (string) ( $item['featured_image']['alt'] ?? '' ) ), 0, 500 ),
		) : null;

		$db = Database::instance();
		$db->query(
			'INSERT INTO wp_content (site_id, object_type, wp_id, subtype, status, url, url_hash, title, slug, parent_wp_id, word_count, h1_count, headings,
				internal_links_out, images_count, images_missing_alt, content_hash, featured_image, modified_gmt, synced_at)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
			 ON DUPLICATE KEY UPDATE subtype = VALUES(subtype), status = VALUES(status), url = VALUES(url), url_hash = VALUES(url_hash), title = VALUES(title),
				slug = VALUES(slug), parent_wp_id = VALUES(parent_wp_id), word_count = VALUES(word_count), h1_count = VALUES(h1_count), headings = VALUES(headings),
				internal_links_out = VALUES(internal_links_out), images_count = VALUES(images_count), images_missing_alt = VALUES(images_missing_alt),
				content_hash = VALUES(content_hash), featured_image = VALUES(featured_image), modified_gmt = VALUES(modified_gmt), synced_at = UTC_TIMESTAMP()',
			array(
				$site_id,
				$type,
				$wp_id,
				mb_substr( sanitize_key( (string) ( $item['subtype'] ?? $type ) ), 0, 60 ),
				mb_substr( sanitize_key( (string) ( $item['status'] ?? 'publish' ) ), 0, 20 ),
				mb_substr( $url, 0, 500 ),
				hash( 'sha256', self::normalize_url( $url ) ),
				$text( html_entity_decode( strip_tags( (string) ( $item['title'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ), 500 ),
				$text( $item['slug'] ?? '', 200 ),
				absint( $item['parent'] ?? 0 ) ?: null,
				$metrics['word_count'],
				$metrics['h1_count'],
				json_encode( $metrics['headings'], JSON_UNESCAPED_UNICODE ),
				count( $metrics['links'] ),
				count( $metrics['images'] ),
				$metrics['images_missing_alt'],
				hash( 'sha256', $html ),
				null === $featured ? null : json_encode( $featured, JSON_UNESCAPED_UNICODE ),
				preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $modified ) ? $modified : null,
			)
		);
		$content_id = (int) $db->get_var(
			'SELECT id FROM wp_content WHERE site_id = ? AND object_type = ? AND wp_id = ?',
			array( $site_id, $type, $wp_id )
		);

		$db->query(
			'INSERT INTO wp_content_bodies (content_id, body_html, excerpt) VALUES (?, ?, ?)
			 ON DUPLICATE KEY UPDATE body_html = VALUES(body_html), excerpt = VALUES(excerpt)',
			array( $content_id, $html, $text( $item['excerpt'] ?? '', 5000 ) )
		);

		$db->query(
			'INSERT INTO wp_seo_meta (content_id, seo_title, seo_title_resolved, meta_description, meta_description_resolved, focus_keyword, canonical,
				robots_noindex, robots_nofollow, og_title, og_description, og_image, yoast_seo_score, yoast_read_score)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE seo_title = VALUES(seo_title), seo_title_resolved = VALUES(seo_title_resolved), meta_description = VALUES(meta_description),
				meta_description_resolved = VALUES(meta_description_resolved), focus_keyword = VALUES(focus_keyword), canonical = VALUES(canonical),
				robots_noindex = VALUES(robots_noindex), robots_nofollow = VALUES(robots_nofollow), og_title = VALUES(og_title), og_description = VALUES(og_description),
				og_image = VALUES(og_image), yoast_seo_score = VALUES(yoast_seo_score), yoast_read_score = VALUES(yoast_read_score)',
			array(
				$content_id,
				$text( $seo['seo_title'] ?? '', 500 ),
				$text( $seo['seo_title_resolved'] ?? '', 500 ),
				$text( $seo['meta_description'] ?? '', 1000 ),
				$text( $seo['meta_description_resolved'] ?? '', 1000 ),
				$text( $seo['focus_keyword'] ?? '', 190 ),
				$text( $seo['canonical'] ?? '', 500 ),
				! empty( $seo['noindex'] ) ? 1 : 0,
				! empty( $seo['nofollow'] ) ? 1 : 0,
				$text( $seo['og_title'] ?? '', 500 ),
				$text( $seo['og_description'] ?? '', 1000 ),
				$text( $seo['og_image'] ?? '', 500 ),
				is_numeric( $seo['seo_score'] ?? null ) ? min( 100, (int) $seo['seo_score'] ) : null,
				is_numeric( $seo['read_score'] ?? null ) ? min( 100, (int) $seo['read_score'] ) : null,
			)
		);

		$db->delete( 'wp_images', array( 'content_id' => $content_id ) );
		foreach ( array_slice( $metrics['images'], 0, 200 ) as $img ) {
			$db->insert(
				'wp_images',
				array(
					'site_id'    => $site_id,
					'content_id' => $content_id,
					'src'        => mb_substr( $img['src'], 0, 500 ),
					'alt'        => null === $img['alt'] ? null : mb_substr( $img['alt'], 0, 500 ),
					'width'      => $img['width'],
					'height'     => $img['height'],
				)
			);
		}

		$db->delete( 'internal_links', array( 'source_content_id' => $content_id ) );
		foreach ( array_slice( $metrics['links'], 0, 500 ) as $link ) {
			$db->insert(
				'internal_links',
				array(
					'site_id'           => $site_id,
					'source_content_id' => $content_id,
					'target_url'        => mb_substr( $link['url'], 0, 500 ),
					'target_url_hash'   => hash( 'sha256', self::normalize_url( $link['url'] ) ),
					'anchor_text'       => '' === $link['anchor'] ? null : mb_substr( $link['anchor'], 0, 500 ),
					'is_nofollow'       => $link['nofollow'] ? 1 : 0,
				)
			);
		}
		return true;
	}

	/**
	 * Deterministic HTML analysis.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $host     Site host.
	 * @param string $page_url Page URL (base for relative links).
	 * @return array{word_count:int,h1_count:int,headings:array,images:array,images_missing_alt:int,links:array}
	 */
	public static function analyze( string $html, string $host, string $page_url ): array {
		$result = array(
			'word_count'         => 0,
			'h1_count'           => 0,
			'headings'           => array(),
			'images'             => array(),
			'images_missing_alt' => 0,
			'links'              => array(),
		);
		if ( '' === trim( $html ) ) {
			return $result;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();

		foreach ( array( 'script', 'style', 'noscript' ) as $tag ) {
			foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $node ) {
				$node->parentNode->removeChild( $node );
			}
		}

		$text                 = (string) $dom->textContent;
		$result['word_count'] = (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', $text );

		$xpath = new \DOMXPath( $dom );
		foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $h ) {
			$level = (int) substr( $h->nodeName, 1 );
			if ( 1 === $level ) {
				++$result['h1_count'];
			}
			if ( count( $result['headings'] ) < 200 ) {
				$result['headings'][] = array(
					'level' => $level,
					'text'  => mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', $h->textContent ) ), 0, 300 ),
				);
			}
		}

		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			if ( ! $img instanceof DOMElement ) {
				continue;
			}
			$alt                  = $img->hasAttribute( 'alt' ) ? trim( $img->getAttribute( 'alt' ) ) : null;
			$result['images'][]   = array(
				'src'    => self::absolute( $img->getAttribute( 'src' ), $page_url ),
				'alt'    => $alt,
				'width'  => ctype_digit( $img->getAttribute( 'width' ) ) ? min( 65535, (int) $img->getAttribute( 'width' ) ) : null,
				'height' => ctype_digit( $img->getAttribute( 'height' ) ) ? min( 65535, (int) $img->getAttribute( 'height' ) ) : null,
			);
			if ( null === $alt || '' === $alt ) {
				++$result['images_missing_alt'];
			}
		}

		$site = (string) preg_replace( '/^www\./', '', strtolower( $host ) );
		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			if ( ! $a instanceof DOMElement ) {
				continue;
			}
			$href = trim( $a->getAttribute( 'href' ) );
			if ( '' === $href || '#' === $href[0] || preg_match( '#^(mailto|tel|javascript|data):#i', $href ) ) {
				continue;
			}
			$abs   = self::absolute( $href, $page_url );
			$lhost = (string) preg_replace( '/^www\./', '', strtolower( (string) parse_url( $abs, PHP_URL_HOST ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			if ( '' === $lhost || $lhost !== $site ) {
				continue;
			}
			$result['links'][] = array(
				'url'      => $abs,
				'anchor'   => mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', $a->textContent ) ), 0, 500 ),
				'nofollow' => false !== stripos( $a->getAttribute( 'rel' ), 'nofollow' ),
			);
		}
		return $result;
	}

	/**
	 * Resolves a relative URL against the page URL.
	 *
	 * @param string $href Href.
	 * @param string $base Base URL.
	 * @return string
	 */
	public static function absolute( string $href, string $base ): string {
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		$b = parse_url( $base ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $b ) || empty( $b['host'] ) ) {
			return $href;
		}
		$origin = ( $b['scheme'] ?? 'https' ) . '://' . $b['host'] . ( isset( $b['port'] ) ? ':' . $b['port'] : '' );
		if ( 0 === strpos( $href, '//' ) ) {
			return ( $b['scheme'] ?? 'https' ) . ':' . $href;
		}
		if ( 0 === strpos( $href, '/' ) ) {
			return $origin . $href;
		}
		$dir = rtrim( (string) preg_replace( '#/[^/]*$#', '/', (string) ( $b['path'] ?? '/' ) ), '/' );
		return $origin . $dir . '/' . $href;
	}

	/**
	 * Resolves link targets and inbound link counts for a site.
	 *
	 * @param int $site_id Site.
	 * @return void
	 */
	private static function recompute_links( int $site_id ): void {
		$db = Database::instance();
		$db->query(
			'UPDATE internal_links il JOIN wp_content c ON c.site_id = il.site_id AND c.url_hash = il.target_url_hash
			 SET il.target_content_id = c.id WHERE il.site_id = ?',
			array( $site_id )
		);
		$db->query(
			'UPDATE wp_content c SET c.internal_links_in = (
				SELECT COUNT(DISTINCT il.source_content_id) FROM internal_links il WHERE il.site_id = c.site_id AND il.target_url_hash = c.url_hash AND il.source_content_id <> c.id
			 ) WHERE c.site_id = ?',
			array( $site_id )
		);
	}

	/**
	 * Host of a site.
	 *
	 * @param int $site_id Site.
	 * @return string
	 */
	private static function site_host( int $site_id ): string {
		$url = (string) Database::instance()->get_var( 'SELECT url FROM sites WHERE id = ?', array( $site_id ) );
		return (string) parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}
