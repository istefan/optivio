<?php
/**
 * OptiVio — Fixed-window rate limiter stored in MySQL (works on shared hosting).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Security;

use OptiVio\Core\Crypto;
use OptiVio\Core\Database;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Rate limiter keyed by an HMAC of the bucket name (no raw emails/IPs stored).
 */
final class Rate_Limiter {

	/**
	 * Returns remaining block seconds for a bucket (0 = not blocked).
	 *
	 * @param string $bucket Bucket name (e.g. "login:ip:1.2.3.4").
	 * @return int
	 */
	public static function blocked_for( string $bucket ): int {
		$seconds = Database::instance()->get_var(
			'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), blocked_until) FROM rate_limits WHERE bucket_hash = ? AND blocked_until > UTC_TIMESTAMP()',
			array( self::key( $bucket ) )
		);
		return null === $seconds ? 0 : max( 1, (int) $seconds );
	}

	/**
	 * Registers a hit; blocks the bucket when the limit is reached inside the window.
	 *
	 * @param string $bucket          Bucket.
	 * @param int    $max_hits        Max hits per window.
	 * @param int    $window_seconds  Window length.
	 * @param int    $lockout_seconds Block duration once the limit is reached.
	 * @return bool True when the bucket is now blocked.
	 */
	public static function hit( string $bucket, int $max_hits, int $window_seconds, int $lockout_seconds ): bool {
		$db  = Database::instance();
		$key = self::key( $bucket );

		$db->query(
			'INSERT INTO rate_limits (bucket_hash, hits, window_start) VALUES (?, 1, UTC_TIMESTAMP())
			 ON DUPLICATE KEY UPDATE
				hits = IF(window_start < UTC_TIMESTAMP() - INTERVAL ? SECOND, 1, hits + 1),
				window_start = IF(window_start < UTC_TIMESTAMP() - INTERVAL ? SECOND, UTC_TIMESTAMP(), window_start)',
			array( $key, $window_seconds, $window_seconds )
		);

		$blocked = $db->query(
			'UPDATE rate_limits SET blocked_until = UTC_TIMESTAMP() + INTERVAL ? SECOND, hits = 0, window_start = UTC_TIMESTAMP()
			 WHERE bucket_hash = ? AND hits >= ?',
			array( $lockout_seconds, $key, $max_hits )
		)->rowCount();

		return $blocked > 0;
	}

	/**
	 * Clears a bucket (e.g. after a successful login).
	 *
	 * @param string $bucket Bucket.
	 * @return void
	 */
	public static function clear( string $bucket ): void {
		Database::instance()->delete( 'rate_limits', array( 'bucket_hash' => self::key( $bucket ) ) );
	}

	/**
	 * Hashes a bucket name.
	 *
	 * @param string $bucket Bucket.
	 * @return string
	 */
	private static function key( string $bucket ): string {
		return Crypto::keyed_hash( $bucket, 'rate_limit' );
	}
}
