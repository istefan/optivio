<?php
/**
 * OptiVio — Audit log.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;
use OptiVio\Security\Audit_Log;

$ov_f = (array) $view['filters'];
?>
<form class="ov-card mb-3" method="get" action="<?php echo esc_url( ov_url( '/audit' ) ); ?>">
	<div class="row g-2 align-items-end">
		<div class="col-md-4">
			<label class="form-label" for="action">Acțiune</label>
			<select class="form-select" id="action" name="action">
				<option value="">Toate</option>
				<?php foreach ( Audit_Log::LABELS as $ov_key => $ov_label ) : ?>
					<option value="<?php echo esc_attr( $ov_key ); ?>"<?php echo $ov_key === $ov_f['action'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-3">
			<label class="form-label" for="user_id">Utilizator</label>
			<select class="form-select" id="user_id" name="user_id">
				<option value="0">Toți</option>
				<?php foreach ( $view['users'] as $ov_u ) : ?>
					<option value="<?php echo esc_attr( (string) $ov_u['id'] ); ?>"<?php echo (int) $ov_u['id'] === (int) $ov_f['user_id'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_u['display_name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-3">
			<label class="form-label" for="business_id">Afacere</label>
			<select class="form-select" id="business_id" name="business_id">
				<option value="0">Toate</option>
				<?php foreach ( $view['businesses'] as $ov_bid => $ov_bname ) : ?>
					<option value="<?php echo esc_attr( (string) $ov_bid ); ?>"<?php echo (int) $ov_bid === (int) $ov_f['business_id'] ? ' selected' : ''; ?>><?php echo esc_html( $ov_bname ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-2 d-grid">
			<button class="btn btn-dark" type="submit">Filtrează</button>
		</div>
	</div>
</form>

<div class="ov-card">
	<div class="ov-card-header">
		<h2 class="ov-card-title"><?php echo esc_html( ov_number( $view['total'] ) ); ?> înregistrări</h2>
	</div>
	<?php if ( empty( $view['rows'] ) ) : ?>
		<div class="ov-empty"><i class="bi bi-shield-check" aria-hidden="true"></i>Nicio înregistrare.</div>
	<?php else : ?>
		<div class="table-responsive">
			<table class="table ov-table">
				<thead>
					<tr>
						<th scope="col">Data</th>
						<th scope="col">Acțiune</th>
						<th scope="col">Utilizator</th>
						<th scope="col">Afacere</th>
						<th scope="col">Obiect</th>
						<th scope="col">IP</th>
						<th scope="col">Detalii</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $view['rows'] as $ov_row ) : ?>
						<tr>
							<td class="text-nowrap ov-cell-sub"><?php echo esc_html( ov_datetime( $ov_row['created_at'], 'd.m.Y H:i:s' ) ); ?></td>
							<td>
								<div class="ov-cell-main"><?php echo esc_html( Audit_Log::label( (string) $ov_row['action'] ) ); ?></div>
								<div class="ov-cell-sub"><?php echo esc_html( $ov_row['action'] ); ?></div>
							</td>
							<td><?php echo esc_html( $ov_row['display_name'] ?? '—' ); ?></td>
							<td><?php echo esc_html( $ov_row['business_name'] ?? '—' ); ?></td>
							<td class="ov-cell-sub"><?php echo esc_html( null !== $ov_row['entity_type'] ? $ov_row['entity_type'] . ( null !== $ov_row['entity_id'] ? ' #' . $ov_row['entity_id'] : '' ) : '—' ); ?></td>
							<td class="ov-cell-sub text-nowrap"><?php echo esc_html( ov_ip_to_string( $ov_row['ip'] ) ); ?></td>
							<td><code class="ov-cell-sub d-inline-block ov-truncate"><?php echo esc_html( (string) ( $ov_row['meta'] ?? '' ) ); ?></code></td>
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
				'base'  => '/audit',
				'query' => array_filter( $ov_f ),
			)
		);
		?>
	<?php endif; ?>
</div>
