<?php
/**
 * OptiVio — Websites: CRUD, connector credentials (HMAC) and per-site integrations.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Config;
use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Session;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Businesses_Repository;
use OptiVio\Repositories\Sites_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Security\Gate;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Sites controller.
 */
final class Sites_Controller extends Business_Scoped_Controller {

	/**
	 * Validation patterns for integration ids.
	 */
	private const ID_PATTERNS = array(
		'gsc'     => array( '#^(sc-domain:[a-z0-9.\-]+\.[a-z]{2,}|https?://[^\s]+/)$#i', 'Folosiți sc-domain:exemplu.ro sau un URL care se termină cu /.' ),
		'ga4'     => array( '/^[0-9]{6,12}$/', 'ID-ul proprietății GA4 este numeric (6–12 cifre).' ),
		'psi'     => array( '#^https?://[^\s]+$#i', 'URL invalid.' ),
		'gtm'     => array( '/^GTM-[A-Z0-9]{4,10}$/', 'Format GTM-XXXXXXX.' ),
		'ads'     => array( '/^[0-9]{10}$/', 'Customer ID are 10 cifre (ex. 123-456-7890).' ),
		'adsense' => array( '/^pub-[0-9]{10,20}$/', 'Format pub-1234567890123456.' ),
		'gbp'     => array( '#^(accounts/[0-9]+/)?locations/[0-9]+$#', 'Format locations/1234567890.' ),
	);

	/**
	 * All sites of the accessible businesses.
	 *
	 * @return void
	 */
	public function index_all(): void {
		$this->render(
			'sites/index',
			array(
				'page_title'    => 'Site-uri & Connector',
				'page_subtitle' => 'Toate site-urile clienților și starea pluginului OptiVio Connector',
				'rows'          => Sites_Repository::list_accessible( Gate::scope_business_ids() ),
			)
		);
	}

	/**
	 * Sites tab of a business.
	 *
	 * @return void
	 */
	public function business_index(): void {
		$business = $this->business( 'view' );
		$this->render_tab(
			'businesses/sites',
			$business,
			'sites',
			array( 'rows' => Sites_Repository::for_business( (int) $business['id'] ) )
		);
	}

	/**
	 * Adds a site to a business.
	 *
	 * @return void
	 */
	public function store(): void {
		$business = $this->business( 'manage' );
		$back     = $this->path( $business, '/sites' );
		[ $data, $v ] = $this->collect( (int) $business['id'], 0 );

		if ( $v->fails() ) {
			$this->back_with_errors( $back, $v->errors(), $data );
		}

		$id = Sites_Repository::create( (int) $business['id'], $data, (int) $this->user()['id'] );
		if ( 'wordpress' === $data['cms'] ) {
			$this->issue_secret( $id );
		}
		Sites_Repository::save_integration( $id, 'psi', $data['url'], 'active' );
		Audit_Log::record( 'site.create', 'site', $id, array( 'domain' => $data['domain'] ), (int) $business['id'] );
		$this->redirect_with( '/sites/' . $id, 'success', 'Site-ul a fost adăugat.' );
	}

	/**
	 * Site detail: connector + integrations.
	 *
	 * @return void
	 */
	public function show(): void {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, 'view' );
		$site        = Sites_Repository::find( $site_id );
		$business    = (array) Businesses_Repository::find( $business_id );

		$revealed = (array) Session::get( '_reveal_secret', array() );
		$secret   = $revealed[ $site_id ] ?? null;
		unset( $revealed[ $site_id ] );
		Session::set( '_reveal_secret', $revealed );

		$this->render(
			'sites/show',
			array(
				'page_title'    => (string) $site['name'],
				'page_subtitle' => (string) $business['name'],
				'site'          => $site,
				'business'      => $business,
				'integrations'  => Sites_Repository::integrations( $site_id ),
				'providers'     => Sites_Repository::PROVIDERS,
				'can_manage'    => Gate::can_access_business( $business_id, 'manage' ),
				'secret_once'   => $secret,
				'errors'        => $this->pull_errors(),
				'api_base'      => rtrim( (string) Config::get( 'app_url', '' ), '/' ) . '/api/connector/v1',
				'google'        => Gate::is_super_admin( $this->user() ) ? \OptiVio\Google\Google_Client::accounts() : array(),
				'is_admin'      => Gate::is_super_admin( $this->user() ),
			)
		);
	}

	/**
	 * Updates site fields.
	 *
	 * @return void
	 */
	public function update(): void {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, 'manage' );
		[ $data, $v ] = $this->collect( $business_id, $site_id );

		$data['status'] = sanitize_key( $this->request->input( 'status', 'active' ) );
		$v->in( 'status', $data['status'], array( 'active', 'paused', 'archived' ) );
		if ( $v->fails() ) {
			$this->back_with_errors( '/sites/' . $site_id, $v->errors(), $data );
		}

		Sites_Repository::update( $site_id, $data );
		Audit_Log::record( 'site.update', 'site', $site_id, array( 'status' => $data['status'] ), $business_id );
		$this->redirect_with( '/sites/' . $site_id, 'success', 'Site-ul a fost actualizat.' );
	}

	/**
	 * Generates / rotates the connector secret (shown once).
	 *
	 * @return void
	 */
	public function rotate_secret(): void {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, 'manage' );
		$this->issue_secret( $site_id );
		Audit_Log::record( 'site.connector_rotate', 'site', $site_id, array(), $business_id );
		$this->redirect_with( '/sites/' . $site_id, 'warning', 'A fost generat un secret nou. Pluginul instalat cu secretul vechi nu se mai poate autentifica până la actualizare.' );
	}

	/**
	 * Revokes the connector credentials.
	 *
	 * @return void
	 */
	public function revoke(): void {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, 'manage' );
		Sites_Repository::revoke_connector( $site_id );
		Audit_Log::record( 'site.connector_revoke', 'site', $site_id, array(), $business_id );
		$this->redirect_with( '/sites/' . $site_id, 'success', 'Accesul pluginului a fost revocat.' );
	}

	/**
	 * Saves one integration (external id) of a site.
	 *
	 * @return void
	 * @throws Http_Exception 404 for unknown providers.
	 */
	public function save_integration(): void {
		$site_id     = $this->request->param_int( 'id' );
		$business_id = Gate::authorize_site( $site_id, 'manage' );
		$provider    = $this->request->param_key( 'provider' );
		if ( ! isset( Sites_Repository::PROVIDERS[ $provider ] ) ) {
			throw Http_Exception::not_found();
		}
		// OAuth integrations read data with the agency Google account: super admin only.
		$is_admin = Gate::is_super_admin( $this->user() );
		if ( 'psi' !== $provider && ! $is_admin ) {
			throw Http_Exception::forbidden();
		}
		$credential_id = absint( $this->request->input( 'credential_id', 0 ) );
		if ( $credential_id > 0 && null === Database::instance()->get_var( "SELECT id FROM api_credentials WHERE id = ? AND provider = 'google_oauth' AND status = 'active'", array( $credential_id ) ) ) {
			$credential_id = 0;
		}

		$value = $this->normalize_external_id( $provider, $this->text( 'external_id', 190 ) );
		$v     = new Validator();
		if ( '' !== $value ) {
			$v->pattern( 'ext_' . $provider, $value, self::ID_PATTERNS[ $provider ][0], self::ID_PATTERNS[ $provider ][1] );
		}
		if ( $v->fails() ) {
			$this->back_with_errors( '/sites/' . $site_id, $v->errors(), array( 'ext_' . $provider => $value ) );
		}

		$paused = '1' === (string) $this->request->input( 'paused', '0' );
		$status = '' === $value ? 'not_configured' : ( $paused ? 'paused' : 'active' );
		Sites_Repository::save_integration( $site_id, $provider, '' === $value ? null : $value, $status );
		if ( 'psi' !== $provider ) {
			Database::instance()->query(
				'UPDATE site_integrations SET credential_id = ?, settings = NULL WHERE site_id = ? AND provider = ?',
				array( $credential_id > 0 ? $credential_id : null, $site_id, $provider )
			);
		}
		Audit_Log::record(
			'site.integration_update',
			'site',
			$site_id,
			array(
				'provider' => $provider,
				'status'   => $status,
			),
			$business_id
		);
		$this->redirect_with( '/sites/' . $site_id, 'success', Sites_Repository::PROVIDERS[ $provider ][0] . ': configurare salvată.' );
	}

	/**
	 * Issues connector credentials and keeps the secret in the session for one display.
	 *
	 * @param int $site_id Site id.
	 * @return void
	 */
	private function issue_secret( int $site_id ): void {
		$credentials             = Sites_Repository::rotate_connector_secret( $site_id );
		$revealed                = (array) Session::get( '_reveal_secret', array() );
		$revealed[ $site_id ]    = $credentials['secret'];
		Session::set( '_reveal_secret', $revealed );
	}

	/**
	 * Normalizes user-entered integration ids.
	 *
	 * @param string $provider Provider.
	 * @param string $value    Raw value.
	 * @return string
	 */
	private function normalize_external_id( string $provider, string $value ): string {
		$value = trim( $value );
		switch ( $provider ) {
			case 'ga4':
				return (string) preg_replace( '#^properties/#', '', $value );
			case 'ads':
				return (string) preg_replace( '/[^0-9]/', '', $value );
			case 'gtm':
				return strtoupper( $value );
			case 'adsense':
				return (string) preg_replace( '/^ca-/', '', strtolower( $value ) );
			default:
				return $value;
		}
	}

	/**
	 * Collects and validates site fields.
	 *
	 * @param int $business_id Business id.
	 * @param int $site_id     Site id (0 when creating).
	 * @return array{0:array,1:Validator}
	 */
	private function collect( int $business_id, int $site_id ): array {
		$url  = trim( $this->text( 'url', 255 ) );
		$url  = '' !== $url && ! preg_match( '#^https?://#i', $url ) ? 'https://' . $url : $url;
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$data = array(
			'name'   => $this->text( 'name', 160 ),
			'url'    => $url,
			'domain' => (string) preg_replace( '/^www\./', '', $host ),
			'cms'    => sanitize_key( $this->request->input( 'cms', 'wordpress' ) ),
		);
		if ( '' === $data['name'] ) {
			$data['name'] = $data['domain'];
		}

		$v = new Validator();
		$v->required( 'url', $url )->in( 'cms', $data['cms'], array( 'wordpress', 'other' ) );
		$host_ok = (bool) preg_match( '/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host ) || ( Config::is_dev() && false !== filter_var( $host, FILTER_VALIDATE_IP ) );
		if ( '' !== $url && ( false === filter_var( $url, FILTER_VALIDATE_URL ) || ! $host_ok ) ) {
			$v->add( 'url', 'URL invalid (ex. https://www.exemplu.ro).' );
		} elseif ( '' !== $data['domain'] && Sites_Repository::domain_exists( $business_id, $data['domain'], $site_id ) ) {
			$v->add( 'url', 'Acest domeniu este deja adăugat pentru afacere.' );
		}
		return array( $data, $v );
	}
}
