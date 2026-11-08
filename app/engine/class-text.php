<?php
/**
 * OptiVio — Deterministic Romanian text utilities (normalization, tokens, stopwords, n-grams).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Text helpers.
 */
final class Text {

	/**
	 * Romanian (+ common English) stopwords, unaccented.
	 */
	private const STOPWORDS = array( 'a', 'ai', 'al', 'ale', 'am', 'ar', 'are', 'as', 'asa', 'acest', 'aceasta', 'aceste', 'acesta', 'acel', 'acea', 'acum', 'ati', 'au', 'avea', 'avem', 'aveti', 'ca', 'cand', 'care', 'cat', 'cate', 'ce', 'cel', 'cea', 'cei', 'cele', 'cu', 'cum', 'da', 'daca', 'dar', 'de', 'deci', 'despre', 'din', 'dintre', 'doar', 'dupa', 'e', 'ea', 'el', 'este', 'eu', 'fi', 'fie', 'fost', 'i', 'ii', 'il', 'in', 'inca', 'intr', 'intre', 'isi', 'la', 'le', 'li', 'lor', 'lui', 'ma', 'mai', 'mult', 'multe', 'ne', 'nici', 'noi', 'nu', 'o', 'ori', 'pe', 'pentru', 'poate', 'prin', 'sa', 'sau', 'se', 'si', 'sunt', 'ta', 'tot', 'toate', 'toti', 'tu', 'un', 'una', 'unde', 'unei', 'unor', 'unui', 'va', 'vor', 'voi', 'the', 'and', 'of', 'to', 'for', 'with', 'your', 'our', 'is', 'are', 'on', 'at', 'by', 'or', 'it', 'this', 'that', 'we', 'you' );

	/**
	 * Lower-case, no diacritics, single spaces.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function normalize( string $text ): string {
		$text = mb_strtolower( $text );
		$text = strtr(
			$text,
			array(
				'ă' => 'a',
				'â' => 'a',
				'î' => 'i',
				'ș' => 's',
				'ş' => 's',
				'ț' => 't',
				'ţ' => 't',
				'é' => 'e',
				'è' => 'e',
				'ö' => 'o',
				'ü' => 'u',
			)
		);
		$text = (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Tokens without stopwords (min. 2 chars).
	 *
	 * @param string $text Text.
	 * @return array<int,string>
	 */
	public static function tokens( string $text ): array {
		$out = array();
		foreach ( explode( ' ', self::normalize( $text ) ) as $t ) {
			if ( mb_strlen( $t ) >= 2 && ! in_array( $t, self::STOPWORDS, true ) && ! ctype_digit( $t ) ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	/**
	 * Light Romanian stemming for matching (strips common inflection endings).
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public static function stem( string $token ): string {
		if ( mb_strlen( $token ) <= 4 ) {
			return $token;
		}
		return (string) preg_replace( '/(urilor|ilor|elor|ului|uri|ele|ile|lor|ii|ul|le|ea|ia|a|e|i|u)$/u', '', $token );
	}

	/**
	 * Comparable key of a phrase (stems, sorted) — "țevi sparte" ≈ "teava sparta".
	 *
	 * @param string $phrase Phrase.
	 * @return string
	 */
	public static function key( string $phrase ): string {
		$stems = array_map( array( self::class, 'stem' ), self::tokens( $phrase ) );
		sort( $stems );
		return implode( ' ', array_unique( $stems ) );
	}

	/**
	 * Term frequency vector of unigrams + bigrams (top N).
	 *
	 * @param string $text Text.
	 * @param int    $top  Top N.
	 * @return array<string,int>
	 */
	public static function term_vector( string $text, int $top = 60 ): array {
		$tokens = self::tokens( $text );
		$counts = array();
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$counts[ $tokens[ $i ] ] = ( $counts[ $tokens[ $i ] ] ?? 0 ) + 1;
			if ( $i + 1 < $count ) {
				$bigram            = $tokens[ $i ] . ' ' . $tokens[ $i + 1 ];
				$counts[ $bigram ] = ( $counts[ $bigram ] ?? 0 ) + 1;
			}
		}
		$counts = array_filter(
			$counts,
			static function ( int $n, string $term ): bool {
				return $n >= ( str_contains( $term, ' ' ) ? 2 : 1 );
			},
			ARRAY_FILTER_USE_BOTH
		);
		arsort( $counts );
		return array_slice( $counts, 0, $top, true );
	}

	/**
	 * Whether all stems of $needle appear in $haystack.
	 *
	 * @param string $needle   Phrase.
	 * @param string $haystack Text.
	 * @return bool
	 */
	public static function contains_phrase( string $needle, string $haystack ): bool {
		$hay = array_map( array( self::class, 'stem' ), self::tokens( $haystack ) );
		foreach ( array_map( array( self::class, 'stem' ), self::tokens( $needle ) ) as $stem ) {
			if ( ! in_array( $stem, $hay, true ) ) {
				return false;
			}
		}
		return '' !== trim( $needle );
	}
}
