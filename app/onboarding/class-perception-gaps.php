<?php
/**
 * OptiVio — Perception vs reality: deterministic gaps, clarity score and Action Cards.
 *
 * Compares a stored perception (what a bot understood) with the confirmed profile of the
 * business and the decisions taken in the wizard (rejected services / areas, confirmed USP
 * pillars, field verdicts). Every gap is typed (confirmed, corrected, missing_on_site,
 * hallucinated, not_applicable, unclear) and turned into a deterministic card with a stable
 * hash, so re-analyses never duplicate cards. Zero AI.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Onboarding;

use OptiVio\Actions\Cards;
use OptiVio\Core\Database;
use OptiVio\Engine\Text;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Perception gaps.
 */
final class Perception_Gaps {

	/**
	 * Gap types => [label, badge class].
	 */
	public const TYPES = array(
		'confirmed'       => array( 'Înțeles corect', 'ov-badge-success' ),
		'corrected'       => array( 'Înțeles greșit', 'ov-badge-danger' ),
		'missing_on_site' => array( 'Lipsă pe site', 'ov-badge-warning' ),
		'hallucinated'    => array( 'Dedus fals', 'ov-badge-danger' ),
		'unclear'         => array( 'Neclar', 'ov-badge-warning' ),
		'not_applicable'  => array( 'Nu se aplică', 'ov-badge-outline' ),
	);

	/**
	 * Fields => label.
	 */
	public const FIELDS = array(
		'business_model'  => 'Model de afacere',
		'summary'         => 'Descrierea afacerii',
		'service'         => 'Serviciu',
		'area'            => 'Zonă deservită',
		'pillar'          => 'Punct forte (USP)',
		'phone'           => 'Telefon',
		'tel_link'        => 'Link tel:',
		'target_audience' => 'Public țintă',
		'unclear'         => 'Neclar',
	);

	/**
	 * Clarity weights.
	 */
	private const WEIGHTS = array(
		'business_model'  => 20,
		'service'         => 25,
		'area'            => 15,
		'pillar'          => 15,
		'phone'           => 15,
		'target_audience' => 10,
	);

	/**
	 * Splits the service area text into areas.
	 *
	 * @param string $text Text.
	 * @return array<int,string>
	 */
	public static function split_areas( string $text ): array {
		$out = array();
		foreach ( preg_split( '/[,;\/\n]+|\s+și\s+|\s+si\s+/u', $text ) as $a ) {
			$a = trim( $a );
			if ( '' !== $a && '' !== Text::key( $a ) ) {
				$out[] = $a;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Fuzzy match between two short phrases (normalized stems).
	 *
	 * @param string $a A.
	 * @param string $b B.
	 * @return bool
	 */
	public static function same( string $a, string $b ): bool {
		$ka = Text::key( $a );
		$kb = Text::key( $b );
		if ( '' === $ka || '' === $kb ) {
			return false;
		}
		if ( $ka === $kb ) {
			return true;
		}
		$ta = explode( ' ', $ka );
		$tb = explode( ' ', $kb );
		return count( array_intersect( $ta, $tb ) ) / max( 1, min( count( $ta ), count( $tb ) ) ) >= 0.6;
	}

	/**
	 * Computes and stores the gaps of a perception; updates its clarity score.
	 *
	 * @param int $business_id   Business.
	 * @param int $perception_id Perception.
	 * @return array{score:int,gaps:array<int,array>}
	 */
	public static function compute( int $business_id, int $perception_id ): array {
		$db         = Database::instance();
		$perception = Perception::find( $perception_id );
		$business   = $db->get_row( 'SELECT * FROM businesses WHERE id = ?', array( $business_id ) );
		if ( null === $perception || null === $business || (int) $perception['business_id'] !== $business_id ) {
			return array(
				'score' => 0,
				'gaps'  => array(),
			);
		}
		$r         = $perception['result'];
		$state     = (array) ( json_decode( (string) $business['onboarding_state'], true ) ?: array() );
		$dec       = (array) ( $state['decisions'] ?? array() );
		$verdicts  = (array) ( $dec['fields'] ?? array() );
		$offerings = $db->get_col( "SELECT name FROM business_offerings WHERE business_id = ? AND is_active = 1 AND type = 'service' ORDER BY priority, id", array( $business_id ) );
		$gaps      = array();
		$add       = static function ( string $field, string $item, string $type, int $severity, ?string $ai, ?string $confirmed, string $evidence = '', string $url = '' ) use ( &$gaps ): void {
			$key          = mb_substr( '' !== $item ? Text::key( $item ) : '', 0, 190 );
			$gaps[ $field . '|' . $key ] = array(
				'field'           => $field,
				'item_key'        => $key,
				'item'            => $item,
				'gap_type'        => $type,
				'severity'        => $severity,
				'ai_value'        => $ai,
				'confirmed_value' => $confirmed,
				'evidence'        => mb_substr( $evidence, 0, 300 ),
				'source_url'      => mb_substr( $url, 0, 700 ),
			);
		};

		// Business model.
		$ai_model = $r['business_model']['value'] ?? null;
		$labels   = ov_business_models();
		if ( null === $ai_model ) {
			$add( 'business_model', '', 'unclear', 2, null, $labels[ $business['business_model'] ] ?? '', '', '' );
		} elseif ( $ai_model === $business['business_model'] ) {
			$add( 'business_model', '', 'confirmed', 9, $labels[ $ai_model ] ?? $ai_model, $labels[ $business['business_model'] ] ?? '', (string) ( $r['business_model']['evidence'] ?? '' ), (string) ( $r['business_model']['source_url'] ?? '' ) );
		} else {
			$add( 'business_model', '', 'corrected', 1, $labels[ $ai_model ] ?? $ai_model, $labels[ $business['business_model'] ] ?? '', (string) ( $r['business_model']['evidence'] ?? '' ), (string) ( $r['business_model']['source_url'] ?? '' ) );
		}

		// Summary verdict.
		if ( isset( $verdicts['summary'] ) ) {
			$type = array(
				'ok'        => 'confirmed',
				'corrected' => 'corrected',
				'na'        => 'not_applicable',
			)[ $verdicts['summary'] ] ?? 'confirmed';
			$add( 'summary', '', $type, 'corrected' === $type ? 2 : 9, (string) ( $r['summary'] ?? '' ), (string) $business['description'] );
		}

		// Services, areas: confirmed list vs AI list.
		$lists = array(
			'service' => array( $offerings, (array) ( $r['services'] ?? array() ), 'name', (array) ( $dec['rejected_services'] ?? array() ), 2 ),
			'area'    => array( self::split_areas( (string) $business['service_area'] ), (array) ( $r['service_areas'] ?? array() ), 'name', (array) ( $dec['rejected_areas'] ?? array() ), 3 ),
		);
		foreach ( $lists as $field => [ $real, $ai, $name_key, $rejected, $sev ] ) {
			foreach ( $real as $item ) {
				$hit = null;
				foreach ( $ai as $candidate ) {
					if ( self::same( (string) $item, (string) $candidate[ $name_key ] ) ) {
						$hit = $candidate;
						break;
					}
				}
				if ( null !== $hit ) {
					$add( $field, (string) $item, 'confirmed', 9, (string) $hit[ $name_key ], (string) $item, (string) ( $hit['evidence'] ?? '' ), (string) ( $hit['source_url'] ?? '' ) );
				} else {
					$add( $field, (string) $item, 'missing_on_site', $sev, null, (string) $item );
				}
			}
			foreach ( $ai as $candidate ) {
				foreach ( $rejected as $no ) {
					if ( self::same( (string) $no, (string) $candidate[ $name_key ] ) ) {
						$add( $field, (string) $candidate[ $name_key ], 'hallucinated', 3, (string) $candidate[ $name_key ], null, (string) ( $candidate['evidence'] ?? '' ), (string) ( $candidate['source_url'] ?? '' ) );
						break;
					}
				}
			}
		}

		// USP pillars (only when the user reviewed them).
		if ( isset( $dec['pillars'] ) ) {
			$keys   = Perception::pillar_keys();
			$ai_set = array();
			foreach ( (array) ( $r['usp_pillars'] ?? array() ) as $p ) {
				$ai_set[ $p['key'] ] = $p;
			}
			foreach ( (array) $dec['pillars'] as $key ) {
				if ( ! isset( $keys[ $key ] ) ) {
					continue;
				}
				if ( isset( $ai_set[ $key ] ) ) {
					$add( 'pillar', $keys[ $key ], 'confirmed', 9, $keys[ $key ], $keys[ $key ], (string) ( $ai_set[ $key ]['evidence'] ?? '' ), (string) ( $ai_set[ $key ]['source_url'] ?? '' ) );
				} else {
					$add( 'pillar', $keys[ $key ], 'missing_on_site', 3, null, $keys[ $key ] );
				}
			}
			foreach ( $ai_set as $key => $p ) {
				if ( isset( $keys[ $key ] ) && ! in_array( $key, (array) $dec['pillars'], true ) ) {
					$add( 'pillar', $keys[ $key ], 'hallucinated', 3, $keys[ $key ], null, (string) ( $p['evidence'] ?? '' ), (string) ( $p['source_url'] ?? '' ) );
				}
			}
		}

		// Phone.
		$phone = Page_Extractor::phone( (string) $business['phone'] );
		if ( '' !== $phone ) {
			$found = array_merge( (array) ( $r['contact']['phones'] ?? array() ), (array) ( $r['deterministic']['phones'] ?? array() ) );
			$hit   = false;
			foreach ( $found as $f ) {
				$hit = $hit || substr( (string) $f, -9 ) === substr( $phone, -9 );
			}
			$add( 'phone', '', $hit ? 'confirmed' : 'missing_on_site', $hit ? 9 : 2, $hit ? $phone : ( implode( ', ', array_slice( $found, 0, 3 ) ) ?: null ), $phone );
			if ( $hit && 0 === (int) ( $r['deterministic']['tel_links'] ?? 0 ) && 'external' === $perception['source'] ) {
				$add( 'tel_link', '', 'missing_on_site', 4, null, $phone );
			}
		}

		// Target audience verdict.
		if ( isset( $verdicts['target_audience'] ) && null !== ( $r['target_audience'] ?? null ) ) {
			$type = array(
				'ok'        => 'confirmed',
				'corrected' => 'corrected',
				'na'        => 'not_applicable',
			)[ $verdicts['target_audience'] ] ?? 'confirmed';
			$add( 'target_audience', '', $type, 'corrected' === $type ? 4 : 9, (string) $r['target_audience']['value'], (string) $business['target_audience'], (string) ( $r['target_audience']['evidence'] ?? '' ), (string) ( $r['target_audience']['source_url'] ?? '' ) );
		}

		// What the model itself found missing / unclear.
		foreach ( (array) ( $r['missing_or_unclear'] ?? array() ) as $u ) {
			$add( 'unclear', (string) $u['topic'], 'unclear', 4, (string) $u['explanation'], (string) $u['topic'] );
		}

		$score = self::score( $gaps );

		$db->transaction(
			static function () use ( $db, $gaps, $business_id, $perception_id, $score ): void {
				$db->delete( 'perception_gaps', array( 'perception_id' => $perception_id ) );
				foreach ( $gaps as $g ) {
					$db->insert(
						'perception_gaps',
						array(
							'business_id'     => $business_id,
							'perception_id'   => $perception_id,
							'field'           => $g['field'],
							'item_key'        => $g['item_key'],
							'ai_value'        => $g['ai_value'],
							'confirmed_value' => $g['confirmed_value'],
							'gap_type'        => $g['gap_type'],
							'severity'        => $g['severity'],
							'evidence'        => '' !== $g['evidence'] ? $g['evidence'] : null,
							'source_url'      => '' !== $g['source_url'] ? $g['source_url'] : null,
						)
					);
				}
				$db->update( 'business_perceptions', array( 'clarity_score' => $score ), array( 'id' => $perception_id ) );
			}
		);

		return array(
			'score' => $score,
			'gaps'  => array_values( $gaps ),
		);
	}

	/**
	 * Clarity score (0–100): weighted share of confirmed facts that the bot understood.
	 *
	 * @param array $gaps Gaps.
	 * @return int
	 */
	public static function score( array $gaps ): int {
		$by = array();
		foreach ( $gaps as $g ) {
			if ( ! isset( self::WEIGHTS[ $g['field'] ] ) || in_array( $g['gap_type'], array( 'not_applicable', 'hallucinated' ), true ) ) {
				continue;
			}
			$by[ $g['field'] ][] = 'confirmed' === $g['gap_type'] ? 1 : 0;
		}
		$sum    = 0;
		$weight = 0;
		foreach ( $by as $field => $values ) {
			$sum    += self::WEIGHTS[ $field ] * array_sum( $values ) / count( $values );
			$weight += self::WEIGHTS[ $field ];
		}
		return $weight > 0 ? (int) round( 100 * $sum / $weight ) : 0;
	}

	/**
	 * Gaps of a perception (worst first).
	 *
	 * @param int $perception_id Perception.
	 * @return array
	 */
	public static function for_perception( int $perception_id ): array {
		return Database::instance()->get_results(
			"SELECT g.*, c.status AS card_status, c.title AS card_title
			 FROM perception_gaps g LEFT JOIN action_cards c ON c.id = g.action_card_id
			 WHERE g.perception_id = ?
			 ORDER BY FIELD(g.gap_type, 'corrected','missing_on_site','hallucinated','unclear','confirmed','not_applicable'), g.severity, g.field, g.id",
			array( $perception_id )
		);
	}

	/**
	 * Creates (or links) the deterministic cards of a perception's gaps.
	 *
	 * @param int $business_id   Business.
	 * @param int $perception_id Perception.
	 * @return int Cards created.
	 */
	public static function cards( int $business_id, int $perception_id ): int {
		$db         = Database::instance();
		$perception = Perception::find( $perception_id );
		$business   = $db->get_row( 'SELECT * FROM businesses WHERE id = ?', array( $business_id ) );
		if ( null === $perception || null === $business ) {
			return 0;
		}
		$site_id = (int) ( $perception['site_id'] ?? 0 );
		if ( $site_id <= 0 ) {
			$site_id = (int) $db->get_var( "SELECT id FROM sites WHERE business_id = ? AND status = 'active' ORDER BY id LIMIT 1", array( $business_id ) );
		}
		$areas   = self::split_areas( (string) $business['service_area'] );
		$where   = 'internal' === $perception['source'] ? 'conținutul complet al site-ului (din plugin)' : 'paginile publice analizate';
		$created = 0;

		foreach ( self::for_perception( $perception_id ) as $g ) {
			$hash = 'gap:' . $g['field'] . ':' . $g['item_key'];
			if ( 'confirmed' === $g['gap_type'] ) {
				self::note_resolved( $business_id, $hash, (string) $perception['source'] );
				continue;
			}
			$card = self::card_for( $g, $site_id, $areas, $where );
			if ( null === $card ) {
				continue;
			}
			$valid = Cards::validate( $business_id, $card );
			if ( null === $valid ) {
				continue;
			}
			$id = Cards::create( $business_id, $valid, 'deterministic', null, $hash );
			if ( null !== $id ) {
				++$created;
			} else {
				$id = (int) $db->get_var( 'SELECT id FROM action_cards WHERE business_id = ? AND card_hash = ?', array( $business_id, hash( 'sha256', $hash ) ) );
			}
			if ( $id > 0 ) {
				$db->update( 'perception_gaps', array( 'action_card_id' => $id ), array( 'id' => (int) $g['id'] ) );
			}
		}
		return $created;
	}

	/**
	 * Appends a "seems solved" note to an open card whose gap is now confirmed.
	 *
	 * @param int    $business_id Business.
	 * @param string $hash_key    Card hash key.
	 * @param string $source      Perception source.
	 * @return void
	 */
	private static function note_resolved( int $business_id, string $hash_key, string $source ): void {
		$db   = Database::instance();
		$card = $db->get_row( "SELECT id, rationale FROM action_cards WHERE business_id = ? AND card_hash = ? AND status = 'proposed'", array( $business_id, hash( 'sha256', $hash_key ) ) );
		if ( null === $card || false !== strpos( (string) $card['rationale'], '[Actualizare percepție]' ) ) {
			return;
		}
		$note = "\n\n[Actualizare percepție] " . gmdate( 'd.m.Y' ) . ': percepția ' . ( 'internal' === $source ? 'din interior' : 'din exterior' ) . ' arată că acest aspect este acum înțeles corect. Verificați și închideți cardul dacă problema e rezolvată.';
		$db->update( 'action_cards', array( 'rationale' => mb_substr( (string) $card['rationale'] . $note, 0, 3000 ) ), array( 'id' => (int) $card['id'] ) );
	}

	/**
	 * Card definition for a gap.
	 *
	 * @param array  $g        Gap row.
	 * @param int    $site_id  Site for new pages.
	 * @param array  $areas    Confirmed areas.
	 * @param string $where    Source description.
	 * @return array|null
	 */
	private static function card_for( array $g, int $site_id, array $areas, string $where ): ?array {
		$item     = (string) ( $g['confirmed_value'] ?? $g['ai_value'] ?? '' );
		$evidence = '' !== (string) $g['evidence'] ? ' Fragmentul care a indus în eroare: „' . $g['evidence'] . '”' . ( '' !== (string) $g['source_url'] ? ' (' . $g['source_url'] . ')' : '' ) . '.' : '';
		$other    = static function ( string $title, string $why, array $steps, int $priority, int $impact ) use ( $site_id ): array {
			return array(
				'category'  => 'other',
				'title'     => $title,
				'rationale' => $why,
				'priority'  => $priority,
				'impact'    => $impact,
				'site_id'   => $site_id > 0 ? $site_id : null,
				'payload'   => array( 'steps' => $steps ),
			);
		};

		switch ( $g['field'] . '|' . $g['gap_type'] ) {
			case 'service|missing_on_site':
				if ( $site_id > 0 ) {
					$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', Text::normalize( $item ) ), '-' );
					return array(
						'category'  => 'new_landing',
						'title'     => 'Pagină dedicată pentru „' . $item . '”',
						'rationale' => 'Serviciul „' . $item . '” este confirmat de afacere, dar nu reiese din ' . $where . '. Un bot (și Google) nu îl asociază cu afacerea, deci nici clienții care îl caută. Completați textul cu butonul „Generează” înainte de aplicare.',
						'priority'  => 2,
						'impact'    => 7,
						'site_id'   => $site_id,
						'payload'   => array(
							'site_id'       => $site_id,
							'post_type'     => 'page',
							'title'         => mb_strtoupper( mb_substr( $item, 0, 1 ) ) . mb_substr( $item, 1 ),
							'slug'          => mb_substr( $slug, 0, 80 ),
							'focus_keyword' => trim( mb_strtolower( $item ) . ( ! empty( $areas ) ? ' ' . mb_strtolower( $areas[0] ) : '' ) ),
							'content_html'  => '',
							'images'        => array(),
						),
					);
				}
				return $other( 'Serviciul „' . $item . '” nu apare pe site', 'Serviciul este confirmat, dar nu reiese din ' . $where . '.', array( 'Creați o pagină dedicată serviciului, cu titlul și primul paragraf despre „' . $item . '”.', 'Legați pagina din meniu și de pe pagina principală.' ), 2, 7 );
			case 'area|missing_on_site':
				return $other(
					'Zona „' . $item . '” nu apare pe site',
					'Afacerea lucrează în „' . $item . '”, dar zona nu reiese din ' . $where . '. Căutările locale („serviciu + zonă”) nu vor asocia site-ul cu această zonă.',
					array( 'Menționați „' . $item . '” în primul paragraf al paginii principale și pe paginile de servicii.', 'Adăugați zona în titlul SEO sau în meta descrierea paginilor de servicii relevante.', 'Verificați că zona apare și în Google Business Profile (zone deservite).' ),
					3,
					6
				);
			case 'pillar|missing_on_site':
				return $other(
					'Punctul forte „' . $item . '” nu este comunicat',
					'Afacerea confirmă „' . $item . '”, dar un bot nu îl găsește în ' . $where . '. Este un argument de conversie pierdut.',
					array( 'Adăugați mesajul în primul ecran al paginii principale (titlu sau subtitlu).', 'Repetați-l lângă butoanele de apel / formular și în meta descriere.', 'Susțineți-l cu o dovadă concretă (cifră, certificat, recenzie).' ),
					3,
					6
				);
			case 'business_model|corrected':
				return $other(
					'Site-ul transmite alt tip de afacere: „' . $g['ai_value'] . '”',
					'Un bot a înțeles „' . $g['ai_value'] . '”, dar afacerea este „' . $g['confirmed_value'] . '”.' . $evidence . ' Modelul greșit schimbă intenția căutărilor pentru care e afișat site-ul.',
					array( 'Rescrieți titlul (H1) și primul paragraf al paginii principale ca să descrie clar: ' . $g['confirmed_value'] . '.', 'Aliniați titlul SEO și meta descrierea paginii principale.', 'Verificați ca serviciile principale să fie în meniu și pe pagina principală.' ),
					1,
					8
				);
			case 'business_model|unclear':
				return $other( 'Tipul afacerii nu reiese din site', 'Un bot nu a putut deduce ce fel de afacere este din ' . $where . '.', array( 'Spuneți explicit în primul ecran ce faceți, pentru cine și unde.', 'Adăugați date structurate LocalBusiness (schema.org) cu tipul corect.' ), 2, 7 );
			case 'summary|corrected':
				return $other(
					'Descrierea afacerii, așa cum o înțelege un bot, nu corespunde',
					'Un bot descrie afacerea așa: „' . mb_substr( (string) $g['ai_value'], 0, 400 ) . '”. Descrierea confirmată este diferită.',
					array( 'Rescrieți introducerea paginii principale pornind de la descrierea confirmată din profil.', 'Actualizați pagina „Despre” și meta descrierea paginii principale.' ),
					2,
					7
				);
			case 'phone|missing_on_site':
				return array(
					'category'  => 'technical',
					'title'     => 'Numărul de telefon ' . $g['confirmed_value'] . ' nu este găsit pe site',
					'rationale' => 'Telefonul afacerii nu apare în ' . $where . ( null !== $g['ai_value'] ? ' (apar alte numere: ' . $g['ai_value'] . ')' : '' ) . '. Apelurile sunt conversia principală.',
					'priority'  => 2,
					'impact'    => 7,
					'site_id'   => $site_id > 0 ? $site_id : null,
					'payload'   => array( 'steps' => array( 'Afișați numărul în header și în footer, ca text (nu imagine).', 'Folosiți link tel: (ex. <a href="tel:' . $g['confirmed_value'] . '">) ca să fie urmărit de OptiVio Connector.', 'Eliminați numerele vechi sau greșite.' ) ),
				);
			case 'tel_link|missing_on_site':
				return array(
					'category'  => 'technical',
					'title'     => 'Telefonul nu are link tel: (click pentru apel)',
					'rationale' => 'Numărul apare pe site, dar nu ca link tel:. Pe mobil nu se poate apela cu un click, iar click-urile nu pot fi măsurate.',
					'priority'  => 3,
					'impact'    => 5,
					'site_id'   => $site_id > 0 ? $site_id : null,
					'payload'   => array( 'steps' => array( 'Transformați fiecare apariție a numărului în link <a href="tel:' . $g['confirmed_value'] . '">.', 'Adăugați un buton „Sună acum” vizibil pe mobil.' ) ),
				);
			case 'target_audience|corrected':
				return $other( 'Publicul țintă nu reiese corect din site', 'Un bot crede că site-ul se adresează: „' . mb_substr( (string) $g['ai_value'], 0, 300 ) . '”.', array( 'Descrieți explicit clienții tipici (cine sunt, ce problemă au) pe pagina principală și pe paginile de servicii.', 'Folosiți exemple și situații concrete ale clienților reali.' ), 4, 5 );
			case 'service|hallucinated':
			case 'area|hallucinated':
			case 'pillar|hallucinated':
				return $other(
					'Text ambiguu: botul a înțeles „' . $g['ai_value'] . '”',
					'Un bot a dedus „' . $g['ai_value'] . '” (' . mb_strtolower( self::FIELDS[ $g['field'] ] ) . '), dar afacerea a confirmat că nu se aplică.' . $evidence,
					array( '' !== (string) $g['source_url'] ? 'Verificați textul de pe ' . $g['source_url'] . '.' : 'Căutați pe site fragmentele care sugerează „' . $g['ai_value'] . '”.', 'Reformulați sau eliminați fragmentul, ca să nu atragă clienți nepotriviți.' ),
					4,
					4
				);
			case 'unclear|unclear':
				return $other( 'Neclar pentru un bot: ' . $g['confirmed_value'], (string) $g['ai_value'], array( 'Adăugați o secțiune sau o întrebare frecventă (FAQ) care răspunde explicit.', 'Folosiți aceleași cuvinte pe care le folosesc clienții când caută.' ), 4, 5 );
		}
		return null;
	}
}
