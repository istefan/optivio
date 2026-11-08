<?php
/**
 * OptiVio — Action Cards list.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Actions\Cards;
use OptiVio\Core\View;

$ov_counts = array();
foreach ( $view['counts'] as $ov_c ) {
	$ov_counts[ $ov_c['status'] ] = (int) $ov_c['total'];
}
$ov_tabs = array(
	'open'        => 'De decis (' . ( ( $ov_counts['proposed'] ?? 0 ) + ( $ov_counts['approved'] ?? 0 ) + ( $ov_counts['failed'] ?? 0 ) ) . ')',
	'applied'     => 'Aplicate (' . ( $ov_counts['applied'] ?? 0 ) . ')',
	'rejected'    => 'Respinse (' . ( $ov_counts['rejected'] ?? 0 ) . ')',
	'rolled_back' => 'Anulate (' . ( $ov_counts['rolled_back'] ?? 0 ) . ')',
	'all'         => 'Toate',
);
?>
<?php View::partial( 'strategy/toolbar', array( 'current' => '/actions', 'can_manage' => false ) ); ?>

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
	<nav class="ov-tabs mb-0" aria-label="Status">
		<?php foreach ( $ov_tabs as $ov_k => $ov_l ) : ?>
			<a class="<?php echo $ov_k === $view['status'] ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/actions', array_filter( array( 'status' => $ov_k, 'category' => $view['category'] ) ) ) ); ?>"><?php echo esc_html( $ov_l ); ?></a>
		<?php endforeach; ?>
	</nav>
	<form class="ms-auto d-flex gap-2" method="get" action="<?php echo esc_url( ov_url( '/actions' ) ); ?>">
		<input type="hidden" name="status" value="<?php echo esc_attr( $view['status'] ); ?>">
		<select class="form-select form-select-sm" name="category" aria-label="Categorie">
			<option value="">Toate categoriile</option>
			<?php foreach ( Cards::CATEGORIES as $ov_k => $ov_c ) : ?>
				<option value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $view['category'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_c[0] ); ?></option>
			<?php endforeach; ?>
		</select>
		<button class="btn btn-sm btn-light" type="submit">Filtrează</button>
	</form>
</div>

<?php if ( empty( $view['rows'] ) ) : ?>
	<div class="ov-card"><div class="ov-empty"><i class="bi bi-card-checklist" aria-hidden="true"></i><div class="ov-empty-title">Niciun card</div>Rulați „Recalculează analiza” (carduri deterministe) sau o analiză AI.</div></div>
<?php else : ?>
	<div class="row g-3">
		<?php foreach ( $view['rows'] as $ov_r ) : ?>
			<?php $ov_s = Cards::STATUSES[ $ov_r['status'] ]; ?>
			<div class="col-12 col-md-6 col-xxl-4">
				<a class="ov-card d-block h-100 text-reset" href="<?php echo esc_url( ov_url( '/actions/' . (int) $ov_r['id'] ) ); ?>">
					<div class="d-flex justify-content-between align-items-start gap-2 mb-2">
						<span class="ov-badge ov-badge-outline"><?php echo esc_html( Cards::CATEGORIES[ $ov_r['category'] ][0] ); ?></span>
						<span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span>
					</div>
					<div class="fw-semibold mb-1"><?php echo esc_html( $ov_r['title'] ); ?></div>
					<div class="ov-cell-sub mb-2" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"><?php echo esc_html( (string) $ov_r['rationale'] ); ?></div>
					<div class="d-flex gap-2 ov-cell-sub">
						<span><i class="bi bi-flag me-1" aria-hidden="true"></i>prioritate <?php echo esc_html( (string) (int) $ov_r['priority'] ); ?></span>
						<span><i class="bi bi-graph-up-arrow me-1" aria-hidden="true"></i>impact <?php echo esc_html( (string) (int) $ov_r['impact_score'] ); ?></span>
						<span class="ms-auto"><?php echo esc_html( 'ai' === $ov_r['origin'] ? 'AI' : 'determinist' ); ?></span>
					</div>
				</a>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
