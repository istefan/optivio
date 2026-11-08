<?php
/**
 * OptiVio — Action Cards: categories, strict payload validation and storage.
 *
 * Every payload (from AI or the deterministic generator or the user's edit) passes through
 * Cards::validate(): unknown categories are dropped, ids must belong to the business,
 * strings are bounded and sanitized. HTML is additionally filtered by KSES in WordPress.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Actions;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Cards repository + validator.
 */
final class Cards {

	/**
	 * Categories: key => [label, applies remotely on the WordPress site].
	 */
	public const CATEGORIES = array(
		'seo_meta'         => array( 'SEO: titlu & meta', true ),
		'seo_content'      => array( 'SEO: rescriere conținut', true ),
		'internal_link'    => array( 'Link intern', true ),
		'new_article'      => array( 'Articol nou', true ),
		'new_landing'      => array( 'Landing page nou', true ),
		'ads_negative'     => array( 'Ads: cuvinte negative', false ),
		'ads_schedule'     => array( 'Ads: program reclame', false ),
		'ads_campaign'     => array( 'Ads: modificare campanie', false ),
		'ads_new_campaign' => array( 'Ads: campanie nouă', false ),
		'gbp'              => array( 'Google Business Profile', false ),
		'technical'        => array( 'Tehnic', false ),
		'other'            => array( 'Altele', false ),
	);

	/**
	 * Status labels.
	 */
	public const STATUSES = array(
		'proposed'    => array( '', 'propus' ),
		'approved'    => array( 'ov-badge-warning', 'aprobat' ),
		'applying'    => array( 'ov-badge-warning', 'se aplică' ),
		'applied'     => array( 'ov-badge-success', 'aplicat' ),
		'failed'      => array( 'ov-badge-danger', 'eșuat' ),
		'rejected'    => array( 'ov-badge-outline', 'respins' ),
		'rolled_back' => array( 'ov-badge-outline', 'anulat (rollback)' ),
	);

	/**
	 * Validates a card. Returns the clean card or null.
	 *
	 * @param int   $business_id Business.
	 * @param array $card        Raw card (category, title, rationale, priority, impact, site_id, payload).
	 * @return array|null
	 */
	public static function validate( int $business_id, array $card ): ?array {
		$category = (string) ( $card['category'] ?? '' );
		if ( ! isset( self::CATEGORIES[ $category ] ) ) {
			return null;
		}
		$title = mb_substr( sanitize_text_field( (string) ( $card['title'] ?? '' ) ), 0, 255 );
		if ( '' === $title ) {
			return null;
		}
		$payload = self::payload( $business_id, $category, is_array( $card['payload'] ?? null ) ? $card['payload'] : array() );
		if ( null === $payload ) {
			return null;
		}
		$site_id = absint( $card['site_id'] ?? ( $payload['site_id'] ?? 0 ) );
		if ( isset( $payload['content_id'] ) ) {
			$site_id = self::content_site( (int) $payload['content_id'] );
		} elseif ( isset( $payload['source_content_id'] ) ) {
			$site_id = self::content_site( (int) $payload['source_content_id'] );
		}
		if ( $site_id > 0 && ! self::site_belongs( $business_id, $site_id ) ) {
			$site_id = 0;
		}
		return array(
			'category'  => $category,
			'title'     => $title,
			'rationale' => mb_substr( sanitize_textarea_field( (string) ( $card['rationale'] ?? '' ) ), 0, 3000 ),
			'priority'  => max( 1, min( 9, absint( $card['priority'] ?? 5 ) ) ),
			'impact'    => max( 1, min( 10, absint( $card['impact'] ?? 5 ) ) ),
			'site_id'   => $site_id > 0 ? $site_id : null,
			'payload'   => $payload,
		);
	}

	/**
	 * Inserts a validated card (deduplicated by hash; rejected/applied cards are never recreated).
	 *
	 * @param int      $business_id Business.
	 * @param array    $card        Validated card.
	 * @param string   $origin      deterministic|ai.
	 * @param int|null $analysis_id Analysis.
	 * @param string   $hash_key    Dedup key (defaults to category|title).
	 * @return int|null Card id or null when deduplicated.
	 */
	public static function create( int $business_id, array $card, string $origin, ?int $analysis_id, string $hash_key = '' ): ?int {
		$db   = Database::instance();
		$hash = hash( 'sha256', '' !== $hash_key ? $hash_key : $card['category'] . '|' . mb_strtolower( $card['title'] ) );
		if ( null !== $db->get_var( 'SELECT id FROM action_cards WHERE business_id = ? AND card_hash = ?', array( $business_id, $hash ) ) ) {
			return null;
		}
		$id = $db->insert(
			'action_cards',
			array(
				'business_id'  => $business_id,
				'site_id'      => $card['site_id'],
				'analysis_id'  => $analysis_id,
				'origin'       => $origin,
				'category'     => $card['category'],
				'title'        => $card['title'],
				'rationale'    => '' !== $card['rationale'] ? $card['rationale'] : null,
				'payload'      => json_encode( $card['payload'], JSON_UNESCAPED_UNICODE ),
				'priority'     => $card['priority'],
				'impact_score' => $card['impact'],
				'card_hash'    => $hash,
			)
		);
		foreach ( (array) ( $card['payload']['images'] ?? array() ) as $img ) {
			$db->insert(
				'action_card_images',
				array(
					'action_card_id' => $id,
					'placement'      => $img['placement'],
					'prompt_text'    => $img['prompt'],
					'alt_text'       => $img['alt'],
				)
			);
		}
		return $id;
	}

	/**
	 * Card of a business (scoped).
	 *
	 * @param int $business_id Business.
	 * @param int $id          Card id.
	 * @return array|null
	 */
	public static function find( int $business_id, int $id ): ?array {
		$row = Database::instance()->get_row( 'SELECT * FROM action_cards WHERE id = ? AND business_id = ?', array( $id, $business_id ) );
		if ( null !== $row ) {
			$row['payload']        = (array) ( json_decode( (string) $row['payload'], true ) ?: array() );
			$row['edited_payload'] = null !== $row['edited_payload'] ? (array) json_decode( (string) $row['edited_payload'], true ) : null;
			$row['effective']      = $row['edited_payload'] ?? $row['payload'];
		}
		return $row;
	}

	/**
	 * Validates a payload for a category.
	 *
	 * @param int    $business_id Business.
	 * @param string $category    Category.
	 * @param array  $p           Raw payload.
	 * @return array|null
	 */
	public static function payload( int $business_id, string $category, array $p ): ?array {
		$text = static function ( $v, int $max ): string {
			return mb_substr( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ), 0, $max );
		};
		$html = static function ( $v ): string {
			$v = is_string( $v ) ? $v : '';
			$v = (string) preg_replace( '#<(script|style|iframe|object|embed|form)\b.*?</\1>#is', '', $v );
			$v = (string) preg_replace( '#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $v );
			$v = (string) preg_replace( '#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $v );
			return mb_substr( $v, 0, 60000 );
		};
		$list = static function ( $v, int $max_items, int $max_len ) use ( $text ): array {
			$out = array();
			foreach ( array_slice( is_array( $v ) ? $v : array(), 0, $max_items ) as $item ) {
				$item = $text( $item, $max_len );
				if ( '' !== $item ) {
					$out[] = $item;
				}
			}
			return $out;
		};

		switch ( $category ) {
			case 'seo_meta':
				$cid = absint( $p['content_id'] ?? 0 );
				if ( ! self::content_belongs( $business_id, $cid ) ) {
					return null;
				}
				return array(
					'content_id'       => $cid,
					'seo_title'        => $text( $p['seo_title'] ?? '', 300 ),
					'meta_description' => $text( $p['meta_description'] ?? '', 500 ),
					'focus_keyword'    => $text( $p['focus_keyword'] ?? '', 190 ),
				);
			case 'seo_content':
				$cid = absint( $p['content_id'] ?? 0 );
				return self::content_belongs( $business_id, $cid ) ? array(
					'content_id'   => $cid,
					'content_html' => $html( $p['content_html'] ?? '' ),
				) : null;
			case 'internal_link':
				$src = absint( $p['source_content_id'] ?? 0 );
				$tgt = absint( $p['target_content_id'] ?? 0 );
				if ( $src === $tgt || ! self::content_belongs( $business_id, $src ) || ! self::content_belongs( $business_id, $tgt ) ) {
					return null;
				}
				return array(
					'source_content_id' => $src,
					'target_content_id' => $tgt,
					'anchor_text'       => $text( $p['anchor_text'] ?? '', 120 ),
					'paragraph_html'    => $html( $p['paragraph_html'] ?? '<p>{{link}}</p>' ),
				);
			case 'new_article':
			case 'new_landing':
				$site = absint( $p['site_id'] ?? 0 );
				if ( ! self::site_belongs( $business_id, $site ) ) {
					return null;
				}
				$images = array();
				foreach ( array_slice( is_array( $p['images'] ?? null ) ? $p['images'] : array(), 0, 6 ) as $img ) {
					$placement = sanitize_key( (string) ( $img['placement'] ?? '' ) );
					if ( preg_match( '/^(featured|inline_[1-5])$/', $placement ) ) {
						$images[] = array(
							'placement' => $placement,
							'prompt'    => mb_substr( sanitize_textarea_field( (string) ( $img['prompt'] ?? '' ) ), 0, 2000 ),
							'alt'       => $text( $img['alt'] ?? '', 255 ),
						);
					}
				}
				return array(
					'site_id'          => $site,
					'post_type'        => 'page' === ( $p['post_type'] ?? '' ) || 'new_landing' === $category ? 'page' : 'post',
					'title'            => $text( $p['title'] ?? '', 200 ),
					'slug'             => sanitize_key( str_replace( ' ', '-', (string) ( $p['slug'] ?? '' ) ) ),
					'seo_title'        => $text( $p['seo_title'] ?? '', 300 ),
					'meta_description' => $text( $p['meta_description'] ?? '', 500 ),
					'focus_keyword'    => $text( $p['focus_keyword'] ?? '', 190 ),
					'content_html'     => $html( $p['content_html'] ?? '' ),
					'images'           => $images,
				);
			case 'ads_negative':
				$kws = $list( $p['keywords'] ?? array(), 50, 80 );
				return empty( $kws ) ? null : array(
					'keywords'   => $kws,
					'match_type' => in_array( $p['match_type'] ?? '', array( 'exact', 'phrase', 'broad' ), true ) ? $p['match_type'] : 'phrase',
					'scope'      => 'campaign' === ( $p['scope'] ?? '' ) ? 'campaign' : 'account',
					'campaign'   => $text( $p['campaign'] ?? '', 255 ),
				);
			case 'ads_schedule':
				$hours = array_values( array_unique( array_filter( array_map( 'intval', is_array( $p['hours'] ?? null ) ? $p['hours'] : array() ), static fn( $h ) => $h >= 0 && $h <= 23 ) ) );
				return empty( $hours ) ? null : array(
					'hours'          => $hours,
					'adjustment_pct' => max( -100, min( 0, (int) ( $p['adjustment_pct'] ?? -100 ) ) ),
					'days'           => $text( $p['days'] ?? 'toate', 40 ),
				);
			case 'ads_campaign':
			case 'ads_new_campaign':
				return array(
					'campaign' => $text( $p['campaign'] ?? '', 255 ),
					'changes'  => $list( $p['changes'] ?? array(), 20, 500 ),
				);
			default:
				return array( 'steps' => $list( $p['steps'] ?? array(), 20, 500 ) );
		}
	}

	/**
	 * Whether a mirrored content item belongs to the business.
	 *
	 * @param int $business_id Business.
	 * @param int $content_id  Content.
	 * @return bool
	 */
	public static function content_belongs( int $business_id, int $content_id ): bool {
		return $content_id > 0 && null !== Database::instance()->get_var(
			'SELECT c.id FROM wp_content c JOIN sites s ON s.id = c.site_id WHERE c.id = ? AND s.business_id = ?',
			array( $content_id, $business_id )
		);
	}

	/**
	 * Whether a site belongs to the business.
	 *
	 * @param int $business_id Business.
	 * @param int $site_id     Site.
	 * @return bool
	 */
	public static function site_belongs( int $business_id, int $site_id ): bool {
		return $site_id > 0 && null !== Database::instance()->get_var( 'SELECT id FROM sites WHERE id = ? AND business_id = ?', array( $site_id, $business_id ) );
	}

	/**
	 * Site of a content item.
	 *
	 * @param int $content_id Content.
	 * @return int
	 */
	private static function content_site( int $content_id ): int {
		return (int) Database::instance()->get_var( 'SELECT site_id FROM wp_content WHERE id = ?', array( $content_id ) );
	}
}
