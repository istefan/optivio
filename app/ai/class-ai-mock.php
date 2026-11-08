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
}
