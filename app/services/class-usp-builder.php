<?php
/**
 * OptiVio — Deterministic USP proposal (zero AI).
 *
 * Detects selling "pillars" in the business description, offerings and profile with
 * Romanian regex patterns, compares them with the pillars that matter for the business
 * model, and composes a USP sentence. The result is a proposal: the user edits/accepts it.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * USP builder.
 */
final class Usp_Builder {

	/**
	 * Pillars: key => [label, regex (case-insensitive, unaccented text), phrase template ({1} = first capture)].
	 */
	private const PILLARS = array(
		'availability'   => array( 'Disponibilitate non-stop', '/\b(non[\s\-]?stop|24\s*\/\s*7|24\s*din\s*24|zi\s+si\s+noapte|inclusiv\s+(in\s+)?weekend)/', 'disponibili non-stop, inclusiv în weekend' ),
		'response_time'  => array( 'Timp de intervenție garantat', '/\b(?:in|sub|maxim(?:um)?|max\.?)\s+(\d{1,3})\s*(?:de\s+)?(?:minute|min)\b/', 'ajungem în maximum {1} de minute' ),
		'fixed_price'    => array( 'Preț transparent', '/\b(pret|tarif|cost)(uri)?\s+(fix|fixe|transparent|clar)|fara\s+(costuri|taxe)\s+ascunse|pretul\s+(se\s+)?comunica/', 'preț fix comunicat înainte de începerea lucrării' ),
		'guarantee'      => array( 'Garanție', '/\bgarantie\s+(?:de\s+)?(\d{1,2})\s*(ani|an|luni|luna)\b/', 'garanție {1}' ),
		'guarantee_any'  => array( 'Garanție', '/\bgarant(ie|am|at)\b/', 'lucrări garantate' ),
		'experience'     => array( 'Experiență', '/\b(\d{1,2})\s*(?:\+\s*)?(?:de\s+)?ani\s+(?:de\s+)?experienta\b/', 'peste {1} ani de experiență' ),
		'certified'      => array( 'Autorizări / certificări', '/\b(autoriza(t|ti|te|tie)|anre|iscir|certifica(t|ti|re)|acreditat)/', 'echipe autorizate' ),
		'free_visit'     => array( 'Deplasare / evaluare gratuită', '/\b(deplasare|constatare|evaluare|consultatie|masuratori)\s+gratuit/', 'deplasare și evaluare gratuite' ),
		'local'          => array( 'Prezență locală', '/\b(echipe\s+mobile|in\s+toate\s+sectoarele|local|din\s+zona)\b/', 'echipe locale' ),
		'fast_delivery'  => array( 'Livrare rapidă', '/\blivrare\s+(rapida|gratuita|in\s+24|in\s+aceeasi\s+zi)/', 'livrare rapidă' ),
		'easy_returns'   => array( 'Retur simplu', '/\bretur\s+(gratuit|in\s+\d+\s+zile|simplu)/', 'retur simplu' ),
		'payment'        => array( 'Plată flexibilă', '/\b(plata\s+cu\s+cardul|plata\s+in\s+rate|rate\s+fara\s+dobanda|factura)/', 'plată cu cardul și factură' ),
		'specialization' => array( 'Specializare', '/\b(specializa(t|ti|re)|exclusiv|doar\s+pe)\b/', 'specializați exclusiv în domeniu' ),
	);

	/**
	 * Pillars that matter most for each business model.
	 */
	private const MODEL_PILLARS = array(
		'emergency_service' => array( 'response_time', 'availability', 'fixed_price', 'guarantee', 'certified' ),
		'recurring_service' => array( 'guarantee', 'certified', 'fixed_price', 'experience', 'payment' ),
		'local_service'     => array( 'experience', 'certified', 'free_visit', 'fixed_price', 'local' ),
		'b2b'               => array( 'experience', 'specialization', 'certified', 'guarantee', 'payment' ),
		'ecommerce'         => array( 'fast_delivery', 'easy_returns', 'payment', 'guarantee', 'fixed_price' ),
		'lead_generation'   => array( 'free_visit', 'experience', 'fixed_price', 'guarantee', 'local' ),
		'publisher'         => array( 'specialization', 'experience' ),
		'other'             => array( 'experience', 'guarantee', 'fixed_price', 'certified' ),
	);

	/**
	 * Builds a proposal.
	 *
	 * @param array $business  Business row.
	 * @param array $offerings Offering rows.
	 * @return array{text:string,detected:array<int,array{key:string,label:string,evidence:string}>,missing:array<int,string>,strength:int}
	 */
	public static function propose( array $business, array $offerings ): array {
		$corpus = self::normalize(
			implode(
				' ',
				array_merge(
					array( (string) $business['description'], (string) $business['target_audience'], (string) $business['usp_text'] ),
					array_map(
						static function ( array $o ): string {
							return $o['name'] . ' ' . $o['description'];
						},
						$offerings
					)
				)
			)
		);

		$detected = array();
		$phrases  = array();
		foreach ( self::PILLARS as $key => $pillar ) {
			if ( 'guarantee_any' === $key && isset( $detected['guarantee'] ) ) {
				continue;
			}
			if ( preg_match( $pillar[1], $corpus, $m ) ) {
				$canonical              = 'guarantee_any' === $key ? 'guarantee' : $key;
				$detected[ $canonical ] = array(
					'key'      => $canonical,
					'label'    => $pillar[0],
					'evidence' => trim( $m[0] ),
				);
				$phrases[ $canonical ]  = str_replace( '{1}', trim( ( $m[1] ?? '' ) . ' ' . ( $m[2] ?? '' ) ), $pillar[2] );
				if ( 'guarantee' === $key ) {
					$phrases[ $canonical ] = 'garanție ' . trim( $m[1] . ' ' . $m[2] );
				}
			}
		}

		$all_emergency = ! empty( $offerings ) && count(
			array_filter(
				$offerings,
				static function ( array $o ): bool {
					return ! empty( $o['is_emergency'] );
				}
			)
		) > 0;
		if ( $all_emergency && ! isset( $detected['availability'] ) && 'emergency_service' === $business['business_model'] ) {
			$phrases['availability'] = 'intervenții de urgență';
		}

		$model    = (string) $business['business_model'];
		$priority = self::MODEL_PILLARS[ $model ] ?? self::MODEL_PILLARS['other'];
		$missing  = array();
		foreach ( $priority as $key ) {
			if ( ! isset( $detected[ $key ] ) ) {
				$missing[] = self::PILLARS[ $key ][0];
			}
		}

		// Order phrases by the model priority, keep the 3 strongest.
		$ordered = array();
		foreach ( array_merge( $priority, array_keys( $phrases ) ) as $key ) {
			if ( isset( $phrases[ $key ] ) && ! isset( $ordered[ $key ] ) ) {
				$ordered[ $key ] = $phrases[ $key ];
			}
		}
		$ordered = array_slice( array_values( $ordered ), 0, 3 );

		$main = self::main_offering( $business, $offerings );
		$area = trim( (string) $business['service_area'] );
		$head = $main . ( '' !== $area ? ' în ' . $area : '' );

		if ( empty( $ordered ) ) {
			$text = $head . '.';
		} else {
			$last = array_pop( $ordered );
			$tail = empty( $ordered ) ? $last : implode( ', ', $ordered ) . ' și ' . $last;
			$text = $head . ': ' . $tail . '.';
		}

		$strength = (int) round( 100 * count( array_intersect( array_keys( $detected ), $priority ) ) / max( 1, count( $priority ) ) );

		return array(
			'text'     => self::ucfirst( $text ),
			'detected' => array_values( $detected ),
			'missing'  => $missing,
			'strength' => $strength,
		);
	}

	/**
	 * Picks the main offering label.
	 *
	 * @param array $business  Business.
	 * @param array $offerings Offerings (ordered by priority).
	 * @return string
	 */
	private static function main_offering( array $business, array $offerings ): string {
		foreach ( $offerings as $offering ) {
			if ( ! empty( $offering['is_active'] ) ) {
				return mb_strtolower( (string) $offering['name'] );
			}
		}
		return (string) $business['name'];
	}

	/**
	 * Lower-cases and removes Romanian diacritics for matching.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalize( string $text ): string {
		$text = mb_strtolower( $text );
		return strtr(
			$text,
			array(
				'ă' => 'a',
				'â' => 'a',
				'î' => 'i',
				'ș' => 's',
				'ş' => 's',
				'ț' => 't',
				'ţ' => 't',
			)
		);
	}

	/**
	 * Multibyte ucfirst.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function ucfirst( string $text ): string {
		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}
}
