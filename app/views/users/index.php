<?php
/**
 * OptiVio — Users list.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;
?>
<div class="d-flex mb-3">
	<a class="btn btn-dark ms-auto" href="<?php echo esc_url( ov_url( '/users/new' ) ); ?>"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Utilizator nou</a>
</div>

<div class="ov-card">
	<div class="table-responsive">
		<table class="table ov-table">
			<thead>
				<tr>
					<th scope="col">Utilizator</th>
					<th scope="col">Rol</th>
					<th scope="col" class="text-center">Afaceri</th>
					<th scope="col">Stare</th>
					<th scope="col">Ultima autentificare</th>
					<th scope="col" class="text-end"><span class="visually-hidden">Acțiuni</span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $view['rows'] as $ov_row ) : ?>
					<tr>
						<td>
							<div class="d-flex align-items-center gap-2">
								<span class="ov-avatar ov-avatar-sm<?php echo 'super_admin' === $ov_row['role'] ? ' ov-avatar-dark' : ''; ?>"><?php echo esc_html( ov_initials( (string) $ov_row['display_name'] ) ); ?></span>
								<div class="min-w-0">
									<div class="ov-cell-main"><?php echo esc_html( $ov_row['display_name'] ); ?></div>
									<div class="ov-cell-sub"><?php echo esc_html( $ov_row['email'] ); ?></div>
								</div>
							</div>
						</td>
						<td><span class="ov-badge<?php echo 'super_admin' === $ov_row['role'] ? ' ov-badge-dark' : ''; ?>"><?php echo esc_html( 'super_admin' === $ov_row['role'] ? 'Super Admin' : 'Operator' ); ?></span></td>
						<td class="text-center"><?php echo esc_html( 'super_admin' === $ov_row['role'] ? 'toate' : (string) (int) $ov_row['businesses_count'] ); ?></td>
						<td><span class="ov-badge <?php echo 'active' === $ov_row['status'] ? 'ov-badge-success' : 'ov-badge-danger'; ?>"><?php echo esc_html( 'active' === $ov_row['status'] ? 'Activ' : 'Dezactivat' ); ?></span></td>
						<td class="ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_row['last_login_at'] ) ); ?></td>
						<td class="text-end"><a class="btn btn-sm btn-light" href="<?php echo esc_url( ov_url( '/users/' . (int) $ov_row['id'] . '/edit' ) ); ?>">Editează</a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
