<?php
/**
 * OptiVio — Application kernel (HTTP entry).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use OptiVio\Security\Auth;
use OptiVio\Services\Layout_Context;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Bootstraps a web request.
 */
final class App {

	/**
	 * Handles the current HTTP request.
	 *
	 * @return void
	 */
	public static function run_http(): void {
		$request = Request::current();

		Response::security_headers( $request->is_https() );

		if ( ! Config::is_loaded() ) {
			Response::text( "OptiVio nu este configurat.\nCopiați config/config.sample.php în config/config.php și completați valorile.", 503 );
		}

		if ( Config::get( 'force_https', false ) && ! $request->is_https() ) {
			$target = rtrim( (string) Config::get( 'app_url', '' ), '/' ) . $request->path();
			if ( 0 === strpos( $target, 'https://' ) ) {
				header( 'Location: ' . $target, true, 301 );
				exit;
			}
		}

		try {
			// Machine endpoints (plugin API, HTTP cron) are stateless: no session file, no cookie.
			if ( ! self::is_machine_path( $request->path() ) ) {
				Session::start( $request->is_https() );
			}

			$router = new Router();
			$routes = require OPTIVIO_APP . '/routes.php';
			$routes( $router );

			// Layout data (menu, businesses switcher, notifications) is resolved lazily for authenticated pages.
			View::share( 'request_path', $request->path() );

			$router->dispatch( $request );
		} catch ( Http_Exception $e ) {
			self::render_error( $request, $e->get_status(), $e->getMessage() );
		} catch ( Throwable $e ) {
			Logger::error( 'Unhandled exception', array( 'exception' => $e ) );
			$message = Config::is_dev() ? get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : 'A apărut o eroare internă. Incidentul a fost înregistrat.';
			self::render_error( $request, 500, $message );
		}
	}

	/**
	 * Whether the path is a stateless machine endpoint.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public static function is_machine_path( string $path ): bool {
		return 0 === strpos( $path, '/api/connector/' ) || '/cron/run' === $path;
	}

	/**
	 * Renders an error page or JSON error.
	 *
	 * @param Request $request Request.
	 * @param int     $status  HTTP status.
	 * @param string  $message Public message.
	 * @return void
	 */
	private static function render_error( Request $request, int $status, string $message ): void {
		if ( $request->wants_json() || self::is_machine_path( $request->path() ) ) {
			Response::json( array( 'error' => $message ), $status );
		}

		$layout = 'auth';
		try {
			if ( Auth::check() ) {
				Layout_Context::share();
				$layout = 'app';
			}
		} catch ( Throwable $e ) {
			$layout = 'auth';
		}

		try {
			View::render(
				'errors/error',
				array(
					'page_title'    => (string) $status,
					'page_subtitle' => '',
					'status'        => $status,
					'message'       => $message,
				),
				$layout,
				$status
			);
		} catch ( Throwable $e ) {
			Response::text( $message, $status );
		}
	}
}
