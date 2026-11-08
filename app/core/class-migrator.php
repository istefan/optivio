<?php
/**
 * OptiVio — Automatic database upgrades after a code deploy (no script, no config change).
 *
 * On the first request after new files are uploaded, the code schema version
 * (OPTIVIO_SCHEMA_VERSION) is compared with the highest version in `schema_migrations`.
 * Missing `database/upgrades/<version>.sql` files are applied in order, under a MySQL lock
 * (only one request migrates, the others wait). Statements that were already applied
 * (duplicate column / key / table) are skipped, so an interrupted upgrade can be resumed.
 * A marker file in storage/cache makes the check free on every later request.
 * An empty database is installed from schema.sql.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use PDOException;
use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Schema migrator.
 */
final class Migrator {

	private const LOCK = 'optivio_migrate';

	/**
	 * MySQL errors meaning "already applied": table/column/key/constraint exists, unknown column to drop, duplicate row.
	 */
	private const TOLERATED = array( 1050, 1060, 1061, 1062, 1022, 1091, 1826 );

	/**
	 * Ensures the database matches the code. Cheap after the first successful check.
	 *
	 * @return void
	 * @throws RuntimeException When an upgrade fails.
	 */
	public static function ensure(): void {
		$marker = self::marker();
		if ( is_file( $marker ) ) {
			return;
		}
		$db      = Database::instance();
		$current = self::current();
		if ( null === $current ) {
			if ( 0 === (int) $db->get_var( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()' ) ) {
				self::with_lock( static fn() => self::install() );
				self::write_marker();
			}
			return;
		}
		if ( version_compare( $current, OPTIVIO_SCHEMA_VERSION, '>=' ) ) {
			self::write_marker();
			return;
		}
		self::with_lock(
			static function (): void {
				$current = (string) self::current();
				foreach ( self::pending( $current ) as $version => $file ) {
					self::apply( $version, $file );
				}
			}
		);
		self::write_marker();
	}

	/**
	 * Highest applied schema version (null when the table does not exist).
	 *
	 * @return string|null
	 */
	public static function current(): ?string {
		try {
			$versions = Database::instance()->get_col( 'SELECT version FROM schema_migrations' );
		} catch ( PDOException $e ) {
			return null;
		}
		usort( $versions, 'version_compare' );
		return empty( $versions ) ? '0.0.0' : (string) end( $versions );
	}

	/**
	 * Upgrade files newer than a version and not newer than the code (version => path).
	 *
	 * @param string $current Current version.
	 * @return array<string,string>
	 */
	public static function pending( string $current ): array {
		$out = array();
		foreach ( (array) glob( OPTIVIO_ROOT . '/database/upgrades/*.sql' ) as $file ) {
			$version = basename( (string) $file, '.sql' );
			if ( preg_match( '/^\d+\.\d+\.\d+$/', $version ) && version_compare( $version, $current, '>' ) && version_compare( $version, OPTIVIO_SCHEMA_VERSION, '<=' ) ) {
				$out[ $version ] = (string) $file;
			}
		}
		uksort( $out, 'version_compare' );
		return $out;
	}

	/**
	 * Applies one upgrade file.
	 *
	 * @param string $version Version.
	 * @param string $file    Path.
	 * @return void
	 * @throws RuntimeException On a non-tolerated SQL error.
	 */
	private static function apply( string $version, string $file ): void {
		$pdo = Database::instance()->pdo();
		foreach ( self::statements( (string) file_get_contents( $file ) ) as $sql ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			try {
				$pdo->exec( $sql );
			} catch ( PDOException $e ) {
				$code = (int) ( $e->errorInfo[1] ?? 0 );
				if ( ! in_array( $code, self::TOLERATED, true ) ) {
					Logger::error(
						'Schema upgrade failed',
						array(
							'version' => $version,
							'error'   => $e->getMessage(),
						)
					);
					throw new RuntimeException( 'Actualizarea bazei de date la versiunea ' . $version . ' a eșuat: ' . $e->getMessage() );
				}
			}
		}
		$pdo->prepare( 'INSERT IGNORE INTO schema_migrations (version, notes) VALUES (?, ?)' )->execute( array( $version, 'Aplicat automat la deploy' ) );
		Logger::info( 'Schema upgraded', array( 'version' => $version ) );
	}

	/**
	 * Installs schema.sql into an empty database.
	 *
	 * @return void
	 */
	private static function install(): void {
		if ( null !== self::current() ) {
			return;
		}
		$pdo = Database::instance()->pdo();
		foreach ( self::statements( (string) file_get_contents( OPTIVIO_ROOT . '/schema.sql' ) ) as $sql ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$pdo->exec( $sql );
		}
		Logger::info( 'Schema installed', array( 'version' => OPTIVIO_SCHEMA_VERSION ) );
	}

	/**
	 * Splits a SQL file into statements (comments removed, quotes respected).
	 *
	 * @param string $sql SQL.
	 * @return array<int,string>
	 */
	public static function statements( string $sql ): array {
		$out   = array();
		$buf   = '';
		$quote = '';
		$len   = strlen( $sql );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $sql[ $i ];
			if ( '' === $quote && '-' === $c && '-' === ( $sql[ $i + 1 ] ?? '' ) ) {
				$end = strpos( $sql, "\n", $i );
				$i   = false === $end ? $len : $end;
				$buf .= "\n";
				continue;
			}
			if ( '' !== $quote ) {
				$buf .= $c;
				if ( '\\' === $c && $i + 1 < $len ) {
					$buf .= $sql[ ++$i ];
				} elseif ( $c === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( "'" === $c || '"' === $c || '`' === $c ) {
				$quote = $c;
				$buf  .= $c;
				continue;
			}
			if ( ';' === $c ) {
				if ( '' !== trim( $buf ) ) {
					$out[] = trim( $buf );
				}
				$buf = '';
				continue;
			}
			$buf .= $c;
		}
		if ( '' !== trim( $buf ) ) {
			$out[] = trim( $buf );
		}
		return $out;
	}

	/**
	 * Runs a callback under the migration lock (waits up to 60 s for another migrating request).
	 *
	 * @param callable $callback Callback.
	 * @return void
	 * @throws RuntimeException When the lock cannot be taken.
	 */
	private static function with_lock( callable $callback ): void {
		$db = Database::instance();
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
		if ( 1 !== (int) $db->get_var( 'SELECT GET_LOCK(?, 60)', array( self::LOCK ) ) ) {
			throw new RuntimeException( 'Baza de date este în curs de actualizare. Reîncărcați pagina în câteva secunde.' );
		}
		try {
			$callback();
		} finally {
			$db->get_var( 'SELECT RELEASE_LOCK(?)', array( self::LOCK ) );
		}
	}

	/**
	 * Marker file path for the current code version.
	 *
	 * @return string
	 */
	private static function marker(): string {
		return OPTIVIO_STORAGE . '/cache/schema-' . OPTIVIO_SCHEMA_VERSION . '.ok';
	}

	/**
	 * Writes the marker (best effort).
	 *
	 * @return void
	 */
	private static function write_marker(): void {
		$dir = OPTIVIO_STORAGE . '/cache';
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0775, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}
		@file_put_contents( self::marker(), gmdate( 'c' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
	}
}
