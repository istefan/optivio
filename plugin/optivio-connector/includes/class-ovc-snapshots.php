<?php
/**
 * Snapshots taken automatically before every remote modification (1-click rollback).
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot store.
 */
final class OVC_Snapshots {

	/**
	 * Captures the current state of a post (fields + all Yoast meta).
	 *
	 * @param int    $post_id   Post id.
	 * @param string $operation Operation.
	 * @return int Snapshot id.
	 */
	public static function capture_post( $post_id, $operation ) {
		$post = get_post( $post_id );
		$meta = array();
		foreach ( array_keys( OVC_Content::YOAST_POST_META ) as $key ) {
			$meta[ $key ] = metadata_exists( 'post', $post_id, $key ) ? get_post_meta( $post_id, $key, true ) : null;
		}
		return self::store(
			$operation,
			'post',
			$post_id,
			'',
			array(
				'fields' => array(
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
					'post_status'  => $post->post_status,
					'post_name'    => $post->post_name,
				),
				'meta'   => $meta,
			)
		);
	}

	/**
	 * Captures a term (fields + Yoast term meta).
	 *
	 * @param int    $term_id   Term id.
	 * @param string $taxonomy  Taxonomy.
	 * @param string $operation Operation.
	 * @return int
	 */
	public static function capture_term( $term_id, $taxonomy, $operation ) {
		$term = get_term( $term_id, $taxonomy );
		$all  = get_option( 'wpseo_taxonomy_meta', array() );
		return self::store(
			$operation,
			'term',
			$term_id,
			$taxonomy,
			array(
				'fields' => array(
					'name'        => $term->name,
					'description' => $term->description,
					'slug'        => $term->slug,
				),
				'yoast'  => is_array( $all ) ? ( $all[ $taxonomy ][ $term_id ] ?? null ) : null,
			)
		);
	}

	/**
	 * Records the creation of an object (rollback = trash / delete it).
	 *
	 * @param string $object_type post|attachment.
	 * @param int    $object_id   Id.
	 * @param string $operation   Operation.
	 * @return int
	 */
	public static function capture_created( $object_type, $object_id, $operation ) {
		return self::store( $operation, $object_type, $object_id, '', array( 'created' => true ) );
	}

	/**
	 * Restores a snapshot.
	 *
	 * @param int $snapshot_id Snapshot id.
	 * @return array|WP_Error Restored object info.
	 */
	public static function rollback( $snapshot_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ovc_snapshots';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $snapshot_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return new WP_Error( 'ovc_snapshot_missing', 'Snapshot inexistent.', array( 'status' => 404 ) );
		}
		if ( null !== $row['rolled_back_gmt'] ) {
			return new WP_Error( 'ovc_snapshot_used', 'Snapshot-ul a fost deja restaurat.', array( 'status' => 409 ) );
		}

		$data = json_decode( $row['data'], true );
		$id   = (int) $row['object_id'];

		if ( ! empty( $data['created'] ) ) {
			if ( 'attachment' === $row['object_type'] ) {
				wp_delete_attachment( $id, true );
			} else {
				wp_trash_post( $id );
			}
		} elseif ( 'post' === $row['object_type'] ) {
			$result = wp_update_post( array_merge( array( 'ID' => $id ), $data['fields'] ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			foreach ( (array) $data['meta'] as $key => $value ) {
				if ( null === $value ) {
					delete_post_meta( $id, $key );
				} else {
					update_post_meta( $id, $key, $value );
				}
			}
			wp_update_post( array( 'ID' => $id ) );
		} elseif ( 'term' === $row['object_type'] ) {
			wp_update_term( $id, $row['taxonomy'], $data['fields'] );
			$all = get_option( 'wpseo_taxonomy_meta', array() );
			$all = is_array( $all ) ? $all : array();
			if ( null === $data['yoast'] ) {
				unset( $all[ $row['taxonomy'] ][ $id ] );
			} else {
				$all[ $row['taxonomy'] ][ $id ] = $data['yoast'];
			}
			update_option( 'wpseo_taxonomy_meta', $all );
			wp_update_term( $id, $row['taxonomy'], array() );
		}

		$wpdb->update( $table, array( 'rolled_back_gmt' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $snapshot_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array(
			'object_type' => $row['object_type'],
			'object_id'   => $id,
			'taxonomy'    => $row['taxonomy'],
		);
	}

	/**
	 * Inserts a snapshot row.
	 *
	 * @param string $operation   Operation.
	 * @param string $object_type Object type.
	 * @param int    $object_id   Id.
	 * @param string $taxonomy    Taxonomy.
	 * @param array  $data        Data.
	 * @return int
	 */
	private static function store( $operation, $object_type, $object_id, $taxonomy, array $data ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'ovc_snapshots',
			array(
				'operation'   => $operation,
				'object_type' => $object_type,
				'object_id'   => (int) $object_id,
				'taxonomy'    => $taxonomy,
				'data'        => wp_json_encode( $data ),
				'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return (int) $wpdb->insert_id;
	}
}
