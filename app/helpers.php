<?php
/**
 * OptiVio — Global helper functions.
 *
 * Escaping/sanitizing helpers intentionally mirror the WordPress API names
 * (esc_html, esc_attr, sanitize_text_field, ...) so that the WordPress Coding
 * Standards sniffs recognise them as safe output/input functions.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

defined( 'OPTIVIO_ROOT' ) || exit;

use OptiVio\Core\Config;
use OptiVio\Core\Csrf;

/*
 * -----------------------------------------------------------------------------
 * Escaping (late escaping — always at output time).
 * -----------------------------------------------------------------------------
 */

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escapes a string for safe output inside HTML.
	 *
	 * @param mixed $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escapes a string for safe output inside an HTML attribute.
	 *
	 * @param mixed $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * Escapes text for output inside a textarea.
	 *
	 * @param mixed $text Text to escape.
	 * @return string
	 */
	function esc_textarea( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escapes a URL for output. Only http(s), mailto, tel and relative URLs are allowed.
	 *
	 * @param mixed $url URL to escape.
	 * @return string Empty string when the protocol is not allowed.
	 */
	function esc_url( $url ): string {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$url = preg_replace( '/[\x00-\x1F\x7F\s]/u', '', $url );

		if ( preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $url, $matches ) ) {
			$scheme = strtolower( $matches[1] );
			if ( ! in_array( $scheme, array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
				return '';
			}
		}

		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8' );
	}
}

/*
 * -----------------------------------------------------------------------------
 * Sanitizing (at input time).
 * -----------------------------------------------------------------------------
 */

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Sanitizes a single-line text value: strips tags, control chars, collapses whitespace.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = (string) $value;
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			return '';
		}
		$value = strip_tags( $value );
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Sanitizes multi-line text: strips tags and control chars but keeps new lines.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function sanitize_textarea_field( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = (string) $value;
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			return '';
		}
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = strip_tags( $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value );
		return trim( $value );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Sanitizes and validates an email address.
	 *
	 * @param mixed $email Raw email.
	 * @return string Lower-cased email or empty string when invalid.
	 */
	function sanitize_email( $email ): string {
		$email = strtolower( sanitize_text_field( $email ) );
		if ( strlen( $email ) > 190 ) {
			return '';
		}
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Sanitizes a key: lowercase alphanumerics, dashes and underscores.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ): string {
		if ( ! is_scalar( $key ) ) {
			return '';
		}
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Converts a value to a non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ): int {
		if ( ! is_scalar( $value ) ) {
			return 0;
		}
		return abs( (int) $value );
	}
}

/*
 * -----------------------------------------------------------------------------
 * Application helpers.
 * -----------------------------------------------------------------------------
 */

/**
 * Builds an absolute application URL.
 *
 * @param string $path  Path relative to the application root (e.g. "/users").
 * @param array  $query Optional query arguments.
 * @return string Unescaped URL — escape with esc_url() at output.
 */
function ov_url( string $path = '/', array $query = array() ): string {
	$base = rtrim( (string) Config::get( 'app_url', '' ), '/' );
	$url  = $base . '/' . ltrim( $path, '/' );
	if ( ! empty( $query ) ) {
		$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}
	return $url;
}

/**
 * Builds a versioned asset URL (cache busting by file mtime).
 *
 * @param string $path Path relative to public/assets.
 * @return string
 */
function ov_asset( string $path ): string {
	$file    = OPTIVIO_ROOT . '/public/assets/' . ltrim( $path, '/' );
	$version = is_file( $file ) ? (string) filemtime( $file ) : OPTIVIO_VERSION;
	return ov_url( '/assets/' . ltrim( $path, '/' ), array( 'v' => $version ) );
}

/**
 * Returns the hidden CSRF input field HTML.
 *
 * @return string Safe HTML.
 */
function ov_csrf_field(): string {
	return '<input type="hidden" name="_ov_csrf" value="' . esc_attr( Csrf::token() ) . '">';
}

/**
 * Encodes data as JSON that is safe to embed inside a <script type="application/json"> tag.
 *
 * @param mixed $data Data to encode.
 * @return string
 */
function ov_json_for_html( $data ): string {
	$json = json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
	return false === $json ? '{}' : $json;
}

/**
 * Formats a UTC datetime string for display in the application timezone.
 *
 * @param string|null $utc    UTC datetime (Y-m-d H:i:s).
 * @param string      $format PHP date format.
 * @return string
 */
function ov_datetime( ?string $utc, string $format = 'd.m.Y H:i' ): string {
	if ( null === $utc || '' === $utc ) {
		return '—';
	}
	try {
		$date = new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) );
		return $date->setTimezone( ov_timezone() )->format( $format );
	} catch ( Exception $e ) {
		return '—';
	}
}

/**
 * Returns a human friendly relative time (Romanian) for a UTC datetime.
 *
 * @param string|null $utc UTC datetime.
 * @return string
 */
function ov_time_ago( ?string $utc ): string {
	if ( null === $utc || '' === $utc ) {
		return '—';
	}
	$diff = time() - strtotime( $utc . ' UTC' );
	if ( $diff < 60 ) {
		return 'acum';
	}
	if ( $diff < 3600 ) {
		return sprintf( 'acum %d min', (int) floor( $diff / 60 ) );
	}
	if ( $diff < 86400 ) {
		return sprintf( 'acum %d h', (int) floor( $diff / 3600 ) );
	}
	if ( $diff < 604800 ) {
		return sprintf( 'acum %d zile', (int) floor( $diff / 86400 ) );
	}
	return ov_datetime( $utc, 'd.m.Y' );
}

/**
 * Returns the configured application timezone.
 *
 * @return DateTimeZone
 */
function ov_timezone(): DateTimeZone {
	static $tz = null;
	if ( null === $tz ) {
		try {
			$tz = new DateTimeZone( (string) Config::get( 'app_timezone', 'Europe/Bucharest' ) );
		} catch ( Exception $e ) {
			$tz = new DateTimeZone( 'UTC' );
		}
	}
	return $tz;
}

/**
 * Formats a number using Romanian conventions.
 *
 * @param float|int $number   Number.
 * @param int       $decimals Decimals.
 * @return string
 */
function ov_number( $number, int $decimals = 0 ): string {
	return number_format( (float) $number, $decimals, ',', '.' );
}

/**
 * Formats a compact number (1.2K, 3.4M).
 *
 * @param float|int $number Number.
 * @return string
 */
function ov_compact_number( $number ): string {
	$number = (float) $number;
	if ( abs( $number ) >= 1000000 ) {
		return ov_number( $number / 1000000, 1 ) . 'M';
	}
	if ( abs( $number ) >= 10000 ) {
		return ov_number( $number / 1000, 1 ) . 'K';
	}
	return ov_number( $number, 0 );
}

/**
 * Returns the initials for an avatar placeholder.
 *
 * @param string $name Full name.
 * @return string
 */
function ov_initials( string $name ): string {
	$words    = preg_split( '/\s+/u', trim( $name ) );
	$initials = '';
	foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
		$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}
	return '' !== $initials ? $initials : '?';
}

/**
 * Masks a secret for display (only the last 4 characters are visible).
 *
 * @param string|null $hint Last characters of the secret.
 * @return string
 */
function ov_mask_secret( ?string $hint ): string {
	if ( null === $hint || '' === $hint ) {
		return '';
	}
	return '••••••••' . $hint;
}

/**
 * Generates a URL-safe slug (handles Romanian diacritics).
 *
 * @param string $text Source text.
 * @return string
 */
function ov_slugify( string $text ): string {
	$map  = array(
		'ă' => 'a',
		'â' => 'a',
		'î' => 'i',
		'ș' => 's',
		'ş' => 's',
		'ț' => 't',
		'ţ' => 't',
		'Ă' => 'a',
		'Â' => 'a',
		'Î' => 'i',
		'Ș' => 's',
		'Ş' => 's',
		'Ț' => 't',
		'Ţ' => 't',
	);
	$text = strtr( $text, $map );
	$text = strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $text ) );
	$text = trim( $text, '-' );
	return '' !== $text ? substr( $text, 0, 170 ) : 'afacere';
}

/**
 * Converts a binary IP (inet_pton) to a printable string.
 *
 * @param string|null $binary Binary IP.
 * @return string
 */
function ov_ip_to_string( ?string $binary ): string {
	if ( null === $binary || '' === $binary ) {
		return '—';
	}
	$ip = @inet_ntop( $binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input returns false.
	return false === $ip ? '—' : $ip;
}

/**
 * Returns the Romanian label for a business model key.
 *
 * @param string $key Business model key.
 * @return string
 */
function ov_business_model_label( string $key ): string {
	$labels = ov_business_models();
	return $labels[ $key ] ?? $key;
}

/**
 * Returns all supported business models.
 *
 * @return array<string,string>
 */
function ov_business_models(): array {
	return array(
		'emergency_service' => 'Intervenții de urgență (nerecurente)',
		'recurring_service' => 'Servicii recurente',
		'local_service'     => 'Servicii locale programate',
		'b2b'               => 'B2B',
		'ecommerce'         => 'E-commerce',
		'lead_generation'   => 'Generare lead-uri',
		'publisher'         => 'Publisher / conținut (AdSense)',
		'other'             => 'Altele',
	);
}

/**
 * Returns " is-invalid" when the field has a validation error.
 *
 * @param array  $errors Errors.
 * @param string $field  Field.
 * @return string
 */
function ov_invalid( array $errors, string $field ): string {
	return isset( $errors[ $field ] ) ? ' is-invalid' : '';
}

/**
 * Echoes the escaped invalid-feedback element for a field.
 *
 * @param array  $errors Errors.
 * @param string $field  Field.
 * @return void
 */
function ov_field_error( array $errors, string $field ): void {
	if ( isset( $errors[ $field ] ) ) {
		echo '<div class="invalid-feedback">' . esc_html( $errors[ $field ] ) . '</div>';
	}
}

/**
 * Returns the value for a form field: old input first, then the entity value.
 *
 * @param array      $old    Old input.
 * @param array|null $entity Entity row.
 * @param string     $field  Field.
 * @param mixed      $fallback Default.
 * @return string
 */
function ov_value( array $old, ?array $entity, string $field, $fallback = '' ): string {
	if ( array_key_exists( $field, $old ) ) {
		return (string) $old[ $field ];
	}
	if ( null !== $entity && array_key_exists( $field, $entity ) && null !== $entity[ $field ] ) {
		return (string) $entity[ $field ];
	}
	return (string) $fallback;
}

/**
 * Minimal, safe Markdown → HTML (text is escaped FIRST; supports ##/### headings, **bold**,
 * - / 1. lists and paragraphs). Used for AI summaries.
 *
 * @param string $markdown Markdown.
 * @return string Safe HTML.
 */
function ov_markdown( string $markdown ): string {
	$html  = '';
	$list  = '';
	$lines = preg_split( '/\R/', esc_html( $markdown ) );
	$close = static function () use ( &$html, &$list ): void {
		if ( '' !== $list ) {
			$html .= '</' . $list . '>';
			$list  = '';
		}
	};
	foreach ( (array) $lines as $line ) {
		$line = trim( (string) $line );
		$line = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line );
		if ( preg_match( '/^(#{2,4})\s+(.+)$/', $line, $m ) ) {
			$close();
			$level = min( 5, strlen( $m[1] ) + 1 );
			$html .= '<h' . $level . ' class="h6 fw-semibold mt-3">' . $m[2] . '</h' . $level . '>';
		} elseif ( preg_match( '/^(?:[-*]|\d+\.)\s+(.+)$/', $line, $m ) ) {
			$type = preg_match( '/^\d+\./', $line ) ? 'ol' : 'ul';
			if ( $list !== $type ) {
				$close();
				$html .= '<' . $type . '>';
				$list  = $type;
			}
			$html .= '<li>' . $m[1] . '</li>';
		} elseif ( '' === $line ) {
			$close();
		} else {
			$close();
			$html .= '<p>' . $line . '</p>';
		}
	}
	$close();
	return $html;
}

/**
 * Prints a "How to set up" button that opens the integration guide pop-up (modal printed by the layout).
 *
 * @param string $key   Guide key (see Services\Integration_Guides::all()).
 * @param string $label Button label; empty = icon only.
 * @return void
 */
function ov_guide_button( string $key, string $label = 'Cum configurez?' ): void {
	if ( null === \OptiVio\Services\Integration_Guides::get( $key ) ) {
		return;
	}
	$id = \OptiVio\Services\Integration_Guides::use( $key );
	echo '<button type="button" class="btn btn-sm btn-link ov-guide-btn p-0" data-bs-toggle="modal" data-bs-target="#' . esc_attr( $id ) . '" aria-label="' . esc_attr( 'Ghid de configurare' ) . '">'
		. '<i class="bi bi-question-circle" aria-hidden="true"></i>' . ( '' !== $label ? ' <span>' . esc_html( $label ) . '</span>' : '' ) . '</button>';
}

/**
 * Escapes a help text, then allows only **bold** and `code` markers.
 *
 * @param string $text Help text.
 * @return string Safe HTML.
 */
function ov_help_text( string $text ): string {
	$html = esc_html( $text );
	$html = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
	return (string) preg_replace( '/`(.+?)`/', '<span class="ov-kbd">$1</span>', $html );
}
