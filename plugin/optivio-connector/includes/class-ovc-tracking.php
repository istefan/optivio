<?php
/**
 * Front-end tracking: attribution cookie script + tel: click endpoint.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tracking module.
 */
final class OVC_Tracking {

	/**
	 * Max tel: clicks accepted per visitor IP per 10 minutes.
	 */
	const IP_LIMIT = 20;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Enqueues the tracking script (footer, deferred, ~2 KB).
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! OVC_Settings::is_configured() ) {
			return;
		}
		wp_enqueue_script( 'ovc-tracking', OVC_URL . 'assets/ovc-tracking.js', array(), OVC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'ovc-tracking',
			'OVC',
			array(
				'endpoint'   => esc_url_raw( rest_url( 'optivio/v1/track' ) ),
				'cookieDays' => 90,
				'host'       => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		);
	}

	/**
	 * Registers the public tracking route.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'optivio/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'track' ),
				'permission_callback' => '__return_true', // Public by design: anonymous visitors; validated + rate limited below.
			)
		);
	}

	/**
	 * Receives a tel: click from the browser and queues it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function track( $request ) {
		$ip     = OVC_Settings::visitor_ip();
		$bucket = 'ovc_rl_' . md5( $ip );
		$hits   = (int) get_transient( $bucket );
		if ( $hits >= self::IP_LIMIT ) {
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}
		set_transient( $bucket, $hits + 1, 600 );

		$data  = json_decode( (string) $request->get_body(), true );
		$data  = is_array( $data ) ? $data : array();
		$uuid  = strtolower( sanitize_text_field( (string) ( $data['uuid'] ?? '' ) ) );
		$phone = substr( preg_replace( '/[^0-9+]/', '', (string) ( $data['phone'] ?? '' ) ), 0, 40 );
		$page  = esc_url_raw( (string) ( $data['page_url'] ?? '' ), array( 'http', 'https' ) );

		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid ) || strlen( $phone ) < 3 ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		// Only pages of this site are accepted.
		if ( '' !== $page && wp_parse_url( $page, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$ua = OVC_Settings::user_agent();
		OVC_Queue::push(
			'events',
			array(
				'type'        => 'phone_click',
				'uuid'        => $uuid,
				'occurred_at' => gmdate( 'c' ),
				'phone'       => $phone,
				'page_url'    => $page,
				'user_agent'  => $ua,
				'device'      => OVC_Attribution::device( $ua ),
				'ip'          => $ip,
				'attribution' => OVC_Attribution::current( is_array( $data['attribution'] ?? null ) ? $data['attribution'] : null ),
			)
		);
		return new WP_REST_Response( array( 'ok' => true ), 202 );
	}
}
