<?php
/**
 * OptiVio — Onboarding step 6: Google integrations per site.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_id    = (int) $view['draft']['id'];
$ov_state = array(
	'active'   => array( 'ov-badge-success', 'activă' ),
	'pending'  => array( 'ov-badge-warning', 'în așteptare' ),
	'error'    => array( 'ov-badge-danger', 'eroare' ),
	'disabled' => array( '', 'oprită' ),
);
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<?php if ( empty( $view['accounts'] ) ) : ?>
	<div class="ov-alert ov-alert-warning"><i class="bi bi-google" aria-hidden="true"></i><div>Niciun cont Google conectat. Conectați contul agenției (cel cu acces de manager la proprietățile clienților) din <a href="<?php echo esc_url( ov_url( '/google' ) ); ?>">Integrări Google</a>. <?php ov_guide_button( 'google_oauth' ); ?></div></div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3">
		<?php foreach ( $view['sites'] as $ov_s ) : ?>
			<?php
			$ov_have = array();
			foreach ( (array) $ov_s['integrations'] as $ov_i ) {
				$ov_have[ $ov_i['provider'] ] = $ov_i;
			}
			?>
			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title"><?php echo esc_html( (string) $ov_s['name'] ); ?></h2>
						<p class="ov-card-subtitle">Alegeți contul și proprietatea pentru fiecare integrare pe pagina site-ului.</p>
					</div>
					<a class="btn btn-dark btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_s['id'] ) ); ?>" target="_blank" rel="noopener">Configurează <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>
				</div>
				<ul class="ov-list mb-0">
					<?php foreach ( $view['providers'] as $ov_pk => $ov_p ) : ?>
						<?php $ov_cur = $ov_have[ $ov_pk ] ?? null; ?>
						<li class="d-flex align-items-center gap-2">
							<span class="flex-grow-1"><?php echo esc_html( $ov_p[0] ); ?> <?php ov_guide_button( $ov_pk, '' ); ?></span>
							<?php if ( null === $ov_cur || '' === (string) ( $ov_cur['external_id'] ?? '' ) ) : ?>
								<span class="ov-badge ov-badge-outline">neconfigurată</span>
							<?php else : ?>
								<?php $ov_b = $ov_state[ $ov_cur['status'] ] ?? array( '', (string) $ov_cur['status'] ); ?>
								<span class="ov-cell-sub ov-truncate" style="max-width: 220px"><?php echo esc_html( (string) $ov_cur['external_id'] ); ?></span>
								<span class="ov-badge <?php echo esc_attr( $ov_b[0] ); ?>"><?php echo esc_html( $ov_b[1] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Analiza în detaliu</h2></div>
			<p class="mb-2">Prin contul de manager Google al agenției, OptiVio citește Search Console, Analytics, Ads, Business Profile. Datele se sincronizează în fundal, fără AI.</p>
			<p class="ov-cell-sub mb-0">Reîncărcați această pagină după configurare ca să vedeți starea.</p>
		</div>
		<div class="ov-card d-flex flex-column gap-2">
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/advance/google' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button class="btn btn-dark w-100" type="submit">Continuă <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
			</form>
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/advance/google' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="skip" value="1">
				<button class="btn btn-light w-100" type="submit">Sar peste, conectez mai târziu</button>
			</form>
		</div>
	</div>
</div>
