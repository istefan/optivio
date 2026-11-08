<?php
/**
 * OptiVio — Google data summary of a site (last 28 days).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_site = (array) $view['site'];
$ov_psi  = $view['psi'];
$ov_gtm  = $view['gtm'];
$ov_tbl  = static function ( string $title, string $sub, array $head, array $rows, callable $cells ): void {
	?>
	<div class="ov-card h-100">
		<div class="ov-card-header"><div><h2 class="ov-card-title"><?php echo esc_html( $title ); ?></h2><p class="ov-card-subtitle"><?php echo esc_html( $sub ); ?></p></div></div>
		<?php if ( empty( $rows ) ) : ?>
			<div class="ov-empty py-3"><i class="bi bi-bar-chart" aria-hidden="true"></i>Fără date sincronizate.</div>
		<?php else : ?>
			<div class="table-responsive">
				<table class="table ov-table">
					<thead><tr>
						<?php foreach ( $head as $ov_i => $ov_h ) : ?>
							<th scope="col"<?php echo $ov_i > 0 ? ' class="text-end"' : ''; ?>><?php echo esc_html( $ov_h ); ?></th>
						<?php endforeach; ?>
					</tr></thead>
					<tbody>
						<?php foreach ( $rows as $ov_r ) : ?>
							<tr>
								<?php foreach ( $cells( $ov_r ) as $ov_i => $ov_c ) : ?>
									<td class="<?php echo $ov_i > 0 ? 'text-end text-nowrap' : 'ov-truncate'; ?>"><?php echo esc_html( (string) $ov_c ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
	<?php
};
$ov_kpi  = array(
	array( 'Click-uri organice (GSC)', ov_number( $view['gsc_totals']['clicks'] ?? 0 ), 'poziție medie ' . ( null !== ( $view['gsc_totals']['position'] ?? null ) ? ov_number( $view['gsc_totals']['position'], 1 ) : '—' ) ),
	array( 'Afișări Google (GSC)', ov_compact_number( $view['gsc_totals']['impressions'] ?? 0 ), 'Search Console' ),
	array( 'Apeluri din Google Maps', ov_number( $view['gbp']['calls'] ?? 0 ), ov_number( $view['gbp']['directions'] ?? 0 ) . ' cereri de direcții (GBP)' ),
	array( 'Performanță mobil (PSI)', null !== $ov_psi ? (int) $ov_psi['performance_score'] . '/100' : '—', null !== $ov_psi ? 'LCP ' . ov_number( (int) $ov_psi['lcp_ms'] / 1000, 1 ) . ' s · CLS ' . $ov_psi['cls'] : 'neauditat' ),
);
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_site['id'] ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?php echo esc_html( $ov_site['name'] ); ?></a>
</div>

<div class="row g-3 mb-3">
	<?php foreach ( $ov_kpi as $ov_i => $ov_k ) : ?>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="ov-card ov-kpi<?php echo 0 === $ov_i ? ' ov-kpi--focus' : ''; ?>">
				<span class="ov-kpi-label"><?php echo esc_html( $ov_k[0] ); ?></span>
				<div class="ov-kpi-value"><?php echo esc_html( $ov_k[1] ); ?></div>
				<div class="ov-kpi-prev"><?php echo esc_html( $ov_k[2] ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-7">
		<?php
		$ov_tbl(
			'Interogări Search Console',
			'Coloana „Pagini” > 1 = posibilă canibalizare (analizată în Etapa 6)',
			array( 'Interogare', 'Click-uri', 'Afișări', 'Poziție', 'Pagini' ),
			(array) $view['gsc_queries'],
			static function ( array $r ): array {
				return array( $r['query'], ov_number( $r['clicks'] ), ov_number( $r['impressions'] ), ov_number( (float) $r['position'], 1 ), (int) $r['pages'] );
			}
		);
		?>
	</div>
	<div class="col-12 col-xl-5">
		<?php
		$ov_tbl(
			'Pagini cu cele mai multe click-uri',
			'Search Console',
			array( 'Pagină', 'Click-uri', 'Afișări' ),
			(array) $view['gsc_pages'],
			static function ( array $r ): array {
				return array( (string) preg_replace( '#^https?://[^/]+#', '', (string) $r['page'] ) ?: '/', ov_number( $r['clicks'] ), ov_number( $r['impressions'] ) );
			}
		);
		?>
	</div>
</div>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-7">
		<?php
		$ov_tbl(
			'Campanii Google Ads',
			sprintf( '%d cuvinte cheie active · %d cuvinte negative', (int) $view['ads_keywords'], (int) $view['ads_negatives'] ),
			array( 'Campanie', 'Click-uri', 'Cost', 'Conversii', 'CPA' ),
			(array) $view['ads_campaigns'],
			static function ( array $r ): array {
				return array( $r['name'] . ' · ' . strtolower( (string) $r['bidding_strategy'] ), ov_number( (int) $r['clicks'] ), ov_number( (float) $r['cost'], 0 ), ov_number( (float) $r['conversions'], 1 ), (float) $r['conversions'] > 0 ? ov_number( (float) $r['cost'] / (float) $r['conversions'], 0 ) : '—' );
			}
		);
		?>
	</div>
	<div class="col-12 col-xl-5">
		<?php
		$ov_tbl(
			'Termeni de căutare plătiți',
			'Top cost — fără conversii = candidați pentru cuvinte negative',
			array( 'Termen', 'Click-uri', 'Cost', 'Conv.' ),
			(array) $view['ads_terms'],
			static function ( array $r ): array {
				return array( $r['search_term'], ov_number( (int) $r['clicks'] ), ov_number( (float) $r['cost'], 0 ), ov_number( (float) $r['conversions'], 0 ) );
			}
		);
		?>
	</div>
</div>

<div class="row g-3">
	<div class="col-12 col-xl-5">
		<?php
		$ov_tbl(
			'Surse de trafic (GA4)',
			'Sesiuni și evenimente cheie',
			array( 'Sursă / mediu', 'Sesiuni', 'Angajate', 'Conv.' ),
			(array) $view['ga4_sources'],
			static function ( array $r ): array {
				return array( $r['source'] . ' / ' . $r['medium'], ov_number( (int) $r['sessions'] ), ov_number( (int) $r['engaged'] ), ov_number( (float) $r['conversions'], 0 ) );
			}
		);
		?>
	</div>
	<div class="col-12 col-xl-4">
		<div class="ov-card h-100">
			<div class="ov-card-header"><div><h2 class="ov-card-title">PageSpeed</h2><p class="ov-card-subtitle"><?php echo esc_html( null !== $ov_psi ? $ov_psi['strategy'] . ' · ' . ov_datetime( $ov_psi['fetched_at'] ) : 'neauditat' ); ?></p></div></div>
			<?php if ( null !== $ov_psi ) : ?>
				<dl class="ov-dl mb-3">
					<dt>Performanță / SEO</dt><dd><?php echo esc_html( $ov_psi['performance_score'] . ' / ' . $ov_psi['seo_score'] ); ?></dd>
					<dt>Accesibilitate / BP</dt><dd><?php echo esc_html( $ov_psi['accessibility_score'] . ' / ' . $ov_psi['best_practices_score'] ); ?></dd>
					<dt>LCP · INP · CLS</dt><dd><?php echo esc_html( ov_number( (int) $ov_psi['lcp_ms'] / 1000, 1 ) . ' s · ' . ( $ov_psi['inp_ms'] ?? '—' ) . ' ms · ' . $ov_psi['cls'] ); ?></dd>
					<dt>TTFB · TBT</dt><dd><?php echo esc_html( $ov_psi['ttfb_ms'] . ' ms · ' . $ov_psi['tbt_ms'] . ' ms' ); ?></dd>
				</dl>
				<?php foreach ( (array) json_decode( (string) $ov_psi['opportunities'], true ) as $ov_o ) : ?>
					<div class="d-flex justify-content-between ov-cell-sub py-1"><span><?php echo esc_html( $ov_o['title'] ); ?></span><span class="text-body"><?php echo esc_html( ov_number( (int) $ov_o['savings_ms'] / 1000, 1 ) . ' s' ); ?></span></div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-12 col-xl-3 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Tag Manager</h2></div>
			<?php if ( null === $ov_gtm ) : ?>
				<p class="ov-cell-sub mb-0">Fără snapshot.</p>
			<?php else : ?>
				<?php $ov_sum = (array) json_decode( (string) $ov_gtm['summary'], true ); ?>
				<dl class="ov-dl mb-0">
					<dt>Container</dt><dd><?php echo esc_html( $ov_gtm['container_id'] . ' v' . $ov_gtm['version_id'] ); ?></dd>
					<dt>Taguri</dt><dd><?php echo esc_html( (string) $ov_gtm['tags_count'] ); ?></dd>
					<dt>GA4</dt><dd><?php echo esc_html( ! empty( $ov_sum['has_ga4'] ) ? 'da' : 'lipsă' ); ?></dd>
					<dt>Conversii Ads</dt><dd><?php echo esc_html( ! empty( $ov_sum['has_ads_conv'] ) ? 'da' : 'lipsă' ); ?></dd>
				</dl>
			<?php endif; ?>
		</div>
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">AdSense</h2></div>
			<dl class="ov-dl mb-0">
				<dt>Afișări pagină</dt><dd><?php echo esc_html( ov_number( $view['adsense']['views'] ?? 0 ) ); ?></dd>
				<dt>Câștig estimat</dt><dd><?php echo esc_html( ov_number( (float) ( $view['adsense']['earnings'] ?? 0 ), 2 ) ); ?></dd>
			</dl>
		</div>
	</div>
</div>
