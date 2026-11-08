<?php
/**
 * OptiVio — Business child records: contacts, offerings (services/products) and permanent rules.
 *
 * Every method is scoped by business_id so a child id from another business can never
 * be read or modified (anti-IDOR at query level, in addition to Gate checks).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Repositories;

use InvalidArgumentException;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Generic repository for tables owned by a business.
 */
final class Business_Children_Repository {

	/**
	 * Table definitions: table => [writable columns, order by].
	 */
	private const TABLES = array(
		'business_contacts'  => array(
			array( 'name', 'job_title', 'email', 'phone', 'is_primary', 'notes' ),
			'is_primary DESC, name ASC',
		),
		'business_offerings' => array(
			array( 'type', 'name', 'description', 'is_emergency', 'is_recurring', 'avg_ticket_value', 'margin_pct', 'priority', 'is_active' ),
			'is_active DESC, priority ASC, name ASC',
		),
		'business_rules'     => array(
			array( 'source', 'action_card_id', 'category', 'rule_text', 'is_active', 'created_by' ),
			'is_active DESC, id DESC',
		),
	);

	/**
	 * Lists rows of a business.
	 *
	 * @param string $table       Table.
	 * @param int    $business_id Business id.
	 * @return array<int,array>
	 */
	public static function all( string $table, int $business_id ): array {
		$def = self::def( $table );
		return Database::instance()->get_results(
			'SELECT * FROM ' . Database::quote_identifier( $table ) . ' WHERE business_id = ? ORDER BY ' . $def[1],
			array( $business_id )
		);
	}

	/**
	 * Finds one row belonging to the business.
	 *
	 * @param string $table       Table.
	 * @param int    $business_id Business id.
	 * @param int    $id          Row id.
	 * @return array|null
	 */
	public static function find( string $table, int $business_id, int $id ): ?array {
		self::def( $table );
		return Database::instance()->get_row(
			'SELECT * FROM ' . Database::quote_identifier( $table ) . ' WHERE id = ? AND business_id = ?',
			array( $id, $business_id )
		);
	}

	/**
	 * Inserts a row.
	 *
	 * @param string $table       Table.
	 * @param int    $business_id Business id.
	 * @param array  $data        Sanitized data.
	 * @return int
	 */
	public static function create( string $table, int $business_id, array $data ): int {
		$def                = self::def( $table );
		$row                = array_intersect_key( $data, array_flip( $def[0] ) );
		$row['business_id'] = $business_id;
		return Database::instance()->transaction(
			static function ( Database $db ) use ( $table, $row, $business_id ): int {
				if ( 'business_contacts' === $table && ! empty( $row['is_primary'] ) ) {
					$db->query( 'UPDATE business_contacts SET is_primary = 0 WHERE business_id = ?', array( $business_id ) );
				}
				return $db->insert( $table, $row );
			}
		);
	}

	/**
	 * Updates a row of the business.
	 *
	 * @param string $table       Table.
	 * @param int    $business_id Business id.
	 * @param int    $id          Row id.
	 * @param array  $data        Sanitized data.
	 * @return int Affected rows.
	 */
	public static function update( string $table, int $business_id, int $id, array $data ): int {
		$def = self::def( $table );
		$row = array_intersect_key( $data, array_flip( $def[0] ) );
		if ( empty( $row ) ) {
			return 0;
		}
		return (int) Database::instance()->transaction(
			static function ( Database $db ) use ( $table, $row, $business_id, $id ): int {
				if ( 'business_contacts' === $table && ! empty( $row['is_primary'] ) ) {
					$db->query( 'UPDATE business_contacts SET is_primary = 0 WHERE business_id = ? AND id <> ?', array( $business_id, $id ) );
				}
				return $db->update(
					$table,
					$row,
					array(
						'id'          => $id,
						'business_id' => $business_id,
					)
				);
			}
		);
	}

	/**
	 * Deletes a row of the business.
	 *
	 * @param string $table       Table.
	 * @param int    $business_id Business id.
	 * @param int    $id          Row id.
	 * @return int
	 */
	public static function delete( string $table, int $business_id, int $id ): int {
		self::def( $table );
		return Database::instance()->delete(
			$table,
			array(
				'id'          => $id,
				'business_id' => $business_id,
			)
		);
	}

	/**
	 * Active rules of a business (used by every future analysis).
	 *
	 * @param int $business_id Business id.
	 * @return array<int,string>
	 */
	public static function active_rules( int $business_id ): array {
		return array_map(
			'strval',
			Database::instance()->get_col( 'SELECT rule_text FROM business_rules WHERE business_id = ? AND is_active = 1 ORDER BY id', array( $business_id ) )
		);
	}

	/**
	 * Returns the table definition.
	 *
	 * @param string $table Table.
	 * @return array
	 * @throws InvalidArgumentException For unknown tables.
	 */
	private static function def( string $table ): array {
		if ( ! isset( self::TABLES[ $table ] ) ) {
			throw new InvalidArgumentException( 'Unknown child table.' );
		}
		return self::TABLES[ $table ];
	}
}
