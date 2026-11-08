<?php
/**
 * OptiVio — Job handler registry and scheduler of recurring jobs.
 *
 * Every handler is deterministic. Handlers MUST NOT call AI providers (RULES.md R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;
use OptiVio\Repositories\Notifications_Repository;
use OptiVio\Repositories\Settings_Repository;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Maps job types to handlers.
 */
final class Job_Handlers {

	/**
	 * Returns the handler map.
	 *
	 * @return array<string,callable>
	 */
	public static function map(): array {
		return array(
			'maintenance.cleanup' => array( self::class, 'maintenance_cleanup' ),
			'connector.health'    => array( self::class, 'connector_health' ),
			'calls.match'         => array( self::class, 'calls_match' ),
			'google.sync'         => array( \OptiVio\Google\Google_Sync::class, 'handle' ),
			'engine.run'          => array( \OptiVio\Engine\Engine_Runner::class, 'handle_run' ),
			'competitors.scan'    => array( \OptiVio\Engine\Engine_Runner::class, 'handle_scan' ),
		);
	}

	/**
	 * Resolves a handler.
	 *
	 * @param string $type Job type.
	 * @return callable
	 * @throws RuntimeException When unknown.
	 */
	public static function resolve( string $type ): callable {
		$map = self::map();
		if ( ! isset( $map[ $type ] ) ) {
			throw new RuntimeException( 'Unknown job type: ' . $type );
		}
		return $map[ $type ];
	}

	/**
	 * Enqueues recurring jobs (called on every cron tick; deduplicated by unique key).
	 *
	 * @return void
	 */
	public static function schedule_recurring(): void {
		Job_Queue::dispatch(
			'maintenance.cleanup',
			array(),
			array(
				'unique'   => gmdate( 'Y-m-d-H' ),
				'priority' => 9,
			)
		);
		Job_Queue::dispatch(
			'connector.health',
			array(),
			array(
				'unique'   => gmdate( 'Y-m-d-H' ),
				'priority' => 6,
			)
		);
		Job_Queue::dispatch(
			'calls.match',
			array(),
			array(
				'unique'   => gmdate( 'Y-m-d-H' ) . '-' . (int) floor( (int) gmdate( 'i' ) / 15 ),
				'priority' => 5,
			)
		);
		\OptiVio\Google\Google_Sync::schedule();
		\OptiVio\Engine\Engine_Runner::schedule();
	}

	/**
	 * Marks connectors without a heartbeat for N hours as "error" and notifies (once per transition).
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return null
	 */
	public static function connector_health( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$hours = max( 1, (int) Settings_Repository::get( 'spam.connector_stale_hours', 48 ) );
		$db    = Database::instance();
		$stale = $db->get_results(
			"SELECT s.id, s.name, s.business_id FROM sites s
			 WHERE s.connector_status = 'connected' AND s.status = 'active'
				AND (s.connector_last_seen_at IS NULL OR s.connector_last_seen_at < UTC_TIMESTAMP() - INTERVAL ? HOUR)",
			array( $hours )
		);
		foreach ( $stale as $site ) {
			$db->update( 'sites', array( 'connector_status' => 'error' ), array( 'id' => (int) $site['id'] ) );
			Notifications_Repository::create(
				array(
					'business_id' => (int) $site['business_id'],
					'type'        => 'connector_stale',
					'severity'    => 'warning',
					'title'       => sprintf( 'Conectorul %s nu mai răspunde de %d ore', $site['name'], $hours ),
					'url'         => '/sites/' . (int) $site['id'],
				)
			);
		}
		return null;
	}

	/**
	 * Links recent unlinked calls to tel: clicks (clicks may arrive after the call was logged).
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return null
	 */
	public static function calls_match( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$ids = Database::instance()->get_col( 'SELECT DISTINCT business_id FROM calls WHERE phone_click_id IS NULL AND call_time > UTC_TIMESTAMP() - INTERVAL 3 DAY' );
		foreach ( $ids as $business_id ) {
			Call_Matcher::match_unlinked( (int) $business_id );
		}
		return null;
	}

	/**
	 * Housekeeping: purges expired rate limits, nonces, old login attempts, old jobs and audit logs.
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return null
	 */
	public static function maintenance_cleanup( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$db        = Database::instance();
		$retention = max( 30, (int) Settings_Repository::get( 'security.audit_retention_days', 365 ) );

		$db->query( 'DELETE FROM rate_limits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 1 DAY AND (blocked_until IS NULL OR blocked_until < UTC_TIMESTAMP())' );
		$db->query( 'DELETE FROM connector_nonces WHERE expires_at < UTC_TIMESTAMP()' );
		$db->query( 'DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 90 DAY' );
		$db->query( 'DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY' );
		$db->query( "DELETE FROM jobs WHERE status IN ('done','cancelled') AND finished_at < UTC_TIMESTAMP() - INTERVAL 14 DAY" );
		$db->query( 'DELETE FROM cron_runs WHERE started_at < UTC_TIMESTAMP() - INTERVAL 30 DAY' );
		$db->query( 'DELETE FROM audit_logs WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? DAY', array( $retention ) );

		return null;
	}
}
