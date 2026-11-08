<?php
/**
 * OptiVio — Applies approved cards on the WordPress site (via Remote_Operations, with
 * snapshots) or records manual application; rejection → permanent business rule; rollback.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Actions;

use OptiVio\Core\Database;
use OptiVio\Repositories\Business_Children_Repository as Children;
use OptiVio\Services\Remote_Operations;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Card applier.
 */
final class Card_Applier {

	/**
	 * Applies a remote-capable card.
	 *
	 * @param array $card    Card (Cards::find()).
	 * @param int   $user_id User.
	 * @return array{ok:bool,error?:string}
	 */
	public static function apply( array $card, int $user_id ): array {
		if ( ! ( Cards::CATEGORIES[ $card['category'] ][1] ?? false ) ) {
			return array( 'ok' => false, 'error' => 'Această categorie se aplică manual.' );
		}
		$db = Database::instance();
		$db->update( 'action_cards', array( 'status' => 'applying', 'decided_by' => $user_id, 'decided_at' => Database::now() ), array( 'id' => (int) $card['id'] ) );

		try {
			$p = (array) $card['effective'];
			switch ( $card['category'] ) {
				case 'seo_meta':
					$c      = self::content( (int) $p['content_id'] );
					$fields = array_filter(
						array(
							'seo_title'        => $p['seo_title'],
							'meta_description' => $p['meta_description'],
							'focus_keyword'    => $p['focus_keyword'],
						),
						static fn( $v ) => '' !== (string) $v
					);
					if ( empty( $fields ) ) {
						throw new RuntimeException( 'Completați cel puțin titlul SEO sau meta descrierea.' );
					}
					self::op(
						$card,
						(int) $c['site_id'],
						'update_seo_meta',
						array(
							'object_type' => $c['object_type'],
							'id'          => (int) $c['wp_id'],
							'taxonomy'    => 'term' === $c['object_type'] ? $c['subtype'] : null,
							'fields'      => $fields,
						),
						$c,
						$user_id
					);
					break;
				case 'seo_content':
					$c = self::content( (int) $p['content_id'] );
					if ( '' === trim( strip_tags( (string) $p['content_html'] ) ) ) {
						throw new RuntimeException( 'Conținutul este gol.' );
					}
					self::op( $card, (int) $c['site_id'], 'update_post', array( 'id' => (int) $c['wp_id'], 'fields' => array( 'content' => $p['content_html'] ) ), $c, $user_id );
					break;
				case 'internal_link':
					$src  = self::content( (int) $p['source_content_id'] );
					$tgt  = self::content( (int) $p['target_content_id'] );
					$link = '<a href="' . htmlspecialchars( (string) $tgt['url'], ENT_QUOTES ) . '">' . htmlspecialchars( '' !== $p['anchor_text'] ? (string) $p['anchor_text'] : (string) $tgt['title'], ENT_QUOTES ) . '</a>';
					$html = str_contains( (string) $p['paragraph_html'], '{{link}}' ) ? str_replace( '{{link}}', $link, (string) $p['paragraph_html'] ) : '<p>' . $link . '</p>';
					self::op( $card, (int) $src['site_id'], 'update_post', array( 'id' => (int) $src['wp_id'], 'fields' => array( 'append' => $html ) ), $src, $user_id );
					break;
				default:
					self::create_content( $card, $p, $user_id );
			}
		} catch ( RuntimeException $e ) {
			$db->update( 'action_cards', array( 'status' => 'failed', 'applied_note' => mb_substr( $e->getMessage(), 0, 500 ) ), array( 'id' => (int) $card['id'] ) );
			return array( 'ok' => false, 'error' => $e->getMessage() );
		}

		$db->update( 'action_cards', array( 'status' => 'applied', 'applied_at' => Database::now(), 'applied_note' => null ), array( 'id' => (int) $card['id'] ) );
		return array( 'ok' => true );
	}

	/**
	 * Marks a manual card (Ads, GBP, technical) as applied.
	 *
	 * @param array  $card    Card.
	 * @param int    $user_id User.
	 * @param string $note    Note.
	 * @return void
	 */
	public static function mark_manual( array $card, int $user_id, string $note ): void {
		Database::instance()->update(
			'action_cards',
			array(
				'status'       => 'applied',
				'applied_at'   => Database::now(),
				'applied_note' => '' !== $note ? mb_substr( $note, 0, 500 ) : 'Aplicat manual.',
				'decided_by'   => $user_id,
				'decided_at'   => Database::now(),
			),
			array( 'id' => (int) $card['id'] )
		);
	}

	/**
	 * Rejects a card; the reason becomes a permanent business rule respected by future analyses.
	 *
	 * @param array  $card    Card.
	 * @param int    $user_id User.
	 * @param string $reason  Reason.
	 * @return void
	 */
	public static function reject( array $card, int $user_id, string $reason ): void {
		$db = Database::instance();
		$db->update(
			'action_cards',
			array(
				'status'           => 'rejected',
				'rejection_reason' => $reason,
				'decided_by'       => $user_id,
				'decided_at'       => Database::now(),
			),
			array( 'id' => (int) $card['id'] )
		);
		$category = str_starts_with( (string) $card['category'], 'ads_' ) ? 'ads' : ( str_starts_with( (string) $card['category'], 'seo_' ) || 'internal_link' === $card['category'] ? 'seo' : ( str_starts_with( (string) $card['category'], 'new_' ) ? 'content' : 'general' ) );
		Children::create(
			'business_rules',
			(int) $card['business_id'],
			array(
				'source'         => 'rejection',
				'action_card_id' => (int) $card['id'],
				'category'       => $category,
				'rule_text'      => mb_substr( 'Propunere respinsă: „' . $card['title'] . '”. Motiv: ' . $reason, 0, 1000 ),
				'is_active'      => 1,
				'created_by'     => $user_id,
			)
		);
	}

	/**
	 * Rolls back every applied remote operation of a card (newest first).
	 *
	 * @param array $card    Card.
	 * @param int   $user_id User.
	 * @return array{ok:bool,error?:string}
	 */
	public static function rollback( array $card, int $user_id ): array {
		$ops = Database::instance()->get_results(
			"SELECT * FROM remote_operations WHERE action_card_id = ? AND status = 'applied' AND remote_snapshot_id IS NOT NULL ORDER BY id DESC",
			array( (int) $card['id'] )
		);
		if ( empty( $ops ) ) {
			return array( 'ok' => false, 'error' => 'Nu există modificări aplicate de anulat.' );
		}
		foreach ( $ops as $op ) {
			$r = Remote_Operations::rollback( $op, $user_id );
			if ( ! $r['ok'] ) {
				return $r;
			}
		}
		Database::instance()->update( 'action_cards', array( 'status' => 'rolled_back' ), array( 'id' => (int) $card['id'] ) );
		return array( 'ok' => true );
	}

	/**
	 * New article / landing page: uploads images, replaces placeholders, creates the post.
	 *
	 * @param array $card    Card.
	 * @param array $p       Payload.
	 * @param int   $user_id User.
	 * @return void
	 * @throws RuntimeException On missing data or remote errors.
	 */
	private static function create_content( array $card, array $p, int $user_id ): void {
		if ( '' === trim( (string) $p['title'] ) || '' === trim( strip_tags( (string) $p['content_html'] ) ) ) {
			throw new RuntimeException( 'Titlul și conținutul sunt obligatorii (completați-le sau folosiți „Generează cu AI”).' );
		}
		$site_id  = (int) $p['site_id'];
		$html     = (string) $p['content_html'];
		$featured = 0;
		$images   = Database::instance()->get_results(
			'SELECT i.placement, i.alt_text, u.stored_name, u.original_name, u.mime FROM action_card_images i JOIN uploads u ON u.id = i.upload_id WHERE i.action_card_id = ?',
			array( (int) $card['id'] )
		);
		foreach ( $images as $img ) {
			$path = OPTIVIO_STORAGE . '/uploads/' . $img['stored_name'];
			if ( ! is_file( $path ) ) {
				continue;
			}
			$res = self::op(
				$card,
				$site_id,
				'upload_media',
				array(
					'filename'    => (string) $img['original_name'],
					'data_base64' => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					'alt'         => (string) $img['alt_text'],
					'title'       => (string) $card['title'],
				),
				null,
				$user_id,
				'attachment'
			);
			if ( 'featured' === $img['placement'] ) {
				$featured = (int) $res['object_id'];
			} else {
				$tag  = '<figure class="wp-block-image"><img src="' . htmlspecialchars( (string) $res['url'], ENT_QUOTES ) . '" alt="' . htmlspecialchars( (string) $img['alt_text'], ENT_QUOTES ) . '"></figure>';
				$mark = '{{image:' . $img['placement'] . '}}';
				// An edited / regenerated text may have lost the marker: the uploaded image is appended instead of dropped.
				$html = false !== strpos( $html, $mark ) ? str_replace( $mark, $tag, $html ) : $html . "
" . $tag;
			}
		}
		$html = (string) preg_replace( '/\{\{image:[a-z0-9_]+\}\}/', '', $html );

		self::op(
			$card,
			$site_id,
			'create_post',
			array(
				'post_type'      => $p['post_type'],
				'fields'         => array(
					'title'   => $p['title'],
					'slug'    => $p['slug'],
					'content' => $html,
					'status'  => 'publish' === $card['publish_status'] ? 'publish' : 'draft',
				),
				'seo'            => array_filter(
					array(
						'seo_title'        => $p['seo_title'],
						'meta_description' => $p['meta_description'],
						'focus_keyword'    => $p['focus_keyword'],
					),
					static fn( $v ) => '' !== (string) $v
				),
				'featured_media' => $featured,
			),
			null,
			$user_id
		);
	}

	/**
	 * Executes one remote operation tied to the card.
	 *
	 * @param array      $card        Card.
	 * @param int        $site_id     Site.
	 * @param string     $operation   Operation.
	 * @param array      $params      Params.
	 * @param array|null $content     Content row.
	 * @param int        $user_id     User.
	 * @param string     $object_type Object type.
	 * @return array Response.
	 * @throws RuntimeException On failure.
	 */
	private static function op( array $card, int $site_id, string $operation, array $params, ?array $content, int $user_id, string $object_type = 'post' ): array {
		if ( $site_id <= 0 || ! Cards::site_belongs( (int) $card['business_id'], $site_id ) ) {
			throw new RuntimeException( 'Site invalid pentru acest card.' );
		}
		$result = Remote_Operations::execute(
			$site_id,
			$operation,
			$params,
			$user_id,
			array(
				'action_card_id' => (int) $card['id'],
				'content_id'     => null !== $content ? (int) $content['id'] : null,
				'object_type'    => null !== $content ? (string) $content['object_type'] : $object_type,
				'taxonomy'       => null !== $content && 'term' === $content['object_type'] ? (string) $content['subtype'] : null,
			)
		);
		if ( ! $result['ok'] ) {
			throw new RuntimeException( (string) $result['error'] );
		}
		return $result['response'];
	}

	/**
	 * Mirrored content row.
	 *
	 * @param int $id Content id.
	 * @return array
	 * @throws RuntimeException When missing.
	 */
	private static function content( int $id ): array {
		$row = Database::instance()->get_row( 'SELECT id, site_id, object_type, subtype, wp_id, url, title FROM wp_content WHERE id = ?', array( $id ) );
		if ( null === $row ) {
			throw new RuntimeException( 'Pagina nu mai există în oglinda site-ului.' );
		}
		return $row;
	}
}
