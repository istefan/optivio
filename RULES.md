# OptiVio — Reguli obligatorii

Aceste reguli sunt **non-negociabile**. Orice modificare de cod care le încalcă este respinsă.

## R1. Documentație și schemă
1. La **orice** modificare de cod se analizează și se actualizează, dacă e cazul, `README.md`, `CLAUDE.md` și `RULES.md`.
2. La **orice** modificare de structură a bazei de date, `schema.sql` se **rescrie integral** și se incrementează versiunea din `schema_migrations` și `OPTIVIO_SCHEMA_VERSION`. Pentru instalările existente se adaugă **în plus** `database/upgrades/<versiune>.sql` (ALTER-uri idempotente ca efect, care inserează versiunea în `schema_migrations`). Schema completă rămâne sursa de adevăr.
3. Când se furnizează cod, fișierele se rescriu **integral**, fără omisiuni („...rest unchanged" este interzis).
4. Fiecare fișier PHP (aplicație și plugin) începe cu un docblock care conține:
   `@author Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>`.
   Această mențiune **nu apare niciodată în UI** (view-uri, emailuri, răspunsuri JSON, headere HTTP).
5. **Deploy fără script**: upgrade-urile se aplică automat de `Core\Migrator` la primul request după încărcarea fișierelor. Fișierele de upgrade conțin doar instrucțiuni SQL simple separate prin `;` (fără proceduri/DELIMITER), care pot fi reluate (`CREATE TABLE IF NOT EXISTS`, `MODIFY`; erorile „există deja” sunt tolerate). **Interzis** să se ceară modificări în `config.php` sau rularea manuală a unui script la actualizare: setările noi au valori implicite în `Settings_Repository::DEFAULTS`, secretele noi se generează automat și se stochează criptat.

## R2. Stack și găzduire
1. Plain PHP 8.2+, MySQL. **Fără framework-uri PHP** și fără dependențe Composer obligatorii la runtime.
2. Compatibil shared hosting (cPanel, Apache/LiteSpeed): fără daemon-uri, websockets, Redis, Node la runtime.
3. Procesele lungi rulează **exclusiv** prin coada `jobs` + cron / AJAX batching, cu buget de timp per rulare (< 50s).
4. Asset-urile front-end sunt găzduite local (`public/assets/vendor`). Fără CDN-uri la runtime.
5. **Fără cron în cPanel**: coada este declanșată exclusiv de cron-ul web (`Services\Web_Cron`, ca WP-Cron) — loopback POST `/cron/spawn` cu secret generat automat, cu rezervă `fastcgi_finish_request` și `/cron/tick` din browser. Nicio funcționalitate nouă nu are voie să ceară configurarea unui Cron Job pe server.

## R3. WordPress Coding Standards
1. Indentare cu tab-uri, spații în paranteze `foo( $bar )`, `array()` (nu `[]`), Yoda conditions (`if ( 'x' === $y )`).
2. Funcții, metode, variabile: `snake_case`. Clase: `Title_Case_With_Underscores`. Fișiere clase: `class-nume-clasa.php`.
3. Comparații stricte (`===`, `!==`, `in_array( ..., true )`).
4. Docblock pentru fiecare clasă/metodă/funcție.
5. **Sanitizare la intrare** (`sanitize_text_field()`, `absint()`, `sanitize_email()`, validatori) și **late escaping la ieșire** (`esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`) — nicio ieșire neescapată în view-uri.
6. Verificare cu `phpcs` (configurație `phpcs.xml.dist`).

## R4. Securitate
1. **SQL**: exclusiv PDO cu prepared statements native. Interzis concatenarea valorilor în SQL. Numele de tabele/coloane dinamice trec prin whitelist (`Database::quote_identifier()`).
2. **CSRF**: toate cererile care modifică starea (POST) trec prin verificarea token-ului (câmp `_ov_csrf` sau header `X-CSRF-Token`) și a `Origin`/`Referer`. GET nu modifică niciodată starea.
3. **Autorizare / anti-IDOR**: orice controller care primește un ID de afacere/site/resursă **trebuie** să apeleze `Gate::authorize_business()` / `Gate::authorize_site()` (sau `Business_Scoped_Controller::business()`) înainte de orice citire/scriere. Înregistrările copil (contacte, servicii, reguli etc.) se citesc/modifică **întotdeauna** cu `WHERE id = ? AND business_id = ?` — un ID de copil dintr-o altă afacere returnează 404 chiar și pentru Super Admin. Repository-urile de listare primesc filtrul din `Gate::accessible_business_ids()` / `Gate::scope_business_ids()`. Accesul refuzat → 404. Modificările cer permisiunea `manage`.
4. **Parole**: `password_hash()` cu `PASSWORD_ARGON2ID` (fallback `PASSWORD_BCRYPT`), niciodată logate, niciodată returnate.
5. **Sesiuni**: regenerare ID la login și la schimbarea privilegiilor, cookie `HttpOnly` + `Secure` (HTTPS) + `SameSite=Lax`, timeout inactivitate și absolut, `session_version`.
6. **Secrete**: chei API, tokeni OAuth, secrete conector, PII din formulare → **numai criptat** cu `Crypto::encrypt( $plain, $context )` (AES-256-GCM). În UI se afișează doar mascat (`••••abcd`). Secretele nu intră în `audit_logs.meta`, loguri sau răspunsuri JSON.
7. **Brute-force**: login protejat de `Rate_Limiter` (IP, email, IP+email). Mesaj de eroare generic.
8. **Audit**: acțiunile sensibile (auth, utilizatori, acces, setări, secrete, aplicări la distanță, rollback) se jurnalizează prin `Audit_Log::record()`.
9. **Upload-uri**: stocate în `storage/uploads` (în afara webroot), nume aleator, validare MIME reală (`finfo`), limită de dimensiune, servite doar prin controller cu verificare de acces.
10. **Comunicarea cu pluginul WP**: HMAC-SHA256 + timestamp ±300s + nonce unic (anti-replay) + secret unic per site, implementat exclusiv prin `OptiVio\Security\Connector_Signature`. Secretul se afișează în clar **o singură dată** (după generare/rotire), apoi există doar criptat; rotirea șterge nonce-urile vechi; revocarea invalidează imediat cheia. Nonce-ul se salvează doar după validarea semnăturii.
11. **Erori**: în producție nu se afișează niciodată stack trace; erorile se loghează în `storage/logs`.
12. **Headere**: CSP (`script-src 'self'`), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS pe HTTPS.
13. **Fără inline JavaScript** în view-uri. Datele pentru JS se transmit prin `data-*` sau `<script type="application/json">` codificat cu `ov_json_for_html()`.
14. **API-uri mașină** (`/api/connector/*`, `/cron/spawn`): fără sesiune și fără cookie (`App::is_machine_path()`), autentificare exclusiv prin semnătură/token, rate-limit pe IP (eșecuri) și pe cheie, corp limitat la 512 KB, max. 100 evenimente/cerere, răspunsuri JSON fără secrete. Datele primite se sanitizează câmp cu câmp; IP-ul și User-Agent-ul vizitatorilor se stochează doar ca HMAC, numerele apelanților doar ca HMAC + ultimele 3 cifre, câmpurile formularelor doar criptat (context `form_entries:fields:{site_id}`).
15. **Upload-uri CSV**: max. 2 MB, extensie .csv/.txt, MIME real verificat cu `finfo`, procesare din fișierul temporar (nu se păstrează pe disc).
16. **Modificări la distanță pe site-uri**: se execută **doar** la acțiunea explicită a unui utilizator cu drept `manage` pe afacere (formular POST + CSRF), exclusiv prin `Remote_Operations::execute()`. Fiecare operație: rând în `remote_operations`, semnătură HMAC, snapshot creat de plugin înainte de modificare, audit `remote.*`. Endpoint-ul plugin-ului este acceptat numai dacă e http(s) pe domeniul site-ului (`Remote_Operations::url_allowed()`, anti-SSRF), fără redirect-uri. Conținutul HTML trimis trece prin KSES în WordPress (operațiile rulează fără utilizator autentificat).
17. **Pluginul WordPress**: respectă WPCS; fiecare fișier PHP începe cu `defined( 'ABSPATH' ) || exit;`; acțiunile din admin cer `manage_options` + nonce; endpoint-ul public `/optivio/v1/track` validează UUID/telefon/pagina proprie și are rate-limit per IP; endpoint-ul `/optivio/v1/remote` acceptă numai cereri semnate (cheie, timestamp ±300s, nonce unic, HMAC). Secretul nu se afișează niciodată în admin.
18. **Integrări Google**: conectarea/deconectarea conturilor Google și asocierea cont + proprietate pentru integrările OAuth (GSC, GA4, GTM, Ads, AdSense, GBP) sunt rezervate Super Admin (un cont de agenție vede proprietățile tuturor clienților); operatorii cu `manage` pot doar porni sincronizarea și configura PageSpeed. Tokenurile (refresh + access) se stochează numai criptat; OAuth folosește `state` de unică folosință cu expirare + PKCE S256. `Google_Http` contactează exclusiv gazde Google prin HTTPS, fără redirect-uri; `google_mock` funcționează numai în development. Sincronizările sunt deterministe, reluabile (o pagină API per rulare) și nu apelează niciodată AI.
19. **Coada de joburi**: cheile `unique` includ perioada (zi, săptămână, oră); un job finalizat în aceeași perioadă nu se mai repetă, iar unul eșuat poate fi reprogramat.
20. **Scanare pagini externe**: orice descărcare de pagini terțe (concurență, onboarding) trece exclusiv prin `Engine\Safe_Fetcher` — doar http/https pe porturi standard, fără credențiale în URL, DNS rezolvat o dată și toate IP-urile publice (fără private/loopback/link-local/rezervate), conexiune fixată pe IP-ul validat (anti DNS rebinding), redirect-uri revalidate (max. 3), HTML (sau XML/text doar pentru sitemap/robots), max. 2 MB, timeout 15 s, max. 5 pagini/rulare de cron. Opțiunea `allow_local` funcționează numai în `development`. Onboarding-ul citește doar gazda site-ului introdus și respectă `Disallow` din robots.txt, cu excepția bifării explicite „Ignoră robots.txt”.
22. **Ștergerea definitivă a unei afaceri**: doar Super Admin, cu confirmarea numelui și re-autentificare prin parolă, exclusiv prin `Services\Business_Deleter` (cascade FK + joburi + notificări + fișiere upload). Jurnalul de audit și costurile AI (`ai_requests`, fără `business_id`) se păstrează.
23. **Backup**: arhivele (bază de date + `config/`) conțin date personale și cheia de criptare — se încarcă doar în Google Drive (scope `drive.file`, tokenuri criptate) și, opțional, în `storage/backups` (în afara webroot, `.htaccess` deny, doar ultima copie). Paginile de backup, descărcarea SQL și ștergerea sunt doar pentru Super Admin, cu audit.
24. **Teste de conexiune**: `Services\Connection_Tester` folosește cel mai ieftin apel care dovedește validitatea (OpenRouter: starea cheii, fără model), rulează doar la click-ul Super Adminului și nu returnează niciodată secretul.
21. **Motorul de analiză**: toate regulile sunt deterministe și documentate (praguri în cod, explicate în UI); insight-urile se identifică prin hash (`insight_hash`), respingerile utilizatorului (`dismissed_at`) se păstrează la recalculare, iar ce nu mai e valabil se șterge / se marchează rezolvat. Paginile de strategie lucrează doar pe afacerea activă (autorizată prin `Gate`).

## R5. Deterministic First, AI On-Demand
1. OpenRouter se apelează **doar** ca urmare a unei acțiuni explicite a unui utilizator autentificat în UI (buton „Generează analiză" etc.).
2. **Interzis** orice apel AI din: cron, joburi de fundal, webhook-uri/ingest plugin, sincronizări Google.
3. Filtrarea spam, auditul SEO, canibalizarea, harta de linkuri, extragerea structurii concurenților, corelațiile Ads ↔ apeluri ↔ venituri sunt **100% deterministe** (PHP + SQL).
4. Fiecare apel AI se jurnalizează în `ai_requests` (model, tokeni, cost, utilizator).
5. Modelele implicite ale celor 4 profiluri (rapid / raționament / copywriting / percepție) sunt editabile global și per client.
6. Orice apel trece prin `Ai\Ai_Client::complete_json()`, care apelează întâi `assert_on_demand()` (refuz în CLI, în `Job_Queue::$running`, pe căile mașină, pe cereri non-POST și dacă utilizatorul nu este cel al sesiunii) și `preflight()` (cheie disponibilă, buget lunar, cost estimat). Dacă `ai.confirm_before_call` este activ, formularul cere bifa de confirmare.
7. Snapshot-ul trimis la AI (`Snapshot_Builder`) conține doar agregate deterministe — **fără** date personale (formulare, telefoane, IP-uri, nume de apelanți) și fără secrete; se salvează în `analyses.input_snapshot`.
8. Răspunsul AI este **nesigur**: fiecare card se validează strict (`Actions\Cards::validate()` / `payload()` — categorie cunoscută, lungimi, ID-uri de conținut/site ale aceleiași afaceri, HTML curățat de script/style/iframe/handler-e `on*`/`javascript:`); ieșirea markdown se afișează doar prin `ov_markdown()` (escape întâi).
9. Nicio propunere AI nu se aplică automat: aplicarea pe site cere butonul explicit pe fiecare card (drept `manage`), trece prin `Remote_Operations` (snapshot + rollback). Respingerea cere motiv (≥ 5 caractere), care devine regulă permanentă (`business_rules` source `rejection`) inclusă în analizele viitoare.
10. Imaginile încărcate pentru carduri: JPEG/PNG/WebP ≤ 8 MB, MIME verificat cu `finfo` + `getimagesize`, nume aleator în `storage/uploads/` (în afara webroot), servite doar prin `GET /uploads/{id}` după autorizarea afacerii.
11. **Percepția AI (onboarding)**: un singur apel pe analiză, la butonul utilizatorului (wizard: Super Admin; percepția din interior: drept `manage`), cu preflight și confirmare de cost. Textul site-urilor este tratat ca **date, nu instrucțiuni** (promptul o spune explicit); răspunsul se validează câmp cu câmp (`Onboarding\Perception::validate()`, URL-urile sursă doar din paginile trimise), se salvează **imuabil** în `business_perceptions` și nu se aplică nimic fără confirmarea utilizatorului. Citirea paginilor (`onboarding.fetch`) și calculul diferențelor (`Perception_Gaps`) sunt deterministe.

## R6. Date și confidențialitate
1. Stocare în UTC; afișare în fusul orar al aplicației/afacerii.
2. Nu stocăm IP-urile vizitatorilor site-urilor clienților — doar HMAC.
3. Datele personale din formulare se criptează; pentru deduplicare se folosește `contact_hash` (HMAC).

## R7. UI
1. Interfața este în limba română, monocromă (alb, `#f4f6f8`, `#111827`, griuri neutre); culorile semantice doar pentru badge-uri de variație și alerte critice.
2. Layout: sidebar fix stânga (offcanvas pe mobil), header cu titlu/subtitlu, căutare, notificări, selector afacere; conținut bento (8–9 coloane + 3–4 coloane).
3. Carduri cu `border-radius` 12–16px, borduri fine, umbre extrem de subtile.
4. Autorul aplicației nu apare în UI.
5. Orice funcționalitate, meniu sau integrare nouă se documentează în pagina **Ajutor** (`app/views/help/index.php`) și, pentru integrări externe, în `Services\Integration_Guides` (pop-up „Cum configurez?”). Pop-up-urile folosesc modalele Bootstrap, fără JavaScript inline.
