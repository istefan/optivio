<?php
/**
 * OptiVio — Permanent business rules tab.
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
$ov_base   = '/businesses/' . $ov_bid . '/rules';
$ov_cats   = (array) $view['rule_categories'];
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Reguli permanente</h2>
					<p class="ov-card-subtitle">Respectate de toate analizele și propunerile viitoare. Motivele de respingere ale Action Cards ajung automat aici.</p>
				</div>
			</div>
			<?php if ( empty( $view['rows'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-journal-check" aria-hidden="true"></i>Nicio regulă definită.</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<li class="align-items-start<?php echo $ov_row['is_active'] ? '' : ' opacity-50'; ?>">
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi <?php echo 'rejection' === $ov_row['source'] ? 'bi-x-circle' : 'bi-pin-angle'; ?>" aria-hidden="true"></i></span>
							<div class="ov-list-body">
								<div class="fw-medium text-wrap"><?php echo nl2br( esc_html( $ov_row['rule_text'] ) ); ?></div>
								<div class="ov-list-sub mt-1">
									<span class="ov-badge"><?php echo esc_html( $ov_cats[ $ov_row['category'] ] ?? (string) $ov_row['category'] ); ?></span>
									<span class="ms-1"><?php echo esc_html( ( 'rejection' === $ov_row['source'] ? 'din respingere' : 'manuală' ) . ' · ' . ov_datetime( $ov_row['created_at'], 'd.m.Y' ) ); ?></span>
								</div>
							</div>
							<?php if ( ! empty( $view['can_manage'] ) ) : ?>
								<div class="d-flex gap-1">
									<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] . '/toggle' ) ); ?>">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-light" type="submit"><?php echo esc_html( $ov_row['is_active'] ? 'Dezactivează' : 'Activează' ); ?></button>
									</form>
									<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . (int) $ov_row['id'] . '/delete' ) ); ?>" data-confirm="Ștergeți definitiv regula?">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Șterge"><i class="bi bi-trash" aria-hidden="true"></i></button>
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
				<div class="ov-card-header"><h2 class="ov-card-title">Regulă nouă</h2></div>
				<div class="mb-2">
					<label class="form-label" for="category">Categorie</label>
					<select class="form-select" id="category" name="category">
						<?php foreach ( $ov_cats as $ov_key => $ov_label ) : ?>
							<option value="<?php echo esc_attr( $ov_key ); ?>"<?php echo ( $ov_old['category'] ?? '' ) === $ov_key ? ' selected' : ''; ?>><?php echo esc_html( $ov_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-2">
					<label class="form-label" for="rule_text">Regula</label>
					<textarea class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'rule_text' ) ); ?>" id="rule_text" name="rule_text" rows="4" maxlength="1000" placeholder="Ex. Nu propune campanii pentru județul Ilfov după ora 20:00."><?php echo esc_textarea( $ov_old['rule_text'] ?? '' ); ?></textarea>
					<?php ov_field_error( $ov_errors, 'rule_text' ); ?>
				</div>
				<button class="btn btn-dark w-100 mt-2" type="submit">Adaugă regula</button>
			</form>
		</div>
	<?php endif; ?>
</div>
