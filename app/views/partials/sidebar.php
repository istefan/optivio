<?php
/**
 * OptiVio — Sidebar navigation (fixed on desktop, offcanvas on mobile).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_path  = (string) ( $view['request_path'] ?? '/' );
$ov_user  = (array) ( $view['current_user'] ?? array() );
$ov_admin = ! empty( $view['is_super_admin'] );

/**
 * Menu definition: sections => items. 'soon' items are planned for later stages.
 */
$ov_menu = array(
	array(
		'id'    => 'main',
		'label' => 'Principal',
		'items' => array(
			array( '/', 'bi-grid-1x2', 'Dashboard', 'exact' ),
			array( '/conversions', 'bi-telephone-inbound', 'Conversii', 'prefix' ),
			array( '/businesses', 'bi-buildings', 'Afaceri', 'prefix' ),
		),
	),
	array(
		'id'    => 'strategy',
		'label' => 'Analiză & strategie',
		'items' => array(
			array( '/keywords', 'bi-key', 'Cuvinte cheie', 'prefix' ),
			array( '/ads', 'bi-badge-ad', 'Google Ads', 'prefix' ),
			array( '/calls', 'bi-telephone-x', 'Apeluri & venituri', 'prefix' ),
			array( '/competitors', 'bi-binoculars', 'Concurență', 'prefix' ),
			array( '/analyses', 'bi-stars', 'Analize AI', 'prefix' ),
			array( '/actions', 'bi-card-checklist', 'Action Cards', 'prefix' ),
		),
	),
	array(
		'id'    => 'web',
		'label' => 'Site-uri & integrări',
		'items' => array(
			array( '/sites', 'bi-globe2', 'Site-uri & Connector', 'prefix' ),
			array( '/google', 'bi-google', 'Integrări Google', 'prefix' ),
			array( '/seo-audit', 'bi-clipboard2-pulse', 'Audit SEO', 'prefix' ),
		),
	),
	array(
		'id'    => 'help',
		'label' => 'Suport',
		'items' => array(
			array( '/help', 'bi-life-preserver', 'Ajutor', 'prefix' ),
		),
	),
);

if ( $ov_admin ) {
	$ov_menu[0]['items'][] = array( '/onboarding', 'bi-magic', 'Afacere nouă (wizard)', 'prefix' );
	$ov_menu[] = array(
		'id'    => 'admin',
		'label' => 'Administrare',
		'items' => array(
			array( '/users', 'bi-people', 'Utilizatori', 'prefix' ),
			array( '/audit', 'bi-shield-check', 'Jurnal audit', 'prefix' ),
			array( '/backup', 'bi-cloud-arrow-up', 'Backup Google Drive', 'prefix' ),
			array( '/settings', 'bi-sliders', 'Setări', 'prefix' ),
		),
	);
}

$ov_is_active = static function ( array $item ) use ( $ov_path ): bool {
	if ( 'exact' === $item[3] ) {
		return $ov_path === $item[0];
	}
	if ( 'prefix' === $item[3] ) {
		return $ov_path === $item[0] || 0 === strpos( $ov_path, $item[0] . '/' );
	}
	return false;
};
?>
<aside class="ov-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="ovSidebar" aria-label="Navigare principală">
	<div class="offcanvas-header d-lg-none pb-0">
		<?php View::partial( 'partials/brand', $view ); ?>
		<button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#ovSidebar" aria-label="Închide"></button>
	</div>
	<div class="offcanvas-body">
		<div class="d-none d-lg-block"><?php View::partial( 'partials/brand', $view ); ?></div>

		<nav class="ov-nav">
			<?php foreach ( $ov_menu as $ov_section ) : ?>
				<?php
				$ov_open = in_array( $ov_section['id'], array( 'main', 'admin', 'help' ), true );
				foreach ( $ov_section['items'] as $ov_item ) {
					$ov_open = $ov_open || $ov_is_active( $ov_item );
				}
				$ov_collapse_id = 'ov-nav-' . $ov_section['id'];
				?>
				<div class="ov-nav-section">
					<button class="ov-nav-heading<?php echo $ov_open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo esc_attr( $ov_collapse_id ); ?>" aria-expanded="<?php echo $ov_open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $ov_collapse_id ); ?>">
						<span><?php echo esc_html( $ov_section['label'] ); ?></span>
						<i class="bi bi-chevron-down" aria-hidden="true"></i>
					</button>
					<div class="collapse<?php echo $ov_open ? ' show' : ''; ?>" id="<?php echo esc_attr( $ov_collapse_id ); ?>">
						<?php foreach ( $ov_section['items'] as $ov_item ) : ?>
							<?php if ( 'soon' === $ov_item[3] ) : ?>
								<span class="ov-nav-link disabled" aria-disabled="true">
									<i class="bi <?php echo esc_attr( $ov_item[1] ); ?>" aria-hidden="true"></i>
									<span><?php echo esc_html( $ov_item[2] ); ?></span>
									<span class="ov-soon">curând</span>
								</span>
							<?php else : ?>
								<a class="ov-nav-link<?php echo $ov_is_active( $ov_item ) ? ' active' : ''; ?>" href="<?php echo esc_url( ov_url( $ov_item[0] ) ); ?>"<?php echo $ov_is_active( $ov_item ) ? ' aria-current="page"' : ''; ?>>
									<i class="bi <?php echo esc_attr( $ov_item[1] ); ?>" aria-hidden="true"></i>
									<span><?php echo esc_html( $ov_item[2] ); ?></span>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</nav>

		<div class="ov-user-card dropup">
			<span class="ov-avatar ov-avatar-dark"><?php echo esc_html( ov_initials( (string) ( $ov_user['display_name'] ?? '' ) ) ); ?></span>
			<div class="ov-user-meta flex-grow-1">
				<div class="ov-user-name"><?php echo esc_html( $ov_user['display_name'] ?? '' ); ?></div>
				<div class="ov-user-role"><?php echo esc_html( $ov_admin ? 'Super Admin' : 'Operator' ); ?></div>
			</div>
			<button class="btn btn-sm btn-light border-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Meniu cont">
				<i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
			</button>
			<ul class="dropdown-menu dropdown-menu-end">
				<li><a class="dropdown-item" href="<?php echo esc_url( ov_url( '/profile' ) ); ?>"><i class="bi bi-person me-2" aria-hidden="true"></i>Profilul meu</a></li>
				<li><hr class="dropdown-divider"></li>
				<li>
					<form method="post" action="<?php echo esc_url( ov_url( '/logout' ) ); ?>">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
						<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Deconectare</button>
					</form>
				</li>
			</ul>
		</div>
	</div>
</aside>
