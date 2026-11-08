<?php
/**
 * OptiVio — Shares layout data (user, business switcher, notifications) with the views.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Csrf;
use OptiVio\Core\View;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Notifications_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Auth;
use OptiVio\Security\Gate;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Layout context provider.
 */
final class Layout_Context {

	/**
	 * Shares the layout variables for authenticated pages.
	 *
	 * @return void
	 */
	public static function share(): void {
		$user = Auth::user();
		if ( null === $user ) {
			return;
		}

		$ids        = Gate::accessible_business_ids( $user );
		$businesses = Businesses_Repository::options( $ids );
		$active_id  = Gate::active_business_id();

		View::share( 'current_user', $user );
		View::share( 'is_super_admin', Gate::is_super_admin( $user ) );
		View::share( 'business_options', $businesses );
		View::share( 'active_business_id', $active_id );
		View::share( 'active_business_name', $active_id > 0 ? ( $businesses[ $active_id ] ?? '' ) : '' );
		View::share( 'notifications', Notifications_Repository::latest_for( (int) $user['id'], $ids, 6 ) );
		View::share( 'notifications_unread', Notifications_Repository::unread_count( (int) $user['id'], $ids ) );
		View::share( 'app_name', (string) Settings_Repository::get( 'general.app_name', 'OptiVio' ) );
		View::share( 'csrf_token', Csrf::token() );
	}
}
