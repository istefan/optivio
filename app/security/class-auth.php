<?php
/**
 * OptiVio — Authentication, session validation and brute-force protection.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Security;

use OptiVio\Core\Crypto;
use OptiVio\Core\Csrf;
use OptiVio\Core\Database;
use OptiVio\Core\Request;
use OptiVio\Core\Session;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Repositories\Users_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Auth service.
 */
final class Auth {

	/**
	 * Session key holding the auth state.
	 */
	private const SESSION_KEY = '_auth';

	/**
	 * Per-IP attempt ceiling per window (independent of the per-account limit).
	 */
	private const IP_MAX_ATTEMPTS = 30;

	/**
	 * Resolved user for this request (false = resolved, none).
	 *
	 * @var array|false|null
	 */
	private static $user = null;

	/**
	 * Attempts to log in.
	 *
	 * @param string  $email    Sanitized email.
	 * @param string  $password Raw password.
	 * @param Request $request  Request.
	 * @return array{ok:bool,error?:string}
	 */
	public static function attempt( string $email, string $password, Request $request ): array {
		$ip       = $request->ip();
		$window   = max( 60, (int) Settings_Repository::get( 'security.login_window_minutes', 15 ) * 60 );
		$lockout  = max( 60, (int) Settings_Repository::get( 'security.lockout_minutes', 15 ) * 60 );
		$max      = max( 3, (int) Settings_Repository::get( 'security.login_max_attempts', 5 ) );
		$buckets  = array(
			'ip'    => 'login:ip:' . $ip,
			'email' => 'login:email:' . $email,
			'pair'  => 'login:pair:' . $email . '|' . $ip,
		);
		$generic  = 'Email sau parolă incorectă.';
		$throttle = 'Prea multe încercări. Încercați din nou peste %d minute.';

		foreach ( $buckets as $bucket ) {
			$blocked = Rate_Limiter::blocked_for( $bucket );
			if ( $blocked > 0 ) {
				self::log_attempt( $email, $request, null, false, 'throttled' );
				Audit_Log::record( 'auth.login_throttled', null, null, array( 'email_ref' => substr( Crypto::keyed_hash( $email, 'email' ), 0, 16 ) ) );
				return array(
					'ok'    => false,
					'error' => sprintf( $throttle, (int) ceil( $blocked / 60 ) ),
				);
			}
		}

		$user = '' !== $email ? Users_Repository::find_for_auth( $email ) : null;

		// Always run password_verify to keep timing constant for unknown emails.
		$hash  = null !== $user ? (string) $user['password_hash'] : self::dummy_hash();
		$valid = password_verify( $password, $hash ) && null !== $user && 'active' === $user['status'];

		if ( ! $valid ) {
			Rate_Limiter::hit( $buckets['ip'], self::IP_MAX_ATTEMPTS, $window, $lockout );
			Rate_Limiter::hit( $buckets['email'], $max * 2, $window, $lockout );
			$now_blocked = Rate_Limiter::hit( $buckets['pair'], $max, $window, $lockout );

			$reason = null === $user ? 'unknown_email' : ( 'active' !== $user['status'] ? 'disabled' : 'bad_password' );
			self::log_attempt( $email, $request, null !== $user ? (int) $user['id'] : null, false, $reason );
			Audit_Log::record( 'auth.login_failed', 'user', null !== $user ? (int) $user['id'] : null, array( 'reason' => $reason ), null, null !== $user ? (int) $user['id'] : null );

			return array(
				'ok'    => false,
				'error' => $now_blocked ? sprintf( $throttle, (int) ceil( $lockout / 60 ) ) : $generic,
			);
		}

		Rate_Limiter::clear( $buckets['pair'] );
		Rate_Limiter::clear( $buckets['email'] );

		if ( Password_Policy::needs_rehash( $hash ) ) {
			Users_Repository::upgrade_hash( (int) $user['id'], Password_Policy::hash( $password ) );
		}

		self::login( (int) $user['id'], (int) $user['session_version'], $request );
		self::log_attempt( $email, $request, (int) $user['id'], true, null );
		Audit_Log::record( 'auth.login', 'user', (int) $user['id'], array(), null, (int) $user['id'] );

		return array( 'ok' => true );
	}

	/**
	 * Establishes an authenticated session.
	 *
	 * @param int     $user_id         User id.
	 * @param int     $session_version Current session version of the user.
	 * @param Request $request         Request.
	 * @return void
	 */
	public static function login( int $user_id, int $session_version, Request $request ): void {
		Session::regenerate();
		Csrf::rotate();
		Session::remove( 'active_business_id' );
		Session::set(
			self::SESSION_KEY,
			array(
				'uid'           => $user_id,
				'ver'           => $session_version,
				'login_at'      => time(),
				'last_activity' => time(),
				'ua'            => hash( 'sha256', $request->user_agent() ),
			)
		);
		Users_Repository::touch_login( $user_id, $request->ip_binary() );
		self::$user = null;
	}

	/**
	 * Logs out the current user.
	 *
	 * @return void
	 */
	public static function logout(): void {
		Session::destroy();
		self::$user = false;
	}

	/**
	 * Whether a valid authenticated session exists.
	 *
	 * @return bool
	 */
	public static function check(): bool {
		return null !== self::user();
	}

	/**
	 * Returns the authenticated user (validated once per request) or null.
	 *
	 * @return array|null
	 */
	public static function user(): ?array {
		if ( false === self::$user ) {
			return null;
		}
		if ( is_array( self::$user ) ) {
			return self::$user;
		}

		$state = Session::get( self::SESSION_KEY );
		if ( ! is_array( $state ) || empty( $state['uid'] ) ) {
			self::$user = false;
			return null;
		}

		$now      = time();
		$idle     = max( 5, (int) Settings_Repository::get( 'security.session_idle_minutes', 30 ) ) * 60;
		$absolute = max( 1, (int) Settings_Repository::get( 'security.session_absolute_hours', 12 ) ) * 3600;

		$expired = ( $now - (int) $state['last_activity'] ) > $idle || ( $now - (int) $state['login_at'] ) > $absolute;
		$ua_ok   = hash_equals( (string) $state['ua'], hash( 'sha256', Request::current()->user_agent() ) );
		$user    = Users_Repository::find( (int) $state['uid'] );

		$valid = ! $expired
			&& $ua_ok
			&& null !== $user
			&& 'active' === $user['status']
			&& (int) $user['session_version'] === (int) $state['ver'];

		if ( ! $valid ) {
			if ( $expired && null !== $user ) {
				Audit_Log::record( 'auth.session_expired', 'user', (int) $user['id'], array(), null, (int) $user['id'] );
			}
			Session::destroy();
			Session::start( Request::current()->is_https() );
			if ( $expired ) {
				Session::flash( 'warning', 'Sesiunea a expirat. Vă rugăm să vă autentificați din nou.' );
			}
			self::$user = false;
			return null;
		}

		$state['last_activity'] = $now;
		Session::set( self::SESSION_KEY, $state );

		self::$user = $user;
		return $user;
	}

	/**
	 * Returns the current user id or 0.
	 *
	 * @return int
	 */
	public static function id(): int {
		$user = self::user();
		return null !== $user ? (int) $user['id'] : 0;
	}

	/**
	 * Re-reads the current user's session version after a self password change,
	 * so the acting session stays valid while all other sessions are invalidated.
	 *
	 * @return void
	 */
	public static function refresh_session_version(): void {
		$state = Session::get( self::SESSION_KEY );
		$user  = Users_Repository::find( (int) ( $state['uid'] ?? 0 ) );
		if ( is_array( $state ) && null !== $user ) {
			Session::regenerate();
			$state['ver'] = (int) $user['session_version'];
			Session::set( self::SESSION_KEY, $state );
			self::$user = $user;
		}
	}

	/**
	 * Stores a login attempt row.
	 *
	 * @param string   $email   Email.
	 * @param Request  $request Request.
	 * @param int|null $user_id User id.
	 * @param bool     $success Success.
	 * @param string|null $reason Reason.
	 * @return void
	 */
	private static function log_attempt( string $email, Request $request, ?int $user_id, bool $success, ?string $reason ): void {
		Database::instance()->insert(
			'login_attempts',
			array(
				'email_hash' => Crypto::keyed_hash( $email, 'email' ),
				'ip'         => $request->ip_binary(),
				'user_id'    => $user_id,
				'success'    => $success ? 1 : 0,
				'reason'     => $reason,
			)
		);
	}

	/**
	 * Returns a dummy hash (same algorithm) for constant-time verification of unknown emails.
	 *
	 * @return string
	 */
	private static function dummy_hash(): string {
		// Precomputed with the default Argon2id cost so unknown emails take the same time as real ones.
		$dummy = '$argon2id$v=19$m=65536,t=4,p=1$SzNKY2JGZDdROXk4YVdHWg$A7HVE5m6F//hg9+PegE3xNrKYSB48HrHyQ50yYYqv2M';
		return PASSWORD_ARGON2ID === Password_Policy::algorithm() ? $dummy : '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
	}
}
