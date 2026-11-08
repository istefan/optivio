<?php
/**
 * OptiVio — AI analysis detail.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Actions\Cards;

$ov_a = (array) $view['analysis'];
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/analyses' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Analize AI</a>
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/actions' ) ); ?>">Action Cards</a>
</div>
<div class="row g-3">
	<div class="col-12 col-xl-7">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Diagnostic & plan</h2>
					<p class="ov-card-subtitle"><?php echo esc_html( ( $ov_a['model'] ?? '—' ) . ' · ' . ( null !== $ov_a['cost_usd'] ? '$' . number_format( (float) $ov_a['cost_usd'], 4 ) : '' ) . ' · ' . ( null !== $ov_a['duration_ms'] ? ov_number( (int) $ov_a['duration_ms'] / 1000, 1 ) . ' s' : '' ) . ' · ' . ( $ov_a['display_name'] ?? '' ) ); ?></p>
				</div>
			</div>
			<?php if ( 'error' === $ov_a['status'] ) : ?>
				<div class="ov-alert ov-alert-error mb-0"><i class="bi bi-x-octagon" aria-hidden="true"></i><div><?php echo esc_html( (string) $ov_a['output_text'] ); ?></div></div>
			<?php else : ?>
				<div class="small"><?php echo ov_markdown( (string) $ov_a['output_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ov_markdown() escapes before formatting. ?></div>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-12 col-xl-5">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title"><?php echo esc_html( count( $view['cards'] ) . ' Action Cards propuse' ); ?></h2></div>
			<?php if ( empty( $view['cards'] ) ) : ?>
				<p class="ov-cell-sub mb-0">Niciun card.</p>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['cards'] as $ov_c ) : ?>
						<?php $ov_s = Cards::STATUSES[ $ov_c['status'] ]; ?>
						<li>
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><?php echo esc_html( (string) (int) $ov_c['priority'] ); ?></span>
							<div class="ov-list-body"><a class="ov-list-title d-block" href="<?php echo esc_url( ov_url( '/actions/' . (int) $ov_c['id'] ) ); ?>"><?php echo esc_html( $ov_c['title'] ); ?></a><div class="ov-list-sub"><?php echo esc_html( Cards::CATEGORIES[ $ov_c['category'] ][0] ); ?></div></div>
							<span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</div>
