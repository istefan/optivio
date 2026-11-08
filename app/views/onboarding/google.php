<?php
/**
 * OptiVio — Onboarding step 6: connect Google services per site (Site Kit style).
 *
 * One Google sign-in for the agency account, automatic discovery of the accessible properties,
 * a suggested property per site and service (matched on the site domain) and a "Conectează"
 * button per service or for all confident matches at once.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Google\Google_Matcher;

$ov_id       = (int) $view['draft']['id'];
$ov_base     = '/onboarding/' . $ov_id . '/google';
$ov_accounts = (array) $view['accounts'];
$ov_cands    = (array) $view['candidates'];
$ov_icons    = array(
	'gsc'     => 'bi-search',
	'ga4'     => 'bi-graph-up',
	'gtm'     => 'bi-tags',
	'ads'     => 'bi-badge-ad',
	'adsense' => 'bi-currency-dollar',
	'gbp'     => 'bi-shop',
	'psi'     => 'bi-speedometer2',
);
$ov_label_of = static function ( string $provider, string $id ) use ( $ov_cands ): string {
	foreach ( (array) ( $ov_cands[ $provider ] ?? array() ) as $c ) {
		if ( $c['id'] === $id ) {
			return $c['label'];
		}
	}
	return $id;
};
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title"><i class="bi bi-google me-2" aria-hidden="true"></i>Cont Google</h2>
					<p class="ov-card-subtitle">Conectați o singură dată contul agenției (cel cu acces de manager la proprietățile clienților). OptiVio găsește apoi singur proprietatea potrivită fiecărui site.</p>
				</div>
			</div>
			<?php if ( empty( $ov_accounts ) ) : ?>
				<form method="post" action="<?php echo esc_url( ov_url( '/google/connect' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="return" value="<?php echo esc_attr( $ov_base ); ?>">
					<button class="btn btn-dark" type="submit"><i class="bi bi-google me-1" aria-hidden="true"></i>Conectează-te cu Google</button>
					<?php ov_guide_button( 'google_oauth' ); ?>
				</form>
			<?php else : ?>
				<ul class="ov-list mb-3">
					<?php foreach ( $ov_accounts as $ov_a ) : ?>
						<li class="d-flex align-items-center gap-2">
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
							<span class="flex-grow-1"><?php echo esc_html( (string) $ov_a['account_email'] ); ?></span>
							<span class="ov-badge <?php echo 'active' === $ov_a['status'] ? 'ov-badge-success' : 'ov-badge-danger'; ?>"><?php echo esc_html( 'active' === $ov_a['status'] ? 'conectat' : 'trebuie reconectat' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="d-flex flex-wrap gap-2">
					<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/discover' ) ); ?>">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<button class="btn btn-light btn-sm" type="submit" data-loading="Caut proprietățile…"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Reîmprospătează proprietățile</button>
					</form>
					<form method="post" action="<?php echo esc_url( ov_url( '/google/connect' ) ); ?>">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="hidden" name="return" value="<?php echo esc_attr( $ov_base ); ?>">
						<button class="btn btn-light btn-sm" type="submit"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Adaugă alt cont Google</button>
					</form>
				</div>
				<?php if ( empty( $view['discovered'] ) ) : ?>
					<p class="ov-cell-sub mt-2 mb-0">Proprietățile nu au fost listate încă: apăsați „Reîmprospătează proprietățile”.</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php foreach ( $view['sites'] as $ov_s ) : ?>
			<?php $ov_sid = (int) $ov_s['id']; ?>
			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title"><?php echo esc_html( (string) $ov_s['name'] ); ?></h2>
						<p class="ov-card-subtitle"><?php echo esc_html( (string) $ov_s['url'] ); ?></p>
					</div>
					<?php if ( $ov_s['auto'] > 0 ) : ?>
						<form method="post" action="<?php echo esc_url( ov_url( $ov_base . '/' . $ov_sid . '/auto' ) ); ?>">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button class="btn btn-dark btn-sm" type="submit" data-loading="Conectez…"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Conectează tot ce am găsit (<?php echo esc_html( (string) (int) $ov_s['auto'] ); ?>)</button>
						</form>
					<?php endif; ?>
				</div>

				<?php foreach ( $view['providers'] as $ov_pk => $ov_p ) : ?>
					<?php
					$ov_cur  = (array) ( $ov_s['integrations'][ $ov_pk ] ?? array() );
					$ov_has  = '' !== (string) ( $ov_cur['external_id'] ?? '' );
					$ov_sug  = (array) ( $ov_s['suggestions'][ $ov_pk ] ?? array() );
					$ov_list = (array) ( $ov_cands[ $ov_pk ] ?? array() );
					$ov_url  = ov_url( $ov_base . '/' . $ov_sid . '/' . $ov_pk );
					?>
					<div class="ov-gservice">
						<div class="ov-gservice-head">
							<span class="ov-gservice-icon"><i class="bi <?php echo esc_attr( $ov_icons[ $ov_pk ] ?? 'bi-google' ); ?>" aria-hidden="true"></i></span>
							<div class="flex-grow-1 min-w-0">
								<div class="fw-semibold"><?php echo esc_html( $ov_p[0] ); ?> <?php ov_guide_button( $ov_pk, '' ); ?></div>
								<?php if ( $ov_has ) : ?>
									<div class="ov-cell-sub ov-truncate"><?php echo esc_html( 'psi' === $ov_pk ? (string) $ov_cur['external_id'] : $ov_label_of( $ov_pk, (string) $ov_cur['external_id'] ) ); ?></div>
								<?php elseif ( 'psi' !== $ov_pk && null !== ( $ov_sug['match'] ?? null ) ) : ?>
									<div class="ov-cell-sub ov-truncate">Găsit: <strong><?php echo esc_html( (string) $ov_sug['match']['label'] ); ?></strong> · <?php echo esc_html( (string) $ov_sug['reason'] ); ?></div>
								<?php elseif ( 'psi' !== $ov_pk ) : ?>
									<div class="ov-cell-sub"><?php echo esc_html( empty( $ov_list ) ? 'Nicio proprietate accesibilă contului Google conectat.' : 'Nicio proprietate potrivită domeniului; alegeți manual.' ); ?></div>
								<?php endif; ?>
							</div>
							<?php if ( $ov_has ) : ?>
								<span class="ov-badge ov-badge-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Conectat</span>
							<?php elseif ( 'psi' === $ov_pk ) : ?>
								<span class="ov-badge">automat</span>
							<?php elseif ( null !== ( $ov_sug['match'] ?? null ) ) : ?>
								<form method="post" action="<?php echo esc_url( $ov_url ); ?>">
									<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<input type="hidden" name="property" value="<?php echo esc_attr( (int) $ov_sug['match']['credential_id'] . '|' . $ov_sug['match']['id'] ); ?>">
									<button class="btn btn-sm <?php echo (int) $ov_sug['score'] >= Google_Matcher::AUTO_SCORE ? 'btn-dark' : 'btn-outline-dark'; ?>" type="submit">Conectează</button>
								</form>
							<?php endif; ?>
						</div>
						<?php if ( 'psi' !== $ov_pk && ! empty( $ov_list ) ) : ?>
							<details class="ov-gservice-more">
								<summary><?php echo esc_html( $ov_has ? 'Schimbă sau deconectează' : 'Altă proprietate' ); ?></summary>
								<form class="d-flex flex-wrap gap-2 mt-2" method="post" action="<?php echo esc_url( $ov_url ); ?>">
									<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<select class="form-select form-select-sm flex-grow-1 w-auto" name="property" aria-label="Proprietate">
										<option value="">— neconectat —</option>
										<?php foreach ( $ov_list as $ov_c ) : ?>
											<?php $ov_v = (int) $ov_c['credential_id'] . '|' . $ov_c['id']; ?>
											<option value="<?php echo esc_attr( $ov_v ); ?>"<?php echo $ov_has && (string) $ov_cur['external_id'] === $ov_c['id'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_c['label'] . ( count( $ov_accounts ) > 1 ? ' — ' . $ov_c['email'] : '' ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<button class="btn btn-sm btn-light" type="submit">Salvează</button>
								</form>
							</details>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Cum funcționează</h2></div>
			<ol class="ov-steps mb-0">
				<li>Conectați contul Google al agenției (o singură dată, pentru toți clienții).</li>
				<li>OptiVio listează proprietățile la care contul are acces și le potrivește cu domeniul fiecărui site: Search Console, fluxul web GA4, containerul Tag Manager, fișa Business Profile, site-ul AdSense.</li>
				<li>Apăsați <strong>Conectează</strong> pe fiecare serviciu sau <strong>Conectează tot ce am găsit</strong>. Prima sincronizare pornește imediat, în fundal.</li>
				<li>Google Ads nu are domeniu: verificați contul sugerat înainte de conectare.</li>
			</ol>
		</div>
		<div class="ov-card d-flex flex-column gap-2">
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/advance/google' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<button class="btn btn-dark w-100" type="submit">Continuă <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
			</form>
			<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/advance/google' ) ); ?>">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="hidden" name="skip" value="1">
				<button class="btn btn-light w-100" type="submit">Sar peste, conectez mai târziu</button>
			</form>
		</div>
	</div>
</div>
