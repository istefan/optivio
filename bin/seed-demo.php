<?php
/**
 * OptiVio — Demo data seeder (development only).
 *
 * Usage: php bin/seed-demo.php          Create demo businesses, sites and 120 days of events.
 *        php bin/seed-demo.php --purge  Remove all demo data (businesses with slug "demo-*").
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

require __DIR__ . '/cli-bootstrap.php';

use OptiVio\Core\Crypto;
use OptiVio\Core\Database;

$ov_db   = Database::instance();
$ov_opts = ov_cli_options( $argv );

$ov_db->query( "DELETE FROM notifications WHERE type = 'demo'" );
$ov_deleted = $ov_db->query( "DELETE FROM businesses WHERE slug LIKE 'demo-%'" )->rowCount();
if ( isset( $ov_opts['purge'] ) ) {
	fwrite( STDOUT, "Date demo șterse ({$ov_deleted} afaceri).\n" ); // phpcs:ignore
	exit( 0 );
}

mt_srand( 20261108 );

$ov_businesses = array(
	array(
		'name'           => 'Instalatori Non-Stop București',
		'slug'           => 'demo-instalatori-non-stop',
		'business_model' => 'emergency_service',
		'cui'            => 'RO41234567',
		'service_area'   => 'București, Ilfov',
		'description'    => 'Intervenții de urgență 24/7 pentru țevi sparte, desfundări canalizare, scurgeri și avarii la instalațiile sanitare și termice. Echipe mobile în toate sectoarele.',
		'usp_text'       => 'Ajungem în maximum 45 de minute, non-stop, cu preț fix comunicat la telefon înainte de deplasare.',
		'avg_job_value'  => 650,
		'daily_capacity' => 14,
		'weight'         => 1.0,
	),
	array(
		'name'           => 'Clinica Dentară Zâmbet',
		'slug'           => 'demo-clinica-zambet',
		'business_model' => 'local_service',
		'cui'            => 'RO38765432',
		'service_area'   => 'Cluj-Napoca',
		'description'    => 'Clinică stomatologică cu implantologie, ortodonție și estetică dentară.',
		'usp_text'       => '',
		'avg_job_value'  => 1200,
		'daily_capacity' => 20,
		'weight'         => 0.55,
	),
	array(
		'name'           => 'Termo Service Pro',
		'slug'           => 'demo-termo-service-pro',
		'business_model' => 'recurring_service',
		'cui'            => '',
		'service_area'   => 'Iași',
		'description'    => '',
		'usp_text'       => '',
		'avg_job_value'  => 350,
		'daily_capacity' => 8,
		'weight'         => 0.35,
	),
);

$ov_campaigns = array( 'Urgente-Instalatii-Search', 'Desfundari-Bucuresti', 'Brand', 'PMax-Servicii' );
$ov_terms     = array( 'instalator non stop', 'teava sparta', 'desfundare canalizare', 'instalator sector 3', 'reparatii centrala', 'instalator urgent' );
$ov_channels  = array( 'google_ads', 'google_ads', 'google_ads', 'organic', 'organic', 'gbp', 'direct', 'referral' );
$ov_devices   = array( 'mobile', 'mobile', 'mobile', 'mobile', 'desktop', 'tablet' );
$ov_outcomes  = array( 'accepted', 'accepted', 'accepted', 'accepted', 'rejected_capacity', 'rejected_area', 'rejected_job_type', 'missed' );

$ov_total_events = 0;

foreach ( $ov_businesses as $ov_index => $ov_b ) {
	$ov_bid = $ov_db->insert(
		'businesses',
		array(
			'name'           => $ov_b['name'],
			'slug'           => $ov_b['slug'],
			'business_model' => $ov_b['business_model'],
			'cui'            => '' !== $ov_b['cui'] ? $ov_b['cui'] : null,
			'legal_name'     => $ov_b['name'] . ' SRL',
			'service_area'   => $ov_b['service_area'],
			'description'    => $ov_b['description'],
			'usp_text'       => '' !== $ov_b['usp_text'] ? $ov_b['usp_text'] : null,
			'usp_source'     => '' !== $ov_b['usp_text'] ? 'manual' : null,
			'avg_job_value'  => $ov_b['avg_job_value'],
			'daily_capacity' => $ov_b['daily_capacity'],
			'notes'          => 'Date demo generate de bin/seed-demo.php',
		)
	);

	if ( 0 === $ov_index ) {
		$ov_db->insert(
			'business_offerings',
			array(
				'business_id'      => $ov_bid,
				'name'             => 'Intervenție urgentă țeavă spartă',
				'is_emergency'     => 1,
				'avg_ticket_value' => 650,
			)
		);
	}

	$ov_domain = str_replace( 'demo-', '', $ov_b['slug'] ) . '.ro';
	$ov_sid    = $ov_db->insert(
		'sites',
		array(
			'business_id'      => $ov_bid,
			'name'             => $ov_domain,
			'url'              => 'https://www.' . $ov_domain,
			'domain'           => $ov_domain,
			'connector_status' => 'not_installed',
		)
	);
	if ( 0 === $ov_index ) {
		OptiVio\Repositories\Sites_Repository::rotate_connector_secret( $ov_sid );
		$ov_db->query( "UPDATE sites SET connector_status = 'connected', connector_last_seen_at = UTC_TIMESTAMP(), plugin_version = '1.0.0', has_yoast = 1, has_gravity_forms = 1 WHERE id = ?", array( $ov_sid ) );
		foreach ( array( 'gsc', 'ga4', 'ads' ) as $ov_provider ) {
			$ov_db->insert(
				'site_integrations',
				array(
					'site_id'  => $ov_sid,
					'provider' => $ov_provider,
					'status'   => 'active',
				)
			);
		}
	}

	for ( $ov_day = 119; $ov_day >= 0; $ov_day-- ) {
		$ov_weekday = (int) gmdate( 'N', time() - $ov_day * 86400 );
		$ov_trend   = 1 + ( 119 - $ov_day ) / 160;
		$ov_wave    = 1 + 0.35 * sin( $ov_day / 5 );
		$ov_base    = ( $ov_weekday >= 6 ? 4 : 7 ) * $ov_b['weight'] * $ov_trend * $ov_wave;
		$ov_phones  = max( 0, (int) round( $ov_base + mt_rand( -2, 3 ) ) );
		$ov_forms   = max( 0, (int) round( $ov_base * 0.45 + mt_rand( -1, 2 ) ) );

		for ( $ov_i = 0; $ov_i < $ov_phones + $ov_forms; $ov_i++ ) {
			$ov_hour    = mt_rand( 0, 100 ) < 80 ? mt_rand( 6, 20 ) : mt_rand( 0, 23 );
			$ov_time    = gmdate( 'Y-m-d', time() - $ov_day * 86400 ) . sprintf( ' %02d:%02d:%02d', max( 0, $ov_hour - 2 ), mt_rand( 0, 59 ), mt_rand( 0, 59 ) );
			if ( strtotime( $ov_time . ' UTC' ) > time() ) {
				continue;
			}
			$ov_channel = $ov_channels[ array_rand( $ov_channels ) ];
			$ov_is_ads  = 'google_ads' === $ov_channel;
			$ov_common  = array(
				'site_id'      => $ov_sid,
				'page_url'     => 'https://www.' . $ov_domain . '/' . ( $ov_is_ads ? 'urgente/' : 'servicii/' ),
				'landing_page' => 'https://www.' . $ov_domain . '/',
				'utm_source'   => $ov_is_ads ? 'google' : ( 'organic' === $ov_channel ? 'google' : null ),
				'utm_medium'   => $ov_is_ads ? 'cpc' : ( 'organic' === $ov_channel ? 'organic' : null ),
				'utm_campaign' => $ov_is_ads ? $ov_campaigns[ array_rand( $ov_campaigns ) ] : null,
				'utm_term'     => $ov_is_ads ? $ov_terms[ array_rand( $ov_terms ) ] : null,
				'channel'      => $ov_channel,
				'device_type'  => $ov_devices[ array_rand( $ov_devices ) ],
				'occurred_at'  => $ov_time,
			);

			if ( $ov_i < $ov_phones ) {
				$ov_click_id = $ov_db->insert(
					'phone_clicks',
					array_merge(
						$ov_common,
						array(
							'event_uuid'   => sprintf( '%08x-%04x-4%03x-a%03x-%012x', mt_rand(), mt_rand( 0, 65535 ), mt_rand( 0, 4095 ), mt_rand( 0, 4095 ), mt_rand() ),
							'phone_number' => '07' . mt_rand( 20, 89 ) . ' ' . mt_rand( 100, 999 ) . ' ' . mt_rand( 100, 999 ),
						)
					)
				);
				$ov_outcome = $ov_outcomes[ array_rand( $ov_outcomes ) ];
				$ov_db->insert(
					'calls',
					array(
						'business_id'    => $ov_bid,
						'site_id'        => $ov_sid,
						'phone_click_id' => $ov_click_id,
						'call_time'      => $ov_time,
						'duration_sec'   => mt_rand( 20, 420 ),
						'outcome'        => $ov_outcome,
						'revenue_amount' => 'accepted' === $ov_outcome ? round( $ov_b['avg_job_value'] * mt_rand( 50, 180 ) / 100, 2 ) : null,
						'keyword_text'   => $ov_common['utm_term'],
						'channel'        => $ov_channel,
						'source'         => 'tracking',
					)
				);
			} else {
				$ov_spam = mt_rand( 0, 100 ) < 12;
				$ov_db->insert(
					'form_entries',
					array_merge(
						$ov_common,
						array(
							'external_entry_id' => $ov_day * 1000 + $ov_i + 1,
							'form_id'           => 1,
							'form_title'        => 'Cerere ofertă',
							'fields_cipher'     => Crypto::encrypt( (string) json_encode( array( 'Nume' => 'Client demo', 'Telefon' => '0722 000 000', 'Mesaj' => 'Solicit o ofertă.' ) ), OptiVio\Services\Event_Ingestor::fields_context( $ov_sid ) ),
							'spam_score'        => $ov_spam ? mt_rand( 70, 99 ) : mt_rand( 0, 25 ),
							'spam_status'       => $ov_spam ? 'spam' : 'clean',
						)
					)
				);
			}
			++$ov_total_events;
		}
	}
}

$ov_db->insert(
	'notifications',
	array(
		'type'     => 'demo',
		'severity' => 'warning',
		'title'    => 'Apeluri refuzate în creștere — Instalatori Non-Stop',
		'body'     => '18% din apeluri au fost refuzate din lipsă de capacitate în ultimele 7 zile.',
		'url'      => '/conversions',
	)
);
$ov_db->insert(
	'notifications',
	array(
		'type'     => 'demo',
		'severity' => 'info',
		'title'    => 'Clinica Dentară Zâmbet: USP lipsă',
		'url'      => '/businesses',
	)
);

fwrite( STDOUT, sprintf( "Demo creat: %d afaceri, %d evenimente.\n", count( $ov_businesses ), $ov_total_events ) ); // phpcs:ignore
