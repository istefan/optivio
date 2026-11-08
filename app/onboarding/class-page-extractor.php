<?php
/**
 * OptiVio — Deterministic extraction of a public web page (onboarding, zero AI).
 *
 * Produces the visible text as lines (for boilerplate removal across pages), the page outline
 * and the facts that can be found with rules: phones, emails, CUI / Reg. Com., prices,
 * opening hours, schema.org JSON-LD, tel:/mailto: links, noindex, canonical.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Onboarding;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Page extractor.
 */
final class Page_Extractor {

	/**
	 * Block-level elements that end a text line.
	 */
	private const BLOCKS = array( 'p', 'div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main', 'li', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'tr', 'td', 'th', 'table', 'br', 'address', 'dt', 'dd', 'dl', 'blockquote', 'figcaption', 'form', 'label', 'option', 'button' );

	/**
	 * Maximum visible text kept per page (characters).
	 */
	public const MAX_TEXT = 40000;

	/**
	 * Extracts everything from an HTML document.
	 *
	 * @param string $html HTML.
	 * @param string $url  Page URL (base for relative links).
	 * @return array
	 */
	public static function extract( string $html, string $url ): array {
		$out = array(
			'title'            => '',
			'meta_description' => '',
			'lang'             => '',
			'canonical'        => '',
			'noindex'          => false,
			'h1'               => array(),
			'headings'         => array(),
			'lines'            => array(),
			'phones'           => array(),
			'emails'           => array(),
			'cui'              => array(),
			'reg_com'          => array(),
			'prices'           => array(),
			'hours'            => array(),
			'jsonld'           => array(),
			'tel_links'        => 0,
			'links'            => array(),
			'thin'             => false,
		);
		if ( '' === trim( $html ) ) {
			$out['thin'] = true;
			return $out;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		$title        = $xpath->query( '//title' )->item( 0 );
		$out['title'] = null !== $title ? self::clean( (string) $title->textContent, 300 ) : '';
		foreach ( $xpath->query( '//meta[@name]' ) as $meta ) {
			if ( ! $meta instanceof DOMElement ) {
				continue;
			}
			$name = strtolower( $meta->getAttribute( 'name' ) );
			if ( 'description' === $name ) {
				$out['meta_description'] = self::clean( $meta->getAttribute( 'content' ), 500 );
			} elseif ( 'robots' === $name && false !== stripos( $meta->getAttribute( 'content' ), 'noindex' ) ) {
				$out['noindex'] = true;
			}
		}
		$canonical        = $xpath->query( '//link[@rel="canonical"]' )->item( 0 );
		$out['canonical'] = $canonical instanceof DOMElement ? mb_substr( $canonical->getAttribute( 'href' ), 0, 500 ) : '';
		$html_el          = $dom->getElementsByTagName( 'html' )->item( 0 );
		$out['lang']      = $html_el instanceof DOMElement ? mb_substr( $html_el->getAttribute( 'lang' ), 0, 10 ) : '';

		// JSON-LD before the scripts are removed.
		foreach ( $xpath->query( '//script[@type="application/ld+json"]' ) as $script ) {
			$data = json_decode( trim( (string) $script->textContent ), true );
			if ( is_array( $data ) ) {
				self::collect_jsonld( $data, $out['jsonld'] );
			}
		}
		$out['jsonld'] = array_slice( $out['jsonld'], 0, 10 );

		foreach ( array( 'script', 'style', 'noscript', 'svg', 'iframe', 'template', 'canvas' ) as $tag ) {
			foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $node ) {
				$node->parentNode->removeChild( $node );
			}
		}

		foreach ( $xpath->query( '//h1|//h2|//h3' ) as $h ) {
			$text = self::clean( (string) $h->textContent, 200 );
			if ( '' === $text ) {
				continue;
			}
			if ( 'h1' === $h->nodeName ) {
				$out['h1'][] = $text;
			}
			if ( count( $out['headings'] ) < 60 ) {
				$out['headings'][] = array(
					'level' => (int) substr( $h->nodeName, 1 ),
					'text'  => $text,
				);
			}
		}

		foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
			if ( ! $a instanceof DOMElement ) {
				continue;
			}
			$href = trim( $a->getAttribute( 'href' ) );
			if ( 0 === stripos( $href, 'tel:' ) ) {
				++$out['tel_links'];
				$out['phones'][] = self::phone( substr( $href, 4 ) );
			} elseif ( 0 === stripos( $href, 'mailto:' ) ) {
				$out['emails'][] = strtolower( (string) strtok( substr( $href, 7 ), '?' ) );
			} elseif ( '' !== $href && '#' !== $href[0] && ! preg_match( '#^(javascript|data):#i', $href ) && count( $out['links'] ) < 400 ) {
				$out['links'][] = array(
					'url'    => \OptiVio\Services\Content_Ingestor::absolute( $href, $url ),
					'anchor' => self::clean( (string) $a->textContent, 120 ),
					'nav'    => self::in_navigation( $a ),
				);
			}
		}

		$body  = $dom->getElementsByTagName( 'body' )->item( 0 );
		$buf   = '';
		self::walk( null !== $body ? $body : $dom, $buf );
		$lines = array();
		$total = 0;
		foreach ( explode( "\n", $buf ) as $line ) {
			$line = self::clean( $line, 2000 );
			if ( mb_strlen( $line ) < 2 ) {
				continue;
			}
			$lines[] = $line;
			$total  += mb_strlen( $line ) + 1;
			if ( $total > self::MAX_TEXT ) {
				break;
			}
		}
		$out['lines'] = $lines;
		$out['thin']  = $total < 250;

		$text = implode( "\n", $lines );
		self::facts( $text, $out );
		return $out;
	}

	/**
	 * Visible text with line breaks after block elements.
	 *
	 * @param DOMNode $node Node.
	 * @param string  $buf  Buffer.
	 * @return void
	 */
	private static function walk( DOMNode $node, string &$buf ): void {
		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType ) {
				$buf .= $child->nodeValue;
				continue;
			}
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			if ( $child instanceof DOMElement && ( $child->hasAttribute( 'hidden' ) || preg_match( '/display\s*:\s*none/i', $child->getAttribute( 'style' ) ) ) ) {
				continue;
			}
			$block = in_array( strtolower( $child->nodeName ), self::BLOCKS, true );
			if ( $block ) {
				$buf .= "\n";
			}
			if ( $child instanceof DOMElement && 'img' === strtolower( $child->nodeName ) ) {
				continue;
			}
			self::walk( $child, $buf );
			if ( $block ) {
				$buf .= "\n";
			} else {
				$buf .= ' ';
			}
		}
	}

	/**
	 * Whether a link sits inside nav / header / footer / menu.
	 *
	 * @param DOMElement $a Link.
	 * @return bool
	 */
	private static function in_navigation( DOMElement $a ): bool {
		for ( $n = $a->parentNode, $depth = 0; $n instanceof DOMElement && $depth < 12; $n = $n->parentNode, $depth++ ) {
			$tag = strtolower( $n->nodeName );
			if ( in_array( $tag, array( 'nav', 'header', 'footer' ), true ) ) {
				return true;
			}
			if ( preg_match( '/\b(menu|nav|navbar|header|footer)\b/i', $n->getAttribute( 'class' ) . ' ' . $n->getAttribute( 'id' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rule-based facts from the visible text.
	 *
	 * @param string $text Text.
	 * @param array  $out  Output (by reference).
	 * @return void
	 */
	private static function facts( string $text, array &$out ): void {
		if ( preg_match_all( '/(?<![\d+])(?:\+\s?4\s?0|0040|0)\s?(?:7\d{2}|[23]\d{1,2})(?:[\s.\-]?\d){6,7}(?!\d)/', $text, $m ) ) {
			foreach ( $m[0] as $phone ) {
				$out['phones'][] = self::phone( $phone );
			}
		}
		if ( preg_match_all( '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $m ) ) {
			foreach ( $m[0] as $email ) {
				$out['emails'][] = strtolower( $email );
			}
		}
		if ( preg_match_all( '/\b(?:CUI|C\.U\.I\.?|CIF|C\.I\.F\.?|cod\s+fiscal|cod\s+unic(?:\s+de\s+[iî]nregistrare)?)\s*[:.]?\s*((?:RO\s?)?\d{2,10})\b/iu', $text, $m ) ) {
			foreach ( $m[1] as $cui ) {
				$out['cui'][] = strtoupper( str_replace( ' ', '', $cui ) );
			}
		}
		if ( preg_match_all( '/\b([JFC])\s?(\d{1,2})\s?\/\s?(\d{1,8})\s?\/\s?(\d{4})\b/', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $r ) {
				$out['reg_com'][] = $r[1] . $r[2] . '/' . $r[3] . '/' . $r[4];
			}
		}
		if ( preg_match_all( '/.{0,60}?\b\d[\d.\s]{0,8}(?:,\d{1,2})?\s?(?:lei|ron|€|eur(?:o)?)\b.{0,30}/iu', $text, $m ) ) {
			foreach ( array_slice( $m[0], 0, 30 ) as $price ) {
				$out['prices'][] = self::clean( $price, 120 );
			}
		}
		foreach ( explode( "\n", $text ) as $line ) {
			if ( count( $out['hours'] ) >= 10 ) {
				break;
			}
			$plain = mb_strtolower( $line );
			if ( mb_strlen( $line ) <= 160 && ( preg_match( '/non[\s\-]?stop|24\s*\/\s*7|program/u', $plain ) || ( preg_match( '/\b(luni|marți|marti|miercuri|joi|vineri|sâmbătă|sambata|duminică|duminica|l-v)\b/u', $plain ) && preg_match( '/\d{1,2}[:.]\d{2}/', $plain ) ) ) ) {
				$out['hours'][] = $line;
			}
		}
		foreach ( array( 'phones', 'emails', 'cui', 'reg_com', 'prices', 'hours' ) as $key ) {
			$out[ $key ] = array_values( array_slice( array_unique( array_filter( $out[ $key ] ) ), 0, 30 ) );
		}
	}

	/**
	 * Flattens relevant schema.org data.
	 *
	 * @param array $data JSON-LD node.
	 * @param array $out  Output list.
	 * @return void
	 */
	private static function collect_jsonld( array $data, array &$out ): void {
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			foreach ( $data['@graph'] as $node ) {
				if ( is_array( $node ) ) {
					self::collect_jsonld( $node, $out );
				}
			}
			return;
		}
		if ( array_is_list( $data ) ) {
			foreach ( $data as $node ) {
				if ( is_array( $node ) ) {
					self::collect_jsonld( $node, $out );
				}
			}
			return;
		}
		$type = is_array( $data['@type'] ?? null ) ? implode( ',', $data['@type'] ) : (string) ( $data['@type'] ?? '' );
		if ( '' === $type || preg_match( '/^(WebPage|WebSite|BreadcrumbList|ImageObject|SearchAction|ReadAction|SiteNavigationElement|ListItem|Person)$/', $type ) ) {
			return;
		}
		$flat = array( 'type' => mb_substr( $type, 0, 80 ) );
		foreach ( array( 'name', 'legalName', 'telephone', 'email', 'priceRange', 'description', 'areaServed', 'address', 'openingHours', 'openingHoursSpecification', 'serviceType', 'vatID', 'taxID' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$value = is_array( $data[ $key ] ) ? self::stringify( $data[ $key ] ) : (string) $data[ $key ];
				if ( '' !== trim( $value ) ) {
					$flat[ $key ] = self::clean( $value, 400 );
				}
			}
		}
		if ( count( $flat ) > 1 ) {
			$out[] = $flat;
		}
	}

	/**
	 * Converts a nested JSON-LD value to text.
	 *
	 * @param array $value Value.
	 * @return string
	 */
	private static function stringify( array $value ): string {
		$parts = array();
		array_walk_recursive(
			$value,
			static function ( $v, $k ) use ( &$parts ): void {
				if ( is_scalar( $v ) && '@' !== substr( (string) $k, 0, 1 ) ) {
					$parts[] = (string) $v;
				}
			}
		);
		return implode( ', ', array_unique( $parts ) );
	}

	/**
	 * Normalizes a Romanian phone number to digits (0XXXXXXXXX).
	 *
	 * @param string $raw Raw.
	 * @return string
	 */
	public static function phone( string $raw ): string {
		$digits = (string) preg_replace( '/\D/', '', rawurldecode( $raw ) );
		if ( 0 === strpos( $digits, '0040' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '40' ) && 11 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 2 );
		}
		return strlen( $digits ) >= 9 && strlen( $digits ) <= 12 ? $digits : '';
	}

	/**
	 * Collapses whitespace and trims to a maximum length.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max chars.
	 * @return string
	 */
	public static function clean( string $text, int $max ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text ) );
		return mb_substr( $text, 0, $max );
	}
}
