<?php
/**
 * OptiVio — Sites table (shared by the global list and the business tab).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view rows, show_business.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_conn = array(
	'not_installed' => array( '', 'Neinstalat' ),
	'pending'       => array( 'ov-badge-warning', 'Cheie generată' ),
	'connected'     => array( 'ov-badge-success', 'Conectat' ),
	'error'         => array( 'ov-badge-danger', 'Eroare' ),
	'revoked'       => array( 'ov-badge-danger', 'Revocat' ),
);
$ov_stat = array(
	'active'   => 'Activ',
	'paused'   => 'Pauză',
	'archived' => 'Arhivat',
);
?>
<?php if ( empty( $view['rows'] ) ) : ?>
	<div class="ov-empty"><i class="bi bi-globe2" aria-hidden="true"></i><div class="ov-empty-title">Niciun site</div>Adăugați primul site al afacerii.</div>
<?php else : ?>
	<div class="table-responsive">
		<table class="table ov-table">
			<thead>
				<tr>
					<th scope="col">Site</th>
					<?php if ( ! empty( $view['show_business'] ) ) : ?>
						<th scope="col">Afacere</th>
					<?php endif; ?>
					<th scope="col">Connector</th>
					<th scope="col" class="text-center">Integrări</th>
					<th scope="col">Ultimul semnal</th>
					<th scope="col">Stare</th>
					<th scope="col" class="text-end"><span class="visually-hidden">Acțiuni</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $view['rows'] as $ov_row ) : ?>
					<?php $ov_c = $ov_conn[ $ov_row['connector_status'] ] ?? array( '', $ov_row['connector_status'] ); ?>
					<tr>
						<td>
							<div class="d-flex align-items-center gap-2">
								<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi <?php echo 'wordpress' === $ov_row['cms'] ? 'bi-wordpress' : 'bi-globe2'; ?>" aria-hidden="true"></i></span>
								<div class="min-w-0">
									<a class="ov-cell-main" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_row['id'] ) ); ?>"><?php echo esc_html( $ov_row['name'] ); ?></a>
									<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['url'] ); ?></div>
								</div>
							</div>
						</td>
						<?php if ( ! empty( $view['show_business'] ) ) : ?>
							<td><?php echo esc_html( $ov_row['business_name'] ); ?></td>
						<?php endif; ?>
						<td><span class="ov-badge <?php echo esc_attr( $ov_c[0] ); ?>"><?php echo esc_html( $ov_c[1] ); ?></span></td>
						<td class="text-center"><?php echo esc_html( (int) $ov_row['integrations_active'] . ' / 7' ); ?></td>
						<td class="ov-cell-sub"><?php echo esc_html( ov_time_ago( $ov_row['connector_last_seen_at'] ) ); ?></td>
						<td><span class="ov-badge"><?php echo esc_html( $ov_stat[ $ov_row['status'] ] ?? $ov_row['status'] ); ?></span></td>
						<td class="text-end"><a class="btn btn-sm btn-light" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_row['id'] ) ); ?>">Configurează</a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
