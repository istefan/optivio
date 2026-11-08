<?php
/**
 * OptiVio — Table of perception gaps (perception vs reality).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view gaps.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Actions\Cards;
use OptiVio\Onboarding\Perception_Gaps;

$ov_gaps = (array) ( $view['gaps'] ?? array() );
?>
<?php if ( empty( $ov_gaps ) ) : ?>
	<div class="ov-empty"><i class="bi bi-check2-all" aria-hidden="true"></i>Nicio diferență calculată.</div>
<?php else : ?>
	<div class="table-responsive">
		<table class="table ov-table">
			<thead><tr><th>Aspect</th><th>Realitate</th><th>Ce a înțeles botul</th><th>Rezultat</th><th>Recomandare</th></tr></thead>
			<tbody>
				<?php foreach ( $ov_gaps as $ov_g ) : ?>
					<?php $ov_t = Perception_Gaps::TYPES[ $ov_g['gap_type'] ] ?? array( $ov_g['gap_type'], '' ); ?>
					<tr>
						<td class="text-nowrap"><?php echo esc_html( Perception_Gaps::FIELDS[ $ov_g['field'] ] ?? $ov_g['field'] ); ?></td>
						<td><?php echo esc_html( mb_strimwidth( (string) ( $ov_g['confirmed_value'] ?? '—' ), 0, 160, '…' ) ); ?></td>
						<td>
							<?php echo esc_html( mb_strimwidth( (string) ( $ov_g['ai_value'] ?? '—' ), 0, 200, '…' ) ); ?>
							<?php if ( '' !== (string) $ov_g['evidence'] ) : ?>
								<div class="ov-cell-sub fst-italic">„<?php echo esc_html( (string) $ov_g['evidence'] ); ?>”</div>
							<?php endif; ?>
							<?php if ( '' !== (string) $ov_g['source_url'] ) : ?>
								<a class="ov-cell-sub" href="<?php echo esc_url( (string) $ov_g['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">sursa</a>
							<?php endif; ?>
						</td>
						<td><span class="ov-badge <?php echo esc_attr( $ov_t[1] ); ?>"><?php echo esc_html( $ov_t[0] ); ?></span></td>
						<td>
							<?php if ( null !== $ov_g['action_card_id'] ) : ?>
								<a href="<?php echo esc_url( ov_url( '/actions/' . (int) $ov_g['action_card_id'] ) ); ?>"><?php echo esc_html( mb_strimwidth( (string) $ov_g['card_title'], 0, 70, '…' ) ); ?></a>
								<div class="ov-cell-sub"><?php echo esc_html( Cards::STATUSES[ $ov_g['card_status'] ][1] ?? (string) $ov_g['card_status'] ); ?></div>
							<?php else : ?>
								<span class="ov-cell-sub">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
