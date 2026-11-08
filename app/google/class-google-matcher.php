<?php
/**
 * OptiVio — Matches discovered Google properties to a site (Site Kit–style "Connect").
 *
 * Uses the properties discovered for every connected Google account (Google_Discovery) and
 * scores them against the site host (www ignored): Search Console URL / domain property,
 * GA4 web stream URLs, GTM container domains, Business Profile website, AdSense sites.
 * Google Ads has no domain: a single accessible customer is suggested, but never auto-connected.
 * Deterministic, no API call.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Google;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Property matcher.
 */
final class Google_Matcher {

	/**
	 * Minimum score for the one-click "connect all".
	 */
	public const AUTO_SCORE = 60;

	/**
	 * Providers matched (PageSpeed needs no account).
	 */
	public const PROVIDERS = array( 'gsc', 'ga4', 'gtm', 'ads', 'adsense', 'gbp' );

	/**
	 * All discovered properties of the active Google accounts, per provider.
	 *
	 * @return array<string,array<int,array{credential_id:int,email:string,id:string,label:string,uris:array}>>
	 */
	public static function candidates(): array {
		$out  = array_fill_keys( self::PROVIDERS, array() );
		$rows = Database::instance()->get_results( "SELECT id, account_email, meta FROM api_credentials WHERE scope = 'global' AND provider = 'google_oauth' AND status = 'active' ORDER BY id" );
		foreach ( $rows as $row ) {
			$meta = (array) ( json_decode( (string) $row['meta'], true ) ?: array() );
			foreach ( self::PROVIDERS as $provider ) {
				foreach ( (array) ( $meta['properties'][ $provider ] ?? array() ) as $p ) {
					if ( empty( $p['id'] ) ) {
						continue;
					}
					$out[ $provider ][] = array(
						'credential_id' => (int) $row['id'],
						'email'         => (string) $row['account_email'],
						'id'            => (string) $p['id'],
						'label'         => (string) ( $p['label'] ?? $p['id'] ),
						'uris'          => (array) ( $p['uris'] ?? array() ),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Whether any account was discovered at least once.
	 *
	 * @return bool
	 */
	public static function has_discovery(): bool {
		return null !== Database::instance()->get_var( "SELECT id FROM api_credentials WHERE scope = 'global' AND provider = 'google_oauth' AND status = 'active' AND JSON_EXTRACT(meta, '$.discovered_at') IS NOT NULL LIMIT 1" );
	}

	/**
	 * Best candidate per provider for a site.
	 *
	 * @param string $site_url   Site URL.
	 * @param array  $candidates candidates() result.
	 * @return array<string,array{match:?array,score:int,reason:string}>
	 */
	public static function suggest( string $site_url, array $candidates ): array {
		$host = self::host( $site_url );
		$out  = array();
		foreach ( self::PROVIDERS as $provider ) {
			$best = null;
			$top  = 0;
			$why  = '';
			foreach ( (array) ( $candidates[ $provider ] ?? array() ) as $c ) {
				[ $score, $reason ] = self::score( $provider, $c, $host );
				if ( $score > $top ) {
					$best = $c;
					$top  = $score;
					$why  = $reason;
				}
			}
			if ( null === $best && 'ads' === $provider && 1 === count( (array) ( $candidates['ads'] ?? array() ) ) ) {
				$best = $candidates['ads'][0];
				$top  = 30;
				$why  = 'singurul cont Ads accesibil — verificați înainte de conectare';
			}
			$out[ $provider ] = array(
				'match'  => $best,
				'score'  => $top,
				'reason' => $why,
			);
		}
		return $out;
	}

	/**
	 * Score of one candidate.
	 *
	 * @param string $provider Provider.
	 * @param array  $c        Candidate.
	 * @param string $host     Site host (no www).
	 * @return array{0:int,1:string}
	 */
	private static function score( string $provider, array $c, string $host ): array {
		if ( '' === $host ) {
			return array( 0, '' );
		}
		if ( 'gsc' === $provider ) {
			$id = strtolower( $c['id'] );
			if ( 'sc-domain:' . $host === $id ) {
				return array( 100, 'proprietate de domeniu ' . $id );
			}
			if ( 0 === strpos( $id, 'http' ) && self::host( $id ) === $host ) {
				return array( 95, 'proprietate URL ' . $c['id'] );
			}
			if ( 0 === strpos( $id, 'sc-domain:' ) && str_ends_with( $host, '.' . substr( $id, 10 ) ) ) {
				return array( 80, 'domeniul părinte ' . $id );
			}
			return array( 0, '' );
		}
		foreach ( (array) $c['uris'] as $uri ) {
			if ( self::host( (string) $uri ) === $host ) {
				$what = array(
					'ga4'     => 'fluxul web ',
					'gtm'     => 'domeniul containerului ',
					'gbp'     => 'site-ul fișei ',
					'adsense' => 'site-ul AdSense ',
				);
				return array( 90, ( $what[ $provider ] ?? '' ) . $uri );
			}
		}
		$label = strtolower( $c['label'] );
		if ( '' !== $label && str_contains( $label, $host ) ) {
			return array( 60, 'numele conține ' . $host );
		}
		$name = explode( '.', $host )[0];
		if ( strlen( $name ) >= 5 && str_contains( (string) preg_replace( '/[^a-z0-9]/', '', $label ), (string) preg_replace( '/[^a-z0-9]/', '', $name ) ) ) {
			return array( 40, 'nume asemănător (' . $name . ')' );
		}
		return array( 0, '' );
	}

	/**
	 * Host without scheme, port path and www.
	 *
	 * @param string $url URL or domain.
	 * @return string
	 */
	public static function host( string $url ): string {
		$url  = trim( strtolower( $url ) );
		$url  = (string) preg_replace( '#^sc-domain:#', '', $url );
		$host = preg_match( '#^[a-z]+://#', $url ) ? (string) parse_url( $url, PHP_URL_HOST ) : (string) strtok( $url, '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		return (string) preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Finds a candidate by credential + id (prevents connecting a property the account cannot see).
	 *
	 * @param string $provider      Provider.
	 * @param int    $credential_id Account.
	 * @param string $id            Property id.
	 * @return array|null
	 */
	public static function find( string $provider, int $credential_id, string $id ): ?array {
		foreach ( (array) ( self::candidates()[ $provider ] ?? array() ) as $c ) {
			if ( $c['credential_id'] === $credential_id && $c['id'] === $id ) {
				return $c;
			}
		}
		return null;
	}
}
