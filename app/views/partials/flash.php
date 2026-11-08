<?php
/**
 * OptiVio — Flash messages.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_icons = array(
	'success' => 'bi-check-circle',
	'error'   => 'bi-x-octagon',
	'warning' => 'bi-exclamation-triangle',
	'info'    => 'bi-info-circle',
);

foreach ( (array) ( $view['flashes'] ?? array() ) as $ov_flash ) :
	$ov_type = isset( $ov_icons[ $ov_flash['type'] ] ) ? $ov_flash['type'] : 'info';
	?>
	<div class="ov-alert ov-alert-<?php echo esc_attr( $ov_type ); ?>" role="<?php echo 'error' === $ov_type ? 'alert' : 'status'; ?>">
		<i class="bi <?php echo esc_attr( $ov_icons[ $ov_type ] ); ?>" aria-hidden="true"></i>
		<div><?php echo esc_html( $ov_flash['message'] ); ?></div>
	</div>
	<?php
endforeach;
