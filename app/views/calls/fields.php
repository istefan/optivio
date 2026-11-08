<?php
/**
 * OptiVio — Call form fields (shared by create and edit).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view call, old, errors, businesses (create only).
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Repositories\Calls_Repository;
use OptiVio\Services\Attribution;

$ov_call   = $view['call'];
$ov_old    = (array) $view['old'];
$ov_errors = (array) $view['errors'];
$ov_time   = isset( $ov_old['call_time'] ) && null !== $ov_old['call_time'] ? ov_datetime( (string) $ov_old['call_time'], 'Y-m-d\TH:i' ) : ( null !== $ov_call ? ov_datetime( (string) $ov_call['call_time'], 'Y-m-d\TH:i' ) : ( new DateTimeImmutable( 'now', ov_timezone() ) )->format( 'Y-m-d\TH:i' ) );
$ov_out    = ov_value( $ov_old, $ov_call, 'outcome', 'accepted' );
$ov_chan   = ov_value( $ov_old, $ov_call, 'channel', 'other' );
?>
<div class="row g-2">
	<?php if ( null === $ov_call ) : ?>
		<div class="col-md-4">
			<label class="form-label" for="business_id">Afacere</label>
			<select class="form-select" id="business_id" name="business_id">
				<?php foreach ( (array) $view['businesses'] as $ov_bid => $ov_bname ) : ?>
					<option value="<?php echo esc_attr( (string) $ov_bid ); ?>"<?php echo (string) $ov_bid === (string) ( $ov_old['business_id'] ?? '' ) ? ' selected' : ''; ?>><?php echo esc_html( $ov_bname ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>
	<div class="col-md-4">
		<label class="form-label" for="call_time">Data și ora</label>
		<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'call_time' ) ); ?>" type="datetime-local" id="call_time" name="call_time" value="<?php echo esc_attr( $ov_time ); ?>" required>
		<?php ov_field_error( $ov_errors, 'call_time' ); ?>
	</div>
	<div class="col-md-4">
		<label class="form-label" for="outcome">Rezultat</label>
		<select class="form-select<?php echo esc_attr( ov_invalid( $ov_errors, 'outcome' ) ); ?>" id="outcome" name="outcome">
			<?php foreach ( Calls_Repository::OUTCOMES as $ov_key => $ov_label ) : ?>
				<option value="<?php echo esc_attr( $ov_key ); ?>"<?php echo $ov_key === $ov_out ? ' selected' : ''; ?>><?php echo esc_html( $ov_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-6 col-md-3">
		<label class="form-label" for="revenue_amount">Venit încasat</label>
		<input class="form-control" id="revenue_amount" name="revenue_amount" inputmode="decimal" value="<?php echo esc_attr( ov_value( $ov_old, $ov_call, 'revenue_amount' ) ); ?>" placeholder="doar pt. acceptate">
	</div>
	<div class="col-6 col-md-3">
		<label class="form-label" for="duration_sec">Durată (sec)</label>
		<input class="form-control" id="duration_sec" name="duration_sec" inputmode="numeric" value="<?php echo esc_attr( ov_value( $ov_old, $ov_call, 'duration_sec' ) ); ?>">
	</div>
	<div class="col-md-3">
		<label class="form-label" for="channel">Canal</label>
		<select class="form-select" id="channel" name="channel">
			<?php foreach ( Attribution::CHANNELS as $ov_key => $ov_label ) : ?>
				<option value="<?php echo esc_attr( $ov_key ); ?>"<?php echo $ov_key === $ov_chan ? ' selected' : ''; ?>><?php echo esc_html( $ov_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-md-3">
		<label class="form-label" for="keyword_text">Cuvânt cheie</label>
		<input class="form-control" id="keyword_text" name="keyword_text" maxlength="190" value="<?php echo esc_attr( ov_value( $ov_old, $ov_call, 'keyword_text' ) ); ?>" placeholder="auto din click tel:">
	</div>
	<?php if ( null === $ov_call ) : ?>
		<div class="col-md-4">
			<label class="form-label" for="caller">Număr apelant (opțional)</label>
			<input class="form-control" type="tel" id="caller" name="caller" maxlength="40" autocomplete="off">
			<div class="form-text">Se păstrează doar ultimele 3 cifre + hash.</div>
		</div>
	<?php endif; ?>
	<div class="<?php echo null === $ov_call ? 'col-md-8' : 'col-12'; ?>">
		<label class="form-label" for="outcome_note">Notă / motiv</label>
		<input class="form-control" id="outcome_note" name="outcome_note" maxlength="500" value="<?php echo esc_attr( ov_value( $ov_old, $ov_call, 'outcome_note' ) ); ?>" placeholder="ex. Sector 6, nu acoperim; lucrare de renovare completă">
	</div>
</div>
