<?php
/**
 * OptiVio — Body of an integration guide (used in the pop-up and on the Help page).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data: guide.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_g = (array) $view['guide'];
?>
<p class="mb-3"><?php echo esc_html( $ov_g['summary'] ); ?></p>
<?php if ( ! empty( $ov_g['links'] ) ) : ?>
	<div class="d-flex flex-wrap gap-2 mb-3">
		<?php foreach ( $ov_g['links'] as $ov_i => $ov_link ) : ?>
			<a class="btn btn-sm <?php echo 0 === $ov_i ? 'btn-dark' : 'btn-light'; ?>" href="<?php echo esc_url( $ov_link[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $ov_link[0] ); ?> <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<ol class="ov-steps">
	<?php foreach ( $ov_g['steps'] as $ov_step ) : ?>
		<li><?php echo esc_html( $ov_step ); ?></li>
	<?php endforeach; ?>
</ol>
<?php foreach ( $ov_g['notes'] as $ov_note ) : ?>
	<div class="ov-alert ov-alert-info mb-2"><i class="bi bi-info-circle" aria-hidden="true"></i><div><?php echo esc_html( $ov_note ); ?></div></div>
<?php endforeach; ?>
