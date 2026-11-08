<?php
/**
 * OptiVio — Onboarding wizard stepper.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view draft, step, steps, business, reanalysis.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_draft   = (array) $view['draft'];
$ov_current = (string) $view['step'];
$ov_num     = (int) $view['steps'][ $ov_current ][0];
$ov_has_biz = null !== ( $view['business'] ?? null );
$ov_steps   = (array) $view['steps'];
if ( ! empty( $view['reanalysis'] ) ) {
	$ov_steps = array_slice( $ov_steps, 0, 2, true );
}
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/onboarding' ) ); ?>" aria-label="Înapoi la lista de onboarding"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
	<ol class="ov-stepper flex-grow-1 mb-0" aria-label="Pașii wizard-ului">
		<?php foreach ( $ov_steps as $ov_key => $ov_s ) : ?>
			<?php
			$ov_state = (int) $ov_s[0] < $ov_num ? 'done' : ( $ov_key === $ov_current ? 'current' : 'todo' );
			// Links: steps 1–3 only before the business exists, steps 4–7 only after.
			$ov_link = ( $ov_has_biz && (int) $ov_s[0] >= 4 && (int) $ov_s[0] <= $ov_num ) || ( ! $ov_has_biz && (int) $ov_s[0] < $ov_num );
			?>
			<li class="ov-stepper-item is-<?php echo esc_attr( $ov_state ); ?>"<?php echo 'current' === $ov_state ? ' aria-current="step"' : ''; ?>>
				<?php if ( $ov_link && 'current' !== $ov_state ) : ?>
					<a href="<?php echo esc_url( ov_url( '/onboarding/' . (int) $ov_draft['id'] . '/' . $ov_key ) ); ?>">
				<?php endif; ?>
				<span class="ov-stepper-dot"><?php echo 'done' === $ov_state ? '<i class="bi bi-check" aria-hidden="true"></i>' : esc_html( (string) $ov_s[0] ); ?></span>
				<span class="ov-stepper-label"><?php echo esc_html( $ov_s[1] ); ?></span>
				<?php if ( $ov_link && 'current' !== $ov_state ) : ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</div>
