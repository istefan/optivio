<?php
/**
 * OptiVio — Streaming ZIP writer to a file (for hosts without the PHP zip extension).
 *
 * Each file is compressed on its own (memory = one file at a time) and written directly to
 * disk; the central directory is kept in memory (small). No ZIP64: archives up to 4 GB.
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
 * Streaming ZIP writer.
 */
final class Zip_Stream {

	/**
	 * File handle.
	 *
	 * @var resource
	 */
	private $handle;

	/**
	 * Central directory records.
	 *
	 * @var array<int,string>
	 */
	private array $central = array();

	/**
	 * Bytes written.
	 *
	 * @var int
	 */
	private int $offset = 0;

	/**
	 * Opens the archive.
	 *
	 * @param string $path Target path.
	 * @throws RuntimeException When the file cannot be created.
	 */
	public function __construct( string $path ) {
		$handle = fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			throw new RuntimeException( 'Arhiva nu poate fi creată: ' . $path );
		}
		$this->handle = $handle;
	}

	/**
	 * Adds a file from disk.
	 *
	 * @param string $source Source path.
	 * @param string $name   Name inside the archive.
	 * @return void
	 */
	public function add_file( string $source, string $name ): void {
		$content = (string) file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->add( $name, $content, (int) filemtime( $source ) );
	}

	/**
	 * Adds content.
	 *
	 * @param string $name    Name inside the archive.
	 * @param string $content Content.
	 * @param int    $mtime   Modification time.
	 * @return void
	 */
	public function add( string $name, string $content, int $mtime = 0 ): void {
		$name       = ltrim( str_replace( '\\', '/', $name ), '/' );
		$crc        = crc32( $content );
		$compressed = (string) gzdeflate( $content, 6 );
		$method     = 8;
		if ( strlen( $compressed ) >= strlen( $content ) ) {
			$compressed = $content;
			$method     = 0;
		}
		[ $time, $date ] = self::dos_time( $mtime > 0 ? $mtime : time() );
		$header          = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, strlen( $compressed ), strlen( $content ), strlen( $name ), 0 ) . $name;
		fwrite( $this->handle, $header ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $this->handle, $compressed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		$this->central[] = pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, strlen( $compressed ), strlen( $content ), strlen( $name ), 0, 0, 0, 0, 32, $this->offset ) . $name;
		$this->offset   += strlen( $header ) + strlen( $compressed );
	}

	/**
	 * Writes the central directory and closes the file.
	 *
	 * @return void
	 */
	public function close(): void {
		$central = implode( '', $this->central );
		fwrite( $this->handle, $central . pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $this->central ), count( $this->central ), strlen( $central ), $this->offset, 0 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * DOS time/date.
	 *
	 * @param int $ts Timestamp.
	 * @return array{0:int,1:int}
	 */
	private static function dos_time( int $ts ): array {
		$d = getdate( $ts );
		return array(
			( $d['hours'] << 11 ) | ( $d['minutes'] << 5 ) | intdiv( $d['seconds'], 2 ),
			( ( max( 1980, $d['year'] ) - 1980 ) << 9 ) | ( $d['mon'] << 5 ) | $d['mday'],
		);
	}
}
