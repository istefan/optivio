<?php
/**
 * Persistent outgoing queue (survives OptiVio downtime). Flushed by WP-Cron.
 *
 * Channels: "events" (phone clicks, form entries) and "content" (content mirror).
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue.
 */
final class OVC_Queue {

	const MAX_ATTEMPTS = 12;

	/**
	 * Channel => [endpoint, batch size, payload key].
	 */
	const CHANNELS = array(
		'events'  => array( '/api/connector/v1/events', 100, 'events' ),
		'content' => array( '/api/connector/v1/content', 10, 'items' ),
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'ovc_flush_queue', array( __CLASS__, 'flush' ) );
		add_action( 'ovc_flush_queue_once', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Adds an item and schedules a near-immediate flush.
	 *
	 * @param string $channel Channel.
	 * @param array  $payload Item.
	 * @return void
	 */
	public static function push( $channel, array $payload ) {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'ovc_queue',
			array(
				'channel'          => $channel,
				'payload'          => wp_json_encode( $payload ),
				'attempts'         => 0,
				'next_attempt_gmt' => $now,
				'created_gmt'      => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
		if ( ! wp_next_scheduled( 'ovc_flush_queue_once' ) ) {
			wp_schedule_single_event( time() + 10, 'ovc_flush_queue_once' );
		}
	}

	/**
	 * Sends due items of every channel (bounded work per run).
	 *
	 * @return array<string,int> Sent items per channel.
	 */
	public static function flush() {
		global $wpdb;
		$table  = $wpdb->prefix . 'ovc_queue';
		$sent   = array();
		$start  = microtime( true );

		foreach ( self::CHANNELS as $channel => $def ) {
			$sent[ $channel ] = 0;
			for ( $round = 0; $round < 5 && ( microtime( true ) - $start ) < 20; $round++ ) {
				$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT id, payload, attempts FROM {$table} WHERE channel = %s AND next_attempt_gmt <= %s ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$channel,
						gmdate( 'Y-m-d H:i:s' ),
						$def[1]
					),
					ARRAY_A
				);
				if ( empty( $rows ) ) {
					break;
				}

				$items = array();
				$ids   = array();
				foreach ( $rows as $row ) {
					$item = json_decode( $row['payload'], true );
					if ( is_array( $item ) ) {
						$items[] = $item;
					}
					$ids[] = (int) $row['id'];
				}

				$result = OVC_Client::post( $def[0], array( $def[2] => $items ) );
				$ok     = 200 === $result['code'] && ! empty( $result['body']['ok'] );
				$id_sql = implode( ',', array_map( 'absint', $ids ) );

				if ( $ok || in_array( $result['code'], array( 400, 413, 422 ), true ) ) {
					// Delivered (or permanently rejected) — never retry the same payload forever.
					$wpdb->query( "DELETE FROM {$table} WHERE id IN ({$id_sql})" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$sent[ $channel ] += $ok ? count( $ids ) : 0;
					continue;
				}

				// Temporary failure: exponential backoff, drop after MAX_ATTEMPTS.
				foreach ( $rows as $row ) {
					$attempts = (int) $row['attempts'] + 1;
					if ( $attempts >= self::MAX_ATTEMPTS ) {
						$wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						continue;
					}
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$table,
						array(
							'attempts'         => $attempts,
							'next_attempt_gmt' => gmdate( 'Y-m-d H:i:s', time() + min( 21600, 60 * ( 2 ** $attempts ) ) ),
						),
						array( 'id' => (int) $row['id'] ),
						array( '%d', '%s' ),
						array( '%d' )
					);
				}
				update_option( 'ovc_last_error', array( 'time' => time(), 'code' => $result['code'], 'error' => $result['error'] ), false );
				break;
			}
		}
		return $sent;
	}

	/**
	 * Items waiting per channel.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		global $wpdb;
		$table  = $wpdb->prefix . 'ovc_queue';
		$rows   = $wpdb->get_results( "SELECT channel, COUNT(*) AS total FROM {$table} GROUP BY channel", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = array(
			'events'  => 0,
			'content' => 0,
		);
		foreach ( (array) $rows as $row ) {
			$counts[ $row['channel'] ] = (int) $row['total'];
		}
		return $counts;
	}
}
