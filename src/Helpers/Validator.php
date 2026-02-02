<?php
/**
 * Validator helper class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\Helpers;

use WP_REST_Request;

/**
 * Validates API request parameters.
 */
class Validator {

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private array $errors = array();

	/**
	 * Request data to validate.
	 *
	 * @var array
	 */
	private array $data = array();

	/**
	 * Constructor.
	 *
	 * @param array|WP_REST_Request $data Data to validate.
	 */
	public function __construct( array|WP_REST_Request $data ) {
		if ( $data instanceof WP_REST_Request ) {
			$this->data = $data->get_params();
		} else {
			$this->data = $data;
		}
	}

	/**
	 * Validate required field.
	 *
	 * @param string $field   Field name.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function required( string $field, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( null === $value || '' === $value ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s is required.', $this->humanize( $field ) )
			);
		}

		return $this;
	}

	/**
	 * Validate email field.
	 *
	 * @param string $field   Field name.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function email( string $field, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && ! is_email( $value ) ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be a valid email address.', $this->humanize( $field ) )
			);
		}

		return $this;
	}

	/**
	 * Validate minimum length.
	 *
	 * @param string $field   Field name.
	 * @param int    $length  Minimum length.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function min_length( string $field, int $length, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && strlen( (string) $value ) < $length ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be at least %d characters.', $this->humanize( $field ), $length )
			);
		}

		return $this;
	}

	/**
	 * Validate maximum length.
	 *
	 * @param string $field   Field name.
	 * @param int    $length  Maximum length.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function max_length( string $field, int $length, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && strlen( (string) $value ) > $length ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be no more than %d characters.', $this->humanize( $field ), $length )
			);
		}

		return $this;
	}

	/**
	 * Validate numeric field.
	 *
	 * @param string $field   Field name.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function numeric( string $field, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && ! is_numeric( $value ) ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be a number.', $this->humanize( $field ) )
			);
		}

		return $this;
	}

	/**
	 * Validate integer field.
	 *
	 * @param string $field   Field name.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function integer( string $field, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && ! filter_var( $value, FILTER_VALIDATE_INT ) ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be an integer.', $this->humanize( $field ) )
			);
		}

		return $this;
	}

	/**
	 * Validate minimum value.
	 *
	 * @param string    $field   Field name.
	 * @param int|float $min     Minimum value.
	 * @param string    $message Custom error message.
	 * @return self
	 */
	public function min( string $field, int|float $min, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value !== null && $value !== '' && (float) $value < $min ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be at least %s.', $this->humanize( $field ), $min )
			);
		}

		return $this;
	}

	/**
	 * Validate maximum value.
	 *
	 * @param string    $field   Field name.
	 * @param int|float $max     Maximum value.
	 * @param string    $message Custom error message.
	 * @return self
	 */
	public function max( string $field, int|float $max, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value !== null && $value !== '' && (float) $value > $max ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be no more than %s.', $this->humanize( $field ), $max )
			);
		}

		return $this;
	}

	/**
	 * Validate field is in allowed values.
	 *
	 * @param string $field   Field name.
	 * @param array  $allowed Allowed values.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function in( string $field, array $allowed, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && ! in_array( $value, $allowed, true ) ) {
			$this->add_error(
				$field,
				$message ?: sprintf(
					'%s must be one of: %s.',
					$this->humanize( $field ),
					implode( ', ', $allowed )
				)
			);
		}

		return $this;
	}

	/**
	 * Validate URL field.
	 *
	 * @param string $field   Field name.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function url( string $field, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be a valid URL.', $this->humanize( $field ) )
			);
		}

		return $this;
	}

	/**
	 * Validate array field.
	 *
	 * @param string $field   Field name.
	 * @param string $message Custom error message.
	 * @return self
	 */
	public function is_array( string $field, string $message = '' ): self {
		$value = $this->get_value( $field );

		if ( $value && ! is_array( $value ) ) {
			$this->add_error(
				$field,
				$message ?: sprintf( '%s must be an array.', $this->humanize( $field ) )
			);
		}

		return $this;
	}

	/**
	 * Check if validation passed.
	 *
	 * @return bool
	 */
	public function passes(): bool {
		return empty( $this->errors );
	}

	/**
	 * Check if validation failed.
	 *
	 * @return bool
	 */
	public function fails(): bool {
		return ! $this->passes();
	}

	/**
	 * Get validation errors.
	 *
	 * @return array
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Get a validated value.
	 *
	 * @param string $field Field name.
	 * @return mixed
	 */
	public function get_value( string $field ): mixed {
		return $this->data[ $field ] ?? null;
	}

	/**
	 * Get all data.
	 *
	 * @return array
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Add a validation error.
	 *
	 * @param string $field   Field name.
	 * @param string $message Error message.
	 * @return void
	 */
	private function add_error( string $field, string $message ): void {
		if ( ! isset( $this->errors[ $field ] ) ) {
			$this->errors[ $field ] = array();
		}

		$this->errors[ $field ][] = $message;
	}

	/**
	 * Convert field name to human readable.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	private function humanize( string $field ): string {
		return ucfirst( str_replace( array( '_', '-' ), ' ', $field ) );
	}
}
