<?php
/**
 * OptiVio — Competitor comparison for a keyword vs. a client page.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_r = $view['result'];
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/competitors' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Concurență</a>
	<form class="d-flex flex-wrap gap-2 ms-auto" method="get" action="<?php echo esc_url( ov_url( '/competitors/compare' ) ); ?>">
		<select class="form-select form-select-sm w-auto" name="keyword" aria-label="Cuvânt cheie">
			<?php foreach ( $view['keywords'] as $ov_k ) : ?>
				<option value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $view['keyword'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_k ); ?></option>
			<?php endforeach; ?>
		</select>
		<select class="form-select form-select-sm w-auto" name="content_id" aria-label="Pagina proprie">
			<option value="0">— pagina proprie —</option>
			<?php foreach ( $view['contents'] as $ov_c ) : ?>
				<option value="<?php echo esc_attr( (string) $ov_c['id'] ); ?>"<?php echo (int) $ov_c['id'] === (int) $view['content_id'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_c['site_name'] . ' › ' . $ov_c['title'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn btn-sm btn-dark" type="submit">Compară</button>
	</form>
</div>

<?php if ( null === $ov_r ) : ?>
	<div class="ov-card"><div class="ov-empty"><i class="bi bi-intersect" aria-hidden="true"></i>Alegeți un cuvânt cheie pentru care există pagini concurente scanate.</div></div>
<?php elseif ( empty( $ov_r['pages'] ) ) : ?>
	<div class="ov-card"><div class="ov-empty"><i class="bi bi-radar" aria-hidden="true"></i>Paginile concurente pentru acest cuvânt nu au fost scanate încă.</div></div>
<?php else : ?>
	<?php $ov_cl = $ov_r['client']; ?>
	<div class="row g-3 mb-3">
		<?php
		foreach ( array(
			array( 'Cuvinte (mediana concurenților)', ov_number( $ov_r['median_words'] ), null !== $ov_cl ? 'pagina proprie: ' . ov_number( $ov_cl['word_count'] ) : 'alegeți pagina proprie' ),
			array( 'H2 în medie', ov_number( $ov_r['avg_h2'], 1 ), count( $ov_r['pages'] ) . ' pagini concurente analizate' ),
			array( 'Termeni lipsă', (string) count( $ov_r['term_gaps'] ), 'folosiți de ≥ jumătate din concurenți' ),
			array( 'Cuvântul în titlu', null === $ov_cl ? '—' : ( $ov_r['client_has_kw'] ? 'da' : 'nu' ), 'titlu / titlu SEO al paginii proprii' ),
		) as $ov_i => $ov_k ) :
			?>
			<div class="col-6 col-xl-3"><div class="ov-card ov-kpi<?php echo 0 === $ov_i ? ' ov-kpi--focus' : ''; ?>"><span class="ov-kpi-label"><?php echo esc_html( $ov_k[0] ); ?></span><div class="ov-kpi-value"><?php echo esc_html( $ov_k[1] ); ?></div><div class="ov-kpi-prev"><?php echo esc_html( $ov_k[2] ); ?></div></div></div>
		<?php endforeach; ?>
	</div>

	<div class="row g-3 mb-3">
		<div class="col-12 col-xl-5">
			<div class="ov-card h-100">
				<div class="ov-card-header"><div><h2 class="ov-card-title">Subiecte acoperite de concurenți, lipsă la dvs.</h2><p class="ov-card-subtitle">Termeni (uni/bi-grame) prezenți la ≥ 50% din pagini</p></div></div>
				<?php if ( null === $ov_cl ) : ?>
					<p class="ov-cell-sub mb-0">Alegeți pagina proprie pentru a calcula diferențele.</p>
				<?php elseif ( empty( $ov_r['term_gaps'] ) ) : ?>
					<p class="ov-cell-sub mb-0">Pagina acoperă termenii comuni ai concurenților.</p>
				<?php else : ?>
					<div class="d-flex flex-wrap gap-1">
						<?php foreach ( $ov_r['term_gaps'] as $ov_term => $ov_n ) : ?>
							<span class="ov-badge ov-badge-outline"><?php echo esc_html( $ov_term . ' · ' . $ov_n ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<div class="col-12 col-xl-7">
			<div class="ov-card h-100">
				<div class="ov-card-header"><div><h2 class="ov-card-title">Secțiuni H2 ale concurenților neacoperite</h2><p class="ov-card-subtitle">Idei de structură pentru pagina proprie</p></div></div>
				<?php if ( null === $ov_cl || empty( $ov_r['heading_gaps'] ) ) : ?>
					<p class="ov-cell-sub mb-0"><?php echo esc_html( null === $ov_cl ? 'Alegeți pagina proprie.' : 'Nicio secțiune lipsă.' ); ?></p>
				<?php else : ?>
					<ul class="ov-list">
						<?php foreach ( $ov_r['heading_gaps'] as $ov_h ) : ?>
							<li><div class="ov-list-body"><div class="ov-list-title text-wrap"><?php echo esc_html( $ov_h['text'] ); ?></div><div class="ov-list-sub"><?php echo esc_html( $ov_h['domain'] ); ?></div></div></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="ov-card">
		<div class="ov-card-header"><div><h2 class="ov-card-title">Pagini concurente</h2><p class="ov-card-subtitle"><?php echo esc_html( 'Schema.org folosit: ' . ( implode( ', ', array_map( static fn( $t, $n ) => $t . ' (' . $n . ')', array_keys( $ov_r['schema_counts'] ), $ov_r['schema_counts'] ) ) ?: '—' ) ); ?></p></div></div>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th scope="col">Poz.</th><th scope="col">Pagină</th><th scope="col">H1</th><th scope="col" class="text-end">Cuvinte</th><th scope="col" class="text-end">H2</th></tr></thead>
				<tbody>
					<?php if ( null !== $ov_cl ) : ?>
						<tr class="table-light">
							<td>—</td>
							<td><div class="ov-cell-main">Pagina proprie</div><div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_cl['seo_title_resolved'] ?? $ov_cl['title'] ); ?></div></td>
							<td class="ov-truncate"><?php echo esc_html( (string) ( ( (array) json_decode( (string) $ov_cl['headings'], true ) )[0]['text'] ?? '—' ) ); ?></td>
							<td class="text-end"><?php echo esc_html( ov_number( $ov_cl['word_count'] ) ); ?></td>
							<td class="text-end"><?php echo esc_html( (string) count( array_filter( (array) json_decode( (string) $ov_cl['headings'], true ), static fn( $h ) => 2 === (int) ( $h['level'] ?? 0 ) ) ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php foreach ( $ov_r['pages'] as $ov_p ) : ?>
						<tr>
							<td><?php echo esc_html( null !== $ov_p['serp_position'] ? '#' . $ov_p['serp_position'] : '—' ); ?></td>
							<td><div class="ov-cell-main"><?php echo esc_html( $ov_p['domain'] ); ?></div><div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_p['title'] ?? $ov_p['url'] ); ?></div></td>
							<td class="ov-truncate"><?php echo esc_html( $ov_p['h1'] ?? '—' ); ?></td>
							<td class="text-end"><?php echo esc_html( ov_number( $ov_p['word_count'] ) ); ?></td>
							<td class="text-end"><?php echo esc_html( (string) $ov_p['h2_count'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
