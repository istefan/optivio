<?php
/**
 * OptiVio — Contacts, offerings (services/products) and permanent rules of a business.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Http_Exception;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Business_Children_Repository as Children;
use OptiVio\Security\Audit_Log;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Handles the three child collections with one consistent CRUD flow.
 */
final class Business_Children_Controller extends Business_Scoped_Controller {

	/**
	 * Collections: route segment => [table, tab, template, audit entity, label].
	 */
	private const COLLECTIONS = array(
		'contacts'  => array( 'business_contacts', 'contacts', 'businesses/contacts', 'business_contact', 'Contactul' ),
		'offerings' => array( 'business_offerings', 'offerings', 'businesses/offerings', 'business_offering', 'Serviciul' ),
		'rules'     => array( 'business_rules', 'rules', 'businesses/rules', 'business_rule', 'Regula' ),
	);

	/**
	 * Rule categories.
	 */
	public const RULE_CATEGORIES = array(
		'general'  => 'General',
		'ads'      => 'Google Ads',
		'seo'      => 'SEO / conținut',
		'content'  => 'Ton & copywriting',
		'pricing'  => 'Prețuri & oferte',
		'location' => 'Zonă / program',
	);

	/**
	 * List + add form.
	 *
	 * @return void
	 */
	public function index(): void {
		$c        = $this->collection();
		$business = $this->business( 'view' );
		$this->render_tab(
			$c[2],
			$business,
			$c[1],
			array(
				'rows'            => Children::all( $c[0], (int) $business['id'] ),
				'rule_categories' => self::RULE_CATEGORIES,
			)
		);
	}

	/**
	 * Creates a row.
	 *
	 * @return void
	 */
	public function store(): void {
		$c        = $this->collection();
		$business = $this->business( 'manage' );
		$back     = $this->path( $business, '/' . $this->segment() );
		[ $data, $v ] = $this->collect( $this->segment() );

		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), $data );
		}
		if ( 'rules' === $this->segment() ) {
			$data['source']     = 'manual';
			$data['created_by'] = (int) $this->user()['id'];
		}

		$id = Children::create( $c[0], (int) $business['id'], $data );
		Audit_Log::record( $c[3] . '.create', $c[3], $id, array(), (int) $business['id'] );
		$this->redirect_with( $back, 'success', $c[4] . ' a fost adăugat' . ( 'rules' === $this->segment() ? 'ă.' : '.' ) );
	}

	/**
	 * Updates a row (scoped to the business).
	 *
	 * @return void
	 * @throws Http_Exception 404 when the row is not part of the business.
	 */
	public function update(): void {
		$c        = $this->collection();
		$business = $this->business( 'manage' );
		$item_id  = $this->request->param_int( 'item_id' );
		$back     = $this->path( $business, '/' . $this->segment() );

		if ( null === Children::find( $c[0], (int) $business['id'], $item_id ) ) {
			throw Http_Exception::not_found();
		}

		[ $data, $v ] = $this->collect( $this->segment() );
		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), $data );
		}

		Children::update( $c[0], (int) $business['id'], $item_id, $data );
		Audit_Log::record( $c[3] . '.update', $c[3], $item_id, array(), (int) $business['id'] );
		$this->redirect_with( $back, 'success', 'Modificările au fost salvate.' );
	}

	/**
	 * Toggles the active flag (rules and offerings).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function toggle(): void {
		$c        = $this->collection();
		$business = $this->business( 'manage' );
		$item_id  = $this->request->param_int( 'item_id' );
		$row      = Children::find( $c[0], (int) $business['id'], $item_id );

		if ( null === $row || ! array_key_exists( 'is_active', $row ) ) {
			throw Http_Exception::not_found();
		}
		Children::update( $c[0], (int) $business['id'], $item_id, array( 'is_active' => empty( $row['is_active'] ) ? 1 : 0 ) );
		Audit_Log::record( $c[3] . '.toggle', $c[3], $item_id, array( 'active' => empty( $row['is_active'] ) ), (int) $business['id'] );
		$this->redirect_with( $this->path( $business, '/' . $this->segment() ), 'success', empty( $row['is_active'] ) ? 'Activat.' : 'Dezactivat.' );
	}

	/**
	 * Deletes a row (scoped to the business).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function destroy(): void {
		$c        = $this->collection();
		$business = $this->business( 'manage' );
		$item_id  = $this->request->param_int( 'item_id' );

		if ( 0 === Children::delete( $c[0], (int) $business['id'], $item_id ) ) {
			throw Http_Exception::not_found();
		}
		Audit_Log::record( $c[3] . '.delete', $c[3], $item_id, array(), (int) $business['id'] );
		$this->redirect_with( $this->path( $business, '/' . $this->segment() ), 'success', 'Înregistrarea a fost ștearsă.' );
	}

	/**
	 * Route segment (contacts|offerings|rules) derived from the path.
	 *
	 * @return string
	 */
	private function segment(): string {
		$parts = explode( '/', trim( $this->request->path(), '/' ) );
		return (string) ( $parts[2] ?? '' );
	}

	/**
	 * Collection definition for the current route.
	 *
	 * @return array
	 * @throws Http_Exception 404 for unknown segments.
	 */
	private function collection(): array {
		$segment = $this->segment();
		if ( ! isset( self::COLLECTIONS[ $segment ] ) ) {
			throw Http_Exception::not_found();
		}
		return self::COLLECTIONS[ $segment ];
	}

	/**
	 * Collects and validates the fields of a collection.
	 *
	 * @param string $segment Segment.
	 * @return array{0:array,1:Validator}
	 */
	private function collect( string $segment ): array {
		$v = new Validator();

		if ( 'contacts' === $segment ) {
			$raw_email = $this->text( 'email', 190 );
			$data      = array(
				'name'       => $this->text( 'name', 120 ),
				'job_title'  => $this->text( 'job_title', 120 ),
				'email'      => sanitize_email( $raw_email ),
				'phone'      => $this->text( 'phone', 40 ),
				'is_primary' => $this->checkbox( 'is_primary' ),
				'notes'      => $this->text( 'notes', 255 ),
			);
			$v->required( 'name', $data['name'] )
				->pattern( 'phone', $data['phone'], '/^[0-9 +().\-\/]{6,40}$/', 'Număr de telefon invalid.' );
			if ( '' !== $raw_email && '' === $data['email'] ) {
				$v->add( 'email', 'Adresă de email invalidă.' );
			}
			return array( $this->nullify( $data, array( 'job_title', 'email', 'phone', 'notes' ) ), $v );
		}

		if ( 'offerings' === $segment ) {
			$data = array(
				'type'             => sanitize_key( $this->request->input( 'type', 'service' ) ),
				'name'             => $this->text( 'name', 160 ),
				'description'      => $this->textarea( 'description', 3000 ),
				'is_emergency'     => $this->checkbox( 'is_emergency' ),
				'is_recurring'     => $this->checkbox( 'is_recurring' ),
				'avg_ticket_value' => $this->decimal( 'avg_ticket_value' ),
				'margin_pct'       => $this->decimal( 'margin_pct' ),
				'priority'         => max( 1, min( 9, absint( $this->request->input( 'priority', 5 ) ) ) ),
			);
			$v->required( 'name', $data['name'] )->in( 'type', $data['type'], array( 'service', 'product' ) );
			if ( null !== $data['avg_ticket_value'] && $data['avg_ticket_value'] < 0 ) {
				$v->add( 'avg_ticket_value', 'Valoare invalidă.' );
			}
			if ( null !== $data['margin_pct'] && ( $data['margin_pct'] < 0 || $data['margin_pct'] > 100 ) ) {
				$v->add( 'margin_pct', 'Marja trebuie să fie între 0 și 100.' );
			}
			return array( $this->nullify( $data, array( 'description' ) ), $v );
		}

		$data = array(
			'category'  => sanitize_key( $this->request->input( 'category', 'general' ) ),
			'rule_text' => $this->textarea( 'rule_text', 1000 ),
		);
		$v->required( 'rule_text', $data['rule_text'] )->in( 'category', $data['category'], array_keys( self::RULE_CATEGORIES ) );
		return array( $data, $v );
	}

	/**
	 * Converts empty strings to null for optional columns.
	 *
	 * @param array $data Data.
	 * @param array $keys Optional keys.
	 * @return array
	 */
	private function nullify( array $data, array $keys ): array {
		foreach ( $keys as $key ) {
			if ( '' === $data[ $key ] ) {
				$data[ $key ] = null;
			}
		}
		return $data;
	}
}
