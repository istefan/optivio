<?php
/**
 * OptiVio — User management and business access grants (super admin only).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Http_Exception;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Users_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Auth;
use OptiVio\Security\Gate;
use OptiVio\Security\Password_Policy;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Users controller. Routes are restricted to super_admin by the router.
 */
final class Users_Controller extends Controller {

	/**
	 * Users list.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->render(
			'users/index',
			array(
				'page_title'    => 'Utilizatori',
				'page_subtitle' => 'Super Admin și operatori cu acces granular pe afaceri',
				'rows'          => Users_Repository::all(),
			)
		);
	}

	/**
	 * New user form.
	 *
	 * @return void
	 */
	public function create(): void {
		$this->render(
			'users/form',
			array(
				'page_title'    => 'Utilizator nou',
				'page_subtitle' => 'Creați un operator sau un Super Admin',
				'edit_user'     => null,
				'grants'        => array(),
				'businesses'    => Businesses_Repository::options( null ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Stores a new user.
	 *
	 * @return void
	 */
	public function store(): void {
		$data     = $this->collect();
		$password = (string) $this->request->input( 'password', '' );
		$v        = $this->validate( $data, 0 );

		$policy = Password_Policy::validate( $password, $data['email'] );
		if ( null !== $policy ) {
			$v->add( 'password', $policy );
		}
		if ( $v->fails() ) {
			$this->back_with_errors( '/users/new', $v->errors(), $data );
		}

		$id = Users_Repository::create(
			array(
				'email'                => $data['email'],
				'display_name'         => $data['display_name'],
				'role'                 => $data['role'],
				'status'               => $data['status'],
				'password_hash'        => Password_Policy::hash( $password ),
				'must_change_password' => 1,
				'created_by'           => (int) $this->user()['id'],
			)
		);

		$grants = 'operator' === $data['role'] ? $this->collect_grants() : array();
		Users_Repository::sync_business_grants( $id, $grants, (int) $this->user()['id'] );

		Audit_Log::record(
			'user.create',
			'user',
			$id,
			array(
				'role'       => $data['role'],
				'businesses' => array_keys( $grants ),
			)
		);
		$this->redirect_with( '/users', 'success', 'Utilizatorul a fost creat. La prima autentificare va trebui să își schimbe parola.' );
	}

	/**
	 * Edit form.
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function edit(): void {
		$id   = $this->request->param_int( 'id' );
		$user = Users_Repository::find( $id );
		if ( null === $user ) {
			throw Http_Exception::not_found();
		}

		$this->render(
			'users/form',
			array(
				'page_title'    => 'Editare utilizator',
				'page_subtitle' => (string) $user['email'],
				'edit_user'     => $user,
				'grants'        => Users_Repository::business_grants( $id ),
				'businesses'    => Businesses_Repository::options( null ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Updates a user (profile, role, status, password reset, business grants).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function update(): void {
		$id      = $this->request->param_int( 'id' );
		$current = Users_Repository::find( $id );
		if ( null === $current ) {
			throw Http_Exception::not_found();
		}

		$data     = $this->collect();
		$password = (string) $this->request->input( 'password', '' );
		$v        = $this->validate( $data, $id );
		$self     = Auth::id() === $id;

		// Never lock out the last active super admin, and never demote/disable yourself.
		$loses_admin = 'super_admin' === $current['role'] && ( 'super_admin' !== $data['role'] || 'active' !== $data['status'] );
		if ( $loses_admin && ( $self || Users_Repository::count_active_super_admins() <= 1 ) ) {
			$v->add( 'role', 'Nu puteți retrage drepturile ultimului Super Admin activ sau propriile drepturi.' );
		}
		if ( '' !== $password ) {
			$policy = Password_Policy::validate( $password, $data['email'] );
			if ( null !== $policy ) {
				$v->add( 'password', $policy );
			}
		}
		if ( $v->fails() ) {
			$this->back_with_errors( '/users/' . $id . '/edit', $v->errors(), $data );
		}

		Users_Repository::update( $id, $data );

		$security_change = $current['role'] !== $data['role'] || $current['status'] !== $data['status'] || $current['email'] !== $data['email'];
		if ( '' !== $password ) {
			Users_Repository::set_password( $id, Password_Policy::hash( $password ), ! $self );
			Audit_Log::record( 'user.password_reset', 'user', $id );
		} elseif ( $security_change && ! $self ) {
			Users_Repository::bump_session_version( $id );
		}
		if ( $self && '' !== $password ) {
			Auth::refresh_session_version();
		}

		$old_grants = Users_Repository::business_grants( $id );
		ksort( $old_grants );
		$grants    = 'operator' === $data['role'] ? $this->collect_grants() : array();
		if ( $old_grants !== $grants ) {
			Users_Repository::sync_business_grants( $id, $grants, (int) $this->user()['id'] );
			Gate::flush();
			Audit_Log::record(
				'user.access_changed',
				'user',
				$id,
				array(
					'before' => $old_grants,
					'after'  => $grants,
				)
			);
		}

		Audit_Log::record(
			'user.update',
			'user',
			$id,
			array(
				'role'   => $data['role'],
				'status' => $data['status'],
			)
		);
		$this->redirect_with( '/users', 'success', 'Utilizatorul a fost actualizat.' );
	}

	/**
	 * Collects sanitized user fields.
	 *
	 * @return array
	 */
	private function collect(): array {
		return array(
			'display_name' => $this->text( 'display_name', 120 ),
			'email'        => sanitize_email( $this->request->input( 'email', '' ) ),
			'role'         => sanitize_key( $this->request->input( 'role', 'operator' ) ),
			'status'       => sanitize_key( $this->request->input( 'status', 'active' ) ),
		);
	}

	/**
	 * Validates user fields.
	 *
	 * @param array $data Data.
	 * @param int   $id   Current user id (0 when creating).
	 * @return Validator
	 */
	private function validate( array $data, int $id ): Validator {
		$v = new Validator();
		$v->required( 'display_name', $data['display_name'] )
			->email( 'email', $data['email'] )
			->in( 'role', $data['role'], array( 'super_admin', 'operator' ) )
			->in( 'status', $data['status'], array( 'active', 'disabled' ) );
		if ( '' !== $data['email'] && Users_Repository::email_exists( $data['email'], $id ) ) {
			$v->add( 'email', 'Există deja un utilizator cu acest email.' );
		}
		return $v;
	}

	/**
	 * Collects business grants from input: grants[business_id] = view|manage|none.
	 * Only existing business ids are kept.
	 *
	 * @return array<int,string>
	 */
	private function collect_grants(): array {
		$input  = $this->request->input( 'grants', array() );
		$valid  = Businesses_Repository::options( null );
		$grants = array();
		if ( ! is_array( $input ) ) {
			return $grants;
		}
		foreach ( $input as $business_id => $permission ) {
			$business_id = absint( $business_id );
			$permission  = sanitize_key( $permission );
			if ( isset( $valid[ $business_id ] ) && in_array( $permission, array( 'view', 'manage' ), true ) ) {
				$grants[ $business_id ] = $permission;
			}
		}
		ksort( $grants );
		return $grants;
	}
}
