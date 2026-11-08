<?php
/**
 * OptiVio — Encrypted API credentials (OpenRouter, Serper, PageSpeed, Google OAuth, RepuVio).
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
 * Credentials store. Secrets are decrypted only on demand, server side.
 */
final class Credentials_Repository {

	/**
	 * Supported providers and their labels.
	 */
	public const PROVIDERS = array(
		'openrouter'   => 'OpenRouter',
		'serper'       => 'Serper.dev (SERP API)',
		'pagespeed'    => 'Google PageSpeed Insights API',
		'google_oauth' => 'Google OAuth',
		'repuvio'      => 'RepuVio',
		'other'        => 'Altele',
	);

	/**
	 * Returns the active global credential row (without decrypting) for a provider.
	 *
	 * @param string $provider Provider.
	 * @return array|null
	 */
	public static function find_global( string $provider ): ?array {
		return Database::instance()->get_row(
			"SELECT id, provider, label, secret_hint, status, last_used_at, updated_at
			 FROM api_credentials WHERE scope = 'global' AND provider = ? AND status = 'active'
			 ORDER BY id DESC LIMIT 1",
			array( $provider )
		);
	}

	/**
	 * Saves (replaces) the global credential for a provider.
	 *
	 * @param string $provider Provider.
	 * @param string $secret   Plain secret.
	 * @param int    $user_id  Acting user.
	 * @return int Credential id.
	 */
	public static function save_global( string $provider, string $secret, int $user_id ): int {
		$db = Database::instance();
		return (int) $db->transaction(
			static function ( Database $db ) use ( $provider, $secret, $user_id ): int {
				$db->query( "DELETE FROM api_credentials WHERE scope = 'global' AND provider = ?", array( $provider ) );
				return $db->insert(
					'api_credentials',
					array(
						'scope'         => 'global',
						'provider'      => $provider,
						'label'         => self::PROVIDERS[ $provider ] ?? $provider,
						'secret_cipher' => Crypto::encrypt( $secret, self::context( 'global', $provider, null, null ) ),
						'secret_hint'   => Crypto::hint( $secret ),
						'status'        => 'active',
						'created_by'    => $user_id,
					)
				);
			}
		);
	}

	/**
	 * Deletes the global credential for a provider.
	 *
	 * @param string $provider Provider.
	 * @return int Deleted rows.
	 */
	public static function delete_global( string $provider ): int {
		return Database::instance()->query( "DELETE FROM api_credentials WHERE scope = 'global' AND provider = ?", array( $provider ) )->rowCount();
	}

	/**
	 * Decrypts a global secret for server-side use.
	 *
	 * @param string $provider Provider.
	 * @return string|null
	 */
	public static function reveal_global( string $provider ): ?string {
		$row = Database::instance()->get_row(
			"SELECT secret_cipher FROM api_credentials WHERE scope = 'global' AND provider = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
			array( $provider )
		);
		return null === $row ? null : Crypto::decrypt( (string) $row['secret_cipher'], self::context( 'global', $provider, null, null ) );
	}

	/**
	 * Returns the active credential of a business for a provider (without decrypting).
	 *
	 * @param int    $business_id Business id.
	 * @param string $provider    Provider.
	 * @return array|null
	 */
	public static function find_for_business( int $business_id, string $provider ): ?array {
		return Database::instance()->get_row(
			"SELECT id, provider, label, secret_hint, status, last_used_at, updated_at
			 FROM api_credentials WHERE scope = 'business' AND business_id = ? AND provider = ? AND status = 'active'
			 ORDER BY id DESC LIMIT 1",
			array( $business_id, $provider )
		);
	}

	/**
	 * Saves (replaces) a business-level credential.
	 *
	 * @param int    $business_id Business id.
	 * @param string $provider    Provider.
	 * @param string $secret      Plain secret.
	 * @param int    $user_id     Acting user.
	 * @return int Credential id.
	 */
	public static function save_for_business( int $business_id, string $provider, string $secret, int $user_id ): int {
		return (int) Database::instance()->transaction(
			static function ( Database $db ) use ( $business_id, $provider, $secret, $user_id ): int {
				$db->query( "DELETE FROM api_credentials WHERE scope = 'business' AND business_id = ? AND provider = ?", array( $business_id, $provider ) );
				$id = $db->insert(
					'api_credentials',
					array(
						'scope'         => 'business',
						'business_id'   => $business_id,
						'provider'      => $provider,
						'label'         => self::PROVIDERS[ $provider ] ?? $provider,
						'secret_cipher' => Crypto::encrypt( $secret, self::context( 'business', $provider, $business_id, null ) ),
						'secret_hint'   => Crypto::hint( $secret ),
						'status'        => 'active',
						'created_by'    => $user_id,
					)
				);
				if ( 'openrouter' === $provider ) {
					$db->query( 'UPDATE business_ai_profiles SET credential_id = ? WHERE business_id = ?', array( $id, $business_id ) );
				}
				return $id;
			}
		);
	}

	/**
	 * Deletes a business-level credential.
	 *
	 * @param int    $business_id Business id.
	 * @param string $provider    Provider.
	 * @return int
	 */
	public static function delete_for_business( int $business_id, string $provider ): int {
		return Database::instance()->query(
			"DELETE FROM api_credentials WHERE scope = 'business' AND business_id = ? AND provider = ?",
			array( $business_id, $provider )
		)->rowCount();
	}

	/**
	 * Decrypts a business-level secret for server-side use.
	 *
	 * @param int    $business_id Business id.
	 * @param string $provider    Provider.
	 * @return string|null
	 */
	public static function reveal_for_business( int $business_id, string $provider ): ?string {
		$row = Database::instance()->get_row(
			"SELECT secret_cipher FROM api_credentials WHERE scope = 'business' AND business_id = ? AND provider = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
			array( $business_id, $provider )
		);
		return null === $row ? null : Crypto::decrypt( (string) $row['secret_cipher'], self::context( 'business', $provider, $business_id, null ) );
	}

	/**
	 * Builds the AAD context for a credential.
	 *
	 * @param string   $scope       Scope.
	 * @param string   $provider    Provider.
	 * @param int|null $business_id Business id.
	 * @param int|null $site_id     Site id.
	 * @return string
	 */
	public static function context( string $scope, string $provider, ?int $business_id, ?int $site_id ): string {
		return sprintf( 'api_credentials:%s:%s:%d:%d', $scope, $provider, (int) $business_id, (int) $site_id );
	}
}
