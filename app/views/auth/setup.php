<?php
/**
 * OptiVio — First-run installer (create Super Admin).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_old    = (array) ( $view['old'] ?? array() );
$ov_errors = (array) ( $view['errors'] ?? array() );

$ov_field = static function ( string $name, string $label, string $type, string $autocomplete, string $help = '' ) use ( $ov_old, $ov_errors ): void {
	$ov_invalid = isset( $ov_errors[ $name ] );
	?>
	<div class="mb-3">
		<label class="form-label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
		<input class="form-control<?php echo $ov_invalid ? ' is-invalid' : ''; ?>" type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" autocomplete="<?php echo esc_attr( $autocomplete ); ?>" required value="<?php echo 'password' === $type ? '' : esc_attr( $ov_old[ $name ] ?? '' ); ?>">
		<?php if ( $ov_invalid ) : ?>
			<div class="invalid-feedback"><?php echo esc_html( $ov_errors[ $name ] ); ?></div>
		<?php elseif ( '' !== $help ) : ?>
			<div class="form-text"><?php echo esc_html( $help ); ?></div>
		<?php endif; ?>
	</div>
	<?php
};
?>
<div class="ov-auth-card ov-auth-wide">
	<?php View::partial( 'partials/brand', array( 'app_name' => 'OptiVio' ) ); ?>
	<h1 class="h5 fw-semibold text-center mb-1">Instalare inițială</h1>
	<p class="text-center ov-cell-sub mb-4">Creați contul de Super Admin. Pagina devine inaccesibilă după primul utilizator.</p>

	<?php View::partial( 'partials/flash', $view ); ?>

	<?php if ( empty( $view['crypto_ready'] ) || empty( $view['token_present'] ) ) : ?>
		<div class="ov-alert ov-alert-warning">
			<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
			<div>Completați <strong>master_key</strong> și <strong>install_token</strong> în <code>config/config.php</code> (rulați <code>php bin/generate-key.php</code>).</div>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( ov_url( '/setup' ) ); ?>" novalidate>
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		$ov_field( 'install_token', 'Token de instalare', 'password', 'off', 'Valoarea install_token din config/config.php.' );
		$ov_field( 'display_name', 'Nume afișat', 'text', 'name' );
		$ov_field( 'email', 'Email', 'email', 'username' );
		$ov_field( 'password', 'Parolă', 'password', 'new-password', 'Minimum 12 caractere. Recomandat: o frază lungă.' );
		$ov_field( 'password_confirm', 'Confirmare parolă', 'password', 'new-password' );
		?>
		<button type="submit" class="btn btn-dark w-100 py-2 mt-2">Creează Super Admin</button>
	</form>
</div>
