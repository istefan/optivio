<?php
/**
 * OptiVio — Guest layout (login, setup, errors for guests).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;
?>
<!doctype html>
<html lang="ro">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( ( $view['page_title'] ?? '' ) . ' · OptiVio' ); ?></title>
	<link rel="icon" href="<?php echo esc_url( ov_asset( 'img/favicon.svg' ) ); ?>" type="image/svg+xml">
	<link rel="stylesheet" href="<?php echo esc_url( ov_asset( 'vendor/bootstrap/bootstrap.min.css' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( ov_asset( 'vendor/bootstrap-icons/bootstrap-icons.min.css' ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( ov_asset( 'css/optivio.css' ) ); ?>">
</head>
<body class="ov-body">
	<div class="ov-auth">
		<?php echo $view['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered template, escaped internally. ?>
	</div>
	<script src="<?php echo esc_url( ov_asset( 'js/optivio.js' ) ); ?>"></script>
</body>
</html>
