<?php
/**
 * OptiVio — In-app notifications / alerts.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Repositories;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Notifications repository.
 */
final class Notifications_Repository {

	/**
	 * Latest notifications visible to a user (personal + those of accessible businesses).
	 *
	 * @param int             $user_id User id.
	 * @param array<int>|null $ids     Accessible business ids.
	 * @param int             $limit   Limit.
	 * @return array<int,array>
	 */
	public static function latest_for( int $user_id, ?array $ids, int $limit = 8 ): array {
		[ $where, $params ] = self::scope( $user_id, $ids );
		$params[]           = $limit;
		return Database::instance()->get_results(
			'SELECT id, type, severity, title, body, url, is_read, created_at FROM notifications WHERE ' . $where . ' ORDER BY id DESC LIMIT ?',
			$params
		);
	}

	/**
	 * Unread count.
	 *
	 * @param int             $user_id User id.
	 * @param array<int>|null $ids     Accessible business ids.
	 * @return int
	 */
	public static function unread_count( int $user_id, ?array $ids ): int {
		[ $where, $params ] = self::scope( $user_id, $ids );
		return (int) Database::instance()->get_var( 'SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND ' . $where, $params );
	}

	/**
	 * Marks visible notifications as read.
	 *
	 * @param int             $user_id User id.
	 * @param array<int>|null $ids     Accessible business ids.
	 * @return void
	 */
	public static function mark_all_read( int $user_id, ?array $ids ): void {
		[ $where, $params ] = self::scope( $user_id, $ids );
		Database::instance()->query( 'UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND ' . $where, $params );
	}

	/**
	 * Creates a notification.
	 *
	 * @param array $data user_id, business_id, type, severity, title, body, url.
	 * @return int
	 */
	public static function create( array $data ): int {
		return Database::instance()->insert(
			'notifications',
			array(
				'user_id'     => $data['user_id'] ?? null,
				'business_id' => $data['business_id'] ?? null,
				'type'        => (string) $data['type'],
				'severity'    => (string) ( $data['severity'] ?? 'info' ),
				'title'       => mb_substr( (string) $data['title'], 0, 190 ),
				'body'        => isset( $data['body'] ) ? mb_substr( (string) $data['body'], 0, 500 ) : null,
				'url'         => $data['url'] ?? null,
			)
		);
	}

	/**
	 * Builds the visibility WHERE clause.
	 *
	 * @param int             $user_id User id.
	 * @param array<int>|null $ids     Accessible business ids.
	 * @return array{0:string,1:array}
	 */
	private static function scope( int $user_id, ?array $ids ): array {
		if ( null === $ids ) {
			return array( '(user_id = ? OR user_id IS NULL)', array( $user_id ) );
		}
		if ( empty( $ids ) ) {
			return array( 'user_id = ?', array( $user_id ) );
		}
		return array(
			'(user_id = ? OR (user_id IS NULL AND business_id IN (' . Database::in_placeholders( $ids ) . ')))',
			array_merge( array( $user_id ), $ids ),
		);
	}
}
