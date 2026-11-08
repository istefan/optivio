<?php
/**
 * OptiVio — Users and business access grants.
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
 * Users repository.
 */
final class Users_Repository {

	/**
	 * Safe column list (password hash only where explicitly required).
	 */
	private const PUBLIC_COLUMNS = 'id, email, display_name, role, status, session_version, must_change_password, password_changed_at, last_login_at, last_login_ip, created_at, updated_at';

	/**
	 * Finds a user by id (without password hash).
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function find( int $id ): ?array {
		return Database::instance()->get_row( 'SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?', array( $id ) );
	}

	/**
	 * Finds a user by email INCLUDING password hash (authentication only).
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function find_for_auth( string $email ): ?array {
		return Database::instance()->get_row( 'SELECT id, email, display_name, role, status, password_hash, session_version FROM users WHERE email = ?', array( $email ) );
	}

	/**
	 * Returns a user's password hash.
	 *
	 * @param int $id Id.
	 * @return string|null
	 */
	public static function password_hash( int $id ): ?string {
		$hash = Database::instance()->get_var( 'SELECT password_hash FROM users WHERE id = ?', array( $id ) );
		return null === $hash ? null : (string) $hash;
	}

	/**
	 * Whether an email is already used (optionally excluding a user).
	 *
	 * @param string $email      Email.
	 * @param int    $exclude_id User id to exclude.
	 * @return bool
	 */
	public static function email_exists( string $email, int $exclude_id = 0 ): bool {
		return null !== Database::instance()->get_var( 'SELECT id FROM users WHERE email = ? AND id <> ?', array( $email, $exclude_id ) );
	}

	/**
	 * Counts users.
	 *
	 * @return int
	 */
	public static function count(): int {
		return (int) Database::instance()->get_var( 'SELECT COUNT(*) FROM users' );
	}

	/**
	 * Counts active super admins.
	 *
	 * @return int
	 */
	public static function count_active_super_admins(): int {
		return (int) Database::instance()->get_var( "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND status = 'active'" );
	}

	/**
	 * Lists users with their number of granted businesses.
	 *
	 * @return array<int,array>
	 */
	public static function all(): array {
		return Database::instance()->get_results(
			'SELECT u.id, u.email, u.display_name, u.role, u.status, u.last_login_at, u.created_at,
				(SELECT COUNT(*) FROM user_business_access a WHERE a.user_id = u.id) AS businesses_count
			 FROM users u ORDER BY u.role DESC, u.display_name ASC'
		);
	}

	/**
	 * Creates a user.
	 *
	 * @param array $data email, display_name, role, status, password_hash, must_change_password, created_by.
	 * @return int
	 */
	public static function create( array $data ): int {
		return Database::instance()->insert(
			'users',
			array(
				'email'                => $data['email'],
				'display_name'         => $data['display_name'],
				'role'                 => $data['role'],
				'status'               => $data['status'] ?? 'active',
				'password_hash'        => $data['password_hash'],
				'must_change_password' => (int) ( $data['must_change_password'] ?? 0 ),
				'password_changed_at'  => Database::now(),
				'created_by'           => $data['created_by'] ?? null,
			)
		);
	}

	/**
	 * Updates profile fields.
	 *
	 * @param int   $id   Id.
	 * @param array $data Allowed: email, display_name, role, status.
	 * @return void
	 */
	public static function update( int $id, array $data ): void {
		$allowed = array_intersect_key( $data, array_flip( array( 'email', 'display_name', 'role', 'status' ) ) );
		if ( ! empty( $allowed ) ) {
			Database::instance()->update( 'users', $allowed, array( 'id' => $id ) );
		}
	}

	/**
	 * Sets a new password and invalidates all existing sessions of the user.
	 *
	 * @param int  $id                   Id.
	 * @param string $hash                 New hash.
	 * @param bool $must_change_password Force change at next login.
	 * @return void
	 */
	public static function set_password( int $id, string $hash, bool $must_change_password = false ): void {
		Database::instance()->query(
			'UPDATE users SET password_hash = ?, must_change_password = ?, password_changed_at = UTC_TIMESTAMP(), session_version = session_version + 1 WHERE id = ?',
			array( $hash, $must_change_password ? 1 : 0, $id )
		);
	}

	/**
	 * Silently upgrades a password hash (rehash on login) without invalidating sessions.
	 *
	 * @param int    $id   Id.
	 * @param string $hash Hash.
	 * @return void
	 */
	public static function upgrade_hash( int $id, string $hash ): void {
		Database::instance()->update( 'users', array( 'password_hash' => $hash ), array( 'id' => $id ) );
	}

	/**
	 * Invalidates all sessions of a user.
	 *
	 * @param int $id Id.
	 * @return void
	 */
	public static function bump_session_version( int $id ): void {
		Database::instance()->query( 'UPDATE users SET session_version = session_version + 1 WHERE id = ?', array( $id ) );
	}

	/**
	 * Records a successful login.
	 *
	 * @param int         $id Id.
	 * @param string|null $ip Binary IP.
	 * @return void
	 */
	public static function touch_login( int $id, ?string $ip ): void {
		Database::instance()->query( 'UPDATE users SET last_login_at = UTC_TIMESTAMP(), last_login_ip = ? WHERE id = ?', array( $ip, $id ) );
	}

	/**
	 * Returns the business ids (and permissions) granted to a user.
	 *
	 * @param int $user_id User id.
	 * @return array<int,string> business_id => permission.
	 */
	public static function business_grants( int $user_id ): array {
		$rows   = Database::instance()->get_results( 'SELECT business_id, permission FROM user_business_access WHERE user_id = ?', array( $user_id ) );
		$grants = array();
		foreach ( $rows as $row ) {
			$grants[ (int) $row['business_id'] ] = (string) $row['permission'];
		}
		return $grants;
	}

	/**
	 * Replaces the business grants of a user.
	 *
	 * @param int               $user_id    User id.
	 * @param array<int,string> $grants     business_id => view|manage.
	 * @param int               $granted_by Acting user.
	 * @return void
	 */
	public static function sync_business_grants( int $user_id, array $grants, int $granted_by ): void {
		Database::instance()->transaction(
			static function ( Database $db ) use ( $user_id, $grants, $granted_by ): void {
				$db->delete( 'user_business_access', array( 'user_id' => $user_id ) );
				foreach ( $grants as $business_id => $permission ) {
					$db->insert(
						'user_business_access',
						array(
							'user_id'     => $user_id,
							'business_id' => (int) $business_id,
							'permission'  => 'view' === $permission ? 'view' : 'manage',
							'granted_by'  => $granted_by,
						)
					);
				}
			}
		);
	}
}
