<?php
/**
 * OptiVio — Mirrored WordPress content with on-page SEO metrics.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_site = (array) $view['site'];
$ov_sid  = (int) $ov_site['id'];
$ov_len  = static function ( ?string $value, int $min, int $max ): array {
	$n = mb_strlen( (string) $value );
	if ( 0 === $n ) {
		return array( 'ov-badge-danger', 'lipsă' );
	}
	return array( $n < $min || $n > $max ? 'ov-badge-warning' : 'ov-badge-success', (string) $n );
};
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?php echo esc_html( $ov_site['name'] ); ?></a>
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/operations' ) ); ?>"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Istoric modificări</a>
	<form class="d-flex gap-2 ms-auto" method="get" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content' ) ); ?>">
		<select class="form-select form-select-sm" name="type" aria-label="Tip">
			<option value="">Toate tipurile</option>
			<?php foreach ( $view['types'] as $ov_t ) : ?>
				<option value="<?php echo esc_attr( $ov_t ); ?>"<?php echo $ov_t === $view['type'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_t ); ?></option>
			<?php endforeach; ?>
			<option value="term"<?php echo 'term' === $view['type'] ? ' selected' : ''; ?>>categorii / taxonomii</option>
		</select>
		<input class="form-control form-control-sm" type="search" name="q" maxlength="100" placeholder="Caută titlu sau URL" value="<?php echo esc_attr( $view['term'] ); ?>" aria-label="Căutare">
		<button class="btn btn-sm btn-dark" type="submit">Filtrează</button>
	</form>
	<?php if ( ! empty( $view['can_manage'] ) ) : ?>
		<form method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/resync' ) ); ?>">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<button class="btn btn-sm btn-light" type="submit"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Resincronizează</button>
		</form>
	<?php endif; ?>
</div>

<div class="ov-card">
	<div class="ov-card-header">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( ov_number( $view['total'] ) ); ?> obiecte oglindite</h2>
			<p class="ov-card-subtitle">Ultima sincronizare: <?php echo esc_html( ov_datetime( $ov_site['content_synced_at'] ) ); ?> · lungimi recomandate: titlu SEO 30–60, meta descriere 70–160</p>
		</div>
	</div>
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-file-earmark-text" aria-hidden="true"></i><div class="ov-empty-title">Niciun conținut sincronizat</div>Instalați pluginul OptiVio Connector și rulați „Sincronizare completă conținut".</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead>
					<tr>
						<th scope="col">Titlu</th>
						<th scope="col">Tip</th>
						<th scope="col" class="text-center">Titlu SEO</th>
						<th scope="col" class="text-center">Meta desc.</th>
						<th scope="col">Cuvânt focus</th>
						<th scope="col" class="text-end">Cuvinte</th>
						<th scope="col" class="text-center">H1</th>
						<th scope="col" class="text-center">Fără ALT</th>
						<th scope="col" class="text-center">Linkuri in/out</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<?php
						$ov_t = $ov_len( $ov_row['seo_title_resolved'] ?? $ov_row['seo_title'] ?? $ov_row['title'], 30, 60 );
						$ov_d = $ov_len( $ov_row['meta_description_resolved'] ?? $ov_row['meta_description'], 70, 160 );
						?>
						<tr>
							<td>
								<a class="ov-cell-main d-block ov-truncate" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content/' . (int) $ov_row['id'] ) ); ?>"><?php echo esc_html( $ov_row['title'] ?: '(fără titlu)' ); ?></a>
								<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['url'] ); ?></div>
							</td>
							<td>
								<span class="ov-badge"><?php echo esc_html( $ov_row['subtype'] ); ?></span>
								<?php if ( 'publish' !== $ov_row['status'] ) : ?>
									<span class="ov-badge ov-badge-outline"><?php echo esc_html( $ov_row['status'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $ov_row['robots_noindex'] ) ) : ?>
									<span class="ov-badge ov-badge-warning">noindex</span>
								<?php endif; ?>
							</td>
							<td class="text-center"><span class="ov-badge <?php echo esc_attr( $ov_t[0] ); ?>"><?php echo esc_html( $ov_t[1] ); ?></span></td>
							<td class="text-center"><span class="ov-badge <?php echo esc_attr( $ov_d[0] ); ?>"><?php echo esc_html( $ov_d[1] ); ?></span></td>
							<td class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['focus_keyword'] ?? '—' ); ?></td>
							<td class="text-end<?php echo 'post' === $ov_row['object_type'] && (int) $ov_row['word_count'] < 300 ? ' text-danger' : ''; ?>"><?php echo esc_html( ov_number( $ov_row['word_count'] ) ); ?></td>
							<td class="text-center<?php echo 'post' === $ov_row['object_type'] && 1 !== (int) $ov_row['h1_count'] ? ' text-danger' : ''; ?>"><?php echo esc_html( (string) (int) $ov_row['h1_count'] ); ?></td>
							<td class="text-center<?php echo (int) $ov_row['images_missing_alt'] > 0 ? ' text-danger' : ''; ?>"><?php echo esc_html( (int) $ov_row['images_missing_alt'] . '/' . (int) $ov_row['images_count'] ); ?></td>
							<td class="text-center<?php echo 'post' === $ov_row['object_type'] && 0 === (int) $ov_row['internal_links_in'] ? ' text-danger' : ''; ?>"><?php echo esc_html( (int) $ov_row['internal_links_in'] . ' / ' . (int) $ov_row['internal_links_out'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		View::partial(
			'partials/pagination',
			array(
				'page'  => $view['page'],
				'pages' => $view['pages'],
				'base'  => '/sites/' . $ov_sid . '/content',
				'query' => array_filter(
					array(
						'type' => $view['type'],
						'q'    => $view['term'],
					)
				),
			)
		);
		?>
	<?php endif; ?>
</div>
