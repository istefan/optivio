<?php
/**
 * OptiVio — Error page (works in both layouts).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_status = (int) ( $view['status'] ?? 500 );
$ov_titles = array(
	403 => 'Acces interzis',
	404 => 'Pagină inexistentă',
	405 => 'Metodă nepermisă',
	419 => 'Sesiune expirată',
	429 => 'Prea multe cereri',
	500 => 'Eroare internă',
	503 => 'Serviciu indisponibil',
);
?>
<div class="<?php echo isset( $view['current_user'] ) ? 'ov-card' : 'ov-auth-card'; ?> text-center py-5">
	<div class="display-6 fw-bold mb-2"><?php echo esc_html( (string) $ov_status ); ?></div>
	<h2 class="h5 fw-semibold"><?php echo esc_html( $ov_titles[ $ov_status ] ?? 'Eroare' ); ?></h2>
	<p class="ov-cell-sub mb-4"><?php echo esc_html( $view['message'] ?? '' ); ?></p>
	<a class="btn btn-dark" href="<?php echo esc_url( ov_url( '/' ) ); ?>">Înapoi la dashboard</a>
</div>
