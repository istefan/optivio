<?php
/**
 * OptiVio — Help page: first setup, adding a business step by step (including the WordPress
 * plugin), menu reference, daily workflow, integration guides and troubleshooting.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 *
 * @var array $view View data.
 */

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\View;

$ov_toc = array(
	'start'        => array( 'bi-signpost-split', 'Cum funcționează OptiVio' ),
	'setup'        => array( 'bi-sliders', 'Configurare inițială (o singură dată)' ),
	'business'     => array( 'bi-building-add', 'Adăugarea unei afaceri, pas cu pas' ),
	'plugin'       => array( 'bi-plug', 'Instalarea pluginului WordPress' ),
	'google'       => array( 'bi-google', 'Conectarea integrărilor Google' ),
	'access'       => array( 'bi-people', 'Acces pentru operatori' ),
	'daily'        => array( 'bi-calendar-check', 'Lucrul zilnic și săptămânal' ),
	'menu'         => array( 'bi-list-ul', 'Ce găsiți în fiecare meniu' ),
	'guides'       => array( 'bi-book', 'Ghiduri de integrare' ),
	'troubleshoot' => array( 'bi-wrench-adjustable', 'Probleme frecvente' ),
);

$ov_steps = static function ( array $steps ): void {
	echo '<ol class="ov-steps">';
	foreach ( $steps as $ov_step ) {
		echo '<li>' . ov_help_text( $ov_step ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ov_help_text().
	}
	echo '</ol>';
};
?>
<div class="row g-3">
	<div class="col-12 col-xl-3 d-none d-xl-block">
		<nav class="ov-card ov-help-toc" aria-label="Cuprins">
			<div class="ov-section-label">Cuprins</div>
			<?php foreach ( $ov_toc as $ov_id => $ov_t ) : ?>
				<a href="#<?php echo esc_attr( $ov_id ); ?>"><i class="bi <?php echo esc_attr( $ov_t[0] ); ?> me-2" aria-hidden="true"></i><?php echo esc_html( $ov_t[1] ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>

	<div class="col-12 col-xl-9 d-flex flex-column gap-3">

		<section class="ov-card ov-help-section" id="start">
			<h2><i class="bi bi-signpost-split me-2" aria-hidden="true"></i>Cum funcționează OptiVio</h2>
			<p>OptiVio adună într-un singur loc datele fiecărei afaceri — site-ul WordPress (conținut, SEO, click-uri pe telefon, formulare), Google (Search Console, Analytics, Ads, Business Profile etc.) și apelurile primite — le analizează <strong>determinist</strong> (reguli fixe, fără costuri) și transformă rezultatul în <strong>Action Cards</strong>: propuneri concrete pe care le aprobați, editați sau respingeți.</p>
			<p class="mb-2">Ordinea recomandată:</p>
			<?php
			$ov_steps(
				array(
					'**Configurare inițială** (Super Admin, o singură dată): setări, chei API, Google OAuth, cron.',
					'**Afacere nouă**: profil, contacte, servicii, USP, reguli.',
					'**Site + plugin**: adăugați site-ul, generați cheia, instalați OptiVio Connector în WordPress.',
					'**Integrări Google** pe site: Search Console, Analytics, Ads, Business Profile…',
					'**Date și analiză**: așteptați sincronizarea, importați apelurile, apăsați „Recalculează analiza”.',
					'**Decizii**: lucrați Action Cards; opțional rulați o Analiză AI pentru un plan complet.',
				)
			);
			?>
			<div class="ov-alert ov-alert-info mb-0"><i class="bi bi-cpu" aria-hidden="true"></i><div><strong>AI doar la cerere.</strong> Sincronizările, cron-ul și pluginul nu folosesc AI. Un apel AI pornește doar când un utilizator apasă butonul, după confirmarea costului estimat.</div></div>
		</section>

		<section class="ov-card ov-help-section" id="setup">
			<h2><i class="bi bi-sliders me-2" aria-hidden="true"></i>Configurare inițială (o singură dată)</h2>
			<p class="ov-cell-sub">Pașii din această secțiune se fac de un <strong>Super Admin</strong>, din meniul Administrare → Setări.</p>
			<?php
			$ov_steps(
				array(
					'**Setări → General**: numele aplicației, fusul orar și moneda implicită.',
					'**Setări → Securitate**: deconectarea după inactivitate, durata maximă a sesiunii, blocarea după încercări eșuate, lungimea minimă a parolei, păstrarea jurnalului de audit.',
					'**Setări → AI / OpenRouter**: cheia globală OpenRouter, modelele celor 3 profiluri (rapid, raționament, copywriting), bugetul lunar și „Cere confirmare înainte de fiecare apel AI”.',
					'**Setări → Integrări & chei API**: Client ID și Client Secret Google OAuth (copiați în Google Cloud Console URI-ul de redirecționare afișat), developer token Google Ads, cheile Serper și PageSpeed. Butonul „Cum configurez?” de lângă fiecare integrare deschide ghidul pas cu pas.',
					'**Integrări Google → Conectează cont Google**: autentificați-vă cu contul Google al agenției (cel care are acces la proprietățile clienților).',
					'**Setări → Anti-spam & conector**: pragurile de spam pentru formulare, timpul minim de completare, cuvintele blocate.',
					'**Setări → Cron & joburi**: configurați în cPanel un Cron Job la 5 minute (comanda de mai jos) — fără el nu rulează sincronizările, analiza zilnică și verificarea conectorilor.',
				)
			);
			?>
			<code class="ov-code d-block"><?php echo esc_html( $view['cron_command'] ); ?></code>
			<p class="ov-cell-sub mt-2 mb-0">Alternativ (gazde fără PHP CLI): un URL apelat la 5 minute, afișat în Setări → Cron & joburi, protejat cu token.</p>
		</section>

		<section class="ov-card ov-help-section" id="business">
			<h2><i class="bi bi-building-add me-2" aria-hidden="true"></i>Adăugarea unei afaceri, pas cu pas</h2>
			<p>Fiecare client este o <strong>afacere</strong>. Pagina afacerii are tab-uri; parcurgeți-le în ordinea de mai jos — cu cât profilul e mai complet, cu atât analiza (deterministă și AI) e mai bună. „Scorul de pregătire” de pe pagina afacerii arată ce lipsește.</p>
			<?php
			$ov_steps(
				array(
					'**Afaceri → Afacere nouă** (Super Admin): numele, zona deservită (ex. „București, Ilfov”), valoarea medie a unei lucrări, capacitatea zilnică, datele de facturare (CUI, Reg. Com., adresă) și telefonul.',
					'**Model de afacere**: alegeți corect (ex. servicii de urgență, servicii locale programate, magazin online, B2B, clinică) — de el depind strategia Google Ads (ex. urgențele merg pe „Maximizare click-uri”, nu pe conversii), ponderile cuvintelor cheie și pilonii USP.',
					'**Tab Profil**: descrierea afacerii și publicul țintă (cine sună, ce problemă are, de unde).',
					'**Tab Contacte**: persoanele de contact ale clientului (decident, marketing, tehnic).',
					'**Tab Servicii & produse**: fiecare serviciu cu preț orientativ, marjă și dacă e prioritar. Combinațiile „serviciu × zonă” devin automat cuvinte cheie candidate.',
					'**Tab USP**: OptiVio calculează un USP din profil (non-stop, timp de intervenție, preț fix, garanție, experiență…) și arată pilonii lipsă. Editați textul final.',
					'**Tab Reguli**: restricții permanente (ex. „Nu lucrăm în Ilfov după ora 20”, „Nu promitem prețuri fixe”). Sunt respectate de toate analizele; motivul fiecărui card respins se adaugă automat aici.',
					'**Tab Site-uri → Adaugă site**: URL-ul și numele site-ului (o afacere poate avea mai multe site-uri). Continuați cu instalarea pluginului (secțiunea următoare).',
					'**Tab AI & conectori** (opțional): cheie OpenRouter proprie clientului (costurile AI se facturează pe contul lui) și modele diferite față de cele globale.',
				)
			);
			?>
		</section>

		<section class="ov-card ov-help-section" id="plugin">
			<h2><i class="bi bi-plug me-2" aria-hidden="true"></i>Instalarea pluginului WordPress (OptiVio Connector)</h2>
			<p>Pluginul trimite către OptiVio click-urile pe numerele de telefon, formularele Gravity Forms (cu filtrare anti-spam), sursa vizitatorului (Google Ads, organic, Maps…) și conținutul site-ului cu datele Yoast SEO. Tot prin el se aplică modificările aprobate, cu rollback.</p>
			<?php
			$ov_steps(
				array(
					'În OptiVio, deschideți site-ul (Afacere → Site-uri → site sau meniul **Site-uri & Connector**).',
					'În cardul „OptiVio Connector” apăsați **Generează cheia**. Secretul se afișează o singură dată — nu trebuie copiat, este inclus automat în plugin.',
					'Apăsați **Descarcă pluginul (.zip) cu cheia inclusă**.',
					'În WordPress: **Plugins → Add New → Upload Plugin**, alegeți fișierul .zip, **Install Now**, apoi **Activate**.',
					'Pluginul se conectează imediat (heartbeat) și pornește sincronizarea completă a conținutului. Verificați în WordPress → **Settings → OptiVio Connector**: starea trebuie să fie „conectat”.',
					'Înapoi în OptiVio, pe pagina site-ului, apăsați **Testează conexiunea OptiVio → WordPress**. Starea conectorului devine „conectat”, iar în **Conținut & SEO** apar paginile site-ului.',
					'Testați tracking-ul: deschideți site-ul de pe telefon, apăsați un număr de telefon și trimiteți un formular de test — apar în **Conversii** în câteva minute.',
					'Pentru site-uri cu trafic mic, cereți un cron real în hosting-ul site-ului: `*/5 * * * * wget -qO- https://site.ro/wp-cron.php >/dev/null`.',
				)
			);
			?>
			<div class="ov-alert ov-alert-warning mb-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div>Dacă apăsați <strong>Rotește secretul</strong> sau <strong>Revocă</strong>, pluginul instalat nu se mai poate conecta: descărcați din nou .zip-ul și reinstalați-l (sau lipiți noua conexiune în Settings → OptiVio Connector).</div></div>
			<p class="ov-cell-sub mb-0">Cerințe: WordPress 6.2+, PHP 8.0+. Gravity Forms și Yoast SEO sunt opționale (dacă lipsesc, modulele respective nu se activează).</p>
		</section>

		<section class="ov-card ov-help-section" id="google">
			<h2><i class="bi bi-google me-2" aria-hidden="true"></i>Conectarea integrărilor Google pentru un site</h2>
			<?php
			$ov_steps(
				array(
					'Asigurați-vă că în Google Cloud Console sunt activate API-urile necesare (vezi Ghiduri de integrare mai jos) și că contul Google al agenției are acces la proprietățile clientului.',
					'Pe pagina site-ului, cardul **Integrări Google**: pentru fiecare integrare alegeți contul Google și proprietatea (lista se completează automat din conturile conectate), apoi **Salvează**. Iconița „?” de lângă fiecare integrare deschide ghidul ei.',
					'Apăsați **Sincronizează**. Prima rulare aduce istoricul (Search Console: implicit 90 de zile); apoi sincronizarea rulează automat zilnic (PageSpeed și Tag Manager săptămânal).',
					'Verificați datele în **Date Google** (pe pagina site-ului). Erorile apar în dreptul integrării, cu motivul.',
					'PageSpeed Insights nu are nevoie de cont Google — doar de URL-ul monitorizat.',
				)
			);
			?>
			<p class="ov-cell-sub mb-0">Asocierea cont + proprietate o face Super Adminul; operatorii cu drept de gestionare pot porni sincronizarea.</p>
		</section>

		<section class="ov-card ov-help-section" id="access">
			<h2><i class="bi bi-people me-2" aria-hidden="true"></i>Acces pentru operatori</h2>
			<?php
			$ov_steps(
				array(
					'**Administrare → Utilizatori → Utilizator nou**: nume, email, rol „Operator” și o parolă temporară (utilizatorul este obligat să o schimbe la prima autentificare).',
					'Pentru fiecare afacere alegeți dreptul: **Vizualizare** (doar citire) sau **Gestionare** (editare, aplicare Action Cards, import apeluri, analize AI).',
					'Operatorul vede doar afacerile primite. Selectorul din dreapta sus schimbă afacerea activă pentru paginile de strategie.',
				)
			);
			?>
		</section>

		<section class="ov-card ov-help-section" id="daily">
			<h2><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Lucrul zilnic și săptămânal</h2>
			<h3>Zilnic</h3>
			<?php
			$ov_steps(
				array(
					'**Dashboard**: conversii (apeluri, click-uri tel:, formulare), evoluție și activitate recentă.',
					'**Conversii**: verificați formularele noi și tab-ul **Spam filtrat** (marcați manual falsurile pozitive).',
					'**Apeluri & venituri**: adăugați rezultatul apelurilor (acceptat / refuzat / pierdut, valoare) sau importați CSV-ul de la centrală (**Import CSV**). Apelurile se leagă automat de click-ul pe telefon și moștenesc canalul și cuvântul cheie.',
				)
			);
			?>
			<h3>Săptămânal</h3>
			<?php
			$ov_steps(
				array(
					'Selectați afacerea în selectorul din dreapta sus și apăsați **Recalculează analiza** (sau așteptați rularea automată zilnică).',
					'Parcurgeți **Cuvinte cheie**, **Google Ads**, **Audit SEO** și **Concurență** — fiecare problemă are explicația regulii care a declanșat-o.',
					'Deschideți **Action Cards** → tab „De decis”. Pentru fiecare card: citiți „De ce”, editați propunerea, apoi **Aprobă și aplică pe site** (SEO, conținut, linkuri, pagini noi — cu snapshot), **Marchează ca aplicat manual** (Ads, Business Profile) sau **Respinge** cu motiv (devine regulă permanentă).',
					'Un card aplicat greșit se anulează din pagina lui cu **Rollback 1-click**.',
					'Lunar sau la nevoie: **Analize AI → Pregătește o analiză nouă** pentru un diagnostic complet și carduri noi (cu confirmarea costului).',
				)
			);
			?>
			<h3>Pagini noi cu imagini</h3>
			<p class="mb-0">Cardurile „Articol nou” / „Landing page nou” includ prompturi pentru imagini. Copiați promptul într-un generator de imagini (sau Canva), încărcați rezultatul în card, apoi aplicați: imaginile se urcă în Media Library, iar pagina se creează ca ciornă sau publicată, după alegere.</p>
		</section>

		<section class="ov-card ov-help-section" id="menu">
			<h2><i class="bi bi-list-ul me-2" aria-hidden="true"></i>Ce găsiți în fiecare meniu</h2>
			<dl class="ov-help-menu mb-0">
				<dt>Principal → Dashboard</dt><dd>Indicatori pe afacerile accesibile: conversii, evoluție pe zile, surse, activitate.</dd>
				<dt>Principal → Conversii</dt><dd>Click-uri pe telefon și formulare din site-uri, cu canalul (Google Ads, organic, Maps, social, direct…) și scorul de spam; tab-ul Spam filtrat.</dd>
				<dt>Principal → Afaceri</dt><dd>Lista clienților și pagina fiecărei afaceri cu tab-urile Prezentare, Profil, Contacte, Servicii & produse, USP, Site-uri, Reguli, AI & conectori.</dd>
				<dt>Analiză & strategie → Cuvinte cheie</dt><dd>Cercetare din Search Console, Ads, servicii × zonă și SERP, cu intenție și prioritate; insight-uri (canibalizare, aproape de top 3, CTR mic).</dd>
				<dt>Analiză & strategie → Google Ads</dt><dd>Strategia recomandată pentru modelul de afacere, termeni plătiți fără rezultat, ore proaste, cuvinte cu apeluri refuzate.</dd>
				<dt>Analiză & strategie → Apeluri & venituri</dt><dd>Registrul apelurilor, import CSV, analiză pe cuvinte cheie, ore și canale; recomandări de cuvinte negative și Ad Scheduling.</dd>
				<dt>Analiză & strategie → Concurență</dt><dd>Concurenți din Google (Serper) sau adăugați manual; comparație de termeni, titluri H2, lungime, date structurate.</dd>
				<dt>Analiză & strategie → Analize AI</dt><dd>Analiza profundă multi-sursă la cerere, cu cost estimat și istoric.</dd>
				<dt>Analiză & strategie → Action Cards</dt><dd>Propunerile de decis, aplicate, respinse și anulate.</dd>
				<dt>Site-uri & integrări → Site-uri & Connector</dt><dd>Toate site-urile, starea pluginului, descărcarea pluginului, Conținut & SEO (editor Yoast), Istoric modificări (rollback), integrările Google ale site-ului.</dd>
				<dt>Site-uri & integrări → Integrări Google</dt><dd>Conturile Google conectate și proprietățile descoperite (conectarea o face Super Adminul).</dd>
				<dt>Site-uri & integrări → Audit SEO</dt><dd>Problemele on-page, tehnice, PageSpeed și Tag Manager, cu severitate și recomandare.</dd>
				<?php if ( ! empty( $view['is_admin'] ) ) : ?>
					<dt>Administrare → Utilizatori</dt><dd>Conturi, roluri și drepturi pe afaceri.</dd>
					<dt>Administrare → Jurnal audit</dt><dd>Cine a făcut ce: autentificări, modificări, aplicări pe site, apeluri AI.</dd>
					<dt>Administrare → Setări</dt><dd>General, Securitate, AI / OpenRouter, Integrări & chei API, Anti-spam & conector, Cron & joburi.</dd>
				<?php endif; ?>
				<dt>Bara de sus</dt><dd>Căutare afaceri, notificări (ex. conector de site inactiv) și selectorul afacerii active.</dd>
			</dl>
		</section>

		<section class="ov-card ov-help-section" id="guides">
			<h2><i class="bi bi-book me-2" aria-hidden="true"></i>Ghiduri de integrare</h2>
			<p class="ov-cell-sub">Aceleași ghiduri se deschid din butoanele „Cum configurez?” din Setări și de pe pagina site-ului.</p>
			<div class="accordion" id="ov-guides-acc">
				<?php foreach ( $view['guides'] as $ov_key => $ov_guide ) : ?>
					<div class="accordion-item" id="ghid-<?php echo esc_attr( $ov_key ); ?>">
						<h3 class="accordion-header m-0">
							<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acc-<?php echo esc_attr( $ov_key ); ?>" aria-expanded="false" aria-controls="acc-<?php echo esc_attr( $ov_key ); ?>">
								<i class="bi <?php echo esc_attr( $ov_guide['icon'] ); ?> me-2" aria-hidden="true"></i><?php echo esc_html( $ov_guide['title'] ); ?>
							</button>
						</h3>
						<div id="acc-<?php echo esc_attr( $ov_key ); ?>" class="accordion-collapse collapse" data-bs-parent="#ov-guides-acc">
							<div class="accordion-body"><?php View::partial( 'partials/guide-body', array( 'guide' => $ov_guide ) ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="ov-card ov-help-section" id="troubleshoot">
			<h2><i class="bi bi-wrench-adjustable me-2" aria-hidden="true"></i>Probleme frecvente</h2>
			<dl class="ov-help-menu mb-0">
				<dt>Conectorul apare „inactiv”</dt><dd>Pluginul nu a mai trimis heartbeat de peste o oră: verificați că pluginul e activ, că WP-Cron rulează și că secretul nu a fost rotit (în acest caz reinstalați .zip-ul nou).</dd>
				<dt>„Testează conexiunea” eșuează</dt><dd>Firewall-ul / pluginul de securitate al site-ului blochează REST API-ul WordPress (<span class="ov-kbd">/wp-json/optivio/v1/remote</span>) — adăugați o excepție.</dd>
				<dt>Integrarea Google are eroare</dt><dd>Mesajul din dreptul integrării spune cauza: API neactivat în Google Cloud, contul agenției fără acces la proprietate sau autorizare expirată (reconectați contul din Integrări Google).</dd>
				<dt>Google Ads: „developer token” respins</dt><dd>Tokenul are doar „Test account access” — solicitați „Basic access” în API Center.</dd>
				<dt>Business Profile: cotă 0</dt><dd>Accesul la API nu a fost încă aprobat de Google pentru proiectul Cloud.</dd>
				<dt>Nu apar date noi</dt><dd>Verificați în Setări → Cron & joburi că ultima rulare e recentă și că nu există joburi eșuate.</dd>
				<dt>Butonul de analiză AI e blocat</dt><dd>Lipsește cheia OpenRouter sau s-a atins bugetul lunar (Setări → AI / OpenRouter).</dd>
				<dt>Am fost deconectat brusc</dt><dd>Sesiunea expiră după inactivitate și este legată de browser; schimbarea browserului sau a modului de emulare cere o nouă autentificare.</dd>
			</dl>
		</section>
	</div>
</div>
