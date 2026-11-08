<?php
/**
 * OptiVio — Backup page (Super Admin): Google Drive connection, content & schedule, run now, log.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Controllers;

use OptiVio\Core\Database;
use OptiVio\Core\Http_Exception;
use OptiVio\Core\Response;
use OptiVio\Google\Google_Oauth;
use OptiVio\Repositories\Settings_Repository;
use OptiVio\Security\Audit_Log;
use OptiVio\Services\Backup_Drive;
use OptiVio\Services\Backup_Service;
use OptiVio\Services\Web_Cron;
use Throwable;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Backup controller.
 */
final class Backup_Controller extends Controller {

	/**
	 * Page.
	 *
	 * @return void
	 */
	public function index(): void {
		$cfg = Backup_Service::config();
		$this->render(
			'backup/index',
			array(
				'page_title'    => 'Backup Google Drive',
				'page_subtitle' => 'Baza de date și fișierele aplicației, automat, în Google Drive',
				'config'        => $cfg,
				'connected'     => Backup_Drive::is_connected(),
				'email'         => Backup_Drive::email(),
				'folder_name'   => Backup_Drive::folder_name(),
				'folder_link'   => Backup_Drive::link(),
				'oauth_ready'   => Google_Oauth::is_configured(),
				'redirect_uri'  => Google_Oauth::redirect_uri(),
				'log'           => Backup_Service::log(),
				'last_ok'       => Backup_Service::last_ok(),
				'next'          => $cfg['enabled'] ? Backup_Service::next_slot( $cfg, time() ) : 0,
				'has_zip'       => class_exists( 'ZipArchive' ),
			)
		);
	}

	/**
	 * Saves content & schedule.
	 *
	 * @return void
	 */
	public function save(): void {
		$keep  = absint( $this->request->input( 'keep', 5 ) );
		$freq  = sanitize_key( (string) $this->request->input( 'frequency', 'daily' ) );
		$hour  = absint( $this->request->input( 'hour', 3 ) );
		$items = array(
			'backup.enabled'       => '1' === (string) $this->request->input( 'enabled', '0' ),
			'backup.include_db'    => '1' === (string) $this->request->input( 'include_db', '0' ),
			'backup.include_files' => '1' === (string) $this->request->input( 'include_files', '0' ),
			'backup.files_mode'    => 'full' === $this->request->input( 'files_mode', 'recommended' ) ? 'full' : 'recommended',
			'backup.frequency'     => isset( Backup_Service::FREQUENCIES[ $freq ] ) ? $freq : 'daily',
			'backup.hour'          => min( 23, $hour ),
			'backup.keep'          => in_array( $keep, Backup_Service::KEEP_OPTIONS, true ) ? $keep : 5,
			'backup.keep_local'    => '1' === (string) $this->request->input( 'keep_local', '0' ),
		);
		$before = Backup_Service::config();
		foreach ( $items as $key => $value ) {
			Settings_Repository::set( $key, $value, (int) $this->user()['id'] );
		}
		// Changing the schedule restarts it from the next slot.
		if ( $before['frequency'] !== $items['backup.frequency'] || $before['hour'] !== $items['backup.hour'] || ( ! $before['enabled'] && $items['backup.enabled'] ) ) {
			Settings_Repository::set( 'backup.last_slot', 0 );
		}
		Audit_Log::record( 'backup.settings', 'settings', null, array_map( static fn( $v ) => is_bool( $v ) ? (int) $v : $v, $items ) );
		$this->redirect_with( '/backup', 'success', 'Configurarea backup-ului a fost salvată.' );
	}

	/**
	 * Verifies / recreates the Drive folder (and renames the target folder).
	 *
	 * @return void
	 */
	public function folder(): void {
		$name = trim( preg_replace( '/[^\p{L}\p{N} ._\-]/u', '', $this->text( 'folder_name', 100 ) ) ?? '' );
		if ( '' !== $name && $name !== Backup_Drive::folder_name() ) {
			Settings_Repository::set( 'backup.folder_name', $name, (int) $this->user()['id'] );
			Settings_Repository::set( 'backup.drive_folder_id', '', (int) $this->user()['id'] );
		}
		try {
			[ $ok, $message ] = Backup_Drive::test();
		} catch ( Throwable $e ) {
			$ok      = false;
			$message = $e->getMessage();
		}
		$this->redirect_with( '/backup', $ok ? 'success' : 'error', $message );
	}

	/**
	 * Disconnects Google Drive.
	 *
	 * @return void
	 */
	public function disconnect(): void {
		Backup_Drive::disconnect();
		Audit_Log::record( 'backup.drive_disconnect', 'settings', null, array() );
		$this->redirect_with( '/backup', 'success', 'Google Drive a fost deconectat. Fișierele existente rămân în Drive.' );
	}

	/**
	 * Queues a backup now and starts the job queue in the background.
	 *
	 * @return void
	 */
	public function run(): void {
		$scope = 'db' === $this->request->input( 'scope', 'all' ) ? array( 'db' ) : array( 'db', 'files' );
		if ( ! Backup_Drive::is_connected() && ! Backup_Service::config()['keep_local'] ) {
			$this->redirect_with( '/backup', 'error', 'Conectați Google Drive sau activați copia locală înainte de a rula un backup.' );
		}
		$ids = Backup_Service::queue( $scope, 'manual', (int) $this->user()['id'] );
		Audit_Log::record( 'backup.run', 'backup', $ids[0] ?? null, array( 'scope' => $scope ) );
		register_shutdown_function( array( Web_Cron::class, 'spawn' ) );
		$this->redirect_with( '/backup', 'success', 'Backup-ul a pornit în fundal. Jurnalul de mai jos se actualizează singur.' );
	}

	/**
	 * Streams a fresh SQL backup to the browser (works without Google Drive).
	 *
	 * @return void
	 */
	public function download(): void {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- May be disabled on shared hosting.
		$name = Backup_Service::file_name( 'db' );
		$path = Backup_Service::dir() . '/dl-' . bin2hex( random_bytes( 8 ) ) . '.sql.gz';
		Backup_Service::dump_database( $path );
		Audit_Log::record( 'backup.download_sql', 'backup', null, array( 'size' => (int) filesize( $path ) ) );
		header( 'Content-Type: application/gzip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (int) filesize( $path ) );
		header( 'Cache-Control: no-store' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		exit;
	}

	/**
	 * Deletes one backup (Drive + local).
	 *
	 * @return void
	 * @throws Http_Exception 404.
	 */
	public function destroy(): void {
		$row = Database::instance()->get_row( "SELECT * FROM backups WHERE id = ? AND status IN ('ok','failed')", array( $this->request->param_int( 'id' ) ) );
		if ( null === $row ) {
			throw Http_Exception::not_found();
		}
		try {
			Backup_Service::remove( $row );
		} catch ( Throwable $e ) {
			$this->redirect_with( '/backup', 'error', 'Ștergerea din Google Drive a eșuat: ' . $e->getMessage() );
		}
		Audit_Log::record( 'backup.delete', 'backup', (int) $row['id'], array( 'file' => $row['file_name'] ) );
		$this->redirect_with( '/backup', 'success', 'Backup-ul a fost șters.' );
	}

	/**
	 * JSON: whether backups are still running (auto refresh of the log).
	 *
	 * @return void
	 */
	public function status(): void {
		Response::json( array( 'running' => (int) Database::instance()->get_var( "SELECT COUNT(*) FROM backups WHERE status IN ('pending','running')" ) ) );
	}
}
