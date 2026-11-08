<?php
/**
 * OptiVio — Edit call.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_call = (array) $view['call'];
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/calls' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Apeluri</a>
	<span class="ov-badge ov-badge-outline align-self-center"><?php echo esc_html( (string) $view['business']['name'] ); ?></span>
	<?php if ( null !== $ov_call['phone_click_id'] ) : ?>
		<span class="ov-badge align-self-center"><i class="bi bi-link-45deg" aria-hidden="true"></i>corelat cu click tel: #<?php echo esc_html( (string) $ov_call['phone_click_id'] ); ?></span>
	<?php endif; ?>
</div>
<div class="row g-3">
	<div class="col-12 col-xl-8">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/calls/' . (int) $ov_call['id'] ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php View::partial( 'calls/fields', array( 'call' => $ov_call, 'old' => $view['old'], 'errors' => $view['errors'], 'businesses' => array() ) ); ?>
			<button class="btn btn-dark mt-3" type="submit">Salvează</button>
		</form>
	</div>
	<div class="col-12 col-xl-4">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/calls/' . (int) $ov_call['id'] . '/delete' ) ); ?>" data-confirm="Ștergeți definitiv acest apel?">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="ov-cell-sub">Sursă: <?php echo esc_html( $ov_call['source'] ); ?> · creat <?php echo esc_html( ov_datetime( $ov_call['created_at'] ) ); ?></p>
			<button class="btn btn-outline-danger" type="submit">Șterge apelul</button>
		</form>
	</div>
</div>
