<?php
/**
 * OptiVio — Business overview.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_b     = (array) $view['business'];
$ov_ready = (array) $view['readiness'];
$ov_dash  = static function ( $value ): string {
	return ( null === $value || '' === $value ) ? '—' : (string) $value;
};
$ov_conn  = array(
	'not_installed' => array( '', 'Neinstalat' ),
	'pending'       => array( 'ov-badge-warning', 'În așteptare' ),
	'connected'     => array( 'ov-badge-success', 'Conectat' ),
	'error'         => array( 'ov-badge-danger', 'Eroare' ),
	'revoked'       => array( 'ov-badge-danger', 'Revocat' ),
);
?>
<?php View::partial( 'partials/business-tabs', $view ); ?>

<div class="row g-3">
	<div class="col-12 col-xl-8 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Model de afacere & USP</h2></div>
			<dl class="ov-dl">
				<dt>Model</dt><dd><?php echo esc_html( ov_business_model_label( (string) $ov_b['business_model'] ) ); ?></dd>
				<dt>Zonă deservită</dt><dd><?php echo esc_html( $ov_dash( $ov_b['service_area'] ) ); ?></dd>
				<dt>Valoare medie lucrare</dt><dd><?php echo esc_html( null !== $ov_b['avg_job_value'] ? ov_number( $ov_b['avg_job_value'], 2 ) . ' ' . $ov_b['currency'] : '—' ); ?></dd>
				<dt>Capacitate zilnică</dt><dd><?php echo esc_html( null !== $ov_b['daily_capacity'] ? $ov_b['daily_capacity'] . ' lucrări / zi' : '—' ); ?></dd>
				<dt>Descriere</dt><dd><?php echo nl2br( esc_html( $ov_dash( $ov_b['description'] ) ) ); ?></dd>
				<dt>USP</dt>
				<dd>
					<?php echo nl2br( esc_html( $ov_dash( $ov_b['usp_text'] ) ) ); ?>
					<?php if ( ! empty( $ov_b['usp_source'] ) ) : ?>
						<span class="ov-badge ms-1"><?php echo esc_html( 'manual' === $ov_b['usp_source'] ? 'manual' : ( 'computed' === $ov_b['usp_source'] ? 'calculat' : 'AI' ) ); ?></span>
					<?php endif; ?>
				</dd>
			</dl>
		</div>

		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Date administrative</h2></div>
			<dl class="ov-dl">
				<dt>Denumire legală</dt><dd><?php echo esc_html( $ov_dash( $ov_b['legal_name'] ) ); ?></dd>
				<dt>CUI</dt><dd><?php echo esc_html( $ov_dash( $ov_b['cui'] ) ); ?><?php echo ! empty( $ov_b['vat_payer'] ) ? ' <span class="ov-badge">plătitor TVA</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup. ?></dd>
				<dt>Reg. Com.</dt><dd><?php echo esc_html( $ov_dash( $ov_b['reg_com'] ) ); ?></dd>
				<dt>Adresă facturare</dt><dd><?php echo esc_html( trim( implode( ', ', array_filter( array( $ov_b['billing_address'], $ov_b['billing_city'], $ov_b['billing_county'] ) ) ) ) ?: '—' ); ?></dd>
				<dt>Email facturare</dt><dd><?php echo esc_html( $ov_dash( $ov_b['billing_email'] ) ); ?></dd>
				<dt>Telefon</dt><dd><?php echo esc_html( $ov_dash( $ov_b['phone'] ) ); ?></dd>
				<dt>RepuVio</dt><dd><span class="ov-badge ov-badge-outline">integrare rezervată · <?php echo esc_html( str_replace( '_', ' ', (string) $ov_b['repuvio_status'] ) ); ?></span></dd>
			</dl>
		</div>

		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Site-uri web</h2>
					<p class="ov-card-subtitle">Conector WordPress și integrări Google per site</p>
				</div>
				<a class="ov-link-more" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_b['id'] . '/sites' ) ); ?>">Gestionează</a>
			</div>
			<?php if ( empty( $view['sites'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-globe2" aria-hidden="true"></i>Niciun site adăugat</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['sites'] as $ov_site ) : ?>
						<?php $ov_cb = $ov_conn[ $ov_site['connector_status'] ] ?? array( '', $ov_site['connector_status'] ); ?>
						<li>
							<span class="ov-avatar ov-avatar-sm ov-avatar-icon"><i class="bi bi-globe2" aria-hidden="true"></i></span>
							<div class="ov-list-body">
								<a class="ov-list-title d-block" href="<?php echo esc_url( ov_url( '/sites/' . (int) $ov_site['id'] ) ); ?>"><?php echo esc_html( $ov_site['name'] ); ?></a>
								<div class="ov-list-sub"><?php echo esc_html( $ov_site['url'] ); ?></div>
							</div>
							<span class="ov-badge <?php echo esc_attr( $ov_cb[0] ); ?>"><?php echo esc_html( $ov_cb[1] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="col-12 col-xl-4 d-flex flex-column gap-3">
		<div class="ov-card">
			<div class="ov-card-header">
				<div>
					<h2 class="ov-card-title">Scor de pregătire</h2>
					<p class="ov-card-subtitle">Calcul determinist, fără AI</p>
				</div>
				<span class="ov-badge ov-badge-dark"><?php echo esc_html( (string) (int) $ov_ready['score'] ); ?>%</span>
			</div>
			<div class="ov-progress mb-3"><span style="width: <?php echo esc_attr( (string) (int) $ov_ready['score'] ); ?>%"></span></div>
			<ul class="ov-list ov-checklist">
				<?php foreach ( $ov_ready['items'] as $ov_item ) : ?>
					<li>
						<span class="ov-check <?php echo $ov_item['done'] ? 'ov-check-done' : 'ov-check-todo'; ?>"><i class="bi bi-check" aria-hidden="true"></i></span>
						<span class="ov-list-body ov-list-sub<?php echo $ov_item['done'] ? '' : ' text-body'; ?>"><?php echo esc_html( $ov_item['label'] ); ?></span>
						<span class="ov-list-meta"><?php echo esc_html( (string) (int) $ov_item['weight'] ); ?>p</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Operatori cu acces</h2></div>
			<?php if ( empty( $view['operators'] ) ) : ?>
				<div class="ov-empty py-3"><i class="bi bi-people" aria-hidden="true"></i>Doar Super Admin</div>
			<?php else : ?>
				<ul class="ov-list">
					<?php foreach ( $view['operators'] as $ov_op ) : ?>
						<li>
							<span class="ov-avatar ov-avatar-sm"><?php echo esc_html( ov_initials( (string) $ov_op['display_name'] ) ); ?></span>
							<div class="ov-list-body">
								<div class="ov-list-title"><?php echo esc_html( $ov_op['display_name'] ); ?></div>
								<div class="ov-list-sub"><?php echo esc_html( $ov_op['email'] ); ?></div>
							</div>
							<span class="ov-badge"><?php echo esc_html( 'manage' === $ov_op['permission'] ? 'gestionare' : 'vizualizare' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<?php
		$ov_onb = (array) ( json_decode( (string) ( $ov_b['onboarding_state'] ?? '' ), true ) ?: array() );
		?>
		<?php if ( ! empty( $view['is_super_admin'] ) && ! empty( $ov_onb['draft_id'] ) && empty( $ov_onb['completed_at'] ) ) : ?>
			<div class="ov-alert ov-alert-warning mb-0">
				<i class="bi bi-magic" aria-hidden="true"></i>
				<div>Onboarding-ul nu este finalizat. <a href="<?php echo esc_url( ov_url( '/onboarding/' . (int) $ov_onb['draft_id'] ) ); ?>">Continuă wizard-ul</a></div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $view['is_super_admin'] ) ) : ?>
			<div class="ov-card ov-danger-zone">
				<div class="ov-card-header"><h2 class="ov-card-title">Zonă periculoasă</h2></div>
				<p class="ov-cell-sub">Ștergerea elimină definitiv afacerea și toate datele ei. Pentru o pauză, folosiți starea „Arhivată” din Profil.</p>
				<a class="btn btn-outline-danger btn-sm" href="<?php echo esc_url( ov_url( '/businesses/' . (int) $ov_b['id'] . '/delete' ) ); ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i>Șterge definitiv</a>
			</div>
		<?php endif; ?>
	</div>
</div>
