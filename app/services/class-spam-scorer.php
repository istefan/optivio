<?php
/**
 * OptiVio — Deterministic spam scoring for form entries (zero AI).
 *
 * Combines the plugin's own signals (honeypot, fill time, blocked patterns) with
 * server-side heuristics: links, blocked words, foreign scripts, disposable emails,
 * invalid contacts, burst from the same IP, duplicate submissions.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;
use OptiVio\Repositories\Settings_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Spam scorer.
 */
final class Spam_Scorer {

	/**
	 * Reason labels.
	 */
	public const REASONS = array(
		'honeypot'        => 'Câmp capcană (honeypot) completat',
		'too_fast'        => 'Completat prea repede',
		'very_fast'       => 'Completat aproape instant',
		'plugin_pattern'  => 'Pattern blocat de plugin',
		'links'           => 'Conține linkuri',
		'many_links'      => 'Multe linkuri',
		'blocked_word'    => 'Cuvinte blocate',
		'foreign_script'  => 'Alfabet străin (chirilic/asiatic)',
		'disposable'      => 'Email de unică folosință',
		'invalid_email'   => 'Email invalid',
		'invalid_phone'   => 'Telefon invalid',
		'all_caps'        => 'Text scris integral cu majuscule',
		'empty_message'   => 'Fără mesaj și fără telefon',
		'ip_burst'        => 'Multe trimiteri de pe același IP',
		'duplicate'       => 'Retrimitere identică (aceeași persoană, 24h)',
	);

	/**
	 * Scores an entry.
	 *
	 * @param array       $fields       label => value (plain).
	 * @param array       $plugin_flags honeypot (bool), fill_seconds (int|null), blocked_patterns (int).
	 * @param int         $site_id      Site id.
	 * @param string|null $ip_hash      HMAC of visitor IP.
	 * @param string|null $contact_hash HMAC of email/phone.
	 * @return array{score:int,status:string,reasons:array<int,string>}
	 */
	public static function score( array $fields, array $plugin_flags, int $site_id, ?string $ip_hash, ?string $contact_hash ): array {
		$points  = array();
		$text    = implode( "\n", array_map( 'strval', $fields ) );
		$lower   = mb_strtolower( $text );
		$min     = max( 1, (int) Settings_Repository::get( 'spam.min_fill_seconds', 3 ) );
		$max_lnk = max( 0, (int) Settings_Repository::get( 'spam.max_links', 2 ) );

		if ( ! empty( $plugin_flags['honeypot'] ) ) {
			$points['honeypot'] = 100;
		}
		if ( isset( $plugin_flags['fill_seconds'] ) && null !== $plugin_flags['fill_seconds'] ) {
			$seconds = (float) $plugin_flags['fill_seconds'];
			if ( $seconds < $min / 2 ) {
				$points['very_fast'] = 60;
			} elseif ( $seconds < $min ) {
				$points['too_fast'] = 35;
			}
		}
		if ( ! empty( $plugin_flags['blocked_patterns'] ) ) {
			$points['plugin_pattern'] = 30;
		}

		$links = preg_match_all( '#(https?://|www\.|\[url|<a\s)#i', $text );
		if ( $links > $max_lnk ) {
			$points['many_links'] = 35;
		} elseif ( $links > 0 ) {
			$points['links'] = 15;
		}

		foreach ( (array) Settings_Repository::get( 'spam.blocked_words', array() ) as $word ) {
			$word = mb_strtolower( trim( (string) $word ) );
			if ( '' !== $word && false !== mb_strpos( $lower, $word ) ) {
				$points['blocked_word'] = 40;
				break;
			}
		}

		if ( preg_match( '/[\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}\x{0600}-\x{06FF}]/u', $text ) ) {
			$points['foreign_script'] = 30;
		}

		$email = self::find_email( $fields );
		if ( null !== $email ) {
			if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$points['invalid_email'] = 15;
			} else {
				$domain = strtolower( substr( (string) strrchr( $email, '@' ), 1 ) );
				if ( in_array( $domain, array_map( 'strtolower', (array) Settings_Repository::get( 'spam.disposable_domains', array() ) ), true ) ) {
					$points['disposable'] = 30;
				}
			}
		}

		$phone = self::find_phone( $fields );
		if ( null !== $phone && strlen( (string) preg_replace( '/\D/', '', $phone ) ) < 9 ) {
			$points['invalid_phone'] = 10;
		}

		$letters = (string) preg_replace( '/[^\p{L}]/u', '', $text );
		if ( mb_strlen( $letters ) >= 20 && mb_strtoupper( $letters ) === $letters ) {
			$points['all_caps'] = 10;
		}

		$message = self::find_message( $fields );
		if ( null === $phone && mb_strlen( trim( (string) $message ) ) < 5 ) {
			$points['empty_message'] = 10;
		}

		$db = Database::instance();
		if ( null !== $ip_hash ) {
			$recent = (int) $db->get_var(
				'SELECT COUNT(*) FROM form_entries WHERE site_id = ? AND ip_hash = ? AND received_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR',
				array( $site_id, $ip_hash )
			);
			if ( $recent >= max( 1, (int) Settings_Repository::get( 'spam.ip_hourly_limit', 3 ) ) ) {
				$points['ip_burst'] = 30;
			}
		}
		if ( null !== $contact_hash ) {
			$dup = (int) $db->get_var(
				'SELECT COUNT(*) FROM form_entries WHERE site_id = ? AND contact_hash = ? AND received_at > UTC_TIMESTAMP() - INTERVAL 1 DAY',
				array( $site_id, $contact_hash )
			);
			if ( $dup > 0 ) {
				$points['duplicate'] = 10;
			}
		}

		$score = min( 100, array_sum( $points ) );
		return array(
			'score'   => $score,
			'status'  => self::status_for( $score ),
			'reasons' => array_keys( $points ),
		);
	}

	/**
	 * Maps a score to a status using the configured thresholds.
	 *
	 * @param int $score Score.
	 * @return string clean|suspect|spam
	 */
	public static function status_for( int $score ): string {
		if ( $score >= (int) Settings_Repository::get( 'spam.threshold_spam', 70 ) ) {
			return 'spam';
		}
		if ( $score >= (int) Settings_Repository::get( 'spam.threshold_suspect', 40 ) ) {
			return 'suspect';
		}
		return 'clean';
	}

	/**
	 * Finds the first email-like value.
	 *
	 * @param array $fields Fields.
	 * @return string|null
	 */
	public static function find_email( array $fields ): ?string {
		foreach ( $fields as $label => $value ) {
			$value = trim( (string) $value );
			if ( preg_match( '/e-?mail/i', (string) $label ) || ( str_contains( $value, '@' ) && ! str_contains( $value, ' ' ) ) ) {
				return '' !== $value ? strtolower( $value ) : null;
			}
		}
		return null;
	}

	/**
	 * Finds the first phone-like value.
	 *
	 * @param array $fields Fields.
	 * @return string|null
	 */
	public static function find_phone( array $fields ): ?string {
		foreach ( $fields as $label => $value ) {
			$value = trim( (string) $value );
			if ( '' !== $value && ( preg_match( '/telefon|phone|mobil|tel\b/i', (string) $label ) || preg_match( '/^\+?[0-9 ()\-.]{9,20}$/', $value ) ) ) {
				return $value;
			}
		}
		return null;
	}

	/**
	 * Finds the message field (longest free-text value).
	 *
	 * @param array $fields Fields.
	 * @return string|null
	 */
	private static function find_message( array $fields ): ?string {
		$best = null;
		foreach ( $fields as $label => $value ) {
			if ( preg_match( '/mesaj|message|detalii|descriere|comentari/i', (string) $label ) ) {
				return (string) $value;
			}
			if ( null === $best || mb_strlen( (string) $value ) > mb_strlen( $best ) ) {
				$best = (string) $value;
			}
		}
		return $best;
	}
}
