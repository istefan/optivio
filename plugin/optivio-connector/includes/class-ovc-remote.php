<?php
/**
 * Remote operations API (OptiVio → WordPress), HMAC signed, with automatic snapshots.
 *
 * Endpoint: POST /wp-json/optivio/v1/remote   body: { "operation": "...", "params": {...} }
 * Operations: ping, update_seo_meta, update_post, create_post, update_term, upload_media, rollback, resync.
 * Runs without a logged-in user, so WordPress applies KSES filtering to submitted HTML.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remote API.
 */
final class OVC_Remote {

	const MAX_MEDIA_BYTES = 8388608;
	const MEDIA_MIMES     = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Canonical (signed) path of the endpoint: path + query of its REST URL.
	 *
	 * @return string
	 */
	public static function canonical_path() {
		$url   = wp_parse_url( rest_url( 'optivio/v1/remote' ) );
		$path  = $url['path'] ?? '/';
		$query = isset( $url['query'] ) ? '?' . $url['query'] : '';
		return $path . $query;
	}

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'optivio/v1',
			'/remote',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => static function ( $request ) {
					return OVC_Signer::verify_request( $request, OVC_Remote::canonical_path() );
				},
			)
		);
	}

	/**
	 * Dispatches an operation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( $request ) {
		$body      = json_decode( (string) $request->get_body(), true );
		$operation = is_array( $body ) ? sanitize_key( (string) ( $body['operation'] ?? '' ) ) : '';
		$params    = is_array( $body['params'] ?? null ) ? $body['params'] : array();
		$map       = array(
			'ping'            => 'op_ping',
			'update_seo_meta' => 'op_update_seo_meta',
			'update_post'     => 'op_update_post',
			'create_post'     => 'op_create_post',
			'update_term'     => 'op_update_term',
			'upload_media'    => 'op_upload_media',
			'rollback'        => 'op_rollback',
			'resync'          => 'op_resync',
		);
		if ( ! isset( $map[ $operation ] ) ) {
			return new WP_Error( 'ovc_unknown_operation', 'Operație necunoscută.', array( 'status' => 400 ) );
		}

		$result = call_user_func( array( __CLASS__, $map[ $operation ] ), $params );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array_merge( array( 'ok' => true, 'operation' => $operation ), $result ), 200 );
	}

	/**
	 * Ping: versions and capabilities.
	 *
	 * @param array $params Params.
	 * @return array
	 */
	private static function op_ping( $params ) {
		unset( $params );
		return array(
			'plugin_version'    => OVC_VERSION,
			'wp_version'        => get_bloginfo( 'version' ),
			'has_yoast'         => defined( 'WPSEO_VERSION' ),
			'has_gravity_forms' => class_exists( 'GFForms' ),
			'queue'             => OVC_Queue::counts(),
		);
	}

	/**
	 * Updates Yoast SEO fields of a post or term.
	 *
	 * @param array $params object_type, id, taxonomy, fields{seo_title, meta_description, focus_keyword, canonical, noindex}.
	 * @return array|WP_Error
	 */
	private static function op_update_seo_meta( $params ) {
		$fields = self::seo_fields( (array) ( $params['fields'] ?? array() ) );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$type = (string) ( $params['object_type'] ?? 'post' );
		$id   = absint( $params['id'] ?? 0 );

		if ( 'term' === $type ) {
			$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? '' ) );
			if ( ! term_exists( $id, $taxonomy ) ) {
				return self::not_found();
			}
			$snapshot = OVC_Snapshots::capture_term( $id, $taxonomy, 'update_seo_meta' );
			$all      = get_option( 'wpseo_taxonomy_meta', array() );
			$all      = is_array( $all ) ? $all : array();
			$current  = $all[ $taxonomy ][ $id ] ?? array();
			$keys     = array_flip( OVC_Content::YOAST_TERM_META );
			foreach ( $fields as $key => $value ) {
				if ( 'noindex' === $key ) {
					$value = $value ? 'noindex' : 'default';
				}
				$current[ $keys[ $key ] ] = $value;
			}
			$all[ $taxonomy ][ $id ] = $current;
			update_option( 'wpseo_taxonomy_meta', $all );
			wp_update_term( $id, $taxonomy, array() );
			return self::done( $snapshot, OVC_Content::serialize_term( $id, $taxonomy ) );
		}

		if ( ! self::post_exists( $id ) ) {
			return self::not_found();
		}
		$snapshot = OVC_Snapshots::capture_post( $id, 'update_seo_meta' );
		$keys     = array_flip( OVC_Content::YOAST_POST_META );
		foreach ( $fields as $key => $value ) {
			if ( 'noindex' === $key ) {
				if ( $value ) {
					update_post_meta( $id, $keys['noindex'], '1' );
				} else {
					delete_post_meta( $id, $keys['noindex'] );
				}
				continue;
			}
			if ( '' === $value ) {
				delete_post_meta( $id, $keys[ $key ] );
			} else {
				update_post_meta( $id, $keys[ $key ], $value );
			}
		}
		wp_update_post( array( 'ID' => $id ) ); // Lets Yoast rebuild its indexable.
		return self::done( $snapshot, OVC_Content::serialize_post( $id ) );
	}

	/**
	 * Updates post fields.
	 *
	 * @param array $params id, fields{title, content, excerpt, status, slug}.
	 * @return array|WP_Error
	 */
	private static function op_update_post( $params ) {
		$id = absint( $params['id'] ?? 0 );
		if ( ! self::post_exists( $id ) ) {
			return self::not_found();
		}
		$data = self::post_fields( (array) ( $params['fields'] ?? array() ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		// "append": adds HTML at the end of the existing raw content (e.g. an internal link paragraph).
		if ( isset( $params['fields']['append'] ) && is_string( $params['fields']['append'] ) && '' !== trim( $params['fields']['append'] ) ) {
			$data['post_content'] = rtrim( (string) get_post( $id )->post_content ) . "\n\n" . wp_kses_post( $params['fields']['append'] );
		}
		if ( empty( $data ) ) {
			return new WP_Error( 'ovc_no_fields', 'Niciun câmp de actualizat.', array( 'status' => 400 ) );
		}
		$snapshot = OVC_Snapshots::capture_post( $id, 'update_post' );
		$result   = wp_update_post( array_merge( array( 'ID' => $id ), $data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( isset( $params['seo'] ) && is_array( $params['seo'] ) ) {
			self::op_update_seo_meta_nosnap( $id, $params['seo'] );
			wp_update_post( array( 'ID' => $id ) ); // Lets Yoast rebuild its indexable with the new meta.
		}
		return self::done( $snapshot, OVC_Content::serialize_post( $id ) );
	}

	/**
	 * Creates a post or page (draft by default).
	 *
	 * @param array $params post_type, fields{...}, seo{...}, featured_media, categories[].
	 * @return array|WP_Error
	 */
	private static function op_create_post( $params ) {
		$type = sanitize_key( (string) ( $params['post_type'] ?? 'post' ) );
		if ( ! in_array( $type, OVC_Content::post_types(), true ) ) {
			return new WP_Error( 'ovc_bad_type', 'Tip de conținut nepermis.', array( 'status' => 400 ) );
		}
		$data = self::post_fields( (array) ( $params['fields'] ?? array() ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['post_title'] ) ) {
			return new WP_Error( 'ovc_missing_title', 'Titlul este obligatoriu.', array( 'status' => 400 ) );
		}

		$id = wp_insert_post(
			array_merge(
				array(
					'post_type'   => $type,
					'post_status' => 'draft',
					'post_author' => self::author_id(),
				),
				$data
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$snapshot = OVC_Snapshots::capture_created( 'post', $id, 'create_post' );

		$media = absint( $params['featured_media'] ?? 0 );
		if ( $media && 'attachment' === get_post_type( $media ) ) {
			set_post_thumbnail( $id, $media );
		}
		if ( 'post' === $type && ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
			wp_set_post_categories( $id, array_map( 'absint', $params['categories'] ) );
		}
		if ( isset( $params['seo'] ) && is_array( $params['seo'] ) ) {
			self::op_update_seo_meta_nosnap( $id, $params['seo'] );
		}
		wp_update_post( array( 'ID' => $id ) );
		return self::done( $snapshot, OVC_Content::serialize_post( $id ) );
	}

	/**
	 * Updates term name/description/slug.
	 *
	 * @param array $params taxonomy, id, fields{name, description, slug}.
	 * @return array|WP_Error
	 */
	private static function op_update_term( $params ) {
		$taxonomy = sanitize_key( (string) ( $params['taxonomy'] ?? '' ) );
		$id       = absint( $params['id'] ?? 0 );
		if ( ! in_array( $taxonomy, OVC_Content::taxonomies(), true ) || ! term_exists( $id, $taxonomy ) ) {
			return self::not_found();
		}
		$f    = (array) ( $params['fields'] ?? array() );
		$args = array();
		if ( isset( $f['name'] ) ) {
			$args['name'] = sanitize_text_field( (string) $f['name'] );
		}
		if ( isset( $f['description'] ) ) {
			$args['description'] = wp_kses_post( (string) $f['description'] );
		}
		if ( isset( $f['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $f['slug'] );
		}
		$snapshot = OVC_Snapshots::capture_term( $id, $taxonomy, 'update_term' );
		$result   = wp_update_term( $id, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::done( $snapshot, OVC_Content::serialize_term( $id, $taxonomy ) );
	}

	/**
	 * Uploads an image (base64) to the media library.
	 *
	 * @param array $params filename, data_base64, alt, title.
	 * @return array|WP_Error
	 */
	private static function op_upload_media( $params ) {
		$binary = base64_decode( (string) ( $params['data_base64'] ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $binary || '' === $binary || strlen( $binary ) > self::MAX_MEDIA_BYTES ) {
			return new WP_Error( 'ovc_bad_media', 'Imagine invalidă sau prea mare.', array( 'status' => 400 ) );
		}
		$info = @getimagesizefromstring( $binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $info || ! in_array( $info['mime'], self::MEDIA_MIMES, true ) ) {
			return new WP_Error( 'ovc_bad_media', 'Sunt acceptate doar imagini JPEG, PNG sau WebP.', array( 'status' => 400 ) );
		}
		$ext      = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		)[ $info['mime'] ];
		$filename = sanitize_file_name( pathinfo( (string) ( $params['filename'] ?? 'imagine' ), PATHINFO_FILENAME ) ) . '.' . $ext;
		$upload   = wp_upload_bits( $filename, null, $binary );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'ovc_upload_failed', (string) $upload['error'], array( 'status' => 500 ) );
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $info['mime'],
				'post_title'     => sanitize_text_field( (string) ( $params['title'] ?? pathinfo( $filename, PATHINFO_FILENAME ) ) ),
				'post_status'    => 'inherit',
				'post_author'    => self::author_id(),
			),
			$upload['file']
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return new WP_Error( 'ovc_upload_failed', 'Atașamentul nu a putut fi creat.', array( 'status' => 500 ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) ( $params['alt'] ?? '' ) ) );

		$snapshot = OVC_Snapshots::capture_created( 'attachment', $id, 'upload_media' );
		return array(
			'snapshot_id' => $snapshot,
			'object_id'   => (int) $id,
			'url'         => (string) wp_get_attachment_url( $id ),
		);
	}

	/**
	 * Restores a snapshot.
	 *
	 * @param array $params snapshot_id.
	 * @return array|WP_Error
	 */
	private static function op_rollback( $params ) {
		$result = OVC_Snapshots::rollback( absint( $params['snapshot_id'] ?? 0 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$object = null;
		if ( 'post' === $result['object_type'] ) {
			$object = OVC_Content::serialize_post( $result['object_id'] );
			if ( null === $object ) {
				$object = array(
					'object_type' => 'post',
					'wp_id'       => $result['object_id'],
					'deleted'     => true,
				);
			}
		} elseif ( 'term' === $result['object_type'] ) {
			$object = OVC_Content::serialize_term( $result['object_id'], $result['taxonomy'] );
		}
		return array(
			'restored' => $result,
			'object'   => $object,
		);
	}

	/**
	 * Re-sends content to OptiVio (single object or everything).
	 *
	 * @param array $params object_type, id, taxonomy | all=true.
	 * @return array
	 */
	private static function op_resync( $params ) {
		if ( ! empty( $params['all'] ) ) {
			OVC_Content::start_full_sync();
			return array( 'scheduled' => 'full' );
		}
		if ( 'term' === ( $params['object_type'] ?? '' ) ) {
			return array( 'object' => OVC_Content::serialize_term( absint( $params['id'] ?? 0 ), sanitize_key( (string) ( $params['taxonomy'] ?? '' ) ) ) );
		}
		return array( 'object' => OVC_Content::serialize_post( absint( $params['id'] ?? 0 ) ) );
	}

	/**
	 * Applies SEO fields without taking another snapshot (already covered by the caller's snapshot).
	 *
	 * @param int   $id  Post id.
	 * @param array $seo SEO fields.
	 * @return void
	 */
	private static function op_update_seo_meta_nosnap( $id, array $seo ) {
		$fields = self::seo_fields( $seo );
		if ( is_wp_error( $fields ) ) {
			return;
		}
		$keys = array_flip( OVC_Content::YOAST_POST_META );
		foreach ( $fields as $key => $value ) {
			if ( 'noindex' === $key ) {
				$value ? update_post_meta( $id, $keys['noindex'], '1' ) : delete_post_meta( $id, $keys['noindex'] );
			} elseif ( '' === $value ) {
				delete_post_meta( $id, $keys[ $key ] );
			} else {
				update_post_meta( $id, $keys[ $key ], $value );
			}
		}
	}

	/**
	 * Validates SEO fields.
	 *
	 * @param array $f Raw fields.
	 * @return array|WP_Error
	 */
	private static function seo_fields( array $f ) {
		$out    = array();
		$limits = array(
			'seo_title'        => 300,
			'meta_description' => 500,
			'focus_keyword'    => 190,
		);
		foreach ( $limits as $key => $max ) {
			if ( array_key_exists( $key, $f ) ) {
				$out[ $key ] = mb_substr( sanitize_text_field( (string) $f[ $key ] ), 0, $max );
			}
		}
		if ( array_key_exists( 'canonical', $f ) ) {
			$out['canonical'] = '' === (string) $f['canonical'] ? '' : esc_url_raw( (string) $f['canonical'], array( 'http', 'https' ) );
		}
		if ( array_key_exists( 'noindex', $f ) ) {
			$out['noindex'] = (bool) $f['noindex'];
		}
		return empty( $out ) ? new WP_Error( 'ovc_no_fields', 'Niciun câmp de actualizat.', array( 'status' => 400 ) ) : $out;
	}

	/**
	 * Validates post fields.
	 *
	 * @param array $f Raw fields.
	 * @return array|WP_Error
	 */
	private static function post_fields( array $f ) {
		$out = array();
		if ( isset( $f['title'] ) ) {
			$out['post_title'] = sanitize_text_field( (string) $f['title'] );
		}
		if ( isset( $f['content'] ) ) {
			$out['post_content'] = wp_kses_post( (string) $f['content'] );
		}
		if ( isset( $f['excerpt'] ) ) {
			$out['post_excerpt'] = sanitize_textarea_field( (string) $f['excerpt'] );
		}
		if ( isset( $f['slug'] ) ) {
			$out['post_name'] = sanitize_title( (string) $f['slug'] );
		}
		if ( isset( $f['status'] ) ) {
			$status = sanitize_key( (string) $f['status'] );
			if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
				return new WP_Error( 'ovc_bad_status', 'Status nepermis.', array( 'status' => 400 ) );
			}
			$out['post_status'] = $status;
		}
		return $out;
	}

	/**
	 * Author used for created content: configured user, else the first administrator.
	 *
	 * @return int
	 */
	private static function author_id() {
		$configured = absint( get_option( 'ovc_author_id', 0 ) );
		if ( $configured && get_userdata( $configured ) ) {
			return $configured;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);
		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * Whether a mirrored post exists.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	private static function post_exists( $id ) {
		$post = $id ? get_post( $id ) : null;
		return $post && in_array( $post->post_type, OVC_Content::post_types(), true ) && 'trash' !== $post->post_status;
	}

	/**
	 * Success payload.
	 *
	 * @param int        $snapshot Snapshot id.
	 * @param array|null $object   Serialized object.
	 * @return array
	 */
	private static function done( $snapshot, $object ) {
		return array(
			'snapshot_id' => (int) $snapshot,
			'object_id'   => (int) ( $object['wp_id'] ?? 0 ),
			'object'      => $object,
		);
	}

	/**
	 * 404 error.
	 *
	 * @return WP_Error
	 */
	private static function not_found() {
		return new WP_Error( 'ovc_not_found', 'Obiect inexistent.', array( 'status' => 404 ) );
	}
}
