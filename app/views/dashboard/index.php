<?php
/**
 * OptiVio — Dashboard (bento grid: KPIs, evolution chart, recent conversions,
 * readiness donut, weekday bars, recent activity).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Security\Audit_Log;

$ov_kpis      = (array) $view['kpis'];
$ov_currency  = (string) $view['currency'];
$ov_active_id = (int) $view['active_id'];
$ov_score     = (int) $view['readiness_score'];

$ov_delta = static function ( ?float $delta ): void {
	if ( null === $delta ) {
		echo '<span class="ov-delta ov-delta-up"><i class="bi bi-arrow-up-short" aria-hidden="true"></i>nou</span>';
		return;
	}
	$ov_class = $delta > 0 ? 'ov-delta-up' : ( $delta < 0 ? 'ov-delta-down' : 'ov-delta-flat' );
	$ov_icon  = $delta > 0 ? 'bi-arrow-up-short' : ( $delta < 0 ? 'bi-arrow-down-short' : 'bi-dash' );
	$ov_sign  = $delta > 0 ? '+' : '';
	printf(
		'<span class="ov-delta %1$s"><i class="bi %2$s" aria-hidden="true"></i>%3$s%%</span>',
		esc_attr( $ov_class ),
		esc_attr( $ov_icon ),
		esc_html( $ov_sign . ov_number( $delta, 1 ) )
	);
};

$ov_kpi_cards = array(
	array(
		'key'    => 'conversions',
		'label'  => 'Conversii totale',
		'icon'   => 'bi-lightning-charge',
		'format' => 'number',
		'focus'  => true,
	),
	array(
		'key'    => 'phone',
		'label'  => 'Click-uri pe telefon',
		'icon'   => 'bi-telephone',
		'format' => 'number',
		'focus'  => false,
	),
	array(
		'key'    => 'revenue',
		'label'  => 'Venituri din apeluri',
		'icon'   => 'bi-cash-stack',
		'format' => 'money',
		'focus'  => false,
	),
);

$ov_channels = array(
	'google_ads' => 'Google Ads',
	'organic'    => 'Organic',
	'gbp'        => 'Google Business',
	'social'     => 'Social',
	'referral'   => 'Referral',
	'direct'     => 'Direct',
	'email'      => 'Email',
	'other'      => 'Altă sursă',
);
$ov_devices  = array(
	'mobile'  => 'bi-phone',
	'tablet'  => 'bi-tablet',
	'desktop' => 'bi-laptop',
	'unknown' => 'bi-question-circle',
);

$ov_chart_data = array(
	'series'    => $view['series'],
	'weekdays'  => $view['weekdays'],
	'readiness' => $ov_score,
	'currency'  => $ov_currency,
	'endpoint'  => ov_url( '/api/dashboard/chart' ),
);
?>

<?php if ( empty( $view['has_businesses'] ) ) : ?>
	<div class="ov-card mb-3 d-flex flex-column flex-md-row align-items-md-center gap-3">
		<span class="ov-avatar ov-avatar-dark"><i class="bi bi-buildings" aria-hidden="true"></i></span>
		<div class="flex-grow-1">
			<div class="fw-semibold">Nicio afacere disponibilă încă</div>
			<div class="ov-cell-sub">
				<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
					Adăugați primul client pentru a începe colectarea datelor și analiza.
				<?php else : ?>
					Contul dumneavoastră nu are încă acces la nicio afacere. Contactați administratorul.
				<?php endif; ?>
			</div>
		</div>
		<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
			<a class="btn btn-dark" href="<?php echo esc_url( ov_url( '/businesses/new' ) ); ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Adaugă afacere</a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="row g-3">
	<!-- Main column -->
	<div class="col-12 col-xl-8 col-xxl-9 d-flex flex-column gap-3">
		<div class="row g-3">
			<?php foreach ( $ov_kpi_cards as $ov_card ) : ?>
				<?php $ov_k = $ov_kpis[ $ov_card['key'] ]; ?>
				<div class="col-12 col-md-4">
					<div class="ov-card ov-kpi<?php echo $ov_card['focus'] ? ' ov-kpi--focus' : ''; ?>">
						<div class="d-flex justify-content-between align-items-start">
							<span class="ov-kpi-label"><?php echo esc_html( $ov_card['label'] ); ?></span>
							<span class="ov-kpi-icon"><i class="bi <?php echo esc_attr( $ov_card['icon'] ); ?>" aria-hidden="true"></i></span>
						</div>
						<div class="ov-kpi-value">
							<?php
							echo esc_html(
								'money' === $ov_card['format']
									? ov_compact_number( $ov_k['value'] ) . ' ' . $ov_currency
									: ov_number( $ov_k['value'] )
							);
							?>
						</div>
						<div class="d-flex justify-content-between align-items-center gap-2">
							<span class="ov-kpi-prev">
								<?php
								echo esc_html(
									sprintf(
										'%s în cele %d zile anterioare',
										'money' === $ov_card['format'] ? ov_compact_number( $ov_k['previous'] ) . ' ' . $ov_currency : ov_number( $ov_k['previous'] ),
										(int) $view['days']
									)
								);
								?>
							</span>
							<?php $ov_delta( null === $ov_k['delta'] ? null : (float) $ov_k['delta'] ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Evoluție conversii</h2>
					<p class="ov-card-subtitle"><span id="ov-series-total"><?php echo esc_html( ov_number( $view['series']['total'], 0 ) ); ?></span> în perioada selectată · linia gri = perioada anterioară</p>
				</div>
				<div class="ov-card-tools">
					<select class="form-select form-select-sm" id="ov-chart-metric" aria-label="Metrică">
						<option value="conversions">Toate conversiile</option>
						<option value="phone">Click-uri telefon</option>
						<option value="forms">Formulare valide</option>
						<option value="revenue">Venituri</option>
					</select>
					<select class="form-select form-select-sm" id="ov-chart-days" aria-label="Perioadă">
						<option value="7">7 zile</option>
						<option value="30" selected>30 zile</option>
						<option value="90">90 zile</option>
					</select>
				</div>
			</div>
			<div class="ov-chart"><canvas id="ov-chart-main" aria-label="Grafic evoluție conversii" role="img"></canvas></div>
		</div>

		<div class="ov-card">
			<div class="ov-card-header mb-2">
				<div>
					<h2 class="ov-card-title">Conversii recente</h2>
					<p class="ov-card-subtitle">Ultimele click-uri pe telefon și formulare, cu atribuirea sursei</p>
				</div>
				<a class="ov-link-more" href="<?php echo esc_url( ov_url( '/conversions' ) ); ?>">Vezi tot</a>
			</div>
			<?php if ( empty( $view['recent'] ) ) : ?>
				<div class="ov-empty">
					<i class="bi bi-inbox" aria-hidden="true"></i>
					<div class="ov-empty-title">Nicio conversie înregistrată</div>
					Datele apar automat după instalarea pluginului OptiVio Connector pe site-urile clienților.
				</div>
			<?php else : ?>
				<div class="table-responsive">
					<table class="table ov-table">
						<thead>
							<tr>
								<th scope="col">Conversie</th>
								<th scope="col">Sursă</th>
								<th scope="col">Campanie / termen</th>
								<th scope="col" class="text-center">Dispozitiv</th>
								<th scope="col" class="text-end">Când</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $view['recent'] as $ov_row ) : ?>
								<tr>
									<td>
										<div class="d-flex align-items-center gap-2">
											<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi <?php echo 'phone' === $ov_row['kind'] ? 'bi-telephone' : 'bi-ui-checks'; ?>" aria-hidden="true"></i></span>
											<div class="min-w-0">
												<div class="ov-cell-main"><?php echo esc_html( 'phone' === $ov_row['kind'] ? 'Click telefon' : 'Formular' ); ?></div>
												<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['business_name'] . ' · ' . ( $ov_row['detail'] ?? '' ) ); ?></div>
											</div>
										</div>
									</td>
									<td><span class="ov-badge ov-badge-outline"><?php echo esc_html( $ov_channels[ $ov_row['channel'] ] ?? $ov_row['channel'] ); ?></span></td>
									<td>
										<div class="ov-truncate"><?php echo esc_html( $ov_row['utm_campaign'] ?? '—' ); ?></div>
										<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['utm_term'] ?? '' ); ?></div>
									</td>
									<td class="text-center"><i class="bi <?php echo esc_attr( $ov_devices[ $ov_row['device_type'] ] ?? 'bi-question-circle' ); ?>" title="<?php echo esc_attr( $ov_row['device_type'] ); ?>" aria-label="<?php echo esc_attr( $ov_row['device_type'] ); ?>"></i></td>
									<td class="text-end text-nowrap"><span class="ov-cell-sub"><?php echo esc_html( ov_time_ago( $ov_row['occurred_at'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Right column -->
	<div class="col-12 col-xl-4 col-xxl-3 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header mb-1">
				<div>
					<h2 class="ov-card-title">Scor de pregătire</h2>
					<p class="ov-card-subtitle">
						<?php
						echo esc_html(
							$ov_active_id > 0
								? 'Baza de date pentru optimizare'
								: sprintf( 'Media pentru %d afaceri', (int) $view['readiness_count'] )
						);
						?>
					</p>
				</div>
			</div>
			<div class="ov-donut">
				<canvas id="ov-chart-donut" aria-label="Scor de pregătire" role="img"></canvas>
				<div class="ov-donut-center">
					<span class="ov-donut-value"><?php echo esc_html( (string) $ov_score ); ?>%</span>
					<span class="ov-donut-label">configurat</span>
				</div>
			</div>
			<div class="ov-legend">
				<span><i class="ov-legend-ink"></i>Configurat</span>
				<span><i class="ov-legend-soft"></i>De completat</span>
			</div>
			<?php if ( null !== $view['readiness'] ) : ?>
				<?php
				$ov_todo = array_values(
					array_filter(
						$view['readiness']['items'],
						static function ( array $item ): bool {
							return ! $item['done'];
						}
					)
				);
				?>
				<?php if ( ! empty( $ov_todo ) ) : ?>
					<ul class="ov-list ov-checklist mb-3">
						<?php foreach ( array_slice( $ov_todo, 0, 3 ) as $ov_item ) : ?>
							<li><span class="ov-check ov-check-todo"></span><span class="ov-list-sub"><?php echo esc_html( $ov_item['label'] ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<a class="btn btn-dark w-100" href="<?php echo esc_url( ov_url( '/businesses/' . $ov_active_id ) ); ?>">Completează configurarea</a>
			<?php else : ?>
				<a class="btn btn-dark w-100" href="<?php echo esc_url( ov_url( '/businesses' ) ); ?>">Vezi afacerile</a>
			<?php endif; ?>
		</div>

		<div class="ov-card">
			<div class="ov-card-header mb-2">
				<div>
					<h2 class="ov-card-title">Conversii pe zile</h2>
					<p class="ov-card-subtitle">Distribuție pe zilele săptămânii</p>
				</div>
			</div>
			<div class="ov-chart-sm"><canvas id="ov-chart-bars" aria-label="Conversii pe zilele săptămânii" role="img"></canvas></div>
		</div>

		<div class="ov-card">
			<div class="ov-card-header mb-1">
				<div>
					<h2 class="ov-card-title">Activitate recentă</h2>
					<p class="ov-card-subtitle">Acțiuni din echipă</p>
				</div>
				<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
					<a class="ov-link-more" href="<?php echo esc_url( ov_url( '/audit' ) ); ?>">Jurnal</a>
				<?php endif; ?>
			</div>
			<?php if ( empty( $view['activity'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-clock-history" aria-hidden="true"></i>Nicio activitate</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['activity'] as $ov_act ) : ?>
						<li>
							<span class="ov-avatar ov-avatar-sm"><?php echo esc_html( ov_initials( (string) ( $ov_act['display_name'] ?? '?' ) ) ); ?></span>
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( Audit_Log::label( (string) $ov_act['action'] ) ); ?></div>
								<div class="ov-list-sub"><?php echo esc_html( trim( ( $ov_act['display_name'] ?? 'Sistem' ) . ( ! empty( $ov_act['business_name'] ) ? ' · ' . $ov_act['business_name'] : '' ) ) ); ?></div>
							</div>
							<span class="ov-list-meta"><?php echo esc_html( ov_time_ago( $ov_act['created_at'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>

<script type="application/json" id="ov-dashboard-data"><?php echo ov_json_for_html( $ov_chart_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON hex-encoded for HTML. ?></script>
