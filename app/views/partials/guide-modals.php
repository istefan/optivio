<?php
/**
 * OptiVio — Pop-ups for the integration guides requested on the current page (printed once by the layout).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Services\Integration_Guides;

foreach ( Integration_Guides::used() as $ov_key ) :
	$ov_guide = Integration_Guides::get( $ov_key );
	$ov_id    = 'ov-guide-' . $ov_key;
	?>
	<div class="modal fade" id="<?php echo esc_attr( $ov_id ); ?>" tabindex="-1" aria-labelledby="<?php echo esc_attr( $ov_id . '-title' ); ?>" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-scrollable">
			<div class="modal-content ov-modal">
				<div class="modal-header">
					<h2 class="modal-title h5 d-flex align-items-center gap-2" id="<?php echo esc_attr( $ov_id . '-title' ); ?>"><i class="bi <?php echo esc_attr( $ov_guide['icon'] ); ?>" aria-hidden="true"></i><?php echo esc_html( $ov_guide['title'] ); ?></h2>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
				</div>
				<div class="modal-body">
					<?php View::partial( 'partials/guide-body', array( 'guide' => $ov_guide ) ); ?>
				</div>
				<div class="modal-footer">
					<a class="btn btn-light" href="<?php echo esc_url( ov_url( '/help' ) . '#ghid-' . $ov_key ); ?>">Toate ghidurile în pagina Ajutor</a>
					<button type="button" class="btn btn-dark" data-bs-dismiss="modal">Am înțeles</button>
				</div>
			</div>
		</div>
	</div>
<?php endforeach; ?>
