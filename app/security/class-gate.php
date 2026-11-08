<?php
/**
 * OptiVio — Role based access control and IDOR prevention.
 *
 * Roles:
 *  - super_admin: full access to everything.
 *  - operator:    access ONLY to the businesses granted in user_business_access
 *                 ("view" = read-only, "manage" = read + write).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Security;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Session;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Users_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Authorization gate.
 */
final class Gate {

	/**
	 * Request cache of grants per user id.
	 *
	 * @var array<int,array<int,string>>
	 */
	private static array $grants = array();

	/**
	 * Whether the user is an active super admin.
	 *
	 * @param array|null $user User row.
	 * @return bool
	 */
	public static function is_super_admin( ?array $user ): bool {
		return null !== $user && 'super_admin' === $user['role'] && 'active' === $user['status'];
	}

	/**
	 * Returns the accessible business ids for a user: null = unrestricted (super admin).
	 *
	 * @param array|null $user User row (defaults to current user).
	 * @return array<int>|null
	 */
	public static function accessible_business_ids( ?array $user = null ): ?array {
		$user = $user ?? Auth::user();
		if ( null === $user ) {
			return array();
		}
		if ( self::is_super_admin( $user ) ) {
			return null;
		}
		return array_keys( self::grants( (int) $user['id'] ) );
	}

	/**
	 * Whether a user can access a business with the given permission.
	 *
	 * @param int        $business_id Business id.
	 * @param string     $permission  view|manage.
	 * @param array|null $user        User (defaults to current).
	 * @return bool
	 */
	public static function can_access_business( int $business_id, string $permission = 'view', ?array $user = null ): bool {
		$user = $user ?? Auth::user();
		if ( null === $user || $business_id <= 0 ) {
			return false;
		}
		if ( self::is_super_admin( $user ) ) {
			return Businesses_Repository::exists( $business_id );
		}

		$grants = self::grants( (int) $user['id'] );
		if ( ! isset( $grants[ $business_id ] ) ) {
			return false;
		}
		return 'view' === $permission || 'manage' === $grants[ $business_id ];
	}

	/**
	 * Aborts with 404 unless the current user may access the business (anti-IDOR).
	 *
	 * @param int    $business_id Business id.
	 * @param string $permission  view|manage.
	 * @return void
	 * @throws Http_Exception 404 when not allowed (does not leak existence).
	 */
	public static function authorize_business( int $business_id, string $permission = 'view' ): void {
		if ( ! self::can_access_business( $business_id, $permission ) ) {
			throw Http_Exception::not_found();
		}
	}

	/**
	 * Aborts with 404 unless the current user may access the site (via its business).
	 *
	 * @param int    $site_id    Site id.
	 * @param string $permission view|manage.
	 * @return int The owning business id.
	 * @throws Http_Exception 404 when not allowed.
	 */
	public static function authorize_site( int $site_id, string $permission = 'view' ): int {
		$business_id = (int) Database::instance()->get_var( 'SELECT business_id FROM sites WHERE id = ?', array( $site_id ) );
		if ( 0 === $business_id ) {
			throw Http_Exception::not_found();
		}
		self::authorize_business( $business_id, $permission );
		return $business_id;
	}

	/**
	 * Aborts with 403 unless the current user is a super admin.
	 *
	 * @return void
	 * @throws Http_Exception 403.
	 */
	public static function require_super_admin(): void {
		if ( ! self::is_super_admin( Auth::user() ) ) {
			throw Http_Exception::forbidden();
		}
	}

	/**
	 * Returns the active business id from the session (validated), or 0 for "all businesses".
	 *
	 * @return int
	 */
	public static function active_business_id(): int {
		$id = absint( Session::get( 'active_business_id', 0 ) );
		if ( $id > 0 && ! self::can_access_business( $id ) ) {
			Session::remove( 'active_business_id' );
			return 0;
		}
		return $id;
	}

	/**
	 * Returns the business ids the current view is scoped to (active business or all accessible).
	 *
	 * @return array<int>|null Null = unrestricted.
	 */
	public static function scope_business_ids(): ?array {
		$active = self::active_business_id();
		return $active > 0 ? array( $active ) : self::accessible_business_ids();
	}

	/**
	 * Clears the grants cache (after changing access).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$grants = array();
	}

	/**
	 * Loads grants for a user.
	 *
	 * @param int $user_id User id.
	 * @return array<int,string>
	 */
	private static function grants( int $user_id ): array {
		if ( ! isset( self::$grants[ $user_id ] ) ) {
			self::$grants[ $user_id ] = Users_Repository::business_grants( $user_id );
		}
		return self::$grants[ $user_id ];
	}
}
