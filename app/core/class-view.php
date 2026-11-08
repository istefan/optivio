<?php
/**
 * OptiVio — Minimal PHP template renderer.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Renders templates from app/views. Templates receive a single $view array and
 * MUST escape every dynamic value at output (late escaping).
 */
final class View {

	/**
	 * Data shared with every template (current user, menu state, ...).
	 *
	 * @var array<string,mixed>
	 */
	private static array $shared = array();

	/**
	 * Shares a value with all templates.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function share( string $key, $value ): void {
		self::$shared[ $key ] = $value;
	}

	/**
	 * Returns shared data.
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	public static function shared( string $key, $default_value = null ) {
		return self::$shared[ $key ] ?? $default_value;
	}

	/**
	 * Renders a template inside a layout and echoes it.
	 *
	 * @param string      $template Template name (e.g. "dashboard/index").
	 * @param array       $data     Template data.
	 * @param string|null $layout   Layout name (e.g. "app"), null for none.
	 * @param int         $status   HTTP status.
	 * @return void
	 */
	public static function render( string $template, array $data = array(), ?string $layout = 'app', int $status = 200 ): void {
		http_response_code( $status );
		header( 'Content-Type: text/html; charset=utf-8' );

		$view    = array_merge( self::$shared, $data );
		$content = self::capture( $template, $view );

		if ( null === $layout ) {
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped inside the template.
			return;
		}

		$view['content'] = $content;
		echo self::capture( 'layouts/' . $layout, $view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Layout escapes its own values.
	}

	/**
	 * Renders a partial and returns the HTML.
	 *
	 * @param string $template Template name.
	 * @param array  $view     Data.
	 * @return string
	 * @throws RuntimeException When the template is missing.
	 */
	public static function capture( string $template, array $view = array() ): string {
		if ( ! preg_match( '#^[a-z0-9_\-/]+$#', $template ) ) {
			throw new RuntimeException( 'Invalid template name.' );
		}
		$file = OPTIVIO_APP . '/views/' . $template . '.php';
		if ( ! is_readable( $file ) ) {
			throw new RuntimeException( 'Template not found: ' . $template );
		}

		ob_start();
		( static function ( string $ov_template_file, array $view ): void {
			require $ov_template_file;
		} )( $file, array_merge( self::$shared, $view ) );
		return (string) ob_get_clean();
	}

	/**
	 * Includes a partial directly (inside a template).
	 *
	 * @param string $template Template name.
	 * @param array  $view     Data.
	 * @return void
	 */
	public static function partial( string $template, array $view = array() ): void {
		echo self::capture( $template, $view ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Partial escapes its own values.
	}
}
