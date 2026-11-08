<?php
/**
 * OptiVio — Form entry detail (decrypted PII for authorized users) and manual spam override.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use OptiVio\Services\Event_Ingestor;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Form entries controller.
 */
final class Form_Entries_Controller extends Controller {

	/**
	 * Entry detail.
	 *
	 * @return void
	 */
	public function show(): void {
		[ $entry, $business_id ] = $this->authorized_entry( 'view' );
		$this->render(
			'forms/show',
			array(
				'page_title'    => 'Formular #' . (int) $entry['external_entry_id'],
				'page_subtitle' => (string) $entry['form_title'] . ' · ' . (string) $entry['site_name'],
				'entry'         => $entry,
				'fields'        => Event_Ingestor::decrypt_fields( $entry ),
				'can_manage'    => Gate::can_access_business( $business_id, 'manage' ),
			)
		);
	}

	/**
	 * Manual spam override: clean | spam | reset.
	 *
	 * @return void
	 */
	public function override(): void {
		[ $entry, $business_id ] = $this->authorized_entry( 'manage' );
		$action                  = sanitize_key( $this->request->input( 'action', '' ) );
		$map                     = array(
			'clean' => 'marked_clean',
			'spam'  => 'marked_spam',
			'reset' => 'none',
		);
		if ( ! isset( $map[ $action ] ) ) {
			throw Http_Exception::not_found();
		}

		Database::instance()->update( 'form_entries', array( 'spam_override' => $map[ $action ] ), array( 'id' => (int) $entry['id'] ) );
		Audit_Log::record( 'form.spam_override', 'form_entry', (int) $entry['id'], array( 'override' => $map[ $action ] ), $business_id );
		$this->redirect_with( '/forms/' . (int) $entry['id'], 'success', 'Clasificarea a fost actualizată.' );
	}

	/**
	 * Loads an entry and authorizes access through its site's business.
	 *
	 * @param string $permission view|manage.
	 * @return array{0:array,1:int}
	 * @throws Http_Exception 404.
	 */
	private function authorized_entry( string $permission ): array {
		$entry = Database::instance()->get_row(
			'SELECT fe.*, s.name AS site_name, s.business_id FROM form_entries fe JOIN sites s ON s.id = fe.site_id WHERE fe.id = ?',
			array( $this->request->param_int( 'id' ) )
		);
		if ( null === $entry ) {
			throw Http_Exception::not_found();
		}
		Gate::authorize_business( (int) $entry['business_id'], $permission );
		return array( $entry, (int) $entry['business_id'] );
	}
}
