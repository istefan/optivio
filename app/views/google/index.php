<?php
/**
 * OptiVio — Google integrations overview.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_status = array(
	'active'         => array( 'ov-badge-success', 'activ' ),
	'paused'         => array( 'ov-badge-warning', 'pauză' ),
	'error'          => array( 'ov-badge-danger', 'eroare' ),
	'not_configured' => array( '', 'neconfigurat' ),
);
?>
<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
	<div class="ov-card mb-3">
		<div class="ov-card-header">
			<div>
				<h2 class="ov-card-title">Conturi Google conectate</h2>
				<p class="ov-card-subtitle">OAuth 2.0 cu PKCE · refresh token criptat AES-256-GCM · acces doar citire (excepție: Ads și Business Profile, care nu oferă scope read-only)</p>
			</div>
			<?php if ( ! empty( $view['configured'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( ov_url( '/google/connect' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button class="btn btn-dark" type="submit"><i class="bi bi-google me-1" aria-hidden="true"></i>Conectează cont Google</button>
				</form>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $view['mock'] ) ) : ?>
			<div class="ov-alert ov-alert-warning"><i class="bi bi-cone-striped" aria-hidden="true"></i><div>Mod <strong>mock</strong> activ (development): apelurile Google sunt simulate local. Dezactivați <code>google_mock</code> în config pentru date reale.</div></div>
		<?php endif; ?>
		<?php if ( empty( $view['configured'] ) ) : ?>
			<div class="ov-alert ov-alert-info mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i><div>Completați Client ID și Client Secret în <a href="<?php echo esc_url( ov_url( '/settings', array( 'tab' => 'integrations' ) ) ); ?>">Setări → Integrări</a>. URI de redirecționare autorizat: <code><?php echo esc_html( $view['redirect_uri'] ); ?></code></div></div>
		<?php elseif ( empty( $view['accounts'] ) ) : ?>
			<div class="ov-empty py-3"><i class="bi bi-google" aria-hidden="true"></i>Niciun cont conectat.</div>
		<?php else : ?>
			<ul class="ov-list">
				<?php foreach ( $view['accounts'] as $ov_acc ) : ?>
					<?php $ov_meta = (array) ( json_decode( (string) $ov_acc['meta'], true ) ?: array() ); ?>
					<li>
						<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi bi-google" aria-hidden="true"></i></span>
						<div class="ov-list-body">
							<a class="ov-list-title d-block" href="<?php echo esc_url( ov_url( '/google/accounts/' . (int) $ov_acc['id'] ) ); ?>"><?php echo esc_html( $ov_acc['account_email'] ); ?></a>
							<div class="ov-list-sub"><?php echo esc_html( 'conectat ' . ov_datetime( $ov_acc['created_at'] ) . ' · proprietăți listate ' . ( ! empty( $ov_meta['discovered_at'] ) ? ov_time_ago( gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) $ov_meta['discovered_at'] ) ) ) : 'niciodată' ) ); ?></div>
						</div>
						<span class="ov-badge <?php echo 'active' === $ov_acc['status'] ? 'ov-badge-success' : 'ov-badge-danger'; ?>"><?php echo esc_html( 'active' === $ov_acc['status'] ? 'activ' : 'reconectare necesară' ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="ov-card">
	<div class="ov-card-header">
		<div>
			<h2 class="ov-card-title">Integrări pe site-uri</h2>
			<p class="ov-card-subtitle">Configurarea se face din pagina fiecărui site</p>
		</div>
	</div>
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-plug" aria-hidden="true"></i>Nicio integrare configurată.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th scope="col">Site</th><th scope="col">Integrare</th><th scope="col">Proprietate</th><th scope="col">Cont</th><th scope="col">Ultima sincronizare</th><th scope="col">Stare</th></tr></thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_r ) : ?>
						<?php $ov_s = $ov_status[ $ov_r['status'] ] ?? array( '', $ov_r['status'] ); ?>
						<tr>
							<td><a class="ov-cell-main" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_r['site_id'] ) ); ?>"><?php echo esc_html( $ov_r['site_name'] ); ?></a><div class="ov-cell-sub"><?php echo esc_html( $ov_r['business_name'] ); ?></div></td>
							<td><?php echo esc_html( $view['providers'][ $ov_r['provider'] ][0] ?? $ov_r['provider'] ); ?></td>
							<td class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_r['external_id'] ?? '—' ); ?></td>
							<td class="ov-cell-sub"><?php echo esc_html( 'psi' === $ov_r['provider'] ? 'cheie API' : ( empty( $view['is_super_admin'] ) ? ( null !== $ov_r['credential_id'] ? 'cont agenție' : '—' ) : ( $ov_r['account_email'] ?? '—' ) ) ); ?></td>
							<td class="ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_r['last_sync_at'] ) ); ?></td>
							<td>
								<span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span>
								<?php if ( ! empty( $ov_r['last_error'] ) ) : ?>
									<div class="ov-cell-sub text-danger ov-truncate" title="<?php echo esc_attr( $ov_r['last_error'] ); ?>"><?php echo esc_html( $ov_r['last_error'] ); ?></div>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
