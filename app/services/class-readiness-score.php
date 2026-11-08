<?php
/**
 * OptiVio — Deterministic optimisation readiness score (0-100) per business.
 *
 * Measures how complete the data foundation is (profile, USP, sites, connector,
 * integrations). Later stages add SEO/PSI/Ads health components.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Readiness score calculator.
 */
final class Readiness_Score {

	/**
	 * Criteria: key => [label, weight].
	 */
	public const CRITERIA = array(
		'business_model' => array( 'Model de afacere definit', 10 ),
		'description'    => array( 'Descriere afacere (min. 80 caractere)', 10 ),
		'usp'            => array( 'USP stabilit', 15 ),
		'offerings'      => array( 'Servicii / produse adăugate', 10 ),
		'legal'          => array( 'Date administrative (CUI)', 5 ),
		'site'           => array( 'Cel puțin un site web', 10 ),
		'connector'      => array( 'Plugin OptiVio Connector activ', 15 ),
		'google_core'    => array( 'Search Console + GA4 configurate', 15 ),
		'google_extra'   => array( 'Ads / GBP / PageSpeed configurate', 10 ),
	);

	/**
	 * Computes the score for a single business.
	 *
	 * @param int $business_id Business id (authorization checked by caller).
	 * @return array{score:int,items:array<int,array{key:string,label:string,done:bool,weight:int}>}
	 */
	public static function for_business( int $business_id ): array {
		$db = Database::instance();
		$b  = $db->get_row( 'SELECT business_model, description, usp_text, cui FROM businesses WHERE id = ?', array( $business_id ) );
		if ( null === $b ) {
			return array(
				'score' => 0,
				'items' => array(),
			);
		}

		$offerings  = (int) $db->get_var( 'SELECT COUNT(*) FROM business_offerings WHERE business_id = ? AND is_active = 1', array( $business_id ) );
		$sites      = (int) $db->get_var( "SELECT COUNT(*) FROM sites WHERE business_id = ? AND status = 'active'", array( $business_id ) );
		$connected  = (int) $db->get_var( "SELECT COUNT(*) FROM sites WHERE business_id = ? AND connector_status = 'connected'", array( $business_id ) );
		$providers  = $db->get_col(
			"SELECT DISTINCT si.provider FROM site_integrations si JOIN sites s ON s.id = si.site_id WHERE s.business_id = ? AND si.status = 'active'",
			array( $business_id )
		);
		$providers  = array_map( 'strval', $providers );
		$done       = array(
			'business_model' => 'other' !== $b['business_model'],
			'description'    => mb_strlen( (string) $b['description'] ) >= 80,
			'usp'            => '' !== trim( (string) $b['usp_text'] ),
			'offerings'      => $offerings > 0,
			'legal'          => '' !== trim( (string) $b['cui'] ),
			'site'           => $sites > 0,
			'connector'      => $connected > 0,
			'google_core'    => in_array( 'gsc', $providers, true ) && in_array( 'ga4', $providers, true ),
			'google_extra'   => (bool) array_intersect( array( 'ads', 'gbp', 'psi' ), $providers ),
		);

		$score = 0;
		$items = array();
		foreach ( self::CRITERIA as $key => $criterion ) {
			if ( $done[ $key ] ) {
				$score += $criterion[1];
			}
			$items[] = array(
				'key'    => $key,
				'label'  => $criterion[0],
				'done'   => $done[ $key ],
				'weight' => $criterion[1],
			);
		}

		return array(
			'score' => $score,
			'items' => $items,
		);
	}

	/**
	 * Average score over a scope.
	 *
	 * @param array<int>|null $ids Business ids (null = all active).
	 * @return array{score:int,count:int,complete:int}
	 */
	public static function for_scope( ?array $ids ): array {
		if ( null === $ids ) {
			$ids = array_map( 'intval', Database::instance()->get_col( "SELECT id FROM businesses WHERE status <> 'archived'" ) );
		}
		if ( empty( $ids ) ) {
			return array(
				'score'    => 0,
				'count'    => 0,
				'complete' => 0,
			);
		}

		$total    = 0;
		$complete = 0;
		foreach ( $ids as $id ) {
			$result = self::for_business( (int) $id );
			$total += $result['score'];
			if ( 100 === $result['score'] ) {
				++$complete;
			}
		}

		return array(
			'score'    => (int) round( $total / count( $ids ) ),
			'count'    => count( $ids ),
			'complete' => $complete,
		);
	}
}
