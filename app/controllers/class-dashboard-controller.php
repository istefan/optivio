<?php
/**
 * OptiVio — Main dashboard.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Response;
use OptiVio\Repositories\Audit_Repository;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Gate;
use OptiVio\Services\Dashboard_Metrics;
use OptiVio\Services\Readiness_Score;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Dashboard controller.
 */
final class Dashboard_Controller extends Controller {

	/**
	 * Allowed periods (days).
	 */
	private const PERIODS = array( 7, 30, 90 );

	/**
	 * Allowed chart metrics.
	 */
	private const METRICS = array( 'conversions', 'phone', 'forms', 'revenue' );

	/**
	 * Dashboard page.
	 *
	 * @return void
	 */
	public function index(): void {
		$user      = $this->user();
		$scope     = Gate::scope_business_ids();
		$active_id = Gate::active_business_id();
		$metrics   = new Dashboard_Metrics( $scope );
		$days      = 30;

		$readiness = $active_id > 0 ? Readiness_Score::for_business( $active_id ) : null;
		$scope_rs  = Readiness_Score::for_scope( $scope );

		$this->render(
			'dashboard/index',
			array(
				'page_title'      => 'Dashboard',
				'page_subtitle'   => $active_id > 0 ? 'Performanța afacerii selectate' : 'Privire de ansamblu asupra tuturor afacerilor',
				'has_businesses'  => ! empty( Businesses_Repository::options( Gate::accessible_business_ids() ) ),
				'kpis'            => $metrics->kpis( $days ),
				'series'          => $metrics->series( $days, 'conversions' ),
				'weekdays'        => $metrics->weekday_distribution( $days ),
				'recent'          => $metrics->recent_conversions( 6 ),
				'activity'        => Audit_Repository::recent_for( (int) $user['id'], Gate::accessible_business_ids(), 5 ),
				'readiness'       => $readiness,
				'readiness_score' => null !== $readiness ? $readiness['score'] : $scope_rs['score'],
				'readiness_count' => $scope_rs['count'],
				'active_id'       => $active_id,
				'currency'        => (string) Settings_Repository::get( 'general.currency', 'RON' ),
				'days'            => $days,
			)
		);
	}

	/**
	 * AJAX: chart series for a period/metric (scope enforced server side).
	 *
	 * @return void
	 */
	public function chart(): void {
		$days   = absint( $this->request->query( 'days', 30 ) );
		$metric = sanitize_key( $this->request->query( 'metric', 'conversions' ) );

		if ( ! in_array( $days, self::PERIODS, true ) ) {
			$days = 30;
		}
		if ( ! in_array( $metric, self::METRICS, true ) ) {
			$metric = 'conversions';
		}

		$metrics = new Dashboard_Metrics( Gate::scope_business_ids() );
		Response::json(
			array(
				'series'   => $metrics->series( $days, $metric ),
				'weekdays' => $metrics->weekday_distribution( $days ),
			)
		);
	}
}
