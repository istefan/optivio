<?php
/**
 * OptiVio — Google accounts (OAuth), property discovery, manual sync and per-site Google data.
 *
 * Connecting / disconnecting Google accounts and binding OAuth integrations are super admin
 * actions: one agency Google account can read every client's properties.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Job_Queue;
use OptiVio\Core\Response;
use OptiVio\Core\Session;
use OptiVio\Google\Google_Client;
use OptiVio\Google\Google_Discovery;
use OptiVio\Google\Google_Http;
use OptiVio\Google\Google_Oauth;
use OptiVio\Google\Google_Sync;
use OptiVio\Repositories\Sites_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Google controller.
 */
final class Google_Controller extends Controller {

	/**
	 * Overview: accounts (super admin) + integration status of the sites in scope.
	 *
	 * @return void
	 */
	public function index(): void {
		$ids    = Gate::scope_business_ids();
		$where  = '1 = 1';
		$params = array();
		if ( is_array( $ids ) ) {
			$where  = empty( $ids ) ? '1 = 0' : 's.business_id IN (' . Database::in_placeholders( $ids ) . ')';
			$params = $ids;
		}
		$rows = Database::instance()->get_results(
			"SELECT si.*, s.name AS site_name, b.name AS business_name, c.account_email
			 FROM site_integrations si JOIN sites s ON s.id = si.site_id JOIN businesses b ON b.id = s.business_id
			 LEFT JOIN api_credentials c ON c.id = si.credential_id
			 WHERE si.status <> 'not_configured' AND " . $where . ' ORDER BY b.name, s.name, si.provider',
			$params
		);

		$this->render(
			'google/index',
			array(
				'page_title'    => 'Integrări Google',
				'page_subtitle' => 'Search Console, GA4, PageSpeed, Tag Manager, Ads, AdSense și Business Profile',
				'accounts'      => Gate::is_super_admin( $this->user() ) ? Google_Client::accounts() : array(),
				'configured'    => Google_Oauth::is_configured(),
				'mock'          => Google_Http::is_mock(),
				'rows'          => $rows,
				'providers'     => Sites_Repository::PROVIDERS,
				'redirect_uri'  => Google_Oauth::redirect_uri(),
			)
		);
	}

	/**
	 * Starts the OAuth flow (super admin).
	 *
	 * @return void
	 */
	public function connect(): void {
		if ( ! Google_Oauth::is_configured() ) {
			$this->redirect_with( '/settings?tab=integrations', 'error', 'Completați Client ID și Client Secret Google în Setări → Integrări.' );
		}
		$state = bin2hex( random_bytes( 24 ) );
		$pkce  = Google_Oauth::pkce();
		Session::set(
			'_google_oauth',
			array(
				'state'    => $state,
				'verifier' => $pkce['verifier'],
				'expires'  => time() + 600,
			)
		);

		if ( Google_Http::is_mock() ) {
			Response::redirect( ov_url( '/oauth/google/callback', array( 'code' => 'mock-code', 'state' => $state ) ) );
		}
		header( 'Location: ' . Google_Oauth::authorization_url( $state, $pkce['challenge'] ), true, 302 );
		exit;
	}

	/**
	 * OAuth callback (state + PKCE verified, single use).
	 *
	 * @return void
	 */
	public function callback(): void {
		$pending = Session::get( '_google_oauth' );
		Session::remove( '_google_oauth' );
		$state = (string) sanitize_text_field( $this->request->query( 'state', '' ) );
		$code  = (string) sanitize_text_field( $this->request->query( 'code', '' ) );
		$error = (string) sanitize_key( $this->request->query( 'error', '' ) );

		if ( ! is_array( $pending ) || $pending['expires'] < time() || '' === $state || ! hash_equals( (string) $pending['state'], $state ) ) {
			$this->redirect_with( '/google', 'error', 'Cererea de autorizare Google a expirat sau este invalidă. Reîncercați.' );
		}
		if ( '' !== $error || '' === $code ) {
			$this->redirect_with( '/google', 'error', 'Autorizarea Google a fost refuzată (' . ( $error ?: 'fără cod' ) . ').' );
		}

		$result = Google_Oauth::exchange( $code, (string) $pending['verifier'] );
		if ( ! $result['ok'] || '' === (string) ( $result['email'] ?? '' ) ) {
			$this->redirect_with( '/google', 'error', 'Conectarea contului Google a eșuat: ' . ( $result['error'] ?? 'email lipsă' ) );
		}

		$id = Google_Client::store_account( (string) $result['email'], (array) $result['tokens'], (int) $this->user()['id'] );
		Audit_Log::record( 'google.connect', 'api_credential', $id, array( 'email' => $result['email'] ) );
		try {
			Google_Discovery::run( $id );
		} catch ( Throwable $e ) {
			Session::flash( 'warning', 'Contul a fost conectat, dar listarea proprietăților a eșuat: ' . $e->getMessage() );
		}
		$this->redirect_with( '/google/accounts/' . $id, 'success', 'Contul Google ' . $result['email'] . ' a fost conectat.' );
	}

	/**
	 * Account detail with discovered properties.
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function account(): void {
		$row = $this->account_row();
		$this->render(
			'google/account',
			array(
				'page_title'    => (string) $row['account_email'],
				'page_subtitle' => 'Cont Google conectat · proprietăți accesibile',
				'account'       => $row,
				'meta'          => (array) ( json_decode( (string) $row['meta'], true ) ?: array() ),
				'providers'     => Sites_Repository::PROVIDERS,
			)
		);
	}

	/**
	 * Re-runs discovery.
	 *
	 * @return void
	 */
	public function discover(): void {
		$row = $this->account_row();
		try {
			$result = Google_Discovery::run( (int) $row['id'] );
			$this->redirect_with( '/google/accounts/' . (int) $row['id'], empty( $result['errors'] ) ? 'success' : 'warning', empty( $result['errors'] ) ? 'Proprietățile au fost actualizate.' : 'Actualizat parțial: unele produse au returnat erori.' );
		} catch ( Throwable $e ) {
			$this->redirect_with( '/google/accounts/' . (int) $row['id'], 'error', $e->getMessage() );
		}
	}

	/**
	 * Revokes and removes an account.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		$row = $this->account_row();
		Google_Client::disconnect( (int) $row['id'] );
		Audit_Log::record( 'google.disconnect', 'api_credential', (int) $row['id'], array( 'email' => $row['account_email'] ) );
		$this->redirect_with( '/google', 'success', 'Contul Google a fost deconectat și tokenul revocat.' );
	}

	/**
	 * Manual sync of one integration (manage): queues the job and processes the queue briefly.
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function sync_now(): void {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, 'manage' );
		$provider    = $this->request->param_key( 'provider' );
		if ( ! isset( Sites_Repository::PROVIDERS[ $provider ] ) ) {
			throw Http_Exception::not_found();
		}
		$job_id = Google_Sync::dispatch( $site_id, $provider, $business_id, 'manual-' . gmdate( 'YmdHi' ), (int) $this->user()['id'] );
		( new Job_Queue() )->work( 25 );

		$job = Database::instance()->get_row( 'SELECT status, last_error FROM jobs WHERE id = ?', array( $job_id ) );
		Audit_Log::record( 'google.sync_manual', 'site', $site_id, array( 'provider' => $provider ), $business_id );
		$status = (string) ( $job['status'] ?? 'pending' );
		$this->redirect_with(
			'/sites/' . $site_id,
			'done' === $status ? 'success' : ( 'failed' === $status || ! empty( $job['last_error'] ) ? 'error' : 'info' ),
			'done' === $status ? Sites_Repository::PROVIDERS[ $provider ][0] . ': sincronizare finalizată.' : ( ! empty( $job['last_error'] ) ? 'Eroare: ' . $job['last_error'] : 'Sincronizarea continuă în fundal (cron).' )
		);
	}

	/**
	 * Google data summary for a site.
	 *
	 * @return void
	 */
	public function site_data(): void {
		$site_id = $this->request->param_int( 'id' );
		Gate::authorize_site( $site_id, 'view' );
		$site = (array) Sites_Repository::find( $site_id );
		$db   = Database::instance();
		$from = gmdate( 'Y-m-d', time() - 28 * 86400 );

		$this->render(
			'google/site',
			array(
				'page_title'    => 'Date Google',
				'page_subtitle' => (string) $site['name'] . ' · ultimele 28 de zile',
				'site'          => $site,
				'gsc_totals'    => $db->get_row( 'SELECT SUM(clicks) clicks, SUM(impressions) impressions, SUM(position * impressions) / NULLIF(SUM(impressions), 0) position FROM gsc_daily WHERE site_id = ? AND stat_date >= ?', array( $site_id, $from ) ),
				'gsc_queries'   => $db->get_results( 'SELECT query, SUM(clicks) clicks, SUM(impressions) impressions, SUM(position * impressions) / NULLIF(SUM(impressions), 0) position, COUNT(DISTINCT page_hash) pages FROM gsc_daily WHERE site_id = ? AND stat_date >= ? GROUP BY query_hash, query ORDER BY clicks DESC, impressions DESC LIMIT 15', array( $site_id, $from ) ),
				'gsc_pages'     => $db->get_results( 'SELECT page, SUM(clicks) clicks, SUM(impressions) impressions FROM gsc_daily WHERE site_id = ? AND stat_date >= ? GROUP BY page_hash, page ORDER BY clicks DESC LIMIT 10', array( $site_id, $from ) ),
				'ga4_sources'   => $db->get_results( 'SELECT source, medium, SUM(sessions) sessions, SUM(engaged_sessions) engaged, SUM(conversions) conversions FROM ga4_daily WHERE site_id = ? AND stat_date >= ? GROUP BY source_medium_hash, source, medium ORDER BY sessions DESC LIMIT 10', array( $site_id, $from ) ),
				'psi'           => $db->get_row( 'SELECT * FROM psi_audits WHERE site_id = ? ORDER BY id DESC LIMIT 1', array( $site_id ) ),
				'gtm'           => $db->get_row( 'SELECT * FROM gtm_snapshots WHERE site_id = ? ORDER BY id DESC LIMIT 1', array( $site_id ) ),
				'ads_campaigns' => $db->get_results(
					'SELECT c.name, c.status, c.bidding_strategy, SUM(h.impressions) impressions, SUM(h.clicks) clicks, SUM(h.cost_micros) / 1000000 cost, SUM(h.conversions) conversions
					 FROM ads_campaigns c LEFT JOIN ads_hourly h ON h.campaign_id = c.id AND h.stat_date >= ? WHERE c.site_id = ? GROUP BY c.id ORDER BY cost DESC',
					array( $from, $site_id )
				),
				'ads_terms'     => $db->get_results(
					'SELECT t.search_term, SUM(t.clicks) clicks, SUM(t.cost_micros) / 1000000 cost, SUM(t.conversions) conversions FROM ads_search_terms t JOIN ads_campaigns c ON c.id = t.campaign_id
					 WHERE c.site_id = ? AND t.stat_date >= ? GROUP BY t.term_hash, t.search_term ORDER BY cost DESC LIMIT 10',
					array( $site_id, $from )
				),
				'ads_negatives' => (int) $db->get_var( 'SELECT COUNT(*) FROM ads_keywords k JOIN ads_campaigns c ON c.id = k.campaign_id WHERE c.site_id = ? AND k.is_negative = 1', array( $site_id ) ),
				'ads_keywords'  => (int) $db->get_var( 'SELECT COUNT(*) FROM ads_keywords k JOIN ads_campaigns c ON c.id = k.campaign_id WHERE c.site_id = ? AND k.is_negative = 0', array( $site_id ) ),
				'adsense'       => $db->get_row( 'SELECT SUM(page_views) views, SUM(clicks) clicks, SUM(earnings_micros) / 1000000 earnings FROM adsense_daily WHERE site_id = ? AND stat_date >= ?', array( $site_id, $from ) ),
				'gbp'           => $db->get_row( 'SELECT SUM(impressions_maps) maps, SUM(impressions_search) search, SUM(call_clicks) calls, SUM(website_clicks) website, SUM(direction_requests) directions FROM gbp_daily WHERE site_id = ? AND stat_date >= ?', array( $site_id, $from ) ),
			)
		);
	}

	/**
	 * Loads a Google account row (super admin routes).
	 *
	 * @return array
	 * @throws Http_Exception 404.
	 */
	private function account_row(): array {
		$row = Database::instance()->get_row(
			"SELECT id, account_email, status, meta, last_used_at, created_at FROM api_credentials WHERE id = ? AND provider = 'google_oauth'",
			array( $this->request->param_int( 'id' ) )
		);
		if ( null === $row ) {
			throw Http_Exception::not_found();
		}
		return $row;
	}
}
