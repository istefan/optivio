<?php
/**
 * OptiVio — Cost estimates from the public OpenRouter model price list (cached 24 h).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Ai;

use OptiVio\Repositories\Settings_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Pricing.
 */
final class Ai_Pricing {

	/**
	 * Estimated USD cost (prompt ≈ chars / 3.5 tokens; completion = max_tokens, i.e. upper bound).
	 *
	 * @param string $model        Model id.
	 * @param int    $prompt_chars Prompt characters.
	 * @param int    $max_tokens   Max completion tokens.
	 * @return float|null Null when the price is unknown.
	 */
	public static function estimate( string $model, int $prompt_chars, int $max_tokens ): ?float {
		$prices = self::prices();
		if ( ! isset( $prices[ $model ] ) ) {
			return null;
		}
		return round( ( $prompt_chars / 3.5 ) * $prices[ $model ][0] + $max_tokens * $prices[ $model ][1], 6 );
	}

	/**
	 * Model => [prompt USD/token, completion USD/token].
	 *
	 * @return array<string,array{0:float,1:float}>
	 */
	public static function prices(): array {
		$cache = Settings_Repository::get( 'ai.pricing_cache', array() );
		if ( is_array( $cache ) && ( (int) ( $cache['at'] ?? 0 ) ) > time() - 86400 && ! empty( $cache['prices'] ) ) {
			return (array) $cache['prices'];
		}
		$prices = Ai_Client::is_mock() ? Ai_Mock::prices() : self::fetch();
		if ( ! empty( $prices ) ) {
			Settings_Repository::set(
				'ai.pricing_cache',
				array(
					'at'     => time(),
					'prices' => $prices,
				)
			);
		}
		return $prices;
	}

	/**
	 * Fetches the public model list.
	 *
	 * @return array
	 */
	private static function fetch(): array {
		$ch = curl_init( 'https://openrouter.ai/api/v1/models' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			)
		);
		$body = json_decode( (string) curl_exec( $ch ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$out  = array();
		foreach ( (array) ( $body['data'] ?? array() ) as $m ) {
			if ( isset( $m['id'], $m['pricing']['prompt'], $m['pricing']['completion'] ) ) {
				$out[ (string) $m['id'] ] = array( (float) $m['pricing']['prompt'], (float) $m['pricing']['completion'] );
			}
		}
		return $out;
	}
}
