<?php
/**
 * OptiVio — Onboarding step 1: page selection per site.
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

$ov_draft  = (array) $view['draft'];
$ov_errors = (array) ( $view['errors'] ?? array() );
$ov_limits = (array) $view['limits'];
$ov_sites  = (array) ( $ov_draft['state']['sites'] ?? array() );
$ov_bysite = array();
foreach ( $view['pages'] as $ov_p ) {
	$ov_bysite[ Page_Discovery::host_key( (string) $ov_p['site_url'] ) ][ $ov_p['page_type'] ][] = $ov_p;
}
$ov_source = array(
	'sitemap' => 'sitemap',
	'menu'    => 'meniu',
	'home'    => 'acasă',
	'manual'  => 'manual',
);
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . (int) $ov_draft['id'] . '/pages' ) ); ?>" novalidate id="ov-pages-form" data-max-site="<?php echo esc_attr( (string) $ov_limits['site'] ); ?>" data-max-run="<?php echo esc_attr( (string) $ov_limits['run'] ); ?>">
	<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="ov-card mb-3 d-flex flex-wrap align-items-center gap-3">
		<div class="flex-grow-1">
			<div class="fw-semibold">Pagini alese: <span id="ov-pages-total">0</span> / <?php echo esc_html( (string) $ov_limits['run'] ); ?></div>
			<div class="ov-cell-sub">Maximum <?php echo esc_html( (string) $ov_limits['site'] ); ?> pe site. Paginile legale sunt debifate implicit; pentru blog sunt alese doar cele mai recente 5 articole.</div>
			<?php ov_field_error( $ov_errors, 'page' ); ?>
			<?php if ( isset( $ov_errors['page'] ) ) : ?>
				<div class="text-danger small mt-1"><?php echo esc_html( (string) $ov_errors['page'] ); ?></div>
			<?php endif; ?>
		</div>
		<button class="btn btn-dark" type="submit" data-loading="Pornesc citirea…"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>Citește paginile alese</button>
	</div>

	<?php foreach ( $ov_sites as $ov_site ) : ?>
		<?php $ov_host = (string) $ov_site['host']; ?>
		<div class="ov-card mb-3" data-ov-site="<?php echo esc_attr( $ov_host ); ?>">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title"><?php echo esc_html( $ov_host ); ?></h2>
					<p class="ov-card-subtitle"><?php echo esc_html( sprintf( '%d pagini descoperite · alese: ', (int) $ov_site['found'] ) ); ?><span data-ov-site-count>0</span></p>
				</div>
			</div>
			<?php foreach ( (array) $ov_site['errors'] as $ov_err ) : ?>
				<div class="ov-alert ov-alert-warning"><i class="bi bi-info-circle" aria-hidden="true"></i><div><?php echo esc_html( (string) $ov_err ); ?></div></div>
			<?php endforeach; ?>
			<?php foreach ( Page_Discovery::TYPES as $ov_type => $ov_label ) : ?>
				<?php if ( empty( $ov_bysite[ $ov_host ][ $ov_type ] ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="ov-section-label mt-3"><?php echo esc_html( $ov_label ); ?> (<?php echo esc_html( (string) count( $ov_bysite[ $ov_host ][ $ov_type ] ) ); ?>)</div>
				<div class="ov-page-list">
					<?php foreach ( $ov_bysite[ $ov_host ][ $ov_type ] as $ov_p ) : ?>
						<label class="ov-page-item">
							<input class="form-check-input" type="checkbox" name="page[]" value="<?php echo esc_attr( (string) (int) $ov_p['id'] ); ?>"<?php echo ! empty( $ov_p['selected'] ) ? ' checked' : ''; ?>>
							<span class="ov-truncate">
								<span class="d-block ov-truncate"><?php echo esc_html( (string) ( $ov_p['title_hint'] ?: (string) parse_url( (string) $ov_p['url'], PHP_URL_PATH ) ?: '/' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url ?></span>
								<span class="ov-cell-sub d-block ov-truncate"><?php echo esc_html( (string) $ov_p['url'] ); ?></span>
							</span>
							<span class="ov-badge ov-badge-outline ms-auto"><?php echo esc_html( $ov_source[ $ov_p['source'] ] ?? $ov_p['source'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<div class="row g-3">
		<div class="col-12 col-lg-6">
			<div class="ov-card h-100">
				<label class="form-label" for="manual_urls">Adaugă URL-uri manual (unul pe rând, aceleași domenii)</label>
				<textarea class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'manual_urls' ) ); ?>" id="manual_urls" name="manual_urls" rows="4" spellcheck="false" placeholder="https://exemplu.ro/servicii/desfundari/"></textarea>
				<?php ov_field_error( $ov_errors, 'manual_urls' ); ?>
				<div class="form-check mt-3">
					<input type="hidden" name="robots_override" value="0">
					<input class="form-check-input" type="checkbox" id="robots_override" name="robots_override" value="1"<?php echo ! empty( $ov_draft['robots_override'] ) ? ' checked' : ''; ?>>
					<label class="form-check-label" for="robots_override">Ignoră robots.txt (am acordul clientului)</label>
				</div>
			</div>
		</div>
		<?php if ( empty( $view['reanalysis'] ) ) : ?>
			<div class="col-12 col-lg-6">
				<div class="ov-card h-100">
					<label class="form-label" for="new_sites">Adaugă alt site al aceleiași afaceri</label>
					<textarea class="form-control" id="new_sites" name="new_sites" rows="2" spellcheck="false" placeholder="alt-site.ro"></textarea>
					<div class="form-text">Dacă completați acest câmp, butonul de mai sus descoperă întâi paginile noului site.</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</form>
