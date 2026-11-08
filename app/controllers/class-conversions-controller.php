<?php
/**
 * OptiVio — Conversions list (phone clicks + form entries).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Security\Gate;
use OptiVio\Services\Dashboard_Metrics;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Conversions controller.
 */
final class Conversions_Controller extends Controller {

	/**
	 * Paginated conversions in the current scope.
	 *
	 * @return void
	 */
	public function index(): void {
		$kind = sanitize_key( $this->request->query( 'kind', 'all' ) );
		$kind = in_array( $kind, array( 'all', 'phone', 'forms', 'spam' ), true ) ? $kind : 'all';
		$page = max( 1, absint( $this->request->query( 'page', 1 ) ) );
		$per  = 25;

		$metrics = new Dashboard_Metrics( Gate::scope_business_ids() );
		$total   = $metrics->count_conversions( $kind );

		$this->render(
			'conversions/index',
			array(
				'page_title'    => 'Conversii',
				'page_subtitle' => 'Click-uri pe telefon și formulare, cu sursa de trafic și filtrare anti-spam deterministă',
				'rows'          => $metrics->recent_conversions( $per, ( $page - 1 ) * $per, $kind ),
				'total'         => $total,
				'page'          => $page,
				'pages'         => max( 1, (int) ceil( $total / $per ) ),
				'kind'          => $kind,
			)
		);
	}
}
