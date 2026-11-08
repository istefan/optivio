<?php
/**
 * OptiVio — Onboarding step 4: USP, permanent rules, commercial data, main contact.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_b   = (array) $view['business'];
$ov_id  = (int) $view['draft']['id'];
$ov_usp = (array) $view['usp'];
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/commercial' ) ); ?>" novalidate>
	<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="row g-3">
		<div class="col-12 col-xl-7 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">USP</h2>
						<p class="ov-card-subtitle">Propunere calculată din serviciile și punctele forte confirmate (determinist). Editați textul final.</p>
					</div>
					<span class="ov-badge"><?php echo esc_html( (string) (int) $ov_usp['strength'] ); ?>% acoperire</span>
				</div>
				<textarea class="form-control" name="usp_text" rows="3" maxlength="2000" aria-label="USP"><?php echo esc_textarea( '' !== (string) $ov_b['usp_text'] ? (string) $ov_b['usp_text'] : (string) $ov_usp['text'] ); ?></textarea>
				<?php if ( ! empty( $ov_usp['missing'] ) ) : ?>
					<p class="ov-cell-sub mt-2 mb-0">Piloni importanți pentru acest model care lipsesc din profil: <?php echo esc_html( implode( ', ', (array) $ov_usp['missing'] ) ); ?>.</p>
				<?php endif; ?>
			</div>

			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Reguli permanente</h2>
						<p class="ov-card-subtitle">Câte una pe rând. Sunt respectate de toate analizele și recomandările.</p>
					</div>
				</div>
				<?php if ( ! empty( $view['rules'] ) ) : ?>
					<ul class="ov-list mb-3">
						<?php foreach ( $view['rules'] as $ov_rule ) : ?>
							<li><?php echo esc_html( (string) $ov_rule['rule_text'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<textarea class="form-control" name="rules" rows="4" maxlength="5000" placeholder="Nu lucrăm în Ilfov după ora 20&#10;Nu promitem prețuri fixe la telefon" aria-label="Reguli noi"></textarea>
			</div>
		</div>

		<div class="col-12 col-xl-5 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Date comerciale</h2></div>
				<div class="row g-3">
					<div class="col-6">
						<label class="form-label" for="avg_job_value">Valoare medie lucrare</label>
						<div class="input-group">
							<input class="form-control" id="avg_job_value" name="avg_job_value" inputmode="decimal" value="<?php echo esc_attr( (string) $ov_b['avg_job_value'] ); ?>">
							<span class="input-group-text"><?php echo esc_html( (string) $ov_b['currency'] ); ?></span>
						</div>
					</div>
					<div class="col-6">
						<label class="form-label" for="daily_capacity">Capacitate / zi</label>
						<input class="form-control" type="number" min="0" id="daily_capacity" name="daily_capacity" value="<?php echo esc_attr( (string) $ov_b['daily_capacity'] ); ?>">
					</div>
				</div>
			</div>

			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Persoană de contact (decident)</h2></div>
				<?php if ( ! empty( $view['contacts'] ) ) : ?>
					<p class="ov-cell-sub">Există deja <?php echo esc_html( (string) count( $view['contacts'] ) ); ?> contacte; completați doar pentru a adăuga unul nou.</p>
				<?php endif; ?>
				<div class="row g-3">
					<div class="col-md-6"><label class="form-label" for="contact_name">Nume</label><input class="form-control" id="contact_name" name="contact_name" maxlength="120"></div>
					<div class="col-md-6"><label class="form-label" for="contact_role">Rol</label><input class="form-control" id="contact_role" name="contact_role" maxlength="120" placeholder="Administrator"></div>
					<div class="col-md-6"><label class="form-label" for="contact_email">Email</label><input class="form-control" type="email" id="contact_email" name="contact_email" maxlength="190"></div>
					<div class="col-md-6"><label class="form-label" for="contact_phone">Telefon</label><input class="form-control" id="contact_phone" name="contact_phone" maxlength="40"></div>
				</div>
			</div>

			<div class="ov-card">
				<button class="btn btn-dark w-100" type="submit">Salvează și continuă <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
			</div>
		</div>
	</div>
</form>
