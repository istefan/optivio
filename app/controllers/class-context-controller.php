<?php
/**
 * OptiVio — Active business switcher and notification actions.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Response;
use OptiVio\Core\Session;
use OptiVio\Repositories\Notifications_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Context controller.
 */
final class Context_Controller extends Controller {

	/**
	 * Switches the active business (0 = all accessible businesses).
	 *
	 * @return void
	 */
	public function switch_business(): void {
		$business_id = absint( $this->request->input( 'business_id', 0 ) );

		if ( $business_id > 0 ) {
			Gate::authorize_business( $business_id );
			Session::set( 'active_business_id', $business_id );
		} else {
			Session::remove( 'active_business_id' );
		}
		Audit_Log::record( 'context.business_switch', 'business', $business_id > 0 ? $business_id : null, array(), $business_id > 0 ? $business_id : null );

		$return = (string) $this->request->input( 'return', '/' );
		Response::redirect( preg_match( '#^/[A-Za-z0-9/_\-]*$#', $return ) ? $return : '/' );
	}

	/**
	 * Marks all visible notifications as read.
	 *
	 * @return void
	 */
	public function mark_notifications_read(): void {
		Notifications_Repository::mark_all_read( (int) $this->user()['id'], Gate::accessible_business_ids() );
		if ( $this->request->wants_json() ) {
			Response::json( array( 'ok' => true ) );
		}
		Response::redirect( '/' );
	}
}
