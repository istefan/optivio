<?php
/**
 * OptiVio — Small validation helper collecting field errors.
 *
 * @package   OptiVio
 * @author    Stefan Iftimie, founder Ahoi Digital SRL <https://www.ahoi.ro/>
 * @copyright 2026 Ahoi Digital SRL
 */

declare( strict_types = 1 );

namespace OptiVio\Core;

defined( 'OPTIVIO_ROOT' ) || exit;

/**
 * Validates already-sanitized values.
 */
final class Validator {

	/**
	 * Field errors.
	 *
	 * @var array<string,string>
	 */
	private array $errors = array();

	/**
	 * Requires a non-empty value.
	 *
	 * @param string $field   Field.
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return self
	 */
	public function required( string $field, $value, string $message = 'Câmp obligatoriu.' ): self {
		if ( null === $value || '' === $value || array() === $value ) {
			$this->add( $field, $message );
		}
		return $this;
	}

	/**
	 * Requires a valid email (value already passed through sanitize_email()).
	 *
	 * @param string $field Field.
	 * @param string $value Value.
	 * @return self
	 */
	public function email( string $field, string $value ): self {
		if ( '' === $value ) {
			$this->add( $field, 'Adresă de email invalidă.' );
		}
		return $this;
	}

	/**
	 * Maximum length in characters.
	 *
	 * @param string $field Field.
	 * @param string $value Value.
	 * @param int    $max   Max length.
	 * @return self
	 */
	public function max_length( string $field, string $value, int $max ): self {
		if ( mb_strlen( $value ) > $max ) {
			$this->add( $field, sprintf( 'Maximum %d caractere.', $max ) );
		}
		return $this;
	}

	/**
	 * Value must be one of the allowed values.
	 *
	 * @param string $field   Field.
	 * @param mixed  $value   Value.
	 * @param array  $allowed Allowed values.
	 * @return self
	 */
	public function in( string $field, $value, array $allowed ): self {
		if ( ! in_array( $value, $allowed, true ) ) {
			$this->add( $field, 'Valoare nepermisă.' );
		}
		return $this;
	}

	/**
	 * Integer range.
	 *
	 * @param string $field Field.
	 * @param int    $value Value.
	 * @param int    $min   Min.
	 * @param int    $max   Max.
	 * @return self
	 */
	public function between( string $field, int $value, int $min, int $max ): self {
		if ( $value < $min || $value > $max ) {
			$this->add( $field, sprintf( 'Valoarea trebuie să fie între %d și %d.', $min, $max ) );
		}
		return $this;
	}

	/**
	 * Optional pattern match (skipped when empty).
	 *
	 * @param string $field   Field.
	 * @param string $value   Value.
	 * @param string $pattern Regex.
	 * @param string $message Message.
	 * @return self
	 */
	public function pattern( string $field, string $value, string $pattern, string $message ): self {
		if ( '' !== $value && ! preg_match( $pattern, $value ) ) {
			$this->add( $field, $message );
		}
		return $this;
	}

	/**
	 * Adds a custom error.
	 *
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return self
	 */
	public function add( string $field, string $message ): self {
		if ( ! isset( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = $message;
		}
		return $this;
	}

	/**
	 * Whether validation failed.
	 *
	 * @return bool
	 */
	public function fails(): bool {
		return ! empty( $this->errors );
	}

	/**
	 * Returns errors.
	 *
	 * @return array<string,string>
	 */
	public function errors(): array {
		return $this->errors;
	}
}
