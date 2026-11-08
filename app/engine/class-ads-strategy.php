<?php
/**
 * OptiVio — Deterministic Google Ads strategy advisor per business model and observed data.
 *
 * Example rule (from the product brief): emergency services (burst pipe, lockout) are not
 * repeat customers, conversion volume per campaign is low and noisy, so Smart Bidding on
 * conversions learns poorly → prefer Maximize Clicks with a max CPC cap, call-focused ads,
 * tight geo and ad schedule based on accepted calls.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Engine;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Ads strategy advisor.
 */
final class Ads_Strategy {

	/**
	 * Conversion-based bidding strategies.
	 */
	private const CONVERSION_BIDDING = array( 'MAXIMIZE_CONVERSIONS', 'TARGET_CPA', 'MAXIMIZE_CONVERSION_VALUE', 'TARGET_ROAS' );

	/**
	 * Recommendations for a business.
	 *
	 * @param int $business_id Business.
	 * @return array{model:string,principles:array<int,string>,campaigns:array<int,array>}
	 */
	public static function recommend( int $business_id ): array {
		$db       = Database::instance();
		$business = (array) $db->get_row( 'SELECT business_model, daily_capacity, avg_job_value FROM businesses WHERE id = ?', array( $business_id ) );
		$model    = (string) ( $business['business_model'] ?? 'other' );
		$from     = gmdate( 'Y-m-d', time() - 30 * 86400 );

		$campaigns = $db->get_results(
			"SELECT c.id, c.name, c.status, c.bidding_strategy, c.channel_type, c.daily_budget_micros, s.name AS site_name,
				COALESCE(SUM(h.clicks), 0) clicks, COALESCE(SUM(h.cost_micros), 0) / 1000000 cost, COALESCE(SUM(h.conversions), 0) conversions
			 FROM ads_campaigns c JOIN sites s ON s.id = c.site_id LEFT JOIN ads_hourly h ON h.campaign_id = c.id AND h.stat_date >= ?
			 WHERE s.business_id = ? AND c.status <> 'REMOVED' GROUP BY c.id ORDER BY cost DESC",
			array( $from, $business_id )
		);

		$accepted = (int) $db->get_var( "SELECT COUNT(*) FROM calls WHERE business_id = ? AND outcome = 'accepted' AND call_time >= UTC_TIMESTAMP() - INTERVAL 30 DAY", array( $business_id ) );
		$rejected = (int) $db->get_var( "SELECT COUNT(*) FROM calls WHERE business_id = ? AND outcome LIKE 'rejected\\_%' AND call_time >= UTC_TIMESTAMP() - INTERVAL 30 DAY", array( $business_id ) );
		$revenue  = (float) $db->get_var( "SELECT COALESCE(SUM(revenue_amount), 0) FROM calls WHERE business_id = ? AND outcome = 'accepted' AND call_time >= UTC_TIMESTAMP() - INTERVAL 30 DAY", array( $business_id ) );

		$principles = self::principles( $model, $business, $accepted, $rejected );
		foreach ( $campaigns as &$c ) {
			$c['advice'] = self::campaign_advice( $model, $c, $accepted, $revenue );
		}
		unset( $c );

		return array(
			'model'      => $model,
			'principles' => $principles,
			'campaigns'  => $campaigns,
			'calls'      => array(
				'accepted' => $accepted,
				'rejected' => $rejected,
				'revenue'  => $revenue,
			),
		);
	}

	/**
	 * General principles per model.
	 *
	 * @param string $model    Model.
	 * @param array  $business Business.
	 * @param int    $accepted Accepted calls (30d).
	 * @param int    $rejected Rejected calls (30d).
	 * @return array<int,string>
	 */
	private static function principles( string $model, array $business, int $accepted, int $rejected ): array {
		$out = array();
		switch ( $model ) {
			case 'emergency_service':
				$out[] = 'Licitare „Maximizare click-uri” cu plafon CPC (sau CPC manual) în locul strategiilor pe conversii: clienții de urgență nu sunt recurenți, volumul de conversii pe campanie e mic și zgomotos, iar algoritmul învață greu.';
				$out[] = 'Reclame axate pe apel (extensii / reclame de apel), cuvinte cu intenție de urgență („non stop”, „urgent”, „acum”), potrivire exactă/frază.';
				$out[] = 'Program reclame strict pe intervalele în care echipele pot prelua lucrări; excludeți orele cu apeluri refuzate.';
				$out[] = 'Geotargetare pe zona deservită (raza în care ajungeți în timpul promis), nu pe „interes în zonă”.';
				break;
			case 'recurring_service':
			case 'b2b':
			case 'lead_generation':
				$out[] = 'Strategii pe conversii („Maximizare conversii” → „CPA țintă”) după ce campania are ≥ 30 de conversii/lună cu tracking corect (formulare + apeluri calificate).';
				$out[] = 'Importați conversiile offline (lead calificat / contract) pentru ca algoritmul să optimizeze pe valoare, nu pe volum.';
				break;
			case 'ecommerce':
				$out[] = 'Performance Max / Shopping cu „ROAS țintă” când există valori de conversie corecte; campanii Search pe brand separat.';
				break;
			default:
				$out[] = 'Începeți cu „Maximizare click-uri” cu plafon CPC până acumulați ≥ 30 de conversii/lună, apoi treceți pe strategii pe conversii.';
		}
		if ( ! empty( $business['daily_capacity'] ) ) {
			$out[] = sprintf( 'Capacitate declarată: %d lucrări/zi — bugetul zilnic nu trebuie să genereze mai multe apeluri decât pot fi preluate.', (int) $business['daily_capacity'] );
		}
		if ( $accepted + $rejected > 0 && $rejected / ( $accepted + $rejected ) > 0.25 ) {
			$out[] = sprintf( '%d%% din apelurile din ultimele 30 de zile au fost refuzate: verificați cuvintele cheie și orele marcate în insight-uri.', (int) round( 100 * $rejected / ( $accepted + $rejected ) ) );
		}
		return $out;
	}

	/**
	 * Advice for one campaign.
	 *
	 * @param string $model    Model.
	 * @param array  $c        Campaign stats.
	 * @param int    $accepted Accepted calls of the business.
	 * @param float  $revenue  Revenue of the business.
	 * @return array<int,array{level:string,text:string}>
	 */
	private static function campaign_advice( string $model, array $c, int $accepted, float $revenue ): array {
		$advice = array();
		$conv   = (float) $c['conversions'];
		$cost   = (float) $c['cost'];
		$conv_bidding = in_array( (string) $c['bidding_strategy'], self::CONVERSION_BIDDING, true );

		if ( 'emergency_service' === $model && $conv_bidding ) {
			$advice[] = array(
				'level' => 'warning',
				'text'  => 'Strategie pe conversii la un serviciu de urgență: recomandat „Maximizare click-uri” cu plafon CPC.',
			);
		}
		if ( $conv_bidding && $conv < 30 ) {
			$advice[] = array(
				'level' => 'warning',
				'text'  => sprintf( 'Doar %s conversii în 30 de zile — sub pragul de ~30 necesar pentru Smart Bidding stabil.', ov_number( $conv, 0 ) ),
			);
		}
		if ( $cost > 0 && 0.0 === $conv ) {
			$advice[] = array(
				'level' => 'critical',
				'text'  => sprintf( '%s cheltuiți fără nicio conversie înregistrată: verificați tracking-ul (tag conversie / apeluri).', ov_number( $cost, 0 ) ),
			);
		}
		if ( $conv > 0 ) {
			$advice[] = array(
				'level' => 'info',
				'text'  => sprintf( 'Cost / conversie: %s.', ov_number( $cost / $conv, 0 ) ),
			);
		}
		if ( $accepted > 0 && $revenue > 0 && $cost > 0 ) {
			$advice[] = array(
				'level' => 'info',
				'text'  => sprintf( 'Venit mediu / apel acceptat (toată afacerea): %s — comparați cu costul pe conversie.', ov_number( $revenue / $accepted, 0 ) ),
			);
		}
		return $advice;
	}
}
