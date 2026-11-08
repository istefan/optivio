<?php
/**
 * OptiVio — Deterministic OpenRouter fixtures (development only, config ai_mock = true).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Ai;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * AI mock.
 */
final class Ai_Mock {

	/**
	 * Mock prices (USD / token).
	 *
	 * @return array
	 */
	public static function prices(): array {
		return array(
			'anthropic/claude-haiku-5.5'  => array( 0.000001, 0.000005 ),
			'anthropic/claude-sonnet-5.5' => array( 0.000003, 0.000015 ),
			'anthropic/claude-opus-5.5'   => array( 0.000015, 0.000075 ),
			'openai/gpt-5-mini'           => array( 0.00000025, 0.000002 ),
			'google/gemini-2.5-flash'     => array( 0.0000003, 0.0000025 ),
		);
	}

	/**
	 * Returns [code, response, error].
	 *
	 * @param string $purpose Purpose.
	 * @param string $user    User prompt (JSON snapshot / card context).
	 * @return array{0:int,1:array,2:string}
	 */
	public static function respond( string $purpose, string $user ): array {
		if ( 0 === strpos( $purpose, 'perception_' ) ) {
			return self::perception( $user );
		}
		$input = json_decode( $user, true );
		$input = is_array( $input ) ? $input : array();

		if ( 'deep_analysis' === $purpose ) {
			$content = (array) ( $input['content'] ?? array() );
			$first   = $content[0] ?? null;
			$second  = $content[1] ?? $first;
			$site_id = (int) ( $input['sites'][0]['site_id'] ?? 0 );
			foreach ( (array) ( $input['sites'] ?? array() ) as $s ) {
				if ( 'connected' === $s['connector'] ) {
					$site_id = (int) $s['site_id'];
				}
			}
			$cards = array(
				array(
					'category'  => 'ads_campaign',
					'title'     => 'Treceți campania de urgență pe „Maximizare click-uri” cu plafon CPC',
					'rationale' => 'Model de urgență, conversii nerepetitive; apelurile refuzate cresc seara.',
					'priority'  => 1,
					'impact'    => 8,
					'site_id'   => null,
					'payload'   => array( 'campaign' => 'Urgente-Instalatii-Search', 'changes' => array( 'Strategie: Maximizare click-uri', 'Plafon CPC: 6 RON', 'Excludeți orele 22–06' ) ),
				),
				array(
					'category'  => 'new_landing',
					'title'     => 'Landing page „Instalator non stop sector 2”',
					'rationale' => 'Termenul convertește în Ads (1 conversie) fără pagină SEO dedicată.',
					'priority'  => 2,
					'impact'    => 7,
					'site_id'   => $site_id,
					'payload'   => array(
						'site_id'          => $site_id,
						'post_type'        => 'page',
						'title'            => 'Instalator non stop Sector 2',
						'slug'             => 'instalator-non-stop-sector-2',
						'seo_title'        => 'Instalator non stop Sector 2 | Intervenție în 45 min',
						'meta_description' => 'Instalator non stop în Sectorul 2: țevi sparte, desfundări, avarii. Ajungem în 45 de minute, preț fix comunicat la telefon.',
						'focus_keyword'    => 'instalator non stop sector 2',
						'content_html'     => '{{image:featured}}<h2>Intervenții non stop în Sectorul 2</h2><p>Echipele noastre ajung în maximum 45 de minute.</p><h2>Ce reparăm</h2><ul><li>Țevi sparte</li><li>Desfundări</li></ul>{{image:inline_1}}<h2>Preț fix</h2><p>Prețul este comunicat la telefon înainte de deplasare.</p>',
						'images'           => array(
							array( 'placement' => 'featured', 'prompt' => 'Professional plumber repairing a burst pipe under a kitchen sink, realistic photo, natural light, Bucharest apartment, no text', 'alt' => 'Instalator reparând o țeavă spartă' ),
							array( 'placement' => 'inline_1', 'prompt' => 'Plumbing service van parked in front of a Bucharest apartment block at dusk, realistic photo, no text', 'alt' => 'Autoutilitară instalator non stop' ),
						),
					),
				),
				array(
					'category'  => 'ads_negative',
					'title'     => 'Adăugați „curs” și „gratuit” ca negative',
					'rationale' => 'Termenul „curs instalator gratuit” consumă buget fără conversii.',
					'priority'  => 3,
					'impact'    => 5,
					'site_id'   => null,
					'payload'   => array( 'keywords' => array( 'curs', 'gratuit' ), 'match_type' => 'phrase', 'scope' => 'account', 'campaign' => null ),
				),
			);
			if ( null !== $first ) {
				$cards[] = array(
					'category'  => 'seo_meta',
					'title'     => 'Titlu și meta pentru „' . $first['title'] . '”',
					'rationale' => 'Meta descrierea lipsește; poziție bună dar CTR mic.',
					'priority'  => 2,
					'impact'    => 6,
					'site_id'   => (int) $first['site_id'],
					'payload'   => array( 'content_id' => (int) $first['content_id'], 'seo_title' => 'Instalator non stop București | Ajungem în 45 min', 'meta_description' => 'Țevi sparte, desfundări și avarii rezolvate non stop în București. Preț fix comunicat la telefon, echipe autorizate.', 'focus_keyword' => 'instalator non stop' ),
				);
			}
			if ( null !== $first && null !== $second && $first['content_id'] !== $second['content_id'] ) {
				$cards[] = array(
					'category'  => 'internal_link',
					'title'     => 'Link intern către „' . $first['title'] . '”',
					'rationale' => 'Pagina are puține linkuri interne primite.',
					'priority'  => 4,
					'impact'    => 4,
					'site_id'   => (int) $second['site_id'],
					'payload'   => array( 'source_content_id' => (int) $second['content_id'], 'target_content_id' => (int) $first['content_id'], 'anchor_text' => 'intervenții urgente non stop', 'paragraph_html' => '<p>Pentru avarii, apelați la {{link}}.</p>' ),
				);
			}
			$cards[] = array( 'category' => 'seo_meta', 'title' => 'Card invalid (content_id inexistent)', 'rationale' => 'test validare', 'priority' => 5, 'impact' => 1, 'site_id' => null, 'payload' => array( 'content_id' => 999999999, 'seo_title' => 'x' ) );
			$cards[] = array( 'category' => 'hack', 'title' => 'Categorie necunoscută', 'payload' => array() );

			$data = array(
				'summary_markdown' => "## Diagnostic\n\nAfacerea primește majoritatea apelurilor din Google Ads, dar **42% sunt refuzate**, concentrate seara.\n\n## Oportunități\n\n- Pagini dedicate pe sectoare pentru termenii care convertesc.\n- Meta descrieri lipsă pe paginile principale.\n\n## Plan 30 de zile\n\n1. Strategie de licitare pe click-uri.\n2. Excluderi orare.\n3. Landing page-uri pe sectoare.",
				'cards'            => $cards,
			);
		} else {
			$category = (string) ( $input['card']['category'] ?? '' );
			$payload  = (array) ( $input['card']['payload'] ?? array() );
			if ( 'seo_meta' === $category ) {
				$payload = array_merge( $payload, array( 'seo_title' => 'Intervenții urgente non stop | Instalatori București', 'meta_description' => 'Echipe autorizate disponibile non stop în București: țevi sparte, desfundări, avarii. Ajungem rapid, preț fix comunicat la telefon.', 'focus_keyword' => 'interventii urgente' ) );
			} else {
				$payload['content_html'] = '<h2>Generat AI (mock)</h2><p>Conținut demonstrativ.</p>';
			}
			$data = array( 'payload' => $payload );
		}

		$json = (string) json_encode( $data, JSON_UNESCAPED_UNICODE );
		return array(
			200,
			array(
				'choices' => array( array( 'message' => array( 'content' => "```json\n" . $json . "\n```" ) ) ),
				'usage'   => array(
					'prompt_tokens'     => (int) ( strlen( $user ) / 3.5 ),
					'completion_tokens' => (int) ( strlen( $json ) / 3.5 ),
					'cost'              => round( strlen( $user ) / 3.5 * 0.000003 + strlen( $json ) / 3.5 * 0.000015, 6 ),
				),
			),
			'',
		);
	}

	/**
	 * Perception fixture derived from the page text (headings, phones, areas, pillars).
	 *
	 * @param string $user User prompt (facts + pages).
	 * @return array{0:int,1:array,2:string}
	 */
	private static function perception( string $user ): array {
		preg_match_all( '/^=== PAGINA \d+: (\S+) \| tip: (\w+) ===$/m', $user, $pm, PREG_SET_ORDER );
		$home     = $pm[0][1] ?? '';
		$title    = preg_match( '/^Titlu: (.+)$/m', $user, $tm ) ? trim( (string) preg_replace( '/\s*[-|–].*$/u', '', $tm[1] ) ) : 'Afacere';
		$title    = '' !== $title ? $title : 'Afacere';
		$services = array();
		$per_page = array();
		foreach ( $pm as $page ) {
			$chunk = (string) substr( $user, (int) strpos( $user, $page[0] ) + strlen( $page[0] ), 3000 );
			$h1    = preg_match( '/^H1: ([^|\n]+)/m', $chunk, $hm ) ? trim( $hm[1] ) : '';
			if ( 'service' === $page[2] && '' !== $h1 && count( $services ) < 6 ) {
				$services[] = array(
					'name'        => mb_substr( $h1, 0, 80 ),
					'description' => 'Serviciu prezentat pe pagina dedicată.',
					'price_hint'  => preg_match( '/(\d+\s?(lei|ron))/iu', $chunk, $pr ) ? $pr[1] : '',
					'confidence'  => 0.8,
					'evidence'    => mb_substr( $h1, 0, 120 ),
					'source_url'  => $page[1],
				);
			}
			$per_page[] = array(
				'url'           => $page[1],
				'understood_as' => 'Pagină de tip ' . $page[2] . ( '' !== $h1 ? ': ' . $h1 : '' ),
				'main_topic'    => '' !== $h1 ? $h1 : $page[2],
				'clarity'       => '' !== $h1 ? 4 : 2,
			);
		}
		$plain   = \OptiVio\Engine\Text::normalize( $user );
		$pillars = array();
		foreach ( \OptiVio\Services\Usp_Builder::PILLARS as $key => $pillar ) {
			if ( 'guarantee_any' !== $key && preg_match( $pillar[1], $plain, $m ) ) {
				$pillars[] = array(
					'key'        => $key,
					'confidence' => 0.7,
					'evidence'   => $m[0],
					'source_url' => $home,
				);
			}
		}
		$areas = array();
		if ( preg_match_all( '/\b(bucuresti|ilfov|sector(?:ul)? [1-6]|cluj|iasi|timisoara|brasov|constanta)\b/', $plain, $am ) ) {
			foreach ( array_slice( array_unique( $am[1] ), 0, 6 ) as $area ) {
				$areas[] = array(
					'name'       => ucwords( $area ),
					'confidence' => 0.75,
					'evidence'   => $area,
					'source_url' => $home,
				);
			}
		}
		preg_match_all( '/(?:\+?40|0)7\d{2}[\s.\-]?\d{3}[\s.\-]?\d{3}/', $user, $ph );
		$emergency = (bool) preg_match( '/non\s?stop|urgen|24\s?\/\s?7/', $plain );
		$content   = array(
			'business_name'      => array(
				'value'      => $title,
				'confidence' => 0.9,
				'evidence'   => $title,
				'source_url' => $home,
			),
			'brand'              => $title,
			'summary'            => $title . ' pare să ofere ' . ( empty( $services ) ? 'servicii nespecificate clar' : mb_strtolower( implode( ', ', array_column( $services, 'name' ) ) ) ) . ( empty( $areas ) ? '' : ' în ' . implode( ', ', array_column( $areas, 'name' ) ) ) . '. (răspuns simulat — ai_mock)',
			'business_model'     => array(
				'value'      => $emergency ? 'emergency_service' : 'local_service',
				'confidence' => 0.6,
				'evidence'   => $emergency ? 'intervenții urgente' : '',
				'source_url' => $home,
			),
			'services'           => $services,
			'service_areas'      => $areas,
			'target_audience'    => array(
				'value'      => 'Proprietari de locuințe care au nevoie rapid de un specialist.',
				'confidence' => 0.4,
				'evidence'   => '',
				'source_url' => $home,
			),
			'usp_pillars'        => $pillars,
			'opening_hours'      => array(
				'value'      => $emergency ? 'Non-stop' : '',
				'confidence' => 0.5,
				'evidence'   => '',
				'source_url' => $home,
			),
			'contact'            => array(
				'phones' => array_values( array_unique( $ph[0] ) ),
				'emails' => array(),
			),
			'legal'              => array(),
			'tone'               => array(
				'value'      => 'Informal, direct',
				'confidence' => 0.5,
			),
			'trust_signals'      => array(),
			'ctas'               => array( 'Sunați acum' ),
			'missing_or_unclear' => array(
				array(
					'topic'       => 'Prețuri',
					'explanation' => 'Nu reiese clar cât costă o intervenție standard.',
				),
				array(
					'topic'       => 'Zona deservită',
					'explanation' => empty( $areas ) ? 'Nu se înțelege în ce localități lucrați.' : 'Zonele apar doar izolat, nu pe paginile de servicii.',
				),
			),
			'per_page'           => $per_page,
		);
		return array(
			200,
			array(
				'choices' => array( array( 'message' => array( 'content' => json_encode( $content, JSON_UNESCAPED_UNICODE ) ) ) ),
				'usage'   => array(
					'prompt_tokens'     => (int) ceil( mb_strlen( $user ) / 4 ),
					'completion_tokens' => 900,
					'cost'              => round( mb_strlen( $user ) / 4 * 0.0000003 + 900 * 0.0000025, 6 ),
				),
			),
			'',
		);
	}
}
