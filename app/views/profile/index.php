<?php
/**
 * OptiVio — Own profile & password.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_user   = (array) $view['current_user'];
$ov_errors = (array) ( $view['errors'] ?? array() );
$ov_old    = (array) ( $view['old'] ?? array() );
?>
<div class="row g-3">
	<div class="col-12 col-lg-6 d-flex flex-column gap-3">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/profile' ) ); ?>" novalidate>
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header"><h2 class="ov-card-title">Date personale</h2></div>
			<div class="mb-3">
				<label class="form-label" for="display_name">Nume afișat</label>
				<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, 'display_name' ) ); ?>" id="display_name" name="display_name" maxlength="120" value="<?php echo esc_attr( ov_value( $ov_old, $ov_user, 'display_name' ) ); ?>">
				<?php ov_field_error( $ov_errors, 'display_name' ); ?>
			</div>
			<dl class="ov-dl mb-3">
				<dt>Email</dt><dd><?php echo esc_html( $ov_user['email'] ); ?></dd>
				<dt>Rol</dt><dd><?php echo esc_html( 'super_admin' === $ov_user['role'] ? 'Super Admin' : 'Operator' ); ?></dd>
				<dt>Ultima autentificare</dt><dd><?php echo esc_html( ov_datetime( $ov_user['last_login_at'] ) . ' · ' . ov_ip_to_string( $ov_user['last_login_ip'] ) ); ?></dd>
				<dt>Parolă schimbată</dt><dd><?php echo esc_html( ov_datetime( $ov_user['password_changed_at'] ) ); ?></dd>
			</dl>
			<button class="btn btn-dark" type="submit">Salvează</button>
		</form>

		<?php if ( 'operator' === $ov_user['role'] ) : ?>
			<div class="ov-card">
				<div class="ov-card-header"><h2 class="ov-card-title">Afacerile mele</h2></div>
				<?php if ( empty( $view['businesses'] ) ) : ?>
					<div class="ov-empty py-3"><i class="bi bi-buildings" aria-hidden="true"></i>Nicio afacere alocată.</div>
				<?php else : ?>
					<ul class="ov-list">
						<?php foreach ( $view['businesses'] as $ov_bid => $ov_bname ) : ?>
							<li>
								<span class="ov-avatar ov-avatar-sm"><?php echo esc_html( ov_initials( (string) $ov_bname ) ); ?></span>
								<div class="ov-list-body"><div class="ov-list-title"><?php echo esc_html( $ov_bname ); ?></div></div>
								<span class="ov-badge"><?php echo esc_html( 'manage' === ( $view['grants'][ $ov_bid ] ?? '' ) ? 'gestionare' : 'vizualizare' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="col-12 col-lg-6">
		<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/profile/password' ) ); ?>" novalidate autocomplete="off">
			<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Schimbare parolă</h2>
					<p class="ov-card-subtitle">Celelalte sesiuni active vor fi închise automat.</p>
				</div>
			</div>
			<?php
			foreach ( array(
				'current_password'     => array( 'Parola curentă', 'current-password' ),
				'new_password'         => array( 'Parola nouă', 'new-password' ),
				'new_password_confirm' => array( 'Confirmare parolă nouă', 'new-password' ),
			) as $ov_name => $ov_meta ) :
				?>
				<div class="mb-3">
					<label class="form-label" for="<?php echo esc_attr( $ov_name ); ?>"><?php echo esc_html( $ov_meta[0] ); ?></label>
					<input class="form-control<?php echo esc_attr( ov_invalid( $ov_errors, $ov_name ) ); ?>" type="password" id="<?php echo esc_attr( $ov_name ); ?>" name="<?php echo esc_attr( $ov_name ); ?>" autocomplete="<?php echo esc_attr( $ov_meta[1] ); ?>" maxlength="256" required>
					<?php ov_field_error( $ov_errors, $ov_name ); ?>
				</div>
			<?php endforeach; ?>
			<button class="btn btn-dark" type="submit">Schimbă parola</button>
		</form>
	</div>
</div>
