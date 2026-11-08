<?php
/**
 * OptiVio — Per-business overrides of the 3 AI model profiles.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Repositories;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * AI profiles repository.
 */
final class Ai_Profiles_Repository {

	/**
	 * Profile keys.
	 */
	public const PROFILES = array( 'fast', 'reasoning', 'copy' );

	/**
	 * Overrides of a business keyed by profile.
	 *
	 * @param int $business_id Business id.
	 * @return array<string,array>
	 */
	public static function for_business( int $business_id ): array {
		$rows   = Database::instance()->get_results( 'SELECT * FROM business_ai_profiles WHERE business_id = ?', array( $business_id ) );
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['profile_key'] ] = $row;
		}
		return $result;
	}

	/**
	 * Saves an override, or removes it when the model is empty (inherit global).
	 *
	 * @param int        $business_id Business id.
	 * @param string     $profile     Profile key.
	 * @param string     $model       Model ('' = inherit).
	 * @param float|null $temperature Temperature (null = inherit).
	 * @param int|null   $max_tokens  Max tokens (null = inherit).
	 * @param int        $user_id     Acting user.
	 * @return void
	 */
	public static function save( int $business_id, string $profile, string $model, ?float $temperature, ?int $max_tokens, int $user_id ): void {
		$db = Database::instance();
		if ( '' === $model && null === $temperature && null === $max_tokens ) {
			$db->delete(
				'business_ai_profiles',
				array(
					'business_id' => $business_id,
					'profile_key' => $profile,
				)
			);
			return;
		}

		$credential = Credentials_Repository::find_for_business( $business_id, 'openrouter' );
		$db->query(
			'INSERT INTO business_ai_profiles (business_id, profile_key, model, temperature, max_tokens, credential_id, updated_by)
			 VALUES (?, ?, ?, ?, ?, ?, ?)
			 ON DUPLICATE KEY UPDATE model = VALUES(model), temperature = VALUES(temperature), max_tokens = VALUES(max_tokens),
				credential_id = VALUES(credential_id), updated_by = VALUES(updated_by)',
			array( $business_id, $profile, $model, null === $temperature ? null : (string) $temperature, $max_tokens, null !== $credential ? (int) $credential['id'] : null, $user_id )
		);
	}
}
