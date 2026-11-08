<?php
/**
 * OptiVio — Business tab "Percepție": how a bot understands the business, over time.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_b     = (array) $view['business'];
$ov_bid   = (int) $ov_b['id'];
$ov_cur   = $view['current'];
$ov_base  = $view['baseline'];
$ov_src   = array(
	'external' => 'din exterior',
	'internal' => 'din interior',
);
$ov_conn  = array(
	'not_installed' => 'plugin neinstalat',
	'pending'       => 'aștept heartbeat',
	'connected'     => 'conectat',
	'error'         => 'conector inactiv',
	'revoked'       => 'cheie revocată',
);
// Gaps that changed compared with the baseline.
$ov_changes = array();
if ( ! empty( $view['base_gaps'] ) ) {
	$ov_before = array();
	foreach ( $view['base_gaps'] as $ov_g ) {
		$ov_before[ $ov_g['field'] . '|' . $ov_g['item_key'] ] = $ov_g['gap_type'];
	}
	foreach ( $view['gaps'] as $ov_g ) {
		$ov_k = $ov_g['field'] . '|' . $ov_g['item_key'];
		if ( isset( $ov_before[ $ov_k ] ) && $ov_before[ $ov_k ] !== $ov_g['gap_type'] ) {
			$ov_changes[] = array( $ov_g, $ov_before[ $ov_k ] );
		}
	}
}
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3">
		<?php if ( null === $ov_cur ) : ?>
			<div class="ov-card">
				<div class="ov-empty">
					<i class="bi bi-robot" aria-hidden="true"></i>
					<div class="ov-empty-title">Nicio percepție AI încă</div>
					Percepția arată cum înțelege un bot afacerea din site. Rulați-o din exterior (Super Admin) sau, după instalarea pluginului, din interior.
				</div>
			</div>
		<?php else : ?>
			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Percepția <?php echo esc_html( $ov_src[ $ov_cur['source'] ] ?? '' ); ?><?php echo ! empty( $ov_cur['is_baseline'] ) ? ' · amprenta inițială' : ''; ?></h2>
						<p class="ov-card-subtitle"><?php echo esc_html( sprintf( '%s · %s · %d pagini · %s caractere%s', ov_datetime( (string) $ov_cur['created_at'] ), (string) $ov_cur['model'], (int) $ov_cur['pages_count'], ov_number( (int) $ov_cur['input_chars'] ), ! empty( $ov_cur['truncated'] ) ? ' (trunchiat)' : '' ) ); ?></p>
					</div>
					<span class="ov-clarity"><?php echo esc_html( (string) (int) $ov_cur['clarity_score'] ); ?>%<small>claritate</small></span>
				</div>
				<p class="mb-2"><?php echo esc_html( (string) ( $ov_cur['result']['summary'] ?? '' ) ); ?></p>
				<?php if ( null !== $ov_base && (int) $ov_base['id'] !== (int) $ov_cur['id'] ) : ?>
					<?php $ov_delta = (int) $ov_cur['clarity_score'] - (int) $ov_base['clarity_score']; ?>
					<p class="ov-cell-sub mb-0">
						Față de amprenta inițială (<?php echo esc_html( ov_datetime( (string) $ov_base['created_at'], 'd.m.Y' ) ); ?>, <?php echo esc_html( (string) (int) $ov_base['clarity_score'] ); ?>%):
						<span class="ov-delta <?php echo esc_attr( $ov_delta > 0 ? 'ov-delta-up' : ( $ov_delta < 0 ? 'ov-delta-down' : 'ov-delta-flat' ) ); ?>"><?php echo esc_html( ( $ov_delta > 0 ? '+' : '' ) . $ov_delta . ' puncte' ); ?></span>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $ov_changes ) ) : ?>
				<div class="ov-card">
					<div class="ov-card-header"><h2 class="ov-card-title">Ce s-a schimbat față de amprenta inițială</h2></div>
					<ul class="ov-list mb-0">
						<?php foreach ( $ov_changes as [ $ov_g, $ov_was ] ) : ?>
							<li class="d-flex flex-wrap gap-2 align-items-center">
								<span class="flex-grow-1"><?php echo esc_html( ( \OptiVio\Onboarding\Perception_Gaps::FIELDS[ $ov_g['field'] ] ?? $ov_g['field'] ) . ( null !== $ov_g['confirmed_value'] ? ': ' . $ov_g['confirmed_value'] : '' ) ); ?></span>
								<span class="ov-badge ov-badge-outline"><?php echo esc_html( \OptiVio\Onboarding\Perception_Gaps::TYPES[ $ov_was ][0] ?? $ov_was ); ?></span>
								<i class="bi bi-arrow-right" aria-hidden="true"></i>
								<span class="ov-badge <?php echo esc_attr( \OptiVio\Onboarding\Perception_Gaps::TYPES[ $ov_g['gap_type'] ][1] ?? '' ); ?>"><?php echo esc_html( \OptiVio\Onboarding\Perception_Gaps::TYPES[ $ov_g['gap_type'] ][0] ?? $ov_g['gap_type'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Percepție vs realitate</h2>
						<p class="ov-card-subtitle">Diferențele devin Action Cards deterministe (fără AI). Cardurile nu se dublează la re-analize.</p>
					</div>
				</div>
				<?php View::partial( 'partials/perception-gaps', array( 'gaps' => $view['gaps'] ) ); ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Percepție din interior</h2>
					<p class="ov-card-subtitle">Pe fiecare site, după ce pluginul este instalat și a sincronizat conținutul.</p>
				</div>
			</div>
			<?php if ( empty( $view['sites'] ) ) : ?>
				<p class="ov-cell-sub mb-0">Afacerea nu are site-uri.</p>
			<?php endif; ?>
			<?php foreach ( $view['sites'] as $ov_s ) : ?>
				<div class="ov-site-perception">
					<div class="d-flex align-items-center gap-2">
						<span class="fw-semibold flex-grow-1 ov-truncate"><?php echo esc_html( (string) $ov_s['name'] ); ?></span>
						<span class="ov-badge<?php echo $ov_s['ready'] ? ' ov-badge-success' : ' ov-badge-outline'; ?>"><?php echo esc_html( $ov_s['ready'] ? (int) $ov_s['content_count'] . ' pagini' : ( $ov_conn[ $ov_s['connector_status'] ] ?? '' ) ); ?></span>
					</div>
					<?php if ( 'wordpress' !== $ov_s['cms'] ) : ?>
						<p class="ov-cell-sub mb-0 mt-1">Nu este WordPress: percepția din interior nu este disponibilă.</p>
					<?php elseif ( ! $ov_s['ready'] ) : ?>
						<p class="ov-cell-sub mb-2 mt-1">Descărcați și instalați pluginul OptiVio Connector pe acest site; butonul apare după sincronizarea conținutului.</p>
						<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_s['id'] ) ); ?>"><i class="bi bi-plug me-1" aria-hidden="true"></i>Plugin pentru acest site</a>
					<?php elseif ( ! empty( $view['can_manage'] ) ) : ?>
						<?php $ov_pre = (array) $ov_s['preflight']; ?>
						<form class="mt-2" method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/perception/internal/' . (int) $ov_s['id'] ) ); ?>">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div class="ov-cell-sub mb-2"><?php echo esc_html( sprintf( '%d pagini · %s · cost estimat %s', (int) $ov_s['pages'], (string) $ov_pre['model'], null !== $ov_pre['estimate'] ? '~' . number_format( (float) $ov_pre['estimate'], 4 ) . ' USD' : 'necunoscut' ) ); ?></div>
							<?php if ( $ov_pre['blocked'] ) : ?>
								<div class="text-danger small"><?php echo esc_html( (string) $ov_pre['reason'] ); ?></div>
							<?php else : ?>
								<?php if ( $view['confirm'] ) : ?>
									<div class="form-check mb-2">
										<input class="form-check-input" type="checkbox" id="confirm_cost_<?php echo esc_attr( (string) (int) $ov_s['id'] ); ?>" name="confirm_cost" value="1" required>
										<label class="form-check-label small" for="confirm_cost_<?php echo esc_attr( (string) (int) $ov_s['id'] ); ?>">Confirm costul estimat</label>
									</div>
								<?php endif; ?>
								<button class="btn btn-dark btn-sm" type="submit" data-loading="Analizez…"><i class="bi bi-stars me-1" aria-hidden="true"></i>Percepție din interior</button>
							<?php endif; ?>
						</form>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $view['is_admin'] ) ) : ?>
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/perception/external' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Re-analizează din exterior</h2>
						<p class="ov-card-subtitle">Aceleași pagini ca data trecută (le puteți modifica). Arată dacă modificările de pe site au îmbunătățit claritatea.</p>
					</div>
				</div>
				<button class="btn btn-light w-100" type="submit" data-loading="Caut paginile…"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Pornește re-analiza</button>
			</form>
		<?php endif; ?>

		<?php if ( ! empty( $view['perceptions'] ) ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Istoric</h2></div>
				<ul class="ov-list mb-0">
					<?php foreach ( $view['perceptions'] as $ov_p ) : ?>
						<li class="d-flex align-items-center gap-2">
							<a class="flex-grow-1<?php echo null !== $ov_cur && (int) $ov_cur['id'] === (int) $ov_p['id'] ? ' fw-semibold' : ''; ?>" href="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/perception', array( 'p' => (int) $ov_p['id'] ) ) ); ?>">
								<?php echo esc_html( ov_datetime( (string) $ov_p['created_at'] ) . ' · ' . ( $ov_src[ $ov_p['source'] ] ?? '' ) . ( null !== $ov_p['site_name'] ? ' · ' . $ov_p['site_name'] : '' ) ); ?>
							</a>
							<?php if ( ! empty( $ov_p['is_baseline'] ) ) : ?>
								<span class="ov-badge ov-badge-outline">inițială</span>
							<?php endif; ?>
							<span class="ov-badge"><?php echo esc_html( null !== $ov_p['clarity_score'] ? (int) $ov_p['clarity_score'] . '%' : '—' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
