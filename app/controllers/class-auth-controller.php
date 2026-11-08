<?php
/**
 * OptiVio — Login / logout.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Response;
use OptiVio\Core\Session;
use OptiVio\Core\View;
use OptiVio\Repositories\Users_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Auth;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Authentication controller.
 */
final class Auth_Controller extends Controller {

	/**
	 * Shows the login form.
	 *
	 * @return void
	 */
	public function show_login(): void {
		if ( 0 === Users_Repository::count() ) {
			Response::redirect( '/setup' );
		}

		View::render(
			'auth/login',
			array(
				'page_title' => 'Autentificare',
				'flashes'    => Session::pull_flashes(),
				'old'        => Session::pull_old_input(),
			),
			'auth'
		);
	}

	/**
	 * Handles the login form.
	 *
	 * @return void
	 */
	public function login(): void {
		$email    = sanitize_email( $this->request->input( 'email', '' ) );
		$password = $this->request->input( 'password', '' );
		$password = is_string( $password ) ? $password : '';

		if ( '' === $email || '' === $password || strlen( $password ) > 256 ) {
			Session::set_old_input( array( 'email' => $email ) );
			$this->redirect_with( '/login', 'error', 'Introduceți emailul și parola.' );
		}

		$result = Auth::attempt( $email, $password, $this->request );
		if ( ! $result['ok'] ) {
			Session::set_old_input( array( 'email' => $email ) );
			$this->redirect_with( '/login', 'error', (string) $result['error'] );
		}

		$intended = (string) Session::get( '_intended', '/' );
		Session::remove( '_intended' );
		$intended = preg_match( '#^/[A-Za-z0-9/_\-]*$#', $intended ) && '/logout' !== $intended ? $intended : '/';
		Response::redirect( $intended );
	}

	/**
	 * Logs out.
	 *
	 * @return void
	 */
	public function logout(): void {
		Audit_Log::record( 'auth.logout', 'user', Auth::id() );
		Auth::logout();
		Session::start( $this->request->is_https() );
		$this->redirect_with( '/login', 'success', 'V-ați deconectat cu succes.' );
	}
}
