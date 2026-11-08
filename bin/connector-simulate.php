<?php
/**
 * OptiVio — Connector simulator (development / QA only).
 *
 * Sends correctly signed requests to the connector API exactly like the WordPress plugin will.
 *
 * Usage:
 *   php bin/connector-simulate.php --site=ID [--clicks=5] [--forms=3] [--spam=1] [--replay] [--base=http://optivio.test]
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

require __DIR__ . '/cli-bootstrap.php';

use OptiVio\Core\Config;
use OptiVio\Core\Database;
use OptiVio\Repositories\Sites_Repository;
use OptiVio\Security\Connector_Signature;

if ( ! Config::is_dev() ) {
	fwrite( STDERR, "Simulatorul rulează doar cu app_env = development.\n" ); // phpcs:ignore
	exit( 1 );
}

$ov_opts = ov_cli_options( $argv );
$ov_sid  = absint( $ov_opts['site'] ?? 0 );
$ov_key  = (string) Database::instance()->get_var( 'SELECT connector_key_id FROM sites WHERE id = ?', array( $ov_sid ) );
$ov_site = '' !== $ov_key ? Sites_Repository::find_by_connector_key( $ov_key ) : null;
if ( null === $ov_site ) {
	fwrite( STDERR, "Site inexistent sau fără cheie de conector activă.\n" ); // phpcs:ignore
	exit( 1 );
}
$ov_base   = rtrim( (string) ( $ov_opts['base'] ?? Config::get( 'app_url' ) ), '/' );
$ov_domain = (string) Database::instance()->get_var( 'SELECT url FROM sites WHERE id = ?', array( $ov_sid ) );

$ov_send = static function ( string $path, array $payload, array $extra = array() ) use ( $ov_base, $ov_key, $ov_site ): array {
	$body    = (string) json_encode( $payload, JSON_UNESCAPED_UNICODE );
	$headers = array_merge( Connector_Signature::headers( $ov_key, (string) $ov_site['secret'], 'POST', $path, $body ), $extra );
	$lines   = array( 'Content-Type: application/json' );
	foreach ( $headers as $name => $value ) {
		$lines[] = $name . ': ' . $value;
	}
	$ch = curl_init( $ov_base . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
	curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $lines,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 20,
		)
	);
	$response = (string) curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
	$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_getinfo
	return array( $status, $response, $headers );
};

$ov_uuid = static function (): string {
	$b    = random_bytes( 16 );
	$b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x40 );
	$b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 );
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
};

[ $ov_code, $ov_body ] = $ov_send(
	'/api/connector/v1/heartbeat',
	array(
		'plugin_version'    => '1.0.0',
		'wp_version'        => '6.8',
		'has_yoast'         => true,
		'has_gravity_forms' => true,
	)
);
fwrite( STDOUT, "heartbeat: {$ov_code} {$ov_body}\n" ); // phpcs:ignore

$ov_events = array();
$ov_terms  = array( 'instalator non stop', 'teava sparta', 'desfundare canalizare' );
for ( $i = 0; $i < absint( $ov_opts['clicks'] ?? 5 ); $i++ ) {
	$ov_ads      = 0 === $i % 2;
	$ov_events[] = array(
		'type'        => 'phone_click',
		'uuid'        => $ov_uuid(),
		'occurred_at' => gmdate( 'c', time() - mt_rand( 60, 7200 ) ),
		'phone'       => '0722 000 111',
		'page_url'    => $ov_domain . '/urgente/',
		'user_agent'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile/15E148',
		'ip'          => '86.12.34.' . mt_rand( 1, 250 ),
		'attribution' => $ov_ads
			? array(
				'gclid'        => 'Cj0KCQ' . bin2hex( random_bytes( 6 ) ),
				'utm_source'   => 'google',
				'utm_medium'   => 'cpc',
				'utm_campaign' => 'Urgente-Search',
				'utm_term'     => $ov_terms[ $i % 3 ],
				'landing_page' => $ov_domain . '/urgente/',
			)
			: array(
				'referrer'     => 'https://www.google.com/',
				'landing_page' => $ov_domain . '/',
			),
	);
}
for ( $i = 0; $i < absint( $ov_opts['forms'] ?? 3 ); $i++ ) {
	$ov_events[] = array(
		'type'        => 'form_entry',
		'entry_id'    => mt_rand( 100000, 999999 ),
		'form_id'     => 1,
		'form_title'  => 'Cerere ofertă',
		'occurred_at' => gmdate( 'c', time() - mt_rand( 60, 7200 ) ),
		'page_url'    => $ov_domain . '/contact/',
		'user_agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
		'ip'          => '86.12.35.' . mt_rand( 1, 250 ),
		'fields'      => array(
			'Nume'    => 'Maria Ionescu',
			'Telefon' => '0733 222 ' . mt_rand( 100, 999 ),
			'Email'   => 'maria' . mt_rand( 1, 99 ) . '@exemplu.ro',
			'Mesaj'   => 'Bună ziua, avem o scurgere la baie, puteți veni azi?',
		),
		'flags'       => array(
			'honeypot'     => false,
			'fill_seconds' => 42,
		),
		'attribution' => array(
			'utm_source'   => 'google',
			'utm_medium'   => 'organic',
			'landing_page' => $ov_domain . '/',
		),
	);
}
for ( $i = 0; $i < absint( $ov_opts['spam'] ?? 1 ); $i++ ) {
	$ov_events[] = array(
		'type'        => 'form_entry',
		'entry_id'    => mt_rand( 100000, 999999 ),
		'form_id'     => 1,
		'form_title'  => 'Cerere ofertă',
		'occurred_at' => gmdate( 'c', time() - 30 ),
		'page_url'    => $ov_domain . '/contact/',
		'ip'          => '45.88.1.1',
		'fields'      => array(
			'Nume'  => 'SEO Expert',
			'Email' => 'seo@mailinator.com',
			'Mesaj' => 'WE OFFER SEO SERVICES AND BACKLINKS https://spam.example https://spam2.example http://x.example',
		),
		'flags'       => array(
			'honeypot'     => true,
			'fill_seconds' => 0.8,
		),
		'attribution' => array(),
	);
}

[ $ov_code, $ov_body, $ov_headers ] = $ov_send( '/api/connector/v1/events', array( 'events' => $ov_events ) );
$ov_res = json_decode( $ov_body, true );
fwrite( STDOUT, sprintf( "events: %d stored=%s duplicates=%s invalid=%s\n", $ov_code, $ov_res['stored'] ?? '?', $ov_res['duplicates'] ?? '?', $ov_res['invalid'] ?? '?' ) ); // phpcs:ignore
foreach ( (array) ( $ov_res['results'] ?? array() ) as $ov_r ) {
	if ( isset( $ov_r['spam_status'] ) ) {
		fwrite( STDOUT, '  form #' . $ov_r['index'] . ' => ' . $ov_r['spam_status'] . "\n" ); // phpcs:ignore
	}
}

if ( isset( $ov_opts['replay'] ) ) {
	[ $ov_code, $ov_body ] = $ov_send( '/api/connector/v1/events', array( 'events' => $ov_events ), $ov_headers );
	fwrite( STDOUT, "replay same signature: {$ov_code} {$ov_body}\n" ); // phpcs:ignore
	[ $ov_code, $ov_body ] = $ov_send( '/api/connector/v1/events', array( 'events' => $ov_events ) );
	fwrite( STDOUT, 'resend same events (new signature): ' . $ov_code . ' ' . substr( $ov_body, 0, 60 ) . "\n" ); // phpcs:ignore
	[ $ov_code, $ov_body ] = $ov_send( '/api/connector/v1/events', array( 'events' => $ov_events ), array( 'X-OptiVio-Signature' => str_repeat( 'a', 64 ) ) );
	fwrite( STDOUT, "bad signature: {$ov_code} {$ov_body}\n" ); // phpcs:ignore
}
