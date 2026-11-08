<?php
/**
 * OptiVio — Insight cards list (shared).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view items, types, title, subtitle, can_manage, return, labels (data key => label).
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_items = array_values(
	array_filter(
		(array) $view['items'],
		static function ( array $i ) use ( $view ): bool {
			return in_array( $i['insight_type'], (array) $view['types'], true );
		}
	)
);
?>
<div class="ov-card h-100">
	<div class="ov-card-header">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( $view['title'] ); ?> <span class="ov-badge ms-1"><?php echo esc_html( (string) count( $ov_items ) ); ?></span></h2>
			<p class="ov-card-subtitle"><?php echo esc_html( $view['subtitle'] ); ?></p>
		</div>
	</div>
	<?php if ( empty( $ov_items ) ) : ?>
		<div class="ov-empty py-3"><i class="bi bi-check2-circle" aria-hidden="true"></i>Nimic de semnalat.</div>
	<?php else : ?>
		<ul class="ov-list">
			<?php foreach ( array_slice( $ov_items, 0, 12 ) as $ov_it ) : ?>
				<?php $ov_d = (array) $ov_it['data']; ?>
				<li class="align-items-start">
					<div class="ov-list-body">
						<div class="ov-list-title text-wrap"><?php echo esc_html( $ov_it['keyword'] ); ?></div>
						<div class="ov-list-sub text-wrap">
							<?php
							$ov_parts = array();
							foreach ( (array) $view['labels'] as $ov_key => $ov_label ) {
								if ( isset( $ov_d[ $ov_key ] ) && null !== $ov_d[ $ov_key ] ) {
									$ov_parts[] = $ov_label . ' ' . ( is_float( $ov_d[ $ov_key ] ) ? ov_number( $ov_d[ $ov_key ], 1 ) : $ov_d[ $ov_key ] );
								}
							}
							echo esc_html( implode( ' · ', $ov_parts ) );
							?>
						</div>
						<?php if ( ! empty( $ov_d['pages'] ) ) : ?>
							<ul class="small mb-1 mt-1 ps-3">
								<?php foreach ( (array) $ov_d['pages'] as $ov_p ) : ?>
									<li class="ov-cell-sub"><?php echo esc_html( preg_replace( '#^https?://[^/]+#', '', (string) $ov_p['page'] ) . ' — ' . $ov_p['impressions'] . ' afișări, poz. ' . $ov_p['position'] ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<div class="ov-cell-sub text-body mt-1"><i class="bi bi-arrow-return-right me-1" aria-hidden="true"></i><?php echo esc_html( (string) ( $ov_d['action'] ?? '' ) ); ?></div>
					</div>
					<?php if ( ! empty( $view['can_manage'] ) ) : ?>
						<form method="post" action="<?php echo esc_url( ov_url( '/insights/' . (int) $ov_it['id'] . '/dismiss' ) ); ?>">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="return" value="<?php echo esc_attr( $view['return'] ); ?>">
							<button class="btn btn-sm btn-light" type="submit" title="Ignoră" aria-label="Ignoră recomandarea"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
						</form>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
