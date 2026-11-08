<?php
/**
 * OptiVio — Remote operations history with rollback buttons.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view history, site_id, can_manage, show_content.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Services\Remote_Operations;

$ov_status = array(
	'queued'      => array( '', 'în coadă' ),
	'sent'        => array( 'ov-badge-warning', 'trimisă' ),
	'applied'     => array( 'ov-badge-success', 'aplicată' ),
	'failed'      => array( 'ov-badge-danger', 'eșuată' ),
	'rolled_back' => array( 'ov-badge-outline', 'anulată (rollback)' ),
);
?>
<?php if ( empty( $view['history'] ) ) : ?>
	<div class="ov-empty py-3"><i class="bi bi-clock-history" aria-hidden="true"></i>Nicio modificare aplicată încă.</div>
<?php else : ?>
	<div class="table-responsive">
		<table class="table ov-table">
			<thead>
				<tr>
					<th scope="col">Data</th>
					<th scope="col">Operație</th>
					<?php if ( ! empty( $view['show_content'] ) ) : ?>
						<th scope="col">Obiect</th>
					<?php endif; ?>
					<th scope="col">Utilizator</th>
					<th scope="col">Stare</th>
					<th scope="col" class="text-end"><span class="visually-hidden">Acțiuni</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $view['history'] as $ov_op ) : ?>
					<?php $ov_s = $ov_status[ $ov_op['status'] ] ?? array( '', $ov_op['status'] ); ?>
					<tr>
						<td class="ov-cell-sub text-nowrap"><?php echo esc_html( ov_datetime( $ov_op['created_at'], 'd.m.Y H:i:s' ) ); ?></td>
						<td>
							<div class="ov-cell-main"><?php echo esc_html( Remote_Operations::LABELS[ $ov_op['operation'] ] ?? $ov_op['operation'] ); ?></div>
							<?php if ( ! empty( $ov_op['error'] ) ) : ?>
								<div class="ov-cell-sub text-danger ov-truncate"><?php echo esc_html( $ov_op['error'] ); ?></div>
							<?php endif; ?>
							<?php if ( ! empty( $ov_op['remote_snapshot_id'] ) ) : ?>
								<div class="ov-cell-sub">snapshot #<?php echo esc_html( (string) $ov_op['remote_snapshot_id'] ); ?></div>
							<?php endif; ?>
						</td>
						<?php if ( ! empty( $view['show_content'] ) ) : ?>
							<td class="ov-truncate">
								<?php if ( null !== $ov_op['content_id'] ) : ?>
									<a href="<?php echo esc_url( ov_url( '/sites/' . (int) $view['site_id'] . '/content/' . (int) $ov_op['content_id'] ) ); ?>"><?php echo esc_html( $ov_op['content_title'] ?? ( '#' . $ov_op['content_id'] ) ); ?></a>
								<?php else : ?>
									<span class="ov-cell-sub"><?php echo esc_html( $ov_op['object_type'] ); ?></span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
						<td><?php echo esc_html( $ov_op['display_name'] ?? '—' ); ?></td>
						<td><span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span></td>
						<td class="text-end">
							<?php if ( ! empty( $view['can_manage'] ) && 'applied' === $ov_op['status'] && ! empty( $ov_op['remote_snapshot_id'] ) && in_array( $ov_op['operation'], Remote_Operations::REVERSIBLE, true ) ) : ?>
								<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . (int) $view['site_id'] . '/operations/' . (int) $ov_op['id'] . '/rollback' ) ); ?>" data-confirm="Restaurați versiunea dinaintea acestei modificări pe site?">
									<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<button class="btn btn-sm btn-light" type="submit"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Rollback</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
