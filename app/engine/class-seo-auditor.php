<?php
/**
 * OptiVio — Deterministic on-page / technical SEO audit of a site (zero AI).
 *
 * Sources: mirrored WordPress content + Yoast meta (Stage 4), latest PageSpeed audit and
 * GTM snapshot (Stage 5). Issues are upserted by hash; issues no longer detected are resolved.
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
 * SEO auditor.
 */
final class Seo_Auditor {

	/**
	 * Issue catalogue: code => [severity, label, recommendation].
	 */
	public const ISSUES = array(
		'title_missing'             => array( 'critical', 'Titlu SEO lipsă', 'Setați un titlu SEO unic de 30–60 de caractere cu cuvântul cheie principal la început.' ),
		'title_too_long'            => array( 'warning', 'Titlu SEO prea lung', 'Scurtați titlul sub 60 de caractere; Google îl trunchiază.' ),
		'title_too_short'           => array( 'info', 'Titlu SEO prea scurt', 'Extindeți titlul (30–60 caractere) cu beneficiu / localitate.' ),
		'title_duplicate'           => array( 'warning', 'Titlu SEO duplicat', 'Fiecare pagină trebuie să aibă un titlu unic.' ),
		'meta_missing'              => array( 'warning', 'Meta descriere lipsă', 'Scrieți o meta descriere de 70–160 de caractere cu un îndemn la acțiune.' ),
		'meta_too_long'             => array( 'info', 'Meta descriere prea lungă', 'Reduceți sub 160 de caractere.' ),
		'meta_too_short'            => array( 'info', 'Meta descriere prea scurtă', 'Extindeți la 70–160 de caractere.' ),
		'meta_duplicate'            => array( 'info', 'Meta descriere duplicată', 'Descrieri unice pentru fiecare pagină.' ),
		'h1_missing'                => array( 'warning', 'H1 lipsă', 'Adăugați un singur titlu H1 care conține subiectul paginii.' ),
		'h1_multiple'               => array( 'info', 'Mai multe H1', 'Păstrați un singur H1; restul ca H2.' ),
		'thin_content'              => array( 'warning', 'Conținut subțire', 'Sub 300 de cuvinte: extindeți cu răspunsuri la întrebările clienților, prețuri, zonă, garanții.' ),
		'images_missing_alt'        => array( 'info', 'Imagini fără ALT', 'Adăugați text alternativ descriptiv imaginilor.' ),
		'orphan_page'               => array( 'warning', 'Pagină orfană', 'Nicio pagină nu trimite link intern către ea; adăugați linkuri din pagini relevante.' ),
		'noindex_published'         => array( 'info', 'Pagină publicată cu noindex', 'Verificați dacă excluderea din Google este intenționată.' ),
		'focus_missing'             => array( 'info', 'Cuvânt cheie focus nesetat', 'Setați cuvântul cheie principal în Yoast.' ),
		'focus_not_in_title'        => array( 'warning', 'Cuvântul focus lipsește din titlu', 'Includeți cuvântul cheie focus în titlul SEO.' ),
		'psi_performance_poor'      => array( 'critical', 'Performanță mobil slabă', 'Scor PageSpeed sub 50: optimizați imaginile, JS/CSS care blochează redarea și serverul.' ),
		'psi_performance_average'   => array( 'warning', 'Performanță mobil medie', 'Scor PageSpeed între 50 și 89.' ),
		'psi_lcp_slow'              => array( 'warning', 'LCP lent', 'Largest Contentful Paint peste 2,5 s.' ),
		'psi_cls_high'              => array( 'warning', 'Instabilitate vizuală (CLS)', 'CLS peste 0,1: rezervați dimensiuni pentru imagini și reclame.' ),
		'gtm_no_ga4'                => array( 'warning', 'GTM fără tag GA4', 'Containerul publicat nu conține tag Google / GA4.' ),
		'gtm_no_ads_conversion'     => array( 'critical', 'Lipsesc conversiile Google Ads în GTM', 'Campaniile Ads rulează fără tag de conversie: algoritmul nu poate optimiza.' ),
	);

	/**
	 * Audits a site. Returns the number of open issues.
	 *
	 * @param int $site_id Site.
	 * @return int
	 */
	public static function run( int $site_id ): int {
		$db     = Database::instance();
		$start  = gmdate( 'Y-m-d H:i:s' );
		$issues = array();
		$rows   = $db->get_results(
			"SELECT c.id, c.object_type, c.subtype, c.status, c.url, c.title, c.word_count, c.h1_count, c.images_count, c.images_missing_alt, c.internal_links_in,
				m.seo_title, m.seo_title_resolved, m.meta_description, m.meta_description_resolved, m.focus_keyword, m.robots_noindex
			 FROM wp_content c LEFT JOIN wp_seo_meta m ON m.content_id = c.id
			 WHERE c.site_id = ? AND c.status = 'publish'",
			array( $site_id )
		);

		$titles = array();
		$metas  = array();
		foreach ( $rows as $r ) {
			$title = trim( (string) ( $r['seo_title_resolved'] ?? $r['seo_title'] ?? $r['title'] ) );
			$meta  = trim( (string) ( $r['meta_description_resolved'] ?? $r['meta_description'] ?? '' ) );
			if ( '' !== $title ) {
				$titles[ Text::normalize( $title ) ][] = (int) $r['id'];
			}
			if ( '' !== $meta ) {
				$metas[ Text::normalize( $meta ) ][] = (int) $r['id'];
			}
		}

		foreach ( $rows as $r ) {
			$id     = (int) $r['id'];
			$is_doc = 'post' === $r['object_type'];
			$title  = trim( (string) ( $r['seo_title_resolved'] ?? $r['seo_title'] ?? $r['title'] ) );
			$meta   = trim( (string) ( $r['meta_description_resolved'] ?? $r['meta_description'] ?? '' ) );
			$tlen   = mb_strlen( $title );
			$mlen   = mb_strlen( $meta );
			$home   = (bool) preg_match( '#^https?://[^/?]+/?$#', (string) $r['url'] );
			$add    = static function ( string $code, array $details = array() ) use ( &$issues, $id ): void {
				$issues[] = array( $id, $code, $details );
			};

			if ( ! empty( $r['robots_noindex'] ) ) {
				$add( 'noindex_published' );
				continue;
			}
			if ( 0 === $tlen ) {
				$add( 'title_missing' );
			} elseif ( $tlen > 60 ) {
				$add( 'title_too_long', array( 'length' => $tlen, 'value' => $title ) );
			} elseif ( $tlen < 30 ) {
				$add( 'title_too_short', array( 'length' => $tlen, 'value' => $title ) );
			}
			if ( $tlen > 0 && count( $titles[ Text::normalize( $title ) ] ?? array() ) > 1 ) {
				$add( 'title_duplicate', array( 'value' => $title, 'others' => array_values( array_diff( $titles[ Text::normalize( $title ) ], array( $id ) ) ) ) );
			}
			if ( 0 === $mlen ) {
				$add( 'meta_missing' );
			} elseif ( $mlen > 160 ) {
				$add( 'meta_too_long', array( 'length' => $mlen ) );
			} elseif ( $mlen < 70 ) {
				$add( 'meta_too_short', array( 'length' => $mlen ) );
			}
			if ( $mlen > 0 && count( $metas[ Text::normalize( $meta ) ] ?? array() ) > 1 ) {
				$add( 'meta_duplicate', array( 'others' => array_values( array_diff( $metas[ Text::normalize( $meta ) ], array( $id ) ) ) ) );
			}
			if ( $is_doc ) {
				if ( 0 === (int) $r['h1_count'] ) {
					$add( 'h1_missing' );
				} elseif ( (int) $r['h1_count'] > 1 ) {
					$add( 'h1_multiple', array( 'count' => (int) $r['h1_count'] ) );
				}
				if ( (int) $r['word_count'] < 300 ) {
					$add( 'thin_content', array( 'words' => (int) $r['word_count'] ) );
				}
				if ( 0 === (int) $r['internal_links_in'] && ! $home ) {
					$add( 'orphan_page' );
				}
				$focus = trim( (string) $r['focus_keyword'] );
				if ( '' === $focus && 'page' === $r['subtype'] ) {
					$add( 'focus_missing' );
				} elseif ( '' !== $focus && $tlen > 0 && ! Text::contains_phrase( $focus, $title ) ) {
					$add( 'focus_not_in_title', array( 'focus' => $focus, 'title' => $title ) );
				}
			}
			if ( (int) $r['images_missing_alt'] > 0 ) {
				$add( 'images_missing_alt', array( 'count' => (int) $r['images_missing_alt'], 'total' => (int) $r['images_count'] ) );
			}
		}

		$psi = $db->get_row( 'SELECT * FROM psi_audits WHERE site_id = ? ORDER BY id DESC LIMIT 1', array( $site_id ) );
		if ( null !== $psi && null !== $psi['performance_score'] ) {
			$score = (int) $psi['performance_score'];
			if ( $score < 50 ) {
				$issues[] = array( null, 'psi_performance_poor', array( 'score' => $score ) );
			} elseif ( $score < 90 ) {
				$issues[] = array( null, 'psi_performance_average', array( 'score' => $score ) );
			}
			if ( null !== $psi['lcp_ms'] && (int) $psi['lcp_ms'] > 2500 ) {
				$issues[] = array( null, 'psi_lcp_slow', array( 'lcp_ms' => (int) $psi['lcp_ms'] ) );
			}
			if ( null !== $psi['cls'] && (float) $psi['cls'] > 0.1 ) {
				$issues[] = array( null, 'psi_cls_high', array( 'cls' => (float) $psi['cls'] ) );
			}
		}
		$gtm = $db->get_row( 'SELECT summary FROM gtm_snapshots WHERE site_id = ? ORDER BY id DESC LIMIT 1', array( $site_id ) );
		if ( null !== $gtm ) {
			$sum = (array) json_decode( (string) $gtm['summary'], true );
			if ( empty( $sum['has_ga4'] ) ) {
				$issues[] = array( null, 'gtm_no_ga4', array() );
			}
			$has_ads = (int) $db->get_var( "SELECT COUNT(*) FROM ads_campaigns WHERE site_id = ? AND status = 'ENABLED'", array( $site_id ) ) > 0;
			if ( $has_ads && empty( $sum['has_ads_conv'] ) ) {
				$issues[] = array( null, 'gtm_no_ads_conversion', array() );
			}
		}

		foreach ( $issues as [ $content_id, $code, $details ] ) {
			$db->query(
				'INSERT INTO seo_audit_issues (site_id, content_id, issue_code, issue_hash, severity, details, detected_at, resolved_at)
				 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), NULL)
				 ON DUPLICATE KEY UPDATE severity = VALUES(severity), details = VALUES(details), detected_at = UTC_TIMESTAMP(), resolved_at = NULL',
				array( $site_id, $content_id, $code, hash( 'sha256', $code . '|' . (int) $content_id ), self::ISSUES[ $code ][0], json_encode( $details, JSON_UNESCAPED_UNICODE ) )
			);
		}
		$db->query( 'UPDATE seo_audit_issues SET resolved_at = UTC_TIMESTAMP() WHERE site_id = ? AND resolved_at IS NULL AND detected_at < ?', array( $site_id, $start ) );
		return count( $issues );
	}
}
