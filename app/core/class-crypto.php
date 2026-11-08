<?php
/**
 * OptiVio — Authenticated encryption (AES-256-GCM) and keyed hashing.
 *
 * Blob format (base64): "ov1:" . base64( key_id[1] | iv[12] | tag[16] | ciphertext )
 * The encryption sub-key is derived with HKDF-SHA256 from the master key, and the
 * caller-supplied context is bound as AAD, so a ciphertext copied into another field
 * (different context) fails authentication.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

use RuntimeException;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Crypto helpers.
 */
final class Crypto {

	private const CIPHER  = 'aes-256-gcm';
	private const PREFIX  = 'ov1:';
	private const IV_LEN  = 12;
	private const TAG_LEN = 16;

	/**
	 * Encrypts a plaintext.
	 *
	 * @param string $plaintext Plaintext.
	 * @param string $context   Context bound as AAD (e.g. "settings:openrouter_api_key").
	 * @return string Encoded blob.
	 * @throws RuntimeException On failure.
	 */
	public static function encrypt( string $plaintext, string $context ): string {
		$key_id = (int) Config::get( 'master_key_id', 1 );
		$key    = self::derive( self::master_key( $key_id ), 'enc' );
		$iv     = random_bytes( self::IV_LEN );
		$tag    = '';

		$cipher = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, self::aad( $context ), self::TAG_LEN );
		if ( false === $cipher ) {
			throw new RuntimeException( 'Encryption failed.' );
		}

		return self::PREFIX . base64_encode( chr( $key_id ) . $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary encoding.
	}

	/**
	 * Decrypts a blob produced by encrypt().
	 *
	 * @param string $blob    Encoded blob.
	 * @param string $context Same context used on encryption.
	 * @return string|null Plaintext, or null when tampered / wrong context / wrong key.
	 */
	public static function decrypt( string $blob, string $context ): ?string {
		if ( 0 !== strpos( $blob, self::PREFIX ) ) {
			return null;
		}
		$raw = base64_decode( substr( $blob, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary decoding.
		if ( false === $raw || strlen( $raw ) < 1 + self::IV_LEN + self::TAG_LEN ) {
			return null;
		}

		$key_id = ord( $raw[0] );
		$iv     = substr( $raw, 1, self::IV_LEN );
		$tag    = substr( $raw, 1 + self::IV_LEN, self::TAG_LEN );
		$cipher = substr( $raw, 1 + self::IV_LEN + self::TAG_LEN );

		try {
			$key = self::derive( self::master_key( $key_id ), 'enc' );
		} catch ( RuntimeException $e ) {
			return null;
		}

		$plain = openssl_decrypt( $cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag, self::aad( $context ) );
		return false === $plain ? null : $plain;
	}

	/**
	 * Keyed hash (HMAC-SHA256) for lookups without storing PII (IPs, phone numbers, emails).
	 *
	 * @param string $value   Value.
	 * @param string $purpose Purpose label (separates key domains).
	 * @return string 64 hex chars.
	 */
	public static function keyed_hash( string $value, string $purpose ): string {
		$key = self::derive( self::master_key( (int) Config::get( 'master_key_id', 1 ) ), 'hash:' . $purpose );
		return hash_hmac( 'sha256', $value, $key );
	}

	/**
	 * Generates a URL-safe random token.
	 *
	 * @param int $bytes Entropy bytes.
	 * @return string
	 */
	public static function random_token( int $bytes = 32 ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Returns the last 4 characters of a secret (for masked display).
	 *
	 * @param string $secret Secret.
	 * @return string
	 */
	public static function hint( string $secret ): string {
		return strlen( $secret ) > 8 ? substr( $secret, -4 ) : '';
	}

	/**
	 * Whether a valid master key is configured.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		try {
			self::master_key( (int) Config::get( 'master_key_id', 1 ) );
			return true;
		} catch ( RuntimeException $e ) {
			return false;
		}
	}

	/**
	 * Loads a master key by id.
	 *
	 * @param int $key_id Key id.
	 * @return string 32 raw bytes.
	 * @throws RuntimeException When missing or invalid.
	 */
	private static function master_key( int $key_id ): string {
		$current_id = (int) Config::get( 'master_key_id', 1 );
		if ( $key_id === $current_id ) {
			$encoded = (string) Config::get( 'master_key', '' );
		} else {
			$previous = (array) Config::get( 'previous_keys', array() );
			$encoded  = (string) ( $previous[ $key_id ] ?? '' );
		}

		$key = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $key || 32 !== strlen( $key ) ) {
			throw new RuntimeException( 'Master key is missing or invalid (must be 32 bytes, base64).' );
		}
		return $key;
	}

	/**
	 * HKDF-SHA256 sub-key derivation.
	 *
	 * @param string $master Master key.
	 * @param string $info   Info label.
	 * @return string 32 raw bytes.
	 */
	private static function derive( string $master, string $info ): string {
		return hash_hkdf( 'sha256', $master, 32, 'optivio:' . $info, 'optivio-v1' );
	}

	/**
	 * Builds the AAD string.
	 *
	 * @param string $context Context.
	 * @return string
	 */
	private static function aad( string $context ): string {
		return 'optivio|' . $context;
	}
}
