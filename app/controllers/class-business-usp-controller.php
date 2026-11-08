<?php
/**
 * OptiVio — USP of a business: deterministic proposal + manual editing.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Business_Children_Repository as Children;
use OptiVio\Security\Audit_Log;
use OptiVio\Services\Usp_Builder;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * USP controller.
 */
final class Business_Usp_Controller extends Business_Scoped_Controller {

	/**
	 * USP page.
	 *
	 * @return void
	 */
	public function index(): void {
		$business = $this->business( 'view' );
		$this->render_tab(
			'businesses/usp',
			$business,
			'usp',
			array(
				'proposal' => Usp_Builder::propose( $business, Children::all( 'business_offerings', (int) $business['id'] ) ),
			)
		);
	}

	/**
	 * Saves the USP (manual text, or the computed proposal when accepted).
	 *
	 * @return void
	 */
	public function save(): void {
		$business = $this->business( 'manage' );
		$back     = $this->path( $business, '/usp' );
		$accept   = 'computed' === sanitize_key( $this->request->input( 'source', 'manual' ) );

		if ( $accept ) {
			$text = Usp_Builder::propose( $business, Children::all( 'business_offerings', (int) $business['id'] ) )['text'];
		} else {
			$text = $this->textarea( 'usp_text', 2000 );
		}

		$v = ( new Validator() )->required( 'usp_text', $text, 'USP-ul nu poate fi gol.' );
		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), array( 'usp_text' => $text ) );
		}

		Database::instance()->update(
			'businesses',
			array(
				'usp_text'       => $text,
				'usp_source'     => $accept ? 'computed' : 'manual',
				'usp_updated_at' => Database::now(),
			),
			array( 'id' => (int) $business['id'] )
		);
		Audit_Log::record( 'business.usp_update', 'business', (int) $business['id'], array( 'source' => $accept ? 'computed' : 'manual' ), (int) $business['id'] );
		$this->redirect_with( $back, 'success', 'USP-ul a fost salvat. Toate analizele viitoare vor ține cont de el.' );
	}
}
