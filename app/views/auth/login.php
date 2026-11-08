<?php
/**
 * OptiVio — Login form.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_old = (array) ( $view['old'] ?? array() );
?>
<div class="ov-auth-card">
	<?php View::partial( 'partials/brand', array( 'app_name' => 'OptiVio' ) ); ?>
	<h1 class="h5 fw-semibold text-center mb-1">Autentificare</h1>
	<p class="text-center ov-cell-sub mb-4">Accesați panoul de control OptiVio</p>

	<?php View::partial( 'partials/flash', $view ); ?>

	<form method="post" action="<?php echo esc_url( ov_url( '/login' ) ); ?>" novalidate autocomplete="on">
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="mb-3">
			<label class="form-label" for="email">Email</label>
			<input class="form-control" type="email" id="email" name="email" required maxlength="190" autocomplete="username" autofocus value="<?php echo esc_attr( $ov_old['email'] ?? '' ); ?>">
		</div>
		<div class="mb-4">
			<label class="form-label" for="password">Parolă</label>
			<input class="form-control" type="password" id="password" name="password" required maxlength="256" autocomplete="current-password">
		</div>
		<button type="submit" class="btn btn-dark w-100 py-2">Intră în cont</button>
	</form>
</div>
