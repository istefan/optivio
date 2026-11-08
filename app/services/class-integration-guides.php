<?php
/**
 * OptiVio — Step-by-step setup guides for every external integration (Google Cloud Console,
 * Google Ads API, Business Profile, PageSpeed, Serper, OpenRouter).
 *
 * Used by the help pop-ups in Settings / site pages and by the Help page, so the
 * instructions live in a single place.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Static catalogue of integration guides.
 */
final class Integration_Guides {

	/**
	 * Keys rendered on the current page (their modals are printed once by the layout).
	 *
	 * @var array<string, true>
	 */
	private static array $used = array();

	/**
	 * All guides: key => title, icon, summary, links [label, url], steps, notes.
	 * The token {redirect_uri} is replaced with the OAuth callback of this installation.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return array(
			'google_oauth' => array(
				'title'   => 'Google OAuth (Client ID și Client Secret)',
				'icon'    => 'bi-google',
				'summary' => 'Un singur client OAuth în Google Cloud Console permite conectarea contului Google al agenției și citirea datelor din Search Console, Analytics, Tag Manager, Ads, AdSense și Business Profile.',
				'links'   => array(
					array( 'Google Cloud Console → Credentials', 'https://console.cloud.google.com/apis/credentials' ),
					array( 'Ecran de consimțământ OAuth', 'https://console.cloud.google.com/apis/credentials/consent' ),
				),
				'steps'   => array(
					'Deschideți Google Cloud Console și creați un proiect nou (ex. „OptiVio”) din selectorul de proiecte din bara de sus.',
					'APIs & Services → OAuth consent screen: alegeți „Internal” (dacă aveți Google Workspace) sau „External”. Completați numele aplicației, emailul de suport și domeniul; la „External” adăugați la „Test users” contul Google al agenției (sau publicați aplicația).',
					'Activați API-urile necesare (APIs & Services → Library) — vedeți ghidurile fiecărei integrări: Search Console, Analytics Data + Admin, Tag Manager, Google Ads, AdSense Management, Business Profile.',
					'APIs & Services → Credentials → Create credentials → OAuth client ID → tip „Web application”.',
					'La „Authorized redirect URIs” adăugați exact: {redirect_uri}',
					'Copiați Client ID și Client Secret în OptiVio → Setări → Integrări & chei API și salvați.',
					'Mergeți la Integrări Google → „Conectează cont Google”, autentificați-vă cu contul agenției și acceptați permisiunile. Proprietățile accesibile se descoperă automat.',
				),
				'notes'   => array(
					'Contul Google conectat trebuie să aibă acces (utilizator) la proprietățile fiecărui client: GSC, GA4, GTM, Ads, AdSense, Business Profile.',
					'Client Secret se stochează criptat și nu mai este afișat după salvare.',
				),
			),
			'gsc'          => array(
				'title'   => 'Google Search Console',
				'icon'    => 'bi-search',
				'summary' => 'Interogări, pagini, afișări, click-uri și poziții medii pentru cercetarea cuvintelor cheie, canibalizare și CTR.',
				'links'   => array(
					array( 'Activează Search Console API', 'https://console.cloud.google.com/apis/library/searchconsole.googleapis.com' ),
					array( 'Search Console', 'https://search.google.com/search-console' ),
				),
				'steps'   => array(
					'În proiectul Google Cloud al OptiVio, activați „Google Search Console API”.',
					'În Search Console, la proprietatea clientului → Setări → Utilizatori și permisiuni, adăugați contul Google al agenției (permisiune „Complet” sau „Restricționat”).',
					'În OptiVio, pe pagina site-ului → Integrări Google → Google Search Console: alegeți contul Google și proprietatea (ex. sc-domain:exemplu.ro sau https://www.exemplu.ro/), apoi „Salvează” și „Sincronizează”.',
				),
				'notes'   => array(
					'Prima sincronizare aduce istoricul configurat în Setări (implicit 90 de zile); apoi rulează zilnic.',
				),
			),
			'ga4'          => array(
				'title'   => 'Google Analytics 4',
				'icon'    => 'bi-graph-up',
				'summary' => 'Sesiuni, utilizatori și conversii pe pagini și surse de trafic.',
				'links'   => array(
					array( 'Activează Analytics Data API', 'https://console.cloud.google.com/apis/library/analyticsdata.googleapis.com' ),
					array( 'Activează Analytics Admin API', 'https://console.cloud.google.com/apis/library/analyticsadmin.googleapis.com' ),
				),
				'steps'   => array(
					'Activați „Google Analytics Data API” și „Google Analytics Admin API” în proiectul Google Cloud.',
					'În Google Analytics → Admin → Property access management, adăugați contul agenției cu rol „Viewer” (minim).',
					'Găsiți ID-ul numeric al proprietății în Admin → Property settings (ex. 312345678).',
					'În OptiVio, pe pagina site-ului → Google Analytics 4: alegeți contul și proprietatea, salvați și sincronizați.',
				),
				'notes'   => array(),
			),
			'gtm'          => array(
				'title'   => 'Google Tag Manager',
				'icon'    => 'bi-tags',
				'summary' => 'Snapshot săptămânal al versiunii live a containerului (taguri, triggere) pentru auditul de tracking.',
				'links'   => array(
					array( 'Activează Tag Manager API', 'https://console.cloud.google.com/apis/library/tagmanager.googleapis.com' ),
					array( 'Tag Manager', 'https://tagmanager.google.com/' ),
				),
				'steps'   => array(
					'Activați „Tag Manager API” în proiectul Google Cloud.',
					'În Tag Manager → Admin → User Management (la nivel de cont sau container), adăugați contul agenției cu permisiune „Read”.',
					'În OptiVio, pe pagina site-ului → Google Tag Manager: introduceți ID-ul containerului (GTM-XXXXXXX), alegeți contul și salvați.',
				),
				'notes'   => array(),
			),
			'ads'          => array(
				'title'   => 'Google Ads API',
				'icon'    => 'bi-badge-ad',
				'summary' => 'Campanii, cuvinte cheie, negative, performanța pe ore și termeni de căutare. Necesită un developer token aprobat.',
				'links'   => array(
					array( 'Activează Google Ads API', 'https://console.cloud.google.com/apis/library/googleads.googleapis.com' ),
					array( 'Google Ads → API Center (developer token)', 'https://ads.google.com/aw/apicenter' ),
					array( 'Documentație acces API', 'https://developers.google.com/google-ads/api/docs/access-levels' ),
				),
				'steps'   => array(
					'Activați „Google Ads API” în proiectul Google Cloud.',
					'Autentificați-vă în contul de manager (MCC) al agenției → Admin → API Center și solicitați developer token-ul. Pentru conturi reale este necesar nivelul „Basic access” (cererea se aprobă de Google în câteva zile).',
					'În OptiVio → Setări → Integrări & chei API: lipiți developer token-ul și, dacă accesați clienții prin MCC, „Login customer ID” = ID-ul contului de manager (fără cratime). Verificați versiunea API (ex. v21) cu versiunea curentă suportată de Google.',
					'Asigurați-vă că contul Google conectat în OptiVio are acces la contul de Ads al clientului (direct sau prin MCC).',
					'Pe pagina site-ului → Google Ads: introduceți Customer ID-ul clientului (123-456-7890), alegeți contul Google, salvați și sincronizați.',
				),
				'notes'   => array(
					'Cu un token „Test account access”, API-ul funcționează doar pe conturi de test.',
				),
			),
			'adsense'      => array(
				'title'   => 'Google AdSense',
				'icon'    => 'bi-currency-dollar',
				'summary' => 'Venituri zilnice din AdSense pentru site-urile de conținut.',
				'links'   => array(
					array( 'Activează AdSense Management API', 'https://console.cloud.google.com/apis/library/adsense.googleapis.com' ),
				),
				'steps'   => array(
					'Activați „AdSense Management API” în proiectul Google Cloud.',
					'Contul Google conectat în OptiVio trebuie să fie utilizator al contului AdSense (Account → Access and authorization → User management).',
					'Pe pagina site-ului → Google AdSense: introduceți Publisher ID-ul (pub-…), alegeți contul și salvați.',
				),
				'notes'   => array(),
			),
			'gbp'          => array(
				'title'   => 'Google Business Profile',
				'icon'    => 'bi-shop',
				'summary' => 'Afișări în Maps / Search, apeluri, cereri de direcții și click-uri pe site din fișa de business.',
				'links'   => array(
					array( 'Cerere acces Business Profile API', 'https://developers.google.com/my-business/content/prereqs' ),
					array( 'Business Profile Performance API', 'https://console.cloud.google.com/apis/library/businessprofileperformance.googleapis.com' ),
					array( 'Account Management API', 'https://console.cloud.google.com/apis/library/mybusinessaccountmanagement.googleapis.com' ),
					array( 'Business Information API', 'https://console.cloud.google.com/apis/library/mybusinessbusinessinformation.googleapis.com' ),
				),
				'steps'   => array(
					'Business Profile API nu este disponibil automat: completați formularul de cerere de acces de la Google (link mai sus) pentru proiectul Google Cloud al OptiVio și așteptați aprobarea.',
					'După aprobare activați „Business Profile Performance API”, „My Business Account Management API” și „My Business Business Information API”.',
					'Contul Google al agenției trebuie să fie Manager/Proprietar al fișei de business a clientului.',
					'Pe pagina site-ului → Google Business Profile: alegeți locația (locations/…) din listă, salvați și sincronizați.',
				),
				'notes'   => array(
					'Până la aprobare, cererile vor eșua cu eroare de cotă 0 — este comportamentul normal al Google.',
				),
			),
			'psi'          => array(
				'title'   => 'PageSpeed Insights',
				'icon'    => 'bi-speedometer2',
				'summary' => 'Scoruri Core Web Vitals / Lighthouse săptămânale. Funcționează și fără cheie; cheia doar mărește cota.',
				'links'   => array(
					array( 'Activează PageSpeed Insights API', 'https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com' ),
					array( 'Creează cheie API', 'https://console.cloud.google.com/apis/credentials' ),
				),
				'steps'   => array(
					'(Opțional) Activați „PageSpeed Insights API” în proiectul Google Cloud.',
					'Credentials → Create credentials → API key. Restricționați cheia la „PageSpeed Insights API” (API restrictions).',
					'Lipiți cheia în OptiVio → Setări → Integrări & chei API → PageSpeed.',
					'Pe pagina site-ului → PageSpeed Insights: URL-ul monitorizat (implicit pagina principală), salvați și sincronizați.',
				),
				'notes'   => array(),
			),
			'serper'       => array(
				'title'   => 'Serper.dev (rezultate Google / concurență)',
				'icon'    => 'bi-binoculars',
				'summary' => 'Extrage automat concurenții din primele rezultate Google pentru cuvintele cheie ale afacerii.',
				'links'   => array(
					array( 'Serper.dev → API key', 'https://serper.dev/api-key' ),
				),
				'steps'   => array(
					'Creați un cont pe serper.dev (include credite gratuite pentru test).',
					'Copiați cheia din pagina „API key”.',
					'Lipiți cheia în OptiVio → Setări → Integrări & chei API → Serper și salvați. Ajustați țara (ro), limba (ro) și locația implicită.',
				),
				'notes'   => array(
					'Fără cheie, concurenții se adaugă manual (URL) în pagina Concurență.',
				),
			),
			'openrouter'   => array(
				'title'   => 'OpenRouter (AI)',
				'icon'    => 'bi-cpu',
				'summary' => 'Acces la modelele AI folosite doar la cerere: analiza profundă și textele Action Cards.',
				'links'   => array(
					array( 'OpenRouter → API keys', 'https://openrouter.ai/settings/keys' ),
					array( 'Credite', 'https://openrouter.ai/settings/credits' ),
					array( 'Lista de modele', 'https://openrouter.ai/models' ),
				),
				'steps'   => array(
					'Creați un cont pe openrouter.ai și adăugați credite (Settings → Credits).',
					'Settings → API keys → „Create key”; opțional setați o limită de credit pe cheie.',
					'Lipiți cheia în OptiVio → Setări → AI / OpenRouter (cheie globală) sau, per client, în pagina afacerii → AI & conectori.',
					'Alegeți modelele pentru cele 4 profiluri (ID-uri din lista de modele OpenRouter, ex. furnizor/model), bugetul lunar și confirmarea înainte de fiecare apel. Pentru profilul „Percepție / Onboarding” (citirea a până la 100 de pagini) este recomandat un model Gemini Flash: context mare, cost mic.',
					'Apăsați „Testează conexiunea” lângă cheie: verifică cheia și consumul, fără să apeleze vreun model.',
				),
				'notes'   => array(
					'AI-ul nu rulează niciodată automat (cron, sincronizări, plugin) — doar la butonul unui utilizator.',
				),
			),
			'gdrive'       => array(
				'title'   => 'Google Drive (backup)',
				'icon'    => 'bi-cloud-arrow-up',
				'summary' => 'Backup automat al bazei de date și al fișierelor într-un folder din Google Drive. Se folosește același client OAuth ca la integrările Google.',
				'links'   => array(
					array( 'Activează Google Drive API', 'https://console.cloud.google.com/apis/library/drive.googleapis.com' ),
					array( 'Ecran de consimțământ OAuth', 'https://console.cloud.google.com/apis/credentials/consent' ),
				),
				'steps'   => array(
					'În proiectul Google Cloud al OptiVio, activați „Google Drive API”.',
					'Clientul OAuth (Client ID / Client Secret) și URI-ul de redirecționare sunt aceleași ca la integrările Google: {redirect_uri}. Nu trebuie adăugat nimic nou.',
					'Dacă aplicația OAuth este „External” în mod Testing, contul Google în care salvați backup-urile trebuie să fie la „Test users”.',
					'În OptiVio → Administrare → Backup Google Drive apăsați „Conectează Google Drive” și acceptați permisiunea de acces la fișierele create de aplicație (drive.file).',
					'Alegeți ce se salvează (bază de date, fișiere), periodicitatea, ora și câte backup-uri se păstrează (1–30), apoi „Salvează configurarea”.',
				),
				'notes'   => array(
					'Cu permisiunea drive.file, OptiVio vede doar folderul și fișierele create de el, nu restul documentelor din Drive.',
					'Backup-ul rulează prin cron-ul automat al aplicației: nu trebuie configurat nimic în cPanel.',
				),
			),
		);
	}

	/**
	 * One guide (with {redirect_uri} resolved) or null.
	 *
	 * @param string $key Guide key.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		$all = self::all();
		if ( ! isset( $all[ $key ] ) ) {
			return null;
		}
		$guide          = $all[ $key ];
		$redirect       = ov_url( '/oauth/google/callback' );
		$guide['steps'] = array_map(
			static function ( string $step ) use ( $redirect ): string {
				return str_replace( '{redirect_uri}', $redirect, $step );
			},
			$guide['steps']
		);
		return $guide;
	}

	/**
	 * Marks a guide as used on the current page and returns the modal id.
	 *
	 * @param string $key Guide key.
	 * @return string
	 */
	public static function use( string $key ): string {
		self::$used[ $key ] = true;
		return 'ov-guide-' . $key;
	}

	/**
	 * Guides used on the current page.
	 *
	 * @return string[]
	 */
	public static function used(): array {
		return array_keys( self::$used );
	}
}
