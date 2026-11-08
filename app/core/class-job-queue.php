<?php
/**
 * OptiVio — MySQL backed job queue for shared hosting (Cron / HTTP / AJAX batching).
 *
 * Handlers NEVER call AI providers (see RULES.md R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use OptiVio\Services\Job_Handlers;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Job queue.
 *
 * A handler is a callable( array $job, Job_Queue $queue ): array|null returning either
 * null (finished) or a new cursor_state array (more batches needed; re-queued immediately).
 */
final class Job_Queue {

	/**
	 * True while jobs are being processed in this PHP process (AI calls are refused then).
	 *
	 * @var bool
	 */
	public static bool $running = false;

	/**
	 * Worker token for this process.
	 *
	 * @var string
	 */
	private string $worker;

	/**
	 * Database.
	 *
	 * @var Database
	 */
	private Database $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->worker = bin2hex( random_bytes( 16 ) );
		$this->db     = Database::instance();
	}

	/**
	 * Enqueues a job. When unique_key is set and an identical active job exists, it is not duplicated.
	 *
	 * @param string $type    Job type (must exist in Job_Handlers::map()).
	 * @param array  $payload Payload.
	 * @param array  $options queue, priority, delay, business_id, site_id, unique, max_attempts, created_by.
	 * @return int Job id (existing id when deduplicated).
	 */
	public static function dispatch( string $type, array $payload = array(), array $options = array() ): int {
		$db     = Database::instance();
		$unique = isset( $options['unique'] ) ? hash( 'sha256', $type . '|' . (string) $options['unique'] ) : null;

		if ( null !== $unique ) {
			$existing = $db->get_var(
				"SELECT id FROM jobs WHERE unique_key = ? AND status IN ('pending','running','done') LIMIT 1",
				array( $unique )
			);
			if ( null !== $existing ) {
				return (int) $existing;
			}
		}

		return $db->insert(
			'jobs',
			array(
				'queue'        => (string) ( $options['queue'] ?? 'default' ),
				'type'         => $type,
				'business_id'  => isset( $options['business_id'] ) ? (int) $options['business_id'] : null,
				'site_id'      => isset( $options['site_id'] ) ? (int) $options['site_id'] : null,
				'payload'      => json_encode( $payload, JSON_UNESCAPED_UNICODE ),
				'priority'     => (int) ( $options['priority'] ?? 5 ),
				'max_attempts' => (int) ( $options['max_attempts'] ?? 3 ),
				'unique_key'   => $unique,
				'available_at' => Database::now( (int) ( $options['delay'] ?? 0 ) ),
				'created_by'   => isset( $options['created_by'] ) ? (int) $options['created_by'] : null,
			)
		);
	}

	/**
	 * Processes jobs until the time budget is exhausted or the queue is empty.
	 *
	 * @param int    $budget_seconds Max seconds.
	 * @param string $queue          Queue name.
	 * @return array{processed:int,failed:int}
	 */
	public function work( int $budget_seconds = 45, string $queue = 'default' ): array {
		$start     = microtime( true );
		$processed = 0;
		$failed    = 0;

		$this->release_stale();
		self::$running = true;

		while ( ( microtime( true ) - $start ) < $budget_seconds ) {
			$job = $this->reserve( $queue );
			if ( null === $job ) {
				break;
			}

			try {
				$handler = Job_Handlers::resolve( (string) $job['type'] );
				$cursor  = $handler( $job, $this );

				if ( is_array( $cursor ) ) {
					$this->db->query(
						"UPDATE jobs SET status = 'pending', cursor_state = ?, progress = ?, reserved_by = NULL, reserved_at = NULL WHERE id = ?",
						array( json_encode( $cursor ), min( 99, (int) ( $cursor['progress'] ?? 0 ) ), (int) $job['id'] )
					);
				} else {
					$this->db->query(
						"UPDATE jobs SET status = 'done', progress = 100, finished_at = UTC_TIMESTAMP(), reserved_by = NULL WHERE id = ?",
						array( (int) $job['id'] )
					);
				}
				++$processed;
			} catch ( Throwable $e ) {
				++$failed;
				$this->fail( $job, $e );
			}
		}

		self::$running = false;
		return array(
			'processed' => $processed,
			'failed'    => $failed,
		);
	}

	/**
	 * Atomically reserves the next available job.
	 *
	 * @param string $queue Queue.
	 * @return array|null
	 */
	private function reserve( string $queue ): ?array {
		$affected = $this->db->query(
			"UPDATE jobs SET status = 'running', reserved_by = ?, reserved_at = UTC_TIMESTAMP(), attempts = attempts + 1
			 WHERE status = 'pending' AND queue = ? AND available_at <= UTC_TIMESTAMP()
			 ORDER BY priority ASC, id ASC LIMIT 1",
			array( $this->worker, $queue )
		)->rowCount();

		if ( 0 === $affected ) {
			return null;
		}

		$job = $this->db->get_row(
			"SELECT * FROM jobs WHERE reserved_by = ? AND status = 'running' ORDER BY reserved_at DESC LIMIT 1",
			array( $this->worker )
		);
		if ( null === $job ) {
			return null;
		}
		$job['payload']      = json_decode( (string) $job['payload'], true ) ?: array();
		$job['cursor_state'] = json_decode( (string) $job['cursor_state'], true ) ?: array();
		return $job;
	}

	/**
	 * Marks a job failed or schedules a retry with exponential backoff.
	 *
	 * @param array     $job Job row.
	 * @param Throwable $e   Error.
	 * @return void
	 */
	private function fail( array $job, Throwable $e ): void {
		$message = mb_substr( get_class( $e ) . ': ' . $e->getMessage(), 0, 1000 );
		Logger::error( 'Job failed', array( 'job_id' => (int) $job['id'], 'type' => $job['type'], 'exception' => $e ) );

		if ( (int) $job['attempts'] < (int) $job['max_attempts'] ) {
			$delay = (int) min( 3600, 60 * ( 2 ** ( (int) $job['attempts'] - 1 ) ) );
			$this->db->query(
				"UPDATE jobs SET status = 'pending', reserved_by = NULL, reserved_at = NULL, last_error = ?, available_at = UTC_TIMESTAMP() + INTERVAL ? SECOND WHERE id = ?",
				array( $message, $delay, (int) $job['id'] )
			);
			return;
		}

		$this->db->query(
			"UPDATE jobs SET status = 'failed', reserved_by = NULL, finished_at = UTC_TIMESTAMP(), last_error = ? WHERE id = ?",
			array( $message, (int) $job['id'] )
		);
	}

	/**
	 * Releases jobs stuck in "running" (worker died / hosting killed the process).
	 *
	 * @return void
	 */
	private function release_stale(): void {
		$this->db->query(
			"UPDATE jobs SET status = 'pending', reserved_by = NULL, reserved_at = NULL
			 WHERE status = 'running' AND reserved_at < UTC_TIMESTAMP() - INTERVAL 10 MINUTE"
		);
	}

	/**
	 * Queue statistics for the settings screen.
	 *
	 * @return array<string,int>
	 */
	public static function stats(): array {
		$rows  = Database::instance()->get_results( 'SELECT status, COUNT(*) AS total FROM jobs GROUP BY status' );
		$stats = array(
			'pending'   => 0,
			'running'   => 0,
			'done'      => 0,
			'failed'    => 0,
			'cancelled' => 0,
		);
		foreach ( $rows as $row ) {
			$stats[ (string) $row['status'] ] = (int) $row['total'];
		}
		return $stats;
	}
}
