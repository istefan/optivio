<?php
/**
 * OptiVio — Global settings (tabs).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_tab    = (string) $view['tab'];
$ov_s      = (array) $view['settings'];
$ov_errors = (array) ( $view['errors'] ?? array() );

$ov_number_field = static function ( string $name, string $label, $value, int $min, int $max, string $suffix = '' ) use ( $ov_errors ): void {
	?>
	<div class="col-md-6 col-xl-4">
		<label class="form-label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
		<div class="input-group">
			<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, $name ) ); ?>" type="number" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" value="<?php echo esc_attr( (string) $value ); ?>">
			<?php if ( '' !== $suffix ) : ?>
				<span class="input-group-text"><?php echo esc_html( $suffix ); ?></span>
			<?php endif; ?>
			<?php ov_field_error( $ov_errors, $name ); ?>
		</div>
	</div>
	<?php
};

$ov_credential = static function ( string $provider, string $help ) use ( $view ): void {
	$ov_cred = $view['credentials'][ $provider ] ?? null;
	$ov_url  = ov_url( '/settings/credentials/' . $provider );
	?>
	<div class="border rounded-3 p-3 mb-3">
		<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
			<div class="fw-semibold"><?php echo esc_html( $view['provider_labels'][ $provider ] ?? $provider ); ?></div>
			<?php ov_guide_button( 'pagespeed' === $provider ? 'psi' : $provider ); ?>
			<?php if ( null !== $ov_cred ) : ?>
				<span class="ov-badge ov-badge-success"><i class="bi bi-lock" aria-hidden="true"></i>configurată</span>
				<span class="ov-secret"><?php echo esc_html( ov_mask_secret( $ov_cred['secret_hint'] ) ?: '••••••••' ); ?></span>
				<span class="ov-cell-sub">actualizată <?php echo esc_html( ov_datetime( $ov_cred['updated_at'] ) ); ?></span>
			<?php else : ?>
				<span class="ov-badge">neconfigurată</span>
			<?php endif; ?>
			<?php if ( null !== $ov_cred || 'pagespeed' === $provider ) : ?>
				<span class="ms-auto"><?php ov_test_button( $provider ); ?></span>
			<?php endif; ?>
		</div>
		<p class="ov-cell-sub mb-2"><?php echo esc_html( $help ); ?></p>
		<div class="d-flex flex-wrap gap-2">
			<form class="d-flex gap-2 flex-grow-1" method="post" action="<?php echo esc_url( $ov_url ); ?>" autocomplete="off">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input class="form-control" type="password" name="secret" placeholder="<?php echo esc_attr( null !== $ov_cred ? 'Înlocuiește cheia…' : 'Lipește cheia API…' ); ?>" autocomplete="new-password" maxlength="512" required aria-label="Cheie API">
				<button class="btn btn-dark text-nowrap" type="submit">Salvează</button>
			</form>
			<?php if ( null !== $ov_cred ) : ?>
				<form method="post" action="<?php echo esc_url( $ov_url ); ?>" data-confirm="Ștergeți definitiv această cheie?">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="action" value="delete">
					<button class="btn btn-outline-danger" type="submit">Șterge</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
};
?>
<nav class="ov-tabs" aria-label="Secțiuni setări">
	<?php foreach ( $view['sections'] as $ov_key => $ov_label ) : ?>
		<a class="<?php echo $ov_key === $ov_tab ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/settings', array( 'tab' => $ov_key ) ) ); ?>"><?php echo esc_html( $ov_label ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'general' === $ov_tab ) : ?>
	<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/settings/general' ) ); ?>" novalidate>
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="row g-3">
			<div class="col-md-4">
				<label class="form-label" for="app_name">Nume aplicație</label>
				<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'app_name' ) ); ?>" id="app_name" name="app_name" maxlength="60" value="<?php echo esc_attr( $ov_s['general.app_name'] ); ?>">
				<?php ov_field_error( $ov_errors, 'app_name' ); ?>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="timezone">Fus orar afișare</label>
				<select class="form-select<?php echo esc_attr( ov_invalid( $ov_errors, 'timezone' ) ); ?>" id="timezone" name="timezone">
					<?php foreach ( $view['timezones'] as $ov_tz ) : ?>
						<option value="<?php echo esc_attr( $ov_tz ); ?>"<?php echo $ov_tz === $ov_s['general.timezone'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_tz ); ?></option>
					<?php endforeach; ?>
				</select>
				<div class="form-text">Datele sunt stocate în UTC. Fusul orar din config.php are prioritate la afișare.</div>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="currency">Monedă implicită</label>
				<select class="form-select" id="currency" name="currency">
					<?php foreach ( array( 'RON', 'EUR', 'USD' ) as $ov_cur ) : ?>
						<option value="<?php echo esc_attr( $ov_cur ); ?>"<?php echo $ov_cur === $ov_s['general.currency'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_cur ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<button class="btn btn-dark mt-4" type="submit">Salvează</button>
	</form>
<?php endif; ?>

<?php if ( 'security' === $ov_tab ) : ?>
	<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/settings/security' ) ); ?>" novalidate>
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="ov-section-label">Sesiuni</div>
		<div class="row g-3 mb-4">
			<?php $ov_number_field( 'session_idle_minutes', 'Deconectare după inactivitate', $ov_s['security.session_idle_minutes'], 5, 480, 'min' ); ?>
			<?php $ov_number_field( 'session_absolute_hours', 'Durată maximă sesiune', $ov_s['security.session_absolute_hours'], 1, 72, 'ore' ); ?>
		</div>
		<div class="ov-section-label">Protecție brute-force</div>
		<div class="row g-3 mb-4">
			<?php $ov_number_field( 'login_max_attempts', 'Încercări eșuate permise (email + IP)', $ov_s['security.login_max_attempts'], 3, 20 ); ?>
			<?php $ov_number_field( 'login_window_minutes', 'Fereastră de numărare', $ov_s['security.login_window_minutes'], 5, 120, 'min' ); ?>
			<?php $ov_number_field( 'lockout_minutes', 'Durată blocare', $ov_s['security.lockout_minutes'], 5, 1440, 'min' ); ?>
		</div>
		<div class="ov-section-label">Parole & jurnal</div>
		<div class="row g-3">
			<?php $ov_number_field( 'password_min_length', 'Lungime minimă parolă', $ov_s['security.password_min_length'], 10, 64, 'caractere' ); ?>
			<?php $ov_number_field( 'audit_retention_days', 'Păstrare jurnal audit', $ov_s['security.audit_retention_days'], 30, 3650, 'zile' ); ?>
		</div>
		<div class="ov-alert ov-alert-info mt-4 mb-0">
			<i class="bi bi-shield-lock" aria-hidden="true"></i>
			<div>Parole: Argon2id · Secrete: AES-256-GCM cu cheie master din config · Cookie: HttpOnly, Secure, SameSite=Lax · CSRF pe toate formularele · CSP strict.</div>
		</div>
		<button class="btn btn-dark mt-4" type="submit">Salvează</button>
	</form>
<?php endif; ?>

<?php if ( 'ai' === $ov_tab ) : ?>
	<div class="row g-3">
		<div class="col-12 col-xl-8">
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/settings/ai' ) ); ?>" novalidate>
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Profiluri de modele (global)</h2>
						<p class="ov-card-subtitle">Modelele implicite pot fi modificate liber aici și, ulterior, suprascrise per client.</p>
					</div>
				</div>
				<?php foreach ( $view['ai_profiles'] as $ov_key => $ov_profile ) : ?>
					<div class="border rounded-3 p-3 mb-3">
						<div class="fw-semibold"><?php echo esc_html( $ov_profile[0] ); ?></div>
						<p class="ov-cell-sub mb-2"><?php echo esc_html( $ov_profile[1] ); ?></p>
						<div class="row g-2">
							<div class="col-md-6">
								<label class="form-label" for="model_<?php echo esc_attr( $ov_key ); ?>">Model OpenRouter</label>
								<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'model_' . $ov_key ) ); ?>" id="model_<?php echo esc_attr( $ov_key ); ?>" name="model_<?php echo esc_attr( $ov_key ); ?>" maxlength="120" value="<?php echo esc_attr( $ov_s[ 'ai.profile.' . $ov_key . '.model' ] ); ?>" spellcheck="false">
								<?php ov_field_error( $ov_errors, 'model_' . $ov_key ); ?>
							</div>
							<div class="col-6 col-md-3">
								<label class="form-label" for="temperature_<?php echo esc_attr( $ov_key ); ?>">Temperatură</label>
								<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'temperature_' . $ov_key ) ); ?>" id="temperature_<?php echo esc_attr( $ov_key ); ?>" name="temperature_<?php echo esc_attr( $ov_key ); ?>" inputmode="decimal" value="<?php echo esc_attr( (string) $ov_s[ 'ai.profile.' . $ov_key . '.temperature' ] ); ?>">
								<?php ov_field_error( $ov_errors, 'temperature_' . $ov_key ); ?>
							</div>
							<div class="col-6 col-md-3">
								<label class="form-label" for="max_tokens_<?php echo esc_attr( $ov_key ); ?>">Max tokens</label>
								<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'max_tokens_' . $ov_key ) ); ?>" type="number" id="max_tokens_<?php echo esc_attr( $ov_key ); ?>" name="max_tokens_<?php echo esc_attr( $ov_key ); ?>" min="256" max="64000" value="<?php echo esc_attr( (string) $ov_s[ 'ai.profile.' . $ov_key . '.max_tokens' ] ); ?>">
								<?php ov_field_error( $ov_errors, 'max_tokens_' . $ov_key ); ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="row g-3 align-items-end">
					<?php $ov_number_field( 'monthly_budget_usd', 'Buget lunar AI (alertă)', $ov_s['ai.monthly_budget_usd'], 0, 100000, 'USD' ); ?>
					<div class="col-md-6 col-xl-8">
						<div class="form-check">
							<input type="hidden" name="confirm_before_call" value="0">
							<input class="form-check-input" type="checkbox" id="confirm_before_call" name="confirm_before_call" value="1"<?php echo ! empty( $ov_s['ai.confirm_before_call'] ) ? ' checked' : ''; ?>>
							<label class="form-check-label" for="confirm_before_call">Cere confirmare (cu estimare de cost) înainte de fiecare apel AI</label>
						</div>
					</div>
				</div>
				<div class="ov-section-label mt-4">Onboarding — analiza percepției</div>
				<div class="row g-3">
					<?php $ov_number_field( 'onboarding_max_pages_per_site', 'Pagini maxime pe site', $ov_s['onboarding.max_pages_per_site'], 1, 50 ); ?>
					<?php $ov_number_field( 'onboarding_max_pages_per_run', 'Pagini maxime pe analiză', $ov_s['onboarding.max_pages_per_run'], 1, 100 ); ?>
					<?php $ov_number_field( 'onboarding_max_input_chars', 'Text maxim trimis la AI', $ov_s['onboarding.max_input_chars'], 20000, 1000000, 'caractere' ); ?>
				</div>
				<p class="ov-cell-sub mt-2 mb-0">Peste limita de text, paginile se prioritizează (acasă, servicii, prețuri, despre, contact) și se trunchiază proporțional.</p>
				<button class="btn btn-dark mt-4" type="submit">Salvează profilurile</button>
			</form>
		</div>
		<div class="col-12 col-xl-4 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Cheie OpenRouter globală</h2></div>
				<?php $ov_credential( 'openrouter', 'Folosită când clientul nu are cheie proprie. Stocată criptat; nu mai poate fi afișată după salvare.' ); ?>
			</div>
			<div class="ov-alert ov-alert-info mb-0">
				<i class="bi bi-cpu" aria-hidden="true"></i>
				<div><strong>Deterministic first, AI on-demand.</strong> OpenRouter este apelat exclusiv la cererea explicită a unui utilizator. Cron-ul, webhook-urile și sincronizările nu consumă tokeni.</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php if ( 'integrations' === $ov_tab ) : ?>
	<div class="row g-3">
		<div class="col-12 col-xl-7">
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/settings/integrations' ) ); ?>" novalidate autocomplete="off">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
					<div class="ov-section-label mb-0">Google OAuth (GSC, GA4, GTM, Ads, AdSense, Business Profile)</div>
					<?php ov_guide_button( 'google_oauth' ); ?>
				</div>
				<div class="ov-guide-chips mb-3">
					<span class="ov-cell-sub">API-uri de activat în Google Cloud Console:</span>
					<?php foreach ( array( 'gsc' => 'Search Console', 'ga4' => 'Analytics 4', 'gtm' => 'Tag Manager', 'ads' => 'Google Ads', 'adsense' => 'AdSense', 'gbp' => 'Business Profile' ) as $ov_gk => $ov_gl ) : ?>
						<?php ov_guide_button( $ov_gk, $ov_gl ); ?>
					<?php endforeach; ?>
				</div>
				<div class="row g-3 mb-4">
					<div class="col-12">
						<label class="form-label" for="google_client_id">Client ID</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'google_client_id' ) ); ?>" id="google_client_id" name="google_client_id" maxlength="200" value="<?php echo esc_attr( $ov_s['integrations.google_client_id'] ); ?>" spellcheck="false">
						<?php ov_field_error( $ov_errors, 'google_client_id' ); ?>
					</div>
					<div class="col-12">
						<label class="form-label" for="google_client_secret">Client Secret <?php echo null !== $view['google_secret_hint'] ? '<span class="ov-badge ov-badge-success ms-1">configurat</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></label>
						<input class="form-control" type="password" id="google_client_secret" name="google_client_secret" maxlength="255" autocomplete="new-password" placeholder="<?php echo esc_attr( null !== $view['google_secret_hint'] ? ov_mask_secret( $view['google_secret_hint'] ) . ' — lăsați gol pentru a păstra' : '' ); ?>">
					</div>
					<div class="col-12">
						<label class="form-label" for="oauth_redirect">URI de redirecționare autorizat (Google Cloud Console)</label>
						<input class="form-control" id="oauth_redirect" readonly value="<?php echo esc_attr( $view['oauth_redirect'] ); ?>">
					</div>
					<div class="col-12"><?php ov_test_button( 'google_oauth', 'Testează clientul OAuth și conturile conectate' ); ?></div>
				</div>
				<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
					<div class="ov-section-label mb-0">Google Ads API & Search Console</div>
					<?php ov_guide_button( 'ads', 'Developer token: cum îl obțin?' ); ?>
				</div>
				<div class="row g-3 mb-4">
					<div class="col-md-6">
						<label class="form-label" for="ads_developer_token">Developer token <?php echo null !== $view['ads_token_hint'] ? '<span class="ov-badge ov-badge-success ms-1">configurat</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></label>
						<input class="form-control" type="password" id="ads_developer_token" name="ads_developer_token" maxlength="100" autocomplete="new-password" placeholder="<?php echo esc_attr( null !== $view['ads_token_hint'] ? 'lăsați gol pentru a păstra' : '' ); ?>">
					</div>
					<div class="col-6 col-md-3">
						<label class="form-label" for="ads_login_customer_id">Login customer ID (MCC)</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'ads_login_customer_id' ) ); ?>" id="ads_login_customer_id" name="ads_login_customer_id" maxlength="20" value="<?php echo esc_attr( $ov_s['integrations.ads_login_customer_id'] ); ?>">
						<?php ov_field_error( $ov_errors, 'ads_login_customer_id' ); ?>
					</div>
					<div class="col-6 col-md-3">
						<label class="form-label" for="ads_api_version">Versiune API Ads</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'ads_api_version' ) ); ?>" id="ads_api_version" name="ads_api_version" maxlength="4" value="<?php echo esc_attr( $ov_s['integrations.ads_api_version'] ); ?>">
						<?php ov_field_error( $ov_errors, 'ads_api_version' ); ?>
					</div>
					<div class="col-6 col-md-4">
						<label class="form-label" for="gsc_backfill_days">Istoric GSC la prima sincronizare</label>
						<div class="input-group"><input class="form-control" type="number" id="gsc_backfill_days" name="gsc_backfill_days" min="7" max="480" value="<?php echo esc_attr( (string) $ov_s['integrations.gsc_backfill_days'] ); ?>"><span class="input-group-text">zile</span></div>
					</div>
					<div class="col-12 d-flex flex-wrap align-items-center gap-3">
						<?php ov_test_button( 'ads', 'Testează developer token-ul' ); ?>
						<a class="ov-link-more" href="<?php echo esc_url( ov_url( '/google' ) ); ?>">Conturi Google conectate →</a>
					</div>
				</div>
				<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
					<div class="ov-section-label mb-0">SERP (Serper.dev) & PageSpeed</div>
					<?php ov_guide_button( 'serper', 'Serper' ); ?>
					<?php ov_guide_button( 'psi', 'PageSpeed' ); ?>
				</div>
				<div class="row g-3">
					<div class="col-4 col-md-2">
						<label class="form-label" for="serper_gl">Țară (gl)</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'serper_gl' ) ); ?>" id="serper_gl" name="serper_gl" maxlength="2" value="<?php echo esc_attr( $ov_s['integrations.serper_gl'] ); ?>">
					</div>
					<div class="col-4 col-md-2">
						<label class="form-label" for="serper_hl">Limbă (hl)</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'serper_hl' ) ); ?>" id="serper_hl" name="serper_hl" maxlength="5" value="<?php echo esc_attr( $ov_s['integrations.serper_hl'] ); ?>">
					</div>
					<div class="col-12 col-md-5">
						<label class="form-label" for="serper_location">Locație implicită</label>
						<input class="form-control" id="serper_location" name="serper_location" maxlength="80" value="<?php echo esc_attr( $ov_s['integrations.serper_location'] ); ?>">
					</div>
					<div class="col-12 col-md-3">
						<label class="form-label" for="psi_strategy">PageSpeed</label>
						<select class="form-select" id="psi_strategy" name="psi_strategy">
							<option value="mobile"<?php echo 'mobile' === $ov_s['integrations.psi_strategy'] ? ' selected' : ''; ?>>Mobil</option>
							<option value="desktop"<?php echo 'desktop' === $ov_s['integrations.psi_strategy'] ? ' selected' : ''; ?>>Desktop</option>
						</select>
					</div>
				</div>
				<button class="btn btn-dark mt-4" type="submit">Salvează</button>
			</form>
		</div>
		<div class="col-12 col-xl-5">
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Chei API globale</h2></div>
				<?php $ov_credential( 'serper', 'Extragerea automată a concurenților din Google. Fără cheie, concurenții se adaugă manual (URL) și sunt scanați direct din PHP.' ); ?>
				<?php $ov_credential( 'pagespeed', 'Opțională — crește cota zilnică a PageSpeed Insights API.' ); ?>
			</div>
			<div class="ov-card mt-3">
				<div class="ov-card-header"><h2 class="ov-card-title">Verificare rapidă a conexiunilor</h2></div>
				<p class="ov-cell-sub">Fiecare test face cel mai ieftin apel care dovedește că cheia funcționează. Serper consumă 1 credit; testul OpenRouter nu apelează niciun model.</p>
				<div class="d-flex flex-column gap-2">
					<?php foreach ( \OptiVio\Services\Connection_Tester::PROVIDERS as $ov_tp => $ov_tl ) : ?>
						<div class="d-flex flex-wrap align-items-center gap-2"><span class="flex-grow-1"><?php echo esc_html( $ov_tl ); ?></span><?php ov_test_button( $ov_tp, 'Testează' ); ?></div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<?php if ( 'spam' === $ov_tab ) : ?>
	<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/settings/spam' ) ); ?>" novalidate>
		<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="ov-section-label">Scor anti-spam (0–100, determinist, fără AI)</div>
		<div class="row g-3 mb-4">
			<?php $ov_number_field( 'threshold_suspect', 'Prag „suspect”', $ov_s['spam.threshold_suspect'], 10, 90, 'puncte' ); ?>
			<?php $ov_number_field( 'threshold_spam', 'Prag „spam”', $ov_s['spam.threshold_spam'], 20, 100, 'puncte' ); ?>
			<?php $ov_number_field( 'min_fill_seconds', 'Timp minim de completare', $ov_s['spam.min_fill_seconds'], 1, 30, 'sec' ); ?>
			<?php $ov_number_field( 'max_links', 'Linkuri tolerate în mesaj', $ov_s['spam.max_links'], 0, 20 ); ?>
			<?php $ov_number_field( 'ip_hourly_limit', 'Trimiteri / oră de pe același IP', $ov_s['spam.ip_hourly_limit'], 1, 50 ); ?>
			<?php $ov_number_field( 'connector_stale_hours', 'Alertă conector inactiv după', $ov_s['spam.connector_stale_hours'], 1, 720, 'ore' ); ?>
		</div>
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label" for="blocked_words">Cuvinte / expresii blocate (una pe linie)</label>
				<textarea class="form-control" id="blocked_words" name="blocked_words" rows="10"><?php echo esc_textarea( implode( "\n", (array) $ov_s['spam.blocked_words'] ) ); ?></textarea>
				<div class="form-text">Sunt trimise și pluginului la heartbeat, pentru blocare direct pe site.</div>
			</div>
			<div class="col-md-6">
				<label class="form-label" for="disposable_domains">Domenii email de unică folosință (unul pe linie)</label>
				<textarea class="form-control" id="disposable_domains" name="disposable_domains" rows="10"><?php echo esc_textarea( implode( "\n", (array) $ov_s['spam.disposable_domains'] ) ); ?></textarea>
			</div>
		</div>
		<div class="ov-alert ov-alert-info mt-4 mb-0">
			<i class="bi bi-info-circle" aria-hidden="true"></i>
			<div>Puncte: honeypot 100 · completare instant 60 / rapidă 35 · multe linkuri 35 / linkuri 15 · cuvânt blocat 40 · alfabet străin 30 · email de unică folosință 30 · rafală IP 30 · pattern plugin 30 · email invalid 15 · telefon invalid 10 · majuscule 10 · fără mesaj 10 · retrimitere 10.</div>
		</div>
		<button class="btn btn-dark mt-4" type="submit">Salvează</button>
	</form>
<?php endif; ?>

<?php if ( 'cron' === $ov_tab ) : ?>
	<div class="row g-3">
		<div class="col-12 col-xl-5 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Coada de joburi</h2></div>
				<div class="row g-2 text-center mb-3">
					<?php
					foreach ( array(
						'pending' => 'În așteptare',
						'running' => 'Rulează',
						'done'    => 'Finalizate',
						'failed'  => 'Eșuate',
					) as $ov_key => $ov_label ) :
						?>
						<div class="col-6 col-md-3">
							<div class="border rounded-3 py-2">
								<div class="fs-5 fw-bold"><?php echo esc_html( ov_number( $view['job_stats'][ $ov_key ] ?? 0 ) ); ?></div>
								<div class="ov-cell-sub"><?php echo esc_html( $ov_label ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<button class="btn btn-dark w-100" type="button" id="ov-run-batch" data-endpoint="<?php echo esc_url( ov_url( '/jobs/run-batch' ) ); ?>">
					<i class="bi bi-play-fill me-1" aria-hidden="true"></i>Rulează coada acum (AJAX batching)
				</button>
				<div class="ov-cell-sub mt-2" id="ov-run-output" aria-live="polite"></div>
			</div>
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/settings/cron' ) ); ?>" novalidate>
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="row g-3">
					<?php $ov_number_field( 'batch_seconds', 'Buget de timp per rulare', $ov_s['cron.batch_seconds'], 10, 55, 'sec' ); ?>
					<?php $ov_number_field( 'interval_minutes', 'Interval minim între rulări', $ov_s['cron.interval_minutes'], 1, 15, 'min' ); ?>
				</div>
				<button class="btn btn-dark mt-3" type="submit">Salvează</button>
			</form>
		</div>
		<div class="col-12 col-xl-7 d-flex flex-column gap-3">
			<div class="ov-card">
				<?php $ov_wc = (array) $view['web_cron']; ?>
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Cron automat (fără cPanel)</h2>
						<p class="ov-card-subtitle">Ca la WordPress: nu trebuie configurat nimic pe server.</p>
					</div>
					<span class="ov-badge <?php echo esc_attr( false === $ov_wc['ok'] ? 'ov-badge-warning' : 'ov-badge-success' ); ?>"><?php echo esc_html( false === $ov_wc['ok'] ? 'mod rezervă' : 'activ' ); ?></span>
				</div>
				<p class="mb-2">Joburile rulează singure, în fundal, după orice accesare a aplicației: paginile deschise de utilizatori și cererile trimise de pluginul OptiVio Connector de pe site-urile clienților (heartbeat, click-uri, formulare, conținut).</p>
				<dl class="ov-dl">
					<dt>Ultima declanșare</dt><dd><?php echo esc_html( $ov_wc['last'] > 0 ? ov_datetime( gmdate( 'Y-m-d H:i:s', (int) $ov_wc['last'] ), 'd.m.Y H:i:s' ) : 'încă nu' ); ?></dd>
					<dt>Cerere internă (loopback)</dt>
					<dd>
						<?php if ( null === $ov_wc['ok'] ) : ?>
							netestată încă
						<?php elseif ( $ov_wc['ok'] ) : ?>
							funcționează
						<?php else : ?>
							blocată de gazdă (<?php echo esc_html( (string) $ov_wc['error'] ); ?>) — joburile pornesc după răspuns sau din browserul utilizatorilor autentificați
						<?php endif; ?>
					</dd>
				</dl>
				<p class="ov-cell-sub mb-0">Dacă nimeni nu deschide aplicația și niciun plugin nu raportează, joburile întârzie până la următoarea accesare. Butonul „Rulează coada acum” pornește imediat procesarea.</p>
			</div>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Ultimele rulări</h2></div>
				<?php if ( empty( $view['cron_runs'] ) ) : ?>
					<div class="ov-empty py-3"><i class="bi bi-clock" aria-hidden="true"></i>Cron-ul nu a rulat încă.</div>
				<?php else : ?>
					<div class="table-responsive">
						<table class="table ov-table">
							<thead><tr><th scope="col">Start</th><th scope="col">Sursă</th><th scope="col" class="text-center">Procesate</th><th scope="col" class="text-center">Eșuate</th><th scope="col">Stare</th></tr></thead>
							<tbody>
								<?php foreach ( $view['cron_runs'] as $ov_run ) : ?>
									<tr>
										<td class="ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_run['started_at'], 'd.m.Y H:i:s' ) ); ?></td>
										<td><span class="ov-badge"><?php echo esc_html( $ov_run['trigger_source'] ); ?></span></td>
										<td class="text-center"><?php echo esc_html( (string) (int) $ov_run['jobs_processed'] ); ?></td>
										<td class="text-center"><?php echo esc_html( (string) (int) $ov_run['jobs_failed'] ); ?></td>
										<td><span class="ov-badge <?php echo 'ok' === $ov_run['status'] ? 'ov-badge-success' : ( 'error' === $ov_run['status'] ? 'ov-badge-danger' : 'ov-badge-warning' ); ?>"><?php echo esc_html( $ov_run['status'] ); ?></span></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
<?php endif; ?>
