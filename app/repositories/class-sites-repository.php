<?php
/**
 * OptiVio — Websites of a business, connector credentials and per-site integrations.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Repositories;

use OptiVio\Core\Crypto;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Sites repository.
 */
final class Sites_Repository {

	/**
	 * Integration providers: key => [label, external id label, help].
	 */
	public const PROVIDERS = array(
		'gsc'     => array( 'Google Search Console', 'Proprietate', 'sc-domain:exemplu.ro sau https://www.exemplu.ro/' ),
		'ga4'     => array( 'Google Analytics 4', 'ID proprietate', 'Numeric, ex. 312345678' ),
		'psi'     => array( 'PageSpeed Insights', 'URL monitorizat', 'Implicit pagina principală a site-ului' ),
		'gtm'     => array( 'Google Tag Manager', 'ID container', 'GTM-XXXXXXX' ),
		'ads'     => array( 'Google Ads', 'Customer ID', '123-456-7890' ),
		'adsense' => array( 'Google AdSense', 'Publisher ID', 'pub-1234567890123456' ),
		'gbp'     => array( 'Google Business Profile', 'Locație', 'locations/1234567890' ),
	);

	/**
	 * Columns safe to expose (never the encrypted secret).
	 */
	private const COLUMNS = 's.id, s.business_id, s.name, s.url, s.domain, s.cms, s.status, s.connector_key_id, s.connector_status,
		s.connector_last_seen_at, s.connector_remote_url, s.plugin_version, s.wp_version, s.has_yoast, s.has_gravity_forms, s.content_synced_at, s.created_at, s.updated_at';

	/**
	 * Finds a site (authorization must be checked by the caller via Gate::authorize_site()).
	 *
	 * @param int $id Site id.
	 * @return array|null
	 */
	public static function find( int $id ): ?array {
		return Database::instance()->get_row(
			'SELECT ' . self::COLUMNS . ', b.name AS business_name FROM sites s JOIN businesses b ON b.id = s.business_id WHERE s.id = ?',
			array( $id )
		);
	}

	/**
	 * Sites of one business.
	 *
	 * @param int $business_id Business id.
	 * @return array<int,array>
	 */
	public static function for_business( int $business_id ): array {
		return Database::instance()->get_results(
			'SELECT ' . self::COLUMNS . ',
				(SELECT COUNT(*) FROM site_integrations i WHERE i.site_id = s.id AND i.status = \'active\') AS integrations_active
			 FROM sites s WHERE s.business_id = ? ORDER BY s.status ASC, s.name ASC',
			array( $business_id )
		);
	}

	/**
	 * Sites of all accessible businesses.
	 *
	 * @param array<int>|null $ids Accessible business ids (null = all).
	 * @return array<int,array>
	 */
	public static function list_accessible( ?array $ids ): array {
		if ( is_array( $ids ) && empty( $ids ) ) {
			return array();
		}
		$where  = '1 = 1';
		$params = array();
		if ( is_array( $ids ) ) {
			$where  = 's.business_id IN (' . Database::in_placeholders( $ids ) . ')';
			$params = array_values( $ids );
		}
		return Database::instance()->get_results(
			'SELECT ' . self::COLUMNS . ', b.name AS business_name,
				(SELECT COUNT(*) FROM site_integrations i WHERE i.site_id = s.id AND i.status = \'active\') AS integrations_active
			 FROM sites s JOIN businesses b ON b.id = s.business_id WHERE ' . $where . ' ORDER BY b.name ASC, s.name ASC',
			$params
		);
	}

	/**
	 * Whether the business already has a site on this domain.
	 *
	 * @param int    $business_id Business id.
	 * @param string $domain      Domain.
	 * @param int    $exclude_id  Site to exclude.
	 * @return bool
	 */
	public static function domain_exists( int $business_id, string $domain, int $exclude_id = 0 ): bool {
		return null !== Database::instance()->get_var(
			'SELECT id FROM sites WHERE business_id = ? AND domain = ? AND id <> ?',
			array( $business_id, $domain, $exclude_id )
		);
	}

	/**
	 * Creates a site.
	 *
	 * @param int   $business_id Business id.
	 * @param array $data        name, url, domain, cms.
	 * @param int   $user_id     Creator.
	 * @return int
	 */
	public static function create( int $business_id, array $data, int $user_id ): int {
		return Database::instance()->insert(
			'sites',
			array(
				'business_id' => $business_id,
				'name'        => $data['name'],
				'url'         => $data['url'],
				'domain'      => $data['domain'],
				'cms'         => $data['cms'],
				'created_by'  => $user_id,
			)
		);
	}

	/**
	 * Updates editable site fields.
	 *
	 * @param int   $id   Site id.
	 * @param array $data name, url, domain, cms, status.
	 * @return void
	 */
	public static function update( int $id, array $data ): void {
		$row = array_intersect_key( $data, array_flip( array( 'name', 'url', 'domain', 'cms', 'status' ) ) );
		if ( ! empty( $row ) ) {
			Database::instance()->update( 'sites', $row, array( 'id' => $id ) );
		}
	}

	/**
	 * Generates (or rotates) the connector credentials. Returns the plain secret ONCE.
	 *
	 * @param int $id Site id.
	 * @return array{key_id:string,secret:string}
	 */
	public static function rotate_connector_secret( int $id ): array {
		$key_id = 'ovk_' . bin2hex( random_bytes( 10 ) );
		$secret = Crypto::random_token( 32 );

		Database::instance()->transaction(
			static function ( Database $db ) use ( $id, $key_id, $secret ): void {
				$db->update(
					'sites',
					array(
						'connector_key_id'        => $key_id,
						'connector_secret_cipher' => Crypto::encrypt( $secret, self::secret_context( $id ) ),
						'connector_status'        => 'pending',
					),
					array( 'id' => $id )
				);
				$db->delete( 'connector_nonces', array( 'site_id' => $id ) );
			}
		);

		return array(
			'key_id' => $key_id,
			'secret' => $secret,
		);
	}

	/**
	 * Revokes the connector credentials (the plugin stops being able to authenticate).
	 *
	 * @param int $id Site id.
	 * @return void
	 */
	public static function revoke_connector( int $id ): void {
		Database::instance()->update(
			'sites',
			array(
				'connector_key_id'        => null,
				'connector_secret_cipher' => null,
				'connector_status'        => 'revoked',
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Finds an active site by its public connector key id, including the decrypted secret.
	 * Server-side use only (HMAC verification / plugin packaging).
	 *
	 * @param string $key_id Public key id.
	 * @return array|null Row with an extra "secret" key.
	 */
	public static function find_by_connector_key( string $key_id ): ?array {
		if ( ! preg_match( '/^ovk_[a-f0-9]{20}$/', $key_id ) ) {
			return null;
		}
		$row = Database::instance()->get_row(
			"SELECT id, business_id, status, connector_key_id, connector_secret_cipher, connector_status FROM sites
			 WHERE connector_key_id = ? AND status <> 'archived' AND connector_status <> 'revoked'",
			array( $key_id )
		);
		if ( null === $row || null === $row['connector_secret_cipher'] ) {
			return null;
		}
		$secret = Crypto::decrypt( (string) $row['connector_secret_cipher'], self::secret_context( (int) $row['id'] ) );
		if ( null === $secret ) {
			return null;
		}
		unset( $row['connector_secret_cipher'] );
		$row['secret'] = $secret;
		return $row;
	}

	/**
	 * Integrations of a site keyed by provider (missing providers are filled with defaults).
	 *
	 * @param int $site_id Site id.
	 * @return array<string,array>
	 */
	public static function integrations( int $site_id ): array {
		$rows   = Database::instance()->get_results( 'SELECT * FROM site_integrations WHERE site_id = ?', array( $site_id ) );
		$result = array();
		foreach ( array_keys( self::PROVIDERS ) as $provider ) {
			$result[ $provider ] = array(
				'provider'      => $provider,
				'credential_id' => null,
				'external_id'   => null,
				'status'       => 'not_configured',
				'last_sync_at' => null,
				'last_error'   => null,
			);
		}
		foreach ( $rows as $row ) {
			$result[ (string) $row['provider'] ] = $row;
		}
		return $result;
	}

	/**
	 * Saves (upserts) or clears a site integration.
	 *
	 * @param int         $site_id     Site id.
	 * @param string      $provider    Provider.
	 * @param string|null $external_id External id (null clears it).
	 * @param string      $status      not_configured|active|paused.
	 * @return void
	 */
	public static function save_integration( int $site_id, string $provider, ?string $external_id, string $status ): void {
		Database::instance()->query(
			'INSERT INTO site_integrations (site_id, provider, external_id, status) VALUES (?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE external_id = VALUES(external_id), status = VALUES(status), last_error = NULL',
			array( $site_id, $provider, $external_id, $status )
		);
	}

	/**
	 * AAD context of a site's connector secret.
	 *
	 * @param int $site_id Site id.
	 * @return string
	 */
	public static function secret_context( int $site_id ): string {
		return 'sites:connector_secret:' . $site_id;
	}
}
