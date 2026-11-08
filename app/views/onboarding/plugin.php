<?php
/**
 * OptiVio — Onboarding step 5: OptiVio Connector plugin per site (live connector status).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_id   = (int) $view['draft']['id'];
$ov_conn = array(
	'not_installed' => array( '', 'Neinstalat' ),
	'pending'       => array( 'ov-badge-warning', 'Aștept heartbeat' ),
	'connected'     => array( 'ov-badge-success', 'Conectat' ),
	'error'         => array( 'ov-badge-danger', 'Eroare' ),
	'revoked'       => array( 'ov-badge-danger', 'Revocat' ),
);
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3" id="ov-connectors" data-endpoint="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/status' ) ); ?>" data-labels="<?php echo esc_attr( (string) json_encode( array_map( static fn( $c ) => $c[1], $ov_conn ), JSON_UNESCAPED_UNICODE ) ); ?>">
		<?php foreach ( $view['sites'] as $ov_s ) : ?>
			<?php $ov_c = $ov_conn[ $ov_s['connector_status'] ] ?? array( '', $ov_s['connector_status'] ); ?>
			<div class="ov-card" data-ov-site-id="<?php echo esc_attr( (string) (int) $ov_s['id'] ); ?>">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title"><?php echo esc_html( (string) $ov_s['name'] ); ?></h2>
						<p class="ov-card-subtitle"><?php echo esc_html( (string) $ov_s['url'] ); ?></p>
					</div>
					<span class="ov-badge <?php echo esc_attr( $ov_c[0] ); ?>" data-ov-conn><?php echo esc_html( $ov_c[1] ); ?></span>
				</div>
				<?php if ( 'wordpress' !== $ov_s['cms'] ) : ?>
					<p class="ov-cell-sub mb-0">Site pe altă platformă: pluginul nu se aplică. Datele vor veni din Google (pasul următor).</p>
				<?php else : ?>
					<ol class="ov-steps">
						<li>Descărcați pluginul (cheia de conectare este inclusă în fișier).</li>
						<li>În WordPress: <strong>Plugins → Add New → Upload Plugin</strong>, alegeți .zip-ul, <strong>Install Now</strong>, apoi <strong>Activate</strong>.</li>
						<li>Starea de aici devine „Conectat” singură, apoi apare numărul de pagini sincronizate.</li>
					</ol>
					<div class="d-flex flex-wrap align-items-center gap-2">
						<?php if ( ! empty( $ov_s['has_key'] ) ) : ?>
							<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_s['id'] . '/plugin' ) ); ?>">
								<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button class="btn btn-dark btn-sm" type="submit"><i class="bi bi-download me-1" aria-hidden="true"></i>Descarcă pluginul (.zip)</button>
							</form>
						<?php else : ?>
							<a class="btn btn-dark btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_s['id'] ) ); ?>">Generează cheia pe pagina site-ului</a>
						<?php endif; ?>
						<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_s['id'] ) ); ?>" target="_blank" rel="noopener">Pagina site-ului</a>
						<span class="ov-cell-sub ms-auto">Pagini sincronizate: <strong data-ov-content><?php echo esc_html( (string) (int) $ov_s['content_count'] ); ?></strong> · plugin <span data-ov-version><?php echo esc_html( (string) ( $ov_s['plugin_version'] ?? '—' ) ); ?></span></span>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">După instalare</h2></div>
			<p class="mb-2">Cu pluginul conectat, OptiVio vede site-ul <strong>din interior</strong>: tot conținutul, Yoast SEO, click-urile pe telefon și formularele.</p>
			<p class="ov-cell-sub mb-0">Din tab-ul <strong>Percepție</strong> al afacerii puteți rula apoi „Percepție din interior” pe fiecare site conectat.</p>
		</div>
		<div class="ov-card d-flex flex-column gap-2">
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/advance/plugin' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button class="btn btn-dark w-100" type="submit">Continuă <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
			</form>
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/advance/plugin' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="skip" value="1">
				<button class="btn btn-light w-100" type="submit">Sar peste, instalez mai târziu</button>
			</form>
		</div>
	</div>
</div>
