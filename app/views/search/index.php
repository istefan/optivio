<?php
/**
 * OptiVio — Search results.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;
?>
<form class="ov-card mb-3 d-md-none" method="get" action="<?php echo esc_url( ov_url( '/search' ) ); ?>" role="search">
	<div class="input-group">
		<input class="form-control" type="search" name="q" maxlength="100" placeholder="Caută afaceri, CUI…" value="<?php echo esc_attr( $view['term'] ); ?>" aria-label="Căutare">
		<button class="btn btn-dark" type="submit"><i class="bi bi-search" aria-hidden="true"></i></button>
	</div>
</form>

<div class="ov-card">
	<?php if ( mb_strlen( (string) $view['term'] ) < 2 ) : ?>
		<div class="ov-empty"><i class="bi bi-search" aria-hidden="true"></i>Introduceți cel puțin 2 caractere.</div>
	<?php elseif ( empty( $view['results'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-search" aria-hidden="true"></i><div class="ov-empty-title">Niciun rezultat</div>Încercați alt termen.</div>
	<?php else : ?>
		<ul class="ov-list">
			<?php foreach ( $view['results'] as $ov_row ) : ?>
				<li>
					<span class="ov-avatar ov-avatar-sm"><?php echo esc_html( ov_initials( (string) $ov_row['name'] ) ); ?></span>
					<div class="ov-list-body">
						<a class="ov-list-title d-block" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_row['id'] ) ); ?>"><?php echo esc_html( $ov_row['name'] ); ?></a>
						<div class="ov-list-sub"><?php echo esc_html( trim( ( $ov_row['legal_name'] ?? '' ) . ' ' . ( $ov_row['cui'] ?? '' ) ) ?: 'Afacere' ); ?></div>
					</div>
					<span class="ov-badge"><?php echo esc_html( $ov_row['status'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
