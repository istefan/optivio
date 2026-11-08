<?php
/**
 * OptiVio — Prompt templates (Romanian) and the strict JSON contracts expected back.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Ai;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Prompts.
 */
final class Prompts {

	/**
	 * Card payload contract shared by prompts and the validator.
	 */
	public const CARD_CONTRACT = <<<'TXT'
Fiecare card: {"category": "...", "title": "max 120 caractere", "rationale": "de ce, cu cifrele din date", "priority": 1-9 (1 = cel mai urgent), "impact": 1-10, "site_id": număr|null, "payload": {...}}
Categorii și payload:
- "seo_meta": {"content_id": id din content, "seo_title": "30-60 caractere", "meta_description": "70-160 caractere", "focus_keyword": "..."}
- "seo_content": {"content_id": id, "content_html": "conținut complet nou al paginii, HTML simplu (h2, h3, p, ul, li, strong, a)"}
- "internal_link": {"source_content_id": id, "target_content_id": id, "anchor_text": "...", "paragraph_html": "<p>propoziție naturală care conține linkul {{link}}</p>"}
- "new_article" / "new_landing": {"site_id": id, "post_type": "post"|"page", "title": "...", "slug": "...", "seo_title": "...", "meta_description": "...", "focus_keyword": "...", "content_html": "HTML; poți folosi {{image:featured}} sau {{image:inline_1}} unde vrei imagini", "images": [{"placement": "featured"|"inline_1"|..., "prompt": "prompt detaliat în engleză pentru un generator de imagini (subiect, compoziție, lumină, stil fotografic realist, fără text)", "alt": "text ALT în română"}]}
- "ads_negative": {"keywords": ["..."], "match_type": "phrase"|"exact"|"broad", "scope": "campaign"|"account", "campaign": "nume sau null"}
- "ads_schedule": {"hours": [0-23], "adjustment_pct": -100..0, "days": "toate"|"lucrătoare"|"weekend"}
- "ads_campaign" / "ads_new_campaign": {"campaign": "...", "changes": ["pas concret 1", "pas concret 2"]}
- "gbp" / "technical" / "other": {"steps": ["pas concret 1", "..."]}
TXT;

	/**
	 * System prompt for the deep analysis.
	 *
	 * @return string
	 */
	public static function deep_system(): string {
		return "Ești un strateg senior de marketing digital (SEO, Google Ads, Google Business Profile, CRO) pentru IMM-uri din România.\n"
			. "Primești un snapshot JSON determinist al unei afaceri (profil, USP, servicii, reguli permanente, conversii, apeluri acceptate/refuzate și venituri, Search Console, GA4, PageSpeed, Google Ads, Business Profile, conținutul site-ului, audit SEO, insight-uri calculate, cuvinte cheie, concurență, carduri existente).\n"
			. "Reguli obligatorii:\n"
			. "1. Respectă ÎNTOTDEAUNA „permanent_rules” — nu propune nimic ce le încalcă.\n"
			. "2. Nu repeta cardurile din „existing_cards” și nu repropune ce a fost respins (vezi rejection_reason).\n"
			. "3. Folosește doar datele primite; nu inventa cifre. Citează cifrele relevante în rationale.\n"
			. "4. Ține cont de modelul de afacere și de USP (ex. pentru intervenții de urgență, campaniile pe click-uri sunt de preferat celor pe conversii; apelurile refuzate indică cuvinte/ore de exclus).\n"
			. "5. content_id și site_id trebuie să existe în snapshot. Textele pentru site sunt în limba română, cu diacritice.\n"
			. "6. Maximum 15 carduri, ordonate după impact.\n\n"
			. self::CARD_CONTRACT . "\n\n"
			. 'Răspunde EXCLUSIV cu un obiect JSON: {"summary_markdown": "analiză de 300-700 de cuvinte în markdown: diagnostic, oportunități, riscuri, plan pe 30 de zile", "cards": [ ... ]}';
	}

	/**
	 * System prompt for generating / completing the payload of one card.
	 *
	 * @return string
	 */
	public static function card_system(): string {
		return "Ești copywriter SEO senior pentru IMM-uri din România. Scrii texte clare, persuasive, cu diacritice, orientate spre conversie (apel / formular), respectând USP-ul afacerii și regulile permanente.\n"
			. "Primești contextul afacerii și un card de acțiune cu payload incomplet. Completează payload-ul pentru categoria cardului. Păstrează marcajele {{image:...}} pentru imaginile planificate.\n\n"
			. self::CARD_CONTRACT . "\n\n"
			. 'Răspunde EXCLUSIV cu un obiect JSON: {"payload": { ... payload complet pentru categoria cardului ... }}';
	}
}
