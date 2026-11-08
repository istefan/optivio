<?php
/**
 * OptiVio — Businesses list.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_statuses = array(
	''         => 'Toate',
	'active'   => 'Active',
	'paused'   => 'În pauză',
	'archived' => 'Arhivate',
);
$ov_badges   = array(
	'active'   => array( 'ov-badge-success', 'Activ' ),
	'paused'   => array( 'ov-badge-warning', 'Pauză' ),
	'archived' => array( '', 'Arhivat' ),
);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
	<div class="ov-tabs mb-0">
		<?php foreach ( $ov_statuses as $ov_key => $ov_label ) : ?>
			<a class="<?php echo $ov_key === $view['status'] ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/businesses', '' !== $ov_key ? array( 'status' => $ov_key ) : array() ) ); ?>"><?php echo esc_html( $ov_label ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
		<a class="btn btn-light ms-auto" href="<?php echo esc_url( ov_url( '/businesses/new' ) ); ?>">Completare manuală</a>
		<a class="btn btn-dark" href="<?php echo esc_url( ov_url( '/onboarding' ) ); ?>"><i class="bi bi-magic me-1" aria-hidden="true"></i>Afacere nouă (wizard)</a>
	<?php endif; ?>
</div>

<div class="ov-card">
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-buildings" aria-hidden="true"></i><div class="ov-empty-title">Nicio afacere</div>Nu există afaceri pentru filtrul selectat.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead>
					<tr>
						<th scope="col">Afacere</th>
						<th scope="col">Model</th>
						<th scope="col" class="text-center">Site-uri</th>
						<th scope="col" class="text-center">Operatori</th>
						<th scope="col">Pregătire</th>
						<th scope="col">Stare</th>
						<th scope="col" class="text-end"><span class="visually-hidden">Acțiuni</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<?php $ov_badge = $ov_badges[ $ov_row['status'] ] ?? array( '', $ov_row['status'] ); ?>
						<tr>
							<td>
								<div class="d-flex align-items-center gap-2">
									<span class="ov-avatar ov-avatar-sm"><?php echo esc_html( ov_initials( (string) $ov_row['name'] ) ); ?></span>
									<div class="min-w-0">
										<a class="ov-cell-main" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_row['id'] ) ); ?>"><?php echo esc_html( $ov_row['name'] ); ?></a>
										<div class="ov-cell-sub"><?php echo esc_html( $ov_row['cui'] ?: 'fără CUI' ); ?></div>
									</div>
								</div>
							</td>
							<td class="ov-cell-sub"><?php echo esc_html( ov_business_model_label( (string) $ov_row['business_model'] ) ); ?></td>
							<td class="text-center"><?php echo esc_html( (string) (int) $ov_row['sites_count'] ); ?></td>
							<td class="text-center"><?php echo esc_html( (string) (int) $ov_row['operators_count'] ); ?></td>
							<td>
								<div class="d-flex align-items-center gap-2">
									<div class="ov-progress flex-grow-1"><span style="width: <?php echo esc_attr( (string) (int) $ov_row['readiness'] ); ?>%"></span></div>
									<span class="ov-cell-sub"><?php echo esc_html( (string) (int) $ov_row['readiness'] ); ?>%</span>
								</div>
							</td>
							<td><span class="ov-badge <?php echo esc_attr( $ov_badge[0] ); ?>"><?php echo esc_html( $ov_badge[1] ); ?></span></td>
							<td class="text-end text-nowrap">
								<a class="btn btn-sm btn-light" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_row['id'] ) ); ?>">Deschide</a>
								<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
									<a class="btn btn-sm btn-light" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_row['id'] . '/delete' ) ); ?>" title="Șterge definitiv" aria-label="Șterge definitiv <?php echo esc_attr( (string) $ov_row['name'] ); ?>"><i class="bi bi-trash" aria-hidden="true"></i></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
