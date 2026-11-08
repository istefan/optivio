<?php
/**
 * HTTP client towards the OptiVio API.
 *
 * @package   OptiVio_Connector
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signed JSON client.
 */
final class OVC_Client {

	/**
	 * POSTs a signed JSON payload.
	 *
	 * @param string $path    App-relative path, e.g. "/api/connector/v1/events".
	 * @param array  $payload Payload.
	 * @return array{code:int,body:array|null,error:string}
	 */
	public static function post( $path, array $payload ) {
		if ( ! OVC_Settings::is_configured() ) {
			return array(
				'code'  => 0,
				'body'  => null,
				'error' => 'not_configured',
			);
		}

		$c        = OVC_Settings::connection();
		$body     = wp_json_encode( $payload );
		$headers  = array_merge( array( 'Content-Type' => 'application/json' ), OVC_Signer::headers( 'POST', $path, $body ) );
		$response = wp_remote_post(
			$c['api_base'] . $path,
			array(
				'headers'     => $headers,
				'body'        => $body,
				'timeout'     => 15,
				'redirection' => 0,
				'user-agent'  => 'OptiVio-Connector/' . OVC_VERSION . '; ' . home_url(),
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'code'  => 0,
				'body'  => null,
				'error' => $response->get_error_message(),
			);
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return array(
			'code'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'  => is_array( $decoded ) ? $decoded : null,
			'error' => '',
		);
	}
}
