<?php
/**
 * OptiVio — Authenticated application layout.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_app_name = (string) ( $view['app_name'] ?? 'OptiVio' );
?>
<!doctype html>
<html lang="ro">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="csrf-token" content="<?php echo esc_attr( $view['csrf_token'] ?? '' ); ?>">
	<meta name="app-url" content="<?php echo esc_url( ov_url( '/' ) ); ?>">
	<title><?php echo esc_html( ( $view['page_title'] ?? '' ) . ' · ' . $ov_app_name ); ?></title>
	<link rel="icon" href="<?php echo esc_url( ov_asset( 'img/favicon.svg' ) ); ?>" type="image/svg+xml">
	<link rel="stylesheet" href="<?php echo esc_url( ov_asset( 'vendor/bootstrap/bootstrap.min.css' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( ov_asset( 'vendor/bootstrap-icons/bootstrap-icons.min.css' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( ov_asset( 'css/optivio.css' ) ); ?>">
</head>
<body class="ov-body">
<div class="ov-shell">
	<?php View::partial( 'partials/sidebar', $view ); ?>

	<div class="ov-main">
		<?php View::partial( 'partials/topbar', $view ); ?>

		<main class="ov-content" id="ov-content">
			<?php View::partial( 'partials/flash', $view ); ?>
			<?php echo $view['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template, escaped internally. ?>
		</main>
	</div>
</div>
<?php View::partial( 'partials/guide-modals', $view ); ?>

<script src="<?php echo esc_url( ov_asset( 'vendor/bootstrap/bootstrap.bundle.min.js' ) ); ?>"></script>
<script src="<?php echo esc_url( ov_asset( 'vendor/chartjs/chart.umd.min.js' ) ); ?>"></script>
<script src="<?php echo esc_url( ov_asset( 'js/optivio.js' ) ); ?>"></script>
</body>
</html>
