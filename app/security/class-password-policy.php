<?php
/**
 * OptiVio — Password hashing and policy (NIST 800-63B inspired: length over composition).
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Security;

use OptiVio\Repositories\Settings_Repository;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Password utilities.
 */
final class Password_Policy {

	/**
	 * Very common passwords that are always refused.
	 */
	private const COMMON = array(
		'parola123456',
		'password1234',
		'123456789012',
		'qwertyuiop12',
		'administrator',
		'optivio12345',
		'parolaparola',
		'passwordpassword',
	);

	/**
	 * Returns the hashing algorithm (Argon2id when available).
	 *
	 * @return string|int
	 */
	public static function algorithm() {
		return defined( 'PASSWORD_ARGON2ID' ) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
	}

	/**
	 * Hashes a password.
	 *
	 * @param string $password Plain password.
	 * @return string
	 */
	public static function hash( string $password ): string {
		return password_hash( $password, self::algorithm() );
	}

	/**
	 * Whether a stored hash should be upgraded.
	 *
	 * @param string $hash Hash.
	 * @return bool
	 */
	public static function needs_rehash( string $hash ): bool {
		return password_needs_rehash( $hash, self::algorithm() );
	}

	/**
	 * Validates a new password. Returns an error message or null.
	 *
	 * @param string $password Plain password.
	 * @param string $email    User email (password must not contain its local part).
	 * @return string|null
	 */
	public static function validate( string $password, string $email = '' ): ?string {
		$min = max( 10, (int) Settings_Repository::get( 'security.password_min_length', 12 ) );

		if ( mb_strlen( $password ) < $min ) {
			return sprintf( 'Parola trebuie să aibă cel puțin %d caractere.', $min );
		}
		if ( mb_strlen( $password ) > 256 ) {
			return 'Parola este prea lungă (maximum 256 caractere).';
		}
		if ( in_array( strtolower( $password ), self::COMMON, true ) ) {
			return 'Parola este prea comună.';
		}
		if ( 1 === count( array_unique( mb_str_split( $password ) ) ) ) {
			return 'Parola nu poate conține un singur caracter repetat.';
		}
		$local = strtolower( (string) strstr( $email, '@', true ) );
		if ( strlen( $local ) >= 4 && false !== stripos( $password, $local ) ) {
			return 'Parola nu poate conține adresa de email.';
		}
		return null;
	}
}
