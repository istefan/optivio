<?php
/**
 * OptiVio — Strategy toolbar: section links + "recalculate" button.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view current, can_manage.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_links = array(
	'/seo-audit'   => 'Audit SEO',
	'/keywords'    => 'Cuvinte cheie',
	'/ads'         => 'Google Ads',
	'/calls'       => 'Apeluri & venituri',
	'/competitors' => 'Concurență',
	'/actions'     => 'Action Cards',
	'/analyses'    => 'Analize AI',
);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
	<nav class="ov-tabs mb-0" aria-label="Strategie">
		<?php foreach ( $ov_links as $ov_path => $ov_label ) : ?>
			<a class="<?php echo $ov_path === $view['current'] ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( $ov_path ) ); ?>"><?php echo esc_html( $ov_label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php if ( ! empty( $view['can_manage'] ) ) : ?>
		<form class="ms-auto" method="post" action="<?php echo esc_url( ov_url( '/engine/run' ) ); ?>">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="return" value="<?php echo esc_attr( $view['current'] ); ?>">
			<button class="btn btn-dark btn-sm" type="submit"><i class="bi bi-cpu me-1" aria-hidden="true"></i>Recalculează analiza</button>
		</form>
	<?php endif; ?>
</div>
