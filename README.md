# OptiVio

Platformă centralizată pentru gestionarea afacerilor clienților, a prezenței lor online, a campaniilor de marketing și a suportului de vânzări. Rulează pe `optivio.ahoi.ro`.

> Autor: Stefan Iftimie, founder Ahoi Digital SRL — https://www.ahoi.ro/
> (Mențiunea apare doar în documentație și în docblock-urile codului, **niciodată în UI**.)

---

## 1. Stack

| Componentă | Alegere | Motiv |
|---|---|---|
| Limbaj | PHP 8.2+ (plain, fără framework) | Rulează pe orice shared hosting cPanel |
| Bază de date | MySQL 8.0+ / MariaDB 10.6+ (InnoDB, utf8mb4) | Standard pe shared hosting |
| Acces DB | PDO, prepared statements native (`EMULATE_PREPARES = false`) | Zero SQL injection |
| UI | Bootstrap 5.3 + Bootstrap Icons + Chart.js 4 — **găzduite local** în `public/assets/vendor` | CSP strict `script-src 'self'`, fără CDN |
| Procese grele | Coadă de joburi în MySQL (`jobs`) + cron web automat (ca WP-Cron, fără cPanel) + AJAX batching | Fără daemon-uri, fără configurare pe server |
| Coding standard | WordPress Coding Standards (`phpcs.xml.dist`) | Cerință proiect |

## 2. Structura proiectului

```
optivio/
├── .htaccess                 # Rescrie tot traficul în public/ (doc root alternativ)
├── public/                   # SINGURUL director expus web (doc root recomandat)
│   ├── index.php             # Front controller
│   ├── .htaccess             # Rewrite + headere securitate
│   └── assets/               # css/, js/, img/, vendor/ (Bootstrap, Icons, Chart.js)
├── app/
│   ├── bootstrap.php         # Autoloader, config, erori, sesiune, headere
│   ├── helpers.php           # esc_html(), esc_attr(), esc_url(), sanitize_*(), ov_url() ...
│   ├── routes.php            # Toate rutele (metodă, cale, handler, rol)
│   ├── core/                 # Config, Database, Request, Response, Router, Session, Csrf, Migrator,
│   │                         # Crypto (AES-256-GCM), View, Http_Exception, Job_Queue, Validator, Zip_Stream
│   ├── security/             # Auth, Gate (RBAC + anti-IDOR), Rate_Limiter, Audit_Log, Password_Policy
│   ├── repositories/         # Acces date (SQL doar aici): Users, Businesses, Settings, Audit, ...
│   ├── services/             # Logică deterministă (Dashboard_Metrics, Readiness_Score, Usp_Builder, Ai_Config, Repuvio_Connector...)
│   ├── google/               # OAuth, client, sincronizări și mock pentru API-urile Google
│   ├── engine/               # Motorul determinist: audit SEO, insight-uri, cuvinte cheie, strategie Ads, concurență
│   ├── ai/                   # Client OpenRouter on-demand (gardă, buget, cost), snapshot determinist, prompturi, mock
│   ├── actions/              # Action Cards: validare, generator determinist, analiză profundă, aplicare / rollback
│   ├── onboarding/           # Wizard: descoperire pagini, extragere deterministă, percepția AI, diferențe percepție/realitate
│   ├── controllers/          # Controllere HTTP subțiri
│   └── views/                # Template-uri PHP (late escaping obligatoriu)
├── bin/                      # Scripturi CLI: install, cron, generate-key, seed-demo, dev-router
├── config/                   # config.php (NU se versionează) + config.sample.php
├── storage/                  # logs/, sessions/, uploads/, cache/, backups/ — în afara webroot, .htaccess deny
├── plugin/optivio-connector/ # Sursa pluginului WordPress OptiVio Connector (împachetat per site din OptiVio)
├── database/upgrades/        # Upgrade-uri aplicate AUTOMAT la primul request după deploy (1.1.0.sql, ...)
├── schema.sql                # Schema COMPLETĂ (rescrisă integral la fiecare modificare)
├── CLAUDE.md                 # Ghid pentru asistenți AI / dezvoltatori
├── RULES.md                  # Reguli obligatorii (securitate, standarde, AI on-demand)
└── phpcs.xml.dist            # Configurare WordPress Coding Standards
```

## 3. Arhitectură

### 3.1 Flux request
```
Apache/LiteSpeed → public/index.php → app/bootstrap.php
  → Migrator::ensure() (aplică automat database/upgrades/*.sql după un deploy; gratuit după prima verificare)
  → Web_Cron::register() (după răspuns: tick de cron dacă e cazul)
  → Session::start() (cookie HttpOnly/Secure/SameSite=Lax, save_path privat)
  → Security headers (CSP, XFO, nosniff, Referrer-Policy, HSTS pe HTTPS)
  → Router::dispatch()
       ├─ potrivire rută + parametri numerici ({id})
       ├─ middleware: auth → rol (super_admin) → CSRF (+ verificare Origin) pe POST
       └─ Controller → Gate::authorize_business($id) (anti-IDOR) → Repository (PDO) → View
```

### 3.2 Securitate (rezumat — detalii în RULES.md)
- **Parole**: `PASSWORD_ARGON2ID` (fallback `BCRYPT`), rehash automat la login, politică de lungime minimă.
- **Sesiuni**: `session_regenerate_id(true)` la login, timeout de inactivitate + timeout absolut, `session_version` per utilizator (schimbarea parolei/dezactivarea invalidează instant toate sesiunile), fișiere de sesiune în `storage/sessions`.
- **Brute-force**: `Rate_Limiter` în MySQL pe IP, pe email și pe IP+email; jurnal în `login_attempts`; verificare `password_verify` și pentru emailuri inexistente (timing constant).
- **RBAC**: `super_admin` (tot) / `operator` (doar afacerile din `user_business_access`). Orice ID de afacere/site este verificat pe backend prin `Gate`; accesul refuzat returnează **404** (nu dezvăluie existența resursei).
- **CSRF**: token sincronizat per sesiune pe toate formularele + header `X-CSRF-Token` pe AJAX + verificare `Origin`/`Referer`.
- **XSS**: sanitizare la intrare, `esc_*()` la afișare, CSP fără `unsafe-inline` pentru scripturi.
- **Secrete**: AES-256-GCM cu sub-chei derivate HKDF din `master_key` (config), cu AAD legat de contextul secretului (un ciphertext mutat în alt câmp nu se decriptează).
- **Plugin WP** (Etapa 4): HMAC-SHA256 peste `method|path|timestamp|nonce|sha256(body)`, fereastră ±300s, nonce stocat în `connector_nonces` (anti-replay), secret unic per site.
- **Audit**: `audit_logs` pentru autentificare, utilizatori, acces, setări, afaceri, aplicări/rollback.

### 3.3 Principiul „Deterministic First, AI On-Demand"
- Cron, webhook-uri plugin, sincronizări Google: **zero apeluri AI**. Clientul OpenRouter (`Ai\Ai_Client::assert_on_demand()`) refuză execuția din CLI, din coada de joburi, pe căile mașină, pe cereri non-POST sau fără utilizatorul autentificat al sesiunii.
- Action Cards deterministe se generează gratuit din insight-uri și audit (`Actions\Card_Generator`, la fiecare rulare a motorului); AI-ul (analiza profundă, completarea textelor unui card) rulează doar la butonul utilizatorului, cu confirmare de cost și buget lunar.
- Toate calculele (spam scoring, audit SEO, canibalizare, link map, corelare Ads ↔ apeluri) sunt SQL + PHP.

### 3.4 Coada de joburi și cron-ul web (fără cPanel)
`jobs` (MySQL) procesată de `Services\Cron_Runner`, declanșat **automat**, ca WP-Cron: după fiecare request (pagini, AJAX, heartbeat-urile și evenimentele pluginurilor), `Services\Web_Cron` revendică atomic un tick (cel mult unul pe interval, implicit 1 minut) și trimite un POST loopback non-blocant la `/cron/spawn`, autentificat cu un secret generat automat și stocat criptat. Dacă gazda blochează loopback-ul, tick-ul rulează după răspuns (`fastcgi_finish_request`) sau din browserul unui utilizator autentificat (`/cron/tick`). Nu există cron cPanel și nici token în `config.php`; `bin/cron.php` rămâne doar pentru dezvoltare. Rezervare atomică prin `UPDATE ... WHERE status='pending' ... LIMIT 1` cu token unic, buget de timp per rulare, `cursor_state` pentru procesare incrementală, retry cu backoff, deduplicare prin `unique_key`.

## 4. Instalare

### 4.1 Local (Laragon)
1. Copiați `config/config.sample.php` în `config/config.php`.
2. Generați cheile: `php bin/generate-key.php` și copiați valorile în `config.php`.
3. Creați baza de date (goală). La primul request, `Migrator` importă automat `schema.sql`; manual:
   ```bash
   mysql -uroot -e "CREATE DATABASE optivio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -uroot optivio < schema.sql
   ```
4. Creați Super Admin: `php bin/install.php --email=... --name="..."` (parola se citește interactiv) **sau** deschideți `/setup` în browser și introduceți `install_token` din config (funcționează doar cât timp nu există niciun utilizator).
5. Opțional, date demo: `php bin/seed-demo.php` (ștergere: `php bin/seed-demo.php --purge`).
6. Pornire:
   - **Server PHP integrat** (recomandat pentru dezvoltare): `php -S 127.0.0.1:8090 -t public bin/dev-router.php` cu `app_url = http://127.0.0.1:8090`.
   - **Laragon** (configurația curentă): Menu → Apache → Reload creează vhost-ul `optivio.test` (cu `AllowOverride All`); setați `app_url = http://optivio.test`. Atenție: `http://localhost/optivio` nu procesează `.htaccess` în configurația Laragon implicită.
7. `app_url` trebuie să corespundă exact originii din browser (verificarea CSRF compară `Origin`).

### 4.2 Producție (cPanel, `optivio.ahoi.ro`)
1. Încărcați proiectul în afara `public_html` (ex. `/home/user/optivio`).
2. Setați **Document Root** al subdomeniului pe `/home/user/optivio/public`. (Dacă nu se poate, rădăcina conține `.htaccess` care rescrie totul în `public/` și blochează restul.)
3. Creați baza de date din cPanel (o bază goală se instalează automat din `schema.sql` la primul request; alternativ importați `schema.sql` din phpMyAdmin).
4. Configurați `config/config.php` (`app_env = production`, `app_url = https://optivio.ahoi.ro`, `force_https = true`).
5. **Nu este nevoie de Cron Job în cPanel**: joburile rulează prin cron-ul web automat (secțiunea 3.4).
6. Permisiuni: `storage/` scriibil de PHP (755/775), `config/config.php` 640.

### 4.3 Actualizare (deploy din GitHub)
1. Încărcați fișierele noi peste cele existente (fără `config/config.php` și fără `storage/`).
2. Deschideți aplicația. La primul request, `Core\Migrator` compară `OPTIVIO_SCHEMA_VERSION` cu `schema_migrations` și aplică în ordine `database/upgrades/<versiune>.sql` lipsă, sub `GET_LOCK` (un singur request migrează, celelalte așteaptă). Instrucțiunile deja aplicate (coloană / cheie / tabel existent) sunt sărite, deci un upgrade întrerupt se reia. Un fișier marker `storage/cache/schema-<versiune>.ok` face verificarea gratuită ulterior.
3. Setările noi au valori implicite (`Settings_Repository::DEFAULTS`); secretele noi se generează automat. Nu se modifică `config.php` și nu se rulează niciun script.

## 5. Plan de implementare pe etape

| Etapă | Conținut | Stare |
|---|---|---|
| **1. Nucleu** | Bootstrap, DB, Router, sesiuni, CSRF, criptare, autentificare, rate-limit, RBAC Super Admin/Operator, audit log, utilizatori, afaceri (bază), layout monocrom, dashboard, setări globale (AI, chei, securitate, cron), coadă joburi + cron runner, notificări, căutare | ✅ Implementat |
| **2. Afaceri & site-uri** | Profil complet afacere în tab-uri (profil + public țintă, contacte, servicii/produse, USP calculat determinist + editabil, reguli permanente), site-uri multiple cu cheie conector HMAC (generare / rotire / revocare, secret afișat o singură dată), integrări per site (GSC, GA4, PSI, GTM, Ads, AdSense, GBP) cu validare de format, cheie OpenRouter + override modele per client, conector RepuVio rezervat, verificator semnătură `Connector_Signature` | ✅ Implementat |
| **3. Atribuire, apeluri, formulare** | API conector semnat HMAC (`/api/connector/v1/heartbeat`, `/events`, stateless, rate-limit), atribuire canal deterministă, `phone_clicks`, `form_entries` (PII criptat) cu scor anti-spam determinist + override manual, modul Apeluri & venituri (manual, import CSV, corelare automată cu click-urile tel:, analiză pe cuvinte cheie / ore / canale, recomandări Ad Scheduling), setări anti-spam, alertă conector inactiv, simulator CLI | ✅ Implementat |
| **4. OptiVio Connector (WP)** | Plugin în `plugin/optivio-connector/`, `.zip` generat per site cu cheia inclusă (ZipArchive nu e necesar), cookie first-party de atribuire, tracking `tel:` (sendBeacon → REST WP → coadă persistentă), Gravity Forms (honeypot, timp minim, cuvinte blocate) cu raportarea tuturor intrărilor, oglindirea conținutului + Yoast (sincronizare completă și incrementală) cu metrici on-page deterministe, API remote semnat OptiVio → WP (SEO Yoast, titlu/conținut, articole noi, categorii, imagini) cu snapshot automat și rollback 1-click, editor SEO în OptiVio, istoric modificări | ✅ Implementat |
| **5. Integrări Google** | OAuth 2.0 cu PKCE (mai multe conturi Google, refresh token criptat, revocare), descoperirea proprietăților, asociere cont + proprietate per site, joburi incrementale reluabile: GSC (dată × interogare × pagină), GA4 (dată × pagină × sursă/mediu), PageSpeed, GTM (snapshot versiune live), Google Ads (campanii, cuvinte cheie, negative, orar, termeni de căutare), AdSense, Business Profile; pagină „Date Google” per site; mod mock pentru development | ✅ Implementat |
| **6. Motor determinist** | Audit SEO (22 de reguli on-page, tehnice, PageSpeed, GTM) cu rezolvare automată, canibalizare, „aproape de top 3”, CTR mic la poziție bună, plătim deși suntem top organic, termeni care convertesc în Ads fără pagină SEO, termeni plătiți fără rezultat, cuvinte cu apeluri refuzate, ore proaste (Ad Schedule), cercetare cuvinte cheie (GSC + Ads + servicii × zonă + SERP) cu intenție și prioritate pe model de afacere, consilier strategie Ads, concurență (Serper + manual, scanner PHP anti-SSRF, comparație termeni/H2/lungime/schema) | ✅ Implementat |
| **7. AI on-demand & Action Cards** | Client OpenRouter (3 profiluri), analiză profundă multi-sursă, Action Cards (aprobare/editare/respingere → regulă permanentă), prompturi imagini + upload, aplicare prin plugin, 1-click rollback | ✅ Implementat |
| **8. Onboarding & percepție** | Wizard în 7 pași (Super Admin): descoperire pagini (robots, sitemap, meniu) și citire în fundal (max. 50/site, 100/analiză), percepția AI din exterior (profil `perception`, Gemini Flash recomandat) salvată imuabil ca amprentă inițială, confirmare câmp cu câmp, diferențe percepție vs realitate + scor de claritate + Action Cards deterministe, plugin și Google în wizard, tab „Percepție” (re-analiză din exterior, percepție din interior per site după plugin), ștergere definitivă a afacerilor | ✅ Implementat |
| **9. Operare fără efort** | Cron web automat fără cPanel, migrări automate ale bazei de date la deploy, „Testează conexiunea” pentru fiecare cheie/API, backup Google Drive (bază de date + fișiere, programat, ultimele 1–30 de copii, copie locală opțională, descărcare SQL) | ✅ Implementat |

## 5b. Pluginul OptiVio Connector
1. OptiVio → Afacere → Site-uri → site WordPress → **Descarcă pluginul (.zip)** (conține `connection.php` cu URL-ul OptiVio, Key ID și secretul site-ului).
2. WordPress → Plugins → Add New → Upload → activare. Pluginul face imediat heartbeat-ul și pornește sincronizarea completă a conținutului.
3. WordPress → Settings → OptiVio Connector: stare, „Testează conexiunea", „Sincronizare completă", „Trimite coada acum", suprascriere conexiune (după rotirea secretului).
4. În OptiVio: pagina site-ului → **Testează conexiunea OptiVio → WordPress**, **Conținut & SEO** (metrici on-page, editor Yoast cu „Aplică pe site") și **Istoric modificări** (rollback).
5. Pluginul folosește WP-Cron-ul standard (heartbeat orar, coadă la 5 minute, sincronizare zilnică) — nu se configurează nimic în hosting. Cererile lui declanșează și cron-ul web al OptiVio.

## 5c. Configurare Google
1. Google Cloud Console → proiect → activați API-urile: Search Console, Google Analytics Data + Admin, PageSpeed Insights, Tag Manager, Google Ads, AdSense Management, Business Profile Performance + Account Management + Business Information.
2. OAuth consent screen (Internal sau External cu utilizatori de test) + credențiale „OAuth client ID” de tip Web application, cu URI de redirecționare: `https://optivio.ahoi.ro/oauth/google/callback`.
3. OptiVio → Setări → Integrări: Client ID + Client Secret; pentru Ads: developer token (+ login customer ID dacă folosiți un cont MCC) și versiunea API (implicit `v21`, actualizați la versiunea curentă suportată de Google).
4. Integrări Google → „Conectează cont Google” (Super Admin). Proprietățile accesibile se listează automat.
5. Pagina site-ului → fiecare integrare: alegeți contul și proprietatea (sugestii din listă) → „Sincronizează”. Apoi sincronizarea rulează zilnic prin cron (PageSpeed și GTM săptămânal). Prima sincronizare GSC aduce istoricul configurat (implicit 90 de zile).
6. Development: `'google_mock' => true` în config (doar cu `app_env = development`) servește răspunsuri simulate pentru toate API-urile.

## 5d. AI & Action Cards
1. Setări → AI: cheia OpenRouter globală (sau per client în tab-ul „AI & conectori”), modelele celor 4 profiluri (rapid, raționament, copywriting, percepție), bugetul lunar, „Confirmare înainte de fiecare apel” și limitele de onboarding.
2. **Analize AI** → „Pregătește o analiză nouă”: se afișează modelul, dimensiunea snapshot-ului determinist (fără date personale), costul maxim estimat și bugetul; după confirmare, modelul „Raționament” întoarce un diagnostic + Action Cards. Cardurile invalide (categorie necunoscută, conținut/site al altei afaceri, câmpuri lipsă) sunt eliminate.
3. **Action Cards**: fiecare card are justificare, propunere editabilă și decizie: *Aprobă și aplică pe site* (SEO meta, conținut, link intern, articol/landing nou — prin pluginul conectat, cu snapshot), *Marchează ca aplicat manual* (Google Ads, Business Profile, tehnic) sau *Respinge* (motivul devine regulă permanentă a afacerii, transmisă tuturor analizelor viitoare). Cardurile aplicate pe site au *Rollback 1-click*.
4. Articole / landing-uri noi: pentru fiecare imagine planificată se afișează promptul (pentru un generator de imagini) și ALT-ul; imaginea încărcată (JPEG/PNG/WebP ≤ 8 MB) se urcă în Media Library la aplicare (imagine reprezentativă sau în conținut). Pagina se creează ca ciornă sau publicată, după alegere.
5. Development: `'ai_mock' => true` în config (doar cu `app_env = development`) simulează răspunsurile OpenRouter.

## 5e. Ajutor și ghiduri de integrare
- Meniul **Suport → Ajutor** (`/help`, toți utilizatorii autentificați): configurarea inițială, wizard-ul de onboarding, percepție vs realitate, instalarea pluginului, integrările Google, accesul operatorilor, fluxul zilnic/săptămânal, backup / cron / actualizări, descrierea fiecărui meniu, ghidurile de integrare și probleme frecvente.
- Butoanele „Cum configurez?” / „?” din Setări → Integrări & chei API, Setări → AI, tab-ul AI & conectori al afacerii și cardul Integrări Google al site-ului deschid un pop-up cu linkul direct către consola furnizorului și pașii de activare.

## 5f. Onboarding (wizard) și percepția AI
1. **Afaceri → Afacere nouă (wizard)** (`/onboarding`, Super Admin): domeniile clientului → `Onboarding\Page_Discovery` (robots.txt, sitemap / sitemap index / wp-sitemap, meniul paginii principale; aceeași gazdă) → selecție (implicit fără pagini legale, max. 5 articole) → citire în fundal (`onboarding.fetch` + AJAX, `Safe_Fetcher`, `Page_Extractor`: text vizibil pe linii, titluri, telefoane, emailuri, CUI/Reg. Com., prețuri, program, JSON-LD).
2. **Percepția AI** (`Onboarding\Perception`): header/footer comun trimis o singură dată, pagini prioritizate și trunchiate la `onboarding.max_input_chars`, un apel `Ai_Client::complete_json()` profil `perception`, răspuns validat câmp cu câmp, salvat imuabil în `business_perceptions` (cu faptele deterministe).
3. **Confirmare**: afacerea se creează doar aici (site-uri cu cheie conector, servicii, decizii în `businesses.onboarding_state`); `Perception_Gaps` calculează diferențele, scorul de claritate și cardurile (`hash_key = gap:{câmp}:{Text::key}`).
4. Pașii 4–7: USP/reguli/contact, plugin per site (stare live), Google, rezumat + `Engine_Runner::run()`.
5. Tab **Percepție**: istoric, comparație cu amprenta inițială, „Percepție din interior” per site (doar după plugin + conținut sincronizat), „Re-analizează din exterior”.
6. **Ștergere definitivă** (Super Admin): `/businesses/{id}/delete` cu sumarul datelor, confirmarea numelui și a parolei; `Services\Business_Deleter` (cascade FK, joburi, notificări, fișiere upload; costurile AI și auditul rămân).

## 5g. Backup Google Drive, teste de conexiune
- **Administrare → Backup Google Drive** (`/backup`): conectare Drive cu scope `drive.file` prin clientul OAuth existent și același redirect URI; bază de date (`.sql.gz`, export PHP pur) și fișiere (`.zip`, „Recomandat” = uploads + config sau „Complet”), periodicitate (zilnic, de 2 ori/săptămână, săptămânal, lunar) la ora aleasă, păstrare ultimele 1/2/3/5/10/15/21/30 per tip, copie locală opțională în `storage/backups`, descărcare SQL în browser, jurnal în tabelul `backups`. Rulează ca job `backup.run` prin cron-ul web.
- **Setări → Integrări → Testează conexiunea** (`Services\Connection_Tester`): OpenRouter (starea cheii, fără apel de model), Serper (1 credit), PageSpeed, client Google OAuth + conturile conectate, developer token Ads, Google Drive.

## 6. Comenzi utile
```bash
php bin/generate-key.php        # master_key + install_token
php bin/install.php --email=admin@exemplu.ro --name="Admin"
php bin/cron.php                # rulează coada manual (doar dezvoltare; în producție cron-ul e automat)
php bin/seed-demo.php           # date demo pentru dashboard
php bin/connector-simulate.php --site=ID --replay   # trimite evenimente semnate ca pluginul (doar development)
vendor/bin/phpcs                # verificare WPCS (dacă phpcs este instalat global/composer)
```
