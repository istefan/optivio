<?php
/**
 * OptiVio — Remote operations history of a site.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_sid = (int) $view['site']['id'];
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?php echo esc_html( $view['site']['name'] ); ?></a>
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content' ) ); ?>">Conținut & SEO</a>
</div>
<div class="ov-card">
	<?php
	View::partial(
		'partials/operations-table',
		array(
			'history'      => $view['history'],
			'site_id'      => $ov_sid,
			'can_manage'   => $view['can_manage'],
			'show_content' => true,
		)
	);
	?>
</div>
