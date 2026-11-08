<?php
/**
 * OptiVio — All sites (scoped to the active business / accessible businesses).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
?>
<div class="ov-card">
	<div class="ov-card-header">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( count( $view['rows'] ) . ' site-uri' ); ?></h2>
			<p class="ov-card-subtitle">Adăugarea se face din pagina afacerii → Site-uri</p>
		</div>
	</div>
	<?php
	View::partial(
		'partials/site-rows',
		array(
			'rows'          => $view['rows'],
			'show_business' => true,
		)
	);
	?>
</div>
