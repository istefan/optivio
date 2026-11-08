<?php
/**
 * OptiVio — Router with auth / role / CSRF middleware.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use OptiVio\Security\Auth;
use OptiVio\Security\Gate;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Route table + dispatcher.
 *
 * Route options:
 *  - auth  (bool, default true)  Requires an authenticated user.
 *  - guest (bool, default false) Only for unauthenticated visitors (login, setup).
 *  - role  (string|null)         Required role (e.g. "super_admin").
 *  - csrf  (bool, default true)  Enforced for POST requests.
 */
final class Router {

	/**
	 * Registered routes.
	 *
	 * @var array<int,array>
	 */
	private array $routes = array();

	/**
	 * Registers a route.
	 *
	 * @param string   $method  GET|POST.
	 * @param string   $pattern Path pattern, e.g. "/users/{id}/edit".
	 * @param callable $handler array( Controller::class, 'method' ).
	 * @param array    $options Options.
	 * @return void
	 */
	public function add( string $method, string $pattern, $handler, array $options = array() ): void {
		$regex = preg_replace_callback(
			'#\{([a-z_]+)\}#',
			static function ( array $m ): string {
				return 'id' === $m[1] || str_ends_with( $m[1], '_id' )
					? '(?P<' . $m[1] . '>[1-9][0-9]{0,9})'
					: '(?P<' . $m[1] . '>[a-z0-9_\-]{1,64})';
			},
			$pattern
		);

		$this->routes[] = array(
			'method'  => strtoupper( $method ),
			'regex'   => '#^' . $regex . '$#',
			'handler' => $handler,
			'options' => array_merge(
				array(
					'auth'  => true,
					'guest' => false,
					'role'  => null,
					'csrf'  => true,
				),
				$options
			),
		);
	}

	/**
	 * Dispatches the request.
	 *
	 * @param Request $request Request.
	 * @return void
	 * @throws Http_Exception On 404/405/403/419.
	 */
	public function dispatch( Request $request ): void {
		$path            = $request->path();
		$method          = 'HEAD' === $request->method() ? 'GET' : $request->method();
		$method_mismatch = false;

		foreach ( $this->routes as $route ) {
			if ( ! preg_match( $route['regex'], $path, $matches ) ) {
				continue;
			}
			if ( $route['method'] !== $method ) {
				$method_mismatch = true;
				continue;
			}

			$params = array();
			foreach ( $matches as $key => $value ) {
				if ( is_string( $key ) ) {
					$params[ $key ] = $value;
				}
			}
			$request->set_params( $params );

			$this->run_middleware( $request, $route['options'] );

			[ $class, $action ] = $route['handler'];
			$controller         = new $class( $request );
			$controller->$action();
			return;
		}

		if ( $method_mismatch ) {
			throw new Http_Exception( 405, 'Metodă HTTP nepermisă.' );
		}
		throw Http_Exception::not_found();
	}

	/**
	 * Applies auth, guest, role and CSRF checks.
	 *
	 * @param Request $request Request.
	 * @param array   $options Route options.
	 * @return void
	 * @throws Http_Exception When a check fails.
	 */
	private function run_middleware( Request $request, array $options ): void {
		if ( $request->is_post() && $options['csrf'] && ! Csrf::verify_request( $request ) ) {
			throw new Http_Exception( 419, 'Sesiunea formularului a expirat. Reîncărcați pagina și încercați din nou.' );
		}

		if ( $options['guest'] && Auth::check() ) {
			Response::redirect( '/' );
		}

		if ( $options['auth'] ) {
			if ( ! Auth::check() ) {
				if ( $request->wants_json() ) {
					Response::json( array( 'error' => 'unauthenticated' ), 401 );
				}
				if ( 'GET' === $request->method() ) {
					Session::set( '_intended', $request->path() );
				}
				Response::redirect( '/login' );
			}

			Response::no_cache();

			$user = Auth::user();
			if ( null !== $user && ! empty( $user['must_change_password'] ) && ! in_array( $request->path(), array( '/profile', '/profile/password', '/logout' ), true ) ) {
				Session::flash( 'warning', 'Trebuie să vă schimbați parola înainte de a continua.' );
				Response::redirect( '/profile' );
			}

			if ( 'super_admin' === $options['role'] && ! Gate::is_super_admin( $user ) ) {
				throw Http_Exception::forbidden();
			}
		}
	}
}
