<?php
/**
 * OptiVio — User create/edit form with granular business access matrix.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_u      = $view['edit_user'];
$ov_old    = (array) ( $view['old'] ?? array() );
$ov_errors = (array) ( $view['errors'] ?? array() );
$ov_grants = (array) $view['grants'];
$ov_action = null === $ov_u ? ov_url( '/users' ) : ov_url( '/users/' . (int) $ov_u['id'] );
$ov_role   = ov_value( $ov_old, $ov_u, 'role', 'operator' );
$ov_status = ov_value( $ov_old, $ov_u, 'status', 'active' );
?>
<form method="post" action="<?php echo esc_url( $ov_action ); ?>" novalidate autocomplete="off">
	<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="row g-3">
		<div class="col-12 col-xl-5">
			<div class="ov-card">
				<div class="ov-section-label">Cont</div>
				<div class="mb-3">
					<label class="form-label" for="display_name">Nume afișat</label>
					<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'display_name' ) ); ?>" id="display_name" name="display_name" maxlength="120" required value="<?php echo esc_attr( ov_value( $ov_old, $ov_u, 'display_name' ) ); ?>">
					<?php ov_field_error( $ov_errors, 'display_name' ); ?>
				</div>
				<div class="mb-3">
					<label class="form-label" for="email">Email</label>
					<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'email' ) ); ?>" type="email" id="email" name="email" maxlength="190" required value="<?php echo esc_attr( ov_value( $ov_old, $ov_u, 'email' ) ); ?>">
					<?php ov_field_error( $ov_errors, 'email' ); ?>
				</div>
				<div class="row g-3 mb-3">
					<div class="col-6">
						<label class="form-label" for="role">Rol</label>
						<select class="form-select<?php echo esc_attr( ov_invalid( $ov_errors, 'role' ) ); ?>" id="role" name="role">
							<option value="operator"<?php echo 'operator' === $ov_role ? ' selected' : ''; ?>>Operator</option>
							<option value="super_admin"<?php echo 'super_admin' === $ov_role ? ' selected' : ''; ?>>Super Admin</option>
						</select>
						<?php ov_field_error( $ov_errors, 'role' ); ?>
					</div>
					<div class="col-6">
						<label class="form-label" for="status">Stare</label>
						<select class="form-select" id="status" name="status">
							<option value="active"<?php echo 'active' === $ov_status ? ' selected' : ''; ?>>Activ</option>
							<option value="disabled"<?php echo 'disabled' === $ov_status ? ' selected' : ''; ?>>Dezactivat</option>
						</select>
					</div>
				</div>
				<div class="mb-1">
					<label class="form-label" for="password"><?php echo esc_html( null === $ov_u ? 'Parolă inițială' : 'Resetare parolă (opțional)' ); ?></label>
					<div class="input-group">
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'password' ) ); ?>" type="password" id="password" name="password" autocomplete="new-password" maxlength="256"<?php echo null === $ov_u ? ' required' : ''; ?>>
						<button class="btn btn-light border" type="button" data-toggle-password="#password" aria-label="Arată parola"><i class="bi bi-eye" aria-hidden="true"></i></button>
						<?php ov_field_error( $ov_errors, 'password' ); ?>
					</div>
					<div class="form-text">Utilizatorul va fi obligat să o schimbe la prima autentificare. Resetarea închide toate sesiunile active.</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-7">
			<div class="ov-card" id="ov-grants">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Acces la afaceri</h2>
						<p class="ov-card-subtitle">Operatorul vede și modifică exclusiv afacerile bifate aici. Super Admin are acces total.</p>
					</div>
				</div>
				<?php if ( empty( $view['businesses'] ) ) : ?>
					<div class="ov-empty py-3"><i class="bi bi-buildings" aria-hidden="true"></i>Nu există afaceri încă.</div>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table ov-table">
							<thead>
								<tr>
									<th scope="col">Afacere</th>
									<th scope="col" class="text-center">Fără acces</th>
									<th scope="col" class="text-center">Vizualizare</th>
									<th scope="col" class="text-center">Gestionare</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $view['businesses'] as $ov_bid => $ov_bname ) : ?>
									<?php $ov_perm = $ov_old['grants'][ $ov_bid ] ?? ( $ov_grants[ $ov_bid ] ?? 'none' ); ?>
									<tr>
										<td class="ov-cell-main"><?php echo esc_html( $ov_bname ); ?></td>
										<?php foreach ( array( 'none', 'view', 'manage' ) as $ov_p ) : ?>
											<td class="text-center">
												<input class="form-check-input" type="radio" name="grants[<?php echo esc_attr( (string) $ov_bid ); ?>]" value="<?php echo esc_attr( $ov_p ); ?>" aria-label="<?php echo esc_attr( $ov_bname . ' — ' . $ov_p ); ?>"<?php echo $ov_p === $ov_perm ? ' checked' : ''; ?>>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>

			<div class="d-flex gap-2 mt-3 justify-content-end">
				<a class="btn btn-light" href="<?php echo esc_url( ov_url( '/users' ) ); ?>">Renunță</a>
				<button type="submit" class="btn btn-dark">Salvează utilizatorul</button>
			</div>
		</div>
	</div>
</form>
