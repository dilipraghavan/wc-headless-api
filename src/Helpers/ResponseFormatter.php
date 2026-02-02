<?php
/**
 * Response Formatter helper class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\Helpers;

use WP_REST_Response;
use WP_Error;

/**
 * Formats API responses in a consistent structure.
 */
class ResponseFormatter {

	/**
	 * Create a success response.
	 *
	 * @param mixed      $data    Response data.
	 * @param int        $status  HTTP status code.
	 * @param array|null $meta    Optional metadata (pagination, etc.).
	 * @return WP_REST_Response
	 */
	public static function success( mixed $data, int $status = 200, ?array $meta = null ): WP_REST_Response {
		$response = array(
			'success' => true,
			'data'    => $data,
			'meta'    => $meta,
			'errors'  => null,
		);

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Create an error response.
	 *
	 * @param string     $code    Error code.
	 * @param string     $message Error message.
	 * @param int        $status  HTTP status code.
	 * @param array|null $details Additional error details.
	 * @return WP_REST_Response
	 */
	public static function error(
		string $code,
		string $message,
		int $status = 400,
		?array $details = null
	): WP_REST_Response {
		$error = array(
			'code'    => $code,
			'message' => $message,
		);

		if ( $details ) {
			$error['details'] = $details;
		}

		$response = array(
			'success' => false,
			'data'    => null,
			'meta'    => null,
			'errors'  => array( $error ),
		);

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Create an error response from WP_Error.
	 *
	 * @param WP_Error $wp_error WordPress error object.
	 * @param int      $status   HTTP status code (optional, uses WP_Error status if available).
	 * @return WP_REST_Response
	 */
	public static function from_wp_error( WP_Error $wp_error, int $status = 400 ): WP_REST_Response {
		$error_data = $wp_error->get_error_data();

		if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
			$status = $error_data['status'];
		}

		return self::error(
			$wp_error->get_error_code(),
			$wp_error->get_error_message(),
			$status
		);
	}

	/**
	 * Create a paginated response.
	 *
	 * @param array $items    Items to return.
	 * @param int   $total    Total number of items.
	 * @param int   $page     Current page number.
	 * @param int   $per_page Items per page.
	 * @return WP_REST_Response
	 */
	public static function paginated(
		array $items,
		int $total,
		int $page,
		int $per_page
	): WP_REST_Response {
		$total_pages = (int) ceil( $total / $per_page );

		$meta = array(
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'has_more'    => $page < $total_pages,
		);

		return self::success( $items, 200, $meta );
	}

	/**
	 * Create a created response (201).
	 *
	 * @param mixed $data Response data.
	 * @return WP_REST_Response
	 */
	public static function created( mixed $data ): WP_REST_Response {
		return self::success( $data, 201 );
	}

	/**
	 * Create a no content response (204).
	 *
	 * @return WP_REST_Response
	 */
	public static function no_content(): WP_REST_Response {
		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Create an unauthorized response (401).
	 *
	 * @param string $message Error message.
	 * @return WP_REST_Response
	 */
	public static function unauthorized( string $message = 'Unauthorized' ): WP_REST_Response {
		return self::error( 'unauthorized', $message, 401 );
	}

	/**
	 * Create a forbidden response (403).
	 *
	 * @param string $message Error message.
	 * @return WP_REST_Response
	 */
	public static function forbidden( string $message = 'Forbidden' ): WP_REST_Response {
		return self::error( 'forbidden', $message, 403 );
	}

	/**
	 * Create a not found response (404).
	 *
	 * @param string $message Error message.
	 * @return WP_REST_Response
	 */
	public static function not_found( string $message = 'Not found' ): WP_REST_Response {
		return self::error( 'not_found', $message, 404 );
	}

	/**
	 * Create a validation error response (422).
	 *
	 * @param array $errors Validation errors array.
	 * @return WP_REST_Response
	 */
	public static function validation_error( array $errors ): WP_REST_Response {
		$formatted_errors = array();

		foreach ( $errors as $field => $messages ) {
			$formatted_errors[] = array(
				'code'    => 'validation_error',
				'message' => is_array( $messages ) ? implode( ' ', $messages ) : $messages,
				'field'   => $field,
			);
		}

		$response = array(
			'success' => false,
			'data'    => null,
			'meta'    => null,
			'errors'  => $formatted_errors,
		);

		return new WP_REST_Response( $response, 422 );
	}
}
