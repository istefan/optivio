<?php
/**
 * OptiVio — Minimal ZIP writer (deflate) for hosts without ext/zip.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * In-memory ZIP builder (PKZIP 2.0, UTF-8 names).
 */
final class Zip_Writer {

	/**
	 * Local file records.
	 *
	 * @var string
	 */
	private string $data = '';

	/**
	 * Central directory records.
	 *
	 * @var array<int,string>
	 */
	private array $central = array();

	/**
	 * Adds a file.
	 *
	 * @param string $name    Path inside the archive (forward slashes).
	 * @param string $content Content.
	 * @return void
	 */
	public function add( string $name, string $content ): void {
		$name       = ltrim( str_replace( '\\', '/', $name ), '/' );
		$crc        = crc32( $content );
		$compressed = function_exists( 'gzdeflate' ) ? (string) gzdeflate( $content, 6 ) : $content;
		$method     = function_exists( 'gzdeflate' ) ? 8 : 0;
		[ $time, $date ] = self::dos_time( time() );
		$offset     = strlen( $this->data );

		$this->data .= pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, $crc, strlen( $compressed ), strlen( $content ), strlen( $name ), 0 )
			. $name . $compressed;

		$this->central[] = pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $method, $time, $date, $crc, strlen( $compressed ), strlen( $content ), strlen( $name ), 0, 0, 0, 0, 32, $offset )
			. $name;
	}

	/**
	 * Returns the archive bytes.
	 *
	 * @return string
	 */
	public function contents(): string {
		$central = implode( '', $this->central );
		return $this->data . $central
			. pack( 'VvvvvVVv', 0x06054b50, 0, 0, count( $this->central ), count( $this->central ), strlen( $central ), strlen( $this->data ), 0 );
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
			( ( $d['year'] - 1980 ) << 9 ) | ( $d['mon'] << 5 ) | $d['mday'],
		);
	}
}
