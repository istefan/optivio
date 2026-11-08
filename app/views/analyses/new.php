<?php
/**
 * OptiVio — AI analysis confirmation (model, data sent, estimated cost, budget).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_p = (array) $view['preflight'];
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/analyses' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Analize AI</a>
</div>
<div class="row g-3">
	<div class="col-12 col-xl-7">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/analyses' ) ); ?>">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header"><div><h2 class="ov-card-title">Confirmați apelul AI</h2><p class="ov-card-subtitle">Profil „Raționament / Strategie”</p></div></div>
			<?php if ( ! empty( $view['mock'] ) ) : ?>
				<div class="ov-alert ov-alert-warning"><i class="bi bi-cone-striped" aria-hidden="true"></i><div>Mod <strong>mock</strong> (development): răspunsul AI este simulat local.</div></div>
			<?php endif; ?>
			<dl class="ov-dl mb-3">
				<dt>Model</dt><dd><span class="ov-secret"><?php echo esc_html( $ov_p['model'] ); ?></span></dd>
				<dt>Cheie folosită</dt><dd><?php echo esc_html( 'business' === $ov_p['key_source'] ? 'cheia clientului' : ( 'global' === $ov_p['key_source'] ? 'cheia globală' : 'niciuna' ) ); ?></dd>
				<dt>Date trimise</dt><dd><?php echo esc_html( ov_number( $view['chars'] ) . ' caractere (snapshot determinist, fără date personale)' ); ?></dd>
				<dt>Cost estimat (maxim)</dt><dd><?php echo esc_html( null !== $ov_p['estimate'] ? '$' . number_format( (float) $ov_p['estimate'], 4 ) : 'necunoscut (preț indisponibil)' ); ?></dd>
				<dt>Buget lunar</dt><dd><?php echo esc_html( '$' . number_format( (float) $ov_p['spent'], 2 ) . ' cheltuit din $' . number_format( (float) $ov_p['budget'], 2 ) ); ?></dd>
			</dl>
			<?php if ( $ov_p['blocked'] ) : ?>
				<div class="ov-alert ov-alert-error mb-0"><i class="bi bi-x-octagon" aria-hidden="true"></i><div><?php echo esc_html( $ov_p['reason'] ); ?></div></div>
			<?php else : ?>
				<div class="form-check mb-3">
					<input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="1" required>
					<label class="form-check-label" for="confirm">Confirm rularea analizei și costul estimat.</label>
				</div>
				<button class="btn btn-dark" type="submit" data-loading="Analiza rulează… poate dura 1–2 minute."><i class="bi bi-stars me-1" aria-hidden="true"></i>Rulează analiza</button>
			<?php endif; ?>
		</form>
	</div>
	<div class="col-12 col-xl-5">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Secțiuni incluse în snapshot</h2></div>
			<div class="d-flex flex-wrap gap-1">
				<?php foreach ( $view['sections'] as $ov_s ) : ?>
					<span class="ov-badge ov-badge-outline"><?php echo esc_html( $ov_s ); ?></span>
				<?php endforeach; ?>
			</div>
			<p class="ov-cell-sub mt-3 mb-0">Snapshot-ul se salvează împreună cu analiza (audit și reproductibilitate). Regulile permanente ale afacerii și cardurile respinse sunt transmise ca restricții obligatorii.</p>
		</div>
	</div>
</div>
