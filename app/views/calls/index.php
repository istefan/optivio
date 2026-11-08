<?php
/**
 * OptiVio — Calls & revenue: KPIs, hourly chart, keyword/channel breakdown, list, manual entry.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Repositories\Calls_Repository;
use OptiVio\Services\Attribution;

$ov_t       = (array) $view['totals'];
$ov_cur     = (string) $view['currency'];
$ov_days    = (int) $view['days'];
$ov_errors  = (array) $view['errors'];
$ov_old     = (array) $view['old'];
$ov_manage  = (array) $view['manageable'];
$ov_outs    = Calls_Repository::OUTCOMES;
$ov_badge   = static function ( string $outcome ): string {
	if ( 'accepted' === $outcome ) {
		return 'ov-badge-success';
	}
	if ( 0 === strpos( $outcome, 'rejected_' ) ) {
		return 'ov-badge-danger';
	}
	return 'missed' === $outcome ? 'ov-badge-warning' : '';
};
$ov_chart   = array(
	'hours'    => $view['by_hour'],
	'endpoint' => '',
);
$ov_filters = array(
	''         => 'Toate',
	'accepted' => 'Acceptate',
	'rejected' => 'Refuzate',
	'missed'   => 'Pierdute',
);
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
	<nav class="ov-tabs mb-0" aria-label="Perioadă">
		<?php foreach ( array( 7, 30, 90, 180, 365 ) as $ov_d ) : ?>
			<a class="<?php echo $ov_d === $ov_days ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/calls', array( 'days' => $ov_d ) ) ); ?>"><?php echo esc_html( $ov_d . ' zile' ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php if ( ! empty( $ov_manage ) ) : ?>
		<a class="btn btn-light ms-auto" href="<?php echo esc_url( ov_url( '/calls/import' ) ); ?>"><i class="bi bi-upload me-1" aria-hidden="true"></i>Import CSV</a>
		<button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#ov-add-call" aria-expanded="<?php echo empty( $ov_errors ) ? 'false' : 'true'; ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Apel nou</button>
	<?php endif; ?>
</div>

<?php if ( ! empty( $ov_manage ) ) : ?>
	<div class="collapse<?php echo empty( $ov_errors ) ? '' : ' show'; ?> mb-3" id="ov-add-call">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/calls' ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php View::partial( 'calls/fields', array( 'call' => null, 'old' => $ov_old, 'errors' => $ov_errors, 'businesses' => $ov_manage ) ); ?>
			<button class="btn btn-dark mt-3" type="submit">Salvează apelul</button>
		</form>
	</div>
<?php endif; ?>

<div class="row g-3 mb-3">
	<?php
	$ov_kpis = array(
		array( 'Apeluri', ov_number( $ov_t['total'] ), sprintf( '%s corelate cu click tel:', ov_number( $ov_t['linked'] ) ), 'bi-telephone' ),
		array( 'Rată acceptare', ov_number( $ov_t['accept_rate'], 1 ) . '%', sprintf( '%s acceptate · %s refuzate · %s pierdute', ov_number( $ov_t['accepted'] ), ov_number( $ov_t['rejected'] ), ov_number( $ov_t['missed'] ) ), 'bi-check2-circle' ),
		array( 'Venituri încasate', ov_compact_number( $ov_t['revenue'] ) . ' ' . $ov_cur, sprintf( '%s %s / apel acceptat', ov_number( $ov_t['revenue_per'], 0 ), $ov_cur ), 'bi-cash-stack' ),
	);
	foreach ( $ov_kpis as $ov_i => $ov_k ) :
		?>
		<div class="col-12 col-md-4">
			<div class="ov-card ov-kpi<?php echo 0 === $ov_i ? ' ov-kpi--focus' : ''; ?>">
				<div class="d-flex justify-content-between"><span class="ov-kpi-label"><?php echo esc_html( $ov_k[0] ); ?></span><span class="ov-kpi-icon"><i class="bi <?php echo esc_attr( $ov_k[3] ); ?>" aria-hidden="true"></i></span></div>
				<div class="ov-kpi-value"><?php echo esc_html( $ov_k[1] ); ?></div>
				<div class="ov-kpi-prev"><?php echo esc_html( $ov_k[2] ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-8">
		<div class="ov-card h-100">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Apeluri pe ore</h2>
					<p class="ov-card-subtitle">Ora locală · acceptate vs. refuzate și pierdute — baza pentru Ad Scheduling</p>
				</div>
			</div>
			<div class="ov-chart"><canvas id="ov-chart-hours" aria-label="Apeluri pe ore" role="img"></canvas></div>
		</div>
	</div>
	<div class="col-12 col-xl-4">
		<div class="ov-card h-100">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Recomandări program reclame</h2>
					<p class="ov-card-subtitle">Ore cu ≥ <?php echo esc_html( (string) \OptiVio\Services\Calls_Analytics::MIN_SAMPLE ); ?> apeluri și ≥ 60% refuzate/pierdute</p>
				</div>
			</div>
			<?php if ( empty( $view['schedule'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-calendar-check" aria-hidden="true"></i>Nicio oră problematică în perioada selectată.</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['schedule'] as $ov_s ) : ?>
						<li>
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi bi-clock" aria-hidden="true"></i></span>
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( sprintf( '%02d:00 – %02d:59', $ov_s['hour'], $ov_s['hour'] ) ); ?></div>
								<div class="ov-list-sub"><?php echo esc_html( sprintf( '%d apeluri · reduceți bid-ul sau opriți reclamele', $ov_s['total'] ) ); ?></div>
							</div>
							<span class="ov-badge ov-badge-danger"><?php echo esc_html( ov_number( $ov_s['bad_share'], 0 ) ); ?>%</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $view['by_outcome'] ) ) : ?>
				<div class="ov-section-label mt-3">Rezultate</div>
				<?php foreach ( $view['by_outcome'] as $ov_o => $ov_n ) : ?>
					<div class="d-flex justify-content-between ov-cell-sub py-1"><span><?php echo esc_html( $ov_outs[ $ov_o ] ?? $ov_o ); ?></span><span class="text-body fw-semibold"><?php echo esc_html( ov_number( $ov_n ) ); ?></span></div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="row g-3 mb-3">
	<?php
	foreach ( array(
		array( 'Cuvinte cheie', 'Marcate: ≥ 3 apeluri și ≥ 60% refuzate/pierdute — candidați pentru cuvinte negative', 'by_keyword', false ),
		array( 'Canale', 'Sursa apelului (din atribuirea click-ului tel: sau introdusă manual)', 'by_channel', true ),
	) as $ov_block ) :
		?>
		<div class="col-12 col-xl-6">
			<div class="ov-card h-100">
				<div class="ov-card-header"><div><h2 class="ov-card-title"><?php echo esc_html( $ov_block[0] ); ?></h2><p class="ov-card-subtitle"><?php echo esc_html( $ov_block[1] ); ?></p></div></div>
				<?php if ( empty( $view[ $ov_block[2] ] ) ) : ?>
					<div class="ov-empty py-3"><i class="bi bi-bar-chart" aria-hidden="true"></i>Fără date.</div>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table ov-table">
							<thead><tr><th scope="col"><?php echo esc_html( $ov_block[3] ? 'Canal' : 'Cuvânt cheie' ); ?></th><th scope="col" class="text-end">Apeluri</th><th scope="col" class="text-end">Acceptate</th><th scope="col" class="text-end">Refuz./pierd.</th><th scope="col" class="text-end">Venit</th></tr></thead>
							<tbody>
								<?php foreach ( $view[ $ov_block[2] ] as $ov_r ) : ?>
									<tr>
										<td>
											<span class="ov-cell-main"><?php echo esc_html( $ov_block[3] ? ( Attribution::CHANNELS[ $ov_r['label'] ] ?? $ov_r['label'] ) : $ov_r['label'] ); ?></span>
											<?php if ( ! empty( $ov_r['flag'] ) && ! $ov_block[3] ) : ?>
												<span class="ov-badge ov-badge-danger ms-1">negativ?</span>
											<?php endif; ?>
										</td>
										<td class="text-end"><?php echo esc_html( ov_number( $ov_r['total'] ) ); ?></td>
										<td class="text-end"><?php echo esc_html( ov_number( $ov_r['accept_pct'], 0 ) . '%' ); ?></td>
										<td class="text-end"><?php echo esc_html( ov_number( $ov_r['bad_share'], 0 ) . '%' ); ?></td>
										<td class="text-end text-nowrap"><?php echo esc_html( ov_number( $ov_r['revenue'], 0 ) . ' ' . $ov_cur ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="ov-card">
	<div class="ov-card-header">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( ov_number( $view['total'] ) ); ?> apeluri</h2>
			<p class="ov-card-subtitle">Numărul apelantului nu este stocat — doar ultimele 3 cifre și un hash pentru deduplicare</p>
		</div>
		<nav class="ov-tabs mb-0" aria-label="Filtru rezultat">
			<?php foreach ( $ov_filters as $ov_key => $ov_label ) : ?>
				<a class="<?php echo $ov_key === $view['outcome'] ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/calls', array_filter( array( 'days' => $ov_days, 'outcome' => $ov_key ) ) ) ); ?>"><?php echo esc_html( $ov_label ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-telephone" aria-hidden="true"></i><div class="ov-empty-title">Niciun apel</div>Adăugați apeluri manual sau importați un CSV.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead>
					<tr>
						<th scope="col">Data</th>
						<th scope="col">Afacere</th>
						<th scope="col">Rezultat</th>
						<th scope="col">Canal / cuvânt</th>
						<th scope="col">Click tel:</th>
						<th scope="col" class="text-end">Venit</th>
						<th scope="col" class="text-end"><span class="visually-hidden">Acțiuni</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<tr>
							<td class="text-nowrap">
								<div class="ov-cell-main"><?php echo esc_html( ov_datetime( $ov_row['call_time'] ) ); ?></div>
								<div class="ov-cell-sub"><?php echo esc_html( ( null !== $ov_row['caller_last3'] ? '•••• ' . $ov_row['caller_last3'] : '—' ) . ( null !== $ov_row['duration_sec'] ? ' · ' . gmdate( 'i:s', (int) $ov_row['duration_sec'] ) : '' ) ); ?></div>
							</td>
							<td><?php echo esc_html( $ov_row['business_name'] ); ?></td>
							<td>
								<span class="ov-badge <?php echo esc_attr( $ov_badge( (string) $ov_row['outcome'] ) ); ?>"><?php echo esc_html( $ov_outs[ $ov_row['outcome'] ] ?? $ov_row['outcome'] ); ?></span>
								<?php if ( ! empty( $ov_row['outcome_note'] ) ) : ?>
									<div class="ov-cell-sub ov-truncate mt-1"><?php echo esc_html( $ov_row['outcome_note'] ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<div><?php echo esc_html( Attribution::CHANNELS[ $ov_row['channel'] ] ?? $ov_row['channel'] ); ?></div>
								<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['keyword_text'] ?? $ov_row['click_term'] ?? '' ); ?></div>
							</td>
							<td><?php echo null !== $ov_row['phone_click_id'] ? '<span class="ov-badge ov-badge-outline"><i class="bi bi-link-45deg" aria-hidden="true"></i>corelat</span>' : '<span class="ov-cell-sub">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></td>
							<td class="text-end text-nowrap"><?php echo esc_html( null !== $ov_row['revenue_amount'] ? ov_number( $ov_row['revenue_amount'], 2 ) . ' ' . $ov_row['currency'] : '—' ); ?></td>
							<td class="text-end">
								<?php if ( isset( $ov_manage[ (int) $ov_row['business_id'] ] ) ) : ?>
									<a class="btn btn-sm btn-light" href="<?php echo esc_url( ov_url( '/calls/' . (int) $ov_row['id'] . '/edit' ) ); ?>">Editează</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		View::partial(
			'partials/pagination',
			array(
				'page'  => $view['page'],
				'pages' => $view['pages'],
				'base'  => '/calls',
				'query' => array_filter(
					array(
						'days'    => $ov_days,
						'outcome' => $view['outcome'],
					)
				),
			)
		);
		?>
	<?php endif; ?>
</div>

<script type="application/json" id="ov-calls-data"><?php echo ov_json_for_html( $ov_chart ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
