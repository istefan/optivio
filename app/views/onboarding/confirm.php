<?php
/**
 * OptiVio — Onboarding step 3: confirm or correct what the AI understood (creates the business).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_draft  = (array) $view['draft'];
$ov_id     = (int) $ov_draft['id'];
$ov_r      = (array) $view['perception']['result'];
$ov_det    = (array) ( $ov_r['deterministic'] ?? array() );
$ov_old    = (array) ( $view['old'] ?? array() );
$ov_errors = (array) ( $view['errors'] ?? array() );
$ov_sites  = (array) ( $ov_draft['state']['sites'] ?? array() );

$ov_first = static function ( ...$lists ): string {
	foreach ( $lists as $list ) {
		foreach ( (array) $list as $v ) {
			if ( '' !== (string) $v ) {
				return (string) $v;
			}
		}
	}
	return '';
};
$ov_prefill = array(
	'name'            => (string) ( $ov_r['business_name']['value'] ?? $ov_r['brand'] ?? '' ),
	'business_model'  => (string) ( $ov_r['business_model']['value'] ?? 'other' ),
	'description'     => (string) ( $ov_r['summary'] ?? '' ),
	'target_audience' => (string) ( $ov_r['target_audience']['value'] ?? '' ),
	'service_area'    => implode( ', ', array_column( (array) ( $ov_r['service_areas'] ?? array() ), 'name' ) ),
	'phone'           => $ov_first( $ov_det['phones'] ?? array(), $ov_r['contact']['phones'] ?? array() ),
	'billing_email'   => $ov_first( $ov_det['emails'] ?? array(), $ov_r['contact']['emails'] ?? array() ),
	'cui'             => $ov_first( $ov_det['cui'] ?? array(), array( $ov_r['legal']['cui'] ?? '' ) ),
	'reg_com'         => $ov_first( $ov_det['reg_com'] ?? array(), array( $ov_r['legal']['reg_com'] ?? '' ) ),
	'legal_name'      => (string) ( $ov_r['legal']['company_name'] ?? '' ),
);
$ov_val = static function ( string $key ) use ( $ov_old, $ov_prefill ): string {
	return (string) ( $ov_old[ $key ] ?? $ov_prefill[ $key ] ?? '' );
};
$ov_found = array(
	'phone'         => ! empty( $ov_det['phones'] ),
	'billing_email' => ! empty( $ov_det['emails'] ),
	'cui'           => ! empty( $ov_det['cui'] ),
	'reg_com'       => ! empty( $ov_det['reg_com'] ),
);

// "AI a înțeles: …" note under a field.
$ov_note = static function ( ?array $f, string $shown = '' ): void {
	if ( null === $f && '' === $shown ) {
		echo '<div class="ov-ai-note is-missing"><i class="bi bi-robot" aria-hidden="true"></i> AI nu a găsit această informație pe site.</div>';
		return;
	}
	?>
	<div class="ov-ai-note">
		<i class="bi bi-robot" aria-hidden="true"></i>
		AI a înțeles: <strong><?php echo esc_html( '' !== $shown ? $shown : (string) ( $f['value'] ?? '' ) ); ?></strong>
		<?php if ( null !== $f ) : ?>
			<span class="ov-cell-sub">· încredere <?php echo esc_html( (string) (int) round( 100 * (float) ( $f['confidence'] ?? 0 ) ) ); ?>%</span>
			<?php if ( '' !== (string) ( $f['source_url'] ?? '' ) ) : ?>
				<a class="ov-cell-sub" href="<?php echo esc_url( (string) $f['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">sursa</a>
			<?php endif; ?>
			<?php if ( '' !== (string) ( $f['evidence'] ?? '' ) ) : ?>
				<div class="ov-cell-sub fst-italic">„<?php echo esc_html( (string) $f['evidence'] ); ?>”</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
};
$ov_verdict = static function ( string $field ) use ( $ov_old ): void {
	$current = (string) ( $ov_old[ 'verdict_' . $field ] ?? 'ok' );
	?>
	<div class="ov-verdict" role="radiogroup" aria-label="Verdict">
		<?php foreach ( array( 'ok' => 'Corect', 'corrected' => 'Corectez', 'na' => 'Nu se aplică' ) as $ov_k => $ov_l ) : ?>
			<label><input type="radio" name="verdict_<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $current ? ' checked' : ''; ?>> <?php echo esc_html( $ov_l ); ?></label>
		<?php endforeach; ?>
	</div>
	<?php
};
$ov_input = static function ( string $name, string $label, string $col = 'col-md-6' ) use ( $ov_val, $ov_errors, $ov_found ): void {
	?>
	<div class="<?php echo esc_attr( $col ); ?>">
		<label class="form-label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?>
			<?php if ( ! empty( $ov_found[ $name ] ) ) : ?>
				<span class="ov-badge ov-badge-outline ms-1">găsit în pagină</span>
			<?php endif; ?>
		</label>
		<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, $name ) ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $ov_val( $name ) ); ?>">
		<?php ov_field_error( $ov_errors, $name ); ?>
	</div>
	<?php
};
$ov_ai_pillars = array();
foreach ( (array) ( $ov_r['usp_pillars'] ?? array() ) as $ov_p ) {
	$ov_ai_pillars[ $ov_p['key'] ] = $ov_p;
}
$ov_checked_pillars = isset( $ov_old['pillars'] ) ? (array) $ov_old['pillars'] : array_keys( $ov_ai_pillars );
?>
<?php View::partial( 'partials/onboarding-steps', $view ); ?>

<div class="ov-alert ov-alert-info"><i class="bi bi-info-circle" aria-hidden="true"></i><div>Sub fiecare câmp vedeți <strong>ce a înțeles AI-ul</strong> din site. Corectați ce nu e adevărat: diferențele (percepție vs realitate) se salvează și devin recomandări. La salvare se creează afacerea.</div></div>

<form method="post" action="<?php echo esc_url( ov_url( '/onboarding/' . $ov_id . '/confirm' ) ); ?>" novalidate>
	<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="row g-3">
		<div class="col-12 col-xl-8 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Identitate și model de afacere</h2></div>
				<div class="row g-3">
					<div class="col-md-6">
						<label class="form-label" for="name">Numele afacerii</label>
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'name' ) ); ?>" id="name" name="name" value="<?php echo esc_attr( $ov_val( 'name' ) ); ?>" required maxlength="160">
						<?php ov_field_error( $ov_errors, 'name' ); ?>
						<?php $ov_note( $ov_r['business_name'] ?? null ); ?>
					</div>
					<div class="col-md-6">
						<label class="form-label" for="business_model">Model de afacere</label>
						<select class="form-select<?php echo esc_attr( ov_invalid( $ov_errors, 'business_model' ) ); ?>" id="business_model" name="business_model">
							<?php foreach ( ov_business_models() as $ov_k => $ov_l ) : ?>
								<option value="<?php echo esc_attr( $ov_k ); ?>"<?php echo $ov_k === $ov_val( 'business_model' ) ? ' selected' : ''; ?>><?php echo esc_html( $ov_l ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php ov_field_error( $ov_errors, 'business_model' ); ?>
						<?php $ov_note( $ov_r['business_model'] ?? null, isset( $ov_r['business_model']['value'] ) ? ov_business_model_label( (string) $ov_r['business_model']['value'] ) : '' ); ?>
						<?php $ov_verdict( 'business_model' ); ?>
					</div>
					<div class="col-12">
						<label class="form-label" for="description">Descrierea afacerii</label>
						<textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"><?php echo esc_textarea( $ov_val( 'description' ) ); ?></textarea>
						<div class="ov-ai-note"><i class="bi bi-robot" aria-hidden="true"></i> Precompletat cu descrierea făcută de AI. Rescrieți-o cum este în realitate.</div>
						<?php $ov_verdict( 'summary' ); ?>
					</div>
					<div class="col-12">
						<label class="form-label" for="target_audience">Public țintă</label>
						<textarea class="form-control" id="target_audience" name="target_audience" rows="2" maxlength="3000"><?php echo esc_textarea( $ov_val( 'target_audience' ) ); ?></textarea>
						<?php $ov_note( $ov_r['target_audience'] ?? null ); ?>
						<?php $ov_verdict( 'target_audience' ); ?>
					</div>
					<div class="col-12">
						<label class="form-label" for="service_area">Zone deservite (separate prin virgulă)</label>
						<input class="form-control" id="service_area" name="service_area" value="<?php echo esc_attr( $ov_val( 'service_area' ) ); ?>" maxlength="255">
						<div class="ov-ai-note"><i class="bi bi-robot" aria-hidden="true"></i>
							<?php if ( empty( $ov_r['service_areas'] ) ) : ?>
								AI nu a găsit zone deservite pe site.
							<?php else : ?>
								AI a înțeles: <?php echo esc_html( implode( ', ', array_column( (array) $ov_r['service_areas'], 'name' ) ) ); ?>. Ștergeți zonele în care nu lucrați și adăugați-le pe cele lipsă.
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Servicii</h2>
						<p class="ov-card-subtitle">Debifați serviciile pe care afacerea NU le oferă (AI le-a dedus greșit). Adăugați serviciile reale pe care AI nu le-a găsit.</p>
					</div>
				</div>
				<?php if ( isset( $ov_errors['services'] ) ) : ?>
					<div class="ov-alert ov-alert-error"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><div><?php echo esc_html( (string) $ov_errors['services'] ); ?></div></div>
				<?php endif; ?>
				<?php if ( empty( $ov_r['services'] ) ) : ?>
					<p class="ov-cell-sub">AI nu a identificat servicii pe site.</p>
				<?php endif; ?>
				<?php foreach ( (array) ( $ov_r['services'] ?? array() ) as $ov_i => $ov_s ) : ?>
					<div class="ov-svc-row">
						<input class="form-check-input mt-2" type="checkbox" name="svc_keep[<?php echo esc_attr( (string) $ov_i ); ?>]" value="1" aria-label="Păstrez serviciul"<?php echo (float) $ov_s['confidence'] >= 0.5 ? ' checked' : ''; ?>>
						<div class="flex-grow-1">
							<input class="form-control form-control-sm" name="svc_name[<?php echo esc_attr( (string) $ov_i ); ?>]" value="<?php echo esc_attr( (string) $ov_s['name'] ); ?>" maxlength="160" aria-label="Nume serviciu">
							<div class="ov-cell-sub mt-1">
								încredere <?php echo esc_html( (string) (int) round( 100 * (float) $ov_s['confidence'] ) ); ?>%
								<?php if ( '' !== (string) $ov_s['price_hint'] ) : ?>
									· preț: <?php echo esc_html( (string) $ov_s['price_hint'] ); ?>
								<?php endif; ?>
								<?php if ( '' !== (string) $ov_s['source_url'] ) : ?>
									· <a href="<?php echo esc_url( (string) $ov_s['source_url'] ); ?>" target="_blank" rel="noopener noreferrer">sursa</a>
								<?php endif; ?>
								<?php if ( '' !== (string) $ov_s['evidence'] ) : ?>
									· <span class="fst-italic">„<?php echo esc_html( (string) $ov_s['evidence'] ); ?>”</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="ov-section-label mt-3">Servicii reale pe care AI nu le-a găsit</div>
				<div class="row g-2">
					<?php for ( $ov_n = 0; $ov_n < 6; $ov_n++ ) : ?>
						<div class="col-md-6"><input class="form-control form-control-sm" name="svc_new[]" maxlength="160" placeholder="Serviciu nou" aria-label="Serviciu nou"></div>
					<?php endfor; ?>
				</div>
			</div>

			<div class="ov-card">
				<div class="ov-card-header">
					<div>
						<h2 class="ov-card-title">Puncte forte (USP) reale</h2>
						<p class="ov-card-subtitle">Bifați doar ce este adevărat. Cele bifate pe care AI nu le-a găsit înseamnă mesaje lipsă de pe site.</p>
					</div>
				</div>
				<div class="row g-2">
					<?php foreach ( $view['pillars'] as $ov_k => $ov_l ) : ?>
						<div class="col-md-6">
							<label class="ov-pillar">
								<input class="form-check-input" type="checkbox" name="pillars[]" value="<?php echo esc_attr( $ov_k ); ?>"<?php echo in_array( $ov_k, $ov_checked_pillars, true ) ? ' checked' : ''; ?>>
								<span>
									<?php echo esc_html( $ov_l ); ?>
									<?php if ( isset( $ov_ai_pillars[ $ov_k ] ) ) : ?>
										<span class="ov-badge ov-badge-outline ms-1">AI: găsit</span>
										<?php if ( '' !== (string) ( $ov_ai_pillars[ $ov_k ]['evidence'] ?? '' ) ) : ?>
											<span class="d-block ov-cell-sub fst-italic">„<?php echo esc_html( (string) $ov_ai_pillars[ $ov_k ]['evidence'] ); ?>”</span>
										<?php endif; ?>
									<?php endif; ?>
								</span>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-4 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Contact și date legale</h2></div>
				<div class="row g-3">
					<?php $ov_input( 'phone', 'Telefon principal', 'col-12' ); ?>
					<?php $ov_input( 'billing_email', 'Email', 'col-12' ); ?>
					<?php $ov_input( 'legal_name', 'Denumire legală', 'col-12' ); ?>
					<?php $ov_input( 'cui', 'CUI' ); ?>
					<?php $ov_input( 'reg_com', 'Reg. Com.' ); ?>
				</div>
			</div>

			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Site-uri</h2></div>
				<?php foreach ( $ov_sites as $ov_site ) : ?>
					<div class="d-flex align-items-center gap-2 mb-2">
						<span class="flex-grow-1 ov-truncate"><?php echo esc_html( (string) $ov_site['host'] ); ?></span>
						<select class="form-select form-select-sm w-auto" name="site_cms[<?php echo esc_attr( (string) $ov_site['host'] ); ?>]" aria-label="Platformă">
							<option value="wordpress">WordPress</option>
							<option value="other">Altă platformă</option>
						</select>
					</div>
				<?php endforeach; ?>
				<p class="ov-cell-sub mb-0">Site-urile WordPress primesc automat cheia pentru plugin (pasul 5).</p>
			</div>

			<?php if ( ! empty( $ov_r['missing_or_unclear'] ) ) : ?>
				<div class="ov-card">
					<div class="ov-card-header"><h2 class="ov-card-title">Neclar pentru un bot</h2></div>
					<ul class="ov-list mb-0">
						<?php foreach ( (array) $ov_r['missing_or_unclear'] as $ov_u ) : ?>
							<li class="d-block"><strong><?php echo esc_html( (string) $ov_u['topic'] ); ?></strong><div class="ov-cell-sub"><?php echo esc_html( (string) $ov_u['explanation'] ); ?></div></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="ov-card">
				<button class="btn btn-dark w-100" type="submit" data-loading="Creez afacerea…"><i class="bi bi-building-add me-1" aria-hidden="true"></i>Creează afacerea</button>
				<p class="ov-cell-sub mt-2 mb-0">Se calculează scorul de claritate și recomandările din diferențe (fără AI).</p>
			</div>
		</div>
	</div>
</form>

<?php if ( ! empty( $ov_r['per_page'] ) ) : ?>
	<div class="ov-card mt-3">
		<div class="ov-card-header"><h2 class="ov-card-title">Cum a înțeles fiecare pagină</h2></div>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead><tr><th>Pagină</th><th>Înțeleasă ca</th><th class="text-end">Claritate</th></tr></thead>
				<tbody>
					<?php foreach ( (array) $ov_r['per_page'] as $ov_pp ) : ?>
						<tr>
							<td class="ov-truncate" style="max-width: 320px"><a href="<?php echo esc_url( (string) $ov_pp['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $ov_pp['url'] ); ?></a></td>
							<td><?php echo esc_html( (string) $ov_pp['understood_as'] ); ?></td>
							<td class="text-end"><?php echo esc_html( (string) (int) $ov_pp['clarity'] ); ?>/5</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
