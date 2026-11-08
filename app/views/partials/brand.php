<?php
/**
 * OptiVio — Brand mark (logo).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;
?>
<a class="ov-brand" href="<?php echo esc_url( ov_url( '/' ) ); ?>">
	<span class="ov-brand-mark" aria-hidden="true">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
			<circle cx="11" cy="12" r="7"></circle>
			<path d="M15.5 7.5 21 3"></path>
			<path d="M17 3h4v4"></path>
		</svg>
	</span>
	<span><?php echo esc_html( $view['app_name'] ?? 'OptiVio' ); ?></span>
</a>
