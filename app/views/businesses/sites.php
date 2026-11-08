<?php
/**
 * OptiVio — Business sites tab.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_bid    = (int) $view['business']['id'];
$ov_errors = (array) $view['errors'];
$ov_old    = (array) $view['old'];
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Site-uri web</h2>
					<p class="ov-card-subtitle">Fiecare site are propriul conector și propriile integrări Google</p>
				</div>
			</div>
			<?php View::partial( 'partials/site-rows', array( 'rows' => $view['rows'] ) ); ?>
		</div>
	</div>
	<?php if ( ! empty( $view['can_manage'] ) ) : ?>
		<div class="col-12 col-xl-4">
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/sites' ) ); ?>" novalidate>
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header"><h2 class="ov-card-title">Adaugă site</h2></div>
				<div class="mb-2">
					<label class="form-label" for="url">URL *</label>
					<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'url' ) ); ?>" id="url" name="url" maxlength="255" placeholder="https://www.exemplu.ro" required value="<?php echo esc_attr( $ov_old['url'] ?? '' ); ?>">
					<?php ov_field_error( $ov_errors, 'url' ); ?>
				</div>
				<div class="mb-2">
					<label class="form-label" for="name">Nume afișat</label>
					<input class="form-control" id="name" name="name" maxlength="160" placeholder="implicit: domeniul" value="<?php echo esc_attr( $ov_old['name'] ?? '' ); ?>">
				</div>
				<div class="mb-3">
					<label class="form-label" for="cms">Platformă</label>
					<select class="form-select" id="cms" name="cms">
						<option value="wordpress">WordPress (cu OptiVio Connector)</option>
						<option value="other"<?php echo 'other' === ( $ov_old['cms'] ?? '' ) ? ' selected' : ''; ?>>Altă platformă (doar integrări Google)</option>
					</select>
				</div>
				<button class="btn btn-dark w-100" type="submit">Adaugă site</button>
				<p class="ov-cell-sub mt-2 mb-0">Pentru WordPress se generează automat cheia de conectare (HMAC-SHA256).</p>
			</form>
		</div>
	<?php endif; ?>
</div>
