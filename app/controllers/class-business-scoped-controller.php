<?php
/**
 * OptiVio — Base controller for pages nested under /businesses/{id}.
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
 * Loads and authorizes the business from the route before anything else.
 */
abstract class Business_Scoped_Controller extends Controller {

	/**
	 * Returns the authorized business from the {id} route parameter.
	 *
	 * @param string $permission view|manage.
	 * @return array
	 */
	protected function business( string $permission = 'view' ): array {
		$id = $this->request->param_int( 'id' );
		Gate::authorize_business( $id, $permission );
		return (array) Businesses_Repository::find( $id );
	}

	/**
	 * Renders a business tab page.
	 *
	 * @param string $template Template.
	 * @param array  $business Business row.
	 * @param string $tab      Active tab key.
	 * @param array  $data     Extra data.
	 * @return void
	 */
	protected function render_tab( string $template, array $business, string $tab, array $data = array() ): void {
		$this->render(
			$template,
			array_merge(
				array(
					'page_title'    => (string) $business['name'],
					'page_subtitle' => ov_business_model_label( (string) $business['business_model'] ),
					'business'      => $business,
					'tab'           => $tab,
					'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
					'errors'        => $this->pull_errors(),
				),
				$data
			)
		);
	}

	/**
	 * Path of a business sub page.
	 *
	 * @param array  $business Business.
	 * @param string $suffix   Suffix (e.g. "/contacts").
	 * @return string
	 */
	protected function path( array $business, string $suffix = '' ): string {
		return '/businesses/' . (int) $business['id'] . $suffix;
	}

	/**
	 * Optional decimal input ("" => null).
	 *
	 * @param string $key Key.
	 * @return float|null
	 */
	protected function decimal( string $key ): ?float {
		$raw = str_replace( array( ' ', ',' ), array( '', '.' ), $this->text( $key, 20 ) );
		return is_numeric( $raw ) ? round( (float) $raw, 2 ) : null;
	}

	/**
	 * Checkbox input.
	 *
	 * @param string $key Key.
	 * @return int 0|1
	 */
	protected function checkbox( string $key ): int {
		return '1' === (string) $this->request->input( $key, '0' ) ? 1 : 0;
	}
}
