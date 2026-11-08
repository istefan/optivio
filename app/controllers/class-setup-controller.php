<?php
/**
 * OptiVio — One-time web installer (creates the first Super Admin).
 *
 * Available only while the users table is empty AND the install_token from
 * config.php is provided. Rate limited.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Config;
use OptiVio\Core\Crypto;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Response;
use OptiVio\Core\Session;
use OptiVio\Core\Validator;
use OptiVio\Core\View;
use OptiVio\Repositories\Users_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Auth;
use OptiVio\Security\Password_Policy;
use OptiVio\Security\Rate_Limiter;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Installer controller.
 */
final class Setup_Controller extends Controller {

	/**
	 * Shows the installer.
	 *
	 * @return void
	 */
	public function show(): void {
		$this->guard();
		View::render(
			'auth/setup',
			array(
				'page_title'    => 'Instalare',
				'flashes'       => Session::pull_flashes(),
				'old'           => Session::pull_old_input(),
				'errors'        => $this->pull_errors(),
				'crypto_ready'  => Crypto::is_configured(),
				'token_present' => '' !== (string) Config::get( 'install_token', '' ),
			),
			'auth'
		);
	}

	/**
	 * Creates the first Super Admin.
	 *
	 * @return void
	 */
	public function store(): void {
		$this->guard();

		$bucket = 'setup:ip:' . $this->request->ip();
		if ( Rate_Limiter::blocked_for( $bucket ) > 0 ) {
			$this->redirect_with( '/setup', 'error', 'Prea multe încercări. Reveniți mai târziu.' );
		}

		$token    = (string) $this->request->input( 'install_token', '' );
		$expected = (string) Config::get( 'install_token', '' );
		$name     = $this->text( 'display_name', 120 );
		$email    = sanitize_email( $this->request->input( 'email', '' ) );
		$password = (string) $this->request->input( 'password', '' );
		$confirm  = (string) $this->request->input( 'password_confirm', '' );

		$v = new Validator();
		if ( strlen( $expected ) < 16 || ! hash_equals( $expected, $token ) ) {
			Rate_Limiter::hit( $bucket, 5, 900, 3600 );
			$v->add( 'install_token', 'Token de instalare invalid.' );
		}
		$v->required( 'display_name', $name )->email( 'email', $email );
		$policy = Password_Policy::validate( $password, $email );
		if ( null !== $policy ) {
			$v->add( 'password', $policy );
		}
		if ( ! hash_equals( $password, $confirm ) ) {
			$v->add( 'password_confirm', 'Parolele nu coincid.' );
		}

		if ( $v->fails() ) {
			$this->back_with_errors(
				'/setup',
				$v->errors(),
				array(
					'display_name' => $name,
					'email'        => $email,
				)
			);
		}

		$user_id = Users_Repository::create(
			array(
				'email'         => $email,
				'display_name'  => $name,
				'role'          => 'super_admin',
				'password_hash' => Password_Policy::hash( $password ),
			)
		);
		Audit_Log::record( 'setup.super_admin', 'user', $user_id, array(), null, $user_id );

		$user = Users_Repository::find( $user_id );
		Auth::login( $user_id, (int) $user['session_version'], $this->request );
		$this->redirect_with( '/', 'success', 'Instalare finalizată. Bine ați venit în OptiVio!' );
	}

	/**
	 * Installer is only reachable while no user exists.
	 *
	 * @return void
	 * @throws Http_Exception 404 once installed.
	 */
	private function guard(): void {
		if ( Users_Repository::count() > 0 ) {
			Response::redirect( '/login' );
		}
		if ( ! Crypto::is_configured() && $this->request->is_post() ) {
			throw new Http_Exception( 503, 'Cheia master nu este configurată în config/config.php.' );
		}
	}
}
