<?php
/**
 * OptiVio — Businesses (clients).
 *
 * Every listing method receives the list of accessible business ids from Gate
 * (null = unrestricted, super admin). Callers MUST NOT bypass this filter.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Repositories;

use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Businesses repository.
 */
final class Businesses_Repository {

	/**
	 * Editable columns (Stage 1 subset; Stage 2 extends the profile screens).
	 */
	public const EDITABLE = array(
		'name',
		'status',
		'legal_name',
		'cui',
		'reg_com',
		'vat_payer',
		'billing_address',
		'billing_city',
		'billing_county',
		'billing_email',
		'phone',
		'business_model',
		'service_area',
		'description',
		'target_audience',
		'usp_text',
		'avg_job_value',
		'daily_capacity',
	);

	/**
	 * Finds a business by id. Authorization must be checked BEFORE calling (Gate::authorize_business).
	 *
	 * @param int $id Id.
	 * @return array|null
	 */
	public static function find( int $id ): ?array {
		return Database::instance()->get_row( 'SELECT * FROM businesses WHERE id = ?', array( $id ) );
	}

	/**
	 * Whether a business exists.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function exists( int $id ): bool {
		return null !== Database::instance()->get_var( 'SELECT id FROM businesses WHERE id = ?', array( $id ) );
	}

	/**
	 * Lists businesses visible to the caller.
	 *
	 * @param array<int>|null $ids    Accessible ids (null = all).
	 * @param string          $status Optional status filter.
	 * @return array<int,array>
	 */
	public static function list_accessible( ?array $ids, string $status = '' ): array {
		if ( is_array( $ids ) && empty( $ids ) ) {
			return array();
		}

		$where  = array( '1 = 1' );
		$params = array();
		if ( is_array( $ids ) ) {
			$where[] = 'b.id IN (' . Database::in_placeholders( $ids ) . ')';
			$params  = array_merge( $params, array_values( $ids ) );
		}
		if ( '' !== $status ) {
			$where[]  = 'b.status = ?';
			$params[] = $status;
		}

		return Database::instance()->get_results(
			'SELECT b.id, b.name, b.slug, b.status, b.cui, b.business_model, b.usp_text, b.updated_at,
				(SELECT COUNT(*) FROM sites s WHERE s.business_id = b.id) AS sites_count,
				(SELECT COUNT(*) FROM user_business_access a WHERE a.business_id = b.id) AS operators_count
			 FROM businesses b WHERE ' . implode( ' AND ', $where ) . ' ORDER BY b.status ASC, b.name ASC',
			$params
		);
	}

	/**
	 * Returns id => name options for selectors.
	 *
	 * @param array<int>|null $ids Accessible ids (null = all).
	 * @return array<int,string>
	 */
	public static function options( ?array $ids ): array {
		$options = array();
		foreach ( self::list_accessible( $ids ) as $row ) {
			if ( 'archived' !== $row['status'] ) {
				$options[ (int) $row['id'] ] = (string) $row['name'];
			}
		}
		return $options;
	}

	/**
	 * Searches accessible businesses by name / CUI / legal name.
	 *
	 * @param string          $term Search term.
	 * @param array<int>|null $ids  Accessible ids.
	 * @param int             $limit Limit.
	 * @return array<int,array>
	 */
	public static function search( string $term, ?array $ids, int $limit = 20 ): array {
		if ( is_array( $ids ) && empty( $ids ) ) {
			return array();
		}
		$like   = '%' . str_replace( array( '\\', '%', '_' ), array( '\\\\', '\\%', '\\_' ), $term ) . '%';
		$params = array( $like, $like, $like );
		$scope  = '';
		if ( is_array( $ids ) ) {
			$scope  = ' AND id IN (' . Database::in_placeholders( $ids ) . ')';
			$params = array_merge( $params, array_values( $ids ) );
		}
		$params[] = $limit;
		return Database::instance()->get_results(
			'SELECT id, name, legal_name, cui, status FROM businesses WHERE (name LIKE ? OR legal_name LIKE ? OR cui LIKE ?)' . $scope . ' ORDER BY name LIMIT ?',
			$params
		);
	}

	/**
	 * Creates a business.
	 *
	 * @param array $data    Sanitized data (EDITABLE keys).
	 * @param int   $user_id Creator.
	 * @return int
	 */
	public static function create( array $data, int $user_id ): int {
		$row               = self::normalize( array_intersect_key( $data, array_flip( self::EDITABLE ) ) );
		$row['slug']       = self::unique_slug( (string) $data['name'] );
		$row['created_by'] = $user_id;
		if ( ! empty( $row['usp_text'] ) ) {
			$row['usp_source']     = 'manual';
			$row['usp_updated_at'] = Database::now();
		}
		return Database::instance()->insert( 'businesses', $row );
	}

	/**
	 * Updates a business.
	 *
	 * @param int   $id   Id.
	 * @param array $data Sanitized data.
	 * @return void
	 */
	public static function update( int $id, array $data ): void {
		$row = self::normalize( array_intersect_key( $data, array_flip( self::EDITABLE ) ) );
		if ( array_key_exists( 'usp_text', $row ) ) {
			$current = Database::instance()->get_var( 'SELECT usp_text FROM businesses WHERE id = ?', array( $id ) );
			if ( (string) $current !== (string) $row['usp_text'] ) {
				$row['usp_source']     = 'manual';
				$row['usp_updated_at'] = Database::now();
			}
		}
		if ( ! empty( $row ) ) {
			Database::instance()->update( 'businesses', $row, array( 'id' => $id ) );
		}
	}

	/**
	 * Converts empty strings of optional columns to NULL.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function normalize( array $row ): array {
		foreach ( $row as $key => $value ) {
			if ( 'name' !== $key && '' === $value ) {
				$row[ $key ] = null;
			}
		}
		return $row;
	}

	/**
	 * Generates a unique slug.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function unique_slug( string $name ): string {
		$base = ov_slugify( $name );
		$slug = $base;
		$i    = 2;
		while ( null !== Database::instance()->get_var( 'SELECT id FROM businesses WHERE slug = ?', array( $slug ) ) ) {
			$slug = $base . '-' . $i;
			++$i;
		}
		return $slug;
	}
}
