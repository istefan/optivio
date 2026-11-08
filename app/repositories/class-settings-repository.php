<?php
/**
 * OptiVio — Global settings (non-secret values JSON encoded; secrets AES-256-GCM).
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
 * Settings store with defaults.
 */
final class Settings_Repository {

	/**
	 * Defaults for every known setting key.
	 */
	public const DEFAULTS = array(
		'general.app_name'                 => 'OptiVio',
		'general.timezone'                 => 'Europe/Bucharest',
		'general.currency'                 => 'RON',
		'general.date_format'              => 'd.m.Y',

		'security.session_idle_minutes'    => 30,
		'security.session_absolute_hours'  => 12,
		'security.login_max_attempts'      => 5,
		'security.login_window_minutes'    => 15,
		'security.lockout_minutes'         => 15,
		'security.password_min_length'     => 12,
		'security.audit_retention_days'    => 365,

		'ai.profile.fast.model'            => 'anthropic/claude-haiku-5.5',
		'ai.profile.fast.temperature'      => 0.2,
		'ai.profile.fast.max_tokens'       => 2000,
		'ai.profile.reasoning.model'       => 'anthropic/claude-opus-5.5',
		'ai.profile.reasoning.temperature' => 0.3,
		'ai.profile.reasoning.max_tokens'  => 8000,
		'ai.profile.copy.model'            => 'anthropic/claude-sonnet-5.5',
		'ai.profile.copy.temperature'      => 0.7,
		'ai.profile.copy.max_tokens'       => 6000,
		'ai.monthly_budget_usd'            => 50,
		'ai.confirm_before_call'           => true,

		'integrations.serper_gl'           => 'ro',
		'integrations.serper_hl'           => 'ro',
		'integrations.serper_location'     => 'Romania',
		'integrations.google_client_id'    => '',
		'integrations.psi_strategy'        => 'mobile',
		'integrations.ads_api_version'     => 'v21',
		'integrations.ads_login_customer_id' => '',
		'integrations.gsc_backfill_days'   => 90,

		'cron.batch_seconds'               => 45,

		'spam.threshold_suspect'           => 40,
		'spam.threshold_spam'              => 70,
		'spam.min_fill_seconds'            => 3,
		'spam.max_links'                   => 2,
		'spam.ip_hourly_limit'             => 3,
		'spam.blocked_words'               => array( 'viagra', 'cialis', 'casino', 'bitcoin', 'crypto', 'forex', 'loan offer', 'seo services', 'backlinks', 'link building', 'web design services', 'porn', 'escort', 'betting', 'rank your website', 'first page of google', 'whatsapp me' ),
		'spam.disposable_domains'          => array( 'mailinator.com', 'yopmail.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'trashmail.com', 'sharklasers.com', 'getnada.com', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com' ),
		'spam.connector_stale_hours'       => 48,
	);

	/**
	 * Keys stored encrypted.
	 */
	public const SECRET_KEYS = array(
		'integrations.google_client_secret',
		'integrations.ads_developer_token',
	);

	/**
	 * Request-level cache of non-secret values.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Returns a setting value (falls back to defaults).
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Fallback when there is no default either.
	 * @return mixed
	 */
	public static function get( string $key, $default_value = null ) {
		self::load();
		if ( array_key_exists( $key, (array) self::$cache ) ) {
			return self::$cache[ $key ];
		}
		return self::DEFAULTS[ $key ] ?? $default_value;
	}

	/**
	 * Saves a non-secret setting.
	 *
	 * @param string   $key     Key.
	 * @param mixed    $value   Value (JSON encodable).
	 * @param int|null $user_id Acting user.
	 * @return void
	 */
	public static function set( string $key, $value, ?int $user_id = null ): void {
		Database::instance()->query(
			'INSERT INTO settings (setting_key, setting_value, is_encrypted, updated_by) VALUES (?, ?, 0, ?)
			 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = 0, updated_by = VALUES(updated_by)',
			array( $key, json_encode( $value, JSON_UNESCAPED_UNICODE ), $user_id )
		);
		if ( null !== self::$cache ) {
			self::$cache[ $key ] = $value;
		}
	}

	/**
	 * Stores an encrypted secret setting.
	 *
	 * @param string   $key     Key (must be in SECRET_KEYS).
	 * @param string   $secret  Plain secret.
	 * @param int|null $user_id Acting user.
	 * @return void
	 */
	public static function set_secret( string $key, string $secret, ?int $user_id = null ): void {
		$payload = json_encode(
			array(
				'c' => Crypto::encrypt( $secret, 'settings:' . $key ),
				'h' => Crypto::hint( $secret ),
			)
		);
		Database::instance()->query(
			'INSERT INTO settings (setting_key, setting_value, is_encrypted, updated_by) VALUES (?, ?, 1, ?)
			 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = 1, updated_by = VALUES(updated_by)',
			array( $key, $payload, $user_id )
		);
	}

	/**
	 * Returns a decrypted secret setting (server-side use only — never render it).
	 *
	 * @param string $key Key.
	 * @return string|null
	 */
	public static function get_secret( string $key ): ?string {
		$row = Database::instance()->get_row( 'SELECT setting_value FROM settings WHERE setting_key = ? AND is_encrypted = 1', array( $key ) );
		if ( null === $row ) {
			return null;
		}
		$data = json_decode( (string) $row['setting_value'], true );
		return is_array( $data ) && isset( $data['c'] ) ? Crypto::decrypt( (string) $data['c'], 'settings:' . $key ) : null;
	}

	/**
	 * Returns the display hint of a secret setting (last 4 chars) or null when not set.
	 *
	 * @param string $key Key.
	 * @return string|null
	 */
	public static function secret_hint( string $key ): ?string {
		$row = Database::instance()->get_row( 'SELECT setting_value FROM settings WHERE setting_key = ? AND is_encrypted = 1', array( $key ) );
		if ( null === $row ) {
			return null;
		}
		$data = json_decode( (string) $row['setting_value'], true );
		return is_array( $data ) ? (string) ( $data['h'] ?? '' ) : '';
	}

	/**
	 * Deletes a setting.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public static function delete( string $key ): void {
		Database::instance()->delete( 'settings', array( 'setting_key' => $key ) );
		if ( null !== self::$cache ) {
			unset( self::$cache[ $key ] );
		}
	}

	/**
	 * Loads all non-secret settings once per request.
	 *
	 * @return void
	 */
	private static function load(): void {
		if ( null !== self::$cache ) {
			return;
		}
		self::$cache = array();
		$rows        = Database::instance()->get_results( 'SELECT setting_key, setting_value FROM settings WHERE is_encrypted = 0' );
		foreach ( $rows as $row ) {
			self::$cache[ (string) $row['setting_key'] ] = json_decode( (string) $row['setting_value'], true );
		}
	}
}
