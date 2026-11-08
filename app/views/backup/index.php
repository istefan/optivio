<?php
/**
 * OptiVio — Backup Google Drive (Super Admin).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Services\Backup_Drive;
use OptiVio\Services\Backup_Service;

$ov_cfg     = (array) $view['config'];
$ov_running = 0;
foreach ( $view['log'] as $ov_row ) {
	$ov_running += in_array( $ov_row['status'], array( 'pending', 'running' ), true ) ? 1 : 0;
}
$ov_status = array(
	'pending' => array( 'ov-badge-warning', 'În așteptare' ),
	'running' => array( 'ov-badge-warning', 'Rulează' ),
	'ok'      => array( 'ov-badge-success', 'Reușit' ),
	'failed'  => array( 'ov-badge-danger', 'Eșuat' ),
);
$ov_size   = static function ( $bytes ): string {
	$bytes = (int) $bytes;
	return $bytes >= 1048576 ? ov_number( $bytes / 1048576, 1 ) . ' MB' : ov_number( max( 1, (int) round( $bytes / 1024 ) ) ) . ' KB';
};
$ov_switch = static function ( string $name, bool $checked, string $label ): void {
	?>
	<div class="form-check form-switch">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
		<input class="form-check-input" type="checkbox" role="switch" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $checked ? ' checked' : ''; ?>>
		<label class="form-check-label fw-semibold" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
	</div>
	<?php
};
?>
<div class="row g-3"<?php echo $ov_running > 0 ? ' id="ov-backup-running" data-endpoint="' . esc_url( ov_url( '/backup/status' ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inline. ?>>
	<div class="col-12 col-xl-7">
		<div class="ov-card h-100">
			<div class="ov-card-header">
				<h2 class="ov-card-title"><i class="bi bi-google me-2" aria-hidden="true"></i>Conexiune Google Drive & folder destinație</h2>
				<?php if ( $view['connected'] ) : ?>
					<span class="ov-badge ov-badge-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Conectat</span>
				<?php else : ?>
					<span class="ov-badge">Neconectat</span>
				<?php endif; ?>
			</div>

			<?php if ( $view['connected'] ) : ?>
				<p class="mb-2">Cont: <strong><?php echo esc_html( (string) $view['email'] ); ?></strong>
					<a class="ov-badge ov-badge-outline ms-1" href="<?php echo esc_url( (string) $view['folder_link'] ); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-folder" aria-hidden="true"></i> <?php echo esc_html( (string) $view['folder_name'] ); ?> <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
				</p>
				<p class="ov-cell-sub">Aplicația are acces doar la fișierele și folderele create de ea (permisiunea <span class="ov-kbd">drive.file</span>), nu și la restul documentelor din Google Drive.</p>
				<div class="d-flex flex-wrap gap-2 mb-3">
					<form method="post" action="<?php echo esc_url( ov_url( '/backup/drive/folder' ) ); ?>">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button class="btn btn-light btn-sm" type="submit"><i class="bi bi-folder-check me-1" aria-hidden="true"></i>Verifică / recreează folderul <?php echo esc_html( (string) $view['folder_name'] ); ?></button>
					</form>
					<button class="btn btn-light btn-sm" type="button" data-ov-test="<?php echo esc_url( ov_url( '/settings/test/gdrive' ) ); ?>"><i class="bi bi-plug me-1" aria-hidden="true"></i>Testează conexiunea</button>
					<form method="post" action="<?php echo esc_url( ov_url( '/backup/drive/disconnect' ) ); ?>" data-confirm="Deconectați Google Drive? Backup-urile programate nu vor mai fi încărcate.">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Deconectează</button>
					</form>
				</div>
				<div class="ov-test-result mb-3" data-ov-test-result="<?php echo esc_url( ov_url( '/settings/test/gdrive' ) ); ?>" aria-live="polite"></div>
			<?php elseif ( ! $view['oauth_ready'] ) : ?>
				<div class="ov-alert ov-alert-warning"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div>Configurați întâi clientul Google OAuth (Client ID și Client Secret) în <a href="<?php echo esc_url( ov_url( '/settings', array( 'tab' => 'integrations' ) ) ); ?>">Setări → Integrări</a>. Același client este folosit și pentru backup.</div></div>
			<?php else : ?>
				<p>Conectați contul Google în care se salvează backup-urile. Se cere doar permisiunea <span class="ov-kbd">drive.file</span>: OptiVio vede numai folderul și fișierele create de el.</p>
				<form class="mb-3" method="post" action="<?php echo esc_url( ov_url( '/backup/drive/connect' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button class="btn btn-dark" type="submit"><i class="bi bi-google me-1" aria-hidden="true"></i>Conectează Google Drive</button>
				</form>
			<?php endif; ?>

			<form class="row g-2 align-items-end" method="post" action="<?php echo esc_url( ov_url( '/backup/drive/folder' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="col-sm-7">
					<label class="form-label" for="folder_name">Folder în Google Drive</label>
					<input class="form-control" id="folder_name" name="folder_name" maxlength="100" value="<?php echo esc_attr( (string) $view['folder_name'] ); ?>">
				</div>
				<div class="col-sm-5">
					<button class="btn btn-light w-100" type="submit"<?php echo $view['connected'] ? '' : ' disabled'; ?>>Salvează folderul</button>
				</div>
			</form>
			<p class="ov-cell-sub mt-3 mb-0">
				Fără URI nou în Google Cloud: se folosește același URI de redirecționare ca la Integrări Google (<span class="ov-kbd"><?php echo esc_html( (string) $view['redirect_uri'] ); ?></span>). Activați doar <strong>Google Drive API</strong> în proiect.
				<?php ov_guide_button( 'gdrive', 'Cum activez Google Drive API?' ); ?>
			</p>
		</div>
	</div>

	<div class="col-12 col-xl-5">
		<div class="ov-card h-100">
			<div class="ov-card-header"><h2 class="ov-card-title"><i class="bi bi-lightning-charge me-2" aria-hidden="true"></i>Backup acum</h2></div>
			<div class="row g-2 mb-3">
				<div class="col-6"><div class="ov-stat-box"><div class="ov-cell-sub">Ultimul backup reușit</div><div class="fw-semibold"><?php echo esc_html( null !== $view['last_ok'] ? ov_datetime( (string) $view['last_ok'] ) : '—' ); ?></div></div></div>
				<div class="col-6"><div class="ov-stat-box"><div class="ov-cell-sub">Următorul backup programat</div><div class="fw-semibold"><?php echo esc_html( $view['next'] > 0 ? ov_datetime( gmdate( 'Y-m-d H:i:s', (int) $view['next'] ) ) : 'dezactivat' ); ?></div></div></div>
			</div>
			<form method="post" action="<?php echo esc_url( ov_url( '/backup/run' ) ); ?>" class="d-grid gap-2">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button class="btn btn-dark" type="submit" name="scope" value="all"><i class="bi bi-cloud-upload me-1" aria-hidden="true"></i>Rulează backup complet acum (DB + fișiere)</button>
				<button class="btn btn-light" type="submit" name="scope" value="db"><i class="bi bi-database me-1" aria-hidden="true"></i>Doar backup bază de date acum</button>
			</form>
			<form method="post" action="<?php echo esc_url( ov_url( '/backup/download' ) ); ?>" class="d-grid mt-2">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button class="btn btn-outline-dark" type="submit"><i class="bi bi-download me-1" aria-hidden="true"></i>Descarcă backup SQL direct în browser (local)</button>
			</form>
			<p class="ov-cell-sub mt-3 mb-0">Descărcarea locală funcționează și fără Google Drive. Arhivele conțin date personale și setări (inclusiv <span class="ov-kbd">config/</span> cu cheia de criptare, la backup-ul de fișiere): păstrați-le doar în locuri private.</p>
		</div>
	</div>
</div>

<form class="ov-card mt-3" method="post" action="<?php echo esc_url( ov_url( '/backup/settings' ) ); ?>" novalidate>
	<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="ov-card-header"><h2 class="ov-card-title"><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Conținut & periodicitate backup</h2></div>
	<div class="mb-3"><?php $ov_switch( 'enabled', $ov_cfg['enabled'], 'Activează backup-ul automat programat' ); ?></div>
	<div class="row g-4">
		<div class="col-12 col-xl-6">
			<div class="ov-section-label">Ce salvăm în „<?php echo esc_html( (string) $view['folder_name'] ); ?>”</div>
			<?php $ov_switch( 'include_db', $ov_cfg['include_db'], 'Salvare bază de date MySQL (.sql.gz)' ); ?>
			<p class="ov-cell-sub ms-5 mb-3">Export complet al tuturor tabelelor: afaceri, site-uri, conversii, apeluri, carduri, setări.</p>
			<?php $ov_switch( 'include_files', $ov_cfg['include_files'], 'Salvare fișiere aplicație (.zip)' ); ?>
			<div class="ms-5 mt-2 d-grid gap-2">
				<label class="ov-choice">
					<input type="radio" name="files_mode" value="recommended"<?php echo 'recommended' === $ov_cfg['files_mode'] ? ' checked' : ''; ?>>
					<span><strong>Recomandat & rapid</strong><span class="d-block ov-cell-sub">Doar imaginile încărcate (/storage/uploads/) + configurarea (/config/)</span></span>
				</label>
				<label class="ov-choice">
					<input type="radio" name="files_mode" value="full"<?php echo 'full' === $ov_cfg['files_mode'] ? ' checked' : ''; ?>>
					<span><strong>Complet</strong><span class="d-block ov-cell-sub">Tot codul aplicației + /storage/uploads/ + /config/</span></span>
				</label>
				<p class="ov-cell-sub mb-0">Excluse mereu: backup-urile anterioare (<span class="ov-kbd">storage/backups</span>), sesiunile, cache-ul, logurile, <span class="ov-kbd">.git</span>, <span class="ov-kbd">node_modules</span> și fișierele .zip / .gz / .log.<?php echo $view['has_zip'] ? '' : ' Serverul nu are extensia PHP zip: se folosește arhivatorul intern (mai lent, dar compatibil).'; ?></p>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="ov-section-label">Periodicitate</div>
			<div class="row g-2 mb-3">
				<?php foreach ( Backup_Service::FREQUENCIES as $ov_k => $ov_f ) : ?>
					<div class="col-sm-6">
						<label class="ov-choice h-100">
							<input type="radio" name="frequency" value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $ov_cfg['frequency'] ? ' checked' : ''; ?>>
							<span><strong><?php echo esc_html( $ov_f[0] ); ?></strong><span class="d-block ov-cell-sub"><?php echo esc_html( $ov_f[1] ); ?></span></span>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="row g-3">
				<div class="col-sm-6">
					<label class="form-label" for="hour">Ora preferată</label>
					<select class="form-select" id="hour" name="hour">
						<?php for ( $ov_h = 0; $ov_h < 24; $ov_h++ ) : ?>
							<option value="<?php echo esc_attr( (string) $ov_h ); ?>"<?php echo $ov_h === $ov_cfg['hour'] ? ' selected' : ''; ?>><?php echo esc_html( sprintf( '%02d:00', $ov_h ) ); ?></option>
						<?php endfor; ?>
					</select>
					<div class="form-text">Rulează la prima execuție a cron-ului automat după această oră (fusul orar al aplicației).</div>
				</div>
				<div class="col-sm-6">
					<label class="form-label" for="keep">Păstrează în Google Drive</label>
					<select class="form-select" id="keep" name="keep">
						<?php foreach ( Backup_Service::KEEP_OPTIONS as $ov_n ) : ?>
							<option value="<?php echo esc_attr( (string) $ov_n ); ?>"<?php echo $ov_n === $ov_cfg['keep'] ? ' selected' : ''; ?>><?php echo esc_html( 1 === $ov_n ? 'Doar ultimul' : 'Ultimele ' . $ov_n ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="form-text">Separat pentru baza de date și pentru fișiere; cele mai vechi sunt șterse automat.</div>
				</div>
			</div>
			<div class="mt-3"><?php $ov_switch( 'keep_local', $ov_cfg['keep_local'], 'Păstrează o copie a ultimului backup și local pe server (storage/backups)' ); ?></div>
		</div>
	</div>
	<button class="btn btn-dark mt-4" type="submit">Salvează configurarea</button>
</form>

<div class="ov-card mt-3">
	<div class="ov-card-header">
		<h2 class="ov-card-title"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Jurnal & istoric backup-uri</h2>
		<?php if ( $view['connected'] ) : ?>
			<a class="btn btn-light btn-sm" href="<?php echo esc_url( (string) $view['folder_link'] ); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Deschide folderul în Google Drive</a>
		<?php endif; ?>
	</div>
	<?php if ( empty( $view['log'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-cloud" aria-hidden="true"></i>Niciun backup încă.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th>Data</th><th>Tip</th><th>Declanșare</th><th>Fișier</th><th class="text-end">Dimensiune</th><th>Status</th><th class="text-end">Acțiuni</th></tr></thead>
				<tbody>
					<?php foreach ( $view['log'] as $ov_row ) : ?>
						<?php $ov_s = $ov_status[ $ov_row['status'] ] ?? array( '', $ov_row['status'] ); ?>
						<tr>
							<td class="text-nowrap"><?php echo esc_html( ov_datetime( (string) $ov_row['created_at'] ) ); ?></td>
							<td><?php echo esc_html( 'db' === $ov_row['kind'] ? 'Bază de date' : 'Fișiere' ); ?></td>
							<td><?php echo esc_html( 'manual' === $ov_row['trigger_source'] ? 'Manual' : 'Programat' ); ?></td>
							<td class="ov-truncate" style="max-width: 360px">
								<span class="ov-kbd"><?php echo esc_html( (string) ( $ov_row['file_name'] ?? '—' ) ); ?></span>
								<?php if ( 'failed' === $ov_row['status'] && null !== $ov_row['error'] ) : ?>
									<div class="text-danger small"><?php echo esc_html( (string) $ov_row['error'] ); ?></div>
								<?php elseif ( 'ok' === $ov_row['status'] && null === $ov_row['drive_file_id'] ) : ?>
									<div class="ov-cell-sub">doar local (Google Drive neconectat)</div>
								<?php endif; ?>
							</td>
							<td class="text-end text-nowrap"><?php echo esc_html( null !== $ov_row['size_bytes'] ? $ov_size( $ov_row['size_bytes'] ) : '—' ); ?></td>
							<td><span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span></td>
							<td class="text-end text-nowrap">
								<?php if ( ! empty( $ov_row['drive_file_id'] ) && 0 !== strpos( (string) $ov_row['drive_file_id'], 'mock-' ) ) : ?>
									<a class="btn btn-sm btn-light" href="<?php echo esc_url( Backup_Drive::link( (string) $ov_row['drive_file_id'] ) ); ?>" target="_blank" rel="noopener noreferrer" title="Deschide în Google Drive" aria-label="Deschide în Google Drive"><i class="bi bi-google" aria-hidden="true"></i></a>
								<?php endif; ?>
								<?php if ( in_array( $ov_row['status'], array( 'ok', 'failed' ), true ) ) : ?>
									<form class="d-inline" method="post" action="<?php echo esc_url( ov_url( '/backup/' . (int) $ov_row['id'] . '/delete' ) ); ?>" data-confirm="Ștergeți acest backup (și din Google Drive)?">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-light" type="submit" title="Șterge" aria-label="Șterge"><i class="bi bi-trash" aria-hidden="true"></i></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
