<?php
/**
 * OptiVio — Action Card: rationale, payload editor, images, decision (apply / manual / reject),
 * AI content on demand, rollback.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Actions\Cards;
use OptiVio\Services\Remote_Operations;

$ov_c      = (array) $view['card'];
$ov_p      = (array) $ov_c['effective'];
$ov_id     = (int) $ov_c['id'];
$ov_cat    = (string) $ov_c['category'];
$ov_open   = in_array( $ov_c['status'], array( 'proposed', 'approved', 'failed' ), true );
$ov_manage = ! empty( $view['can_manage'] ) && $ov_open;
$ov_s      = Cards::STATUSES[ $ov_c['status'] ];
$ov_ctx    = (array) $view['context'];
$ov_site   = $view['site'];
$ov_ready  = null !== $ov_site && 'connected' === $ov_site['connector_status'] && null !== $ov_site['connector_remote_url'];
$ov_ai     = (array) $view['ai_preflight'];
$ov_dis    = $ov_manage ? '' : ' disabled';

$ov_input = static function ( string $key, string $label, int $max, array $p, string $dis, string $hint = '', string $counter = '' ): void {
	?>
	<div class="mb-3">
		<label class="form-label d-flex justify-content-between" for="p_<?php echo esc_attr( $key ); ?>"><span><?php echo esc_html( $label ); ?></span><?php if ( '' !== $counter ) : ?><span class="ov-cell-sub" data-ov-counter="#p_<?php echo esc_attr( $key ); ?>" data-min="<?php echo esc_attr( explode( '-', $counter )[0] ); ?>" data-max="<?php echo esc_attr( explode( '-', $counter )[1] ); ?>"></span><?php endif; ?></label>
		<input class="form-control" id="p_<?php echo esc_attr( $key ); ?>" name="payload[<?php echo esc_attr( $key ); ?>]" maxlength="<?php echo esc_attr( (string) $max ); ?>" value="<?php echo esc_attr( (string) ( $p[ $key ] ?? '' ) ); ?>"<?php echo $dis; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute. ?>>
		<?php if ( '' !== $hint ) : ?>
			<div class="form-text"><?php echo esc_html( $hint ); ?></div>
		<?php endif; ?>
	</div>
	<?php
};
$ov_area  = static function ( string $key, string $label, string $value, int $rows, string $dis, string $hint = '' ): void {
	?>
	<div class="mb-3">
		<label class="form-label" for="p_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
		<textarea class="form-control font-monospace small" id="p_<?php echo esc_attr( $key ); ?>" name="payload[<?php echo esc_attr( $key ); ?>]" rows="<?php echo esc_attr( (string) $rows ); ?>"<?php echo $dis; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute. ?>><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( '' !== $hint ) : ?>
			<div class="form-text"><?php echo esc_html( $hint ); ?></div>
		<?php endif; ?>
	</div>
	<?php
};
?>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/actions' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Action Cards</a>
	<span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?> align-self-center"><?php echo esc_html( $ov_s[1] ); ?></span>
	<?php if ( null !== $ov_c['analysis_id'] ) : ?>
		<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/analyses/' . (int) $ov_c['analysis_id'] ) ); ?>">Analiza #<?php echo esc_html( (string) $ov_c['analysis_id'] ); ?></a>
	<?php endif; ?>
</div>

<?php if ( ! empty( $ov_c['applied_note'] ) ) : ?>
	<div class="ov-alert <?php echo 'failed' === $ov_c['status'] ? 'ov-alert-error' : 'ov-alert-info'; ?>"><i class="bi bi-info-circle" aria-hidden="true"></i><div><?php echo esc_html( $ov_c['applied_note'] ); ?></div></div>
<?php endif; ?>
<?php if ( 'rejected' === $ov_c['status'] ) : ?>
	<div class="ov-alert ov-alert-info"><i class="bi bi-journal-x" aria-hidden="true"></i><div>Respins: <?php echo esc_html( (string) $ov_c['rejection_reason'] ); ?> (salvat ca regulă permanentă)</div></div>
<?php endif; ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><div><h2 class="ov-card-title">De ce</h2><p class="ov-card-subtitle"><?php echo esc_html( 'Prioritate ' . (int) $ov_c['priority'] . ' · impact ' . (int) $ov_c['impact_score'] . '/10' . ( null !== $ov_site ? ' · site: ' . $ov_site['name'] : '' ) ); ?></p></div></div>
			<p class="mb-0"><?php echo nl2br( esc_html( (string) $ov_c['rationale'] ) ); ?></p>
		</div>

		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header"><div><h2 class="ov-card-title">Propunere <?php echo null !== $ov_c['edited_payload'] ? '<span class="ov-badge ms-1">editată</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></h2><p class="ov-card-subtitle">Editați înainte de aprobare. Nimic nu se aplică pe site fără butonul „Aprobă și aplică”.</p></div></div>

			<?php if ( 'seo_meta' === $ov_cat ) : ?>
				<?php $ov_cur = $ov_ctx['content_id'] ?? null; ?>
				<p class="ov-cell-sub">Pagina: <a href="<?php echo esc_url( (string) ( $ov_cur['url'] ?? '#' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $ov_cur['title'] ?? '' ) ); ?></a> · acum: „<?php echo esc_html( (string) ( $ov_cur['seo_title_resolved'] ?? '' ) ); ?>”</p>
				<?php $ov_input( 'seo_title', 'Titlu SEO', 300, $ov_p, $ov_dis, '', '30-60' ); ?>
				<?php $ov_input( 'meta_description', 'Meta descriere', 500, $ov_p, $ov_dis, '', '70-160' ); ?>
				<?php $ov_input( 'focus_keyword', 'Cuvânt cheie focus', 190, $ov_p, $ov_dis ); ?>
			<?php elseif ( 'seo_content' === $ov_cat ) : ?>
				<?php $ov_area( 'content_html', 'Conținut nou (HTML)', (string) $ov_p['content_html'], 16, $ov_dis, 'Înlocuiește integral conținutul paginii; versiunea curentă se salvează automat în snapshot.' ); ?>
			<?php elseif ( 'internal_link' === $ov_cat ) : ?>
				<p class="ov-cell-sub">Din „<?php echo esc_html( (string) ( $ov_ctx['source_content_id']['title'] ?? '' ) ); ?>” către „<?php echo esc_html( (string) ( $ov_ctx['target_content_id']['title'] ?? '' ) ); ?>”. Paragraful se adaugă la finalul paginii sursă.</p>
				<?php $ov_input( 'anchor_text', 'Text ancoră', 120, $ov_p, $ov_dis ); ?>
				<?php $ov_area( 'paragraph_html', 'Paragraf (folosiți {{link}} pentru poziția linkului)', (string) $ov_p['paragraph_html'], 3, $ov_dis ); ?>
			<?php elseif ( in_array( $ov_cat, array( 'new_article', 'new_landing' ), true ) ) : ?>
				<?php $ov_input( 'title', 'Titlu', 200, $ov_p, $ov_dis ); ?>
				<div class="row g-2">
					<div class="col-md-6"><?php $ov_input( 'slug', 'Slug', 200, $ov_p, $ov_dis ); ?></div>
					<div class="col-md-6"><?php $ov_input( 'focus_keyword', 'Cuvânt cheie focus', 190, $ov_p, $ov_dis ); ?></div>
				</div>
				<?php $ov_input( 'seo_title', 'Titlu SEO', 300, $ov_p, $ov_dis, '', '30-60' ); ?>
				<?php $ov_input( 'meta_description', 'Meta descriere', 500, $ov_p, $ov_dis, '', '70-160' ); ?>
				<?php $ov_area( 'content_html', 'Conținut (HTML; {{image:featured}}, {{image:inline_1}} … marchează imaginile)', (string) $ov_p['content_html'], 14, $ov_dis ); ?>
				<div class="mb-3">
					<label class="form-label" for="publish_status">La aplicare</label>
					<select class="form-select w-auto" id="publish_status" name="publish_status"<?php echo $ov_dis; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<option value="draft"<?php echo 'draft' === $ov_c['publish_status'] ? ' selected' : ''; ?>>Salvează ca ciornă în WordPress</option>
						<option value="publish"<?php echo 'publish' === $ov_c['publish_status'] ? ' selected' : ''; ?>>Publică imediat</option>
					</select>
				</div>
			<?php elseif ( 'ads_negative' === $ov_cat ) : ?>
				<?php $ov_area( 'keywords', 'Cuvinte negative (unul pe linie)', implode( "\n", (array) $ov_p['keywords'] ), 5, $ov_dis ); ?>
				<div class="row g-2">
					<div class="col-md-4"><?php $ov_input( 'match_type', 'Potrivire (phrase / exact / broad)', 10, $ov_p, $ov_dis ); ?></div>
					<div class="col-md-4"><?php $ov_input( 'scope', 'Nivel (account / campaign)', 10, $ov_p, $ov_dis ); ?></div>
					<div class="col-md-4"><?php $ov_input( 'campaign', 'Campanie', 255, $ov_p, $ov_dis ); ?></div>
				</div>
			<?php elseif ( 'ads_schedule' === $ov_cat ) : ?>
				<?php $ov_input( 'hours', 'Ore (0–23, separate prin virgulă)', 100, array( 'hours' => implode( ', ', (array) $ov_p['hours'] ) ), $ov_dis ); ?>
				<div class="row g-2">
					<div class="col-md-6"><?php $ov_input( 'adjustment_pct', 'Ajustare bid % (-100 = oprit)', 5, $ov_p, $ov_dis ); ?></div>
					<div class="col-md-6"><?php $ov_input( 'days', 'Zile', 40, $ov_p, $ov_dis ); ?></div>
				</div>
			<?php elseif ( in_array( $ov_cat, array( 'ads_campaign', 'ads_new_campaign' ), true ) ) : ?>
				<?php $ov_input( 'campaign', 'Campanie', 255, $ov_p, $ov_dis ); ?>
				<?php $ov_area( 'changes', 'Modificări (una pe linie)', implode( "\n", (array) $ov_p['changes'] ), 6, $ov_dis ); ?>
			<?php else : ?>
				<?php $ov_area( 'steps', 'Pași (unul pe linie)', implode( "\n", (array) $ov_p['steps'] ), 6, $ov_dis ); ?>
			<?php endif; ?>

			<?php if ( $ov_manage ) : ?>
				<button class="btn btn-light" type="submit"><i class="bi bi-save me-1" aria-hidden="true"></i>Salvează modificările</button>
			<?php endif; ?>
		</form>

		<?php if ( in_array( $ov_cat, array( 'new_article', 'new_landing' ), true ) ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><div><h2 class="ov-card-title">Imagini</h2><p class="ov-card-subtitle">Folosiți promptul într-un generator AI de imagini sau Canva, apoi încărcați rezultatul. Imaginile se urcă în WordPress la aplicare.</p></div></div>
				<?php if ( empty( $view['images'] ) ) : ?>
					<p class="ov-cell-sub">Nicio imagine planificată.</p>
				<?php endif; ?>
				<ul class="ov-list">
					<?php foreach ( $view['images'] as $ov_img ) : ?>
						<li class="align-items-start flex-wrap">
							<?php if ( null !== $ov_img['upload_id'] ) : ?>
								<img src="<?php echo esc_url( ov_url( '/uploads/' . (int) $ov_img['upload_id'] ) ); ?>" alt="<?php echo esc_attr( (string) $ov_img['alt_text'] ); ?>" width="96" height="64" class="rounded border object-fit-cover">
							<?php else : ?>
								<span class="ov-avatar ov-avatar-icon"><i class="bi bi-image" aria-hidden="true"></i></span>
							<?php endif; ?>
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( $ov_img['placement'] . ' · ALT: ' . $ov_img['alt_text'] ); ?></div>
								<div class="ov-cell-sub text-wrap user-select-all"><?php echo esc_html( (string) $ov_img['prompt_text'] ); ?></div>
								<?php if ( $ov_manage ) : ?>
									<form class="d-flex gap-2 mt-2" method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/images/' . (int) $ov_img['id'] ) ); ?>" enctype="multipart/form-data">
										<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<input class="form-control form-control-sm" type="file" name="image" accept="image/jpeg,image/png,image/webp" required aria-label="Imagine">
										<button class="btn btn-sm btn-dark text-nowrap" type="submit"><?php echo esc_html( null !== $ov_img['upload_id'] ? 'Înlocuiește' : 'Încarcă' ); ?></button>
									</form>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $ov_manage ) : ?>
					<form class="row g-2 mt-2" method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/images' ) ); ?>">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div class="col-md-3"><input class="form-control form-control-sm" name="placement" placeholder="inline_2" required aria-label="Poziție"></div>
						<div class="col-md-4"><input class="form-control form-control-sm" name="alt" maxlength="255" placeholder="Text ALT" aria-label="ALT"></div>
						<div class="col-md-5 d-flex gap-2"><input class="form-control form-control-sm" name="prompt" placeholder="Prompt" aria-label="Prompt"><button class="btn btn-sm btn-light" type="submit">Adaugă</button></div>
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<?php if ( $ov_manage ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Decizie</h2></div>
				<?php if ( $view['remote'] ) : ?>
					<?php if ( ! $ov_ready ) : ?>
						<div class="ov-alert ov-alert-warning"><i class="bi bi-plug" aria-hidden="true"></i><div>Site-ul nu are pluginul OptiVio Connector conectat; aplicarea automată nu este disponibilă.</div></div>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/apply' ) ); ?>" data-confirm="Aprobați și aplicați această modificare pe site-ul live? Se salvează automat un snapshot pentru rollback." class="mb-2">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<button class="btn btn-dark w-100" type="submit" data-loading="Se aplică pe site…"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Aprobă și aplică pe site</button>
						</form>
					<?php endif; ?>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/manual' ) ); ?>" class="mb-2">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input class="form-control form-control-sm mb-2" name="note" maxlength="500" placeholder="Notă (ex. aplicat în Google Ads pe 08.11)" aria-label="Notă">
						<button class="btn btn-dark w-100" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Marchează ca aplicat manual</button>
					</form>
					<p class="ov-cell-sub">Modificările Google Ads / Business Profile se aplică manual în platformele Google.</p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/reject' ) ); ?>" class="mt-3">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<label class="form-label" for="reason">Motivul respingerii (devine regulă permanentă)</label>
					<textarea class="form-control mb-2" id="reason" name="reason" rows="2" maxlength="1000" required placeholder="ex. Nu facem lucrări în Ilfov după ora 20"></textarea>
					<button class="btn btn-outline-danger w-100" type="submit">Respinge</button>
				</form>
			</div>

			<?php if ( in_array( $ov_cat, array( 'seo_meta', 'seo_content', 'new_article', 'new_landing' ), true ) ) : ?>
				<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/generate' ) ); ?>">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="ov-card-header"><div><h2 class="ov-card-title">Generează cu AI</h2><p class="ov-card-subtitle"><?php echo esc_html( 'Profil copywriting · ' . $ov_ai['model'] . ' · max. ' . ( null !== $ov_ai['estimate'] ? '$' . number_format( (float) $ov_ai['estimate'], 4 ) : 'cost necunoscut' ) ); ?></p></div></div>
					<?php if ( $ov_ai['blocked'] ) : ?>
						<p class="ov-cell-sub text-danger mb-0"><?php echo esc_html( $ov_ai['reason'] ); ?></p>
					<?php else : ?>
						<?php if ( ! empty( $view['confirm_ai'] ) ) : ?>
							<div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="confirm_ai" name="confirm" value="1" required><label class="form-check-label small" for="confirm_ai">Confirm apelul AI și costul estimat</label></div>
						<?php endif; ?>
						<button class="btn btn-light w-100" type="submit" data-loading="Se generează…"><i class="bi bi-stars me-1" aria-hidden="true"></i>Completează / rescrie textele</button>
						<p class="ov-cell-sub mt-2 mb-0">Rezultatul se salvează ca propunere editată; nu se aplică automat.</p>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( 'applied' === $ov_c['status'] && ! empty( $view['can_manage'] ) && ! empty( $view['operations'] ) ) : ?>
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/actions/' . $ov_id . '/rollback' ) ); ?>" data-confirm="Restaurați versiunea dinaintea acestei modificări?">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ov-card-header"><h2 class="ov-card-title">Anulare</h2></div>
				<button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Rollback 1-click</button>
			</form>
		<?php endif; ?>

		<?php if ( ! empty( $view['operations'] ) ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Operații pe site</h2></div>
				<ul class="ov-list">
					<?php foreach ( $view['operations'] as $ov_op ) : ?>
						<li><div class="ov-list-body"><div class="ov-list-title"><?php echo esc_html( Remote_Operations::LABELS[ $ov_op['operation'] ] ?? $ov_op['operation'] ); ?></div><div class="ov-list-sub"><?php echo esc_html( ov_datetime( $ov_op['created_at'] ) . ( null !== $ov_op['remote_snapshot_id'] ? ' · snapshot #' . $ov_op['remote_snapshot_id'] : '' ) . ( ! empty( $ov_op['error'] ) ? ' · ' . $ov_op['error'] : '' ) ); ?></div></div><span class="ov-badge"><?php echo esc_html( $ov_op['status'] ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</div>
