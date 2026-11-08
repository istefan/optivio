<?php
/**
 * OptiVio — Runs the full deterministic engine for a business and schedules it daily.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Engine runner.
 */
final class Engine_Runner {

	/**
	 * Runs audit + insights + keyword research for a business.
	 *
	 * @param int $business_id Business.
	 * @return array<string,int> Summary counts.
	 */
	public static function run( int $business_id ): array {
		$db      = Database::instance();
		$start   = gmdate( 'Y-m-d H:i:s' );
		$summary = array( 'seo_issues' => 0 );

		foreach ( $db->get_col( "SELECT id FROM sites WHERE business_id = ? AND status = 'active'", array( $business_id ) ) as $site_id ) {
			$summary['seo_issues'] += Seo_Auditor::run( (int) $site_id );
		}
		$summary  = array_merge( $summary, Search_Insights::gsc( $business_id ), Search_Insights::ads( $business_id ) );
		Insight_Store::prune( $business_id, array_keys( Insight_Store::LABELS ), $start );
		$summary['keywords'] = Keyword_Research::run( $business_id );
		$summary['cards']    = \OptiVio\Actions\Card_Generator::run( $business_id );
		return $summary;
	}

	/**
	 * Daily scheduling (cron) — one engine run and one competitor scan per active business.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		foreach ( Database::instance()->get_col( "SELECT id FROM businesses WHERE status = 'active'" ) as $id ) {
			Job_Queue::dispatch(
				'engine.run',
				array( 'business_id' => (int) $id ),
				array(
					'unique'      => 'engine:' . $id . ':' . gmdate( 'Y-m-d' ),
					'business_id' => (int) $id,
					'priority'    => 8,
					'delay'       => 1800,
				)
			);
			Job_Queue::dispatch(
				'competitors.scan',
				array( 'business_id' => (int) $id ),
				array(
					'unique'      => 'competitors:' . $id . ':' . gmdate( 'o-W' ),
					'business_id' => (int) $id,
					'priority'    => 9,
				)
			);
		}
	}

	/**
	 * Job: engine.run.
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return null
	 */
	public static function handle_run( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		self::run( (int) $job['payload']['business_id'] );
		return null;
	}

	/**
	 * Job: competitors.scan — scans up to 5 stale pages per run (polite crawling), resumable.
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return array|null
	 */
	public static function handle_scan( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$ids = self::stale_pages( (int) $job['payload']['business_id'], 5 );
		foreach ( $ids as $id ) {
			Competitors::scan_page( (int) $id );
		}
		return count( $ids ) < 5 ? null : array( 'progress' => 50 );
	}

	/**
	 * Competitor pages not scanned in the last 14 days.
	 *
	 * @param int $business_id Business.
	 * @param int $limit       Limit.
	 * @return array<int,int>
	 */
	public static function stale_pages( int $business_id, int $limit ): array {
		return array_map(
			'intval',
			Database::instance()->get_col(
				'SELECT cp.id FROM competitor_pages cp JOIN competitors c ON c.id = cp.competitor_id
				 WHERE c.business_id = ? AND c.is_active = 1 AND (cp.last_fetched_at IS NULL OR cp.last_fetched_at < UTC_TIMESTAMP() - INTERVAL 14 DAY)
				 ORDER BY cp.last_fetched_at IS NULL DESC, cp.id LIMIT ?',
				array( $business_id, $limit )
			)
		);
	}
}
