# CLAUDE.md — Ghid pentru asistenți AI și dezvoltatori

Acest fișier descrie **cum se lucrează** în codul OptiVio. Citiți-l împreună cu `RULES.md` (reguli obligatorii) și `README.md` (arhitectură, instalare, plan pe etape).

> Autor aplicație: Stefan Iftimie, founder Ahoi Digital SRL — https://www.ahoi.ro/ (doar în docblock-uri și documentație, **niciodată în UI**).

## 0. Obligații la fiecare modificare
1. Actualizați `README.md`, `CLAUDE.md`, `RULES.md` dacă modificarea le afectează.
2. Orice schimbare de structură DB ⇒ **rescrieți integral** `schema.sql` + incrementați versiunea din `INSERT INTO schema_migrations` + `OPTIVIO_SCHEMA_VERSION` din `app/bootstrap.php` + adăugați `database/upgrades/<versiune>.sql` (se aplică automat la deploy prin `Core\Migrator`; doar SQL simplu, reluabil).
3. Fișierele modificate se furnizează integral.
4. Fiecare fișier PHP nou începe cu docblock-ul standard (vezi orice fișier existent, `@author` inclus).
5. Rulați `php -l` pe fișierele atinse și, dacă e disponibil, `phpcs` (config `phpcs.xml.dist`).

## 1. Stare curentă
- **Etapa 1 — implementată**: nucleu, securitate, autentificare, RBAC Super Admin/Operator, audit, utilizatori, afaceri (profil de bază), layout monocrom, dashboard, setări globale, coadă de joburi + cron, notificări, căutare, conversii.
- **Etapa 2 — implementată**: pagina afacerii pe tab-uri (`partials/business-tabs`): Prezentare, Profil, Contacte, Servicii & produse, USP, Site-uri, Reguli, AI & conectori; `/sites` (listă globală) și `/sites/{id}` (conector + integrări Google); `Connector_Signature` (HMAC) gata pentru endpoint-urile din Etapa 3. Etapa 2 nu a modificat structura DB.
- **Etapa 3 — implementată** (schema 1.1.0): API conector (`Connector_Api_Controller`), `Event_Ingestor`, `Attribution`, `Spam_Scorer`, `Call_Matcher`, `Calls_Analytics`, `Call_Csv_Importer`; pagini `/calls`, `/calls/import`, `/calls/{id}/edit`, `/forms/{id}`; tab „Spam filtrat" în Conversii; tab „Anti-spam & conector" în Setări; joburi `connector.health` și `calls.match`.
- **Etapa 4 — implementată** (schema 1.2.0): plugin `plugin/optivio-connector/` (module `OVC_*`), `Plugin_Packager` + `Zip_Writer`, endpoint `POST /api/connector/v1/content`, `Content_Ingestor`, `Remote_Operations` (OptiVio → WP), `Site_Connector_Controller` (`/sites/{id}/plugin|ping|resync|content|content/{content_id}|operations|operations/{op_id}/rollback`).
- **Etapa 5 — implementată** (fără modificări de schemă): namespace `OptiVio\Google` în `app/google/` (`Google_Oauth`, `Google_Client`, `Google_Http`, `Google_Mock`, `Google_Discovery`, `Google_Sync`), `Google_Controller` (`/google`, `/google/connect`, `/oauth/google/callback`, `/google/accounts/{id}`, `/sites/{id}/integrations/{provider}/sync`, `/sites/{id}/google`), job `google.sync`, `Database::upsert_many()`.
- **Etapa 6 — implementată** (schema 1.3.0): namespace `OptiVio\Engine` în `app/engine/` (`Text`, `Insight_Store`, `Seo_Auditor`, `Search_Insights`, `Keyword_Research`, `Ads_Strategy`, `Safe_Fetcher`, `Competitors`, `Engine_Runner`), `Strategy_Controller` (`/seo-audit`, `/keywords`, `/ads`, `/competitors`, `/competitors/compare`, `/engine/run`, `/insights/{id}/dismiss`), joburi `engine.run` (zilnic) și `competitors.scan` (săptămânal).
- **Etapa 7 — implementată** (schema 1.4.0): namespace `OptiVio\Ai` în `app/ai/` (`Ai_Client`, `Ai_Pricing`, `Ai_Mock`, `Snapshot_Builder`, `Prompts`) și `OptiVio\Actions` în `app/actions/` (`Cards`, `Card_Generator`, `Analysis_Service`, `Card_Applier`); `Analyses_Controller` (`/analyses`, `/analyses/new`, `/analyses/{id}`), `Actions_Controller` (`/actions`, `/actions/{id}` + `apply|manual|reject|rollback|generate|images|images/{image_id}`, `/uploads/{id}`); plugin 1.1.0 (`update_post` acceptă `fields.append`).
- Toate cele 7 etape din planul inițial sunt implementate.
- **Etapa 8 — Onboarding & percepție** (schema 1.5.0): namespace `OptiVio\Onboarding` în `app/onboarding/` (`Page_Discovery`, `Page_Extractor`, `Drafts`, `Perception`, `Perception_Gaps`); `Onboarding_Controller` (`/onboarding`, `/onboarding/{id}/pages|fetch|analyze|confirm|commercial|plugin|status|google|advance/{step}|summary|finish|delete`, `/businesses/{id}/perception/external`), `Perception_Controller` (`/businesses/{id}/perception`, `/businesses/{id}/perception/internal/{site_id}`), tab „Percepție”, job `onboarding.fetch`, profil AI `perception`; ștergere definitivă `Services\Business_Deleter` (`/businesses/{id}/delete`).
- **Etapa 9 — Operare fără efort** (schema 1.6.0): `Core\Migrator` (upgrade-uri automate la primul request), `Services\Web_Cron` + `/cron/spawn` și `/cron/tick` (cron fără cPanel; `/cron/run` și `cron_token` au fost eliminate), `Services\Connection_Tester` + `POST /settings/test/{provider}` și `ov_test_button()`, backup `Services\Backup_Service` + `Backup_Drive` + `Core\Zip_Stream`, `Backup_Controller` (`/backup…`), job `backup.run`, tabel `backups`.
- `schema.sql` = versiunea 1.6.0.
- **Ajutor & ghiduri** (fără modificări de schemă): `Help_Controller` (`/help`, `app/views/help/index.php`), catalogul ghidurilor de integrare `Services\Integration_Guides` (cheie → titlu, iconiță, rezumat, linkuri, pași, note; `{redirect_uri}` se înlocuiește automat). În view-uri: `ov_guide_button( 'cheie', 'Etichetă' )` afișează butonul, iar layout-ul tipărește o singură dată modalele folosite (`partials/guide-modals`, corpul în `partials/guide-body`). Integrare nouă ⇒ adăugați ghidul în `Integration_Guides::all()`; pas nou în flux ⇒ actualizați `/help`.

## 2. Hartă cod
| Zonă | Fișier / director | Rol |
|---|---|---|
| Intrare HTTP | `public/index.php` → `App::run_http()` (`app/core/class-app.php`) | Headere, sesiune, router, tratare erori |
| Bootstrap | `app/bootstrap.php` | Autoloader, config, erori, UTC |
| Rute | `app/routes.php` | Toate rutele + opțiuni (`auth`, `guest`, `role`, `csrf`) |
| Router | `app/core/class-router.php` | Potrivire, middleware CSRF → guest → auth → parolă forțată → rol |
| DB | `app/core/class-database.php` | `get_row/get_results/get_var/get_col/insert/update/delete/transaction`, `in_placeholders()`, `quote_identifier()` |
| Criptare | `app/core/class-crypto.php` | `encrypt( $plain, $context )`, `decrypt()`, `keyed_hash( $value, $purpose )` |
| Sesiune / CSRF | `class-session.php`, `class-csrf.php` | Flash, old input, token, Origin check |
| View | `app/core/class-view.php` | `render( $tpl, $data, $layout )`, `partial()`; template-urile primesc `$view` |
| Joburi | `app/core/class-job-queue.php`, `app/services/class-job-handlers.php`, `class-cron-runner.php` | Coadă MySQL, handler-e, rulare cu lock `GET_LOCK` |
| Cron web | `app/services/class-web-cron.php`, `class-cron-controller.php` | `register()` la fiecare request → `on_shutdown()` → `claim()` atomic pe `cron.web_last_spawn` → `spawn()` loopback `/cron/spawn` (header `X-OptiVio-Cron`, secret `cron.web_token` auto-generat); rezerve `fastcgi_finish_request` / `/cron/tick` |
| Migrări | `app/core/class-migrator.php` | `ensure()` (marker `storage/cache/schema-<v>.ok`), `pending()`, `statements()`, instalare `schema.sql` pe bază goală |
| Onboarding | `app/onboarding/` | `Page_Discovery` (robots, sitemap, meniu, clasificare, preselecție), `Page_Extractor` (text pe linii + fapte), `Drafts` (ciorne, pagini, `process()` cu claim atomic), `Perception` (buget text, prompt, `validate()`, imuabil), `Perception_Gaps` (diferențe, scor claritate, carduri `gap:*`) |
| Backup | `app/services/class-backup-service.php`, `class-backup-drive.php`, `app/core/class-zip-stream.php` | Export SQL gzip PHP pur, ZIP streaming, programare `last_slot/next_slot`, rotație `keep`; Drive `drive.file`, upload resumable 8 MB |
| Teste conexiune | `app/services/class-connection-tester.php` | `test( $provider )` → `{ok, message, ms}`; provider nou ⇒ `PROVIDERS` + metodă |
| Auth | `app/security/class-auth.php` | Login, validare sesiune (idle/absolut/UA/session_version) |
| RBAC | `app/security/class-gate.php` | `is_super_admin()`, `accessible_business_ids()`, `authorize_business()`, `authorize_site()`, `scope_business_ids()` |
| Rate limit | `app/security/class-rate-limiter.php` | `blocked_for()`, `hit()`, `clear()` |
| Audit | `app/security/class-audit-log.php` | `record( $action, $entity_type, $entity_id, $meta, $business_id )` |
| Repository-uri | `app/repositories/` | SQL pentru entități (Users, Businesses, Business_Children, Sites, Settings, Credentials, Ai_Profiles, Audit, Notifications) |
| Servicii | `app/services/` | Logică deterministă (Dashboard_Metrics, Readiness_Score, Usp_Builder, Ai_Config, Repuvio_Connector, Layout_Context, Cron_Runner) |
| Semnătură conector | `app/security/class-connector-signature.php` | `sign()`, `headers()` (OptiVio → plugin), `verify()` (plugin → OptiVio, cu anti-replay) |
| API conector | `app/controllers/class-connector-api-controller.php` | `POST /api/connector/v1/heartbeat`, `POST /api/connector/v1/events` (stateless) |
| Ingest & analiză | `app/services/class-event-ingestor.php`, `class-attribution.php`, `class-spam-scorer.php`, `class-call-matcher.php`, `class-calls-analytics.php`, `class-call-csv-importer.php` | Totul determinist (zero AI) |
| AI (on-demand) | `app/ai/` | `Ai_Client::assert_on_demand()`, `preflight()` (cheie, buget, cost estimat), `complete_json()` (jurnal `ai_requests`); `Snapshot_Builder::build()` (agregate fără PII); `Prompts`; `Ai_Mock` (dev); `Ai_Pricing` (prețuri OpenRouter, cache 24 h) |
| Action Cards | `app/actions/` | `Cards` (categorii, validare strictă payload, creare deduplicată prin `card_hash`), `Card_Generator` (carduri deterministe din insight-uri + audit, în `Engine_Runner`), `Analysis_Service` (analiză profundă, completare card), `Card_Applier` (aplicare prin `Remote_Operations`, manual, respingere → regulă, rollback) |
| Controllere | `app/controllers/` | Extind `Controller` (`render()`, `redirect_with()`, `back_with_errors()`, `text()`, `textarea()`); paginile de sub `/businesses/{id}/…` extind `Business_Scoped_Controller` (`business( 'view' / 'manage' )`, `render_tab()`, `decimal()`, `checkbox()`) |
| UI | `app/views/`, `public/assets/css/optivio.css`, `public/assets/js/optivio.js` | Bootstrap 5.3 local + design monocrom |
| CLI | `bin/` | `generate-key.php` (master_key + install_token), `install.php`, `cron.php` (doar dezvoltare), `seed-demo.php`, `dev-router.php`; `cli-bootstrap.php` rulează și `Migrator::ensure()` |

## 3. Convenții de nume (WPCS)
- Namespace `OptiVio\<Zona>` ↔ director `app/<zona>/`; clasa `Rate_Limiter` ↔ fișier `class-rate-limiter.php`.
- Variabile locale în template-uri: prefix `$ov_` (evită coliziuni, cerință WPCS pentru globale).
- Funcții globale proprii: prefix `ov_` (`ov_url()`, `ov_asset()`, `ov_csrf_field()`, `ov_value()`, `ov_invalid()`, `ov_field_error()`...). Funcțiile `esc_*` / `sanitize_*` / `absint` sunt replici ale API-ului WordPress, ca sniff-urile WPCS să le recunoască.
- `array()` long syntax, Yoda, tab-uri, spații în paranteze.

## 4. Rețete

### 4.1 Rută + pagină nouă
1. Adăugați ruta în `app/routes.php` (implicit autentificată; `$admin` pentru Super Admin).
2. Creați `app/controllers/class-x-controller.php` extinzând `Controller`.
3. În controller: sanitizați input-ul (`$this->text()`, `absint()`, `sanitize_key()`...), validați cu `Validator`, **autorizați** cu `Gate` înainte de orice citire.
4. `$this->render( 'x/index', array( 'page_title' => ..., 'page_subtitle' => ... ) )`.
5. Template în `app/views/x/index.php`: **escape la fiecare ieșire**.
6. Adăugați intrarea în meniu în `app/views/partials/sidebar.php`.

### 4.2 Resursă legată de o afacere (anti-IDOR)
```php
$id = $this->request->param_int( 'id' );
Gate::authorize_business( $id, 'manage' ); // 404 dacă nu are acces
```
Pentru site-uri: `$business_id = Gate::authorize_site( $site_id, 'view' );`
Pentru listări: transmiteți `Gate::accessible_business_ids()` (null = toate) sau `Gate::scope_business_ids()` (ține cont de afacerea activă din selector) în repository.

### 4.2b Colecție nouă sub o afacere
- Tabel cu `business_id` + FK `ON DELETE CASCADE`.
- Definiția în `Business_Children_Repository::TABLES` (coloane scriibile + ordonare) și în `Business_Children_Controller::COLLECTIONS`, plus ramura de validare din `collect()`.
- Rutele se generează în bucla din `app/routes.php` (`index`, `store`, `update`, `delete`, `toggle`).
- View: `app/views/businesses/<colectie>.php` care include `partials/business-tabs`.

### 4.3 Secret nou (cheie API, token)
- Global/per client/per site → `api_credentials` prin `Credentials_Repository` (`save_global()` / `save_for_business()`; context AAD = `api_credentials:{scope}:{provider}:{business}:{site}`).
- Secret conector site → `Sites_Repository::rotate_connector_secret()` (context AAD `sites:connector_secret:{site_id}`); citire server-side doar prin `find_by_connector_key()`.
- Configurația AI efectivă (global + override client + sursa cheii) → `Ai_Config::effective( $business_id )`.
- Setare de aplicație secretă → `Settings_Repository::set_secret()` + adăugare în `SECRET_KEYS`.
- În UI: doar `ov_mask_secret( $hint )`. Nu returnați niciodată secretul în HTML/JSON.

### 4.4 Job de fundal
1. Handler static în `Job_Handlers::map()` — semnătură `( array $job, Job_Queue $queue )`; returnează `null` (gata) sau un array `cursor_state` (mai sunt batch-uri; include `progress` 0–99).
2. Programare: `Job_Queue::dispatch( 'tip', $payload, array( 'unique' => '...', 'business_id' => ... ) )`.
3. Recurent: adăugați în `Job_Handlers::schedule_recurring()` cu `unique` pe interval. Coada este pornită de cron-ul web (fără cPanel); pentru a porni imediat un job lansat de utilizator: `register_shutdown_function( array( Web_Cron::class, 'spawn' ) )`.
4. **Interzis**: apeluri AI în handler-e (RULES R5).

### 4.5 AJAX
- GET pentru citire (`Response::json()`), POST pentru modificări cu header `X-CSRF-Token` (meta `csrf-token` din layout) și `X-Requested-With: XMLHttpRequest`.
- Codul JS stă în `public/assets/js/optivio.js`; datele vin prin `<script type="application/json">` + `ov_json_for_html()`.

## 5. Model de date (rezumat)
- Securitate: `users`, `user_business_access` (view/manage), `login_attempts`, `rate_limits`, `password_resets`, `audit_logs`, `notifications`.
- Config: `settings` (JSON / criptat), `api_credentials` (AES-256-GCM).
- Afaceri: `businesses` (+ RepuVio rezervat), `business_contacts`, `business_offerings`, `business_rules` (reguli din respingeri), `business_ai_profiles`.
- Site-uri: `sites` (secret HMAC conector criptat), `site_integrations` (gsc, ga4, psi, gtm, ads, adsense, gbp), `connector_nonces`.
- Evenimente: `phone_clicks`, `form_entries` (PII criptat, spam scoring), `calls` + `call_import_batches`.
- Conținut WP: `wp_content`, `wp_content_bodies`, `wp_seo_meta`, `internal_links`, `wp_images`, `seo_audit_issues`.
- Google: `gsc_daily`, `ga4_daily`, `psi_audits`, `gtm_snapshots`, `ads_campaigns`, `ads_keywords`, `ads_hourly`, `ads_search_terms`, `adsense_daily`, `gbp_daily`.
- Strategie: `keywords`, `keyword_insights`, `serp_queries`, `competitors`, `competitor_pages`.
- AI & execuție: `ai_requests`, `analyses`, `action_cards`, `action_card_images`, `uploads`, `remote_operations`.
- Onboarding & percepție: `onboarding_drafts`, `onboarding_pages`, `business_perceptions` (imuabil, `is_baseline`), `perception_gaps`; `businesses.onboarding_state` (pas, amânări, decizii).
- Infrastructură: `jobs`, `cron_runs` (`trigger_source` web/ajax/cli), `schema_migrations`, `backups`.

## 6. Dezvoltare locală
- Server PHP integrat: `php -S 127.0.0.1:8090 -t public bin/dev-router.php` (config: `app_url = http://127.0.0.1:8090`). Configurația este și în `.claude/launch.json` (`optivio`).
- Laragon (configurația activă): după „Reload" Apache se creează vhost-ul `optivio.test` (cu `AllowOverride All`); setați `app_url = http://optivio.test`. `http://localhost/optivio` NU funcționează local deoarece vhost-ul implicit are `AllowOverride None`.
- Conturi de test: `storage/dev-credentials.txt` (neversionat). Date demo: `php bin/seed-demo.php` / `--purge`.
- Sesiunea este legată de User-Agent: schimbarea UA (ex. emulare mobil în browser) deconectează utilizatorul — comportament intenționat.
- Cron-ul web funcționează și local (loopback la `app_url`); `php bin/cron.php` procesează coada manual. Onboarding-ul poate citi site-ul WordPress de test (`127.0.0.1:8091`) doar în `development` (`Safe_Fetcher` opțiunea `allow_local`).
- Testarea migrărilor: o bază cu schema veche + `Migrator::ensure()` (vezi `database/upgrades`); markerul `storage/cache/schema-<versiune>.ok` se șterge pentru a forța verificarea.

## 6b. Contract API conector (v1)
- Autentificare: headere `X-OptiVio-Key`, `X-OptiVio-Timestamp` (unix), `X-OptiVio-Nonce` (32 hex), `X-OptiVio-Signature` = HMAC-SHA256(secret, `METHOD\nPATH\nTIMESTAMP\nNONCE\nsha256(body)`). `PATH` = calea relativă la `app_url` (ex. `/api/connector/v1/events`).
- `heartbeat` body: `{plugin_version, wp_version, has_yoast, has_gravity_forms}` → răspuns `{ok, server_time, config:{spam:{min_fill_seconds, blocked_words}, max_events_per_request}}`.
- `events` body: `{events:[...]}` (max 100). Tipuri:
  - `phone_click`: `uuid` (v4), `occurred_at` (ISO 8601/unix, max 30 zile în trecut), `phone`, `page_url`, `user_agent`, `device`, `ip`, `attribution{gclid,gbraid,wbraid,utm_source,utm_medium,utm_campaign,utm_term,utm_content,referrer,landing_page}`.
  - `form_entry`: `entry_id`, `form_id`, `form_title`, `occurred_at`, `fields{eticheta: valoare}` (max 50), `page_url`, `user_agent`, `device`, `ip`, `attribution{...}`, `flags{honeypot, fill_seconds, blocked_patterns}`.
  - Răspuns: `{ok, stored, duplicates, invalid, results:[{index, status, error?, spam_status?}]}`. Retrimiterea aceluiași eveniment este idempotentă (`duplicate`).
- Coduri: 401 semnătură invalidă/replay/cheie revocată · 409 site în pauză/arhivat · 413 corp/evenimente prea multe · 422 fără evenimente · 429 rate limit.
- Testare: `php bin/connector-simulate.php --site=ID [--clicks=N --forms=N --spam=N --replay]`.

## 6a. Contract API remote (OptiVio → WordPress)
- URL: raportat de plugin la heartbeat (`remote_url` = `rest_url( 'optivio/v1/remote' )`, ex. `https://site.ro/wp-json/optivio/v1/remote` sau `/index.php?rest_route=/optivio/v1/remote`), salvat în `sites.connector_remote_url` doar dacă trece `url_allowed()`.
- Semnătură: aceleași headere `X-OptiVio-*`; `PATH` canonic = path + `?query` al URL-ului remote. Pluginul păstrează nonce-ul în transient 600 s.
- Body: `{operation, params}`. Operații: `ping`, `update_seo_meta {object_type, id, taxonomy?, fields{seo_title, meta_description, focus_keyword, canonical, noindex}}`, `update_post {id, fields{title, content, excerpt, status, slug}, seo?}`, `create_post {post_type, fields, seo?, featured_media?, categories?}` (implicit draft), `update_term {taxonomy, id, fields{name, description, slug}}`, `upload_media {filename, data_base64, alt, title}` (JPEG/PNG/WebP ≤ 8 MB), `rollback {snapshot_id}`, `resync {all | object_type,id,taxonomy}`.
- Răspuns: `{ok, operation, snapshot_id, object_id, object}` — `object` (serializat ca la sincronizare) actualizează imediat oglinda din OptiVio.
- Snapshot-urile sunt în tabelul WP `{prefix}ovc_snapshots`; rollback-ul unei creări = trash (post) / ștergere (atașament).

## 6c. Reguli deterministe Etapa 3
- **Canal**: gclid/gbraid/wbraid sau cpc+google → `google_ads`; gbp/gmb/maps → `gbp`; email/newsletter → `email`; social (utm sau referrer) → `social`; `organic` sau referrer motor de căutare → `organic`; alt utm → `other`; referrer extern → `referral`; altfel `direct`.
- **Spam**: puncte cumulate (vezi `Spam_Scorer` / Setări → Anti-spam), praguri configurabile `suspect`/`spam`; override manual `marked_clean`/`marked_spam` are prioritate în toate statisticile.
- **Corelare apel ↔ click tel:**: cel mai apropiat click neasociat al aceleiași afaceri în fereastra −30 min / +2 min; apelul moștenește site, canal și cuvânt cheie dacă nu le are.
- **Recomandări**: cuvânt cheie sau oră cu ≥ 3 apeluri și ≥ 60% refuzate/pierdute → candidat pentru cuvânt negativ / excludere din Ad Scheduling.

## 6d. Google (Etapa 5)
- Tokenuri: `api_credentials` provider `google_oauth`, `secret_cipher` = JSON criptat `{refresh_token, access_token, expires_at}` (context `Google_Client::context()`), `meta` = `{scopes, connected_at, properties{provider:[{id,label}]}, errors, discovered_at}`.
- `Google_Client::request()` reîmprospătează automat tokenul, reîncearcă o dată la 401, marchează contul `invalid` la `invalid_grant` și aruncă `RuntimeException` cu mesaj sigur pentru `site_integrations.last_error`.
- `Google_Sync::handle()` = un singur apel API per rulare, întoarce `cursor_state` până termină. Interval: backfill la prima rulare (GSC configurabil, restul 90 zile), apoi fereastră scurtă de reîmprospătare (GSC 5, GA4 3, Ads 7, AdSense 3, GBP 7 zile) până ieri, în fusul orar al aplicației.
- Ads: GAQL prin `googleAds:search` cu `pageToken`; faze campaigns → keywords (șterge și reîncarcă) → negatives → hourly → terms. Versiunea API e setare (`integrations.ads_api_version`).
- Programare: `Google_Sync::schedule()` din cron (zilnic; PSI și GTM săptămânal), deduplicat prin `unique`.
- Mock: `Google_Mock::respond()` acoperă toate endpoint-urile folosite; pentru un endpoint nou adăugați ruta în mock.

## 6e. Motorul determinist (Etapa 6)
- `Engine_Runner::run( $business_id )` = `Seo_Auditor::run()` per site + `Search_Insights::gsc()` + `Search_Insights::ads()` + `Insight_Store::prune()` + `Keyword_Research::run()`; rulează în < 1 s pe datele demo.
- Potrivirea dintre GSC, Ads, apeluri și cuvinte cheie se face pe `Text::key()` (tokeni fără diacritice și stopwords, stemming ușor, sortați).
- Praguri: canibalizare ≥ 2 pagini cu ≥ 10% din afișările a ≥ 30 de afișări; „aproape de top 3” poziție 4–20 și ≥ 50 de afișări; CTR mic: poziție ≤ 5, ≥ 100 de afișări, CTR < 50% din curba așteptată (28/15/10/7/5%); top organic = poziție ≤ 3 cu click-uri; termen irosit = 0 conversii și cost ≥ 50 sau ≥ 3× CPC mediu; ore/cuvinte cu apeluri: ≥ 3 apeluri, ≥ 60% refuzate/pierdute.
- Prioritate cuvânt cheie = pondere intenție (per model de afacere) × (log afișări·2 + log click-uri·3 + conversii Ads·5 + apeluri − 2·refuzate + 5 dacă poziția e 4–20 + 3 pentru combinații servicii × zonă).
- Insight nou: adăugați tipul în `keyword_insights.insight_type` (schemă + upgrade), în `Insight_Store::LABELS` și calculați-l în `Search_Insights` cu `Insight_Store::put()`.
- Regulă de audit nouă: `Seo_Auditor::ISSUES` (cod → severitate, etichetă, recomandare) + detectarea în `run()`.

## 7. USP determinist (`Usp_Builder`)
Normalizează textul (fără diacritice), caută „piloni" prin regex (non-stop, timp de intervenție, preț fix, garanție, experiență, autorizări, deplasare gratuită, livrare, retur, plată, specializare), îi ordonează după importanța pentru modelul de afacere (`MODEL_PILLARS`) și compune `{serviciul principal} în {zonă}: {top 3 piloni}.` Returnează și pilonii lipsă + procentul de acoperire. Piloni noi: în `PILLARS` și, dacă e cazul, în `MODEL_PILLARS`.

## 7b. Oglindirea conținutului (Etapa 4)
- Pluginul trimite pe canalul `content` (max 20 obiecte/cerere) posturi/pagini/CPT publice și termeni ai taxonomiilor publice: HTML randat (`the_content`, max 300 KB), Yoast brut + rezolvat (`wpseo_replace_vars` / `YoastSEO()->meta`), imagine reprezentativă; ștergerile ca `{deleted:true}`.
- `Content_Ingestor::analyze()` (DOMDocument): cuvinte, H1, outline headings, imagini + ALT lipsă, linkuri interne (aceeași gazdă, fără www). `normalize_url()` păstrează query-ul identificator (`?p=`, `?page_id=`) și elimină parametrii de tracking. După fiecare lot se recalculează `target_content_id` și `internal_links_in`.
- Testare locală: WordPress de test în scratchpad (`php -S 127.0.0.1:8091`), driver `wptest-tools/drive.php` (setup / sync / track / state). În `development`, site-urile pot avea IP ca domeniu.

## 8. AI on-demand & Action Cards (Etapa 7)
- **Gardă**: orice apel AI trece prin `Ai_Client::complete_json( $user_id, $business_id, $profile, $purpose, $system, $user, $context )`; acesta cheamă `assert_on_demand()` (excepție în CLI, `Job_Queue::$running`, căi mașină, non-POST, utilizator ≠ sesiune) și `preflight()`. Nu apelați niciodată OpenRouter direct.
- **Profiluri**: `fast`, `reasoning` (analiza profundă), `copy` (texte card), `perception` (onboarding; implicit 32 000 tokeni de ieșire, minim forțat `Ai_Config::PERCEPTION_MIN_TOKENS` = 16 000); model + cheie din `Ai_Config::effective()`.
- **Răspuns tăiat**: `complete_json()` citește `finish_reason`; un JSON tăiat la limita de tokeni este recuperat cu `Ai_Client::repair_json()` (se păstrează valorile complete, rezultatul are `truncated = true`), iar eșecurile se loghează cu începutul și finalul răspunsului (`AI answer not usable`).
- **Categorii** (`Cards::CATEGORIES`, cheie → [etichetă, se aplică pe site]): `seo_meta`, `seo_content`, `internal_link`, `new_article`, `new_landing` (remote); `ads_negative`, `ads_schedule`, `ads_campaign`, `ads_new_campaign`, `gbp`, `technical`, `other` (manual).
- **Payload** (validat în `Cards::payload()`): `seo_meta {site_id, content_id, seo_title, meta_description, focus_keyword}` · `seo_content {site_id, content_id, content_html}` · `internal_link {site_id, source_content_id, target_content_id, anchor_text, paragraph_html cu {{link}}}` · `new_article|new_landing {site_id, post_type, title, slug, seo_title, meta_description, focus_keyword, content_html cu {{image:featured|inline_N}}, images[{placement, prompt, alt}]}` · `ads_negative {keywords[], match_type, scope, campaign}` · `ads_schedule {hours[], adjustment_pct, days}` · `ads_campaign|ads_new_campaign {campaign, changes[]}` · restul `{steps[]}`.
- **Flux**: `proposed` → editare (`edited_payload`) → `applied` (remote: operații `remote_operations.action_card_id`; manual: `applied_note`) / `failed` / `rejected` (motiv → `business_rules` source `rejection`) → `rolled_back`. Cardurile închise nu mai pot fi modificate.
- **Card nou determinist**: adăugați regula în `Card_Generator` cu o cheie stabilă `hash_key` (deduplicare `UNIQUE(business_id, card_hash)`). **Categorie nouă**: `Cards::CATEGORIES` + ramura din `Cards::payload()` + `Card_Applier` (dacă e remote) + formularul din `app/views/actions/show.php` + `Prompts::CARD_CONTRACT` + `Ai_Mock`.
- **Imagini**: slot `action_card_images` (prompt + ALT) → upload `uploads` (`storage/uploads/`, servit prin `/uploads/{id}`) → la aplicare `upload_media`; markerul `{{image:…}}` lipsă ⇒ imaginea inline se adaugă la final.
- **Testare locală**: `'ai_mock' => true` (doar `development`); pentru purpose nou adăugați răspunsul în `Ai_Mock`.

## 9. Onboarding & percepție (Etapa 8)
- **Flux**: ciornă (`onboarding_drafts`, fără afacere) → pagini (`onboarding_pages`, max. `onboarding.max_pages_per_site` / `_per_run`) → `Drafts::process()` (job `onboarding.fetch` + AJAX `POST /onboarding/{id}/fetch`) → `Perception::run()` (AI, profil `perception`, doar POST explicit) → confirmare (afacerea se creează în tranzacție, percepția devine `is_baseline`, `ai_requests.business_id` se leagă) → `Perception_Gaps::compute()` + `cards()`.
- **Decizii** în `businesses.onboarding_state.decisions`: `fields` (verdict `ok|corrected|na` pentru business_model, summary, target_audience), `rejected_services`, `rejected_areas`, `pillars` (cheile `Usp_Builder::PILLARS` confirmate). Re-analizele (externe sau interne) folosesc aceleași decizii.
- **Tipuri de diferență**: `confirmed`, `corrected`, `missing_on_site`, `hallucinated`, `unclear`, `not_applicable`; scor de claritate ponderat (`Perception_Gaps::WEIGHTS`).
- **Diferență / card nou**: adăugați câmpul în `Perception_Gaps::FIELDS`, calculul în `compute()` (cu `item_key = Text::key()`), cardul în `card_for()` (`hash_key gap:{field}:{item_key}`), eticheta în Ajutor.
- **Percepție din interior**: `Perception::pages_from_site()` (din `wp_content` + `wp_content_bodies` + `wp_seo_meta`), disponibilă doar dacă site-ul are `connector_status = connected` și conținut sincronizat.
- **Mock**: `Ai_Mock::perception()` derivă răspunsul din textul paginilor (pentru purpose `perception_*`).
- **Pasul Google (Site Kit)**: `Google\Google_Discovery` salvează pentru fiecare proprietate și `uris` (fluxuri web GA4, `domainName` GTM, site-uri AdSense, `websiteUri` GBP); `Google\Google_Matcher::suggest()` dă scorul (GSC domeniu 100 / URL 95 / domeniu părinte 80, URI 90, nume 60/40; Ads singurul cont 30, niciodată automat; conectare automată ≥ `AUTO_SCORE` 60). Rute: `/onboarding/{id}/google/discover`, `/google/{site_id}/auto`, `/google/{site_id}/{provider}` (valoare `credential_id|property_id`, verificată cu `Google_Matcher::find()`). `/google/connect` acceptă `return` (doar `/onboarding/{id}/…` sau `/sites/{id}/…`).

## 10. Operare: cron web, migrări, backup, teste de conexiune
- **Cron web**: nu adăugați instrucțiuni de tip „configurați un cron în cPanel”. Intervalul (`cron.interval_minutes`, 1–15) și bugetul (`cron.batch_seconds`) sunt setări. Starea loopback-ului: `cron.web_loopback`.
- **Upgrade nou**: `database/upgrades/X.Y.Z.sql` doar cu instrucțiuni separate prin `;`, fără `DELIMITER`; erorile 1050/1060/1061/1062/1022/1091/1826 sunt tolerate (reluare sigură). La final inserați versiunea în `schema_migrations` (migratorul o inserează oricum cu `INSERT IGNORE`).
- **Setare nouă**: valoare implicită în `Settings_Repository::DEFAULTS` (niciodată cheie nouă obligatorie în `config.php`); secret nou → `SECRET_KEYS` + generare automată la prima utilizare.
- **Backup**: setări `backup.*` (enabled, include_db, include_files, files_mode, frequency, hour, keep ∈ {1,2,3,5,10,15,21,30}, keep_local, folder_name, drive_folder_id, last_slot); tokenurile Drive în secretul `backup.drive_tokens`. Conectarea Drive folosește `Google_Controller::start_oauth( 'drive' )` (scope `drive.file`, același redirect URI, `state.purpose = drive`). Mock: `Google_Mock` răspunde la `/drive/v3/files…`, iar `Backup_Drive::upload()` întoarce un ID `mock-…`.
- **Test de conexiune nou**: `Connection_Tester::PROVIDERS` + metoda (cel mai ieftin apel, mesaj fără secrete) + `ov_test_button( 'cheie' )` în view.

