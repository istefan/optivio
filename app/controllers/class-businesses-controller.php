<?php
/**
 * OptiVio — Businesses (Stage 1: core profile; Stage 2 adds contacts, offerings, sites, rules).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Session;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use OptiVio\Services\Business_Deleter;
use OptiVio\Services\Readiness_Score;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Businesses controller.
 */
final class Businesses_Controller extends Controller {

	/**
	 * List of accessible businesses.
	 *
	 * @return void
	 */
	public function index(): void {
		$status = sanitize_key( $this->request->query( 'status', '' ) );
		$status = in_array( $status, array( 'active', 'paused', 'archived' ), true ) ? $status : '';
		$rows   = Businesses_Repository::list_accessible( Gate::accessible_business_ids(), $status );

		foreach ( $rows as &$row ) {
			$row['readiness'] = Readiness_Score::for_business( (int) $row['id'] )['score'];
		}
		unset( $row );

		$this->render(
			'businesses/index',
			array(
				'page_title'    => 'Afaceri',
				'page_subtitle' => 'Clienții gestionați și gradul lor de pregătire',
				'rows'          => $rows,
				'status'        => $status,
			)
		);
	}

	/**
	 * Business overview.
	 *
	 * @return void
	 */
	public function show(): void {
		$id = $this->request->param_int( 'id' );
		Gate::authorize_business( $id );
		$business = Businesses_Repository::find( $id );

		$this->render(
			'businesses/show',
			array(
				'page_title'    => (string) $business['name'],
				'page_subtitle' => ov_business_model_label( (string) $business['business_model'] ),
				'business'      => $business,
				'tab'           => 'overview',
				'readiness'     => Readiness_Score::for_business( $id ),
				'can_manage'    => Gate::can_access_business( $id, 'manage' ),
				'operators'     => Database::instance()->get_results(
					'SELECT u.display_name, u.email, a.permission FROM user_business_access a JOIN users u ON u.id = a.user_id WHERE a.business_id = ? ORDER BY u.display_name',
					array( $id )
				),
				'sites'         => Database::instance()->get_results( 'SELECT id, name, url, connector_status, status FROM sites WHERE business_id = ? ORDER BY name', array( $id ) ),
			)
		);
	}

	/**
	 * New business form (super admin).
	 *
	 * @return void
	 */
	public function create(): void {
		$this->render(
			'businesses/form',
			array(
				'page_title'    => 'Afacere nouă',
				'page_subtitle' => 'Datele de bază ale clientului',
				'business'      => null,
				'errors'        => $this->pull_errors(),
				'can_status'    => true,
			)
		);
	}

	/**
	 * Stores a new business.
	 *
	 * @return void
	 */
	public function store(): void {
		$data = $this->collect( true );
		$v    = $this->validate( $data );
		if ( $v->fails() ) {
			$this->back_with_errors( '/businesses/new', $v->errors(), $data );
		}

		$id = Businesses_Repository::create( $data, (int) $this->user()['id'] );
		Audit_Log::record( 'business.create', 'business', $id, array( 'name' => $data['name'] ), $id );
		$this->redirect_with( '/businesses/' . $id, 'success', 'Afacerea a fost creată.' );
	}

	/**
	 * Edit form.
	 *
	 * @return void
	 */
	public function edit(): void {
		$id = $this->request->param_int( 'id' );
		Gate::authorize_business( $id, 'manage' );

		$this->render(
			'businesses/form',
			array(
				'page_title'    => 'Editare afacere',
				'page_subtitle' => 'Profil, model de afacere și USP',
				'business'      => Businesses_Repository::find( $id ),
				'tab'           => 'profile',
				'errors'        => $this->pull_errors(),
				'can_status'    => Gate::is_super_admin( $this->user() ),
			)
		);
	}

	/**
	 * Updates a business.
	 *
	 * @return void
	 * @throws Http_Exception When not allowed.
	 */
	public function update(): void {
		$id = $this->request->param_int( 'id' );
		Gate::authorize_business( $id, 'manage' );

		$is_admin = Gate::is_super_admin( $this->user() );
		$data     = $this->collect( $is_admin );
		$v        = $this->validate( $data );
		if ( $v->fails() ) {
			$this->back_with_errors( '/businesses/' . $id . '/edit', $v->errors(), $data );
		}

		$before = Businesses_Repository::find( $id );
		Businesses_Repository::update( $id, $data );

		$changed = array();
		foreach ( $data as $key => $value ) {
			if ( (string) ( $before[ $key ] ?? '' ) !== (string) $value ) {
				$changed[] = $key;
			}
		}
		Audit_Log::record( 'business.update', 'business', $id, array( 'changed' => $changed ), $id );
		$this->redirect_with( '/businesses/' . $id, 'success', 'Modificările au fost salvate.' );
	}

	/**
	 * Confirmation page for the permanent deletion (Super Admin).
	 *
	 * @return void
	 */
	public function confirm_delete(): void {
		$id       = $this->request->param_int( 'id' );
		$business = Businesses_Repository::find( $id );
		if ( null === $business ) {
			throw new Http_Exception( 404 );
		}
		$this->render(
			'businesses/delete',
			array(
				'page_title'    => 'Ștergere definitivă',
				'page_subtitle' => (string) $business['name'],
				'business'      => $business,
				'summary'       => Business_Deleter::summary( $id ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Permanently deletes a business (name typed + password re-authentication).
	 *
	 * @return void
	 */
	public function destroy(): void {
		$id       = $this->request->param_int( 'id' );
		$business = Businesses_Repository::find( $id );
		if ( null === $business ) {
			throw new Http_Exception( 404 );
		}
		$back   = '/businesses/' . $id . '/delete';
		$errors = array();
		if ( trim( (string) $this->request->input( 'confirm_name', '' ) ) !== (string) $business['name'] ) {
			$errors['confirm_name'] = 'Numele nu corespunde.';
		}
		$hash = (string) Database::instance()->get_var( 'SELECT password_hash FROM users WHERE id = ?', array( (int) $this->user()['id'] ) );
		if ( ! password_verify( (string) $this->request->input( 'password', '' ), $hash ) ) {
			$errors['password'] = 'Parola este greșită.';
		}
		if ( ! empty( $errors ) ) {
			Audit_Log::record( 'business.delete_denied', 'business', $id, array( 'fields' => array_keys( $errors ) ), $id );
			$this->back_with_errors( $back, $errors, array() );
		}

		$deleted = Business_Deleter::delete( $id );
		if ( (int) Session::get( 'active_business_id', 0 ) === $id ) {
			Session::remove( 'active_business_id' );
		}
		Audit_Log::record(
			'business.deleted',
			'business',
			$id,
			array(
				'name'    => (string) $business['name'],
				'deleted' => array_filter( $deleted ),
			),
			null
		);
		$this->redirect_with( '/businesses', 'success', 'Afacerea „' . $business['name'] . '” și toate datele ei au fost șterse definitiv.' );
	}

	/**
	 * Collects and sanitizes form input.
	 *
	 * @param bool $with_status Whether status may be changed.
	 * @return array
	 */
	private function collect( bool $with_status ): array {
		$avg      = str_replace( ',', '.', $this->text( 'avg_job_value', 20 ) );
		$capacity = $this->text( 'daily_capacity', 6 );

		$data = array(
			'name'            => $this->text( 'name', 160 ),
			'business_model'  => sanitize_key( $this->request->input( 'business_model', 'other' ) ),
			'legal_name'      => $this->text( 'legal_name', 200 ),
			'cui'             => strtoupper( str_replace( ' ', '', $this->text( 'cui', 20 ) ) ),
			'reg_com'         => strtoupper( $this->text( 'reg_com', 40 ) ),
			'vat_payer'       => '1' === (string) $this->request->input( 'vat_payer', '0' ) ? 1 : 0,
			'billing_address' => $this->text( 'billing_address', 255 ),
			'billing_city'    => $this->text( 'billing_city', 100 ),
			'billing_county'  => $this->text( 'billing_county', 100 ),
			'billing_email'   => sanitize_email( $this->request->input( 'billing_email', '' ) ),
			'phone'           => $this->text( 'phone', 40 ),
			'service_area'    => $this->text( 'service_area', 255 ),
			'description'     => $this->textarea( 'description', 5000 ),
			'target_audience' => $this->textarea( 'target_audience', 3000 ),
			'avg_job_value'   => is_numeric( $avg ) ? round( (float) $avg, 2 ) : null,
			'daily_capacity'  => '' !== $capacity && ctype_digit( $capacity ) ? (int) $capacity : null,
		);
		if ( 0 === $this->request->param_int( 'id' ) ) {
			$data['usp_text'] = $this->textarea( 'usp_text', 2000 );
		}
		if ( $with_status ) {
			$data['status'] = sanitize_key( $this->request->input( 'status', 'active' ) );
		}
		$data['_billing_email_raw'] = sanitize_text_field( $this->request->input( 'billing_email', '' ) );
		return $data;
	}

	/**
	 * Validates business data.
	 *
	 * @param array $data Data (modified: helper keys removed).
	 * @return Validator
	 */
	private function validate( array &$data ): Validator {
		$v = new Validator();
		$v->required( 'name', $data['name'] )
			->in( 'business_model', $data['business_model'], array_keys( ov_business_models() ) )
			->pattern( 'cui', $data['cui'], '/^(RO)?[0-9]{2,10}$/', 'CUI invalid (ex. RO12345678).' )
			->pattern( 'reg_com', $data['reg_com'], '/^[JFC][0-9]{1,2}\/[0-9]{1,8}\/[0-9]{4}$|^J[0-9]{4}[0-9]{6,}[A-Z0-9]*$/', 'Nr. Reg. Com. invalid (ex. J40/1234/2020).' );

		if ( '' !== $data['_billing_email_raw'] && '' === $data['billing_email'] ) {
			$v->add( 'billing_email', 'Adresă de email invalidă.' );
		}
		if ( isset( $data['status'] ) ) {
			$v->in( 'status', $data['status'], array( 'active', 'paused', 'archived' ) );
		}
		if ( null !== $data['avg_job_value'] && $data['avg_job_value'] < 0 ) {
			$v->add( 'avg_job_value', 'Valoare invalidă.' );
		}
		if ( null !== $data['daily_capacity'] && $data['daily_capacity'] > 65535 ) {
			$v->add( 'daily_capacity', 'Valoare invalidă.' );
		}
		unset( $data['_billing_email_raw'] );
		return $v;
	}
}
