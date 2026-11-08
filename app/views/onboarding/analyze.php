<?php
/**
 * OptiVio — Onboarding step 2: what the AI understands (preflight + on-demand call).
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
$ov_id    = (int) $ov_draft['id'];
$ov_in    = (array) $view['input'];
$ov_pre   = (array) $view['preflight'];
$ov_facts = (array) $ov_in['facts'];
$ov_trunc = count( array_filter( array_column( $ov_in['pages'], 'truncated' ) ) );
$ov_per   = $view['perception'];
$ov_src   = array(
	'none'     => 'nicio cheie',
	'global'   => 'cheia globală',
	'business' => 'cheia clientului',
);
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-7 d-flex flex-column gap-3">
		<?php if ( null !== $ov_per ) : ?>
			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Cum descrie un bot afacerea</h2>
						<p class="ov-card-subtitle"><?php echo esc_html( sprintf( 'Analiză din %s · %s · %d pagini', ov_datetime( (string) $ov_per['created_at'] ), (string) $ov_per['model'], (int) $ov_per['pages_count'] ) ); ?></p>
					</div>
				</div>
				<p class="mb-3"><?php echo esc_html( (string) ( $ov_per['result']['summary'] ?? '' ) ); ?></p>
				<a class="btn btn-dark" href="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/confirm' ) ); ?>">Confirmă profilul <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
			</div>
		<?php endif; ?>

		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/analyze' ) ); ?>">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title"><?php echo null === $ov_per ? 'Analiza AI a paginilor' : 'Re-analizează'; ?></h2>
					<p class="ov-card-subtitle">Un singur apel AI, pornit doar de acest buton. Textul site-urilor este tratat ca date, nu ca instrucțiuni.</p>
				</div>
			</div>
			<dl class="ov-dl">
				<dt>Pagini trimise</dt><dd><?php echo esc_html( (string) count( $ov_in['pages'] ) ); ?><?php echo $ov_trunc > 0 ? esc_html( sprintf( ' (%d trunchiate ca să încapă în limită)', $ov_trunc ) ) : ''; ?></dd>
				<dt>Text trimis</dt><dd><?php echo esc_html( ov_number( (int) $ov_in['chars'] ) ); ?> caractere<?php echo (int) $ov_in['common_chars'] > 0 ? esc_html( sprintf( ' (header/footer comun: %s, trimis o singură dată)', ov_number( (int) $ov_in['common_chars'] ) ) ) : ''; ?></dd>
				<dt>Model</dt><dd><span class="ov-kbd"><?php echo esc_html( (string) $ov_pre['model'] ); ?></span> · <?php echo esc_html( $ov_src[ $ov_pre['key_source'] ] ?? $ov_pre['key_source'] ); ?></dd>
				<dt>Cost estimat</dt><dd><?php echo null !== $ov_pre['estimate'] ? esc_html( '~' . number_format( (float) $ov_pre['estimate'], 4 ) . ' USD' ) : 'necunoscut (prețul modelului nu a putut fi citit)'; ?></dd>
				<dt>Buget lunar</dt><dd><?php echo esc_html( number_format( (float) $ov_pre['spent'], 2 ) . ' / ' . number_format( (float) $ov_pre['budget'], 2 ) . ' USD' ); ?></dd>
			</dl>
			<?php if ( $ov_pre['blocked'] ) : ?>
				<div class="ov-alert ov-alert-error mb-0"><i class="bi bi-slash-circle" aria-hidden="true"></i><div><?php echo esc_html( (string) $ov_pre['reason'] ); ?> <a href="<?php echo esc_url( ov_url( '/settings', array( 'tab' => 'ai' ) ) ); ?>">Setări → AI</a></div></div>
			<?php elseif ( empty( $ov_in['pages'] ) ) : ?>
				<div class="ov-alert ov-alert-warning mb-0"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div>Nicio pagină nu a putut fi citită. <a href="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/pages' ) ); ?>">Modificați selecția</a> sau completați afacerea manual.</div></div>
			<?php else : ?>
				<?php if ( $view['confirm'] ) : ?>
					<div class="form-check mb-3">
						<input class="form-check-input" type="checkbox" id="confirm_cost" name="confirm_cost" value="1" required>
						<label class="form-check-label" for="confirm_cost">Confirm costul estimat al apelului AI</label>
					</div>
				<?php endif; ?>
				<button class="btn btn-dark" type="submit" data-loading="Analizez… (poate dura până la un minut)"><i class="bi bi-stars me-1" aria-hidden="true"></i><?php echo null === $ov_per ? 'Analizează cu AI' : 'Rulează din nou analiza AI'; ?></button>
			<?php endif; ?>
		</form>
	</div>

	<div class="col-12 col-xl-5 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Găsit automat (fără AI)</h2></div>
			<dl class="ov-dl mb-0">
				<dt>Telefoane</dt><dd><?php echo esc_html( implode( ', ', (array) $ov_facts['phones'] ) ?: '—' ); ?> <span class="ov-cell-sub">(linkuri tel: <?php echo esc_html( (string) (int) $ov_facts['tel_links'] ); ?>)</span></dd>
				<dt>Emailuri</dt><dd><?php echo esc_html( implode( ', ', (array) $ov_facts['emails'] ) ?: '—' ); ?></dd>
				<dt>CUI / Reg. Com.</dt><dd><?php echo esc_html( ( implode( ', ', (array) $ov_facts['cui'] ) ?: '—' ) . ' / ' . ( implode( ', ', (array) $ov_facts['reg_com'] ) ?: '—' ) ); ?></dd>
				<dt>Program</dt><dd><?php echo esc_html( implode( ' · ', array_slice( (array) $ov_facts['hours'], 0, 3 ) ) ?: '—' ); ?></dd>
				<dt>Prețuri</dt><dd><?php echo esc_html( implode( ' · ', array_slice( (array) $ov_facts['prices'], 0, 4 ) ) ?: '—' ); ?></dd>
				<dt>Schema.org</dt><dd><?php echo esc_html( implode( ', ', array_unique( array_column( (array) $ov_facts['jsonld'], 'type' ) ) ) ?: '—' ); ?></dd>
			</dl>
		</div>
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Pagini trimise</h2></div>
			<ul class="ov-list mb-0">
				<?php foreach ( $ov_in['pages'] as $ov_p ) : ?>
					<li class="d-flex gap-2 align-items-center">
						<span class="ov-truncate flex-grow-1"><?php echo esc_html( (string) $ov_p['url'] ); ?></span>
						<span class="ov-badge ov-badge-outline"><?php echo esc_html( Page_Discovery::TYPES[ $ov_p['type'] ] ?? $ov_p['type'] ); ?></span>
						<?php if ( $ov_p['truncated'] ) : ?>
							<span class="ov-badge ov-badge-warning">parțial</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
