<?php
/**
 * OptiVio — Business section tabs.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view business, tab, can_manage.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_bid  = (int) $view['business']['id'];
$ov_tabs = array(
	'overview'  => array( '', 'Prezentare', true ),
	'profile'   => array( '/edit', 'Profil', ! empty( $view['can_manage'] ?? true ) ),
	'contacts'  => array( '/contacts', 'Contacte', true ),
	'offerings' => array( '/offerings', 'Servicii & produse', true ),
	'usp'       => array( '/usp', 'USP', true ),
	'sites'     => array( '/sites', 'Site-uri', true ),
	'rules'     => array( '/rules', 'Reguli', true ),
	'perception' => array( '/perception', 'Percepție', true ),
	'ai'        => array( '/ai', 'AI & conectori', true ),
);
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/businesses' ) ); ?>" aria-label="Înapoi la afaceri"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
	<nav class="ov-tabs mb-0 flex-grow-1" aria-label="Secțiuni afacere">
		<?php foreach ( $ov_tabs as $ov_key => $ov_tab ) : ?>
			<?php if ( $ov_tab[2] ) : ?>
				<a class="<?php echo ( $view['tab'] ?? '' ) === $ov_key ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . $ov_tab[0] ) ); ?>"><?php echo esc_html( $ov_tab[1] ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
</div>
