<?php
/**
 * OptiVio — Google Ads analysis & strategy.
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
$ov_st     = (array) $view['strategy'];
$ov_lvl    = array(
	'critical' => 'ov-badge-danger',
	'warning'  => 'ov-badge-warning',
	'info'     => '',
);
$ov_list   = static function ( array $types, string $title, string $sub, array $labels ) use ( $view, $ov_manage ): void {
	View::partial(
		'strategy/insight-list',
		array(
			'items'      => $view['insights'],
			'types'      => $types,
			'title'      => $title,
			'subtitle'   => $sub,
			'labels'     => $labels,
			'can_manage' => $ov_manage,
			'return'     => '/ads',
		)
	);
};
?>
<?php View::partial( 'strategy/toolbar', array( 'current' => '/ads', 'can_manage' => $ov_manage ) ); ?>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-5">
		<div class="ov-card h-100">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Strategie recomandată</h2>
					<p class="ov-card-subtitle"><?php echo esc_html( ov_business_model_label( (string) $ov_st['model'] ) . ' · ultimele 30 de zile: ' . (int) $ov_st['calls']['accepted'] . ' apeluri acceptate, ' . (int) $ov_st['calls']['rejected'] . ' refuzate' ); ?></p>
				</div>
			</div>
			<ul class="ov-list ov-checklist">
				<?php foreach ( $ov_st['principles'] as $ov_p ) : ?>
					<li class="align-items-start"><span class="ov-check ov-check-done"><i class="bi bi-lightbulb" aria-hidden="true"></i></span><span class="ov-list-sub text-body text-wrap"><?php echo esc_html( $ov_p ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<div class="col-12 col-xl-7">
		<div class="ov-card h-100">
			<div class="ov-card-header"><div><h2 class="ov-card-title">Campanii</h2><p class="ov-card-subtitle">Strategia de licitare față de modelul de afacere și volumul de conversii</p></div></div>
			<?php if ( empty( $ov_st['campaigns'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-badge-ad" aria-hidden="true"></i>Nicio campanie sincronizată (conectați Google Ads în pagina site-ului).</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $ov_st['campaigns'] as $ov_c ) : ?>
						<li class="align-items-start">
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( $ov_c['name'] ); ?> <span class="ov-badge ms-1"><?php echo esc_html( strtolower( str_replace( '_', ' ', (string) $ov_c['bidding_strategy'] ) ) ); ?></span></div>
								<div class="ov-list-sub"><?php echo esc_html( $ov_c['site_name'] . ' · ' . ov_number( (int) $ov_c['clicks'] ) . ' click-uri · cost ' . ov_number( (float) $ov_c['cost'], 0 ) . ' · ' . ov_number( (float) $ov_c['conversions'], 1 ) . ' conversii' ); ?></div>
								<?php foreach ( $ov_c['advice'] as $ov_a ) : ?>
									<div class="mt-1 small"><span class="ov-badge <?php echo esc_attr( $ov_lvl[ $ov_a['level'] ] ?? '' ); ?> me-1"><?php echo esc_html( $ov_a['level'] ); ?></span><?php echo esc_html( $ov_a['text'] ); ?></div>
								<?php endforeach; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="row g-3 mb-3">
	<div class="col-12 col-xl-4"><?php $ov_list( array( 'ads_wasted_term', 'rejected_calls_keyword' ), 'Candidați cuvinte negative', 'Cost fără conversii sau apeluri majoritar refuzate', array( 'ads_cost' => 'cost', 'ads_clicks' => 'click-uri', 'calls' => 'apeluri', 'bad_share' => '% refuzate' ) ); ?></div>
	<div class="col-12 col-xl-4"><?php $ov_list( array( 'paid_while_top_organic' ), 'Plătim deși suntem top organic', 'Termeni plătiți unde rankați deja în top 3', array( 'ads_cost' => 'cost', 'organic_position' => 'poz. organică', 'organic_clicks' => 'click-uri organice' ) ); ?></div>
	<div class="col-12 col-xl-4"><?php $ov_list( array( 'ads_converting_no_landing' ), 'Convertesc în Ads, fără pagină SEO', 'Oportunități pentru pagini / landing page-uri noi', array( 'ads_conversions' => 'conversii', 'ads_cost' => 'cost', 'organic_position' => 'poz. organică' ) ); ?></div>
</div>

<div class="row g-3">
	<div class="col-12 col-xl-5"><?php $ov_list( array( 'ads_bad_hours' ), 'Program reclame (Ad Schedule)', 'Ore cu ≥ 60% apeluri refuzate/pierdute și costul Ads aferent', array( 'calls' => 'apeluri', 'bad_share' => '% refuzate', 'ads_spend' => 'cost Ads' ) ); ?></div>
	<div class="col-12 col-xl-7">
		<div class="ov-card h-100">
			<div class="ov-card-header"><div><h2 class="ov-card-title">Cuvinte negative existente</h2><p class="ov-card-subtitle"><?php echo esc_html( count( $view['negatives'] ) . ' la nivel de campanie / grup' ); ?></p></div></div>
			<?php if ( empty( $view['negatives'] ) ) : ?>
				<p class="ov-cell-sub mb-0">Niciun cuvânt negativ.</p>
			<?php else : ?>
				<div class="d-flex flex-wrap gap-1">
					<?php foreach ( $view['negatives'] as $ov_n ) : ?>
						<span class="ov-badge ov-badge-outline" title="<?php echo esc_attr( $ov_n['campaign'] ); ?>"><?php echo esc_html( '-' . $ov_n['keyword_text'] . ' (' . $ov_n['match_type'] . ')' ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
