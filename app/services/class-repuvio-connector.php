<?php
/**
 * OptiVio — Reserved connector for RepuVio (repuvio.ro) reputation management.
 *
 * Architectural placeholder: the business stores repuvio_account_id / repuvio_status and the
 * API credential (api_credentials.provider = 'repuvio'). The real integration is a later phase.
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
 * RepuVio connector contract.
 */
final class Repuvio_Connector {

	/**
	 * Whether the live integration is available in this version.
	 */
	public const AVAILABLE = false;

	/**
	 * Links a business to a RepuVio account id (status "pending" until the integration exists).
	 *
	 * @param int    $business_id Business id (authorization checked by caller).
	 * @param string $account_id  RepuVio account id ('' to unlink).
	 * @return string New status.
	 */
	public static function link( int $business_id, string $account_id ): string {
		$status = '' === $account_id ? 'not_connected' : ( self::AVAILABLE ? 'connected' : 'pending' );
		Database::instance()->update(
			'businesses',
			array(
				'repuvio_account_id' => '' === $account_id ? null : $account_id,
				'repuvio_status'     => $status,
			),
			array( 'id' => $business_id )
		);
		return $status;
	}

	/**
	 * Reputation snapshot for analyses (empty until the integration is live).
	 *
	 * @param int $business_id Business id.
	 * @return array
	 */
	public static function snapshot( int $business_id ): array {
		unset( $business_id );
		return array();
	}
}
