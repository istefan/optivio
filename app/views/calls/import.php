<?php
/**
 * OptiVio — CSV import of calls.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

$ov_result = $view['result'];
?>
<div class="d-flex gap-2 mb-3">
	<a class="btn btn-light btn-sm" href="<?php echo esc_url( ov_url( '/calls' ) ); ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Apeluri</a>
</div>
<div class="row g-3">
	<div class="col-12 col-xl-6">
		<?php if ( empty( $view['manageable'] ) ) : ?>
			<div class="ov-card"><div class="ov-empty"><i class="bi bi-lock" aria-hidden="true"></i>Nu aveți drept de gestionare pe nicio afacere.</div></div>
		<?php else : ?>
			<form class="ov-card" method="post" action="<?php echo esc_url( ov_url( '/calls/import' ) ); ?>" enctype="multipart/form-data">
				<?php echo ov_csrf_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="mb-3">
					<label class="form-label" for="business_id">Afacere</label>
					<select class="form-select" id="business_id" name="business_id">
						<?php foreach ( $view['manageable'] as $ov_bid => $ov_bname ) : ?>
							<option value="<?php echo esc_attr( (string) $ov_bid ); ?>"><?php echo esc_html( $ov_bname ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-3">
					<label class="form-label" for="csv">Fișier CSV (max. 2 MB, 5.000 rânduri)</label>
					<input class="form-control" type="file" id="csv" name="csv" accept=".csv,text/csv" required>
				</div>
				<button class="btn btn-dark" type="submit"><i class="bi bi-upload me-1" aria-hidden="true"></i>Importă</button>
			</form>
		<?php endif; ?>

		<?php if ( is_array( $ov_result ) && ! empty( $ov_result['errors'] ) ) : ?>
			<div class="ov-card mt-3">
				<div class="ov-card-header"><h2 class="ov-card-title">Probleme la ultimul import</h2></div>
				<ul class="mb-0 ov-cell-sub">
					<?php foreach ( $ov_result['errors'] as $ov_err ) : ?>
						<li><?php echo esc_html( $ov_err ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<div class="col-12 col-xl-6">
		<div class="ov-card">
			<div class="ov-card-header"><h2 class="ov-card-title">Format acceptat</h2></div>
			<p class="ov-cell-sub">Prima linie = antet. Separator „,” „;” sau Tab (detectat automat). Orele se interpretează în fusul orar al afacerii.</p>
			<div class="table-responsive">
				<table class="table ov-table">
					<thead><tr><th scope="col">Coloană</th><th scope="col">Antete recunoscute</th></tr></thead>
					<tbody>
						<tr><td class="ov-cell-main">Data *</td><td class="ov-cell-sub">data, data_ora, datetime, date — ex. 08.11.2026 14:30</td></tr>
						<tr><td class="ov-cell-main">Rezultat *</td><td class="ov-cell-sub">rezultat, status — acceptat / refuzat capacitate / zonă / lucrare / preț / pierdut / spam</td></tr>
						<tr><td>Venit</td><td class="ov-cell-sub">venit, suma, valoare — 1.250,50 sau 1250.50</td></tr>
						<tr><td>Durată</td><td class="ov-cell-sub">durata, durata_sec (secunde)</td></tr>
						<tr><td>Apelant</td><td class="ov-cell-sub">telefon, apelant, numar (se stochează doar hash + ultimele 3 cifre)</td></tr>
						<tr><td>Cuvânt cheie</td><td class="ov-cell-sub">cuvant_cheie, keyword</td></tr>
						<tr><td>Canal</td><td class="ov-cell-sub">canal, sursa — ads / maps / organic / facebook / recomandare</td></tr>
						<tr><td>Notă</td><td class="ov-cell-sub">nota, observatii, motiv</td></tr>
					</tbody>
				</table>
			</div>
			<code class="ov-code">data;rezultat;venit;durata;telefon;nota
08.11.2026 09:15;acceptat;850;240;0722123456;
08.11.2026 21:40;refuzat capacitate;;60;0733111222;toate echipele ocupate</code>
			<p class="ov-cell-sub mt-2 mb-0">După import, apelurile fără sursă se corelează automat cu click-urile tel: din fereastra −30 min / +2 min.</p>
		</div>
	</div>
</div>
