<?php
/**
 * OptiVio — PDO database wrapper (native prepared statements only).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;
use InvalidArgumentException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Thin, safe PDO wrapper. All values MUST be passed as bound parameters.
 */
final class Database {

	/**
	 * Singleton instance.
	 *
	 * @var Database|null
	 */
	private static ?Database $instance = null;

	/**
	 * PDO connection.
	 *
	 * @var PDO
	 */
	private PDO $pdo;

	/**
	 * Opens the connection.
	 *
	 * @throws RuntimeException When the configuration is incomplete.
	 */
	private function __construct() {
		$host    = (string) Config::get( 'db.host', 'localhost' );
		$port    = (int) Config::get( 'db.port', 3306 );
		$name    = (string) Config::get( 'db.name', '' );
		$charset = (string) Config::get( 'db.charset', 'utf8mb4' );

		if ( '' === $name ) {
			throw new RuntimeException( 'Database is not configured.' );
		}

		$dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset );

		$this->pdo = new PDO(
			$dsn,
			(string) Config::get( 'db.user', '' ),
			(string) Config::get( 'db.password', '' ),
			array(
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES   => false,
				PDO::ATTR_STRINGIFY_FETCHES  => false,
				PDO::ATTR_PERSISTENT         => false,
			)
		);

		$this->pdo->exec( "SET time_zone = '+00:00', sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'" );
	}

	/**
	 * Returns the shared instance.
	 *
	 * @return Database
	 */
	public static function instance(): Database {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Returns the raw PDO object (use only for advanced cases, still with prepared statements).
	 *
	 * @return PDO
	 */
	public function pdo(): PDO {
		return $this->pdo;
	}

	/**
	 * Prepares and executes a statement.
	 *
	 * @param string $sql    SQL with ? or :named placeholders.
	 * @param array  $params Bound parameters.
	 * @return PDOStatement
	 */
	public function query( string $sql, array $params = array() ): PDOStatement {
		$stmt = $this->pdo->prepare( $sql );
		if ( array_is_list( $params ) ) {
			foreach ( $params as $index => $value ) {
				$stmt->bindValue( $index + 1, $value, self::pdo_type( $value ) );
			}
		} else {
			foreach ( $params as $key => $value ) {
				$stmt->bindValue( ':' . ltrim( (string) $key, ':' ), $value, self::pdo_type( $value ) );
			}
		}
		$stmt->execute();
		return $stmt;
	}

	/**
	 * Returns a single row or null.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Params.
	 * @return array|null
	 */
	public function get_row( string $sql, array $params = array() ): ?array {
		$row = $this->query( $sql, $params )->fetch();
		return false === $row ? null : $row;
	}

	/**
	 * Returns all rows.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Params.
	 * @return array<int,array>
	 */
	public function get_results( string $sql, array $params = array() ): array {
		return $this->query( $sql, $params )->fetchAll();
	}

	/**
	 * Returns the first column of the first row.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Params.
	 * @return mixed|null
	 */
	public function get_var( string $sql, array $params = array() ) {
		$value = $this->query( $sql, $params )->fetchColumn();
		return false === $value ? null : $value;
	}

	/**
	 * Returns a single column of all rows.
	 *
	 * @param string $sql    SQL.
	 * @param array  $params Params.
	 * @return array
	 */
	public function get_col( string $sql, array $params = array() ): array {
		return $this->query( $sql, $params )->fetchAll( PDO::FETCH_COLUMN );
	}

	/**
	 * Inserts a row and returns the inserted id.
	 *
	 * @param string $table Table (whitelisted identifier).
	 * @param array  $data  Column => value.
	 * @return int
	 */
	public function insert( string $table, array $data ): int {
		$columns      = array_map( array( self::class, 'quote_identifier' ), array_keys( $data ) );
		$placeholders = implode( ', ', array_fill( 0, count( $data ), '?' ) );
		$sql          = sprintf( 'INSERT INTO %s (%s) VALUES (%s)', self::quote_identifier( $table ), implode( ', ', $columns ), $placeholders );
		$this->query( $sql, array_values( $data ) );
		return (int) $this->pdo->lastInsertId();
	}

	/**
	 * Updates rows matching an equality WHERE clause.
	 *
	 * @param string $table Table.
	 * @param array  $data  Column => value.
	 * @param array  $where Column => value (AND).
	 * @return int Affected rows.
	 * @throws InvalidArgumentException When the WHERE clause is empty.
	 */
	public function update( string $table, array $data, array $where ): int {
		if ( empty( $where ) ) {
			throw new InvalidArgumentException( 'Refusing UPDATE without WHERE.' );
		}
		$set = array();
		foreach ( array_keys( $data ) as $column ) {
			$set[] = self::quote_identifier( $column ) . ' = ?';
		}
		$conditions = array();
		foreach ( array_keys( $where ) as $column ) {
			$conditions[] = self::quote_identifier( $column ) . ' = ?';
		}
		$sql = sprintf( 'UPDATE %s SET %s WHERE %s', self::quote_identifier( $table ), implode( ', ', $set ), implode( ' AND ', $conditions ) );
		return $this->query( $sql, array_merge( array_values( $data ), array_values( $where ) ) )->rowCount();
	}

	/**
	 * Deletes rows matching an equality WHERE clause.
	 *
	 * @param string $table Table.
	 * @param array  $where Column => value (AND).
	 * @return int Affected rows.
	 * @throws InvalidArgumentException When the WHERE clause is empty.
	 */
	public function delete( string $table, array $where ): int {
		if ( empty( $where ) ) {
			throw new InvalidArgumentException( 'Refusing DELETE without WHERE.' );
		}
		$conditions = array();
		foreach ( array_keys( $where ) as $column ) {
			$conditions[] = self::quote_identifier( $column ) . ' = ?';
		}
		$sql = sprintf( 'DELETE FROM %s WHERE %s', self::quote_identifier( $table ), implode( ' AND ', $conditions ) );
		return $this->query( $sql, array_values( $where ) )->rowCount();
	}

	/**
	 * Multi-row INSERT ... ON DUPLICATE KEY UPDATE (chunks of 500 rows).
	 *
	 * @param string $table   Table.
	 * @param array  $columns Column names.
	 * @param array  $rows    Rows (list arrays in column order).
	 * @param array  $update  Columns updated on duplicate key.
	 * @return int Rows sent.
	 */
	public function upsert_many( string $table, array $columns, array $rows, array $update ): int {
		if ( empty( $rows ) ) {
			return 0;
		}
		$cols = implode( ', ', array_map( array( self::class, 'quote_identifier' ), $columns ) );
		$set  = implode(
			', ',
			array_map(
				static function ( string $c ): string {
					$q = self::quote_identifier( $c );
					return $q . ' = VALUES(' . $q . ')';
				},
				$update
			)
		);
		$row_ph = '(' . implode( ', ', array_fill( 0, count( $columns ), '?' ) ) . ')';
		foreach ( array_chunk( $rows, 500 ) as $chunk ) {
			$params = array();
			foreach ( $chunk as $row ) {
				foreach ( array_values( $row ) as $value ) {
					$params[] = $value;
				}
			}
			$this->query(
				sprintf( 'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s', self::quote_identifier( $table ), $cols, implode( ', ', array_fill( 0, count( $chunk ), $row_ph ) ), $set ),
				$params
			);
		}
		return count( $rows );
	}

	/**
	 * Runs a callback inside a transaction. Re-entrant: a nested call joins the outer
	 * transaction (the outermost call commits or rolls back).
	 *
	 * @param callable $callback Callback receiving this instance.
	 * @return mixed Callback result.
	 * @throws Throwable Re-thrown after rollback.
	 */
	public function transaction( callable $callback ) {
		if ( $this->pdo->inTransaction() ) {
			return $callback( $this );
		}
		$this->pdo->beginTransaction();
		try {
			$result = $callback( $this );
			$this->pdo->commit();
			return $result;
		} catch ( Throwable $e ) {
			if ( $this->pdo->inTransaction() ) {
				$this->pdo->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Builds "?, ?, ?" placeholders for an IN() list.
	 *
	 * @param array $values Values.
	 * @return string
	 */
	public static function in_placeholders( array $values ): string {
		return implode( ', ', array_fill( 0, max( 1, count( $values ) ), '?' ) );
	}

	/**
	 * Validates and quotes an SQL identifier (table/column). Whitelist: [a-z0-9_].
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 * @throws InvalidArgumentException On invalid identifier.
	 */
	public static function quote_identifier( string $identifier ): string {
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $identifier ) ) {
			throw new InvalidArgumentException( 'Invalid SQL identifier.' );
		}
		return '`' . $identifier . '`';
	}

	/**
	 * Returns current UTC datetime string for SQL.
	 *
	 * @param int $offset_seconds Optional offset.
	 * @return string
	 */
	public static function now( int $offset_seconds = 0 ): string {
		return gmdate( 'Y-m-d H:i:s', time() + $offset_seconds );
	}

	/**
	 * Maps a PHP value to a PDO param type.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function pdo_type( $value ): int {
		if ( is_int( $value ) ) {
			return PDO::PARAM_INT;
		}
		if ( is_bool( $value ) ) {
			return PDO::PARAM_BOOL;
		}
		if ( null === $value ) {
			return PDO::PARAM_NULL;
		}
		return PDO::PARAM_STR;
	}
}
