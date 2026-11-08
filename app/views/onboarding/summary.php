<?php
/**
 * OptiVio — Onboarding step 7: summary (readiness, clarity, gaps, first cards).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Onboarding\Perception_Gaps;

$ov_b     = (array) $view['business'];
$ov_id    = (int) $view['draft']['id'];
$ov_ready = (array) $view['readiness'];
$ov_per   = $view['perception'];
$ov_state = (array) $view['state'];
$ov_count = array();
foreach ( $view['gaps'] as $ov_g ) {
	$ov_count[ $ov_g['gap_type'] ] = ( $ov_count[ $ov_g['gap_type'] ] ?? 0 ) + 1;
}
$ov_cards = count( array_unique( array_filter( array_column( $view['gaps'], 'action_card_id' ) ) ) );
$ov_skip  = array(
	'plugin' => 'Plugin WordPress — instalați-l din pagina site-ului',
	'google' => 'Integrări Google — configurați-le din pagina site-ului',
);
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Cum vă vede un bot</h2>
					<p class="ov-card-subtitle">Amprenta inițială (percepția din exterior), comparată cu profilul confirmat.</p>
				</div>
				<?php if ( null !== $ov_per ) : ?>
					<span class="ov-clarity"><?php echo esc_html( (string) (int) $ov_per['clarity_score'] ); ?>%<small>claritate</small></span>
				<?php endif; ?>
			</div>
			<?php if ( null === $ov_per ) : ?>
				<p class="ov-cell-sub mb-0">Nu există o percepție AI pentru această afacere.</p>
			<?php else : ?>
				<p><?php echo esc_html( (string) ( $ov_per['result']['summary'] ?? '' ) ); ?></p>
				<div class="d-flex flex-wrap gap-2 mb-3">
					<?php foreach ( Perception_Gaps::TYPES as $ov_k => $ov_t ) : ?>
						<?php if ( ! empty( $ov_count[ $ov_k ] ) ) : ?>
							<span class="ov-badge <?php echo esc_attr( $ov_t[1] ); ?>"><?php echo esc_html( $ov_t[0] . ': ' . $ov_count[ $ov_k ] ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php View::partial( 'partials/perception-gaps', array( 'gaps' => $view['gaps'] ) ); ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Scor de pregătire</h2><span class="ov-badge"><?php echo esc_html( (string) (int) $ov_ready['score'] ); ?>%</span></div>
			<div class="ov-progress mb-3"><span style="width: <?php echo esc_attr( (string) (int) $ov_ready['score'] ); ?>%"></span></div>
			<ul class="ov-list ov-checklist mb-0">
				<?php foreach ( $ov_ready['items'] as $ov_item ) : ?>
					<li class="d-flex align-items-center">
						<span class="ov-check <?php echo $ov_item['done'] ? 'ov-check-done' : 'ov-check-todo'; ?>"><i class="bi bi-check" aria-hidden="true"></i></span>
						<span><?php echo esc_html( (string) $ov_item['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<?php if ( ! empty( $ov_state['skipped'] ) ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Amânate</h2></div>
				<ul class="ov-list mb-0">
					<?php foreach ( (array) $ov_state['skipped'] as $ov_s ) : ?>
						<li><?php echo esc_html( $ov_skip[ $ov_s ] ?? $ov_s ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="ov-card">
			<p class="mb-3"><strong><?php echo esc_html( (string) $ov_cards ); ?></strong> recomandări (Action Cards) create din diferențele percepție vs realitate. La finalizare rulează și motorul determinist (audit, cuvinte cheie).</p>
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/finish' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button class="btn btn-dark w-100" type="submit" data-loading="Finalizez…"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Finalizează onboarding-ul</button>
			</form>
			<a class="btn btn-light w-100 mt-2" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_b['id'] . '/perception' ) ); ?>">Vezi tab-ul Percepție</a>
		</div>
	</div>
</div>
