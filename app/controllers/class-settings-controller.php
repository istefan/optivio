<?php
/**
 * OptiVio — Global settings (super admin): general, security, AI profiles, integrations, cron.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use DateTimeZone;
use OptiVio\Core\Config;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Job_Queue;
use OptiVio\Core\Validator;
use OptiVio\Repositories\Credentials_Repository;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Services\Cron_Runner;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Settings controller.
 */
final class Settings_Controller extends Controller {

	/**
	 * Tabs.
	 */
	private const SECTIONS = array(
		'general'      => 'General',
		'security'     => 'Securitate',
		'ai'           => 'AI / OpenRouter',
		'integrations' => 'Integrări & chei API',
		'spam'         => 'Anti-spam & conector',
		'cron'         => 'Cron & joburi',
	);

	/**
	 * Providers whose global keys are managed here.
	 */
	private const GLOBAL_PROVIDERS = array( 'openrouter', 'serper', 'pagespeed' );

	/**
	 * AI profiles.
	 */
	public const AI_PROFILES = array(
		'fast'      => array( 'Model Rapid / Audit', 'Clasificări, rezumate și verificări rapide.' ),
		'reasoning' => array( 'Model Raționament / Strategie', 'Analiza profundă multi-sursă și strategia Ads/SEO.' ),
		'copy'      => array( 'Model Copywriting / Conținut', 'Titluri, meta descrieri, articole și landing page-uri.' ),
		'perception' => array( 'Model Percepție / Onboarding', 'Citește paginile site-ului ca un bot și descrie ce înțelege despre afacere (recomandat: Gemini Flash, context mare, cost mic). Max tokens: minim 16 000 (se aplică automat), recomandat 32 000.' ),
	);

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public function index(): void {
		$tab = sanitize_key( $this->request->query( 'tab', 'general' ) );
		$tab = isset( self::SECTIONS[ $tab ] ) ? $tab : 'general';

		$credentials = array();
		foreach ( self::GLOBAL_PROVIDERS as $provider ) {
			$credentials[ $provider ] = Credentials_Repository::find_global( $provider );
		}

		$settings = array();
		foreach ( array_keys( Settings_Repository::DEFAULTS ) as $key ) {
			$settings[ $key ] = Settings_Repository::get( $key );
		}

		$this->render(
			'settings/index',
			array(
				'page_title'         => 'Setări globale',
				'page_subtitle'      => 'Configurarea aplicației, securitate, AI și integrări',
				'tab'                => $tab,
				'sections'           => self::SECTIONS,
				'settings'           => $settings,
				'errors'             => $this->pull_errors(),
				'credentials'        => $credentials,
				'provider_labels'    => Credentials_Repository::PROVIDERS,
				'google_secret_hint' => Settings_Repository::secret_hint( 'integrations.google_client_secret' ),
				'ads_token_hint'     => Settings_Repository::secret_hint( 'integrations.ads_developer_token' ),
				'ai_profiles'        => self::AI_PROFILES,
				'job_stats'          => Job_Queue::stats(),
				'cron_runs'          => Cron_Runner::recent( 8 ),
				'web_cron'           => \OptiVio\Services\Web_Cron::status(),
				'timezones'          => DateTimeZone::listIdentifiers( DateTimeZone::EUROPE ),
				'oauth_redirect'     => ov_url( '/oauth/google/callback' ),
			)
		);
	}

	/**
	 * POST /settings/test/{provider} — "Testează conexiunea" (JSON).
	 *
	 * @return void
	 */
	public function test_connection(): void {
		$provider = $this->request->param_key( 'provider' );
		if ( ! isset( \OptiVio\Services\Connection_Tester::PROVIDERS[ $provider ] ) ) {
			throw \OptiVio\Core\Http_Exception::not_found();
		}
		$result = \OptiVio\Services\Connection_Tester::test( $provider );
		Audit_Log::record(
			'settings.test_connection',
			'settings',
			null,
			array(
				'provider' => $provider,
				'ok'       => $result['ok'],
			)
		);
		\OptiVio\Core\Response::json( $result );
	}

	/**
	 * Saves a settings section.
	 *
	 * @return void
	 * @throws Http_Exception 404 for unknown sections.
	 */
	public function update(): void {
		$section = $this->request->param_key( 'section' );
		if ( ! isset( self::SECTIONS[ $section ] ) ) {
			throw Http_Exception::not_found();
		}

		$v      = new Validator();
		$values = $this->{'collect_' . $section}( $v );

		if ( $v->fails() ) {
			$this->back_with_errors( '/settings?tab=' . $section, $v->errors(), array() );
		}

		$user_id = (int) $this->user()['id'];
		$changed = array();
		foreach ( $values as $key => $value ) {
			if ( Settings_Repository::get( $key ) !== $value ) {
				$changed[] = $key;
			}
			Settings_Repository::set( $key, $value, $user_id );
		}

		if ( 'integrations' === $section ) {
			$secret = trim( (string) $this->request->input( 'google_client_secret', '' ) );
			if ( '' !== $secret ) {
				Settings_Repository::set_secret( 'integrations.google_client_secret', mb_substr( $secret, 0, 255 ), $user_id );
				$changed[] = 'integrations.google_client_secret';
			}
			$dev_token = trim( (string) $this->request->input( 'ads_developer_token', '' ) );
			if ( '' !== $dev_token ) {
				Settings_Repository::set_secret( 'integrations.ads_developer_token', mb_substr( $dev_token, 0, 100 ), $user_id );
				$changed[] = 'integrations.ads_developer_token';
			}
		}

		Audit_Log::record(
			'settings.update',
			'settings',
			null,
			array(
				'section' => $section,
				'changed' => $changed,
			)
		);
		$this->redirect_with( '/settings?tab=' . $section, 'success', 'Setările au fost salvate.' );
	}

	/**
	 * Saves or deletes a global API credential.
	 *
	 * @return void
	 * @throws Http_Exception 404 for unknown providers.
	 */
	public function save_credential(): void {
		$provider = $this->request->param_key( 'provider' );
		if ( ! in_array( $provider, self::GLOBAL_PROVIDERS, true ) ) {
			throw Http_Exception::not_found();
		}
		$tab = 'openrouter' === $provider ? 'ai' : 'integrations';

		if ( 'delete' === sanitize_key( $this->request->input( 'action', '' ) ) ) {
			Credentials_Repository::delete_global( $provider );
			Audit_Log::record( 'credential.delete', 'api_credential', null, array( 'provider' => $provider ) );
			$this->redirect_with( '/settings?tab=' . $tab, 'success', 'Cheia a fost ștearsă.' );
		}

		$secret = trim( (string) $this->request->input( 'secret', '' ) );
		if ( strlen( $secret ) < 16 || strlen( $secret ) > 512 || ! preg_match( '/^[\x21-\x7E]+$/', $secret ) ) {
			$this->redirect_with( '/settings?tab=' . $tab, 'error', 'Cheia API pare invalidă (16–512 caractere, fără spații).' );
		}

		$id = Credentials_Repository::save_global( $provider, $secret, (int) $this->user()['id'] );
		Audit_Log::record( 'credential.save', 'api_credential', $id, array( 'provider' => $provider ) );
		$this->redirect_with( '/settings?tab=' . $tab, 'success', 'Cheia a fost salvată criptat (AES-256-GCM).' );
	}

	/**
	 * General section.
	 *
	 * @param Validator $v Validator.
	 * @return array
	 */
	private function collect_general( Validator $v ): array {
		$values = array(
			'general.app_name' => $this->text( 'app_name', 60 ),
			'general.timezone' => $this->text( 'timezone', 64 ),
			'general.currency' => strtoupper( $this->text( 'currency', 3 ) ),
		);
		$v->required( 'app_name', $values['general.app_name'] )
			->in( 'timezone', $values['general.timezone'], DateTimeZone::listIdentifiers() )
			->in( 'currency', $values['general.currency'], array( 'RON', 'EUR', 'USD' ) );
		return $values;
	}

	/**
	 * Security section.
	 *
	 * @param Validator $v Validator.
	 * @return array
	 */
	private function collect_security( Validator $v ): array {
		$ranges = array(
			'session_idle_minutes'   => array( 5, 480 ),
			'session_absolute_hours' => array( 1, 72 ),
			'login_max_attempts'     => array( 3, 20 ),
			'login_window_minutes'   => array( 5, 120 ),
			'lockout_minutes'        => array( 5, 1440 ),
			'password_min_length'    => array( 10, 64 ),
			'audit_retention_days'   => array( 30, 3650 ),
		);
		$values = array();
		foreach ( $ranges as $field => $range ) {
			$value = absint( $this->request->input( $field, 0 ) );
			$v->between( $field, $value, $range[0], $range[1] );
			$values[ 'security.' . $field ] = $value;
		}
		return $values;
	}

	/**
	 * AI section (3 editable model profiles + budget).
	 *
	 * @param Validator $v Validator.
	 * @return array
	 */
	private function collect_ai( Validator $v ): array {
		$values = array();
		foreach ( array_keys( self::AI_PROFILES ) as $profile ) {
			$model       = strtolower( $this->text( 'model_' . $profile, 120 ) );
			$temperature = (float) str_replace( ',', '.', $this->text( 'temperature_' . $profile, 5 ) );
			$max_tokens  = absint( $this->request->input( 'max_tokens_' . $profile, 0 ) );

			$v->required( 'model_' . $profile, $model )
				->pattern( 'model_' . $profile, $model, '#^[a-z0-9._\-]+/[a-z0-9._:\-]+$#', 'Format model OpenRouter invalid (ex. anthropic/claude-sonnet-5.5).' )
				->between( 'max_tokens_' . $profile, $max_tokens, 256, 64000 );
			if ( $temperature < 0 || $temperature > 2 ) {
				$v->add( 'temperature_' . $profile, 'Temperatura trebuie să fie între 0 și 2.' );
			}

			$values[ 'ai.profile.' . $profile . '.model' ]       = $model;
			$values[ 'ai.profile.' . $profile . '.temperature' ] = round( $temperature, 2 );
			$values[ 'ai.profile.' . $profile . '.max_tokens' ]  = $max_tokens;
		}

		$budget = absint( $this->request->input( 'monthly_budget_usd', 0 ) );
		$v->between( 'monthly_budget_usd', $budget, 0, 100000 );
		$values['ai.monthly_budget_usd']  = $budget;

		$limits = array(
			'max_pages_per_site' => array( 1, 50 ),
			'max_pages_per_run'  => array( 1, 100 ),
			'max_input_chars'    => array( 20000, 1000000 ),
		);
		foreach ( $limits as $field => $range ) {
			$value = absint( $this->request->input( 'onboarding_' . $field, 0 ) );
			$v->between( 'onboarding_' . $field, $value, $range[0], $range[1] );
			$values[ 'onboarding.' . $field ] = $value;
		}
		$values['ai.confirm_before_call'] = '1' === (string) $this->request->input( 'confirm_before_call', '0' );
		return $values;
	}

	/**
	 * Integrations section (non-secret values; secrets go through dedicated fields).
	 *
	 * @param Validator $v Validator.
	 * @return array
	 */
	private function collect_integrations( Validator $v ): array {
		$values = array(
			'integrations.google_client_id' => $this->text( 'google_client_id', 200 ),
			'integrations.serper_gl'        => strtolower( $this->text( 'serper_gl', 2 ) ),
			'integrations.serper_hl'        => strtolower( $this->text( 'serper_hl', 5 ) ),
			'integrations.serper_location'  => $this->text( 'serper_location', 80 ),
			'integrations.psi_strategy'     => sanitize_key( $this->request->input( 'psi_strategy', 'mobile' ) ),
			'integrations.ads_api_version'  => sanitize_key( $this->request->input( 'ads_api_version', 'v21' ) ),
			'integrations.ads_login_customer_id' => (string) preg_replace( '/\D/', '', $this->text( 'ads_login_customer_id', 20 ) ),
			'integrations.gsc_backfill_days' => absint( $this->request->input( 'gsc_backfill_days', 90 ) ),
		);
		$v->pattern( 'ads_api_version', $values['integrations.ads_api_version'], '/^v[0-9]{2}$/', 'Format vNN (ex. v21).' )
			->pattern( 'ads_login_customer_id', $values['integrations.ads_login_customer_id'], '/^[0-9]{10}$/', 'Customer ID MCC are 10 cifre.' )
			->between( 'gsc_backfill_days', $values['integrations.gsc_backfill_days'], 7, 480 );
		$v->pattern( 'google_client_id', $values['integrations.google_client_id'], '/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/', 'Client ID Google invalid.' )
			->pattern( 'serper_gl', $values['integrations.serper_gl'], '/^[a-z]{2}$/', 'Cod țară invalid (ex. ro).' )
			->pattern( 'serper_hl', $values['integrations.serper_hl'], '/^[a-z]{2}(-[a-z]{2})?$/', 'Cod limbă invalid (ex. ro).' )
			->in( 'psi_strategy', $values['integrations.psi_strategy'], array( 'mobile', 'desktop' ) );
		return $values;
	}

	/**
	 * Anti-spam section (deterministic thresholds and lists).
	 *
	 * @param Validator $v Validator.
	 * @return array
	 */
	private function collect_spam( Validator $v ): array {
		$ranges = array(
			'threshold_suspect'     => array( 10, 90 ),
			'threshold_spam'        => array( 20, 100 ),
			'min_fill_seconds'      => array( 1, 30 ),
			'max_links'             => array( 0, 20 ),
			'ip_hourly_limit'       => array( 1, 50 ),
			'connector_stale_hours' => array( 1, 720 ),
		);
		$values = array();
		foreach ( $ranges as $field => $range ) {
			$value = absint( $this->request->input( $field, 0 ) );
			$v->between( $field, $value, $range[0], $range[1] );
			$values[ 'spam.' . $field ] = $value;
		}
		if ( $values['spam.threshold_suspect'] >= $values['spam.threshold_spam'] ) {
			$v->add( 'threshold_suspect', 'Pragul „suspect” trebuie să fie mai mic decât pragul „spam”.' );
		}

		$values['spam.blocked_words']      = $this->lines( 'blocked_words', 100 );
		$values['spam.disposable_domains'] = array_values(
			array_filter(
				$this->lines( 'disposable_domains', 60 ),
				static function ( string $domain ): bool {
					return (bool) preg_match( '/^[a-z0-9.\-]+\.[a-z]{2,}$/', $domain );
				}
			)
		);
		return $values;
	}

	/**
	 * Reads a textarea as a unique, lower-cased list (one item per line, max 300 items).
	 *
	 * @param string $key Field.
	 * @param int    $max Max length per item.
	 * @return array<int,string>
	 */
	private function lines( string $key, int $max ): array {
		$items = array();
		foreach ( preg_split( '/\R/', $this->textarea( $key, 20000 ) ) as $line ) {
			$line = mb_strtolower( trim( $line ) );
			if ( '' !== $line && mb_strlen( $line ) <= $max ) {
				$items[ $line ] = $line;
			}
		}
		return array_slice( array_values( $items ), 0, 300 );
	}

	/**
	 * Cron section.
	 *
	 * @param Validator $v Validator.
	 * @return array
	 */
	private function collect_cron( Validator $v ): array {
		$seconds  = absint( $this->request->input( 'batch_seconds', 45 ) );
		$interval = absint( $this->request->input( 'interval_minutes', 1 ) );
		$v->between( 'batch_seconds', $seconds, 10, 55 )->between( 'interval_minutes', $interval, 1, 15 );
		return array(
			'cron.batch_seconds'    => $seconds,
			'cron.interval_minutes' => $interval,
		);
	}
}
