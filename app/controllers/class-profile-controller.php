<?php
/**
 * OptiVio — Own profile and password change.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Validator;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Users_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Auth;
use OptiVio\Security\Gate;
use OptiVio\Security\Password_Policy;
use OptiVio\Security\Rate_Limiter;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Profile controller.
 */
final class Profile_Controller extends Controller {

	/**
	 * Profile page.
	 *
	 * @return void
	 */
	public function index(): void {
		$user   = $this->user();
		$grants = Gate::is_super_admin( $user ) ? array() : Users_Repository::business_grants( (int) $user['id'] );

		$this->render(
			'profile/index',
			array(
				'page_title'    => 'Profilul meu',
				'page_subtitle' => 'Date personale și securitatea contului',
				'errors'        => $this->pull_errors(),
				'grants'        => $grants,
				'businesses'    => Businesses_Repository::options( array_keys( $grants ) ?: array() ),
			)
		);
	}

	/**
	 * Updates the display name.
	 *
	 * @return void
	 */
	public function update(): void {
		$name = $this->text( 'display_name', 120 );
		$v    = ( new Validator() )->required( 'display_name', $name );
		if ( $v->fails() ) {
			$this->back_with_errors( '/profile', $v->errors(), array( 'display_name' => $name ) );
		}
		Users_Repository::update( Auth::id(), array( 'display_name' => $name ) );
		$this->redirect_with( '/profile', 'success', 'Profilul a fost actualizat.' );
	}

	/**
	 * Changes the own password (requires the current password; invalidates other sessions).
	 *
	 * @return void
	 */
	public function update_password(): void {
		$user    = $this->user();
		$bucket  = 'password_change:' . (int) $user['id'];
		$current = (string) $this->request->input( 'current_password', '' );
		$new     = (string) $this->request->input( 'new_password', '' );
		$confirm = (string) $this->request->input( 'new_password_confirm', '' );
		$v       = new Validator();

		if ( Rate_Limiter::blocked_for( $bucket ) > 0 ) {
			$this->redirect_with( '/profile', 'error', 'Prea multe încercări. Reveniți peste câteva minute.' );
		}

		$hash = (string) Users_Repository::password_hash( (int) $user['id'] );
		if ( ! password_verify( $current, $hash ) ) {
			Rate_Limiter::hit( $bucket, 5, 900, 900 );
			$v->add( 'current_password', 'Parola curentă este incorectă.' );
		}
		$policy = Password_Policy::validate( $new, (string) $user['email'] );
		if ( null !== $policy ) {
			$v->add( 'new_password', $policy );
		} elseif ( password_verify( $new, $hash ) ) {
			$v->add( 'new_password', 'Parola nouă trebuie să fie diferită de cea curentă.' );
		}
		if ( ! hash_equals( $new, $confirm ) ) {
			$v->add( 'new_password_confirm', 'Parolele nu coincid.' );
		}
		if ( $v->fails() ) {
			$this->back_with_errors( '/profile', $v->errors(), array() );
		}

		Users_Repository::set_password( (int) $user['id'], Password_Policy::hash( $new ), false );
		Auth::refresh_session_version();
		Rate_Limiter::clear( $bucket );
		Audit_Log::record( 'auth.password_changed', 'user', (int) $user['id'] );
		$this->redirect_with( '/', 'success', 'Parola a fost schimbată. Celelalte sesiuni active au fost închise.' );
	}
}
