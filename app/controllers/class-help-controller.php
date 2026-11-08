<?php
/**
 * OptiVio — Help page: step-by-step onboarding, menu reference and integration guides.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Security\Gate;
use OptiVio\Services\Integration_Guides;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Help controller (static documentation, available to every authenticated user).
 */
final class Help_Controller extends Controller {

	/**
	 * Help page.
	 *
	 * @return void
	 */
	public function index(): void {
		$guides = array();
		foreach ( array_keys( Integration_Guides::all() ) as $key ) {
			$guides[ $key ] = Integration_Guides::get( $key );
		}

		$this->render(
			'help/index',
			array(
				'page_title'    => 'Ajutor',
				'page_subtitle' => 'Ghid pas cu pas: configurare, wizard-ul de onboarding, plugin WordPress, integrări, backup și lucrul zilnic',
				'guides'        => $guides,
				'is_admin'      => Gate::is_super_admin( $this->user() ),
			)
		);
	}
}
