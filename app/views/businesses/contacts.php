<?php
/**
 * OptiVio — Business contacts tab.
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
$ov_base   = '/businesses/' . $ov_bid . '/contacts';

$ov_fields = static function ( ?array $row, array $old, array $errors, string $prefix ): void {
	?>
	<div class="row g-2">
		<div class="col-md-6">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>name">Nume *</label>
			<input class="form-control<?php echo esc_attr( null === $row ? ov_invalid( $errors, 'name' ) : '' ); ?>" id="<?php echo esc_attr( $prefix ); ?>name" name="name" maxlength="120" required value="<?php echo esc_attr( ov_value( null === $row ? $old : array(), $row, 'name' ) ); ?>">
			<?php null === $row ? ov_field_error( $errors, 'name' ) : null; ?>
		</div>
		<div class="col-md-6">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>job_title">Funcție</label>
			<input class="form-control" id="<?php echo esc_attr( $prefix ); ?>job_title" name="job_title" maxlength="120" value="<?php echo esc_attr( ov_value( null === $row ? $old : array(), $row, 'job_title' ) ); ?>">
		</div>
		<div class="col-md-6">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>email">Email</label>
			<input class="form-control<?php echo esc_attr( null === $row ? ov_invalid( $errors, 'email' ) : '' ); ?>" type="email" id="<?php echo esc_attr( $prefix ); ?>email" name="email" maxlength="190" value="<?php echo esc_attr( ov_value( null === $row ? $old : array(), $row, 'email' ) ); ?>">
			<?php null === $row ? ov_field_error( $errors, 'email' ) : null; ?>
		</div>
		<div class="col-md-6">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>phone">Telefon</label>
			<input class="form-control<?php echo esc_attr( null === $row ? ov_invalid( $errors, 'phone' ) : '' ); ?>" type="tel" id="<?php echo esc_attr( $prefix ); ?>phone" name="phone" maxlength="40" value="<?php echo esc_attr( ov_value( null === $row ? $old : array(), $row, 'phone' ) ); ?>">
			<?php null === $row ? ov_field_error( $errors, 'phone' ) : null; ?>
		</div>
		<div class="col-12">
			<label class="form-label" for="<?php echo esc_attr( $prefix ); ?>notes">Note</label>
			<input class="form-control" id="<?php echo esc_attr( $prefix ); ?>notes" name="notes" maxlength="255" value="<?php echo esc_attr( ov_value( null === $row ? $old : array(), $row, 'notes' ) ); ?>">
		</div>
		<div class="col-12">
			<div class="form-check">
				<input type="hidden" name="is_primary" value="0">
				<input class="form-check-input" type="checkbox" id="<?php echo esc_attr( $prefix ); ?>is_primary" name="is_primary" value="1"<?php echo '1' === ov_value( null === $row ? $old : array(), $row, 'is_primary', '0' ) ? ' checked' : ''; ?>>
				<label class="form-check-label" for="<?php echo esc_attr( $prefix ); ?>is_primary">Persoană de contact principală</label>
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
					<h2 class="ov-card-title">Persoane de contact</h2>
					<p class="ov-card-subtitle">Cine decide, cine aprobă modificările, cine răspunde la telefon</p>
				</div>
			</div>
			<?php if ( empty( $view['rows'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-person-lines-fill" aria-hidden="true"></i>Nicio persoană de contact.</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<li class="flex-wrap">
							<span class="ov-avatar ov-avatar-sm<?php echo $ov_row['is_primary'] ? ' ov-avatar-dark' : ''; ?>"><?php echo esc_html( ov_initials( (string) $ov_row['name'] ) ); ?></span>
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( $ov_row['name'] ); ?> <?php echo $ov_row['is_primary'] ? '<span class="ov-badge ov-badge-dark ms-1">principal</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></div>
								<div class="ov-list-sub"><?php echo esc_html( implode( ' · ', array_filter( array( $ov_row['job_title'], $ov_row['email'], $ov_row['phone'] ) ) ) ?: '—' ); ?></div>
							</div>
							<?php if ( ! empty( $view['can_manage'] ) ) : ?>
								<div class="d-flex gap-1">
									<button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#ov-edit-<?php echo esc_attr( (string) $ov_row['id'] ); ?>">Editează</button>
									<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] . '/delete' ) ); ?>" data-confirm="Ștergeți acest contact?">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Șterge"><i class="bi bi-trash" aria-hidden="true"></i></button>
									</form>
								</div>
								<div class="collapse w-100" id="ov-edit-<?php echo esc_attr( (string) $ov_row['id'] ); ?>">
									<form class="border rounded-3 p-3 mt-2" method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] ) ); ?>" novalidate>
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php $ov_fields( $ov_row, array(), array(), 'c' . (int) $ov_row['id'] . '_' ); ?>
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
				<div class="ov-card-header"><h2 class="ov-card-title">Adaugă contact</h2></div>
				<?php $ov_fields( null, $ov_old, $ov_errors, 'new_' ); ?>
				<button class="btn btn-dark w-100 mt-3" type="submit">Adaugă</button>
			</form>
		</div>
	<?php endif; ?>
</div>
