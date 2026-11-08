<?php
/**
 * Content mirror: posts, pages, public custom post types and taxonomy terms with Yoast SEO data.
 *
 * Full sync in background batches (WP-Cron) + incremental sync on every change.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Content module.
 */
final class OVC_Content {

	const BATCH     = 40;
	const MAX_HTML  = 300000;
	const STATUSES  = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Yoast post meta keys => payload keys.
	 */
	const YOAST_POST_META = array(
		'_yoast_wpseo_title'                  => 'seo_title',
		'_yoast_wpseo_metadesc'               => 'meta_description',
		'_yoast_wpseo_focuskw'                => 'focus_keyword',
		'_yoast_wpseo_canonical'              => 'canonical',
		'_yoast_wpseo_meta-robots-noindex'    => 'noindex',
		'_yoast_wpseo_meta-robots-nofollow'   => 'nofollow',
		'_yoast_wpseo_opengraph-title'        => 'og_title',
		'_yoast_wpseo_opengraph-description'  => 'og_description',
		'_yoast_wpseo_opengraph-image'        => 'og_image',
		'_yoast_wpseo_linkdex'                => 'seo_score',
		'_yoast_wpseo_content_score'          => 'read_score',
	);

	/**
	 * Yoast term meta keys => payload keys.
	 */
	const YOAST_TERM_META = array(
		'wpseo_title'         => 'seo_title',
		'wpseo_desc'          => 'meta_description',
		'wpseo_focuskw'       => 'focus_keyword',
		'wpseo_canonical'     => 'canonical',
		'wpseo_noindex'       => 'noindex',
		'wpseo_linkdex'       => 'seo_score',
		'wpseo_content_score' => 'read_score',
	);

	/**
	 * Objects already queued in this request.
	 *
	 * @var array<string,bool>
	 */
	private static $queued = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_post_saved' ), 20, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_deleted' ) );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_deleted' ) );
		add_action( 'saved_term', array( __CLASS__, 'on_term_saved' ), 20, 3 );
		add_action( 'pre_delete_term', array( __CLASS__, 'on_term_deleted' ), 10, 2 );
		add_action( 'ovc_content_full_sync', array( __CLASS__, 'start_full_sync' ) );
		add_action( 'ovc_content_sync_batch', array( __CLASS__, 'sync_batch' ) );
	}

	/**
	 * Post types mirrored.
	 *
	 * @return array<int,string>
	 */
	public static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	/**
	 * Taxonomies mirrored.
	 *
	 * @return array<int,string>
	 */
	public static function taxonomies() {
		return array_values( array_diff( get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'names' ), array( 'post_format' ) ) );
	}

	/**
	 * Post saved.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public static function on_post_saved( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
		if ( 'trash' === $post->post_status || 'auto-draft' === $post->post_status ) {
			self::on_post_deleted( $post_id );
			return;
		}
		self::queue_post( (int) $post_id );
	}

	/**
	 * Queues a post (deduplicated per request).
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function queue_post( $post_id ) {
		$key = 'p' . $post_id;
		if ( isset( self::$queued[ $key ] ) || ! OVC_Settings::is_configured() ) {
			return;
		}
		$item = self::serialize_post( $post_id );
		if ( null !== $item ) {
			self::$queued[ $key ] = true;
			OVC_Queue::push( 'content', $item );
		}
	}

	/**
	 * Post deleted/trashed.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function on_post_deleted( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) || ! OVC_Settings::is_configured() ) {
			return;
		}
		OVC_Queue::push(
			'content',
			array(
				'object_type' => 'post',
				'wp_id'       => (int) $post_id,
				'deleted'     => true,
			)
		);
	}

	/**
	 * Term saved.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_term_saved( $term_id, $tt_id, $taxonomy ) {
		unset( $tt_id );
		if ( ! in_array( $taxonomy, self::taxonomies(), true ) || ! OVC_Settings::is_configured() ) {
			return;
		}
		$item = self::serialize_term( (int) $term_id, $taxonomy );
		if ( null !== $item ) {
			OVC_Queue::push( 'content', $item );
		}
	}

	/**
	 * Term deleted.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_term_deleted( $term_id, $taxonomy ) {
		if ( in_array( $taxonomy, self::taxonomies(), true ) && OVC_Settings::is_configured() ) {
			OVC_Queue::push(
				'content',
				array(
					'object_type' => 'term',
					'wp_id'       => (int) $term_id,
					'deleted'     => true,
				)
			);
		}
	}

	/**
	 * Starts a full sync (daily cron, activation, admin button, remote "resync").
	 *
	 * @return void
	 */
	public static function start_full_sync() {
		update_option(
			'ovc_sync_cursor',
			array(
				'phase'   => 'posts',
				'last_id' => 0,
				'started' => time(),
			),
			false
		);
		wp_schedule_single_event( time() + 5, 'ovc_content_sync_batch' );
	}

	/**
	 * Processes one full-sync batch and reschedules itself until done.
	 *
	 * @return void
	 */
	public static function sync_batch() {
		global $wpdb;
		$cursor = get_option( 'ovc_sync_cursor' );
		if ( ! is_array( $cursor ) || ! OVC_Settings::is_configured() ) {
			return;
		}

		if ( 'posts' === $cursor['phase'] ) {
			$types    = self::post_types();
			$statuses = self::STATUSES;
			$in_types = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$in_stat  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$ids      = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ({$in_types}) AND post_status IN ({$in_stat}) ORDER BY ID ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					array_merge( array( (int) $cursor['last_id'] ), $types, $statuses, array( self::BATCH ) )
				)
			);
			foreach ( $ids as $id ) {
				self::queue_post( (int) $id );
				$cursor['last_id'] = (int) $id;
			}
			if ( count( $ids ) < self::BATCH ) {
				$cursor = array_merge( $cursor, array( 'phase' => 'terms', 'last_id' => 0 ) );
			}
		} elseif ( 'terms' === $cursor['phase'] ) {
			$terms = get_terms(
				array(
					'taxonomy'   => self::taxonomies(),
					'hide_empty' => false,
					'orderby'    => 'term_id',
					'order'      => 'ASC',
					'number'     => self::BATCH,
					'offset'     => (int) $cursor['last_id'],
				)
			);
			$terms = is_array( $terms ) ? $terms : array();
			foreach ( $terms as $term ) {
				$item = self::serialize_term( (int) $term->term_id, $term->taxonomy );
				if ( null !== $item ) {
					OVC_Queue::push( 'content', $item );
				}
			}
			$cursor['last_id'] = (int) $cursor['last_id'] + count( $terms );
			if ( count( $terms ) < self::BATCH ) {
				$cursor['phase'] = 'done';
			}
		}

		update_option( 'ovc_sync_cursor', $cursor, false );
		if ( 'done' !== $cursor['phase'] ) {
			wp_schedule_single_event( time() + 15, 'ovc_content_sync_batch' );
		} else {
			update_option( 'ovc_last_full_sync', time(), false );
			delete_option( 'ovc_full_sync_pending' );
		}
	}

	/**
	 * Serializes a post with its rendered content and Yoast data.
	 *
	 * @param int $post_id Post id.
	 * @return array|null
	 */
	public static function serialize_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_status, self::STATUSES, true ) ) {
			return null;
		}

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for the_content filters.
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		wp_reset_postdata();

		$seo = array();
		foreach ( self::YOAST_POST_META as $meta_key => $key ) {
			$seo[ $key ] = (string) get_post_meta( $post_id, $meta_key, true );
		}
		$seo['noindex']  = '1' === $seo['noindex'];
		$seo['nofollow'] = '1' === $seo['nofollow'];
		$seo             = array_merge( $seo, self::yoast_resolved( 'post', $post_id ) );
		$seo             = self::resolve_custom( $seo, $post );

		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		return array(
			'object_type'    => 'post',
			'wp_id'          => (int) $post_id,
			'subtype'        => $post->post_type,
			'status'         => $post->post_status,
			'url'            => (string) get_permalink( $post ),
			'title'          => get_the_title( $post ),
			'slug'           => $post->post_name,
			'parent'         => (int) $post->post_parent,
			'modified_gmt'   => $post->post_modified_gmt,
			'content_html'   => substr( (string) $html, 0, self::MAX_HTML ),
			'excerpt'        => wp_strip_all_tags( (string) $post->post_excerpt ),
			'seo'            => $seo,
			'featured_image' => $thumb_id ? array(
				'id'  => $thumb_id,
				'src' => (string) wp_get_attachment_url( $thumb_id ),
				'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			) : null,
		);
	}

	/**
	 * Serializes a term with Yoast data.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy.
	 * @return array|null
	 */
	public static function serialize_term( $term_id, $taxonomy ) {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$all  = get_option( 'wpseo_taxonomy_meta', array() );
		$meta = is_array( $all ) ? ( $all[ $taxonomy ][ $term_id ] ?? array() ) : array();
		$seo  = array();
		foreach ( self::YOAST_TERM_META as $meta_key => $key ) {
			$seo[ $key ] = (string) ( $meta[ $meta_key ] ?? '' );
		}
		$seo['noindex'] = 'noindex' === $seo['noindex'];
		$seo            = array_merge( $seo, self::yoast_resolved( 'term', $term_id ) );
		$seo            = self::resolve_custom( $seo, $term );
		$link           = get_term_link( $term );

		return array(
			'object_type'  => 'term',
			'wp_id'        => (int) $term_id,
			'subtype'      => $taxonomy,
			'status'       => 'publish',
			'url'          => is_wp_error( $link ) ? '' : (string) $link,
			'title'        => $term->name,
			'slug'         => $term->slug,
			'parent'       => (int) $term->parent,
			'modified_gmt' => null,
			'content_html' => substr( (string) term_description( $term ), 0, self::MAX_HTML ),
			'excerpt'      => '',
			'seo'          => $seo,
		);
	}

	/**
	 * Custom Yoast values are resolved directly (%%variables%%), so the mirror is correct even
	 * when Yoast's indexable cache has not been rebuilt yet in this request.
	 *
	 * @param array          $seo    SEO payload.
	 * @param WP_Post|WP_Term $object Object.
	 * @return array
	 */
	private static function resolve_custom( array $seo, $object ) {
		if ( ! function_exists( 'wpseo_replace_vars' ) ) {
			return $seo;
		}
		if ( '' !== $seo['seo_title'] ) {
			$seo['seo_title_resolved'] = html_entity_decode( wpseo_replace_vars( $seo['seo_title'], $object ), ENT_QUOTES, 'UTF-8' );
		}
		if ( '' !== $seo['meta_description'] ) {
			$seo['meta_description_resolved'] = html_entity_decode( wpseo_replace_vars( $seo['meta_description'], $object ), ENT_QUOTES, 'UTF-8' );
		}
		return $seo;
	}

	/**
	 * Resolved Yoast output (title / description as rendered), when Yoast >= 14 is active.
	 *
	 * @param string $type post|term.
	 * @param int    $id   Object id.
	 * @return array{seo_title_resolved?:string,meta_description_resolved?:string}
	 */
	private static function yoast_resolved( $type, $id ) {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return array();
		}
		try {
			$meta = 'post' === $type ? YoastSEO()->meta->for_post( $id ) : YoastSEO()->meta->for_term( $id );
			if ( ! $meta ) {
				return array();
			}
			return array(
				'seo_title_resolved'        => html_entity_decode( (string) $meta->title, ENT_QUOTES, 'UTF-8' ),
				'meta_description_resolved' => html_entity_decode( (string) $meta->description, ENT_QUOTES, 'UTF-8' ),
			);
		} catch ( Throwable $e ) {
			return array();
		}
	}
}
