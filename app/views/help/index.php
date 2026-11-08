<?php
/**
 * OptiVio — Help page: first setup, the onboarding wizard (AI perception vs reality), the
 * WordPress plugin, Google, daily workflow, backups, updates, menu reference, integration
 * guides and troubleshooting.
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
	'business'     => array( 'bi-magic', 'Afacere nouă: wizard-ul pas cu pas' ),
	'perception'   => array( 'bi-robot', 'Cum vă vede un bot: percepție vs realitate' ),
	'plugin'       => array( 'bi-plug', 'Instalarea pluginului WordPress' ),
	'google'       => array( 'bi-google', 'Conectarea integrărilor Google' ),
	'access'       => array( 'bi-people', 'Acces pentru operatori' ),
	'daily'        => array( 'bi-calendar-check', 'Lucrul zilnic și săptămânal' ),
	'backup'       => array( 'bi-cloud-arrow-up', 'Backup, cron și actualizări' ),
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
			<p>OptiVio pornește de la <strong>ce înțelege un bot</strong> despre afacere din site-urile ei, adaugă apoi datele din interior (pluginul WordPress: conținut, SEO, click-uri pe telefon, formulare) și din Google (Search Console, Analytics, Ads, Business Profile), le analizează <strong>determinist</strong> (reguli fixe, fără costuri) și transformă rezultatul în <strong>Action Cards</strong>: propuneri concrete pe care le aprobați, editați sau respingeți.</p>
			<p class="mb-2">Analiza are trei straturi, în ordine:</p>
			<?php
			$ov_steps(
				array(
					'**Din exterior** (în wizard): paginile publice citite automat + o singură analiză AI la cerere. Rezultatul este **amprenta inițială**: cum descrie un bot afacerea.',
					'**Din interior** (după instalarea pluginului pe fiecare site): tot conținutul, Yoast SEO, linkurile interne, conversiile. Opțional, „Percepție din interior” (AI, la cerere) pe fiecare site.',
					'**Din Google** (prin contul de manager al agenției): Search Console, Analytics, Ads, Business Profile — sincronizate zilnic, fără AI.',
				)
			);
			?>
			<div class="ov-alert ov-alert-info mb-0"><i class="bi bi-cpu" aria-hidden="true"></i><div><strong>AI doar la cerere.</strong> Sincronizările, cron-ul, backup-ul și pluginul nu folosesc AI. Un apel AI pornește doar când un utilizator apasă butonul, după confirmarea costului estimat.</div></div>
		</section>

		<section class="ov-card ov-help-section" id="setup">
			<h2><i class="bi bi-sliders me-2" aria-hidden="true"></i>Configurare inițială (o singură dată)</h2>
			<p class="ov-cell-sub">Pașii din această secțiune se fac de un <strong>Super Admin</strong>, din meniul Administrare → Setări. Nu este nevoie de cPanel: cron-ul și actualizările bazei de date sunt automate.</p>
			<?php
			$ov_steps(
				array(
					'**Setări → General**: numele aplicației, fusul orar și moneda implicită.',
					'**Setări → Securitate**: deconectarea după inactivitate, durata maximă a sesiunii, blocarea după încercări eșuate, lungimea minimă a parolei, păstrarea jurnalului de audit.',
					'**Setări → AI / OpenRouter**: cheia globală OpenRouter, modelele celor 4 profiluri (rapid, raționament, copywriting, **percepție / onboarding** — recomandat Gemini Flash), bugetul lunar, „Cere confirmare înainte de fiecare apel AI” și limitele de onboarding (implicit 50 de pagini pe site, 100 pe analiză). Apăsați **Testează conexiunea** lângă cheie.',
					'**Setări → Integrări & chei API**: Client ID și Client Secret Google OAuth (copiați în Google Cloud Console URI-ul de redirecționare afișat), developer token Google Ads, cheile Serper și PageSpeed. Butonul **Testează conexiunea** verifică fiecare cheie; „Cum configurez?” deschide ghidul pas cu pas.',
					'**Integrări Google → Conectează cont Google**: autentificați-vă cu contul Google al agenției (cel cu acces de manager la proprietățile clienților).',
					'**Administrare → Backup Google Drive**: conectați Google Drive, alegeți ce se salvează, periodicitatea și câte backup-uri se păstrează.',
					'**Setări → Anti-spam & conector**: pragurile de spam pentru formulare, timpul minim de completare, cuvintele blocate.',
					'**Setări → Cron & joburi**: doar verificare — cron-ul pornește singur (vezi „Backup, cron și actualizări”).',
				)
			);
			?>
		</section>

		<section class="ov-card ov-help-section" id="business">
			<h2><i class="bi bi-magic me-2" aria-hidden="true"></i>Afacere nouă: wizard-ul pas cu pas</h2>
			<p>Din <strong>Afaceri → Afacere nouă (wizard)</strong> (Super Admin). Wizard-ul se poate întrerupe oricând: ciornele în curs apar în lista de onboarding, cu butonul „Continuă”. Ciornele abandonate se șterg automat după 30 de zile.</p>
			<?php
			$ov_steps(
				array(
					'**Site-uri și pagini**: introduceți unul sau mai multe domenii. OptiVio citește (fără AI) robots.txt, sitemap-ul și meniul paginii principale și propune paginile, grupate pe tip (acasă, servicii, prețuri, despre, contact, blog, legal). Bifați până la 50 de pagini pe site și 100 pe analiză; adăugați URL-uri manual dacă lipsesc. Paginile se citesc în fundal, cu progres live.',
					'**Ce înțelege AI-ul**: vedeți ce se trimite (pagini, caractere, header/footer comun trimis o singură dată), costul estimat și datele găsite automat (telefoane, emailuri, CUI, program, prețuri, schema.org). Un singur apel AI, la buton, descrie afacerea așa cum o înțelege un bot.',
					'**Confirmă profilul**: formularul vine precompletat. Sub fiecare câmp vedeți „AI a înțeles: …” cu încrederea, sursa și citatul. Alegeți **Corect / Corectez / Nu se aplică**, debifați serviciile deduse greșit, adăugați serviciile reale pe care AI nu le-a găsit și bifați punctele forte reale. La salvare se creează afacerea, site-urile (cu cheia pentru plugin) și serviciile, iar diferențele devin recomandări.',
					'**USP și reguli**: USP-ul calculat din punctele forte confirmate, regulile permanente, valoarea medie a unei lucrări, capacitatea zilnică și persoana de contact.',
					'**Plugin**: pentru fiecare site WordPress, descărcați pluginul (cheia e inclusă) și instalați-l. Starea se actualizează singură: „Aștept heartbeat” → „Conectat” → pagini sincronizate. Se poate amâna.',
					'**Google**: starea integrărilor fiecărui site și linkul spre configurare. Se poate amâna.',
					'**Rezumat**: scorul de pregătire, scorul de claritate, diferențele percepție vs realitate și cardurile create. **Finalizează** rulează o dată motorul determinist.',
				)
			);
			?>
			<p class="ov-cell-sub mb-0">Fără cheie AI sau dacă site-ul blochează citirea automată, folosiți <strong>Completare manuală</strong> (formularul clasic); tab-urile afacerii (Profil, Contacte, Servicii & produse, USP, Site-uri, Reguli, AI & conectori) rămân disponibile pentru completări ulterioare.</p>
		</section>

		<section class="ov-card ov-help-section" id="perception">
			<h2><i class="bi bi-robot me-2" aria-hidden="true"></i>Cum vă vede un bot: percepție vs realitate</h2>
			<p>Analiza AI din wizard este salvată neschimbată ca <strong>amprentă inițială</strong>. Comparând-o cu profilul confirmat, OptiVio clasifică fiecare informație (fără AI):</p>
			<dl class="ov-help-menu">
				<dt>Înțeles corect</dt><dd>Botul a înțeles ce este adevărat (model de afacere, serviciu, zonă, punct forte, telefon).</dd>
				<dt>Înțeles greșit</dt><dd>Botul a înțeles altceva decât realitatea (ex. alt tip de afacere) — card cu prioritate mare pentru mesajul paginii principale.</dd>
				<dt>Lipsă pe site</dt><dd>Ceva real pe care site-ul nu îl comunică (un serviciu, o zonă, „garanție”) — card „Landing page nou” sau pași concreți de completare.</dd>
				<dt>Dedus fals</dt><dd>Botul a dedus ceva ce afacerea nu oferă — card cu fragmentul de text care a indus în eroare.</dd>
				<dt>Neclar</dt><dd>Subiecte pe care botul nu le-a putut lămuri (prețuri, program, zonă).</dd>
			</dl>
			<p>Pe aceste rezultate se calculează <strong>scorul de claritate</strong> (0–100%), ponderat: model de afacere 20, servicii 25, zone 15, puncte forte 15, telefon 15, public țintă 10.</p>
			<?php
			$ov_steps(
				array(
					'Tab-ul **Percepție** al afacerii arată istoricul percepțiilor, scorul, diferențele și cardul creat pentru fiecare.',
					'**Percepție din interior**: buton pe fiecare site, disponibil după ce pluginul a fost instalat, este conectat și a sincronizat conținutul. Citește tot conținutul din WordPress (inclusiv titlurile și descrierile Yoast).',
					'**Re-analizează din exterior** (Super Admin): aceleași pagini ca data trecută; arată dacă modificările de pe site au crescut claritatea față de amprenta inițială.',
					'Cardurile nu se dublează la re-analize. Când o problemă apare rezolvată, cardul deschis primește o notă „Actualizare percepție”.',
				)
			);
			?>
		</section>

		<section class="ov-card ov-help-section" id="plugin">
			<h2><i class="bi bi-plug me-2" aria-hidden="true"></i>Instalarea pluginului WordPress (OptiVio Connector)</h2>
			<p>Pluginul trimite către OptiVio click-urile pe numerele de telefon, formularele Gravity Forms (cu filtrare anti-spam), sursa vizitatorului (Google Ads, organic, Maps…) și conținutul site-ului cu datele Yoast SEO. Tot prin el se aplică modificările aprobate, cu rollback. Se instalează din pasul 5 al wizard-ului sau oricând din pagina site-ului.</p>
			<?php
			$ov_steps(
				array(
					'Deschideți site-ul (pasul **Plugin** din wizard, Afacere → Site-uri sau meniul **Site-uri & Connector**). Cheia conectorului este deja generată pentru site-urile WordPress create de wizard; altfel apăsați **Generează cheia**.',
					'Apăsați **Descarcă pluginul (.zip)** — cheia este inclusă, nu trebuie copiată.',
					'În WordPress: **Plugins → Add New → Upload Plugin**, alegeți fișierul .zip, **Install Now**, apoi **Activate**.',
					'Pluginul se conectează imediat (heartbeat) și pornește sincronizarea completă a conținutului. În OptiVio starea devine „Conectat” și apare numărul de pagini sincronizate.',
					'Pe pagina site-ului, **Testează conexiunea OptiVio → WordPress** verifică și canalul invers (aplicarea modificărilor).',
					'Testați tracking-ul: deschideți site-ul de pe telefon, apăsați un număr de telefon și trimiteți un formular de test — apar în **Conversii** în câteva minute.',
					'Din tab-ul **Percepție** al afacerii puteți rula acum „Percepție din interior” pentru acest site.',
				)
			);
			?>
			<div class="ov-alert ov-alert-warning mb-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div>Dacă apăsați <strong>Rotește secretul</strong> sau <strong>Revocă</strong>, pluginul instalat nu se mai poate conecta: descărcați din nou .zip-ul și reinstalați-l (sau lipiți noua conexiune în Settings → OptiVio Connector).</div></div>
			<p class="ov-cell-sub mb-0">Cerințe: WordPress 6.2+, PHP 8.0+. Gravity Forms și Yoast SEO sunt opționale. Pluginul folosește WP-Cron-ul standard al WordPress: nu trebuie configurat nimic în hosting.</p>
		</section>

		<section class="ov-card ov-help-section" id="google">
			<h2><i class="bi bi-google me-2" aria-hidden="true"></i>Conectarea integrărilor Google pentru un site</h2>
			<?php
			$ov_steps(
				array(
					'Asigurați-vă că în Google Cloud Console sunt activate API-urile necesare (vezi Ghiduri de integrare mai jos) și că contul Google al agenției are acces la proprietățile clientului (ideal prin contul de manager).',
					'Pe pagina site-ului, cardul **Integrări Google**: pentru fiecare integrare alegeți contul Google și proprietatea (lista se completează automat din conturile conectate), apoi **Salvează**. Iconița „?” de lângă fiecare integrare deschide ghidul ei.',
					'Apăsați **Sincronizează**. Prima rulare aduce istoricul (Search Console: implicit 90 de zile); apoi sincronizarea rulează automat zilnic (PageSpeed și Tag Manager săptămânal).',
					'Verificați datele în **Date Google** (pe pagina site-ului). Erorile apar în dreptul integrării, cu motivul. **Setări → Integrări → Testează conexiunea** verifică clientul OAuth, conturile conectate și developer token-ul Ads.',
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
					'Pentru fiecare afacere alegeți dreptul: **Vizualizare** (doar citire) sau **Gestionare** (editare, aplicare Action Cards, import apeluri, analize AI, percepție din interior).',
					'Operatorul vede doar afacerile primite. Wizard-ul de onboarding, ștergerea afacerilor, backup-ul și setările sunt doar pentru Super Admin.',
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
					'Deschideți **Action Cards** → tab „De decis” (inclusiv cardurile din diferențele de percepție). Pentru fiecare card: citiți „De ce”, editați propunerea, apoi **Aprobă și aplică pe site** (SEO, conținut, linkuri, pagini noi — cu snapshot), **Marchează ca aplicat manual** (Ads, Business Profile) sau **Respinge** cu motiv (devine regulă permanentă).',
					'Un card aplicat greșit se anulează din pagina lui cu **Rollback 1-click**.',
					'Lunar sau după modificări mari pe site: **Percepție → Re-analizează** (claritate) și **Analize AI → Pregătește o analiză nouă** (cu confirmarea costului).',
				)
			);
			?>
			<h3>Pagini noi cu imagini</h3>
			<p class="mb-0">Cardurile „Articol nou” / „Landing page nou” includ prompturi pentru imagini. Copiați promptul într-un generator de imagini (sau Canva), încărcați rezultatul în card, apoi aplicați: imaginile se urcă în Media Library, iar pagina se creează ca ciornă sau publicată, după alegere. Cardurile create din diferențele de percepție au textul gol: apăsați „Generează” înainte de aplicare.</p>
		</section>

		<section class="ov-card ov-help-section" id="backup">
			<h2><i class="bi bi-cloud-arrow-up me-2" aria-hidden="true"></i>Backup, cron și actualizări</h2>
			<h3>Cron automat (fără cPanel)</h3>
			<p>Ca la WordPress, joburile (sincronizări Google, analiza zilnică, verificarea conectorilor, citirea paginilor în onboarding, backup-urile) pornesc singure, în fundal, după orice accesare a aplicației: paginile deschise de utilizatori și cererile trimise de pluginul OptiVio Connector de pe site-urile clienților. Nu se configurează nimic pe server. Starea se vede în <strong>Setări → Cron & joburi</strong>; „Rulează coada acum” pornește imediat procesarea.</p>
			<h3>Backup Google Drive</h3>
			<?php
			$ov_steps(
				array(
					'**Administrare → Backup Google Drive → Conectează Google Drive**. Se folosește același client Google OAuth ca la integrări (activați doar Google Drive API); permisiunea este `drive.file`: OptiVio vede doar folderul și fișierele create de el.',
					'Alegeți ce se salvează: **baza de date** (.sql.gz) și/sau **fișierele** (.zip) — „Recomandat” (imagini încărcate + configurare) sau „Complet” (tot codul aplicației).',
					'Alegeți periodicitatea (zilnic, de două ori pe săptămână, săptămânal, lunar), ora și câte backup-uri se păstrează: ultimele 1, 2, 3, 5, 10, 15, 21 sau 30 (separat pentru bază de date și fișiere); cele mai vechi se șterg automat.',
					'Oricând: **Rulează backup complet acum**, **Doar backup bază de date** sau **Descarcă backup SQL** direct în browser (funcționează și fără Google Drive).',
					'Jurnalul arată fiecare backup, dimensiunea, statusul și linkul spre fișierul din Drive. Un backup eșuat creează o notificare.',
				)
			);
			?>
			<div class="ov-alert ov-alert-warning"><i class="bi bi-shield-lock" aria-hidden="true"></i><div>Arhivele conțin date personale, iar backup-ul de fișiere include <span class="ov-kbd">config/</span> cu cheia de criptare. Păstrați-le doar în locuri private.</div></div>
			<h3>Actualizarea aplicației</h3>
			<p class="mb-0">Încărcați fișierele noi pe server (de exemplu din GitHub), fără a modifica <span class="ov-kbd">config/config.php</span>. La prima accesare după upload, OptiVio aplică singur actualizările bazei de date (<span class="ov-kbd">database/upgrades</span>), o singură dată și în siguranță; nu trebuie rulat niciun script. Setările noi primesc valori implicite, iar secretele noi (de ex. cheia cron-ului intern) se generează automat.</p>
		</section>

		<section class="ov-card ov-help-section" id="menu">
			<h2><i class="bi bi-list-ul me-2" aria-hidden="true"></i>Ce găsiți în fiecare meniu</h2>
			<dl class="ov-help-menu mb-0">
				<dt>Principal → Dashboard</dt><dd>Indicatori pe afacerile accesibile: conversii, evoluție pe zile, surse, activitate.</dd>
				<dt>Principal → Conversii</dt><dd>Click-uri pe telefon și formulare din site-uri, cu canalul (Google Ads, organic, Maps, social, direct…) și scorul de spam; tab-ul Spam filtrat.</dd>
				<dt>Principal → Afaceri</dt><dd>Lista clienților și pagina fiecărei afaceri cu tab-urile Prezentare, Profil, Contacte, Servicii & produse, USP, Site-uri, Reguli, Percepție, AI & conectori. Super Adminul poate șterge definitiv o afacere (din listă sau din „Zonă periculoasă”), cu confirmarea numelui și a parolei.</dd>
				<?php if ( ! empty( $view['is_admin'] ) ) : ?>
					<dt>Principal → Afacere nouă (wizard)</dt><dd>Onboarding-ul în 7 pași și lista ciornelor în curs.</dd>
				<?php endif; ?>
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
					<dt>Administrare → Jurnal audit</dt><dd>Cine a făcut ce: autentificări, modificări, aplicări pe site, apeluri AI, backup-uri, ștergeri.</dd>
					<dt>Administrare → Backup Google Drive</dt><dd>Conexiunea Google Drive, conținutul și periodicitatea backup-ului, backup la cerere, jurnalul backup-urilor.</dd>
					<dt>Administrare → Setări</dt><dd>General, Securitate, AI / OpenRouter (inclusiv limitele de onboarding), Integrări & chei API (cu „Testează conexiunea”), Anti-spam & conector, Cron & joburi.</dd>
				<?php endif; ?>
				<dt>Bara de sus</dt><dd>Căutare afaceri, notificări (ex. conector de site inactiv, backup eșuat) și selectorul afacerii active.</dd>
			</dl>
		</section>

		<section class="ov-card ov-help-section" id="guides">
			<h2><i class="bi bi-book me-2" aria-hidden="true"></i>Ghiduri de integrare</h2>
			<p class="ov-cell-sub">Aceleași ghiduri se deschid din butoanele „Cum configurez?” din Setări, de pe pagina site-ului și din pagina de backup.</p>
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
				<dt>Wizard: pagini cu „HTTP 403 / 429 / 503”</dt><dd>Site-ul este protejat de Cloudflare sau de un firewall care blochează citirea automată. Cereți clientului o excepție pentru „OptiVioBot” sau excludeți paginile respective; la nevoie folosiți completarea manuală.</dd>
				<dt>Wizard: „Nu am găsit un sitemap XML”</dt><dd>Se folosește doar meniul paginii principale. Adăugați manual URL-urile importante (servicii, prețuri, contact).</dd>
				<dt>Wizard: „Text foarte puțin”</dt><dd>Pagina este randată doar cu JavaScript sau este goală: botul vede aproape nimic. Este chiar o problemă de SEO de discutat cu clientul.</dd>
				<dt>Wizard: pagini „blocate” de robots.txt</dt><dd>Bifați „Ignoră robots.txt” doar dacă aveți acordul clientului.</dd>
				<dt>Analiza AI este blocată sau prea scumpă</dt><dd>Lipsește cheia OpenRouter sau s-ar depăși bugetul lunar. La 100 de pagini, reduceți selecția sau limita de caractere (Setări → AI); un model Gemini Flash costă de obicei câțiva cenți pe analiză.</dd>
				<dt>„Percepție din interior” nu apare</dt><dd>Pluginul nu este instalat, nu este conectat sau nu a sincronizat încă paginile site-ului. Butonul apare pe fiecare site după sincronizare.</dd>
				<dt>Ștergere sau arhivare?</dt><dd>Arhivarea (Profil → Stare) este reversibilă. Ștergerea definitivă elimină afacerea și toate datele ei; pluginul de pe site trebuie dezinstalat manual.</dd>
				<dt>Conectorul apare „inactiv”</dt><dd>Pluginul nu a mai trimis heartbeat: verificați că pluginul e activ și că secretul nu a fost rotit (în acest caz reinstalați .zip-ul nou).</dd>
				<dt>„Testează conexiunea” (WordPress) eșuează</dt><dd>Firewall-ul / pluginul de securitate al site-ului blochează REST API-ul WordPress (<span class="ov-kbd">/wp-json/optivio/v1/remote</span>) — adăugați o excepție.</dd>
				<dt>Un test de conexiune din Setări eșuează</dt><dd>Mesajul spune cauza: cheie greșită, API neactivat în Google Cloud, client OAuth greșit (invalid_client) sau cont Google care trebuie reconectat.</dd>
				<dt>Integrarea Google are eroare</dt><dd>Mesajul din dreptul integrării spune cauza: API neactivat în Google Cloud, contul agenției fără acces la proprietate sau autorizare expirată (reconectați contul din Integrări Google).</dd>
				<dt>Google Ads: „developer token” respins</dt><dd>Tokenul are doar „Test account access” — solicitați „Basic access” în API Center.</dd>
				<dt>Business Profile: cotă 0</dt><dd>Accesul la API nu a fost încă aprobat de Google pentru proiectul Cloud.</dd>
				<dt>Nu apar date noi / joburile întârzie</dt><dd>Cron-ul pornește la accesările aplicației și la cererile pluginurilor. Verificați în Setări → Cron & joburi ultima declanșare și dacă cererea internă (loopback) este blocată de gazdă; în acest caz joburile pornesc din browserul utilizatorilor autentificați. „Rulează coada acum” procesează imediat.</dd>
				<dt>Backup-ul eșuează</dt><dd>Verificați că Google Drive API este activat, că Google Drive este conectat (Testează conexiunea) și că există spațiu în Drive. Pentru backup-ul „Complet” pe site-uri mari alegeți „Recomandat”.</dd>
				<dt>„OptiVio își actualizează baza de date”</dt><dd>Apare câteva secunde la prima accesare după încărcarea unei versiuni noi. Dacă persistă, cauza este scrisă în <span class="ov-kbd">storage/logs</span>.</dd>
				<dt>Am fost deconectat brusc</dt><dd>Sesiunea expiră după inactivitate și este legată de browser; schimbarea browserului sau a modului de emulare cere o nouă autentificare.</dd>
			</dl>
		</section>
	</div>
</div>
