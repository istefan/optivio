<?php
/**
 * OptiVio — Onboarding step 1b: reading the selected pages (live progress).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Onboarding\Page_Discovery;

$ov_draft = (array) $view['draft'];
$ov_prog  = (array) $view['progress'];
$ov_id    = (int) $ov_draft['id'];
$ov_badge = array(
	'pending'  => array( '', 'în așteptare' ),
	'fetching' => array( 'ov-badge-warning', 'se citește' ),
	'ok'       => array( 'ov-badge-success', 'citită' ),
	'error'    => array( 'ov-badge-danger', 'eroare' ),
	'skipped'  => array( 'ov-badge-outline', 'blocată' ),
);
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<div class="ov-card mb-3" id="ov-fetch" data-endpoint="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/fetch' ) ); ?>" data-running="<?php echo 'fetching' === $ov_draft['status'] ? '1' : '0'; ?>">
	<div class="d-flex flex-wrap align-items-center gap-3">
		<div class="flex-grow-1">
			<div class="fw-semibold">Citesc paginile alese (fără AI)</div>
			<div class="ov-cell-sub"><span id="ov-fetch-done"><?php echo esc_html( (string) $ov_prog['done'] ); ?></span> din <?php echo esc_html( (string) $ov_prog['total'] ); ?> · erori: <span id="ov-fetch-errors"><?php echo esc_html( (string) $ov_prog['error'] ); ?></span></div>
			<div class="ov-progress mt-2"><span id="ov-fetch-bar" style="width: <?php echo esc_attr( (string) (int) $ov_prog['percent'] ); ?>%"></span></div>
		</div>
		<a class="btn btn-light" href="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/pages' ) ); ?>">Modifică selecția</a>
		<a class="btn btn-dark<?php echo $ov_prog['pending'] > 0 ? ' disabled' : ''; ?>" id="ov-fetch-next" href="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/analyze' ) ); ?>"<?php echo $ov_prog['pending'] > 0 ? ' aria-disabled="true" tabindex="-1"' : ''; ?>>Continuă la analiza AI <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
	</div>
	<p class="ov-cell-sub mt-2 mb-0">Puteți închide pagina: citirea continuă în fundal prin cron. Revenind, apăsați „Continuă” din lista de onboarding.</p>
</div>

<div class="ov-card">
	<div class="table-responsive">
		<table class="table ov-table">
			<thead><tr><th>Pagină</th><th>Tip</th><th>Stare</th><th class="text-end">Text</th><th class="text-end">Acțiuni</th></tr></thead>
			<tbody>
				<?php foreach ( $view['pages'] as $ov_p ) : ?>
					<?php $ov_b = $ov_badge[ $ov_p['fetch_status'] ] ?? array( '', $ov_p['fetch_status'] ); ?>
					<tr data-ov-page="<?php echo esc_attr( (string) (int) $ov_p['id'] ); ?>">
						<td class="ov-truncate" style="max-width: 420px">
							<div class="ov-cell-main ov-truncate"><?php echo esc_html( (string) $ov_p['url'] ); ?></div>
							<div class="ov-cell-sub" data-ov-error><?php echo esc_html( (string) $ov_p['error'] ); ?></div>
						</td>
						<td><?php echo esc_html( Page_Discovery::TYPES[ $ov_p['page_type'] ] ?? $ov_p['page_type'] ); ?></td>
						<td><span class="ov-badge <?php echo esc_attr( $ov_b[0] ); ?>" data-ov-status><?php echo esc_html( $ov_b[1] ); ?></span></td>
						<td class="text-end" data-ov-chars><?php echo esc_html( ov_number( mb_strlen( (string) $ov_p['body_text'] ) ) ); ?></td>
						<td class="text-end text-nowrap">
							<form class="d-inline" method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/pages/' . (int) $ov_p['id'] . '/retry' ) ); ?>">
								<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button class="btn btn-sm btn-light" type="submit" title="Reîncearcă" aria-label="Reîncearcă"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
							</form>
							<form class="d-inline" method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/pages/' . (int) $ov_p['id'] . '/exclude' ) ); ?>">
								<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<button class="btn btn-sm btn-light" type="submit" title="Exclude din analiză" aria-label="Exclude din analiză"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
