<?php
/**
 * OptiVio — File logger (storage/logs, outside the web root).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Simple daily-rotated JSON-lines logger.
 */
final class Logger {

	/**
	 * Logs an error.
	 *
	 * @param string $message Message.
	 * @param array  $context Context (exceptions are expanded; secrets must never be passed).
	 * @return void
	 */
	public static function error( string $message, array $context = array() ): void {
		self::write( 'error', $message, $context );
	}

	/**
	 * Logs an info message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public static function info( string $message, array $context = array() ): void {
		self::write( 'info', $message, $context );
	}

	/**
	 * Writes a log line.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	private static function write( string $level, string $message, array $context ): void {
		foreach ( $context as $key => $value ) {
			if ( $value instanceof Throwable ) {
				$context[ $key ] = array(
					'class'   => get_class( $value ),
					'message' => $value->getMessage(),
					'file'    => $value->getFile() . ':' . $value->getLine(),
					'trace'   => array_slice( explode( "\n", $value->getTraceAsString() ), 0, 12 ),
				);
			}
		}

		$line = json_encode(
			array(
				'time'    => gmdate( 'c' ),
				'level'   => $level,
				'message' => $message,
				'context' => $context,
			),
			JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);

		$file = OPTIVIO_STORAGE . '/logs/app-' . gmdate( 'Y-m-d' ) . '.log';
		@file_put_contents( $file, $line . PHP_EOL, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Logging must never break the request.
	}
}
