<?php
/**
 * OptiVio — Web cron endpoints (no cPanel) and the AJAX batch runner (super admin).
 *
 * /cron/spawn  — loopback POST fired by Web_Cron after a request; secret header generated
 *                automatically (never configured by hand); stateless machine endpoint.
 * /cron/tick   — browser fallback for hosts that block loopback requests (logged-in users).
 * /jobs/run-batch — manual "run now" from Settings → Cron & joburi.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Http_Exception;
use OptiVio\Core\Response;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Rate_Limiter;
use OptiVio\Services\Cron_Runner;
use OptiVio\Services\Web_Cron;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Cron controller.
 */
final class Cron_Controller extends Controller {

	/**
	 * POST /cron/spawn — runs the job queue in the background (loopback from Web_Cron).
	 *
	 * @return void
	 * @throws Http_Exception 404 on a wrong token, 429 when rate limited.
	 */
	public function spawn(): void {
		$bucket = 'cron:ip:' . $this->request->ip();
		if ( Rate_Limiter::blocked_for( $bucket ) > 0 ) {
			throw new Http_Exception( 429, 'Too many requests.' );
		}
		if ( ! hash_equals( Web_Cron::token(), $this->request->header( 'X-OptiVio-Cron' ) ) ) {
			Rate_Limiter::hit( $bucket, 10, 600, 1800 );
			throw Http_Exception::not_found();
		}
		ignore_user_abort( true );
		Response::json( Cron_Runner::run( 'web' ) );
	}

	/**
	 * POST /cron/tick — browser fallback when the host blocks loopback requests.
	 *
	 * @return void
	 */
	public function tick(): void {
		if ( ! Web_Cron::is_due() || ! Web_Cron::claim() ) {
			Response::json( array( 'status' => 'not_due' ) );
		}
		ignore_user_abort( true );
		Response::json( Cron_Runner::run( 'web', 20 ) );
	}

	/**
	 * POST /jobs/run-batch — AJAX batching from the settings screen.
	 *
	 * @return void
	 */
	public function run_batch(): void {
		$result = Cron_Runner::run( 'ajax', 20 );
		Audit_Log::record( 'jobs.run_manual', 'cron_run', (int) $result['run_id'], $result );
		Response::json( $result );
	}
}
