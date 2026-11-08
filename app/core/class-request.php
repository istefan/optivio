<?php
/**
 * OptiVio — HTTP request abstraction.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Immutable view over the current HTTP request.
 *
 * Raw superglobals are read ONLY here; values must still be sanitized by the caller.
 */
final class Request {

	/**
	 * Route parameters set by the router.
	 *
	 * @var array<string,string>
	 */
	private array $params = array();

	/**
	 * Captured request (singleton for the current request).
	 *
	 * @var Request|null
	 */
	private static ?Request $current = null;

	/**
	 * Returns the current request.
	 *
	 * @return Request
	 */
	public static function current(): Request {
		if ( null === self::$current ) {
			self::$current = new self();
		}
		return self::$current;
	}

	/**
	 * HTTP method (upper case).
	 *
	 * @return string
	 */
	public function method(): string {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( $_SERVER['REQUEST_METHOD'] ) ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		return in_array( $method, array( 'GET', 'POST', 'HEAD' ), true ) ? $method : 'GET';
	}

	/**
	 * Whether the method changes state.
	 *
	 * @return bool
	 */
	public function is_post(): bool {
		return 'POST' === $this->method();
	}

	/**
	 * Request path relative to the application base path, always starting with "/".
	 *
	 * @return string
	 */
	public function path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed and normalised below.
		$path = (string) parse_url( $uri, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path = rawurldecode( $path );
		$base = Config::base_path();

		if ( '' !== $base && 0 === strpos( $path, $base ) ) {
			$path = substr( $path, strlen( $base ) );
		}
		if ( 0 === strpos( $path, '/public/' ) ) {
			$path = substr( $path, 7 );
		}
		if ( '/index.php' === $path || 0 === strpos( $path, '/index.php/' ) ) {
			$path = substr( $path, 10 );
		}

		$path = '/' . trim( $path, '/' );
		return preg_match( '#^[A-Za-z0-9/_\-.]*$#', $path ) ? $path : '/__invalid__';
	}

	/**
	 * Raw GET value (unsanitized — sanitize at the call site).
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	public function query( string $key, $default_value = null ) {
		return $_GET[ $key ] ?? $default_value; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- Caller sanitizes; GET never changes state.
	}

	/**
	 * Raw POST value (unsanitized — sanitize at the call site). CSRF is verified by the Router.
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	public function input( string $key, $default_value = null ) {
		return $_POST[ $key ] ?? $default_value; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- CSRF verified in Router; caller sanitizes.
	}

	/**
	 * Whether a POST key exists.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public function has_input( string $key ): bool {
		return isset( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Returns a request header value.
	 *
	 * @param string $name Header name (e.g. "X-CSRF-Token").
	 * @return string
	 */
	public function header( string $name ): string {
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) );
		return isset( $_SERVER[ $key ] ) ? trim( (string) $_SERVER[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared/validated by callers.
	}

	/**
	 * Raw request body (machine endpoints only), capped to a maximum size.
	 *
	 * @param int $max_bytes Maximum bytes accepted.
	 * @return string|null Null when the body exceeds the limit.
	 */
	public function raw_body( int $max_bytes = 524288 ): ?string {
		static $body = null;
		if ( null === $body ) {
			$stream = fopen( 'php://input', 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$body   = false === $stream ? '' : (string) stream_get_contents( $stream, $max_bytes + 1 );
			if ( false !== $stream ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
		return strlen( $body ) > $max_bytes ? null : $body;
	}

	/**
	 * Uploaded file entry (validated by the caller).
	 *
	 * @param string $key Field name.
	 * @return array|null
	 */
	public function file( string $key ): ?array {
		if ( ! isset( $_FILES[ $key ] ) || ! is_array( $_FILES[ $key ] ) || is_array( $_FILES[ $key ]['name'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return null;
		}
		return $_FILES[ $key ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Whether the request expects JSON (AJAX).
	 *
	 * @return bool
	 */
	public function wants_json(): bool {
		return 'XMLHttpRequest' === $this->header( 'X-Requested-With' )
			|| false !== strpos( $this->header( 'Accept' ), 'application/json' );
	}

	/**
	 * Client IP. X-Forwarded-For is honoured only from configured trusted proxies.
	 *
	 * @return string
	 */
	public function ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below.
		$remote = false !== filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';

		$trusted = (array) Config::get( 'trusted_proxies', array() );
		if ( in_array( $remote, $trusted, true ) ) {
			$forwarded = explode( ',', $this->header( 'X-Forwarded-For' ) );
			$candidate = trim( (string) end( $forwarded ) );
			if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				return $candidate;
			}
		}
		return $remote;
	}

	/**
	 * Client IP in binary form (for VARBINARY(16) columns).
	 *
	 * @return string|null
	 */
	public function ip_binary(): ?string {
		$packed = @inet_pton( $this->ip() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $packed ? null : $packed;
	}

	/**
	 * Truncated user agent.
	 *
	 * @return string
	 */
	public function user_agent(): string {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		return mb_substr( $ua, 0, 255 );
	}

	/**
	 * Whether the connection is HTTPS.
	 *
	 * @return bool
	 */
	public function is_https(): bool {
		if ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( (string) $_SERVER['HTTPS'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return true;
		}
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( in_array( $remote, (array) Config::get( 'trusted_proxies', array() ), true ) ) {
			return 'https' === strtolower( $this->header( 'X-Forwarded-Proto' ) );
		}
		return false;
	}

	/**
	 * Sets route parameters (router only).
	 *
	 * @param array<string,string> $params Params.
	 * @return void
	 */
	public function set_params( array $params ): void {
		$this->params = $params;
	}

	/**
	 * Returns a numeric route parameter.
	 *
	 * @param string $name Name.
	 * @return int
	 */
	public function param_int( string $name ): int {
		return absint( $this->params[ $name ] ?? 0 );
	}

	/**
	 * Returns a slug-like route parameter (already restricted to [a-z0-9_-] by the router).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	public function param_key( string $name ): string {
		return sanitize_key( $this->params[ $name ] ?? '' );
	}
}
