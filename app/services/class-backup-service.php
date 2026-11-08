<?php
/**
 * OptiVio — Backups: MySQL export (.sql.gz) and application files (.zip) to Google Drive.
 *
 * - Pure PHP (no mysqldump, no zip extension required): SQL streamed into gzip, files streamed
 *   into a ZIP (ZipArchive when available, otherwise Core\Zip_Stream);
 * - runs as the job `backup.run` (web cron, no cPanel) — manual or scheduled
 *   (daily, twice a week, weekly, monthly, at the preferred hour, in the app timezone);
 * - keeps the last N backups per type in Drive (1, 2, 3, 5, 10, 15, 21, 30); older ones are deleted;
 * - optional local copy of the latest backup in storage/backups (outside the web root, denied by .htaccess).
 * Zero AI (RULES.md R5). Archives contain personal data and config/ (secrets): private storage only.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use DateTimeImmutable;
use OptiVio\Core\Database;
use OptiVio\Core\Job_Queue;
use OptiVio\Core\Logger;
use OptiVio\Core\Zip_Stream;
use OptiVio\Repositories\Settings_Repository;
use PDO;
use RuntimeException;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Backup service.
 */
final class Backup_Service {

	/**
	 * How many backups to keep (per type).
	 */
	public const KEEP_OPTIONS = array( 1, 2, 3, 5, 10, 15, 21, 30 );

	/**
	 * Frequencies => [label, description].
	 */
	public const FREQUENCIES = array(
		'daily'        => array( 'Zilnic', 'În fiecare noapte' ),
		'twice_weekly' => array( 'De două ori pe săptămână', 'Lunea și joia' ),
		'weekly'       => array( 'Săptămânal', 'În fiecare duminică' ),
		'monthly'      => array( 'Lunar', 'Pe data de 1 a fiecărei luni' ),
	);

	/**
	 * Directories never included in the files archive (relative to the app root).
	 */
	private const EXCLUDE_DIRS = array( '.git', 'node_modules', 'storage/backups', 'storage/sessions', 'storage/cache', 'storage/logs', '.idea', '.vscode' );

	/**
	 * Files larger than this are skipped (bytes).
	 */
	private const MAX_FILE = 104857600;

	/**
	 * Current configuration.
	 *
	 * @return array
	 */
	public static function config(): array {
		$keep = (int) Settings_Repository::get( 'backup.keep', 5 );
		return array(
			'enabled'       => (bool) Settings_Repository::get( 'backup.enabled', false ),
			'include_db'    => (bool) Settings_Repository::get( 'backup.include_db', true ),
			'include_files' => (bool) Settings_Repository::get( 'backup.include_files', true ),
			'files_mode'    => 'full' === Settings_Repository::get( 'backup.files_mode', 'recommended' ) ? 'full' : 'recommended',
			'frequency'     => isset( self::FREQUENCIES[ (string) Settings_Repository::get( 'backup.frequency', 'daily' ) ] ) ? (string) Settings_Repository::get( 'backup.frequency', 'daily' ) : 'daily',
			'hour'          => max( 0, min( 23, (int) Settings_Repository::get( 'backup.hour', 3 ) ) ),
			'keep'          => in_array( $keep, self::KEEP_OPTIONS, true ) ? $keep : 5,
			'keep_local'    => (bool) Settings_Repository::get( 'backup.keep_local', false ),
		);
	}

	/**
	 * Private local directory (created with deny rules).
	 *
	 * @return string
	 */
	public static function dir(): string {
		$dir = OPTIVIO_STORAGE . '/backups';
		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0750, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
			file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		}
		return $dir;
	}

	/**
	 * Queues a backup (one log row per type) and returns the row ids.
	 *
	 * @param array    $kinds   db, files.
	 * @param string   $trigger manual|scheduled.
	 * @param int|null $user_id User.
	 * @return array<int,int>
	 */
	public static function queue( array $kinds, string $trigger, ?int $user_id ): array {
		$db  = Database::instance();
		$ids = array();
		foreach ( array_values( array_intersect( array( 'db', 'files' ), $kinds ) ) as $kind ) {
			$ids[] = $db->insert(
				'backups',
				array(
					'kind'           => $kind,
					'trigger_source' => 'scheduled' === $trigger ? 'scheduled' : 'manual',
					'status'         => 'pending',
					'created_by'     => $user_id,
				)
			);
		}
		if ( ! empty( $ids ) ) {
			Job_Queue::dispatch(
				'backup.run',
				array( 'ids' => $ids ),
				array(
					'priority'     => 1,
					'max_attempts' => 1,
					'created_by'   => $user_id,
				)
			);
		}
		return $ids;
	}

	/**
	 * Job handler `backup.run`.
	 *
	 * @param array     $job   Job.
	 * @param Job_Queue $queue Queue.
	 * @return null
	 */
	public static function handle( array $job, Job_Queue $queue ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
		ignore_user_abort( true );
		$payload = is_array( $job['payload'] ?? null ) ? $job['payload'] : (array) json_decode( (string) ( $job['payload'] ?? '{}' ), true );
		foreach ( array_map( 'intval', (array) ( $payload['ids'] ?? array() ) ) as $id ) {
			self::run_one( $id );
		}
		return null;
	}

	/**
	 * Builds, uploads and rotates one backup.
	 *
	 * @param int $id Log row.
	 * @return void
	 */
	public static function run_one( int $id ): void {
		$db  = Database::instance();
		$row = $db->get_row( "SELECT * FROM backups WHERE id = ? AND status = 'pending'", array( $id ) );
		if ( null === $row ) {
			return;
		}
		$db->update( 'backups', array( 'status' => 'running' ), array( 'id' => $id ) );
		$cfg  = self::config();
		$name = self::file_name( (string) $row['kind'] );
		$path = self::dir() . '/' . $name;
		try {
			if ( 'db' === $row['kind'] ) {
				self::dump_database( $path );
				$mime = 'application/gzip';
			} else {
				self::archive_files( $path, $cfg['files_mode'] );
				$mime = 'application/zip';
			}
			$drive_id = null;
			if ( Backup_Drive::is_connected() ) {
				$drive_id = Backup_Drive::upload( $path, $name, $mime );
			} elseif ( ! $cfg['keep_local'] ) {
				throw new RuntimeException( 'Google Drive nu este conectat și copia locală este dezactivată.' );
			}
			$db->update(
				'backups',
				array(
					'status'        => 'ok',
					'file_name'     => $name,
					'size_bytes'    => (int) filesize( $path ),
					'sha256'        => hash_file( 'sha256', $path ),
					'drive_file_id' => $drive_id,
					'local_name'    => $cfg['keep_local'] ? $name : null,
					'finished_at'   => Database::now(),
				),
				array( 'id' => $id )
			);
			if ( ! $cfg['keep_local'] ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			}
			self::rotate( (string) $row['kind'] );
		} catch ( Throwable $e ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			$db->update(
				'backups',
				array(
					'status'      => 'failed',
					'file_name'   => $name,
					'error'       => mb_substr( $e->getMessage(), 0, 500 ),
					'finished_at' => Database::now(),
				),
				array( 'id' => $id )
			);
			Logger::error( 'Backup failed', array( 'exception' => $e ) );
			\OptiVio\Repositories\Notifications_Repository::create(
				array(
					'type'     => 'backup_failed',
					'severity' => 'critical',
					'title'    => 'Backup eșuat (' . ( 'db' === $row['kind'] ? 'bază de date' : 'fișiere' ) . ')',
					'body'     => mb_substr( $e->getMessage(), 0, 300 ),
					'url'      => '/backup',
				)
			);
		}
	}

	/**
	 * Keeps the newest N successful backups of a type; deletes the rest (Drive + local).
	 *
	 * @param string $kind db|files.
	 * @return int Deleted.
	 */
	public static function rotate( string $kind ): int {
		$db   = Database::instance();
		$keep = self::config()['keep'];
		$old  = $db->get_results( "SELECT * FROM backups WHERE kind = ? AND status = 'ok' ORDER BY id DESC LIMIT 1000 OFFSET " . (int) $keep, array( $kind ) );
		foreach ( $old as $row ) {
			self::remove( $row );
		}
		// Only the latest local copy is kept on the server.
		$local = $db->get_results( "SELECT * FROM backups WHERE kind = ? AND status = 'ok' AND local_name IS NOT NULL ORDER BY id DESC LIMIT 1000 OFFSET 1", array( $kind ) );
		foreach ( $local as $row ) {
			self::unlink_local( (string) $row['local_name'] );
			$db->update( 'backups', array( 'local_name' => null ), array( 'id' => (int) $row['id'] ) );
		}
		return count( $old );
	}

	/**
	 * Deletes one backup (Drive file + local copy) and marks the row.
	 *
	 * @param array $row Row.
	 * @return void
	 */
	public static function remove( array $row ): void {
		if ( ! empty( $row['drive_file_id'] ) && Backup_Drive::is_connected() ) {
			Backup_Drive::delete( (string) $row['drive_file_id'] );
		}
		if ( ! empty( $row['local_name'] ) ) {
			self::unlink_local( (string) $row['local_name'] );
		}
		Database::instance()->update(
			'backups',
			array(
				'status'     => 'deleted',
				'local_name' => null,
			),
			array( 'id' => (int) $row['id'] )
		);
	}

	/**
	 * Removes a local file of storage/backups.
	 *
	 * @param string $name File name.
	 * @return void
	 */
	private static function unlink_local( string $name ): void {
		$path = self::dir() . '/' . basename( $name );
		if ( is_file( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Backup file name: {host}-{db|files}-YYYY-MM-DD_HHMMSS-{random}.{sql.gz|zip}.
	 *
	 * @param string $kind db|files.
	 * @return string
	 */
	public static function file_name( string $kind ): string {
		$host = (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) parse_url( ov_url( '/' ), PHP_URL_HOST ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$host = trim( '' !== $host ? $host : 'optivio', '-' );
		return $host . '-' . ( 'db' === $kind ? 'db' : 'files' ) . '-' . gmdate( 'Y-m-d_His' ) . '-' . bin2hex( random_bytes( 3 ) ) . ( 'db' === $kind ? '.sql.gz' : '.zip' );
	}

	/**
	 * Exports the whole database into a gzip SQL file (pure PHP, unbuffered reads).
	 *
	 * @param string $path Target .sql.gz.
	 * @return void
	 * @throws RuntimeException When the file cannot be written.
	 */
	public static function dump_database( string $path ): void {
		$pdo = Database::instance()->pdo();
		$gz  = gzopen( $path, 'wb6' );
		if ( false === $gz ) {
			throw new RuntimeException( 'Fișierul de backup nu poate fi creat.' );
		}
		gzwrite( $gz, "-- OptiVio " . OPTIVIO_VERSION . ' (schema ' . OPTIVIO_SCHEMA_VERSION . ') — backup ' . gmdate( 'c' ) . "\nSET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\n\n" );
		$tables = $pdo->query( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" )->fetchAll( PDO::FETCH_NUM );
		foreach ( $tables as $t ) {
			$table  = (string) $t[0];
			$quoted = '`' . str_replace( '`', '``', $table ) . '`';
			$create = $pdo->query( 'SHOW CREATE TABLE ' . $quoted )->fetch( PDO::FETCH_NUM );
			gzwrite( $gz, "DROP TABLE IF EXISTS {$quoted};\n" . $create[1] . ";\n\n" );

			$pdo->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false );
			try {
				$stmt  = $pdo->query( 'SELECT * FROM ' . $quoted );
				$batch = array();
				$cols  = null;
				while ( $row = $stmt->fetch( PDO::FETCH_ASSOC ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
					if ( null === $cols ) {
						$cols = '(' . implode( ', ', array_map( static fn( $c ) => '`' . str_replace( '`', '``', (string) $c ) . '`', array_keys( $row ) ) ) . ')';
					}
					$values = array();
					foreach ( $row as $value ) {
						if ( null === $value ) {
							$values[] = 'NULL';
						} elseif ( ! mb_check_encoding( (string) $value, 'UTF-8' ) ) {
							$values[] = '0x' . bin2hex( (string) $value );
						} else {
							$values[] = "'" . strtr( (string) $value, array( '\\' => '\\\\', "'" => "\\'", "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "\x1a" => '\\Z' ) ) . "'";
						}
					}
					$batch[] = '(' . implode( ',', $values ) . ')';
					if ( count( $batch ) >= 200 ) {
						gzwrite( $gz, "INSERT INTO {$quoted} {$cols} VALUES\n" . implode( ",\n", $batch ) . ";\n" );
						$batch = array();
					}
				}
				if ( ! empty( $batch ) ) {
					gzwrite( $gz, "INSERT INTO {$quoted} {$cols} VALUES\n" . implode( ",\n", $batch ) . ";\n" );
				}
				$stmt->closeCursor();
			} finally {
				$pdo->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true );
			}
			gzwrite( $gz, "\n" );
		}
		gzwrite( $gz, "SET FOREIGN_KEY_CHECKS = 1;\n" );
		gzclose( $gz );
	}

	/**
	 * Archives the application files.
	 *
	 * @param string $path Target .zip.
	 * @param string $mode recommended (uploads + config) | full (whole app).
	 * @return int Files added.
	 */
	public static function archive_files( string $path, string $mode ): int {
		$root  = str_replace( '\\', '/', OPTIVIO_ROOT );
		$roots = 'full' === $mode ? array( '' ) : array( 'storage/uploads', 'config' );
		$files = array();
		foreach ( $roots as $rel ) {
			$base = '' === $rel ? $root : $root . '/' . $rel;
			if ( ! is_dir( $base ) ) {
				continue;
			}
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}
				$abs  = str_replace( '\\', '/', $file->getPathname() );
				$name = ltrim( substr( $abs, strlen( $root ) ), '/' );
				if ( self::excluded( $name ) || $file->getSize() > self::MAX_FILE ) {
					continue;
				}
				$files[ $name ] = $abs;
			}
		}
		ksort( $files );
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new \ZipArchive();
			if ( true !== $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
				throw new RuntimeException( 'Arhiva ZIP nu poate fi creată.' );
			}
			foreach ( $files as $name => $abs ) {
				$zip->addFile( $abs, $name );
			}
			$zip->close();
		} else {
			$zip = new Zip_Stream( $path );
			foreach ( $files as $name => $abs ) {
				$zip->add_file( $abs, $name );
			}
			$zip->close();
		}
		return count( $files );
	}

	/**
	 * Whether a relative path is excluded from the files archive.
	 *
	 * @param string $name Relative path.
	 * @return bool
	 */
	private static function excluded( string $name ): bool {
		foreach ( self::EXCLUDE_DIRS as $dir ) {
			if ( $name === $dir || 0 === strpos( $name, $dir . '/' ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/\.(zip|gz|log)$/i', $name ) || str_ends_with( $name, '.gitkeep' );
	}

	/**
	 * Most recent scheduled slot (app timezone) not later than now.
	 *
	 * @param array $cfg Config.
	 * @param int   $now Unix time.
	 * @return int Unix time of the slot.
	 */
	public static function last_slot( array $cfg, int $now ): int {
		$tz  = ov_timezone();
		$day = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->setTime( $cfg['hour'], 0 );
		for ( $i = 0; $i < 40; $i++ ) {
			$candidate = $day->modify( '-' . $i . ' day' );
			if ( $candidate->getTimestamp() <= $now && self::matches( $cfg['frequency'], $candidate ) ) {
				return $candidate->getTimestamp();
			}
		}
		return 0;
	}

	/**
	 * Next scheduled slot after now.
	 *
	 * @param array $cfg Config.
	 * @param int   $now Unix time.
	 * @return int
	 */
	public static function next_slot( array $cfg, int $now ): int {
		$day = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( ov_timezone() )->setTime( $cfg['hour'], 0 );
		for ( $i = 0; $i < 40; $i++ ) {
			$candidate = $day->modify( '+' . $i . ' day' );
			if ( $candidate->getTimestamp() > $now && self::matches( $cfg['frequency'], $candidate ) ) {
				return $candidate->getTimestamp();
			}
		}
		return 0;
	}

	/**
	 * Whether a day matches the frequency.
	 *
	 * @param string            $frequency Frequency.
	 * @param DateTimeImmutable $day       Day.
	 * @return bool
	 */
	private static function matches( string $frequency, DateTimeImmutable $day ): bool {
		switch ( $frequency ) {
			case 'twice_weekly':
				return in_array( (int) $day->format( 'N' ), array( 1, 4 ), true );
			case 'weekly':
				return 7 === (int) $day->format( 'N' );
			case 'monthly':
				return 1 === (int) $day->format( 'j' );
			default:
				return true;
		}
	}

	/**
	 * Called on every cron tick: queues the scheduled backup when its slot has passed.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		$cfg = self::config();
		if ( ! $cfg['enabled'] || ( ! $cfg['include_db'] && ! $cfg['include_files'] ) ) {
			return;
		}
		$slot = self::last_slot( $cfg, time() );
		if ( 0 === $slot || $slot <= (int) Settings_Repository::get( 'backup.last_slot', 0 ) ) {
			return;
		}
		// The first time the schedule is enabled, the past slot is only recorded.
		if ( 0 === (int) Settings_Repository::get( 'backup.last_slot', 0 ) && $slot < time() - 6 * 3600 ) {
			Settings_Repository::set( 'backup.last_slot', $slot );
			return;
		}
		Settings_Repository::set( 'backup.last_slot', $slot );
		$kinds = array();
		if ( $cfg['include_db'] ) {
			$kinds[] = 'db';
		}
		if ( $cfg['include_files'] ) {
			$kinds[] = 'files';
		}
		self::queue( $kinds, 'scheduled', null );
	}

	/**
	 * Backup log.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function log( int $limit = 60 ): array {
		return Database::instance()->get_results( "SELECT b.*, u.display_name AS author FROM backups b LEFT JOIN users u ON u.id = b.created_by WHERE b.status <> 'deleted' ORDER BY b.id DESC LIMIT " . (int) $limit );
	}

	/**
	 * Last successful backup time.
	 *
	 * @return string|null
	 */
	public static function last_ok(): ?string {
		$v = Database::instance()->get_var( "SELECT MAX(finished_at) FROM backups WHERE status IN ('ok')" );
		return null !== $v ? (string) $v : null;
	}
}
