<?php
/**
 * OptiVio — Conversions list.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_kinds    = array(
	'all'   => 'Toate',
	'phone' => 'Telefon',
	'forms' => 'Formulare',
	'spam'  => 'Spam filtrat',
);
$ov_channels = array(
	'google_ads' => 'Google Ads',
	'organic'    => 'Organic',
	'gbp'        => 'Google Business',
	'social'     => 'Social',
	'referral'   => 'Referral',
	'direct'     => 'Direct',
	'email'      => 'Email',
	'other'      => 'Altă sursă',
);
?>
<div class="ov-tabs">
	<?php foreach ( $ov_kinds as $ov_key => $ov_label ) : ?>
		<a class="<?php echo $ov_key === $view['kind'] ? 'active' : ''; ?>" href="<?php echo esc_url( ov_url( '/conversions', array( 'kind' => $ov_key ) ) ); ?>"><?php echo esc_html( $ov_label ); ?></a>
	<?php endforeach; ?>
</div>

<div class="ov-card">
	<div class="ov-card-header">
		<div>
			<h2 class="ov-card-title"><?php echo esc_html( ov_number( $view['total'] ) ); ?> conversii</h2>
			<p class="ov-card-subtitle"><?php echo esc_html( 'spam' === $view['kind'] ? 'Formulare clasificate ca spam — verificați periodic falsurile pozitive' : 'Formularele spam sunt excluse (vezi tab-ul Spam filtrat)' ); ?></p>
		</div>
	</div>
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-inbox" aria-hidden="true"></i><div class="ov-empty-title">Nicio conversie</div>Datele vor apărea după conectarea site-urilor.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead>
					<tr>
						<th scope="col">Tip</th>
						<th scope="col">Afacere / site</th>
						<th scope="col">Sursă</th>
						<th scope="col">Campanie / termen</th>
						<th scope="col">Pagina</th>
						<th scope="col">Dispozitiv</th>
						<th scope="col" class="text-end">Data</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<tr>
							<td>
								<?php if ( 'form' === $ov_row['kind'] ) : ?>
									<a class="ov-cell-main" href="<?php echo esc_url( ov_url( '/forms/' . (int) $ov_row['id'] ) ); ?>">Formular</a>
								<?php else : ?>
									<div class="ov-cell-main">Telefon</div>
								<?php endif; ?>
								<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['detail'] ?? '' ); ?></div>
								<?php if ( 'suspect' === ( $ov_row['spam_status'] ?? '' ) ) : ?>
									<span class="ov-badge ov-badge-warning mt-1">suspect</span>
								<?php elseif ( 'spam' === ( $ov_row['spam_status'] ?? '' ) ) : ?>
									<span class="ov-badge ov-badge-danger mt-1">spam</span>
								<?php endif; ?>
							</td>
							<td>
								<div><?php echo esc_html( $ov_row['business_name'] ); ?></div>
								<div class="ov-cell-sub"><?php echo esc_html( $ov_row['site_name'] ); ?></div>
							</td>
							<td><span class="ov-badge ov-badge-outline"><?php echo esc_html( $ov_channels[ $ov_row['channel'] ] ?? $ov_row['channel'] ); ?></span></td>
							<td>
								<div class="ov-truncate"><?php echo esc_html( $ov_row['utm_campaign'] ?? '—' ); ?></div>
								<div class="ov-cell-sub ov-truncate"><?php echo esc_html( $ov_row['utm_term'] ?? '' ); ?></div>
							</td>
							<td><div class="ov-truncate ov-cell-sub"><?php echo esc_html( $ov_row['page_url'] ?? '—' ); ?></div></td>
							<td class="ov-cell-sub"><?php echo esc_html( $ov_row['device_type'] ); ?></td>
							<td class="text-end text-nowrap ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_row['occurred_at'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		View::partial(
			'partials/pagination',
			array(
				'page'  => $view['page'],
				'pages' => $view['pages'],
				'base'  => '/conversions',
				'query' => array( 'kind' => $view['kind'] ),
			)
		);
		?>
	<?php endif; ?>
</div>
