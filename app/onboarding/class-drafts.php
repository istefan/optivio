<?php
/**
 * OptiVio — Onboarding drafts: pages, selection, deterministic fetching in batches.
 *
 * Steps 1–2 of the wizard work on a draft (no business yet). Fetching runs in small batches,
 * either from the job queue (`onboarding.fetch`, cron) or from the wizard page (AJAX), with an
 * atomic claim per page so both can run at the same time. Zero AI (RULES.md R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Onboarding;

use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;
use OptiVio\Engine\Safe_Fetcher;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Services\Content_Ingestor;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Drafts store.
 */
final class Drafts {

	/**
	 * Pages fetched per batch.
	 */
	public const BATCH = 5;

	/**
	 * Limits from settings.
	 *
	 * @return array{site:int,run:int,chars:int}
	 */
	public static function limits(): array {
		return array(
			'site'  => max( 1, min( 50, (int) Settings_Repository::get( 'onboarding.max_pages_per_site', 50 ) ) ),
			'run'   => max( 1, min( 100, (int) Settings_Repository::get( 'onboarding.max_pages_per_run', 100 ) ) ),
			'chars' => max( 20000, (int) Settings_Repository::get( 'onboarding.max_input_chars', 250000 ) ),
		);
	}

	/**
	 * Finds a draft (decoded state).
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function find( int $id ): ?array {
		$row = Database::instance()->get_row( 'SELECT * FROM onboarding_drafts WHERE id = ?', array( $id ) );
		if ( null !== $row ) {
			$row['state'] = (array) ( json_decode( (string) $row['state'], true ) ?: array() );
		}
		return $row;
	}

	/**
	 * Open drafts (not converted / abandoned) and drafts of businesses still in onboarding.
	 *
	 * @return array
	 */
	public static function open_list(): array {
		return Database::instance()->get_results(
			"SELECT d.*, u.display_name AS author, b.name AS business_name,
				(SELECT COUNT(*) FROM onboarding_pages p WHERE p.draft_id = d.id AND p.selected = 1) AS selected_pages
			 FROM onboarding_drafts d
			 JOIN users u ON u.id = d.created_by
			 LEFT JOIN businesses b ON b.id = d.business_id
			 WHERE d.status <> 'abandoned'
				AND (d.business_id IS NULL OR JSON_EXTRACT(b.onboarding_state, '$.completed_at') IS NULL OR JSON_TYPE(JSON_EXTRACT(b.onboarding_state, '$.completed_at')) = 'NULL')
			 ORDER BY d.updated_at DESC LIMIT 50"
		);
	}

	/**
	 * Creates a draft.
	 *
	 * @param int    $user_id Creator.
	 * @param string $label   Label.
	 * @param array  $state   State.
	 * @param int    $business_id Business for a re-analysis (0 = new onboarding).
	 * @return int
	 */
	public static function create( int $user_id, string $label, array $state, int $business_id = 0 ): int {
		return Database::instance()->insert(
			'onboarding_drafts',
			array(
				'created_by'  => $user_id,
				'business_id' => $business_id > 0 ? $business_id : null,
				'label'       => mb_substr( $label, 0, 190 ),
				'state'       => json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);
	}

	/**
	 * Merges keys into the draft state.
	 *
	 * @param int   $id    Draft.
	 * @param array $patch Keys.
	 * @param array $extra Extra columns (status, business_id...).
	 * @return void
	 */
	public static function patch( int $id, array $patch, array $extra = array() ): void {
		$draft = self::find( $id );
		if ( null === $draft ) {
			return;
		}
		$state = array_merge( $draft['state'], $patch );
		Database::instance()->update( 'onboarding_drafts', array_merge( $extra, array( 'state' => json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ), array( 'id' => $id ) );
	}

	/**
	 * Inserts discovered candidates (ignores duplicates).
	 *
	 * @param int    $draft_id Draft.
	 * @param string $root     Site root.
	 * @param array  $pages    Pages from Page_Discovery (with selected flag).
	 * @return int Inserted.
	 */
	public static function add_pages( int $draft_id, string $root, array $pages ): int {
		$db = Database::instance();
		$n  = 0;
		foreach ( $pages as $page ) {
			$hash = hash( 'sha256', Content_Ingestor::normalize_url( (string) $page['url'] ) );
			$db->query(
				'INSERT IGNORE INTO onboarding_pages (draft_id, site_url, url, url_hash, page_type, title_hint, source, selected) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
				array( $draft_id, $root, mb_substr( (string) $page['url'], 0, 700 ), $hash, $page['type'], '' !== (string) $page['title'] ? mb_substr( (string) $page['title'], 0, 300 ) : null, $page['source'], ! empty( $page['selected'] ) ? 1 : 0 )
			);
			++$n;
		}
		return $n;
	}

	/**
	 * Pages of a draft.
	 *
	 * @param int  $draft_id      Draft.
	 * @param bool $selected_only Only selected.
	 * @return array
	 */
	public static function pages( int $draft_id, bool $selected_only = false ): array {
		$rows = Database::instance()->get_results(
			'SELECT * FROM onboarding_pages WHERE draft_id = ?' . ( $selected_only ? ' AND selected = 1' : '' ) . " ORDER BY site_url, FIELD(page_type, 'home','service','pricing','about','contact','other','blog','legal'), url",
			array( $draft_id )
		);
		foreach ( $rows as &$row ) {
			$row['extracted'] = null !== $row['extracted'] ? (array) json_decode( (string) $row['extracted'], true ) : array();
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Fetch progress of the selected pages.
	 *
	 * @param int $draft_id Draft.
	 * @return array{total:int,done:int,ok:int,error:int,pending:int,percent:int}
	 */
	public static function progress( int $draft_id ): array {
		$rows   = Database::instance()->get_results( 'SELECT fetch_status, COUNT(*) AS n FROM onboarding_pages WHERE draft_id = ? AND selected = 1 GROUP BY fetch_status', array( $draft_id ) );
		$counts = array_fill_keys( array( 'pending', 'fetching', 'ok', 'error', 'skipped' ), 0 );
		foreach ( $rows as $r ) {
			$counts[ $r['fetch_status'] ] = (int) $r['n'];
		}
		$total = array_sum( $counts );
		$done  = $counts['ok'] + $counts['error'] + $counts['skipped'];
		return array(
			'total'   => $total,
			'done'    => $done,
			'ok'      => $counts['ok'],
			'error'   => $counts['error'] + $counts['skipped'],
			'pending' => $counts['pending'] + $counts['fetching'],
			'percent' => $total > 0 ? (int) floor( 100 * $done / $total ) : 100,
		);
	}

	/**
	 * Queues the fetch of the selected pages that were not fetched yet.
	 *
	 * @param int $draft_id Draft.
	 * @param int $user_id  User.
	 * @return void
	 */
	public static function start_fetch( int $draft_id, int $user_id ): void {
		$db = Database::instance();
		$db->query( "UPDATE onboarding_pages SET fetch_status = 'pending', error = NULL WHERE draft_id = ? AND selected = 1 AND fetch_status IN ('pending','error','skipped','fetching')", array( $draft_id ) );
		$db->update( 'onboarding_drafts', array( 'status' => 'fetching' ), array( 'id' => $draft_id ) );
		Job_Queue::dispatch(
			'onboarding.fetch',
			array( 'draft_id' => $draft_id ),
			array(
				'unique'     => 'onboarding-' . $draft_id . '-' . gmdate( 'YmdHi' ),
				'priority'   => 2,
				'created_by' => $user_id,
			)
		);
	}

	/**
	 * Fetches the next batch of pending pages (atomic claim per page).
	 *
	 * @param int $draft_id Draft.
	 * @param int $limit    Pages.
	 * @return array Progress.
	 */
	public static function process( int $draft_id, int $limit = self::BATCH ): array {
		$db    = Database::instance();
		$draft = self::find( $draft_id );
		if ( null === $draft ) {
			return self::progress( $draft_id );
		}
		// Pages stuck in "fetching" for more than 2 minutes go back to the queue.
		$db->query( "UPDATE onboarding_pages SET fetch_status = 'pending' WHERE draft_id = ? AND fetch_status = 'fetching' AND fetched_at < UTC_TIMESTAMP() - INTERVAL 2 MINUTE", array( $draft_id ) );

		$robots   = (array) ( $draft['state']['robots'] ?? array() );
		$override = ! empty( $draft['robots_override'] );
		$ids      = $db->get_col( "SELECT id FROM onboarding_pages WHERE draft_id = ? AND selected = 1 AND fetch_status = 'pending' ORDER BY FIELD(page_type, 'home','service','pricing','about','contact','other','blog','legal'), id LIMIT " . (int) $limit, array( $draft_id ) );
		foreach ( $ids as $id ) {
			$claimed = $db->query( "UPDATE onboarding_pages SET fetch_status = 'fetching', fetched_at = UTC_TIMESTAMP() WHERE id = ? AND fetch_status = 'pending'", array( (int) $id ) )->rowCount();
			if ( 1 !== $claimed ) {
				continue;
			}
			$page = $db->get_row( 'SELECT * FROM onboarding_pages WHERE id = ?', array( (int) $id ) );
			$host = Page_Discovery::host_key( (string) $page['url'] );
			if ( ! $override && Page_Discovery::is_disallowed( (string) $page['url'], (array) ( $robots[ $host ] ?? array() ) ) ) {
				$db->update(
					'onboarding_pages',
					array(
						'fetch_status' => 'skipped',
						'error'        => 'Blocată de robots.txt (bifați „Ignoră robots.txt” dacă aveți acordul clientului).',
					),
					array( 'id' => (int) $id )
				);
				continue;
			}
			self::fetch_one( $page );
		}

		$progress = self::progress( $draft_id );
		if ( 0 === $progress['pending'] && 'fetching' === $draft['status'] ) {
			$db->update( 'onboarding_drafts', array( 'status' => 'fetched' ), array( 'id' => $draft_id ) );
		}
		return $progress;
	}

	/**
	 * Fetches and extracts one page.
	 *
	 * @param array $page Page row.
	 * @return void
	 */
	private static function fetch_one( array $page ): void {
		$res = Safe_Fetcher::get( (string) $page['url'], array( 'allow_local' => Page_Discovery::allow_local() ) );
		$row = array(
			'http_status' => $res['status'] > 0 ? $res['status'] : null,
			'fetched_at'  => Database::now(),
		);
		if ( ! $res['ok'] ) {
			$row['fetch_status'] = 'error';
			$row['error']        = mb_substr( self::friendly_error( $res ), 0, 300 );
		} else {
			$x                   = Page_Extractor::extract( $res['html'], (string) $res['url'] );
			$lines               = $x['lines'];
			unset( $x['lines'], $x['links'] );
			$row['fetch_status'] = 'ok';
			$row['extracted']    = json_encode( $x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
			$row['body_text']    = implode( "\n", $lines );
			$row['error']        = $x['thin'] ? 'Text foarte puțin: pagina pare randată doar cu JavaScript sau este goală.' : null;
		}
		Database::instance()->update( 'onboarding_pages', $row, array( 'id' => (int) $page['id'] ) );
	}

	/**
	 * Human readable fetch error.
	 *
	 * @param array $res Fetch result.
	 * @return string
	 */
	private static function friendly_error( array $res ): string {
		if ( in_array( (int) $res['status'], array( 401, 403, 429, 503 ), true ) ) {
			return 'HTTP ' . $res['status'] . ': acces refuzat (posibil Cloudflare / firewall). Cereți clientului o excepție pentru OptiVioBot sau excludeți pagina.';
		}
		if ( 404 === (int) $res['status'] ) {
			return 'HTTP 404: pagina nu există.';
		}
		return (string) $res['error'];
	}

	/**
	 * Job handler `onboarding.fetch` (deterministic, no AI).
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return array|null
	 */
	public static function handle_fetch( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$payload  = is_array( $job['payload'] ?? null ) ? $job['payload'] : (array) json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		$draft_id = (int) ( $payload['draft_id'] ?? 0 );
		if ( $draft_id <= 0 || null === self::find( $draft_id ) ) {
			return null;
		}
		$progress = self::process( $draft_id );
		return $progress['pending'] > 0 ? array( 'progress' => min( 99, $progress['percent'] ) ) : null;
	}

	/**
	 * Deletes abandoned drafts older than 30 days (and their pages).
	 *
	 * @return void
	 */
	public static function purge_stale(): void {
		Database::instance()->query( "DELETE FROM onboarding_drafts WHERE business_id IS NULL AND status <> 'converted' AND updated_at < UTC_TIMESTAMP() - INTERVAL 30 DAY" );
	}
}
