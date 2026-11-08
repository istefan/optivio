<?php
/**
 * OptiVio — Permanent deletion of a business and everything it owns (Super Admin only).
 *
 * Child tables are removed by the ON DELETE CASCADE foreign keys (sites → content, events,
 * Google data, operations; offerings, rules, cards, analyses, perceptions…). Tables without
 * a foreign key (jobs, notifications) are cleaned explicitly; the AI cost log is kept for the
 * monthly budget (business_id set to NULL) and the audit log is kept as history. Uploaded
 * files are removed from storage after the transaction.
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
 * Business deleter.
 */
final class Business_Deleter {

	/**
	 * What will be deleted: label => SQL count.
	 */
	private const COUNTS = array(
		'Site-uri'                      => 'SELECT COUNT(*) FROM sites WHERE business_id = ?',
		'Pagini oglindite (WordPress)'  => 'SELECT COUNT(*) FROM wp_content c JOIN sites s ON s.id = c.site_id WHERE s.business_id = ?',
		'Click-uri pe telefon'          => 'SELECT COUNT(*) FROM phone_clicks p JOIN sites s ON s.id = p.site_id WHERE s.business_id = ?',
		'Formulare'                     => 'SELECT COUNT(*) FROM form_entries f JOIN sites s ON s.id = f.site_id WHERE s.business_id = ?',
		'Apeluri'                       => 'SELECT COUNT(*) FROM calls WHERE business_id = ?',
		'Servicii & produse'            => 'SELECT COUNT(*) FROM business_offerings WHERE business_id = ?',
		'Contacte'                      => 'SELECT COUNT(*) FROM business_contacts WHERE business_id = ?',
		'Reguli'                        => 'SELECT COUNT(*) FROM business_rules WHERE business_id = ?',
		'Action Cards'                  => 'SELECT COUNT(*) FROM action_cards WHERE business_id = ?',
		'Analize AI'                    => 'SELECT COUNT(*) FROM analyses WHERE business_id = ?',
		'Percepții AI'                  => 'SELECT COUNT(*) FROM business_perceptions WHERE business_id = ?',
		'Cuvinte cheie'                 => 'SELECT COUNT(*) FROM keywords WHERE business_id = ?',
		'Concurenți'                    => 'SELECT COUNT(*) FROM competitors WHERE business_id = ?',
		'Imagini încărcate'             => 'SELECT COUNT(*) FROM uploads WHERE business_id = ?',
		'Operatori cu acces'            => 'SELECT COUNT(*) FROM user_business_access WHERE business_id = ?',
	);

	/**
	 * Counts of the rows that will be deleted.
	 *
	 * @param int $business_id Business.
	 * @return array<string,int>
	 */
	public static function summary( int $business_id ): array {
		$db  = Database::instance();
		$out = array();
		foreach ( self::COUNTS as $label => $sql ) {
			$out[ $label ] = (int) $db->get_var( $sql, array( $business_id ) );
		}
		return $out;
	}

	/**
	 * Deletes the business.
	 *
	 * @param int $business_id Business.
	 * @return array<string,int> Counts that were deleted.
	 */
	public static function delete( int $business_id ): array {
		$db      = Database::instance();
		$summary = self::summary( $business_id );
		$files   = $db->get_col( 'SELECT stored_name FROM uploads WHERE business_id = ?', array( $business_id ) );

		$db->transaction(
			static function () use ( $db, $business_id ): void {
				$db->query( 'DELETE FROM jobs WHERE business_id = ? OR site_id IN (SELECT id FROM sites WHERE business_id = ?)', array( $business_id, $business_id ) );
				$db->query( 'DELETE FROM notifications WHERE business_id = ?', array( $business_id ) );
				$db->query( 'UPDATE ai_requests SET business_id = NULL WHERE business_id = ?', array( $business_id ) );
				$db->query( 'DELETE FROM onboarding_drafts WHERE business_id = ?', array( $business_id ) );
				$db->query( 'DELETE FROM businesses WHERE id = ?', array( $business_id ) );
			}
		);

		foreach ( $files as $name ) {
			$name = (string) $name;
			if ( preg_match( '/^[a-f0-9]{32,64}\.[a-z]{3,4}$/', $name ) ) {
				$path = OPTIVIO_STORAGE . '/uploads/' . $name;
				if ( is_file( $path ) ) {
					unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}
		return $summary;
	}
}
