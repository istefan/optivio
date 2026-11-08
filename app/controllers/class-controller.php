<?php
/**
 * OptiVio — Base controller.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Request;
use OptiVio\Core\Response;
use OptiVio\Core\Session;
use OptiVio\Core\View;
use OptiVio\Security\Auth;
use OptiVio\Services\Layout_Context;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Shared controller helpers.
 */
abstract class Controller {

	/**
	 * Current request.
	 *
	 * @var Request
	 */
	protected Request $request;

	/**
	 * Constructor.
	 *
	 * @param Request $request Request.
	 */
	public function __construct( Request $request ) {
		$this->request = $request;
	}

	/**
	 * Renders a page inside the authenticated layout.
	 *
	 * @param string $template Template.
	 * @param array  $data     Data (must include page_title, page_subtitle).
	 * @param int    $status   HTTP status.
	 * @return void
	 */
	protected function render( string $template, array $data = array(), int $status = 200 ): void {
		Layout_Context::share();
		$data['flashes'] = Session::pull_flashes();
		$data['old']     = $data['old'] ?? Session::pull_old_input();
		View::render( $template, $data, 'app', $status );
	}

	/**
	 * Returns the current user (guaranteed on authenticated routes).
	 *
	 * @return array
	 */
	protected function user(): array {
		return (array) Auth::user();
	}

	/**
	 * Flashes a message and redirects.
	 *
	 * @param string $path    Path.
	 * @param string $type    Flash type.
	 * @param string $message Message.
	 * @return never
	 */
	protected function redirect_with( string $path, string $type, string $message ): never {
		Session::flash( $type, $message );
		Response::redirect( $path );
	}

	/**
	 * Redirects back to a form with validation errors and old input.
	 *
	 * @param string $path   Path.
	 * @param array  $errors Field errors.
	 * @param array  $old    Sanitized input.
	 * @return never
	 */
	protected function back_with_errors( string $path, array $errors, array $old ): never {
		Session::set( '_errors', $errors );
		Session::set_old_input( $old );
		Session::flash( 'error', 'Verificați câmpurile marcate.' );
		Response::redirect( $path );
	}

	/**
	 * Returns and clears validation errors stored in session.
	 *
	 * @return array<string,string>
	 */
	protected function pull_errors(): array {
		$errors = (array) Session::get( '_errors', array() );
		Session::remove( '_errors' );
		return $errors;
	}

	/**
	 * Sanitized text input helper.
	 *
	 * @param string $key Key.
	 * @param int    $max Max length.
	 * @return string
	 */
	protected function text( string $key, int $max = 255 ): string {
		return mb_substr( sanitize_text_field( $this->request->input( $key, '' ) ), 0, $max );
	}

	/**
	 * Sanitized multi-line input helper.
	 *
	 * @param string $key Key.
	 * @param int    $max Max length.
	 * @return string
	 */
	protected function textarea( string $key, int $max = 5000 ): string {
		return mb_substr( sanitize_textarea_field( $this->request->input( $key, '' ) ), 0, $max );
	}
}
