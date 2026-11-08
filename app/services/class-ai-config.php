<?php
/**
 * OptiVio — Resolves the effective AI configuration (global settings + business overrides).
 *
 * This class only RESOLVES configuration; it never calls a provider. The OpenRouter client
 * (Stage 7) uses it and must only run on an explicit user action (RULES.md R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Repositories\Ai_Profiles_Repository;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * AI configuration resolver.
 */
final class Ai_Config {

	/**
	 * Minimum output tokens of the perception profile.
	 */
	public const PERCEPTION_MIN_TOKENS = 16000;

	/**
	 * Effective configuration of every profile for a business (or global when 0).
	 *
	 * @param int $business_id Business id (0 = global only).
	 * @return array<string,array{model:string,temperature:float,max_tokens:int,model_source:string,key_source:string}>
	 */
	public static function effective( int $business_id ): array {
		$overrides  = $business_id > 0 ? Ai_Profiles_Repository::for_business( $business_id ) : array();
		$client_key = $business_id > 0 ? Credentials_Repository::find_for_business( $business_id, 'openrouter' ) : null;
		$global_key = Credentials_Repository::find_global( 'openrouter' );
		$key_source = null !== $client_key ? 'business' : ( null !== $global_key ? 'global' : 'none' );
		$result     = array();

		foreach ( Ai_Profiles_Repository::PROFILES as $profile ) {
			$o                  = $overrides[ $profile ] ?? array();
			$model              = '' !== (string) ( $o['model'] ?? '' ) ? (string) $o['model'] : (string) Settings_Repository::get( 'ai.profile.' . $profile . '.model' );
			$result[ $profile ] = array(
				'model'        => $model,
				'temperature'  => null !== ( $o['temperature'] ?? null ) ? (float) $o['temperature'] : (float) Settings_Repository::get( 'ai.profile.' . $profile . '.temperature' ),
				'max_tokens'   => null !== ( $o['max_tokens'] ?? null ) ? (int) $o['max_tokens'] : (int) Settings_Repository::get( 'ai.profile.' . $profile . '.max_tokens' ),
				'model_source' => '' !== (string) ( $o['model'] ?? '' ) ? 'business' : 'global',
				'key_source'   => $key_source,
			);
		}
		// The perception answer describes up to 100 pages; thinking models (e.g. Gemini 2.5) also
		// spend output tokens on reasoning: below this limit the JSON gets cut.
		$result['perception']['max_tokens'] = max( self::PERCEPTION_MIN_TOKENS, (int) $result['perception']['max_tokens'] );
		return $result;
	}
}
