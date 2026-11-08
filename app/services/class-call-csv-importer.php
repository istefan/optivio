<?php
/**
 * OptiVio — CSV import of calls (exports from phone systems / manual spreadsheets).
 *
 * Header aliases (RO/EN), delimiter auto-detection (, ; tab), local times converted to UTC
 * using the business timezone, Romanian number formats. Caller numbers are never stored
 * in clear (HMAC + last 3 digits). After import, calls are matched to tel: clicks.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Services;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use OptiVio\Core\Database;
use OptiVio\Repositories\Calls_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * CSV importer.
 */
final class Call_Csv_Importer {

	public const MAX_ROWS  = 5000;
	public const MAX_BYTES = 2097152;

	/**
	 * Column aliases => canonical column.
	 */
	private const ALIASES = array(
		'call_time' => array( 'data', 'data_ora', 'data ora', 'ora', 'datetime', 'date', 'time', 'call_time', 'start', 'data apel', 'data_apel' ),
		'duration'  => array( 'durata', 'durata_sec', 'durata sec', 'duration', 'secunde' ),
		'outcome'   => array( 'rezultat', 'status', 'outcome', 'stare' ),
		'revenue'   => array( 'venit', 'suma', 'valoare', 'revenue', 'amount', 'incasat' ),
		'caller'    => array( 'telefon', 'apelant', 'numar', 'număr', 'caller', 'phone', 'from', 'numar apelant' ),
		'keyword'   => array( 'cuvant_cheie', 'cuvant cheie', 'cuvânt cheie', 'keyword', 'termen' ),
		'channel'   => array( 'canal', 'sursa', 'sursă', 'channel', 'source' ),
		'note'      => array( 'nota', 'notă', 'observatii', 'observații', 'note', 'motiv', 'detalii' ),
	);

	/**
	 * Imports a CSV file for a business.
	 *
	 * @param int    $business_id   Business id (authorized by caller with "manage").
	 * @param string $path          Uploaded temp file path.
	 * @param string $original_name Original file name.
	 * @param int    $user_id       Acting user.
	 * @return array{batch_id:int,total:int,imported:int,skipped:int,matched:int,errors:array<int,string>}
	 */
	public static function import( int $business_id, string $path, string $original_name, int $user_id ): array {
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return self::empty_result( 'Fișierul nu poate fi citit.' );
		}

		$first = (string) fgets( $handle );
		$first = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delim = self::delimiter( $first );
		$head  = array_map( array( self::class, 'normalize_header' ), str_getcsv( $first, $delim, '"', '' ) );
		$map   = self::map_columns( $head );

		if ( ! isset( $map['call_time'], $map['outcome'] ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return self::empty_result( 'Lipsesc coloanele obligatorii „data” și „rezultat”.' );
		}

		$tz       = self::business_timezone( $business_id );
		$db       = Database::instance();
		$batch_id = $db->insert(
			'call_import_batches',
			array(
				'business_id'   => $business_id,
				'original_name' => mb_substr( sanitize_text_field( $original_name ), 0, 190 ),
				'created_by'    => $user_id,
			)
		);

		$total    = 0;
		$imported = 0;
		$errors   = array();

		$db->pdo()->beginTransaction();
		while ( false !== ( $cells = fgetcsv( $handle, 0, $delim, '"', '' ) ) && $total < self::MAX_ROWS ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( array( null ) === $cells || '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}
			++$total;
			$get  = static function ( string $key ) use ( $map, $cells ): string {
				return isset( $map[ $key ] ) ? trim( (string) ( $cells[ $map[ $key ] ] ?? '' ) ) : '';
			};
			$time = self::parse_time( $get( 'call_time' ), $tz );
			if ( null === $time ) {
				$errors[] = sprintf( 'Rândul %d: dată invalidă.', $total + 1 );
				continue;
			}

			$outcome = self::parse_outcome( $get( 'outcome' ) );
			$revenue = self::parse_money( $get( 'revenue' ) );
			Calls_Repository::create(
				$business_id,
				array(
					'call_time'      => $time,
					'duration_sec'   => ctype_digit( $get( 'duration' ) ) ? (int) $get( 'duration' ) : null,
					'outcome'        => $outcome,
					'outcome_note'   => '' !== $get( 'note' ) ? mb_substr( sanitize_text_field( $get( 'note' ) ), 0, 500 ) : null,
					'revenue_amount' => 'accepted' === $outcome ? $revenue : null,
					'keyword_text'   => '' !== $get( 'keyword' ) ? mb_substr( sanitize_text_field( $get( 'keyword' ) ), 0, 190 ) : null,
					'channel'        => self::parse_channel( $get( 'channel' ) ),
				),
				$get( 'caller' ),
				'import',
				$user_id,
				$batch_id
			);
			++$imported;
		}
		$db->pdo()->commit();
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$matched = Call_Matcher::match_unlinked( $business_id, $batch_id );
		$db->update(
			'call_import_batches',
			array(
				'rows_total'    => $total,
				'rows_imported' => $imported,
				'rows_skipped'  => $total - $imported,
				'rows_matched'  => $matched,
			),
			array( 'id' => $batch_id )
		);

		return array(
			'batch_id' => $batch_id,
			'total'    => $total,
			'imported' => $imported,
			'skipped'  => $total - $imported,
			'matched'  => $matched,
			'errors'   => array_slice( $errors, 0, 10 ),
		);
	}

	/**
	 * Maps a free-text outcome to the enum.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function parse_outcome( string $value ): string {
		$v = strtr(
			mb_strtolower( trim( $value ) ),
			array(
				'ă' => 'a',
				'â' => 'a',
				'î' => 'i',
				'ș' => 's',
				'ț' => 't',
			)
		);
		$rules = array(
			'rejected_capacity' => '/capacit|ocupat|aglomer|nu avem echip|full/',
			'rejected_area'     => '/zon|area|distan|prea departe|localitate/',
			'rejected_job_type' => '/lucrare|tip|nepotriv|job|nu facem|serviciu/',
			'rejected_price'    => '/pret|price|scump|cost/',
			'missed'            => '/pierdut|nepreluat|missed|no answer|neraspuns|abandon/',
			'spam'              => '/spam|gresit|wrong|reclama/',
			'accepted'          => '/accept|preluat|ok|confirmat|programat|realizat|da\b|yes|won/',
		);
		foreach ( $rules as $outcome => $pattern ) {
			if ( preg_match( $pattern, $v ) ) {
				return $outcome;
			}
		}
		return array_key_exists( $v, Calls_Repository::OUTCOMES ) ? $v : 'other';
	}

	/**
	 * Parses RO/EN money ("1.250,50", "1250.50", "1 250 lei").
	 *
	 * @param string $value Value.
	 * @return float|null
	 */
	public static function parse_money( string $value ): ?float {
		$v = (string) preg_replace( '/[^0-9,.\-]/', '', $value );
		if ( '' === $v ) {
			return null;
		}
		if ( preg_match( '/,\d{1,2}$/', $v ) ) {
			$v = str_replace( array( '.', ',' ), array( '', '.' ), $v );
		} else {
			$v = str_replace( ',', '', $v );
		}
		return is_numeric( $v ) ? round( max( 0, (float) $v ), 2 ) : null;
	}

	/**
	 * Parses a local date/time to UTC.
	 *
	 * @param string       $value Value.
	 * @param DateTimeZone $tz    Business timezone.
	 * @return string|null
	 */
	public static function parse_time( string $value, DateTimeZone $tz ): ?string {
		$value = trim( $value );
		foreach ( array( 'd.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d\TH:i:s', 'd.m.Y' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value, $tz );
			if ( false !== $date && $date->format( $format ) === $value ) {
				$ts = $date->getTimestamp();
				return $ts <= time() + 3600 && $ts > time() - 3 * 365 * 86400 ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
			}
		}
		return null;
	}

	/**
	 * Maps a free-text channel.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function parse_channel( string $value ): string {
		$v = mb_strtolower( $value );
		$map = array(
			'google_ads' => '/ads|adwords|cpc|ppc|platit/',
			'gbp'        => '/maps|business|gmb|gbp|harti/',
			'organic'    => '/organic|seo|cautare/',
			'social'     => '/facebook|instagram|tiktok|social/',
			'referral'   => '/recomand|referral/',
			'direct'     => '/direct/',
			'email'      => '/email|newsletter/',
		);
		foreach ( $map as $channel => $pattern ) {
			if ( '' !== $v && preg_match( $pattern, $v ) ) {
				return $channel;
			}
		}
		return 'other';
	}

	/**
	 * Detects the delimiter of a header line.
	 *
	 * @param string $line Line.
	 * @return string
	 */
	private static function delimiter( string $line ): string {
		$counts = array(
			';'  => substr_count( $line, ';' ),
			','  => substr_count( $line, ',' ),
			"\t" => substr_count( $line, "\t" ),
		);
		arsort( $counts );
		return (string) array_key_first( $counts );
	}

	/**
	 * Normalizes a header cell.
	 *
	 * @param string $cell Cell.
	 * @return string
	 */
	private static function normalize_header( string $cell ): string {
		return trim( mb_strtolower( trim( $cell, " \t\n\r\0\x0B\"" ) ) );
	}

	/**
	 * Maps canonical columns to indexes.
	 *
	 * @param array $head Header cells.
	 * @return array<string,int>
	 */
	private static function map_columns( array $head ): array {
		$map = array();
		foreach ( self::ALIASES as $key => $aliases ) {
			foreach ( $head as $index => $name ) {
				if ( in_array( $name, $aliases, true ) || in_array( str_replace( ' ', '_', $name ), $aliases, true ) ) {
					$map[ $key ] = (int) $index;
					break;
				}
			}
		}
		return $map;
	}

	/**
	 * Timezone of a business.
	 *
	 * @param int $business_id Business id.
	 * @return DateTimeZone
	 */
	private static function business_timezone( int $business_id ): DateTimeZone {
		try {
			return new DateTimeZone( (string) Database::instance()->get_var( 'SELECT timezone FROM businesses WHERE id = ?', array( $business_id ) ) );
		} catch ( Exception $e ) {
			return ov_timezone();
		}
	}

	/**
	 * Empty failure result.
	 *
	 * @param string $error Error.
	 * @return array
	 */
	private static function empty_result( string $error ): array {
		return array(
			'batch_id' => 0,
			'total'    => 0,
			'imported' => 0,
			'skipped'  => 0,
			'matched'  => 0,
			'errors'   => array( $error ),
		);
	}
}
