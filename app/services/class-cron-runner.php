<?php
/**
 * OptiVio — Cron runner shared by CLI, HTTP trigger and AJAX batching.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;
use OptiVio\Core\Logger;
use OptiVio\Repositories\Settings_Repository;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Executes one cron tick: schedules recurring jobs and processes the queue within a time budget.
 */
final class Cron_Runner {

	/**
	 * Runs a tick.
	 *
	 * @param string   $trigger cli|http|ajax.
	 * @param int|null $budget  Time budget in seconds (defaults to settings).
	 * @return array{run_id:int,processed:int,failed:int,pending:int,status:string}
	 */
	public static function run( string $trigger, ?int $budget = null ): array {
		$db     = Database::instance();
		$budget = $budget ?? max( 10, min( 55, (int) Settings_Repository::get( 'cron.batch_seconds', 45 ) ) );
		$lock   = 'optivio_cron';

		// Prevent overlapping runs (shared hosting may start a new cron before the previous ended).
		$got_lock = (int) $db->get_var( 'SELECT GET_LOCK(?, 0)', array( $lock ) );
		if ( 1 !== $got_lock ) {
			return array(
				'run_id'    => 0,
				'processed' => 0,
				'failed'    => 0,
				'pending'   => Job_Queue::stats()['pending'],
				'status'    => 'locked',
			);
		}

		$run_id = $db->insert( 'cron_runs', array( 'trigger_source' => $trigger ) );
		$status = 'ok';
		$result = array(
			'processed' => 0,
			'failed'    => 0,
		);
		$note   = null;

		try {
			@set_time_limit( $budget + 15 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
			Job_Handlers::schedule_recurring();
			$result = ( new Job_Queue() )->work( $budget );
		} catch ( Throwable $e ) {
			$status = 'error';
			$note   = mb_substr( $e->getMessage(), 0, 500 );
			Logger::error( 'Cron run failed', array( 'exception' => $e ) );
		} finally {
			$db->query(
				'UPDATE cron_runs SET finished_at = UTC_TIMESTAMP(), jobs_processed = ?, jobs_failed = ?, status = ?, message = ? WHERE id = ?',
				array( (int) $result['processed'], (int) $result['failed'], $status, $note, $run_id )
			);
			$db->get_var( 'SELECT RELEASE_LOCK(?)', array( $lock ) );
		}

		return array(
			'run_id'    => $run_id,
			'processed' => (int) $result['processed'],
			'failed'    => (int) $result['failed'],
			'pending'   => Job_Queue::stats()['pending'],
			'status'    => $status,
		);
	}

	/**
	 * Last cron runs.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array>
	 */
	public static function recent( int $limit = 8 ): array {
		return Database::instance()->get_results( 'SELECT * FROM cron_runs ORDER BY id DESC LIMIT ?', array( $limit ) );
	}
}
