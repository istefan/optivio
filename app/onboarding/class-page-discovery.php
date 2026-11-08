<?php
/**
 * OptiVio — Deterministic page discovery for onboarding (zero AI).
 *
 * robots.txt (Sitemap + Disallow for "*"), sitemap.xml / sitemap index / wp-sitemap.xml and the
 * navigation links of the home page. Same host only (www ignored). Every URL is classified
 * (home, service, pricing, about, contact, blog, legal, other) for the preselection.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Onboarding;

use OptiVio\Core\Config;
use OptiVio\Engine\Safe_Fetcher;
use OptiVio\Engine\Text;
use OptiVio\Services\Content_Ingestor;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Page discovery.
 */
final class Page_Discovery {

	/**
	 * Page types => label (UI) and preselection order.
	 */
	public const TYPES = array(
		'home'    => 'Pagina principală',
		'service' => 'Servicii / produse',
		'pricing' => 'Prețuri / oferte',
		'about'   => 'Despre',
		'contact' => 'Contact',
		'other'   => 'Alte pagini',
		'blog'    => 'Blog / articole',
		'legal'   => 'Legal / GDPR',
	);

	/**
	 * Maximum URLs collected per site before selection.
	 */
	private const MAX_CANDIDATES = 400;

	/**
	 * Whether local / private hosts may be fetched (development only).
	 *
	 * @return bool
	 */
	public static function allow_local(): bool {
		return Config::is_dev();
	}

	/**
	 * Normalizes user input to a site root (scheme://host[:port]).
	 *
	 * @param string $input Input.
	 * @return string|null
	 */
	public static function site_root( string $input ): ?string {
		$input = trim( $input );
		if ( '' === $input ) {
			return null;
		}
		if ( ! preg_match( '#^https?://#i', $input ) ) {
			$input = 'https://' . $input;
		}
		$p = parse_url( $input ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $p ) || empty( $p['host'] ) || isset( $p['user'] ) ) {
			return null;
		}
		$host = strtolower( (string) $p['host'] );
		$ok   = (bool) preg_match( '/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host ) || ( self::allow_local() && false !== filter_var( $host, FILTER_VALIDATE_IP ) );
		if ( ! $ok ) {
			return null;
		}
		return strtolower( (string) $p['scheme'] ) . '://' . $host . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
	}

	/**
	 * Host without www (with port when present).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function host_key( string $url ): string {
		$p = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! is_array( $p ) || empty( $p['host'] ) ) {
			return '';
		}
		return (string) preg_replace( '/^www\./', '', strtolower( (string) $p['host'] ) ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
	}

	/**
	 * Discovers the candidate pages of a site.
	 *
	 * @param string $root Site root (scheme://host).
	 * @return array{pages:array<int,array{url:string,title:string,source:string,type:string,lastmod:string}>,disallow:array<int,string>,home_ok:bool,errors:array<int,string>,root:string}
	 */
	public static function discover( string $root ): array {
		$local    = self::allow_local();
		$errors   = array();
		$disallow = array();
		$sitemaps = array();
		$pages    = array();

		$robots = Safe_Fetcher::get(
			$root . '/robots.txt',
			array(
				'accept'      => 'text',
				'allow_local' => $local,
			)
		);
		if ( $robots['ok'] ) {
			[ $sitemaps, $disallow ] = self::parse_robots( $robots['html'] );
		}

		// Home page first: it resolves redirects (http → https, www) and gives the menu.
		$home    = Safe_Fetcher::get( $root . '/', array( 'allow_local' => $local ) );
		$home_ok = $home['ok'];
		if ( $home_ok ) {
			$final = self::site_root( (string) $home['url'] );
			if ( null !== $final && self::host_key( $final ) === self::host_key( $root ) ) {
				$root = $final;
			}
			$self = $root . '/';
			self::add( $pages, $self, 'Pagina principală', 'home', '', $root );
			$extract = Page_Extractor::extract( $home['html'], (string) $home['url'] );
			foreach ( $extract['links'] as $link ) {
				if ( $link['nav'] ) {
					self::add( $pages, $link['url'], $link['anchor'], 'menu', '', $root );
				}
			}
		} else {
			$errors[] = 'Pagina principală nu a putut fi citită: ' . $home['error'];
		}

		if ( empty( $sitemaps ) ) {
			$sitemaps = array( $root . '/sitemap.xml', $root . '/sitemap_index.xml', $root . '/wp-sitemap.xml' );
		}
		$queue   = array_slice( array_values( array_unique( $sitemaps ) ), 0, 5 );
		$visited = 0;
		$found   = false;
		while ( ! empty( $queue ) && $visited < 15 && count( $pages ) < self::MAX_CANDIDATES ) {
			$map = (string) array_shift( $queue );
			if ( self::host_key( $map ) !== self::host_key( $root ) ) {
				continue;
			}
			++$visited;
			$res = Safe_Fetcher::get(
				$map,
				array(
					'accept'      => 'xml',
					'allow_local' => $local,
				)
			);
			if ( ! $res['ok'] ) {
				continue;
			}
			[ $children, $urls ] = self::parse_sitemap( $res['html'] );
			$found               = $found || ! empty( $children ) || ! empty( $urls );
			foreach ( $children as $child ) {
				// Skip taxonomy / author sitemaps: they rarely describe the business.
				if ( ! preg_match( '/(tag|author|user|category|taxonom|format)/i', $child ) ) {
					$queue[] = $child;
				}
			}
			$blogish = (bool) preg_match( '/post[s]?-sitemap|sitemap-posts|wp-sitemap-posts-post|posts?\.xml/i', $map );
			foreach ( $urls as $u ) {
				self::add( $pages, $u['loc'], '', 'sitemap', $u['lastmod'], $root, $blogish ? 'blog' : '' );
			}
		}
		if ( ! $found ) {
			$errors[] = 'Nu am găsit un sitemap XML; folosesc doar meniul paginii principale.';
		}

		return array(
			'pages'    => array_values( $pages ),
			'disallow' => $disallow,
			'home_ok'  => $home_ok,
			'errors'   => $errors,
			'root'     => $root,
		);
	}

	/**
	 * Adds a candidate (same host, HTML-looking, deduplicated by normalized URL).
	 *
	 * @param array  $pages   Pages (by reference, keyed by normalized URL).
	 * @param string $url     URL.
	 * @param string $title   Title / anchor.
	 * @param string $source  sitemap|menu|home|manual.
	 * @param string $lastmod Lastmod.
	 * @param string $root    Site root.
	 * @param string $type    Forced type ('' = classify).
	 * @return void
	 */
	private static function add( array &$pages, string $url, string $title, string $source, string $lastmod, string $root, string $type = '' ): void {
		if ( count( $pages ) >= self::MAX_CANDIDATES ) {
			return;
		}
		$url = self::clean_url( $url, $root );
		if ( null === $url ) {
			return;
		}
		$key = Content_Ingestor::normalize_url( $url );
		if ( isset( $pages[ $key ] ) ) {
			if ( '' === $pages[ $key ]['title'] && '' !== $title ) {
				$pages[ $key ]['title'] = $title;
			}
			if ( 'menu' === $source ) {
				$pages[ $key ]['source'] = 'menu';
			}
			return;
		}
		$pages[ $key ] = array(
			'url'     => $url,
			'title'   => mb_substr( $title, 0, 300 ),
			'source'  => 'home' === $type ? 'home' : $source,
			'type'    => '' !== $type ? $type : self::classify( $url, $title, $root ),
			'lastmod' => $lastmod,
		);
		if ( 'home' === $type ) {
			$pages[ $key ]['type'] = 'home';
		}
	}

	/**
	 * Validates a candidate URL (same host, no files, no admin/feeds/archives).
	 *
	 * @param string $url  URL.
	 * @param string $root Root.
	 * @return string|null
	 */
	public static function clean_url( string $url, string $root ): ?string {
		$url = trim( (string) strtok( $url, '#' ) );
		if ( '' === $url || self::host_key( $url ) !== self::host_key( $root ) ) {
			return null;
		}
		$path = strtolower( (string) parse_url( $url, PHP_URL_PATH ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( preg_match( '/\.(jpe?g|png|gif|webp|svg|pdf|zip|docx?|xlsx?|mp4|mp3|xml|txt|css|js|ico|avif)$/', $path ) ) {
			return null;
		}
		if ( preg_match( '#/(wp-admin|wp-login|wp-json|feed|comments|cart|cos|checkout|my-account|contul-meu|login|autentificare|xmlrpc)\b|/page/\d+|/author/|/tag/|\?(s|replytocom|add-to-cart)=#', $path . '?' . (string) parse_url( $url, PHP_URL_QUERY ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			return null;
		}
		$query = (string) parse_url( $url, PHP_URL_QUERY ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( '' !== $query && ! preg_match( '/^(p|page_id)=\d+$/', $query ) ) {
			$url = (string) strtok( $url, '?' );
		}
		return $url;
	}

	/**
	 * Classifies a page by URL and title.
	 *
	 * @param string $url   URL.
	 * @param string $title Title.
	 * @param string $root  Site root.
	 * @return string
	 */
	public static function classify( string $url, string $title, string $root ): string {
		$path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$q    = (string) parse_url( $url, PHP_URL_QUERY ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( '' === $path && '' === $q && self::host_key( $url ) === self::host_key( $root ) ) {
			return 'home';
		}
		$hay = Text::normalize( str_replace( array( '/', '-', '_' ), ' ', $path ) . ' ' . $title );
		$map = array(
			'legal'   => '/\b(politica|confidentialitate|gdpr|cookie|cookies|termeni|conditii|privacy|terms|anpc|sal|sol|dezabonare)\b/',
			'contact' => '/\b(contact|contacteaza|contactati|unde ne gasiti|locatie)\b/',
			'about'   => '/\b(despre|about|echipa|cine suntem|povestea|misiune|istoric)\b/',
			'pricing' => '/\b(pret|preturi|tarif|tarife|pricing|lista de preturi|oferta|oferte|abonament|abonamente|pachete)\b/',
			'blog'    => '/\b(blog|articole|articol|noutati|stiri|news|sfaturi|ghid|ghiduri)\b/',
			'service' => '/\b(servicii|serviciu|services|service|reparatii|reparatie|montaj|instalare|instalatii|intretinere|interventii|interventie|desfundare|curatare|produse|produs|solutii|lucrari|categorie)\b/',
		);
		foreach ( $map as $type => $re ) {
			if ( preg_match( $re, $hay ) ) {
				return $type;
			}
		}
		if ( preg_match( '#^\d{4}/\d{2}/#', $path ) ) {
			return 'blog';
		}
		return substr_count( $path, '/' ) <= 1 ? 'service' : 'other';
	}

	/**
	 * Marks the preselected pages (sets 'selected' => bool).
	 *
	 * @param array $pages Pages of one site.
	 * @param int   $max   Max pages.
	 * @return array
	 */
	public static function preselect( array $pages, int $max ): array {
		$order = array_flip( array_keys( self::TYPES ) );
		usort(
			$pages,
			static function ( array $a, array $b ) use ( $order ): int {
				$ta = $order[ $a['type'] ] ?? 9;
				$tb = $order[ $b['type'] ] ?? 9;
				if ( $ta !== $tb ) {
					return $ta <=> $tb;
				}
				if ( ( 'menu' === $a['source'] ) !== ( 'menu' === $b['source'] ) ) {
					return 'menu' === $a['source'] ? -1 : 1;
				}
				return strcmp( (string) $b['lastmod'], (string) $a['lastmod'] );
			}
		);
		$taken = 0;
		$blogs = 0;
		foreach ( $pages as &$page ) {
			$want = 'legal' !== $page['type'] && ( 'blog' !== $page['type'] || $blogs < 5 );
			if ( $want && $taken < $max ) {
				$page['selected'] = true;
				++$taken;
				if ( 'blog' === $page['type'] ) {
					++$blogs;
				}
			} else {
				$page['selected'] = false;
			}
		}
		unset( $page );
		return $pages;
	}

	/**
	 * Parses robots.txt: Sitemap lines and Disallow rules of "User-agent: *".
	 *
	 * @param string $txt Content.
	 * @return array{0:array<int,string>,1:array<int,string>}
	 */
	public static function parse_robots( string $txt ): array {
		$sitemaps = array();
		$disallow = array();
		$applies  = false;
		$in_group = false;
		foreach ( preg_split( '/\r\n|\r|\n/', $txt ) as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			[ $field, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
			$field             = strtolower( $field );
			if ( 'sitemap' === $field ) {
				$sitemaps[] = $value;
			} elseif ( 'user-agent' === $field ) {
				$applies  = $in_group ? ( '*' === $value ) : ( $applies || '*' === $value );
				$in_group = false;
			} elseif ( 'disallow' === $field ) {
				$in_group = true;
				if ( $applies && '' !== $value && count( $disallow ) < 100 ) {
					$disallow[] = $value;
				}
			} else {
				$in_group = true;
			}
		}
		return array( array_slice( $sitemaps, 0, 10 ), array_values( array_unique( $disallow ) ) );
	}

	/**
	 * Whether a URL is blocked by Disallow rules (prefix match, * and $ supported).
	 *
	 * @param string $url   URL.
	 * @param array  $rules Disallow paths.
	 * @return bool
	 */
	public static function is_disallowed( string $url, array $rules ): bool {
		$path = (string) parse_url( $url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = '' === $path ? '/' : $path;
		$q    = (string) parse_url( $url, PHP_URL_QUERY ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = '' !== $q ? $path . '?' . $q : $path;
		foreach ( $rules as $rule ) {
			$re = '#^' . str_replace( array( '\*', '\$' ), array( '.*', '$' ), preg_quote( (string) $rule, '#' ) ) . '#';
			if ( preg_match( $re, $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parses a sitemap or sitemap index.
	 *
	 * @param string $xml XML.
	 * @return array{0:array<int,string>,1:array<int,array{loc:string,lastmod:string}>}
	 */
	public static function parse_sitemap( string $xml ): array {
		$children = array();
		$urls     = array();
		if ( '' === trim( $xml ) ) {
			return array( $children, $urls );
		}
		libxml_use_internal_errors( true );
		$doc = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		if ( false === $doc ) {
			return array( $children, $urls );
		}
		$name = strtolower( $doc->getName() );
		foreach ( $doc->children() as $node ) {
			$loc = trim( (string) $node->loc );
			if ( '' === $loc ) {
				continue;
			}
			if ( 'sitemapindex' === $name ) {
				$children[] = $loc;
			} elseif ( count( $urls ) < self::MAX_CANDIDATES ) {
				$urls[] = array(
					'loc'     => $loc,
					'lastmod' => mb_substr( trim( (string) $node->lastmod ), 0, 25 ),
				);
			}
		}
		return array( $children, $urls );
	}
}
