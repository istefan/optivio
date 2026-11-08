=== OptiVio Connector ===
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 1.1.0
License: Proprietary

Conectează site-ul la platforma OptiVio.

== Description ==
* Atribuirea sursei de trafic în cookie first-party (gclid, gbraid, wbraid, utm_*, landing page, referrer).
* Monitorizarea click-urilor pe linkurile tel: (număr, pagină, sursă, cuvânt cheie, dispozitiv, oră).
* Integrare Gravity Forms: honeypot, timp minim de completare, cuvinte blocate; toate intrările sunt raportate.
* Oglindirea conținutului (pagini, articole, CPT, categorii) și a setărilor Yoast SEO.
* Aplicarea de la distanță a modificărilor aprobate în OptiVio, cu snapshot automat și rollback.

Comunicarea este autentificată cu HMAC-SHA256 + timestamp + nonce (anti-replay), cu secret unic per site.
Nu se stochează IP-uri în clar în OptiVio (doar HMAC).

== Installation ==
1. Descărcați arhiva din OptiVio (Site → OptiVio Connector → Descarcă pluginul). Arhiva conține deja cheia site-ului.
2. Plugins → Add New → Upload Plugin → activați.
3. Settings → OptiVio Connector → „Testează conexiunea".
