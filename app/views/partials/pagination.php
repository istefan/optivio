<?php
/**
 * OptiVio — Pagination.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view page, pages, base (path), query (array).
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_page  = (int) ( $view['page'] ?? 1 );
$ov_pages = (int) ( $view['pages'] ?? 1 );
$ov_base  = (string) ( $view['base'] ?? '/' );
$ov_query = (array) ( $view['query'] ?? array() );

if ( $ov_pages <= 1 ) {
	return;
}

$ov_from = max( 1, $ov_page - 2 );
$ov_to   = min( $ov_pages, $ov_page + 2 );
?>
<nav aria-label="Paginare" class="mt-3">
	<ul class="pagination pagination-sm justify-content-end mb-0">
		<li class="page-item<?php echo $ov_page <= 1 ? ' disabled' : ''; ?>">
			<a class="page-link" href="<?php echo esc_url( ov_url( $ov_base, array_merge( $ov_query, array( 'page' => max( 1, $ov_page - 1 ) ) ) ) ); ?>" aria-label="Anterior">&lsaquo;</a>
		</li>
		<?php for ( $ov_i = $ov_from; $ov_i <= $ov_to; $ov_i++ ) : ?>
			<li class="page-item<?php echo $ov_i === $ov_page ? ' active' : ''; ?>">
				<a class="page-link" href="<?php echo esc_url( ov_url( $ov_base, array_merge( $ov_query, array( 'page' => $ov_i ) ) ) ); ?>"><?php echo esc_html( (string) $ov_i ); ?></a>
			</li>
		<?php endfor; ?>
		<li class="page-item<?php echo $ov_page >= $ov_pages ? ' disabled' : ''; ?>">
			<a class="page-link" href="<?php echo esc_url( ov_url( $ov_base, array_merge( $ov_query, array( 'page' => min( $ov_pages, $ov_page + 1 ) ) ) ) ); ?>" aria-label="Următor">&rsaquo;</a>
		</li>
	</ul>
</nav>
