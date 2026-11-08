<?php
/**
 * OptiVio — Business offerings (services / products) tab.
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
$ov_base   = '/businesses/' . $ov_bid . '/offerings';
$ov_cur    = (string) $view['business']['currency'];

$ov_fields = static function ( ?array $row, array $old, array $errors, string $prefix ): void {
	$ov_src  = null === $row ? $old : array();
	$ov_type = ov_value( $ov_src, $row, 'type', 'service' );
	?>
	<div class="row g-2">
		<div class="col-md-8">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>name">Denumire *</label>
			<input class="form-control<?php echo esc_attr( null === $row ? ov_invalid( $errors, 'name' ) : '' ); ?>" id="<?php echo esc_attr( $prefix ); ?>name" name="name" maxlength="160" required value="<?php echo esc_attr( ov_value( $ov_src, $row, 'name' ) ); ?>">
			<?php null === $row ? ov_field_error( $errors, 'name' ) : null; ?>
		</div>
		<div class="col-md-4">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>type">Tip</label>
			<select class="form-select" id="<?php echo esc_attr( $prefix ); ?>type" name="type">
				<option value="service"<?php echo 'service' === $ov_type ? ' selected' : ''; ?>>Serviciu</option>
				<option value="product"<?php echo 'product' === $ov_type ? ' selected' : ''; ?>>Produs</option>
			</select>
		</div>
		<div class="col-12">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>description">Descriere / diferențiatori</label>
			<textarea class="form-control" id="<?php echo esc_attr( $prefix ); ?>description" name="description" rows="2" maxlength="3000"><?php echo esc_textarea( ov_value( $ov_src, $row, 'description' ) ); ?></textarea>
		</div>
		<div class="col-6 col-md-4">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>avg_ticket_value">Valoare medie</label>
			<input class="form-control<?php echo esc_attr( null === $row ? ov_invalid( $errors, 'avg_ticket_value' ) : '' ); ?>" id="<?php echo esc_attr( $prefix ); ?>avg_ticket_value" name="avg_ticket_value" inputmode="decimal" value="<?php echo esc_attr( ov_value( $ov_src, $row, 'avg_ticket_value' ) ); ?>">
		</div>
		<div class="col-6 col-md-4">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>margin_pct">Marjă %</label>
			<input class="form-control<?php echo esc_attr( null === $row ? ov_invalid( $errors, 'margin_pct' ) : '' ); ?>" id="<?php echo esc_attr( $prefix ); ?>margin_pct" name="margin_pct" inputmode="decimal" value="<?php echo esc_attr( ov_value( $ov_src, $row, 'margin_pct' ) ); ?>">
			<?php null === $row ? ov_field_error( $errors, 'margin_pct' ) : null; ?>
		</div>
		<div class="col-6 col-md-4">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>priority">Prioritate (1–9)</label>
			<input class="form-control" type="number" min="1" max="9" id="<?php echo esc_attr( $prefix ); ?>priority" name="priority" value="<?php echo esc_attr( ov_value( $ov_src, $row, 'priority', '5' ) ); ?>">
		</div>
		<div class="col-12 d-flex flex-wrap gap-3">
			<div class="form-check">
				<input type="hidden" name="is_emergency" value="0">
				<input class="form-check-input" type="checkbox" id="<?php echo esc_attr( $prefix ); ?>is_emergency" name="is_emergency" value="1"<?php echo '1' === ov_value( $ov_src, $row, 'is_emergency', '0' ) ? ' checked' : ''; ?>>
				<label class="form-check-label" for="<?php echo esc_attr( $prefix ); ?>is_emergency">Urgență (nerecurent)</label>
			</div>
			<div class="form-check">
				<input type="hidden" name="is_recurring" value="0">
				<input class="form-check-input" type="checkbox" id="<?php echo esc_attr( $prefix ); ?>is_recurring" name="is_recurring" value="1"<?php echo '1' === ov_value( $ov_src, $row, 'is_recurring', '0' ) ? ' checked' : ''; ?>>
				<label class="form-check-label" for="<?php echo esc_attr( $prefix ); ?>is_recurring">Recurent</label>
			</div>
		</div>
	</div>
	<?php
};
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Servicii & produse</h2>
					<p class="ov-card-subtitle">Prioritatea 1 = cel mai important. Tipul (urgență / recurent) influențează strategia Ads recomandată.</p>
				</div>
			</div>
			<?php if ( empty( $view['rows'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-box-seam" aria-hidden="true"></i>Niciun serviciu sau produs.</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<li class="flex-wrap<?php echo $ov_row['is_active'] ? '' : ' opacity-50'; ?>">
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><?php echo esc_html( (string) (int) $ov_row['priority'] ); ?></span>
							<div class="ov-list-body">
								<div class="ov-list-title">
									<?php echo esc_html( $ov_row['name'] ); ?>
									<?php if ( $ov_row['is_emergency'] ) : ?>
										<span class="ov-badge ov-badge-danger ms-1">urgență</span>
									<?php endif; ?>
									<?php if ( $ov_row['is_recurring'] ) : ?>
										<span class="ov-badge ms-1">recurent</span>
									<?php endif; ?>
									<?php if ( ! $ov_row['is_active'] ) : ?>
										<span class="ov-badge ov-badge-outline ms-1">inactiv</span>
									<?php endif; ?>
								</div>
								<div class="ov-list-sub">
									<?php
									echo esc_html(
										implode(
											' · ',
											array_filter(
												array(
													'product' === $ov_row['type'] ? 'Produs' : 'Serviciu',
													null !== $ov_row['avg_ticket_value'] ? ov_number( $ov_row['avg_ticket_value'], 2 ) . ' ' . $ov_cur : null,
													null !== $ov_row['margin_pct'] ? 'marjă ' . ov_number( $ov_row['margin_pct'], 1 ) . '%' : null,
												)
											)
										)
									);
									?>
								</div>
							</div>
							<?php if ( ! empty( $view['can_manage'] ) ) : ?>
								<div class="d-flex gap-1">
									<button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#ov-edit-<?php echo esc_attr( (string) $ov_row['id'] ); ?>">Editează</button>
									<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] . '/toggle' ) ); ?>">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-light" type="submit"><?php echo esc_html( $ov_row['is_active'] ? 'Dezactivează' : 'Activează' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] . '/delete' ) ); ?>" data-confirm="Ștergeți acest serviciu?">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Șterge"><i class="bi bi-trash" aria-hidden="true"></i></button>
									</form>
								</div>
								<div class="collapse w-100" id="ov-edit-<?php echo esc_attr( (string) $ov_row['id'] ); ?>">
									<form class="border rounded-3 p-3 mt-2" method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] ) ); ?>" novalidate>
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php $ov_fields( $ov_row, array(), array(), 'o' . (int) $ov_row['id'] . '_' ); ?>
										<button class="btn btn-dark btn-sm mt-3" type="submit">Salvează</button>
									</form>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( ! empty( $view['can_manage'] ) ) : ?>
		<div class="col-12 col-xl-4">
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( $ov_base ) ); ?>" novalidate>
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header"><h2 class="ov-card-title">Adaugă serviciu / produs</h2></div>
				<?php $ov_fields( null, $ov_old, $ov_errors, 'new_' ); ?>
				<button class="btn btn-dark w-100 mt-3" type="submit">Adaugă</button>
			</form>
		</div>
	<?php endif; ?>
</div>
