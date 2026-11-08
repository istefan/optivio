<?php
/**
 * OptiVio — Audit log queries.
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
 * Audit queries.
 */
final class Audit_Repository {

	/**
	 * Paginated audit entries (super admin screen).
	 *
	 * @param array $filters action (prefix), user_id, business_id.
	 * @param int   $page    Page (1-based).
	 * @param int   $per     Per page.
	 * @return array{rows:array,total:int}
	 */
	public static function paginate( array $filters, int $page, int $per = 50 ): array {
		$where  = array( '1 = 1' );
		$params = array();

		if ( ! empty( $filters['action'] ) ) {
			$where[]  = 'l.action LIKE ?';
			$params[] = str_replace( array( '%', '_' ), array( '\\%', '\\_' ), (string) $filters['action'] ) . '%';
		}
		if ( ! empty( $filters['user_id'] ) ) {
			$where[]  = 'l.user_id = ?';
			$params[] = (int) $filters['user_id'];
		}
		if ( ! empty( $filters['business_id'] ) ) {
			$where[]  = 'l.business_id = ?';
			$params[] = (int) $filters['business_id'];
		}

		$sql_where = implode( ' AND ', $where );
		$db        = Database::instance();
		$total     = (int) $db->get_var( 'SELECT COUNT(*) FROM audit_logs l WHERE ' . $sql_where, $params );
		$rows      = $db->get_results(
			'SELECT l.*, u.display_name, u.email, b.name AS business_name
			 FROM audit_logs l
			 LEFT JOIN users u ON u.id = l.user_id
			 LEFT JOIN businesses b ON b.id = l.business_id
			 WHERE ' . $sql_where . ' ORDER BY l.id DESC LIMIT ? OFFSET ?',
			array_merge( $params, array( $per, max( 0, ( $page - 1 ) * $per ) ) )
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Recent activity visible to a user: everything for super admins; for operators
	 * only their own actions and actions on their businesses.
	 *
	 * @param int             $user_id User id.
	 * @param array<int>|null $ids     Accessible business ids (null = all).
	 * @param int             $limit   Limit.
	 * @return array<int,array>
	 */
	public static function recent_for( int $user_id, ?array $ids, int $limit = 6 ): array {
		$exclude = "l.action NOT IN ('auth.session_expired')";
		if ( null === $ids ) {
			return Database::instance()->get_results(
				'SELECT l.action, l.created_at, l.entity_type, l.entity_id, u.display_name, b.name AS business_name
				 FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN businesses b ON b.id = l.business_id
				 WHERE ' . $exclude . ' ORDER BY l.id DESC LIMIT ?',
				array( $limit )
			);
		}

		$params = array( $user_id );
		$scope  = 'l.user_id = ?';
		if ( ! empty( $ids ) ) {
			$scope .= ' OR l.business_id IN (' . Database::in_placeholders( $ids ) . ')';
			$params = array_merge( $params, $ids );
		}
		$params[] = $limit;

		return Database::instance()->get_results(
			'SELECT l.action, l.created_at, l.entity_type, l.entity_id, u.display_name, b.name AS business_name
			 FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN businesses b ON b.id = l.business_id
			 WHERE (' . $scope . ') AND ' . $exclude . ' ORDER BY l.id DESC LIMIT ?',
			$params
		);
	}
}
