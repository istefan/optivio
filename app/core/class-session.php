<?php
/**
 * OptiVio — Hardened PHP session handling.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Session wrapper: secure cookie flags, private save path, flash messages.
 */
final class Session {

	/**
	 * Starts the session with secure settings.
	 *
	 * @param bool $https Whether the request is HTTPS.
	 * @return void
	 */
	public static function start( bool $https ): void {
		if ( PHP_SESSION_ACTIVE === session_status() ) {
			return;
		}

		$save_path = OPTIVIO_STORAGE . '/sessions';
		if ( is_dir( $save_path ) && is_writable( $save_path ) ) {
			session_save_path( $save_path );
		}

		ini_set( 'session.use_strict_mode', '1' );
		ini_set( 'session.use_only_cookies', '1' );
		ini_set( 'session.use_trans_sid', '0' );
		ini_set( 'session.cookie_httponly', '1' );
		ini_set( 'session.gc_maxlifetime', (string) (int) Config::get( 'session.absolute_timeout', 43200 ) );
		ini_set( 'session.gc_probability', '1' );
		ini_set( 'session.gc_divisor', '100' );

		$secure = $https || (bool) Config::get( 'force_https', false );
		$path   = Config::base_path();

		session_name( (string) Config::get( 'session.name', 'OVSESSID' ) );
		session_set_cookie_params(
			array(
				'lifetime' => 0,
				'path'     => '' !== $path ? $path . '/' : '/',
				'domain'   => '',
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		session_start();
	}

	/**
	 * Regenerates the session id (call on login / privilege change).
	 *
	 * @return void
	 */
	public static function regenerate(): void {
		if ( PHP_SESSION_ACTIVE === session_status() ) {
			session_regenerate_id( true );
		}
	}

	/**
	 * Gets a session value.
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	public static function get( string $key, $default_value = null ) {
		return $_SESSION[ $key ] ?? $default_value;
	}

	/**
	 * Sets a session value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set( string $key, $value ): void {
		$_SESSION[ $key ] = $value;
	}

	/**
	 * Removes a session value.
	 *
	 * @param string $key Key.
	 * @return void
	 */
	public static function remove( string $key ): void {
		unset( $_SESSION[ $key ] );
	}

	/**
	 * Destroys the session and expires the cookie.
	 *
	 * @return void
	 */
	public static function destroy(): void {
		if ( PHP_SESSION_ACTIVE !== session_status() ) {
			return;
		}
		$_SESSION = array();
		$params   = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			array(
				'expires'  => time() - 42000,
				'path'     => $params['path'],
				'domain'   => $params['domain'],
				'secure'   => $params['secure'],
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		session_destroy();
	}

	/**
	 * Adds a flash message shown on the next page view.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Plain text message (escaped at output).
	 * @return void
	 */
	public static function flash( string $type, string $message ): void {
		$flashes            = (array) ( $_SESSION['_flash'] ?? array() );
		$flashes[]          = array(
			'type'    => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
			'message' => $message,
		);
		$_SESSION['_flash'] = $flashes;
	}

	/**
	 * Returns and clears flash messages.
	 *
	 * @return array<int,array{type:string,message:string}>
	 */
	public static function pull_flashes(): array {
		$flashes = (array) ( $_SESSION['_flash'] ?? array() );
		unset( $_SESSION['_flash'] );
		return $flashes;
	}

	/**
	 * Stores old form input for re-display after a validation error (never passwords).
	 *
	 * @param array $input Sanitized input.
	 * @return void
	 */
	public static function set_old_input( array $input ): void {
		foreach ( array_keys( $input ) as $key ) {
			if ( false !== stripos( (string) $key, 'password' ) || false !== stripos( (string) $key, 'secret' ) || false !== stripos( (string) $key, 'key' ) ) {
				unset( $input[ $key ] );
			}
		}
		$_SESSION['_old'] = $input;
	}

	/**
	 * Returns and clears old input.
	 *
	 * @return array
	 */
	public static function pull_old_input(): array {
		$old = (array) ( $_SESSION['_old'] ?? array() );
		unset( $_SESSION['_old'] );
		return $old;
	}
}
