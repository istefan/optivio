<?php
/**
 * OptiVio — Strategy pages for the ACTIVE business: SEO audit, keywords & search insights,
 * Google Ads analysis & strategy, competitors. Everything deterministic (zero AI).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Engine\Ads_Strategy;
use OptiVio\Engine\Competitors;
use OptiVio\Engine\Engine_Runner;
use OptiVio\Engine\Insight_Store;
use OptiVio\Engine\Keyword_Research;
use OptiVio\Engine\Seo_Auditor;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Strategy controller.
 */
final class Strategy_Controller extends Controller {

	/**
	 * SEO audit page.
	 *
	 * @return void
	 */
	public function seo_audit(): void {
		$business = $this->active_business( '/seo-audit', 'Audit SEO' );
		$db       = Database::instance();
		$site_id  = absint( $this->request->query( 'site', 0 ) );
		$code     = sanitize_key( $this->request->query( 'code', '' ) );
		$sites    = $db->get_results( 'SELECT id, name FROM sites WHERE business_id = ? ORDER BY name', array( (int) $business['id'] ) );
		$site_ids = array_map( 'intval', array_column( $sites, 'id' ) );
		if ( $site_id > 0 && ! in_array( $site_id, $site_ids, true ) ) {
			$site_id = 0;
		}
		$scope  = $site_id > 0 ? array( $site_id ) : $site_ids;
		$issues = array();
		$counts = array();
		if ( ! empty( $scope ) ) {
			$in     = Database::in_placeholders( $scope );
			$counts = $db->get_results(
				"SELECT issue_code, severity, COUNT(*) total FROM seo_audit_issues WHERE site_id IN ({$in}) AND resolved_at IS NULL GROUP BY issue_code, severity
				 ORDER BY FIELD(severity, 'critical', 'warning', 'info'), total DESC",
				$scope
			);
			$params = $scope;
			$filter = '';
			if ( isset( Seo_Auditor::ISSUES[ $code ] ) ) {
				$filter   = ' AND i.issue_code = ?';
				$params[] = $code;
			}
			$issues = $db->get_results(
				"SELECT i.*, c.title, c.url, s.name AS site_name FROM seo_audit_issues i LEFT JOIN wp_content c ON c.id = i.content_id JOIN sites s ON s.id = i.site_id
				 WHERE i.site_id IN ({$in}) AND i.resolved_at IS NULL{$filter} ORDER BY FIELD(i.severity, 'critical', 'warning', 'info'), i.issue_code, c.title LIMIT 300",
				$params
			);
		}

		$this->render(
			'strategy/seo-audit',
			array(
				'page_title'    => 'Audit SEO',
				'page_subtitle' => (string) $business['name'] . ' · on-page, tehnic și tracking — calcul determinist',
				'business'      => $business,
				'sites'         => $sites,
				'site_id'       => $site_id,
				'code'          => $code,
				'counts'        => $counts,
				'issues'        => $issues,
				'resolved'      => empty( $scope ) ? 0 : (int) $db->get_var( 'SELECT COUNT(*) FROM seo_audit_issues WHERE site_id IN (' . Database::in_placeholders( $scope ) . ') AND resolved_at > UTC_TIMESTAMP() - INTERVAL 30 DAY', $scope ),
				'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
			)
		);
	}

	/**
	 * Keywords + GSC insights page.
	 *
	 * @return void
	 */
	public function keywords(): void {
		$business = $this->active_business( '/keywords', 'Cuvinte cheie' );
		$status   = sanitize_key( $this->request->query( 'status', 'active' ) );
		$intent   = sanitize_key( $this->request->query( 'intent', '' ) );
		$where    = array( 'business_id = ?' );
		$params   = array( (int) $business['id'] );
		if ( 'active' === $status ) {
			$where[] = "status <> 'excluded'";
		} elseif ( in_array( $status, array( 'proposed', 'tracked', 'excluded' ), true ) ) {
			$where[]  = 'status = ?';
			$params[] = $status;
		}
		if ( isset( Keyword_Research::INTENT_LABELS[ $intent ] ) ) {
			$where[]  = 'intent = ?';
			$params[] = $intent;
		}

		$this->render(
			'strategy/keywords',
			array(
				'page_title'    => 'Cuvinte cheie',
				'page_subtitle' => (string) $business['name'] . ' · cercetare din GSC, Ads, servicii × zonă și concurență',
				'business'      => $business,
				'rows'          => Database::instance()->get_results( 'SELECT * FROM keywords WHERE ' . implode( ' AND ', $where ) . ' ORDER BY priority_score DESC, gsc_impressions DESC LIMIT 300', $params ),
				'insights'      => Insight_Store::active( (int) $business['id'], array( 'cannibalization', 'striking_distance', 'low_ctr_high_position' ), 60 ),
				'status'        => $status,
				'intent'        => $intent,
				'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Adds a manual keyword.
	 *
	 * @return void
	 */
	public function keyword_add(): void {
		$business = $this->active_business( '/keywords', '', 'manage' );
		$keyword  = $this->text( 'keyword', 190 );
		$channel  = sanitize_key( $this->request->input( 'channel', 'both' ) );
		if ( ! in_array( $channel, array( 'seo', 'ads', 'both' ), true ) || ! Keyword_Research::add_manual( (int) $business['id'], $keyword, $channel ) ) {
			$this->redirect_with( '/keywords', 'error', 'Cuvânt cheie invalid.' );
		}
		Audit_Log::record( 'keyword.add', 'business', (int) $business['id'], array( 'keyword' => $keyword ), (int) $business['id'] );
		$this->redirect_with( '/keywords', 'success', 'Cuvântul cheie a fost adăugat și este urmărit.' );
	}

	/**
	 * Changes a keyword status (tracked / excluded / proposed).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function keyword_status(): void {
		$business = $this->active_business( '/keywords', '', 'manage' );
		$status   = sanitize_key( $this->request->input( 'status', '' ) );
		if ( ! in_array( $status, array( 'proposed', 'tracked', 'excluded' ), true ) ) {
			throw Http_Exception::not_found();
		}
		$updated = Database::instance()->update(
			'keywords',
			array( 'status' => $status ),
			array(
				'id'          => $this->request->param_int( 'id' ),
				'business_id' => (int) $business['id'],
			)
		);
		if ( 0 === $updated && null === Database::instance()->get_var( 'SELECT id FROM keywords WHERE id = ? AND business_id = ?', array( $this->request->param_int( 'id' ), (int) $business['id'] ) ) ) {
			throw Http_Exception::not_found();
		}
		$this->redirect_with( $this->back_path( '/keywords' ), 'success', 'Statusul a fost actualizat.' );
	}

	/**
	 * Dismisses an insight (stays dismissed across recalculations).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function insight_dismiss(): void {
		$business = $this->active_business( '/keywords', '', 'manage' );
		$done     = Database::instance()->query(
			'UPDATE keyword_insights SET dismissed_at = UTC_TIMESTAMP() WHERE id = ? AND business_id = ?',
			array( $this->request->param_int( 'id' ), (int) $business['id'] )
		)->rowCount();
		if ( 0 === $done ) {
			throw Http_Exception::not_found();
		}
		Audit_Log::record( 'insight.dismiss', 'keyword_insight', $this->request->param_int( 'id' ), array(), (int) $business['id'] );
		$this->redirect_with( $this->back_path( '/keywords' ), 'success', 'Recomandarea a fost ignorată.' );
	}

	/**
	 * Google Ads analysis & strategy.
	 *
	 * @return void
	 */
	public function ads(): void {
		$business = $this->active_business( '/ads', 'Google Ads' );
		$this->render(
			'strategy/ads',
			array(
				'page_title'    => 'Google Ads',
				'page_subtitle' => (string) $business['name'] . ' · strategie, cuvinte negative, program și corelare cu apelurile',
				'business'      => $business,
				'strategy'      => Ads_Strategy::recommend( (int) $business['id'] ),
				'insights'      => Insight_Store::active( (int) $business['id'], array( 'paid_while_top_organic', 'ads_converting_no_landing', 'ads_wasted_term', 'rejected_calls_keyword', 'ads_bad_hours' ), 100 ),
				'negatives'     => Database::instance()->get_results(
					'SELECT k.keyword_text, k.match_type, c.name AS campaign FROM ads_keywords k JOIN ads_campaigns c ON c.id = k.campaign_id JOIN sites s ON s.id = c.site_id
					 WHERE s.business_id = ? AND k.is_negative = 1 ORDER BY k.keyword_text LIMIT 200',
					array( (int) $business['id'] )
				),
				'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
			)
		);
	}

	/**
	 * Competitors page.
	 *
	 * @return void
	 */
	public function competitors(): void {
		$business = $this->active_business( '/competitors', 'Concurență' );
		$db       = Database::instance();
		$this->render(
			'strategy/competitors',
			array(
				'page_title'    => 'Concurență',
				'page_subtitle' => (string) $business['name'] . ' · descoperire SERP (Serper) sau manual, scanare structură și comparație',
				'business'      => $business,
				'competitors'   => $db->get_results(
					'SELECT c.*, COUNT(p.id) pages, SUM(p.last_fetched_at IS NOT NULL AND p.word_count IS NOT NULL) scanned, MIN(p.serp_position) best_position
					 FROM competitors c LEFT JOIN competitor_pages p ON p.competitor_id = c.id WHERE c.business_id = ? GROUP BY c.id ORDER BY c.is_active DESC, best_position IS NULL, best_position, c.domain',
					array( (int) $business['id'] )
				),
				'keywords'      => $db->get_col(
					'SELECT DISTINCT p.keyword FROM competitor_pages p JOIN competitors c ON c.id = p.competitor_id WHERE c.business_id = ? AND p.keyword IS NOT NULL ORDER BY p.keyword',
					array( (int) $business['id'] )
				),
				'tracked'       => $db->get_col( "SELECT keyword FROM keywords WHERE business_id = ? AND status <> 'excluded' ORDER BY priority_score DESC LIMIT 30", array( (int) $business['id'] ) ),
				'stale'         => count( Engine_Runner::stale_pages( (int) $business['id'], 500 ) ),
				'can_manage'    => Gate::can_access_business( (int) $business['id'], 'manage' ),
				'errors'        => $this->pull_errors(),
			)
		);
	}

	/**
	 * Adds a manual competitor URL.
	 *
	 * @return void
	 */
	public function competitor_add(): void {
		$business = $this->active_business( '/competitors', '', 'manage' );
		try {
			Competitors::add_manual( (int) $business['id'], $this->text( 'url', 500 ), $this->text( 'keyword', 190 ) );
		} catch ( Throwable $e ) {
			$this->redirect_with( '/competitors', 'error', $e->getMessage() );
		}
		Audit_Log::record( 'competitor.add', 'business', (int) $business['id'], array( 'url' => $this->text( 'url', 500 ) ), (int) $business['id'] );
		$this->redirect_with( '/competitors', 'success', 'Pagina concurentă a fost adăugată. Rulați „Scanează” pentru analiză.' );
	}

	/**
	 * SERP discovery for a keyword (Serper).
	 *
	 * @return void
	 */
	public function competitor_discover(): void {
		$business = $this->active_business( '/competitors', '', 'manage' );
		$keyword  = $this->text( 'keyword', 190 );
		if ( '' === $keyword ) {
			$this->redirect_with( '/competitors', 'error', 'Introduceți un cuvânt cheie.' );
		}
		try {
			$added = Competitors::discover( (int) $business['id'], $keyword );
		} catch ( Throwable $e ) {
			$this->redirect_with( '/competitors', 'error', $e->getMessage() );
		}
		Audit_Log::record( 'competitor.discover', 'business', (int) $business['id'], array( 'keyword' => $keyword ), (int) $business['id'] );
		$this->redirect_with( '/competitors', 'success', sprintf( '%d pagini concurente găsite pentru „%s”.', $added, $keyword ) );
	}

	/**
	 * Scans up to 8 stale competitor pages now (the rest continues via cron).
	 *
	 * @return void
	 */
	public function competitor_scan(): void {
		$business = $this->active_business( '/competitors', '', 'manage' );
		$ok       = 0;
		$errors   = array();
		foreach ( Engine_Runner::stale_pages( (int) $business['id'], 8 ) as $page_id ) {
			$result = Competitors::scan_page( $page_id );
			if ( $result['ok'] ) {
				++$ok;
			} else {
				$errors[] = $result['error'];
			}
		}
		$this->redirect_with( '/competitors', empty( $errors ) ? 'success' : 'warning', sprintf( '%d pagini scanate%s.', $ok, empty( $errors ) ? '' : '; ' . count( $errors ) . ' erori (ex. ' . $errors[0] . ')' ) );
	}

	/**
	 * Activates / deactivates a competitor.
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function competitor_toggle(): void {
		$business = $this->active_business( '/competitors', '', 'manage' );
		$done     = Database::instance()->query(
			'UPDATE competitors SET is_active = 1 - is_active WHERE id = ? AND business_id = ?',
			array( $this->request->param_int( 'id' ), (int) $business['id'] )
		)->rowCount();
		if ( 0 === $done ) {
			throw Http_Exception::not_found();
		}
		$this->redirect_with( '/competitors', 'success', 'Actualizat.' );
	}

	/**
	 * Comparison for a keyword against one client page.
	 *
	 * @return void
	 */
	public function compare(): void {
		$business   = $this->active_business( '/competitors', 'Concurență' );
		$keyword    = mb_substr( sanitize_text_field( $this->request->query( 'keyword', '' ) ), 0, 190 );
		$content_id = absint( $this->request->query( 'content_id', 0 ) );
		$contents   = Database::instance()->get_results(
			"SELECT c.id, c.title, s.name AS site_name FROM wp_content c JOIN sites s ON s.id = c.site_id
			 WHERE s.business_id = ? AND c.object_type = 'post' AND c.status = 'publish' ORDER BY s.name, c.title LIMIT 500",
			array( (int) $business['id'] )
		);

		$this->render(
			'strategy/compare',
			array(
				'page_title'    => 'Comparație concurență',
				'page_subtitle' => '' !== $keyword ? '„' . $keyword . '”' : 'Alegeți cuvântul cheie și pagina proprie',
				'business'      => $business,
				'keyword'       => $keyword,
				'content_id'    => $content_id,
				'contents'      => $contents,
				'keywords'      => Database::instance()->get_col( 'SELECT DISTINCT p.keyword FROM competitor_pages p JOIN competitors c ON c.id = p.competitor_id WHERE c.business_id = ? AND p.keyword IS NOT NULL ORDER BY p.keyword', array( (int) $business['id'] ) ),
				'result'        => '' !== $keyword ? Competitors::compare( (int) $business['id'], $keyword, $content_id ) : null,
			)
		);
	}

	/**
	 * Recalculates the deterministic engine for the active business now.
	 *
	 * @return void
	 */
	public function engine_run(): void {
		$business = $this->active_business( '/', '', 'manage' );
		$summary  = Engine_Runner::run( (int) $business['id'] );
		Audit_Log::record( 'engine.run', 'business', (int) $business['id'], $summary, (int) $business['id'] );
		$this->redirect_with(
			$this->back_path( '/seo-audit' ),
			'success',
			sprintf( 'Analiză recalculată: %d probleme SEO, %d cuvinte cheie, %d recomandări, %d Action Cards noi.', $summary['seo_issues'], $summary['keywords'], array_sum( $summary ) - $summary['seo_issues'] - $summary['keywords'] - $summary['cards'], $summary['cards'] )
		);
	}

	/**
	 * Resolves the active business (authorized) or renders the "choose a business" page.
	 *
	 * @param string $path       Current page path.
	 * @param string $title      Page title for the chooser.
	 * @param string $permission view|manage.
	 * @return array
	 */
	private function active_business( string $path, string $title, string $permission = 'view' ): array {
		$id = Gate::active_business_id();
		if ( $id > 0 ) {
			Gate::authorize_business( $id, $permission );
			return (array) Businesses_Repository::find( $id );
		}
		if ( $this->request->is_post() ) {
			$this->redirect_with( $path, 'warning', 'Selectați mai întâi afacerea activă.' );
		}
		$this->render(
			'strategy/choose',
			array(
				'page_title'    => '' !== $title ? $title : 'Strategie',
				'page_subtitle' => 'Selectați afacerea analizată',
				'return'        => $path,
			)
		);
		exit;
	}

	/**
	 * Safe back path from the "return" input.
	 *
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function back_path( string $fallback ): string {
		$return = (string) $this->request->input( 'return', '' );
		return preg_match( '#^/[a-z0-9/_\-]*(\?[a-z0-9=&_\-]*)?$#i', $return ) ? $return : $fallback;
	}
}
