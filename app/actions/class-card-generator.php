<?php
/**
 * OptiVio — Deterministic Action Cards generated from engine insights and SEO audit issues.
 * No AI: texts are templates; content fields can later be filled with "Generează cu AI".
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Actions;

use OptiVio\Core\Database;
use OptiVio\Engine\Insight_Store;
use OptiVio\Engine\Seo_Auditor;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Card generator.
 */
final class Card_Generator {

	private const MAX_NEW = 30;

	/**
	 * Generates cards for a business. Returns the number of new cards.
	 *
	 * @param int $business_id Business.
	 * @return int
	 */
	public static function run( int $business_id ): int {
		$created = 0;
		$add     = static function ( array $raw, string $key ) use ( $business_id, &$created ): void {
			if ( $created >= self::MAX_NEW ) {
				return;
			}
			$card = Cards::validate( $business_id, $raw );
			if ( null !== $card && null !== Cards::create( $business_id, $card, 'deterministic', null, $key ) ) {
				++$created;
			}
		};
		$insights = Insight_Store::active( $business_id, array_keys( Insight_Store::LABELS ), 200 );
		$hours    = array();

		foreach ( $insights as $i ) {
			$d = (array) $i['data'];
			switch ( $i['insight_type'] ) {
				case 'ads_wasted_term':
				case 'rejected_calls_keyword':
					$add(
						array(
							'category'  => 'ads_negative',
							'title'     => 'Cuvânt negativ: „' . $i['keyword'] . '”',
							'rationale' => (string) $d['action'] . ( isset( $d['ads_cost'] ) ? ' Cost 30 zile: ' . $d['ads_cost'] . '.' : '' ) . ( isset( $d['bad_share'] ) ? ' Apeluri refuzate/pierdute: ' . $d['bad_share'] . '%.' : '' ),
							'priority'  => 2,
							'impact'    => 6,
							'payload'   => array(
								'keywords'   => array( $i['keyword'] ),
								'match_type' => 'phrase',
								'scope'      => 'account',
							),
						),
						'ads_negative|' . mb_strtolower( (string) $i['keyword'] )
					);
					break;
				case 'ads_bad_hours':
					$hours[] = (int) $d['hour'];
					break;
				case 'paid_while_top_organic':
					$add(
						array(
							'category'  => 'ads_campaign',
							'title'     => 'Test reducere bid: „' . $i['keyword'] . '” (top ' . $d['organic_position'] . ' organic)',
							'rationale' => (string) $d['action'],
							'priority'  => 4,
							'impact'    => 4,
							'payload'   => array(
								'campaign' => '',
								'changes'  => array( 'Reduceți bid-ul pentru „' . $i['keyword'] . '” cu 30–50% timp de 14 zile', 'Comparați apelurile acceptate înainte/după' ),
							),
						),
						'paid_top|' . mb_strtolower( (string) $i['keyword'] )
					);
					break;
				case 'ads_converting_no_landing':
					$site = (int) Database::instance()->get_var( "SELECT id FROM sites WHERE business_id = ? AND connector_status = 'connected' ORDER BY id LIMIT 1", array( $business_id ) );
					$kw   = (string) $i['keyword'];
					$add(
						array(
							'category'  => 'new_landing',
							'title'     => 'Landing page SEO: „' . $kw . '”',
							'rationale' => (string) $d['action'] . ' Conversii Ads: ' . $d['ads_conversions'] . '.',
							'priority'  => 3,
							'impact'    => 7,
							'site_id'   => $site,
							'payload'   => array(
								'site_id'       => $site,
								'post_type'     => 'page',
								'title'         => self::ucfirst( $kw ),
								'slug'          => ov_slugify( $kw ),
								'focus_keyword' => $kw,
								'content_html'  => '',
								'images'        => array(
									array(
										'placement' => 'featured',
										'prompt'    => 'Realistic professional photo illustrating "' . $kw . '" for a local service business in Romania, natural light, no text',
										'alt'       => self::ucfirst( $kw ),
									),
								),
							),
						),
						'landing|' . mb_strtolower( $kw )
					);
					break;
			}
		}
		if ( ! empty( $hours ) ) {
			sort( $hours );
			$add(
				array(
					'category'  => 'ads_schedule',
					'title'     => 'Program reclame: reduceți orele ' . implode( ', ', array_map( static fn( $h ) => sprintf( '%02d', $h ), $hours ) ),
					'rationale' => 'În aceste intervale majoritatea apelurilor sunt refuzate sau pierdute.',
					'priority'  => 2,
					'impact'    => 6,
					'payload'   => array(
						'hours'          => $hours,
						'adjustment_pct' => -100,
						'days'           => 'toate',
					),
				),
				'schedule|' . implode( ',', $hours )
			);
		}

		self::from_audit( $business_id, $add );
		return $created;
	}

	/**
	 * Cards from SEO audit issues.
	 *
	 * @param int      $business_id Business.
	 * @param callable $add         Adder.
	 * @return void
	 */
	private static function from_audit( int $business_id, callable $add ): void {
		$db     = Database::instance();
		$issues = $db->get_results(
			"SELECT i.issue_code, i.content_id, i.site_id, c.title, c.url, m.seo_title, m.meta_description, m.focus_keyword
			 FROM seo_audit_issues i JOIN sites s ON s.id = i.site_id LEFT JOIN wp_content c ON c.id = i.content_id LEFT JOIN wp_seo_meta m ON m.content_id = c.id
			 WHERE s.business_id = ? AND i.resolved_at IS NULL ORDER BY FIELD(i.severity, 'critical', 'warning', 'info')",
			array( $business_id )
		);
		$meta_codes = array( 'title_missing', 'title_too_long', 'title_too_short', 'meta_missing', 'meta_too_long', 'meta_too_short', 'focus_not_in_title', 'title_duplicate', 'meta_duplicate' );
		$by_content = array();
		foreach ( $issues as $is ) {
			if ( in_array( $is['issue_code'], $meta_codes, true ) && null !== $is['content_id'] ) {
				$by_content[ (int) $is['content_id'] ]['row']     = $is;
				$by_content[ (int) $is['content_id'] ]['codes'][] = Seo_Auditor::ISSUES[ $is['issue_code'] ][1];
			} elseif ( 'orphan_page' === $is['issue_code'] && null !== $is['content_id'] ) {
				$hub = $db->get_row(
					"SELECT id, title FROM wp_content WHERE site_id = ? AND id <> ? AND object_type = 'post' AND status = 'publish' ORDER BY internal_links_in DESC, word_count DESC LIMIT 1",
					array( (int) $is['site_id'], (int) $is['content_id'] )
				);
				if ( null !== $hub ) {
					$add(
						array(
							'category'  => 'internal_link',
							'title'     => 'Link intern către pagina orfană „' . $is['title'] . '”',
							'rationale' => 'Nicio pagină nu trimite link către această pagină; propunere din pagina cea mai legată: „' . $hub['title'] . '”.',
							'priority'  => 5,
							'impact'    => 4,
							'payload'   => array(
								'source_content_id' => (int) $hub['id'],
								'target_content_id' => (int) $is['content_id'],
								'anchor_text'       => (string) $is['title'],
								'paragraph_html'    => '<p>Aflați mai multe despre {{link}}.</p>',
							),
						),
						'orphan|' . (int) $is['content_id']
					);
				}
			} elseif ( in_array( $is['issue_code'], array( 'psi_performance_poor', 'gtm_no_ads_conversion', 'gtm_no_ga4' ), true ) ) {
				$def = Seo_Auditor::ISSUES[ $is['issue_code'] ];
				$add(
					array(
						'category'  => 'technical',
						'title'     => $def[1],
						'rationale' => $def[2],
						'priority'  => 'critical' === $def[0] ? 1 : 3,
						'impact'    => 'critical' === $def[0] ? 8 : 5,
						'site_id'   => (int) $is['site_id'],
						'payload'   => array( 'steps' => array( $def[2] ) ),
					),
					'tech|' . $is['issue_code'] . '|' . (int) $is['site_id']
				);
			}
		}
		foreach ( $by_content as $cid => $entry ) {
			$r = $entry['row'];
			$add(
				array(
					'category'  => 'seo_meta',
					'title'     => 'Titlu & meta: „' . $r['title'] . '”',
					'rationale' => 'Probleme: ' . implode( ', ', array_unique( $entry['codes'] ) ) . '. Completați textele sau folosiți „Generează cu AI”.',
					'priority'  => 3,
					'impact'    => 5,
					'payload'   => array(
						'content_id'       => $cid,
						'seo_title'        => (string) $r['seo_title'],
						'meta_description' => (string) $r['meta_description'],
						'focus_keyword'    => (string) $r['focus_keyword'],
					),
				),
				'seo_meta|' . $cid
			);
		}
	}

	/**
	 * Multibyte ucfirst.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	private static function ucfirst( string $s ): string {
		return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
	}
}
