<?php
/**
 * OptiVio — Business create/edit form.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_b      = $view['business'];
$ov_old    = (array) ( $view['old'] ?? array() );
$ov_errors = (array) ( $view['errors'] ?? array() );
$ov_action = null === $ov_b ? ov_url( '/businesses' ) : ov_url( '/businesses/' . (int) $ov_b['id'] );
$ov_back   = null === $ov_b ? ov_url( '/businesses' ) : ov_url( '/businesses/' . (int) $ov_b['id'] );

$ov_input = static function ( string $name, string $label, string $type = 'text', string $help = '', string $col = 'col-md-6', array $attrs = array() ) use ( $ov_old, $ov_b, $ov_errors ): void {
	?>
	<div class="<?php echo esc_attr( $col ); ?>">
		<label class="form-label" for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
		<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, $name ) ); ?>" type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( ov_value( $ov_old, $ov_b, $name ) ); ?>"
			<?php foreach ( $attrs as $ov_attr => $ov_val ) : ?>
				<?php echo esc_attr( $ov_attr ); ?>="<?php echo esc_attr( $ov_val ); ?>"
			<?php endforeach; ?>
		>
		<?php ov_field_error( $ov_errors, $name ); ?>
		<?php if ( '' !== $help && ! isset( $ov_errors[ $name ] ) ) : ?>
			<div class="form-text"><?php echo esc_html( $help ); ?></div>
		<?php endif; ?>
	</div>
	<?php
};
?>
<?php if ( null !== $ov_b ) : ?>
	<?php View::partial( 'partials/business-tabs', $view ); ?>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( $ov_action ); ?>" novalidate>
	<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="row g-3">
		<div class="col-12 col-xl-8 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-section-label">Identitate</div>
				<div class="row g-3">
					<?php $ov_input( 'name', 'Nume afacere *', 'text', '', 'col-md-6', array( 'maxlength' => '160', 'required' => 'required' ) ); ?>
					<div class="col-md-6">
						<label class="form-label" for="business_model">Model de afacere</label>
						<select class="form-select<?php echo esc_attr( ov_invalid( $ov_errors, 'business_model' ) ); ?>" id="business_model" name="business_model">
							<?php $ov_current = ov_value( $ov_old, $ov_b, 'business_model', 'other' ); ?>
							<?php foreach ( ov_business_models() as $ov_key => $ov_label ) : ?>
								<option value="<?php echo esc_attr( $ov_key ); ?>"<?php echo $ov_key === $ov_current ? ' selected' : ''; ?>><?php echo esc_html( $ov_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="form-text">Determină strategia recomandată (ex. urgențe → campanii pe Clicks, nu pe Conversii).</div>
					</div>
					<?php $ov_input( 'service_area', 'Zonă deservită', 'text', 'Ex. București, Ilfov (rază 30 km)', 'col-md-12', array( 'maxlength' => '255' ) ); ?>
					<div class="col-12">
						<label class="form-label" for="description">Descrierea afacerii</label>
						<textarea class="form-control" id="description" name="description" rows="5" maxlength="5000"><?php echo esc_textarea( ov_value( $ov_old, $ov_b, 'description' ) ); ?></textarea>
						<div class="form-text">Servicii, public țintă, diferențiatori. Stă la baza tuturor analizelor.</div>
					</div>
					<div class="col-12">
						<label class="form-label" for="target_audience">Public țintă</label>
						<textarea class="form-control" id="target_audience" name="target_audience" rows="3" maxlength="3000"><?php echo esc_textarea( ov_value( $ov_old, $ov_b, 'target_audience' ) ); ?></textarea>
						<div class="form-text">Cine sună / cumpără: proprietari de apartamente, administratori de bloc, firme etc.</div>
					</div>
					<?php if ( null === $ov_b ) : ?>
						<div class="col-12">
							<label class="form-label" for="usp_text">USP (Unique Selling Proposition)</label>
							<textarea class="form-control" id="usp_text" name="usp_text" rows="3" maxlength="2000"><?php echo esc_textarea( ov_value( $ov_old, $ov_b, 'usp_text' ) ); ?></textarea>
							<div class="form-text">Opțional. După salvare, tab-ul USP propune automat un USP calculat din profil și servicii.</div>
						</div>
					<?php endif; ?>
					<?php $ov_input( 'avg_job_value', 'Valoare medie lucrare / comandă (RON)', 'text', '', 'col-md-6', array( 'inputmode' => 'decimal' ) ); ?>
					<?php $ov_input( 'daily_capacity', 'Capacitate zilnică (lucrări)', 'text', 'Folosită pentru corelarea apelurilor refuzate.', 'col-md-6', array( 'inputmode' => 'numeric' ) ); ?>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-4 d-flex flex-column gap-3">
			<div class="ov-card">
				<div class="ov-section-label">Date administrative</div>
				<div class="row g-3">
					<?php $ov_input( 'legal_name', 'Denumire legală', 'text', '', 'col-12', array( 'maxlength' => '200' ) ); ?>
					<?php $ov_input( 'cui', 'CUI', 'text', '', 'col-6', array( 'maxlength' => '20' ) ); ?>
					<?php $ov_input( 'reg_com', 'Nr. Reg. Com.', 'text', '', 'col-6', array( 'maxlength' => '40' ) ); ?>
					<div class="col-12">
						<div class="form-check">
							<input type="hidden" name="vat_payer" value="0">
							<input class="form-check-input" type="checkbox" id="vat_payer" name="vat_payer" value="1"<?php echo '1' === ov_value( $ov_old, $ov_b, 'vat_payer', '0' ) ? ' checked' : ''; ?>>
							<label class="form-check-label" for="vat_payer">Plătitor de TVA</label>
						</div>
					</div>
					<?php $ov_input( 'billing_address', 'Adresă', 'text', '', 'col-12', array( 'maxlength' => '255' ) ); ?>
					<?php $ov_input( 'billing_city', 'Localitate', 'text', '', 'col-6', array( 'maxlength' => '100' ) ); ?>
					<?php $ov_input( 'billing_county', 'Județ', 'text', '', 'col-6', array( 'maxlength' => '100' ) ); ?>
					<?php $ov_input( 'billing_email', 'Email facturare', 'email', '', 'col-12', array( 'maxlength' => '190' ) ); ?>
					<?php $ov_input( 'phone', 'Telefon', 'tel', '', 'col-12', array( 'maxlength' => '40' ) ); ?>
				</div>
			</div>

			<?php if ( ! empty( $view['can_status'] ) ) : ?>
				<div class="ov-card">
					<label class="form-label" for="status">Stare</label>
					<select class="form-select" id="status" name="status">
						<?php $ov_status = ov_value( $ov_old, $ov_b, 'status', 'active' ); ?>
						<?php
						foreach ( array(
							'active'   => 'Activ',
							'paused'   => 'În pauză',
							'archived' => 'Arhivat',
						) as $ov_key => $ov_label ) :
							?>
							<option value="<?php echo esc_attr( $ov_key ); ?>"<?php echo $ov_key === $ov_status ? ' selected' : ''; ?>><?php echo esc_html( $ov_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="d-flex gap-2">
				<a class="btn btn-light flex-grow-1" href="<?php echo esc_url( $ov_back ); ?>">Renunță</a>
				<button type="submit" class="btn btn-dark flex-grow-1">Salvează</button>
			</div>
		</div>
	</div>
</form>
