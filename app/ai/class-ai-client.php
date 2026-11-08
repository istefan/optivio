<?php
/**
 * OptiVio — OpenRouter client. AI ON-DEMAND ONLY (RULES.md R5).
 *
 * Every call must originate from an explicit action of an authenticated user in an HTTP
 * POST request. Calls are refused in CLI, cron, job handlers and machine endpoints, logged
 * in ai_requests (tokens, cost, model, user) and checked against the monthly budget when
 * the global key is used.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Ai;

use OptiVio\Core\App;
use OptiVio\Core\Config;
use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;
use OptiVio\Core\Request;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Auth;
use OptiVio\Services\Ai_Config;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * OpenRouter client.
 */
final class Ai_Client {

	private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Whether the mock is active (development only).
	 *
	 * @return bool
	 */
	public static function is_mock(): bool {
		return Config::is_dev() && true === Config::get( 'ai_mock', false );
	}

	/**
	 * Throws unless the current context is an interactive, authenticated user POST.
	 *
	 * @param int $user_id User expected to be the actor.
	 * @return void
	 * @throws RuntimeException When the call is not user-initiated.
	 */
	public static function assert_on_demand( int $user_id ): void {
		$request = Request::current();
		if ( 'cli' === PHP_SAPI || Job_Queue::$running || App::is_machine_path( $request->path() ) || ! $request->is_post() || Auth::id() !== $user_id || 0 === $user_id ) {
			throw new RuntimeException( 'Apelurile AI sunt permise doar la cererea explicită a unui utilizator autentificat.' );
		}
	}

	/**
	 * Monthly spend (USD) with the global key.
	 *
	 * @return float
	 */
	public static function month_spend(): float {
		return (float) Database::instance()->get_var(
			"SELECT COALESCE(SUM(cost_usd), 0) FROM ai_requests WHERE credential_scope = 'global' AND status = 'ok' AND created_at >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')"
		);
	}

	/**
	 * Pre-flight info for the confirmation screen.
	 *
	 * @param int    $business_id   Business.
	 * @param string $profile       fast|reasoning|copy.
	 * @param int    $prompt_chars  Prompt size in characters.
	 * @return array{model:string,key_source:string,estimate:?float,budget:float,spent:float,blocked:bool,reason:string}
	 */
	public static function preflight( int $business_id, string $profile, int $prompt_chars ): array {
		$config   = Ai_Config::effective( $business_id )[ $profile ];
		$estimate = Ai_Pricing::estimate( $config['model'], $prompt_chars, (int) $config['max_tokens'] );
		$budget   = (float) Settings_Repository::get( 'ai.monthly_budget_usd', 50 );
		$spent    = self::month_spend();
		$blocked  = false;
		$reason   = '';
		if ( 'none' === $config['key_source'] && ! self::is_mock() ) {
			$blocked = true;
			$reason  = 'Nu este configurată nicio cheie OpenRouter (globală sau a clientului).';
		} elseif ( 'global' === $config['key_source'] && $budget > 0 && $spent + (float) $estimate > $budget ) {
			$blocked = true;
			$reason  = sprintf( 'Bugetul lunar AI (%s USD) ar fi depășit: cheltuit %s USD.', number_format( $budget, 2 ), number_format( $spent, 2 ) );
		}
		return array(
			'model'      => $config['model'],
			'key_source' => $config['key_source'],
			'estimate'   => $estimate,
			'budget'     => $budget,
			'spent'      => $spent,
			'blocked'    => $blocked,
			'reason'     => $reason,
		);
	}

	/**
	 * Calls a model profile and returns the decoded JSON object answer.
	 *
	 * @param int    $user_id     Acting user (must be the authenticated user of this POST).
	 * @param int    $business_id Business (0 = none).
	 * @param string $profile     fast|reasoning|copy.
	 * @param string $purpose     Purpose label (logged).
	 * @param string $system      System prompt.
	 * @param string $user        User prompt.
	 * @param array  $context     analysis_id, action_card_id.
	 * @return array{request_id:int,data:array,raw:string}
	 * @throws RuntimeException On guard, budget, transport or format errors.
	 */
	public static function complete_json( int $user_id, int $business_id, string $profile, string $purpose, string $system, string $user, array $context = array() ): array {
		self::assert_on_demand( $user_id );
		$pre = self::preflight( $business_id, $profile, mb_strlen( $system . $user ) );
		if ( $pre['blocked'] ) {
			throw new RuntimeException( $pre['reason'] );
		}
		$config = Ai_Config::effective( $business_id )[ $profile ];
		$key    = 'business' === $config['key_source'] ? Credentials_Repository::reveal_for_business( $business_id, 'openrouter' ) : Credentials_Repository::reveal_global( 'openrouter' );

		$db  = Database::instance();
		$rid = $db->insert(
			'ai_requests',
			array(
				'business_id'        => $business_id > 0 ? $business_id : null,
				'user_id'            => $user_id,
				'profile_key'        => $profile,
				'model'              => $config['model'],
				'purpose'            => mb_substr( $purpose, 0, 60 ),
				'analysis_id'        => $context['analysis_id'] ?? null,
				'action_card_id'     => $context['action_card_id'] ?? null,
				'estimated_cost_usd' => $pre['estimate'],
				'credential_scope'   => 'business' === $config['key_source'] ? 'business' : 'global',
			)
		);

		@set_time_limit( 200 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
		$start = microtime( true );
		$body  = array(
			'model'           => $config['model'],
			'temperature'     => (float) $config['temperature'],
			'max_tokens'      => (int) $config['max_tokens'],
			'response_format' => array( 'type' => 'json_object' ),
			'usage'           => array( 'include' => true ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $user,
				),
			),
		);

		[ $code, $response, $error ] = self::is_mock() ? Ai_Mock::respond( $purpose, $user ) : self::send( (string) $key, $body );
		$content  = (string) ( $response['choices'][0]['message']['content'] ?? '' );
		$usage    = (array) ( $response['usage'] ?? array() );
		$finish   = (string) ( $response['choices'][0]['finish_reason'] ?? $response['choices'][0]['native_finish_reason'] ?? '' );
		$data     = self::decode_json( $content );
		$repaired = false;
		if ( null === $data && 200 === $code && '' !== trim( $content ) ) {
			// Output cut at the token limit: keep every complete value (the rest is dropped).
			$data     = self::repair_json( $content );
			$repaired = null !== $data;
		}
		$ok      = 200 === $code && null !== $data;
		$invalid = 'Răspuns AI în format invalid (JSON așteptat).';
		if ( in_array( strtolower( $finish ), array( 'length', 'max_tokens' ), true ) ) {
			$invalid = sprintf( 'Răspunsul AI a fost trunchiat la limita de %d tokeni de ieșire a profilului „%s”. Măriți „Max tokens” în Setări → AI sau trimiteți mai puține pagini.', (int) $config['max_tokens'], $profile );
		} elseif ( '' === trim( $content ) ) {
			$invalid = 'Modelul nu a returnat niciun text (posibil filtrat sau limită de tokeni consumată de raționament). Reîncercați sau măriți „Max tokens”.';
		}
		$message = $ok ? null : mb_substr( (string) ( $response['error']['message'] ?? $error ?: ( 200 === $code ? $invalid : 'HTTP ' . $code ) ), 0, 500 );
		if ( ! $ok && 200 === $code ) {
			\OptiVio\Core\Logger::error(
				'AI answer not usable',
				array(
					'purpose' => $purpose,
					'model'   => $config['model'],
					'finish'  => $finish,
					'chars'   => strlen( $content ),
					'head'    => mb_substr( $content, 0, 300 ),
					'tail'    => mb_substr( $content, -300 ),
				)
			);
		} elseif ( $repaired ) {
			\OptiVio\Core\Logger::info(
				'AI answer repaired (truncated JSON)',
				array(
					'purpose' => $purpose,
					'finish'  => $finish,
					'chars'   => strlen( $content ),
				)
			);
		}

		$db->update(
			'ai_requests',
			array(
				'prompt_tokens'     => isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : null,
				'completion_tokens' => isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : null,
				'cost_usd'          => isset( $usage['cost'] ) ? round( (float) $usage['cost'], 6 ) : $pre['estimate'],
				'duration_ms'       => (int) round( ( microtime( true ) - $start ) * 1000 ),
				'status'            => $ok ? 'ok' : 'error',
				'error'             => $message,
			),
			array( 'id' => $rid )
		);
		if ( ! $ok ) {
			throw new RuntimeException( 'Apelul AI a eșuat: ' . $message );
		}
		return array(
			'request_id' => $rid,
			'data'       => $data,
			'raw'        => $content,
			'truncated'  => $repaired,
		);
	}

	/**
	 * HTTP call to OpenRouter.
	 *
	 * @param string $key  API key.
	 * @param array  $body Body.
	 * @return array{0:int,1:array,2:string}
	 */
	private static function send( string $key, array $body ): array {
		$ch = curl_init( self::ENDPOINT ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => json_encode( $body, JSON_UNESCAPED_UNICODE ),
				CURLOPT_HTTPHEADER     => array(
					'Authorization: Bearer ' . $key,
					'Content-Type: application/json',
					'HTTP-Referer: ' . rtrim( (string) Config::get( 'app_url', '' ), '/' ),
					'X-Title: OptiVio',
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 180,
				CURLOPT_CONNECTTIMEOUT => 10,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
			)
		);
		$raw   = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$error = false === $raw ? curl_error( $ch ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		$json  = json_decode( (string) $raw, true );
		return array( $code, is_array( $json ) ? $json : array(), $error );
	}

	/**
	 * Decodes a JSON object answer (tolerates ```json fences).
	 *
	 * @param string $content Content.
	 * @return array|null
	 */
	public static function decode_json( string $content ): ?array {
		$content = trim( $content );
		$content = (string) preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $content );
		$data    = json_decode( $content, true );
		if ( ! is_array( $data ) && preg_match( '/\{.*\}/s', $content, $m ) ) {
			$data = json_decode( $m[0], true );
		}
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Repairs a JSON object cut in the middle (token limit): keeps everything up to the last
	 * complete value and closes the open arrays / objects. Returns null when nothing usable remains.
	 *
	 * @param string $content Raw content.
	 * @return array|null
	 */
	public static function repair_json( string $content ): ?array {
		$content = (string) preg_replace( '/^\s*```(?:json)?\s*/i', '', $content );
		$start   = strpos( $content, '{' );
		if ( false === $start ) {
			return null;
		}
		$json   = substr( $content, $start );
		$len    = strlen( $json );
		$stack  = array();
		$in_str = false;
		$cuts   = array();
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $json[ $i ];
			if ( $in_str ) {
				if ( '\\' === $c ) {
					++$i;
				} elseif ( '"' === $c ) {
					$in_str = false;
				}
				continue;
			}
			if ( '"' === $c ) {
				$in_str = true;
			} elseif ( '{' === $c || '[' === $c ) {
				$stack[] = '{' === $c ? '}' : ']';
			} elseif ( '}' === $c || ']' === $c ) {
				array_pop( $stack );
				// A closed container is a complete value: cutting right after it is safe.
				$cuts[] = array( $i + 1, $stack );
			} elseif ( ',' === $c ) {
				$cuts[] = array( $i, $stack );
			}
		}
		// Try the longest prefixes first (max. 300 attempts).
		foreach ( array_slice( array_reverse( $cuts ), 0, 300 ) as [ $pos, $open ] ) {
			$candidate = rtrim( substr( $json, 0, $pos ), " \t\r\n," ) . implode( '', array_reverse( $open ) );
			$data      = json_decode( $candidate, true );
			if ( is_array( $data ) && ! empty( $data ) ) {
				return $data;
			}
		}
		return null;
	}
}
