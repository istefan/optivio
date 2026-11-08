<?php
/**
 * OptiVio — HTTP cron trigger (token) and AJAX batch runner (super admin).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Config;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Response;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Rate_Limiter;
use OptiVio\Services\Cron_Runner;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Cron controller.
 */
final class Cron_Controller extends Controller {

	/**
	 * GET /cron/run?token=... for hosts without CLI cron.
	 *
	 * @return void
	 * @throws Http_Exception 404 when disabled or token invalid.
	 */
	public function run_http(): void {
		$expected = (string) Config::get( 'cron_token', '' );
		$token    = (string) sanitize_text_field( $this->request->query( 'token', '' ) );
		$bucket   = 'cron:ip:' . $this->request->ip();

		if ( Rate_Limiter::blocked_for( $bucket ) > 0 ) {
			throw new Http_Exception( 429, 'Too many requests.' );
		}
		if ( strlen( $expected ) < 24 || ! hash_equals( $expected, $token ) ) {
			Rate_Limiter::hit( $bucket, 10, 600, 1800 );
			throw Http_Exception::not_found();
		}

		ignore_user_abort( true );
		$result = Cron_Runner::run( 'http' );
		Response::json( $result );
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
