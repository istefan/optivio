<?php
/**
 * OptiVio — Audit log viewer (super admin).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Repositories\Audit_Repository;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Users_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Audit controller.
 */
final class Audit_Controller extends Controller {

	/**
	 * Paginated audit log with filters.
	 *
	 * @return void
	 */
	public function index(): void {
		$filters = array(
			'action'      => preg_replace( '/[^a-z_.]/', '', strtolower( (string) sanitize_text_field( $this->request->query( 'action', '' ) ) ) ),
			'user_id'     => absint( $this->request->query( 'user_id', 0 ) ),
			'business_id' => absint( $this->request->query( 'business_id', 0 ) ),
		);
		$page    = max( 1, absint( $this->request->query( 'page', 1 ) ) );
		$per     = 50;
		$result  = Audit_Repository::paginate( $filters, $page, $per );

		$this->render(
			'audit/index',
			array(
				'page_title'    => 'Jurnal audit',
				'page_subtitle' => 'Toate acțiunile sensibile din aplicație',
				'rows'          => $result['rows'],
				'total'         => $result['total'],
				'page'          => $page,
				'pages'         => max( 1, (int) ceil( $result['total'] / $per ) ),
				'filters'       => $filters,
				'users'         => Users_Repository::all(),
				'businesses'    => Businesses_Repository::options( null ),
			)
		);
	}
}
