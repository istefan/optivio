<?php
/**
 * OptiVio — Top header: page title, quick search, notifications, business switcher.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_businesses = (array) ( $view['business_options'] ?? array() );
$ov_active_id  = (int) ( $view['active_business_id'] ?? 0 );
$ov_active     = $ov_active_id > 0 ? (string) ( $view['active_business_name'] ?? '' ) : 'Toate afacerile';
$ov_notifs     = (array) ( $view['notifications'] ?? array() );
$ov_unread     = (int) ( $view['notifications_unread'] ?? 0 );
$ov_return     = (string) ( $view['request_path'] ?? '/' );
$ov_severity   = array(
	'info'     => 'bi-info-circle',
	'warning'  => 'bi-exclamation-triangle',
	'critical' => 'bi-exclamation-octagon',
);
?>
<header class="ov-topbar">
	<button class="ov-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#ovSidebar" aria-controls="ovSidebar" aria-label="Deschide meniul">
		<i class="bi bi-list" aria-hidden="true"></i>
	</button>

	<div class="me-auto">
		<h1 class="ov-page-title"><?php echo esc_html( $view['page_title'] ?? '' ); ?></h1>
		<?php if ( ! empty( $view['page_subtitle'] ) ) : ?>
			<p class="ov-page-subtitle"><?php echo esc_html( $view['page_subtitle'] ); ?></p>
		<?php endif; ?>
	</div>

	<div class="ov-topbar-actions">
		<form class="ov-search d-none d-md-block" method="get" action="<?php echo esc_url( ov_url( '/search' ) ); ?>" role="search">
			<i class="bi bi-search" aria-hidden="true"></i>
			<input class="form-control form-control-sm" type="search" name="q" placeholder="Caută afaceri, CUI…" aria-label="Căutare" maxlength="100" value="<?php echo esc_attr( $view['term'] ?? '' ); ?>">
		</form>

		<div class="dropdown">
			<button class="ov-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notificări">
				<i class="bi bi-bell" aria-hidden="true"></i>
				<?php if ( $ov_unread > 0 ) : ?>
					<span class="ov-dot"></span>
				<?php endif; ?>
			</button>
			<div class="dropdown-menu dropdown-menu-end ov-notif-menu">
				<div class="d-flex justify-content-between align-items-center px-2 py-1">
					<span class="dropdown-header p-0">Notificări</span>
					<?php if ( $ov_unread > 0 ) : ?>
						<form method="post" action="<?php echo esc_url( ov_url( '/notifications/read' ) ); ?>">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button type="submit" class="btn btn-link btn-sm p-0 ov-link-more">Marchează citite</button>
						</form>
					<?php endif; ?>
				</div>
				<?php if ( empty( $ov_notifs ) ) : ?>
					<div class="ov-empty py-4"><i class="bi bi-bell-slash" aria-hidden="true"></i>Nicio notificare</div>
				<?php else : ?>
					<?php foreach ( $ov_notifs as $ov_n ) : ?>
						<a class="ov-notif-item<?php echo empty( $ov_n['is_read'] ) ? ' unread' : ''; ?>" href="<?php echo esc_url( ! empty( $ov_n['url'] ) && 0 === strpos( (string) $ov_n['url'], '/' ) ? ov_url( (string) $ov_n['url'] ) : '#' ); ?>">
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi <?php echo esc_attr( $ov_severity[ $ov_n['severity'] ] ?? 'bi-info-circle' ); ?>" aria-hidden="true"></i></span>
							<span class="min-w-0">
								<span class="d-block ov-notif-title"><?php echo esc_html( $ov_n['title'] ); ?></span>
								<span class="d-block ov-cell-sub"><?php echo esc_html( ov_time_ago( $ov_n['created_at'] ) ); ?></span>
							</span>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="dropdown">
			<button class="ov-switcher dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Afacerea activă">
				<i class="bi bi-buildings" aria-hidden="true"></i>
				<span><?php echo esc_html( $ov_active ); ?></span>
			</button>
			<div class="dropdown-menu dropdown-menu-end ov-switcher-menu">
				<span class="dropdown-header">Afacerea activă</span>
				<form method="post" action="<?php echo esc_url( ov_url( '/context/business' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input type="hidden" name="return" value="<?php echo esc_attr( $ov_return ); ?>">
					<button type="submit" name="business_id" value="0" class="dropdown-item<?php echo 0 === $ov_active_id ? ' active' : ''; ?>">
						<i class="bi bi-collection me-2" aria-hidden="true"></i>Toate afacerile
					</button>
					<?php if ( ! empty( $ov_businesses ) ) : ?>
						<hr class="dropdown-divider">
					<?php endif; ?>
					<?php foreach ( $ov_businesses as $ov_bid => $ov_bname ) : ?>
						<button type="submit" name="business_id" value="<?php echo esc_attr( (string) $ov_bid ); ?>" class="dropdown-item text-truncate<?php echo (int) $ov_bid === $ov_active_id ? ' active' : ''; ?>">
							<?php echo esc_html( $ov_bname ); ?>
						</button>
					<?php endforeach; ?>
				</form>
			</div>
		</div>
	</div>
</header>
