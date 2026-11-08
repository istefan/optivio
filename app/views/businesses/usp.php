<?php
/**
 * OptiVio — USP tab: current USP, deterministic proposal and editor.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_b      = (array) $view['business'];
$ov_p      = (array) $view['proposal'];
$ov_errors = (array) $view['errors'];
$ov_old    = (array) $view['old'];
$ov_action = ov_url( '/businesses/' . (int) $ov_b['id'] . '/usp' );
$ov_source = array(
	'manual'   => 'editat manual',
	'computed' => 'propunere calculată',
	'ai'       => 'generat AI',
);
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-7 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">USP curent</h2>
					<p class="ov-card-subtitle">
						<?php
						echo esc_html(
							! empty( $ov_b['usp_text'] )
								? ( $ov_source[ $ov_b['usp_source'] ] ?? 'setat' ) . ' · ' . ov_datetime( $ov_b['usp_updated_at'] )
								: 'Nesetat — analizele nu au încă o propunere de valoare de referință'
						);
						?>
					</p>
				</div>
			</div>
			<?php if ( ! empty( $view['can_manage'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( $ov_action ); ?>" novalidate>
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="source" value="manual">
					<textarea class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'usp_text' ) ); ?>" name="usp_text" rows="4" maxlength="2000" aria-label="USP"><?php echo esc_textarea( $ov_old['usp_text'] ?? (string) $ov_b['usp_text'] ); ?></textarea>
					<?php ov_field_error( $ov_errors, 'usp_text' ); ?>
					<button class="btn btn-dark mt-3" type="submit">Salvează USP</button>
				</form>
			<?php else : ?>
				<p class="mb-0"><?php echo nl2br( esc_html( (string) ( $ov_b['usp_text'] ?? '—' ) ) ); ?></p>
			<?php endif; ?>
		</div>

		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Propunere calculată</h2>
					<p class="ov-card-subtitle">Determinist, din descriere, public țintă, servicii și zonă — fără AI</p>
				</div>
				<span class="ov-badge ov-badge-dark"><?php echo esc_html( (string) (int) $ov_p['strength'] ); ?>% acoperire</span>
			</div>
			<blockquote class="border-start border-3 border-dark ps-3 mb-3 fs-6"><?php echo esc_html( $ov_p['text'] ); ?></blockquote>
			<?php if ( ! empty( $view['can_manage'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( $ov_action ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="source" value="computed">
					<button class="btn btn-light" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Folosește propunerea</button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<div class="col-12 col-xl-5 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Piloni detectați</h2></div>
			<?php if ( empty( $ov_p['detected'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-search" aria-hidden="true"></i>Niciun diferențiator detectat în descriere și servicii.</div>
			<?php else : ?>
				<ul class="ov-list ov-checklist">
					<?php foreach ( $ov_p['detected'] as $ov_d ) : ?>
						<li>
							<span class="ov-check ov-check-done"><i class="bi bi-check" aria-hidden="true"></i></span>
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( $ov_d['label'] ); ?></div>
								<div class="ov-list-sub">„<?php echo esc_html( $ov_d['evidence'] ); ?>”</div>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Recomandat pentru acest model</h2>
					<p class="ov-card-subtitle"><?php echo esc_html( ov_business_model_label( (string) $ov_b['business_model'] ) ); ?></p>
				</div>
			</div>
			<?php if ( empty( $ov_p['missing'] ) ) : ?>
				<p class="ov-cell-sub mb-0">Toți pilonii importanți pentru acest model sunt acoperiți.</p>
			<?php else : ?>
				<ul class="ov-list ov-checklist">
					<?php foreach ( $ov_p['missing'] as $ov_m ) : ?>
						<li><span class="ov-check ov-check-todo"></span><span class="ov-list-sub text-body"><?php echo esc_html( $ov_m ); ?></span></li>
					<?php endforeach; ?>
				</ul>
				<p class="ov-cell-sub mt-3 mb-0">Dacă afacerea oferă aceste lucruri, menționați-le în descriere sau la servicii — propunerea se actualizează automat.</p>
			<?php endif; ?>
		</div>
	</div>
</div>
