<?php
/**
 * OptiVio — Connected Google account with discovered properties.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_acc  = (array) $view['account'];
$ov_meta = (array) $view['meta'];
$ov_id   = (int) $ov_acc['id'];
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/google' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Integrări Google</a>
	<form class="ms-auto" method="post" action="<?php echo esc_url( ov_url( '/google/accounts/' . $ov_id . '/discover' ) ); ?>">
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button class="btn btn-light btn-sm" type="submit"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Reîmprospătează proprietățile</button>
	</form>
	<form method="post" action="<?php echo esc_url( ov_url( '/google/accounts/' . $ov_id . '/disconnect' ) ); ?>" data-confirm="Deconectați contul? Integrările care îl folosesc vor fi puse pe pauză, iar tokenul va fi revocat la Google.">
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button class="btn btn-outline-danger btn-sm" type="submit">Deconectează</button>
	</form>
</div>

<div class="row g-3">
	<?php foreach ( $view['providers'] as $ov_pk => $ov_p ) : ?>
		<?php
		if ( 'psi' === $ov_pk ) {
			continue;
		}
		$ov_list = (array) ( $ov_meta['properties'][ $ov_pk ] ?? array() );
		$ov_err  = $ov_meta['errors'][ $ov_pk ] ?? null;
		?>
		<div class="col-12 col-xl-6">
			<div class="ov-card h-100">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title"><?php echo esc_html( $ov_p[0] ); ?></h2>
						<p class="ov-card-subtitle"><?php echo esc_html( count( $ov_list ) . ' ' . mb_strtolower( $ov_p[1] ) . '(i) accesibile' ); ?></p>
					</div>
				</div>
				<?php if ( null !== $ov_err ) : ?>
					<div class="ov-alert ov-alert-error mb-0"><i class="bi bi-x-octagon" aria-hidden="true"></i><div><?php echo esc_html( $ov_err ); ?></div></div>
				<?php elseif ( empty( $ov_list ) ) : ?>
					<p class="ov-cell-sub mb-0">Nicio proprietate.</p>
				<?php else : ?>
					<ul class="ov-list">
						<?php foreach ( $ov_list as $ov_prop ) : ?>
							<li><div class="ov-list-body"><div class="ov-list-title"><?php echo esc_html( (string) $ov_prop['label'] ); ?></div><div class="ov-list-sub"><span class="ov-secret"><?php echo esc_html( (string) $ov_prop['id'] ); ?></span></div></div></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
