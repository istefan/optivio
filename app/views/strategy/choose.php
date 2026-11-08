<?php
/**
 * OptiVio — Prompt to select the active business for strategy pages.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;
?>
<div class="ov-card">
	<div class="ov-empty">
		<i class="bi bi-buildings" aria-hidden="true"></i>
		<div class="ov-empty-title">Analiza se face pe o singură afacere</div>
		<p>Alegeți afacerea pentru care doriți audit, cuvinte cheie, Ads și concurență.</p>
		<?php if ( empty( $view['business_options'] ) ) : ?>
			<p class="mb-0">Nu aveți acces la nicio afacere.</p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( ov_url( '/context/business' ) ); ?>" class="d-flex flex-wrap gap-2 justify-content-center">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="return" value="<?php echo esc_attr( $view['return'] ); ?>">
				<?php foreach ( $view['business_options'] as $ov_id => $ov_name ) : ?>
					<button class="btn btn-light" type="submit" name="business_id" value="<?php echo esc_attr( (string) $ov_id ); ?>"><?php echo esc_html( $ov_name ); ?></button>
				<?php endforeach; ?>
			</form>
		<?php endif; ?>
	</div>
</div>
