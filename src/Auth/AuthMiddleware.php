<?php
/**
 * Auth Middleware class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\Auth;

use WP_Error;
use WP_REST_Request;

/**
 * Middleware for authenticating REST API requests.
 */
class AuthMiddleware {

	/**
	 * JWT Handler instance.
	 *
	 * @var JWTHandler
	 */
	private JWTHandler $jwt_handler;

	/**
	 * Constructor.
	 *
	 * @param JWTHandler $jwt_handler JWT Handler instance.
	 */
	public function __construct( JWTHandler $jwt_handler ) {
		$this->jwt_handler = $jwt_handler;
	}

	/**
	 * Permission callback for authenticated endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function authenticate( WP_REST_Request $request ): bool|WP_Error {
		$token = $this->jwt_handler->get_token_from_header();

		if ( empty( $token ) ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authorization header missing or invalid.', 'wc-headless-api' ),
				array( 'status' => 401 )
			);
		}

		$result = $this->jwt_handler->validate_token( $token );

		if ( ! $result['valid'] ) {
			$status = isset( $result['expired'] ) && $result['expired'] ? 401 : 403;

			return new WP_Error(
				'rest_forbidden',
				$result['error'] ?? __( 'Invalid token.', 'wc-headless-api' ),
				array( 'status' => $status )
			);
		}

		// Set current user for the request.
		wp_set_current_user( $result['user_id'] );

		// Store user ID in request for easy access.
		$request->set_param( 'authenticated_user_id', $result['user_id'] );

		return true;
	}

	/**
	 * Permission callback for optional authentication.
	 * Returns true always but sets user if token is valid.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function optional_authenticate( WP_REST_Request $request ): bool {
		$token = $this->jwt_handler->get_token_from_header();

		if ( ! empty( $token ) ) {
			$result = $this->jwt_handler->validate_token( $token );

			if ( $result['valid'] ) {
				wp_set_current_user( $result['user_id'] );
				$request->set_param( 'authenticated_user_id', $result['user_id'] );
			}
		}

		return true;
	}

	/**
	 * Get authenticated user ID from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return int|null
	 */
	public function get_user_id( WP_REST_Request $request ): ?int {
		$user_id = $request->get_param( 'authenticated_user_id' );

		return $user_id ? (int) $user_id : null;
	}
}
