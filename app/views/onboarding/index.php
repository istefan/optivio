<?php
/**
 * OptiVio — Onboarding: start + drafts in progress.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_errors = (array) ( $view['errors'] ?? array() );
$ov_old    = (array) ( $view['old'] ?? array() );
$ov_limits = (array) $view['limits'];
$ov_status = array(
	'draft'     => array( '', 'Selecție pagini' ),
	'fetching'  => array( 'ov-badge-warning', 'Citire pagini' ),
	'fetched'   => array( 'ov-badge-warning', 'Gata pentru analiză' ),
	'analyzed'  => array( 'ov-badge-warning', 'De confirmat' ),
	'converted' => array( 'ov-badge-success', 'Afacere creată' ),
);
?>
<div class="row g-3">
	<div class="col-12 col-xl-7">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/onboarding' ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Pasul 1: site-urile clientului</h2>
					<p class="ov-card-subtitle">Introduceți unul sau mai multe domenii. OptiVio citește sitemap-ul și meniul (fără AI) și propune paginile de analizat.</p>
				</div>
			</div>
			<label class="form-label" for="sites">Domenii (unul pe rând)</label>
			<textarea class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'sites' ) ); ?>" id="sites" name="sites" rows="4" placeholder="exemplu.ro&#10;magazin.exemplu.ro" spellcheck="false" required><?php echo esc_textarea( (string) ( $ov_old['sites'] ?? '' ) ); ?></textarea>
			<?php ov_field_error( $ov_errors, 'sites' ); ?>
			<div class="form-text">Maximum <?php echo esc_html( (string) $ov_limits['site'] ); ?> de pagini pe site și <?php echo esc_html( (string) $ov_limits['run'] ); ?> pe analiză. Descoperirea durează câteva secunde pentru fiecare site.</div>
			<div class="d-flex flex-wrap gap-2 mt-3">
				<button class="btn btn-dark" type="submit" data-loading="Caut paginile…"><i class="bi bi-search me-1" aria-hidden="true"></i>Descoperă paginile</button>
				<a class="btn btn-light" href="<?php echo esc_url( ov_url( '/businesses/new' ) ); ?>">Completez manual, fără analiză</a>
			</div>
		</form>
	</div>
	<div class="col-12 col-xl-5">
		<div class="ov-card h-100">
			<div class="ov-card-header"><h2 class="ov-card-title">Cum funcționează</h2></div>
			<ol class="ov-steps mb-0">
				<li><strong>Site-uri și pagini</strong>: alegeți paginile; se citesc automat, fără AI.</li>
				<li><strong>Ce înțelege AI-ul</strong>: un singur apel AI (la buton, cu cost estimat) descrie afacerea ca un bot.</li>
				<li><strong>Confirmați profilul</strong>: corectați ce a înțeles greșit. Diferențele devin recomandări.</li>
				<li><strong>USP, reguli, date comerciale</strong>.</li>
				<li><strong>Plugin</strong> pe fiecare site (poate fi amânat).</li>
				<li><strong>Google</strong>: Search Console, Analytics, Ads… (poate fi amânat).</li>
				<li><strong>Rezumat</strong>: scor de claritate și primele Action Cards.</li>
			</ol>
		</div>
	</div>
</div>

<div class="ov-card mt-3">
	<div class="ov-card-header"><h2 class="ov-card-title">Onboarding în curs</h2></div>
	<?php if ( empty( $view['drafts'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-hourglass" aria-hidden="true"></i>Niciun onboarding în curs.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th>Site / afacere</th><th>Stare</th><th>Pagini alese</th><th>Pornit de</th><th>Actualizat</th><th class="text-end">Acțiuni</th></tr></thead>
				<tbody>
					<?php foreach ( $view['drafts'] as $ov_d ) : ?>
						<?php $ov_st = $ov_status[ $ov_d['status'] ] ?? array( '', $ov_d['status'] ); ?>
						<tr>
							<td>
								<div class="ov-cell-main"><?php echo esc_html( (string) ( $ov_d['business_name'] ?? $ov_d['label'] ) ); ?></div>
								<?php if ( null !== $ov_d['business_name'] ) : ?>
									<div class="ov-cell-sub"><?php echo esc_html( (string) $ov_d['label'] ); ?></div>
								<?php endif; ?>
							</td>
							<td><span class="ov-badge <?php echo esc_attr( $ov_st[0] ); ?>"><?php echo esc_html( $ov_st[1] ); ?></span></td>
							<td><?php echo esc_html( (string) (int) $ov_d['selected_pages'] ); ?></td>
							<td><?php echo esc_html( (string) $ov_d['author'] ); ?></td>
							<td><?php echo esc_html( ov_datetime( (string) $ov_d['updated_at'] ) ); ?></td>
							<td class="text-end text-nowrap">
								<a class="btn btn-sm btn-dark" href="<?php echo esc_url( ov_url( '/onboarding/' . (int) $ov_d['id'] ) ); ?>">Continuă</a>
								<form class="d-inline" method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . (int) $ov_d['id'] . '/delete' ) ); ?>" data-confirm="Ștergeți ciorna? Afacerea, dacă a fost creată, rămâne.">
									<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<button class="btn btn-sm btn-light" type="submit" aria-label="Șterge ciorna"><i class="bi bi-trash" aria-hidden="true"></i></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
