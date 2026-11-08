<?php
/**
 * OptiVio — Site detail: settings, OptiVio Connector credentials and Google integrations.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_s      = (array) $view['site'];
$ov_sid    = (int) $ov_s['id'];
$ov_errors = (array) $view['errors'];
$ov_old    = (array) $view['old'];
$ov_manage = ! empty( $view['can_manage'] );
$ov_conn   = array(
	'not_installed' => array( '', 'Neinstalat' ),
	'pending'       => array( 'ov-badge-warning', 'Cheie generată — așteaptă pluginul' ),
	'connected'     => array( 'ov-badge-success', 'Conectat' ),
	'error'         => array( 'ov-badge-danger', 'Eroare' ),
	'revoked'       => array( 'ov-badge-danger', 'Revocat' ),
);
$ov_ist    = array(
	'not_configured' => array( '', 'neconfigurat' ),
	'active'         => array( 'ov-badge-success', 'configurat' ),
	'paused'         => array( 'ov-badge-warning', 'în pauză' ),
	'error'          => array( 'ov-badge-danger', 'eroare' ),
);
$ov_c      = $ov_conn[ $ov_s['connector_status'] ] ?? array( '', $ov_s['connector_status'] );
$ov_status = ov_value( $ov_old, $ov_s, 'status', 'active' );
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_s['business_id'] . '/sites' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?php echo esc_html( $view['business']['name'] ); ?></a>
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( $ov_s['url'] ); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Deschide site-ul</a>
</div>

<?php if ( null !== $view['secret_once'] ) : ?>
	<div class="ov-alert ov-alert-warning">
		<i class="bi bi-key" aria-hidden="true"></i>
		<div class="w-100">
			<strong>Secretul conectorului — afișat o singură dată.</strong> Este stocat criptat și va fi inclus automat în pluginul descărcabil (Etapa 4). Nu îl trimiteți pe canale nesecurizate.
			<code class="ov-code mt-2"><?php echo esc_html( $view['secret_once'] ); ?></code>
		</div>
	</div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-12 col-xl-5 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">OptiVio Connector</h2>
					<p class="ov-card-subtitle">Autentificare HMAC-SHA256 + timestamp ±300s + nonce (anti-replay)</p>
				</div>
				<span class="ov-badge <?php echo esc_attr( $ov_c[0] ); ?>"><?php echo esc_html( $ov_c[1] ); ?></span>
			</div>
			<?php if ( 'wordpress' !== $ov_s['cms'] ) : ?>
				<p class="ov-cell-sub mb-0">Site-ul nu este WordPress: conectorul nu se aplică. Integrările Google funcționează independent.</p>
			<?php else : ?>
				<dl class="ov-dl mb-3">
					<dt>Key ID</dt><dd><span class="ov-secret"><?php echo esc_html( $ov_s['connector_key_id'] ?? '—' ); ?></span></dd>
					<dt>Secret</dt><dd><?php echo esc_html( null !== $ov_s['connector_key_id'] ? '•••••••• (criptat AES-256-GCM)' : '—' ); ?></dd>
					<dt>Endpoint API</dt><dd class="ov-cell-sub"><?php echo esc_html( $view['api_base'] ); ?></dd>
					<dt>Endpoint plugin</dt><dd class="ov-cell-sub"><?php echo esc_html( $ov_s['connector_remote_url'] ?? '— (raportat la primul heartbeat)' ); ?></dd>
					<dt>Ultimul semnal</dt><dd><?php echo esc_html( ov_datetime( $ov_s['connector_last_seen_at'] ) ); ?></dd>
					<dt>Plugin / WP</dt><dd><?php echo esc_html( ( $ov_s['plugin_version'] ?? '—' ) . ' / ' . ( $ov_s['wp_version'] ?? '—' ) ); ?></dd>
					<dt>Yoast / Gravity</dt><dd><?php echo esc_html( ( $ov_s['has_yoast'] ? 'da' : 'nu' ) . ' / ' . ( $ov_s['has_gravity_forms'] ? 'da' : 'nu' ) ); ?></dd>
				</dl>
				<?php if ( $ov_manage && null !== $ov_s['connector_key_id'] ) : ?>
					<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/plugin' ) ); ?>" class="mb-2" data-confirm="Arhiva conține secretul conectorului. Continuați?">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button class="btn btn-light w-100" type="submit"><i class="bi bi-download me-1" aria-hidden="true"></i>Descarcă pluginul (.zip) cu cheia inclusă</button>
					</form>
				<?php endif; ?>
				<div class="d-flex gap-2 mb-2">
					<a class="btn btn-light flex-grow-1" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content' ) ); ?>"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Conținut & SEO</a>
					<a class="btn btn-light flex-grow-1" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/operations' ) ); ?>"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Istoric modificări</a>
				</div>
				<?php if ( $ov_manage && null !== $ov_s['connector_remote_url'] ) : ?>
					<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/ping' ) ); ?>" class="mb-2">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button class="btn btn-light w-100" type="submit"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>Testează conexiunea OptiVio → WordPress</button>
					</form>
				<?php endif; ?>
				<?php if ( $ov_manage ) : ?>
					<div class="d-flex gap-2">
						<form class="flex-grow-1" method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/rotate' ) ); ?>" data-confirm="Generați un secret nou? Pluginul instalat va trebui actualizat.">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button class="btn btn-dark w-100" type="submit"><?php echo esc_html( null !== $ov_s['connector_key_id'] ? 'Rotește secretul' : 'Generează cheia' ); ?></button>
						</form>
						<?php if ( null !== $ov_s['connector_key_id'] ) : ?>
							<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/revoke' ) ); ?>" data-confirm="Revocați accesul pluginului? Site-ul nu va mai putea trimite date.">
								<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button class="btn btn-outline-danger" type="submit">Revocă</button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header"><h2 class="ov-card-title">Setări site</h2></div>
			<fieldset<?php echo $ov_manage ? '' : ' disabled'; ?>>
				<div class="mb-2">
					<label class="form-label" for="url">URL</label>
					<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'url' ) ); ?>" id="url" name="url" maxlength="255" value="<?php echo esc_attr( ov_value( $ov_old, $ov_s, 'url' ) ); ?>">
					<?php ov_field_error( $ov_errors, 'url' ); ?>
				</div>
				<div class="mb-2">
					<label class="form-label" for="name">Nume afișat</label>
					<input class="form-control" id="name" name="name" maxlength="160" value="<?php echo esc_attr( ov_value( $ov_old, $ov_s, 'name' ) ); ?>">
				</div>
				<div class="row g-2 mb-3">
					<div class="col-6">
						<label class="form-label" for="cms">Platformă</label>
						<select class="form-select" id="cms" name="cms">
							<option value="wordpress"<?php echo 'wordpress' === $ov_s['cms'] ? ' selected' : ''; ?>>WordPress</option>
							<option value="other"<?php echo 'other' === $ov_s['cms'] ? ' selected' : ''; ?>>Altă platformă</option>
						</select>
					</div>
					<div class="col-6">
						<label class="form-label" for="status">Stare</label>
						<select class="form-select" id="status" name="status">
							<option value="active"<?php echo 'active' === $ov_status ? ' selected' : ''; ?>>Activ</option>
							<option value="paused"<?php echo 'paused' === $ov_status ? ' selected' : ''; ?>>În pauză</option>
							<option value="archived"<?php echo 'archived' === $ov_status ? ' selected' : ''; ?>>Arhivat</option>
						</select>
					</div>
				</div>
				<?php if ( $ov_manage ) : ?>
					<button class="btn btn-dark" type="submit">Salvează</button>
				<?php endif; ?>
			</fieldset>
		</form>
	</div>

	<div class="col-12 col-xl-7">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Integrări Google</h2>
					<p class="ov-card-subtitle">Conturile Google și proprietățile se asociază de Super Admin. Sincronizare automată zilnică (PSI și GTM săptămânal).</p>
				</div>
				<a class="ov-link-more" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/google' ) ); ?>">Date Google</a>
			</div>
			<?php
			$ov_accounts = (array) ( $view['google'] ?? array() );
			$ov_props    = array();
			foreach ( $ov_accounts as $ov_acc ) {
				$ov_meta = json_decode( (string) $ov_acc['meta'], true );
				foreach ( (array) ( $ov_meta['properties'] ?? array() ) as $ov_prov => $ov_list ) {
					foreach ( (array) $ov_list as $ov_prop ) {
						$ov_props[ $ov_prov ][] = $ov_prop;
					}
				}
			}
			?>
			<ul class="ov-list">
				<?php foreach ( $view['providers'] as $ov_pk => $ov_p ) : ?>
					<?php
					$ov_i     = $view['integrations'][ $ov_pk ];
					$ov_ib    = $ov_ist[ $ov_i['status'] ] ?? array( '', $ov_i['status'] );
					$ov_val   = $ov_old[ 'ext_' . $ov_pk ] ?? (string) ( $ov_i['external_id'] ?? '' );
					$ov_oauth = 'psi' !== $ov_pk;
					$ov_edit  = $ov_manage && ( ! $ov_oauth || ! empty( $view['is_admin'] ) );
					if ( 'ads' === $ov_pk && 10 === strlen( $ov_val ) && ctype_digit( $ov_val ) ) {
						$ov_val = substr( $ov_val, 0, 3 ) . '-' . substr( $ov_val, 3, 3 ) . '-' . substr( $ov_val, 6 );
					}
					?>
					<li class="flex-wrap">
						<div class="ov-list-body">
							<div class="ov-list-title"><?php echo esc_html( $ov_p[0] ); ?> <span class="ov-badge <?php echo esc_attr( $ov_ib[0] ); ?> ms-1"><?php echo esc_html( $ov_ib[1] ); ?></span> <?php ov_guide_button( $ov_pk, '' ); ?></div>
							<div class="ov-list-sub">
								<?php echo esc_html( null !== $ov_i['last_sync_at'] ? 'Ultima sincronizare: ' . ov_datetime( $ov_i['last_sync_at'] ) : 'Nesincronizat încă' ); ?>
								<?php if ( $ov_oauth && null === $ov_i['credential_id'] && null !== $ov_i['external_id'] ) : ?>
									<span class="text-danger"> · fără cont Google asociat</span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $ov_i['last_error'] ) ) : ?>
								<div class="ov-cell-sub text-danger"><?php echo esc_html( $ov_i['last_error'] ); ?></div>
							<?php endif; ?>
						</div>
						<?php if ( $ov_manage && 'active' === $ov_i['status'] && ( ! $ov_oauth || null !== $ov_i['credential_id'] ) ) : ?>
							<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/integrations/' . $ov_pk . '/sync' ) ); ?>">
								<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button class="btn btn-sm btn-light" type="submit"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Sincronizează</button>
							</form>
						<?php endif; ?>
						<form class="w-100 mt-2" method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/integrations/' . $ov_pk ) ); ?>" novalidate>
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<fieldset class="d-flex flex-wrap gap-2 align-items-start"<?php echo $ov_edit ? '' : ' disabled'; ?>>
								<?php if ( $ov_oauth ) : ?>
									<select class="form-select form-select-sm w-auto" name="credential_id" aria-label="Cont Google">
										<option value="0">— cont Google —</option>
										<?php foreach ( $ov_accounts as $ov_acc ) : ?>
											<option value="<?php echo esc_attr( (string) $ov_acc['id'] ); ?>"<?php echo (int) $ov_acc['id'] === (int) $ov_i['credential_id'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_acc['account_email'] ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
								<div class="flex-grow-1">
									<input class="form-control form-control-sm<?php echo esc_attr( ov_invalid( $ov_errors, 'ext_' . $ov_pk ) ); ?>" name="external_id" maxlength="190" spellcheck="false" list="ov-props-<?php echo esc_attr( $ov_pk ); ?>" placeholder="<?php echo esc_attr( $ov_p[2] ); ?>" aria-label="<?php echo esc_attr( $ov_p[0] . ' — ' . $ov_p[1] ); ?>" value="<?php echo esc_attr( $ov_val ); ?>">
									<datalist id="ov-props-<?php echo esc_attr( $ov_pk ); ?>">
										<?php foreach ( (array) ( $ov_props[ $ov_pk ] ?? array() ) as $ov_prop ) : ?>
											<option value="<?php echo esc_attr( (string) $ov_prop['id'] ); ?>"><?php echo esc_html( (string) $ov_prop['label'] ); ?></option>
										<?php endforeach; ?>
									</datalist>
									<?php ov_field_error( $ov_errors, 'ext_' . $ov_pk ); ?>
								</div>
								<div class="form-check mt-1">
									<input type="hidden" name="paused" value="0">
									<input class="form-check-input" type="checkbox" id="paused_<?php echo esc_attr( $ov_pk ); ?>" name="paused" value="1"<?php echo 'paused' === $ov_i['status'] ? ' checked' : ''; ?>>
									<label class="form-check-label ov-cell-sub" for="paused_<?php echo esc_attr( $ov_pk ); ?>">pauză</label>
								</div>
								<?php if ( $ov_edit ) : ?>
									<button class="btn btn-sm btn-dark" type="submit">Salvează</button>
								<?php endif; ?>
							</fieldset>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
