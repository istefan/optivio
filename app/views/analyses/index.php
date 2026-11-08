<?php
/**
 * OptiVio — AI analyses list.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_st = array(
	'collecting' => array( '', 'colectare' ),
	'ready'      => array( '', 'pregătită' ),
	'running'    => array( 'ov-badge-warning', 'rulează' ),
	'done'       => array( 'ov-badge-success', 'finalizată' ),
	'error'      => array( 'ov-badge-danger', 'eroare' ),
);
?>
<?php View::partial( 'strategy/toolbar', array( 'current' => '/analyses', 'can_manage' => false ) ); ?>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-8">
		<div class="ov-card h-100">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Analiză profundă multi-sursă</h2>
					<p class="ov-card-subtitle">Agregă determinist profilul, USP-ul, regulile, apelurile și veniturile, GSC, GA4, PageSpeed, Ads, Business Profile, conținutul site-ului, auditul, insight-urile și concurența, apoi cere modelului „Raționament / Strategie” un diagnostic și Action Cards.</p>
				</div>
			</div>
			<?php if ( ! empty( $view['can_manage'] ) ) : ?>
				<a class="btn btn-dark" href="<?php echo esc_url( ov_url( '/analyses/new' ) ); ?>"><i class="bi bi-stars me-1" aria-hidden="true"></i>Pregătește o analiză nouă</a>
				<p class="ov-cell-sub mt-2 mb-0">AI-ul rulează doar la confirmarea dumneavoastră; nimic nu se aplică pe site fără aprobare explicită pe fiecare card.</p>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-12 col-xl-4">
		<div class="ov-card ov-kpi h-100">
			<span class="ov-kpi-label">Cost AI luna aceasta (cheia globală)</span>
			<div class="ov-kpi-value"><?php echo esc_html( '$' . number_format( (float) $view['month_spend'], 2 ) ); ?></div>
			<div class="ov-kpi-prev"><?php echo esc_html( 'din bugetul de $' . number_format( (float) $view['budget'], 2 ) ); ?></div>
			<?php $ov_pct = $view['budget'] > 0 ? min( 100, (int) round( 100 * $view['month_spend'] / $view['budget'] ) ) : 0; ?>
			<div class="ov-progress mt-2"><span style="width: <?php echo esc_attr( (string) $ov_pct ); ?>%"></span></div>
		</div>
	</div>
</div>

<div class="ov-card">
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-stars" aria-hidden="true"></i>Nicio analiză încă.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th scope="col">#</th><th scope="col">Data</th><th scope="col">Cerută de</th><th scope="col">Model</th><th scope="col" class="text-end">Tokeni</th><th scope="col" class="text-end">Cost</th><th scope="col" class="text-center">Carduri</th><th scope="col">Stare</th></tr></thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_r ) : ?>
						<?php $ov_s = $ov_st[ $ov_r['status'] ] ?? array( '', $ov_r['status'] ); ?>
						<tr>
							<td><a class="ov-cell-main" href="<?php echo esc_url( ov_url( '/analyses/' . (int) $ov_r['id'] ) ); ?>"><?php echo esc_html( '#' . $ov_r['id'] ); ?></a></td>
							<td class="ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_r['created_at'] ) ); ?></td>
							<td><?php echo esc_html( $ov_r['display_name'] ?? '—' ); ?></td>
							<td class="ov-cell-sub"><?php echo esc_html( $ov_r['model'] ?? '—' ); ?></td>
							<td class="text-end ov-cell-sub"><?php echo esc_html( null !== $ov_r['prompt_tokens'] ? ov_number( (int) $ov_r['prompt_tokens'] + (int) $ov_r['completion_tokens'] ) : '—' ); ?></td>
							<td class="text-end"><?php echo esc_html( null !== $ov_r['cost_usd'] ? '$' . number_format( (float) $ov_r['cost_usd'], 4 ) : '—' ); ?></td>
							<td class="text-center"><?php echo esc_html( (string) (int) $ov_r['cards'] ); ?></td>
							<td><span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
