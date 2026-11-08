<?php
/**
 * OptiVio — Deterministic traffic attribution (channel + device) from first-party data.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Attribution rules (ordered; first match wins).
 */
final class Attribution {

	/**
	 * Channels and labels.
	 */
	public const CHANNELS = array(
		'google_ads' => 'Google Ads',
		'organic'    => 'Organic',
		'gbp'        => 'Google Business',
		'social'     => 'Social',
		'referral'   => 'Referral',
		'direct'     => 'Direct',
		'email'      => 'Email',
		'other'      => 'Altă sursă',
	);

	private const SEARCH_ENGINES = '/(^|\.)(google\.[a-z.]+|bing\.com|yahoo\.com|duckduckgo\.com|yandex\.[a-z]+|ecosia\.org|search\.brave\.com)$/';
	private const SOCIAL         = '/(^|\.)(facebook\.com|fb\.com|instagram\.com|tiktok\.com|linkedin\.com|lnkd\.in|youtube\.com|t\.co|x\.com|twitter\.com|pinterest\.[a-z.]+|reddit\.com|whatsapp\.com)$/';

	/**
	 * Resolves the channel.
	 *
	 * @param array  $a         Attribution: gclid, gbraid, wbraid, utm_source, utm_medium, referrer.
	 * @param string $site_host Host of the site (to ignore internal referrers).
	 * @return string Channel key.
	 */
	public static function channel( array $a, string $site_host ): string {
		$source   = strtolower( (string) ( $a['utm_source'] ?? '' ) );
		$medium   = strtolower( (string) ( $a['utm_medium'] ?? '' ) );
		$ref_host = strtolower( (string) parse_url( (string) ( $a['referrer'] ?? '' ), PHP_URL_HOST ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$ref_host = (string) preg_replace( '/^www\./', '', $ref_host );
		$site     = (string) preg_replace( '/^www\./', '', strtolower( $site_host ) );

		if ( ! empty( $a['gclid'] ) || ! empty( $a['gbraid'] ) || ! empty( $a['wbraid'] ) ) {
			return 'google_ads';
		}
		if ( in_array( $medium, array( 'cpc', 'ppc', 'paid', 'paidsearch', 'paid_search', 'sem' ), true ) && ( '' === $source || false !== strpos( $source, 'google' ) || false !== strpos( $source, 'adwords' ) ) ) {
			return 'google_ads';
		}
		if ( preg_match( '/\b(gbp|gmb|google_business|googlemybusiness|business_profile|maps)\b/', $source . ' ' . $medium ) || 0 === strpos( $ref_host, 'maps.google.' ) ) {
			return 'gbp';
		}
		if ( in_array( $medium, array( 'email', 'e-mail', 'newsletter' ), true ) ) {
			return 'email';
		}
		if ( in_array( $medium, array( 'social', 'social-media', 'paid_social', 'cpm' ), true ) || preg_match( self::SOCIAL, $source . '.com' ) || ( '' !== $ref_host && preg_match( self::SOCIAL, $ref_host ) ) ) {
			return 'social';
		}
		if ( 'organic' === $medium || ( '' !== $ref_host && preg_match( self::SEARCH_ENGINES, $ref_host ) ) ) {
			return 'organic';
		}
		if ( '' !== $source || '' !== $medium ) {
			return 'other';
		}
		if ( '' !== $ref_host && $ref_host !== $site && ! str_ends_with( $ref_host, '.' . $site ) ) {
			return 'referral';
		}
		return 'direct';
	}

	/**
	 * Normalizes the device type (explicit value wins, else derived from the user agent).
	 *
	 * @param string $device     Device reported by the plugin.
	 * @param string $user_agent User agent.
	 * @return string mobile|tablet|desktop|unknown
	 */
	public static function device( string $device, string $user_agent ): string {
		$device = strtolower( $device );
		if ( in_array( $device, array( 'mobile', 'tablet', 'desktop' ), true ) ) {
			return $device;
		}
		if ( '' === $user_agent ) {
			return 'unknown';
		}
		if ( preg_match( '/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $user_agent ) ) {
			return 'tablet';
		}
		if ( preg_match( '/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $user_agent ) ) {
			return 'mobile';
		}
		return 'desktop';
	}
}
