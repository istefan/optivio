<?php
/**
 * OptiVio — SEO audit.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Engine\Seo_Auditor;

$ov_sev   = array(
	'critical' => array( 'ov-badge-danger', 'critic' ),
	'warning'  => array( 'ov-badge-warning', 'atenție' ),
	'info'     => array( '', 'info' ),
);
$ov_total = array(
	'critical' => 0,
	'warning'  => 0,
	'info'     => 0,
);
foreach ( $view['counts'] as $ov_c ) {
	$ov_total[ $ov_c['severity'] ] += (int) $ov_c['total'];
}
?>
<?php View::partial( 'strategy/toolbar', array( 'current' => '/seo-audit', 'can_manage' => $view['can_manage'] ) ); ?>

<div class="row g-3 mb-3">
	<?php
	foreach ( array(
		array( 'Critice', $ov_total['critical'], 'bi-exclamation-octagon' ),
		array( 'Atenționări', $ov_total['warning'], 'bi-exclamation-triangle' ),
		array( 'Informative', $ov_total['info'], 'bi-info-circle' ),
		array( 'Rezolvate (30 zile)', (int) $view['resolved'], 'bi-check2-circle' ),
	) as $ov_i => $ov_k ) :
		?>
		<div class="col-6 col-xl-3">
			<div class="ov-card ov-kpi<?php echo 0 === $ov_i ? ' ov-kpi--focus' : ''; ?>">
				<div class="d-flex justify-content-between"><span class="ov-kpi-label"><?php echo esc_html( $ov_k[0] ); ?></span><span class="ov-kpi-icon"><i class="bi <?php echo esc_attr( $ov_k[2] ); ?>" aria-hidden="true"></i></span></div>
				<div class="ov-kpi-value"><?php echo esc_html( ov_number( $ov_k[1] ) ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<div class="row g-3">
	<div class="col-12 col-xl-4">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Probleme pe tipuri</h2></div>
			<form method="get" action="<?php echo esc_url( ov_url( '/seo-audit' ) ); ?>" class="mb-3">
				<select class="form-select form-select-sm" name="site" aria-label="Site">
					<option value="0">Toate site-urile</option>
					<?php foreach ( $view['sites'] as $ov_s ) : ?>
						<option value="<?php echo esc_attr( (string) $ov_s['id'] ); ?>"<?php echo (int) $ov_s['id'] === (int) $view['site_id'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_s['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="hidden" name="code" value="<?php echo esc_attr( $view['code'] ); ?>">
				<button class="btn btn-sm btn-light w-100 mt-2" type="submit">Aplică</button>
			</form>
			<?php if ( empty( $view['counts'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-clipboard2-check" aria-hidden="true"></i>Nicio problemă deschisă. Rulați „Recalculează analiza” după sincronizarea conținutului.</div>
			<?php else : ?>
				<ul class="ov-list">
					<li><a class="ov-list-title<?php echo '' === $view['code'] ? ' text-decoration-underline' : ''; ?>" href="<?php echo esc_url( ov_url( '/seo-audit', array_filter( array( 'site' => $view['site_id'] ) ) ) ); ?>">Toate</a></li>
					<?php foreach ( $view['counts'] as $ov_c ) : ?>
						<?php $ov_b = $ov_sev[ $ov_c['severity'] ]; ?>
						<li>
							<div class="ov-list-body"><a class="ov-list-title d-block<?php echo $ov_c['issue_code'] === $view['code'] ? ' text-decoration-underline' : ''; ?>" href="<?php echo esc_url( ov_url( '/seo-audit', array_filter( array( 'site' => $view['site_id'], 'code' => $ov_c['issue_code'] ) ) ) ); ?>"><?php echo esc_html( Seo_Auditor::ISSUES[ $ov_c['issue_code'] ][1] ?? $ov_c['issue_code'] ); ?></a></div>
							<span class="ov-badge <?php echo esc_attr( $ov_b[0] ); ?>"><?php echo esc_html( (string) (int) $ov_c['total'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-12 col-xl-8">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title"><?php echo esc_html( '' !== $view['code'] && isset( Seo_Auditor::ISSUES[ $view['code'] ] ) ? Seo_Auditor::ISSUES[ $view['code'] ][1] : 'Toate problemele deschise' ); ?></h2>
					<?php if ( '' !== $view['code'] && isset( Seo_Auditor::ISSUES[ $view['code'] ] ) ) : ?>
						<p class="ov-card-subtitle"><?php echo esc_html( Seo_Auditor::ISSUES[ $view['code'] ][2] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( empty( $view['issues'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-check2-circle" aria-hidden="true"></i>Nimic de afișat.</div>
			<?php else : ?>
				<div class="table-responsive">
					<table class="table ov-table">
						<thead><tr><th scope="col">Problemă</th><th scope="col">Pagină</th><th scope="col">Detalii</th><th scope="col">Severitate</th></tr></thead>
						<tbody>
							<?php foreach ( $view['issues'] as $ov_i ) : ?>
								<?php
								$ov_d = (array) ( json_decode( (string) $ov_i['details'], true ) ?: array() );
								$ov_b = $ov_sev[ $ov_i['severity'] ];
								unset( $ov_d['others'] );
								?>
								<tr>
									<td><div class="ov-cell-main"><?php echo esc_html( Seo_Auditor::ISSUES[ $ov_i['issue_code'] ][1] ?? $ov_i['issue_code'] ); ?></div><div class="ov-cell-sub"><?php echo esc_html( $ov_i['site_name'] ); ?></div></td>
									<td class="ov-truncate">
										<?php if ( null !== $ov_i['content_id'] ) : ?>
											<a href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_i['site_id'] . '/content/' . (int) $ov_i['content_id'] ) ); ?>"><?php echo esc_html( $ov_i['title'] ?: $ov_i['url'] ); ?></a>
										<?php else : ?>
											<span class="ov-cell-sub">întregul site</span>
										<?php endif; ?>
									</td>
									<td class="ov-cell-sub ov-truncate">
										<?php
										$ov_parts = array();
										foreach ( $ov_d as $ov_k => $ov_v ) {
											$ov_parts[] = $ov_k . ': ' . ( is_scalar( $ov_v ) ? $ov_v : (string) json_encode( $ov_v, JSON_UNESCAPED_UNICODE ) );
										}
										echo esc_html( implode( ' · ', $ov_parts ) );
										?>
									</td>
									<td><span class="ov-badge <?php echo esc_attr( $ov_b[0] ); ?>"><?php echo esc_html( $ov_b[1] ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
