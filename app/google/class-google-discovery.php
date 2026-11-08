<?php
/**
 * OptiVio — Lists the properties a connected Google account can access (for selection per site).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use OptiVio\Core\Database;
use OptiVio\Repositories\Settings_Repository;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Property discovery.
 */
final class Google_Discovery {

	/**
	 * Runs discovery for an account and stores the result in api_credentials.meta.properties.
	 *
	 * @param int $credential_id Credential.
	 * @return array{properties:array<string,array>,errors:array<string,string>}
	 */
	public static function run( int $credential_id ): array {
		$client     = new Google_Client( $credential_id );
		$properties = array();
		$errors     = array();
		$tasks      = array(
			'gsc'     => array( self::class, 'gsc' ),
			'ga4'     => array( self::class, 'ga4' ),
			'gtm'     => array( self::class, 'gtm' ),
			'ads'     => array( self::class, 'ads' ),
			'adsense' => array( self::class, 'adsense' ),
			'gbp'     => array( self::class, 'gbp' ),
		);
		foreach ( $tasks as $provider => $task ) {
			try {
				$properties[ $provider ] = $task( $client );
			} catch ( Throwable $e ) {
				$properties[ $provider ] = array();
				$errors[ $provider ]     = mb_substr( $e->getMessage(), 0, 300 );
			}
		}

		$db   = Database::instance();
		$meta = json_decode( (string) $db->get_var( 'SELECT meta FROM api_credentials WHERE id = ?', array( $credential_id ) ), true );
		$meta = is_array( $meta ) ? $meta : array();
		$meta = array_merge(
			$meta,
			array(
				'properties'    => $properties,
				'errors'        => $errors,
				'discovered_at' => gmdate( 'c' ),
			)
		);
		$db->update( 'api_credentials', array( 'meta' => json_encode( $meta, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $credential_id ) );

		return array(
			'properties' => $properties,
			'errors'     => $errors,
		);
	}

	/**
	 * Search Console properties.
	 *
	 * @param Google_Client $c Client.
	 * @return array<int,array{id:string,label:string}>
	 */
	private static function gsc( Google_Client $c ): array {
		$body = $c->request( 'GET', 'https://www.googleapis.com/webmasters/v3/sites' )['body'];
		$out  = array();
		foreach ( (array) ( $body['siteEntry'] ?? array() ) as $s ) {
			$out[] = array(
				'id'    => (string) $s['siteUrl'],
				'label' => (string) $s['siteUrl'] . ' (' . ( $s['permissionLevel'] ?? '' ) . ')',
			);
		}
		return $out;
	}

	/**
	 * GA4 properties.
	 *
	 * @param Google_Client $c Client.
	 * @return array
	 */
	private static function ga4( Google_Client $c ): array {
		$body = $c->request( 'GET', 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries', array( 'pageSize' => 200 ) )['body'];
		$out  = array();
		foreach ( (array) ( $body['accountSummaries'] ?? array() ) as $a ) {
			foreach ( (array) ( $a['propertySummaries'] ?? array() ) as $p ) {
				$out[] = array(
					'id'    => (string) preg_replace( '#^properties/#', '', (string) $p['property'] ),
					'label' => ( $a['displayName'] ?? '' ) . ' › ' . ( $p['displayName'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * GTM containers.
	 *
	 * @param Google_Client $c Client.
	 * @return array
	 */
	private static function gtm( Google_Client $c ): array {
		$accounts = $c->request( 'GET', 'https://tagmanager.googleapis.com/tagmanager/v2/accounts' )['body'];
		$out      = array();
		foreach ( array_slice( (array) ( $accounts['account'] ?? array() ), 0, 20 ) as $a ) {
			$containers = $c->request( 'GET', 'https://tagmanager.googleapis.com/tagmanager/v2/' . $a['path'] . '/containers' )['body'];
			foreach ( (array) ( $containers['container'] ?? array() ) as $ct ) {
				$out[] = array(
					'id'    => (string) $ct['publicId'],
					'label' => ( $a['name'] ?? '' ) . ' › ' . ( $ct['name'] ?? '' ),
					'path'  => (string) $ct['path'],
				);
			}
		}
		return $out;
	}

	/**
	 * Google Ads customers.
	 *
	 * @param Google_Client $c Client.
	 * @return array
	 */
	private static function ads( Google_Client $c ): array {
		$version = (string) Settings_Repository::get( 'integrations.ads_api_version', 'v21' );
		$body    = $c->request( 'GET', 'https://googleads.googleapis.com/' . $version . '/customers:listAccessibleCustomers', array(), null, true )['body'];
		$out     = array();
		foreach ( (array) ( $body['resourceNames'] ?? array() ) as $name ) {
			$id    = (string) preg_replace( '#^customers/#', '', (string) $name );
			$out[] = array(
				'id'    => $id,
				'label' => substr( $id, 0, 3 ) . '-' . substr( $id, 3, 3 ) . '-' . substr( $id, 6 ),
			);
		}
		return $out;
	}

	/**
	 * AdSense accounts.
	 *
	 * @param Google_Client $c Client.
	 * @return array
	 */
	private static function adsense( Google_Client $c ): array {
		$body = $c->request( 'GET', 'https://adsense.googleapis.com/v2/accounts' )['body'];
		$out  = array();
		foreach ( (array) ( $body['accounts'] ?? array() ) as $a ) {
			$out[] = array(
				'id'    => (string) preg_replace( '#^accounts/#', '', (string) $a['name'] ),
				'label' => (string) ( $a['displayName'] ?? $a['name'] ),
			);
		}
		return $out;
	}

	/**
	 * Business Profile locations.
	 *
	 * @param Google_Client $c Client.
	 * @return array
	 */
	private static function gbp( Google_Client $c ): array {
		$accounts = $c->request( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts' )['body'];
		$out      = array();
		foreach ( array_slice( (array) ( $accounts['accounts'] ?? array() ), 0, 20 ) as $a ) {
			$locations = $c->request(
				'GET',
				'https://mybusinessbusinessinformation.googleapis.com/v1/' . $a['name'] . '/locations',
				array(
					'readMask' => 'name,title,websiteUri',
					'pageSize' => 100,
				)
			)['body'];
			foreach ( (array) ( $locations['locations'] ?? array() ) as $l ) {
				$out[] = array(
					'id'    => (string) $l['name'],
					'label' => ( $l['title'] ?? '' ) . ( ! empty( $l['websiteUri'] ) ? ' — ' . $l['websiteUri'] : '' ),
				);
			}
		}
		return $out;
	}
}
