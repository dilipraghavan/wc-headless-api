<?php
/**
 * Base Controller class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WCHeadlessAPI\Helpers\ResponseFormatter;
use WCHeadlessAPI\Helpers\Validator;

/**
 * Base controller for all API endpoints.
 */
abstract class BaseController extends WP_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc-headless/v1';

	/**
	 * Get pagination parameters from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array{page: int, per_page: int, offset: int}
	 */
	protected function get_pagination_params( WP_REST_Request $request ): array {
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 12 ) );
		$offset   = ( $page - 1 ) * $per_page;

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => $offset,
		);
	}

	/**
	 * Create a validator instance.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return Validator
	 */
	protected function validate( WP_REST_Request $request ): Validator {
		return new Validator( $request );
	}

	/**
	 * Success response helper.
	 *
	 * @param mixed      $data   Response data.
	 * @param int        $status HTTP status code.
	 * @param array|null $meta   Optional metadata.
	 * @return WP_REST_Response
	 */
	protected function success( mixed $data, int $status = 200, ?array $meta = null ): WP_REST_Response {
		return ResponseFormatter::success( $data, $status, $meta );
	}

	/**
	 * Error response helper.
	 *
	 * @param string     $code    Error code.
	 * @param string     $message Error message.
	 * @param int        $status  HTTP status code.
	 * @param array|null $details Additional details.
	 * @return WP_REST_Response
	 */
	protected function error(
		string $code,
		string $message,
		int $status = 400,
		?array $details = null
	): WP_REST_Response {
		return ResponseFormatter::error( $code, $message, $status, $details );
	}

	/**
	 * Paginated response helper.
	 *
	 * @param array $items    Items to return.
	 * @param int   $total    Total items count.
	 * @param int   $page     Current page.
	 * @param int   $per_page Items per page.
	 * @return WP_REST_Response
	 */
	protected function paginated( array $items, int $total, int $page, int $per_page ): WP_REST_Response {
		return ResponseFormatter::paginated( $items, $total, $page, $per_page );
	}

	/**
	 * Validation error response helper.
	 *
	 * @param array $errors Validation errors.
	 * @return WP_REST_Response
	 */
	protected function validation_error( array $errors ): WP_REST_Response {
		return ResponseFormatter::validation_error( $errors );
	}

	/**
	 * Not found response helper.
	 *
	 * @param string $message Error message.
	 * @return WP_REST_Response
	 */
	protected function not_found( string $message = 'Resource not found.' ): WP_REST_Response {
		return ResponseFormatter::not_found( $message );
	}

	/**
	 * Created response helper.
	 *
	 * @param mixed $data Response data.
	 * @return WP_REST_Response
	 */
	protected function created( mixed $data ): WP_REST_Response {
		return ResponseFormatter::created( $data );
	}

	/**
	 * No content response helper.
	 *
	 * @return WP_REST_Response
	 */
	protected function no_content(): WP_REST_Response {
		return ResponseFormatter::no_content();
	}

	/**
	 * Get common collection params.
	 *
	 * @return array
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'wc-headless-api' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items per page.', 'wc-headless-api' ),
				'type'              => 'integer',
				'default'           => 12,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get ID parameter schema.
	 *
	 * @return array
	 */
	protected function get_id_param(): array {
		return array(
			'id' => array(
				'description'       => __( 'Unique identifier.', 'wc-headless-api' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $value ) {
					return is_numeric( $value ) && $value > 0;
				},
			),
		);
	}
}
