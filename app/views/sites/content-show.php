<?php
/**
 * OptiVio — Mirrored content detail: on-page metrics, SEO editor (applied remotely), history + rollback.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_c      = (array) $view['content'];
$ov_sid    = (int) $view['site']['id'];
$ov_errors = (array) $view['errors'];
$ov_old    = (array) $view['old'];
$ov_manage = ! empty( $view['can_manage'] );
$ov_head   = (array) ( json_decode( (string) $ov_c['headings'], true ) ?: array() );
$ov_val    = static function ( string $key ) use ( $ov_old, $ov_c ): string {
	return array_key_exists( $key, $ov_old ) ? (string) $ov_old[ $key ] : (string) ( $ov_c[ $key ] ?? '' );
};
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Conținut & SEO</a>
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( $ov_c['url'] ); ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Vezi pe site</a>
</div>

<div class="row g-3">
	<div class="col-12 col-xl-7 d-flex flex-column gap-3">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content/' . (int) $ov_c['id'] ) ); ?>" novalidate data-confirm="Aplicați modificările pe site-ul live?">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">SEO (Yoast) & titlu</h2>
					<p class="ov-card-subtitle">„Aplică pe site" = aprobare explicită. Se salvează automat un snapshot pentru rollback.</p>
				</div>
			</div>
			<fieldset<?php echo $ov_manage ? '' : ' disabled'; ?>>
				<?php if ( 'post' === $ov_c['object_type'] ) : ?>
					<div class="mb-3">
						<label class="form-label" for="title">Titlu pagină / articol</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'title' ) ); ?>" id="title" name="title" maxlength="300" value="<?php echo esc_attr( $ov_val( 'title' ) ); ?>">
						<?php ov_field_error( $ov_errors, 'title' ); ?>
					</div>
				<?php endif; ?>
				<div class="mb-3">
					<label class="form-label d-flex justify-content-between" for="seo_title"><span>Titlu SEO</span><span class="ov-cell-sub" data-ov-counter="#seo_title" data-min="30" data-max="60"></span></label>
					<input class="form-control" id="seo_title" name="seo_title" maxlength="300" value="<?php echo esc_attr( $ov_val( 'seo_title' ) ); ?>" placeholder="<?php echo esc_attr( (string) ( $ov_c['seo_title_resolved'] ?? '' ) ); ?>">
					<div class="form-text">Afișat acum: <?php echo esc_html( (string) ( $ov_c['seo_title_resolved'] ?? '—' ) ); ?></div>
				</div>
				<div class="mb-3">
					<label class="form-label d-flex justify-content-between" for="meta_description"><span>Meta descriere</span><span class="ov-cell-sub" data-ov-counter="#meta_description" data-min="70" data-max="160"></span></label>
					<textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="500"><?php echo esc_textarea( $ov_val( 'meta_description' ) ); ?></textarea>
				</div>
				<div class="row g-2 mb-3">
					<div class="col-md-6">
						<label class="form-label" for="focus_keyword">Cuvânt cheie focus</label>
						<input class="form-control" id="focus_keyword" name="focus_keyword" maxlength="190" value="<?php echo esc_attr( $ov_val( 'focus_keyword' ) ); ?>">
					</div>
					<div class="col-md-6">
						<label class="form-label" for="canonical">URL canonic</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'canonical' ) ); ?>" id="canonical" name="canonical" maxlength="500" value="<?php echo esc_attr( $ov_val( 'canonical' ) ); ?>" placeholder="implicit">
						<?php ov_field_error( $ov_errors, 'canonical' ); ?>
					</div>
				</div>
				<div class="form-check mb-3">
					<input type="hidden" name="noindex" value="0">
					<input class="form-check-input" type="checkbox" id="noindex" name="noindex" value="1"<?php echo ! empty( $ov_c['robots_noindex'] ) ? ' checked' : ''; ?>>
					<label class="form-check-label" for="noindex">noindex (exclude din Google)</label>
				</div>
				<?php if ( $ov_manage ) : ?>
					<button class="btn btn-dark" type="submit"><i class="bi bi-cloud-upload me-1" aria-hidden="true"></i>Aplică pe site</button>
				<?php endif; ?>
			</fieldset>
		</form>

		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Istoric modificări</h2></div>
			<?php
			View::partial(
				'partials/operations-table',
				array(
					'history'    => $view['history'],
					'site_id'    => $ov_sid,
					'can_manage' => $ov_manage,
				)
			);
			?>
		</div>
	</div>

	<div class="col-12 col-xl-5 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Metrici on-page</h2></div>
			<dl class="ov-dl">
				<dt>Cuvinte</dt><dd><?php echo esc_html( ov_number( $ov_c['word_count'] ) ); ?></dd>
				<dt>H1</dt><dd><?php echo esc_html( (string) (int) $ov_c['h1_count'] ); ?></dd>
				<dt>Imagini fără ALT</dt><dd><?php echo esc_html( (int) $ov_c['images_missing_alt'] . ' din ' . (int) $ov_c['images_count'] ); ?></dd>
				<dt>Linkuri interne</dt><dd><?php echo esc_html( (int) $ov_c['internal_links_in'] . ' primite · ' . (int) $ov_c['internal_links_out'] . ' trimise' ); ?></dd>
				<dt>Scor Yoast SEO / lizibilitate</dt><dd><?php echo esc_html( ( $ov_c['yoast_seo_score'] ?? '—' ) . ' / ' . ( $ov_c['yoast_read_score'] ?? '—' ) ); ?></dd>
				<dt>Status</dt><dd><?php echo esc_html( $ov_c['status'] ); ?></dd>
				<dt>Modificat în WP</dt><dd><?php echo esc_html( ov_datetime( $ov_c['modified_gmt'] ) ); ?></dd>
				<dt>Sincronizat</dt><dd><?php echo esc_html( ov_datetime( $ov_c['synced_at'] ) ); ?></dd>
			</dl>
		</div>

		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Structura headings</h2></div>
			<?php if ( empty( $ov_head ) ) : ?>
				<p class="ov-cell-sub mb-0">Niciun heading.</p>
			<?php else : ?>
				<ul class="list-unstyled mb-0 small">
					<?php foreach ( $ov_head as $ov_h ) : ?>
						<li class="py-1" style="padding-left: <?php echo esc_attr( (string) ( ( (int) $ov_h['level'] - 1 ) * 14 ) ); ?>px"><span class="ov-badge me-1">H<?php echo esc_html( (string) (int) $ov_h['level'] ); ?></span><?php echo esc_html( $ov_h['text'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Linkuri interne primite</h2></div>
			<?php if ( empty( $view['links_in'] ) ) : ?>
				<p class="ov-cell-sub mb-0">Nicio pagină nu trimite către aceasta (pagină orfană).</p>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['links_in'] as $ov_l ) : ?>
						<li><div class="ov-list-body"><a class="ov-list-title d-block" href="<?php echo esc_url( ov_url( '/sites/' . $ov_sid . '/content/' . (int) $ov_l['id'] ) ); ?>"><?php echo esc_html( $ov_l['title'] ?? $ov_l['url'] ); ?></a><div class="ov-list-sub">ancoră: „<?php echo esc_html( $ov_l['anchor_text'] ?? '' ); ?>”</div></div></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<?php if ( (int) $ov_c['images_missing_alt'] > 0 ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Imagini fără ALT</h2></div>
				<ul class="ov-list">
					<?php foreach ( $view['images'] as $ov_img ) : ?>
						<?php if ( '' === (string) $ov_img['alt'] ) : ?>
							<li><div class="ov-list-body"><div class="ov-list-sub"><?php echo esc_html( $ov_img['src'] ); ?></div></div></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
