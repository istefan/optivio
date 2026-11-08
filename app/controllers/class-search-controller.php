<?php
/**
 * OptiVio — Quick search (scoped to accessible businesses).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Security\Gate;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Search controller.
 */
final class Search_Controller extends Controller {

	/**
	 * Search results page.
	 *
	 * @return void
	 */
	public function index(): void {
		$term    = mb_substr( sanitize_text_field( $this->request->query( 'q', '' ) ), 0, 100 );
		$results = mb_strlen( $term ) >= 2 ? Businesses_Repository::search( $term, Gate::accessible_business_ids() ) : array();

		$this->render(
			'search/index',
			array(
				'page_title'    => 'Căutare',
				'page_subtitle' => '' !== $term ? sprintf( 'Rezultate pentru „%s”', $term ) : 'Căutați afaceri după nume sau CUI',
				'term'          => $term,
				'results'       => $results,
			)
		);
	}
}
