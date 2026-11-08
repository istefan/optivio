<?php
/**
 * OptiVio — Business AI & connectors tab.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_b       = (array) $view['business'];
$ov_bid     = (int) $ov_b['id'];
$ov_errors  = (array) $view['errors'];
$ov_old     = (array) $view['old'];
$ov_over    = (array) $view['overrides'];
$ov_eff     = (array) $view['effective'];
$ov_key     = $view['client_key'];
$ov_manage  = ! empty( $view['can_manage'] );
$ov_sources = array(
	'business' => 'cheia clientului',
	'global'   => 'cheia globală',
	'none'     => 'nicio cheie configurată',
);
$ov_repuvio = array(
	'not_connected' => array( '', 'neconectat' ),
	'pending'       => array( 'ov-badge-warning', 'în așteptare' ),
	'connected'     => array( 'ov-badge-success', 'conectat' ),
	'error'         => array( 'ov-badge-danger', 'eroare' ),
);
$ov_rs      = $ov_repuvio[ $ov_b['repuvio_status'] ] ?? array( '', (string) $ov_b['repuvio_status'] );
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/ai' ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Profiluri de modele pentru acest client</h2>
					<p class="ov-card-subtitle">Lăsați câmpurile goale pentru a moșteni setările globale. Valoarea efectivă este afișată sub fiecare profil.</p>
				</div>
			</div>
			<?php foreach ( $view['ai_profiles'] as $ov_pk => $ov_profile ) : ?>
				<?php
				$ov_o = $ov_over[ $ov_pk ] ?? array();
				$ov_e = $ov_eff[ $ov_pk ];
				?>
				<div class="border rounded-3 p-3 mb-3">
					<div class="fw-semibold"><?php echo esc_html( $ov_profile[0] ); ?></div>
					<div class="row g-2 mt-1">
						<div class="col-md-6">
							<label class="form-label" for="model_<?php echo esc_attr( $ov_pk ); ?>">Model (override)</label>
							<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'model_' . $ov_pk ) ); ?>" id="model_<?php echo esc_attr( $ov_pk ); ?>" name="model_<?php echo esc_attr( $ov_pk ); ?>" maxlength="120" spellcheck="false" placeholder="<?php echo esc_attr( $ov_e['model'] ); ?>" value="<?php echo esc_attr( (string) ( $ov_o['model'] ?? '' ) ); ?>"<?php echo $ov_manage ? '' : ' disabled'; ?>>
							<?php ov_field_error( $ov_errors, 'model_' . $ov_pk ); ?>
						</div>
						<div class="col-6 col-md-3">
							<label class="form-label" for="temperature_<?php echo esc_attr( $ov_pk ); ?>">Temperatură</label>
							<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'temperature_' . $ov_pk ) ); ?>" id="temperature_<?php echo esc_attr( $ov_pk ); ?>" name="temperature_<?php echo esc_attr( $ov_pk ); ?>" inputmode="decimal" placeholder="<?php echo esc_attr( (string) $ov_e['temperature'] ); ?>" value="<?php echo esc_attr( null !== ( $ov_o['temperature'] ?? null ) ? (string) (float) $ov_o['temperature'] : '' ); ?>"<?php echo $ov_manage ? '' : ' disabled'; ?>>
							<?php ov_field_error( $ov_errors, 'temperature_' . $ov_pk ); ?>
						</div>
						<div class="col-6 col-md-3">
							<label class="form-label" for="max_tokens_<?php echo esc_attr( $ov_pk ); ?>">Max tokens</label>
							<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'max_tokens_' . $ov_pk ) ); ?>" id="max_tokens_<?php echo esc_attr( $ov_pk ); ?>" name="max_tokens_<?php echo esc_attr( $ov_pk ); ?>" inputmode="numeric" placeholder="<?php echo esc_attr( (string) $ov_e['max_tokens'] ); ?>" value="<?php echo esc_attr( (string) ( $ov_o['max_tokens'] ?? '' ) ); ?>"<?php echo $ov_manage ? '' : ' disabled'; ?>>
							<?php ov_field_error( $ov_errors, 'max_tokens_' . $ov_pk ); ?>
						</div>
					</div>
					<div class="ov-cell-sub mt-2">
						Efectiv: <span class="ov-secret"><?php echo esc_html( $ov_e['model'] ); ?></span>
						· <?php echo esc_html( 'business' === $ov_e['model_source'] ? 'override client' : 'global' ); ?>
						· temp <?php echo esc_html( (string) $ov_e['temperature'] ); ?> · <?php echo esc_html( ov_number( $ov_e['max_tokens'] ) ); ?> tokens
					</div>
				</div>
			<?php endforeach; ?>
			<?php if ( $ov_manage ) : ?>
				<button class="btn btn-dark" type="submit">Salvează profilurile</button>
			<?php endif; ?>
		</form>
	</div>

	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Cheie OpenRouter a clientului</h2>
					<?php ov_guide_button( 'openrouter' ); ?>
					<p class="ov-card-subtitle">Se folosește acum: <strong><?php echo esc_html( $ov_sources[ $ov_eff['fast']['key_source'] ] ?? '' ); ?></strong></p>
				</div>
			</div>
			<?php if ( null !== $ov_key ) : ?>
				<p class="mb-2"><span class="ov-badge ov-badge-success"><i class="bi bi-lock" aria-hidden="true"></i>configurată</span> <span class="ov-secret"><?php echo esc_html( ov_mask_secret( $ov_key['secret_hint'] ) ?: '••••••••' ); ?></span></p>
			<?php endif; ?>
			<?php if ( $ov_manage ) : ?>
				<form method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/ai/key' ) ); ?>" autocomplete="off" class="d-flex gap-2">
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<input class="form-control" type="password" name="secret" maxlength="512" autocomplete="new-password" required placeholder="<?php echo esc_attr( null !== $ov_key ? 'Înlocuiește cheia…' : 'sk-or-…' ); ?>" aria-label="Cheie OpenRouter client">
					<button class="btn btn-dark" type="submit">Salvează</button>
				</form>
				<?php if ( null !== $ov_key ) : ?>
					<form method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/ai/key' ) ); ?>" class="mt-2" data-confirm="Ștergeți cheia clientului? Se va folosi cheia globală.">
						<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<input type="hidden" name="action" value="delete">
						<button class="btn btn-sm btn-outline-danger" type="submit">Șterge cheia clientului</button>
					</form>
				<?php endif; ?>
			<?php endif; ?>
			<p class="ov-cell-sub mt-3 mb-0">Costurile apelurilor AI făcute pentru acest client se vor factura pe contul său OpenRouter.</p>
		</div>

		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">RepuVio</h2>
					<p class="ov-card-subtitle">Management reputație (repuvio.ro) — integrare rezervată</p>
				</div>
				<span class="ov-badge <?php echo esc_attr( $ov_rs[0] ); ?>"><?php echo esc_html( $ov_rs[1] ); ?></span>
			</div>
			<?php if ( $ov_manage ) : ?>
				<form method="post" action="<?php echo esc_url( ov_url( '/businesses/' . $ov_bid . '/repuvio' ) ); ?>" novalidate>
					<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<label class="form-label" for="repuvio_account_id">ID cont RepuVio</label>
					<div class="input-group">
						<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'repuvio_account_id' ) ); ?>" id="repuvio_account_id" name="repuvio_account_id" maxlength="64" value="<?php echo esc_attr( $ov_old['repuvio_account_id'] ?? (string) $ov_b['repuvio_account_id'] ); ?>">
						<button class="btn btn-dark" type="submit">Salvează</button>
						<?php ov_field_error( $ov_errors, 'repuvio_account_id' ); ?>
					</div>
					<div class="form-text">Datele de reputație vor fi incluse în analizele multi-sursă după activarea integrării.</div>
				</form>
			<?php else : ?>
				<p class="mb-0"><?php echo esc_html( (string) ( $ov_b['repuvio_account_id'] ?? '—' ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
