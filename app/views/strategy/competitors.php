<?php
/**
 * OptiVio — Competitors.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_manage = ! empty( $view['can_manage'] );
?>
<?php View::partial( 'strategy/toolbar', array( 'current' => '/competitors', 'can_manage' => $ov_manage ) ); ?>

<?php if ( $ov_manage ) : ?>
	<div class="row g-3 mb-3">
		<div class="col-12 col-xl-6">
			<form class="ov-card h-100" method="post" action="<?php echo esc_url( ov_url( '/competitors/discover' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header"><div><h2 class="ov-card-title">Descoperire automată (Serper.dev)</h2><p class="ov-card-subtitle">Top 10 Google pentru un cuvânt cheie; directoarele și rețelele sociale sunt excluse</p></div></div>
				<div class="d-flex gap-2">
					<input class="form-control" name="keyword" list="ov-tracked" maxlength="190" placeholder="ex. instalator non stop bucuresti" required aria-label="Cuvânt cheie">
					<datalist id="ov-tracked">
						<?php foreach ( $view['tracked'] as $ov_k ) : ?>
							<option value="<?php echo esc_attr( $ov_k ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
					<button class="btn btn-dark text-nowrap" type="submit">Caută</button>
				</div>
			</form>
		</div>
		<div class="col-12 col-xl-6">
			<form class="ov-card h-100" method="post" action="<?php echo esc_url( ov_url( '/competitors' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header"><div><h2 class="ov-card-title">Adaugă manual</h2><p class="ov-card-subtitle">URL-ul paginii concurente (scanată direct din PHP, doar adrese publice)</p></div></div>
				<div class="d-flex flex-wrap gap-2">
					<input class="form-control" style="min-width:220px;flex:2" name="url" type="url" maxlength="500" placeholder="https://www.concurent.ro/servicii/" required aria-label="URL">
					<input class="form-control" style="min-width:160px;flex:1" name="keyword" maxlength="190" placeholder="cuvânt cheie (opțional)" aria-label="Cuvânt cheie">
					<button class="btn btn-dark" type="submit">Adaugă</button>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>

<div class="ov-card">
	<div class="ov-card-header flex-wrap">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( count( $view['competitors'] ) . ' concurenți' ); ?></h2>
			<p class="ov-card-subtitle"><?php echo esc_html( (int) $view['stale'] . ' pagini de scanat (rescanare automată la 14 zile)' ); ?></p>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<?php if ( ! empty( $view['keywords'] ) ) : ?>
				<form class="d-flex gap-2" method="get" action="<?php echo esc_url( ov_url( '/competitors/compare' ) ); ?>">
					<select class="form-select form-select-sm" name="keyword" aria-label="Cuvânt cheie">
						<?php foreach ( $view['keywords'] as $ov_k ) : ?>
							<option value="<?php echo esc_attr( $ov_k ); ?>"><?php echo esc_html( $ov_k ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="btn btn-sm btn-light text-nowrap" type="submit">Compară</button>
				</form>
			<?php endif; ?>
			<?php if ( $ov_manage && $view['stale'] > 0 ) : ?>
				<form method="post" action="<?php echo esc_url( ov_url( '/competitors/scan' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<button class="btn btn-sm btn-dark text-nowrap" type="submit"><i class="bi bi-radar me-1" aria-hidden="true"></i>Scanează acum</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( empty( $view['competitors'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-binoculars" aria-hidden="true"></i><div class="ov-empty-title">Niciun concurent</div>Folosiți descoperirea automată sau adăugați URL-uri manual.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th scope="col">Domeniu</th><th scope="col">Sursă</th><th scope="col" class="text-center">Cea mai bună poziție</th><th scope="col" class="text-center">Pagini scanate</th><th scope="col">Ultima scanare</th><th scope="col" class="text-end"><span class="visually-hidden">Acțiuni</span></th></tr></thead>
				<tbody>
					<?php foreach ( $view['competitors'] as $ov_c ) : ?>
						<tr class="<?php echo $ov_c['is_active'] ? '' : 'opacity-50'; ?>">
							<td><div class="ov-cell-main"><?php echo esc_html( $ov_c['domain'] ); ?></div><div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_c['name'] ?? '' ); ?></div></td>
							<td><span class="ov-badge"><?php echo esc_html( 'serp' === $ov_c['source'] ? 'SERP' : 'manual' ); ?></span></td>
							<td class="text-center"><?php echo esc_html( null !== $ov_c['best_position'] ? '#' . $ov_c['best_position'] : '—' ); ?></td>
							<td class="text-center"><?php echo esc_html( (int) $ov_c['scanned'] . ' / ' . (int) $ov_c['pages'] ); ?></td>
							<td class="ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_c['last_scanned_at'] ) ); ?></td>
							<td class="text-end">
								<?php if ( $ov_manage ) : ?>
									<form method="post" action="<?php echo esc_url( ov_url( '/competitors/' . (int) $ov_c['id'] . '/toggle' ) ); ?>">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<button class="btn btn-sm btn-light" type="submit"><?php echo esc_html( $ov_c['is_active'] ? 'Ignoră' : 'Reactivează' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
