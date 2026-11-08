<?php
/**
 * OptiVio — Form entry detail (decrypted fields, attribution, spam analysis, manual override).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Services\Attribution;
use OptiVio\Services\Spam_Scorer;

$ov_e       = (array) $view['entry'];
$ov_reasons = (array) ( json_decode( (string) $ov_e['spam_reasons'], true ) ?: array() );
$ov_flags   = (array) ( json_decode( (string) $ov_e['plugin_spam_flags'], true ) ?: array() );
$ov_state   = array(
	'clean'   => array( 'ov-badge-success', 'Curat' ),
	'suspect' => array( 'ov-badge-warning', 'Suspect' ),
	'spam'    => array( 'ov-badge-danger', 'Spam' ),
);
$ov_over    = array(
	'none'         => '',
	'marked_clean' => 'marcat manual ca valid',
	'marked_spam'  => 'marcat manual ca spam',
);
$ov_s       = $ov_state[ $ov_e['spam_status'] ] ?? array( '', $ov_e['spam_status'] );
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/conversions', array( 'kind' => 'forms' ) ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Conversii</a>
</div>
<div class="row g-3">
	<div class="col-12 col-xl-7 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Date trimise</h2>
					<p class="ov-card-subtitle">Decriptate la afișare (stocate AES-256-GCM) · <?php echo esc_html( ov_datetime( $ov_e['occurred_at'], 'd.m.Y H:i:s' ) ); ?></p>
				</div>
			</div>
			<?php if ( empty( $view['fields'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-lock" aria-hidden="true"></i>Câmpurile nu pot fi decriptate.</div>
			<?php else : ?>
				<dl class="ov-dl">
					<?php foreach ( $view['fields'] as $ov_label => $ov_value ) : ?>
						<dt><?php echo esc_html( (string) $ov_label ); ?></dt>
						<dd><?php echo nl2br( esc_html( (string) $ov_value ) ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</div>
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Atribuire</h2></div>
			<dl class="ov-dl">
				<dt>Canal</dt><dd><?php echo esc_html( Attribution::CHANNELS[ $ov_e['channel'] ] ?? $ov_e['channel'] ); ?></dd>
				<dt>Campanie / termen</dt><dd><?php echo esc_html( trim( ( $ov_e['utm_campaign'] ?? '' ) . ' / ' . ( $ov_e['utm_term'] ?? '' ), ' /' ) ?: '—' ); ?></dd>
				<dt>Sursă / mediu</dt><dd><?php echo esc_html( trim( ( $ov_e['utm_source'] ?? '' ) . ' / ' . ( $ov_e['utm_medium'] ?? '' ), ' /' ) ?: '—' ); ?></dd>
				<dt>GCLID</dt><dd class="ov-cell-sub"><?php echo esc_html( $ov_e['gclid'] ?? '—' ); ?></dd>
				<dt>Pagina formularului</dt><dd class="ov-cell-sub"><?php echo esc_html( $ov_e['page_url'] ?? '—' ); ?></dd>
				<dt>Landing page</dt><dd class="ov-cell-sub"><?php echo esc_html( $ov_e['landing_page'] ?? '—' ); ?></dd>
				<dt>Referrer</dt><dd class="ov-cell-sub"><?php echo esc_html( $ov_e['referrer'] ?? '—' ); ?></dd>
				<dt>Dispozitiv</dt><dd><?php echo esc_html( $ov_e['device_type'] ); ?></dd>
			</dl>
		</div>
	</div>
	<div class="col-12 col-xl-5">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Analiză anti-spam</h2>
					<p class="ov-card-subtitle">Determinist · <?php echo esc_html( $ov_over[ $ov_e['spam_override'] ] ?? '' ); ?></p>
				</div>
				<span class="ov-badge <?php echo esc_attr( $ov_s[0] ); ?>"><?php echo esc_html( $ov_s[1] . ' · ' . (int) $ov_e['spam_score'] ); ?></span>
			</div>
			<div class="ov-progress mb-3"><span style="width: <?php echo esc_attr( (string) (int) $ov_e['spam_score'] ); ?>%"></span></div>
			<?php if ( empty( $ov_reasons ) ) : ?>
				<p class="ov-cell-sub">Niciun semnal de spam.</p>
			<?php else : ?>
				<ul class="ov-list ov-checklist mb-3">
					<?php foreach ( $ov_reasons as $ov_r ) : ?>
						<li><span class="ov-check ov-check-done"><i class="bi bi-exclamation" aria-hidden="true"></i></span><span class="ov-list-sub text-body"><?php echo esc_html( Spam_Scorer::REASONS[ $ov_r ] ?? $ov_r ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="ov-cell-sub">Plugin: honeypot <?php echo esc_html( ! empty( $ov_flags['honeypot'] ) ? 'completat' : 'gol' ); ?> · timp completare <?php echo esc_html( isset( $ov_flags['fill_seconds'] ) && null !== $ov_flags['fill_seconds'] ? ov_number( $ov_flags['fill_seconds'], 1 ) . ' s' : 'n/a' ); ?></p>
			<?php if ( ! empty( $view['can_manage'] ) ) : ?>
				<div class="d-flex flex-wrap gap-2">
					<?php
					foreach ( array(
						'clean' => array( 'btn-light', 'Marchează valid' ),
						'spam'  => array( 'btn-outline-danger', 'Marchează spam' ),
						'reset' => array( 'btn-light', 'Revino la scorul automat' ),
					) as $ov_action => $ov_btn ) :
						?>
						<form method="post" action="<?php echo esc_url( ov_url( '/forms/' . (int) $ov_e['id'] . '/spam' ) ); ?>">
							<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<input type="hidden" name="action" value="<?php echo esc_attr( $ov_action ); ?>">
							<button class="btn btn-sm <?php echo esc_attr( $ov_btn[0] ); ?>" type="submit"><?php echo esc_html( $ov_btn[1] ); ?></button>
						</form>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
