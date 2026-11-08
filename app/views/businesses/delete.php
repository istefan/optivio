<?php
/**
 * OptiVio — Permanent deletion confirmation (Super Admin).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_b      = (array) $view['business'];
$ov_errors = (array) ( $view['errors'] ?? array() );
?>
<div class="row g-3">
	<div class="col-12 col-xl-7">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_b['id'] . '/delete' ) ); ?>" novalidate autocomplete="off">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-alert ov-alert-error">
				<i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
				<div><strong>Ștergerea este definitivă și nu poate fi anulată.</strong> Se șterg afacerea, site-urile ei și toate datele de mai jos. Jurnalul de audit și costurile AI rămân (fără legătura cu afacerea).</div>
			</div>
			<p>Dacă doriți doar să opriți lucrul cu clientul, folosiți <a href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_b['id'] . '/edit' ) ); ?>">Profil → Stare: Arhivată</a> (reversibil).</p>
			<div class="mb-3">
				<label class="form-label" for="confirm_name">Tastați numele afacerii: <strong><?php echo esc_html( (string) $ov_b['name'] ); ?></strong></label>
				<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'confirm_name' ) ); ?>" id="confirm_name" name="confirm_name" required>
				<?php ov_field_error( $ov_errors, 'confirm_name' ); ?>
			</div>
			<div class="mb-3">
				<label class="form-label" for="password">Parola contului dumneavoastră</label>
				<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'password' ) ); ?>" type="password" id="password" name="password" required autocomplete="current-password">
				<?php ov_field_error( $ov_errors, 'password' ); ?>
			</div>
			<div class="d-flex gap-2">
				<button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1" aria-hidden="true"></i>Șterge definitiv</button>
				<a class="btn btn-light" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_b['id'] ) ); ?>">Renunță</a>
			</div>
		</form>
	</div>
	<div class="col-12 col-xl-5 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Ce se șterge</h2></div>
			<dl class="ov-dl mb-0">
				<?php foreach ( $view['summary'] as $ov_label => $ov_n ) : ?>
					<dt><?php echo esc_html( (string) $ov_label ); ?></dt><dd><?php echo esc_html( ov_number( (int) $ov_n ) ); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
		<div class="ov-alert ov-alert-warning mb-0">
			<i class="bi bi-plug" aria-hidden="true"></i>
			<div>Pluginul OptiVio Connector instalat pe site-urile clientului rămâne în WordPress, dar nu se mai poate conecta. Dezinstalați-l manual din Plugins.</div>
		</div>
	</div>
</div>
