<?php
/**
 * OptiVio — Keywords and organic search insights.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Engine\Keyword_Research;

$ov_manage  = ! empty( $view['can_manage'] );
$ov_status  = array(
	'proposed' => array( '', 'propus' ),
	'tracked'  => array( 'ov-badge-dark', 'urmărit' ),
	'excluded' => array( 'ov-badge-outline', 'exclus' ),
);
$ov_sources = array(
	'gsc'        => 'GSC',
	'ads'        => 'Ads',
	'serp'       => 'SERP',
	'manual'     => 'manual',
	'ai'         => 'AI',
	'competitor' => 'concurență',
	'generated'  => 'servicii × zonă',
);
$ov_return  = '/keywords?' . http_build_query( array_filter( array( 'status' => $view['status'], 'intent' => $view['intent'] ) ) );
?>
<?php View::partial( 'strategy/toolbar', array( 'current' => '/keywords', 'can_manage' => $ov_manage ) ); ?>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-4">
		<?php
		View::partial(
			'strategy/insight-list',
			array(
				'items'      => $view['insights'],
				'types'      => array( 'cannibalization' ),
				'title'      => 'Canibalizare',
				'subtitle'   => 'Aceeași interogare împărțită între mai multe pagini',
				'labels'     => array( 'impressions' => 'afișări' ),
				'can_manage' => $ov_manage,
				'return'     => $ov_return,
			)
		);
		?>
	</div>
	<div class="col-12 col-xl-4">
		<?php
		View::partial(
			'strategy/insight-list',
			array(
				'items'      => $view['insights'],
				'types'      => array( 'striking_distance' ),
				'title'      => 'Aproape de top 3',
				'subtitle'   => 'Poziții 4–20 cu afișări — câștiguri rapide',
				'labels'     => array( 'position' => 'poz.', 'impressions' => 'afișări', 'potential_clicks' => '+click-uri potențiale' ),
				'can_manage' => $ov_manage,
				'return'     => $ov_return,
			)
		);
		?>
	</div>
	<div class="col-12 col-xl-4">
		<?php
		View::partial(
			'strategy/insight-list',
			array(
				'items'      => $view['insights'],
				'types'      => array( 'low_ctr_high_position' ),
				'title'      => 'CTR mic la poziție bună',
				'subtitle'   => 'Titlul / descrierea nu atrag click-uri',
				'labels'     => array( 'position' => 'poz.', 'ctr' => 'CTR %', 'expected_ctr' => 'așteptat %' ),
				'can_manage' => $ov_manage,
				'return'     => $ov_return,
			)
		);
		?>
	</div>
</div>

<div class="ov-card">
	<div class="ov-card-header flex-wrap">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( count( $view['rows'] ) . ' cuvinte cheie' ); ?></h2>
			<p class="ov-card-subtitle">Prioritate calculată din afișări, click-uri, conversii Ads, apeluri (minus refuzate) și intenție ponderată după modelul de afacere</p>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<form class="d-flex gap-2" method="get" action="<?php echo esc_url( ov_url( '/keywords' ) ); ?>">
				<select class="form-select form-select-sm" name="status" aria-label="Status">
					<?php foreach ( array( 'active' => 'Active', 'proposed' => 'Propuse', 'tracked' => 'Urmărite', 'excluded' => 'Excluse', 'all' => 'Toate' ) as $ov_k => $ov_l ) : ?>
						<option value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $view['status'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_l ); ?></option>
					<?php endforeach; ?>
				</select>
				<select class="form-select form-select-sm" name="intent" aria-label="Intenție">
					<option value="">Orice intenție</option>
					<?php foreach ( Keyword_Research::INTENT_LABELS as $ov_k => $ov_l ) : ?>
						<option value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $view['intent'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_l ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="btn btn-sm btn-light" type="submit">Filtrează</button>
			</form>
			<?php if ( $ov_manage ) : ?>
				<form class="d-flex gap-2" method="post" action="<?php echo esc_url( ov_url( '/keywords' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input class="form-control form-control-sm" name="keyword" maxlength="190" placeholder="Cuvânt cheie nou" required aria-label="Cuvânt cheie nou">
					<select class="form-select form-select-sm w-auto" name="channel" aria-label="Canal"><option value="both">SEO + Ads</option><option value="seo">SEO</option><option value="ads">Ads</option></select>
					<button class="btn btn-sm btn-dark" type="submit">Adaugă</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-key" aria-hidden="true"></i><div class="ov-empty-title">Niciun cuvânt cheie</div>Rulați „Recalculează analiza” după ce adăugați servicii, zona deservită și conectați Search Console / Ads.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead>
					<tr>
						<th scope="col">Cuvânt cheie</th>
						<th scope="col">Intenție</th>
						<th scope="col" class="text-end">Afișări</th>
						<th scope="col" class="text-end">Click-uri</th>
						<th scope="col" class="text-end">Poz.</th>
						<th scope="col" class="text-end">Cost Ads</th>
						<th scope="col" class="text-end">Conv. Ads</th>
						<th scope="col" class="text-end">Apeluri (refuz.)</th>
						<th scope="col" class="text-end">Prioritate</th>
						<th scope="col">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_r ) : ?>
						<?php $ov_s = $ov_status[ $ov_r['status'] ]; ?>
						<tr>
							<td>
								<div class="ov-cell-main"><?php echo esc_html( $ov_r['keyword'] ); ?></div>
								<div class="ov-cell-sub"><?php echo esc_html( ( $ov_sources[ $ov_r['source'] ] ?? $ov_r['source'] ) . ' · ' . ( 'both' === $ov_r['channel'] ? 'SEO + Ads' : strtoupper( (string) $ov_r['channel'] ) ) ); ?></div>
							</td>
							<td><span class="ov-badge<?php echo 'emergency' === $ov_r['intent'] ? ' ov-badge-danger' : ''; ?>"><?php echo esc_html( Keyword_Research::INTENT_LABELS[ $ov_r['intent'] ] ?? $ov_r['intent'] ); ?></span></td>
							<td class="text-end"><?php echo esc_html( ov_number( $ov_r['gsc_impressions'] ) ); ?></td>
							<td class="text-end"><?php echo esc_html( ov_number( $ov_r['gsc_clicks'] ) ); ?></td>
							<td class="text-end"><?php echo esc_html( null !== $ov_r['gsc_position'] ? ov_number( $ov_r['gsc_position'], 1 ) : '—' ); ?></td>
							<td class="text-end text-nowrap"><?php echo esc_html( ov_number( (int) $ov_r['ads_cost_micros'] / 1000000, 0 ) ); ?></td>
							<td class="text-end"><?php echo esc_html( ov_number( $ov_r['ads_conversions'], 1 ) ); ?></td>
							<td class="text-end"><?php echo esc_html( (int) $ov_r['calls_total'] . ' (' . (int) $ov_r['calls_rejected'] . ')' ); ?></td>
							<td class="text-end fw-semibold"><?php echo esc_html( ov_number( $ov_r['priority_score'], 1 ) ); ?></td>
							<td>
								<?php if ( $ov_manage ) : ?>
									<form method="post" action="<?php echo esc_url( ov_url( '/keywords/' . (int) $ov_r['id'] . '/status' ) ); ?>" class="d-flex gap-1">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<input type="hidden" name="return" value="<?php echo esc_attr( $ov_return ); ?>">
										<?php if ( 'tracked' !== $ov_r['status'] ) : ?>
											<button class="btn btn-sm btn-light" name="status" value="tracked" type="submit" title="Urmărește"><i class="bi bi-star" aria-hidden="true"></i></button>
										<?php endif; ?>
										<?php if ( 'excluded' !== $ov_r['status'] ) : ?>
											<button class="btn btn-sm btn-light" name="status" value="excluded" type="submit" title="Exclude"><i class="bi bi-slash-circle" aria-hidden="true"></i></button>
										<?php else : ?>
											<button class="btn btn-sm btn-light" name="status" value="proposed" type="submit" title="Reactivează"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
										<?php endif; ?>
									</form>
								<?php else : ?>
									<span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
