<?php
/**
 * OptiVio — Web cron (like WP-Cron): no cPanel / crontab needed.
 *
 * Every request (pages, AJAX, plugin heartbeats and events) checks, after the response,
 * whether a tick is due (at most once per interval, claimed atomically in MySQL). When due it
 * fires a non-blocking loopback POST to /cron/spawn, authenticated with a secret generated
 * automatically and stored encrypted in settings; that request runs the job queue in the
 * background. When the host blocks loopback requests, the tick runs after the response
 * (fastcgi_finish_request) or is triggered by the browser of a logged-in user (/cron/tick).
 * Handlers never call AI (RULES.md R5).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;
use OptiVio\Core\Logger;
use OptiVio\Repositories\Settings_Repository;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Web cron.
 */
final class Web_Cron {

	private const LAST_KEY     = 'cron.web_last_spawn';
	private const LOOPBACK_KEY = 'cron.web_loopback';
	private const TOKEN_KEY    = 'cron.web_token';

	/**
	 * Paths that never spawn (the cron endpoints themselves).
	 */
	private const SKIP = array( '/cron/spawn', '/cron/tick', '/jobs/run-batch' );

	/**
	 * Interval between ticks (seconds).
	 *
	 * @return int
	 */
	public static function interval(): int {
		return 60 * max( 1, min( 15, (int) Settings_Repository::get( 'cron.interval_minutes', 1 ) ) );
	}

	/**
	 * Secret of the loopback endpoint (generated on first use, stored encrypted).
	 *
	 * @return string
	 */
	public static function token(): string {
		$token = Settings_Repository::get_secret( self::TOKEN_KEY );
		if ( null === $token || strlen( $token ) < 32 ) {
			$token = bin2hex( random_bytes( 24 ) );
			Settings_Repository::set_secret( self::TOKEN_KEY, $token );
		}
		return $token;
	}

	/**
	 * Registered at the start of every web request; runs after the response.
	 *
	 * @param string $path Request path.
	 * @return void
	 */
	public static function register( string $path ): void {
		if ( 'cli' === PHP_SAPI || in_array( $path, self::SKIP, true ) ) {
			return;
		}
		register_shutdown_function( array( self::class, 'on_shutdown' ) );
	}

	/**
	 * Shutdown hook: claims a due tick and spawns it.
	 *
	 * @return void
	 */
	public static function on_shutdown(): void {
		try {
			if ( ! self::claim() ) {
				return;
			}
			if ( self::loopback_ok() ) {
				self::spawn();
				return;
			}
			// Loopback blocked by the host: run after the response when the SAPI allows it.
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
				ignore_user_abort( true );
				Cron_Runner::run( 'web', 25 );
			} elseif ( function_exists( 'litespeed_finish_request' ) ) {
				litespeed_finish_request();
				ignore_user_abort( true );
				Cron_Runner::run( 'web', 25 );
			} else {
				// Last resort: the next page of a logged-in user triggers /cron/tick (see layout meta).
				self::release();
			}
		} catch ( Throwable $e ) {
			Logger::error( 'Web cron spawn failed', array( 'exception' => $e ) );
		}
	}

	/**
	 * Atomically claims the next tick (one request per interval wins).
	 *
	 * @return bool
	 */
	public static function claim(): bool {
		$db   = Database::instance();
		$now  = time();
		$prev = $now - self::interval();
		$hit  = $db->query(
			'UPDATE settings SET setting_value = ? WHERE setting_key = ? AND CAST(setting_value AS UNSIGNED) <= ?',
			array( (string) $now, self::LAST_KEY, $prev )
		)->rowCount();
		if ( 1 === $hit ) {
			return true;
		}
		// First run ever: the row does not exist yet.
		return 1 === $db->query( 'INSERT IGNORE INTO settings (setting_key, setting_value, is_encrypted) VALUES (?, ?, 0)', array( self::LAST_KEY, (string) $now ) )->rowCount();
	}

	/**
	 * Gives the tick back (so the browser fallback can take it).
	 *
	 * @return void
	 */
	private static function release(): void {
		Database::instance()->query( 'UPDATE settings SET setting_value = ? WHERE setting_key = ?', array( '0', self::LAST_KEY ) );
	}

	/**
	 * Whether a tick is due (used by the browser fallback).
	 *
	 * @return bool
	 */
	public static function is_due(): bool {
		return (int) Settings_Repository::get( self::LAST_KEY, 0 ) <= time() - self::interval();
	}

	/**
	 * Whether the layout must ask the browser to trigger the tick (loopback blocked, no fastcgi).
	 *
	 * @return bool
	 */
	public static function needs_browser_tick(): bool {
		try {
			return ! self::loopback_ok() && ! function_exists( 'fastcgi_finish_request' ) && ! function_exists( 'litespeed_finish_request' ) && self::is_due();
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Whether loopback requests work on this host (re-tested every hour after a failure).
	 *
	 * @return bool
	 */
	public static function loopback_ok(): bool {
		$state = (array) Settings_Repository::get( self::LOOPBACK_KEY, array() );
		return ! isset( $state['ok'] ) || ! empty( $state['ok'] ) || (int) ( $state['at'] ?? 0 ) < time() - 3600;
	}

	/**
	 * Loopback status for the settings screen.
	 *
	 * @return array{ok:?bool,at:int,error:string,last:int}
	 */
	public static function status(): array {
		$state = (array) Settings_Repository::get( self::LOOPBACK_KEY, array() );
		return array(
			'ok'    => isset( $state['ok'] ) ? (bool) $state['ok'] : null,
			'at'    => (int) ( $state['at'] ?? 0 ),
			'error' => (string) ( $state['error'] ?? '' ),
			'last'  => (int) Settings_Repository::get( self::LAST_KEY, 0 ),
		);
	}

	/**
	 * Fires the non-blocking loopback request.
	 *
	 * @return bool Whether the request reached the server.
	 */
	public static function spawn(): bool {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		$ch = curl_init( ov_url( '/cron/spawn' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
		curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
			$ch,
			array(
				CURLOPT_POST              => true,
				CURLOPT_POSTFIELDS        => '',
				CURLOPT_HTTPHEADER        => array( 'X-OptiVio-Cron: ' . self::token(), 'User-Agent: OptiVio-Cron/' . OPTIVIO_VERSION ),
				CURLOPT_RETURNTRANSFER    => true,
				CURLOPT_TIMEOUT_MS        => 1000,
				CURLOPT_CONNECTTIMEOUT_MS => 800,
				CURLOPT_NOSIGNAL          => true,
				CURLOPT_FOLLOWLOCATION    => false,
				// Request to our own app_url (like WordPress' cron loopback): certificate errors must not stop the jobs.
				CURLOPT_SSL_VERIFYPEER    => false,
				CURLOPT_SSL_VERIFYHOST    => 0,
			)
		);
		curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
		$errno = curl_errno( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_errno
		$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
		$error = curl_error( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_error
		// Timeout (28) after sending = expected: the job keeps running server side.
		$ok = ( 0 === $errno && $code > 0 && $code < 400 ) || 28 === $errno;
		Settings_Repository::set(
			self::LOOPBACK_KEY,
			array(
				'ok'    => $ok,
				'at'    => time(),
				'error' => $ok ? '' : mb_substr( '' !== $error ? $error : 'HTTP ' . $code, 0, 200 ),
			)
		);
		if ( ! $ok ) {
			self::release();
		}
		return $ok;
	}
}
