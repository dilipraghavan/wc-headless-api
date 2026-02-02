<?php
/**
 * Auth Controller class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;
use WCHeadlessAPI\Auth\JWTHandler;

/**
 * Handles authentication endpoints.
 */
class AuthController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'auth';

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
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /auth/login.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_login_args(),
				),
			)
		);

		// POST /auth/refresh.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/refresh',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'refresh' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_refresh_args(),
				),
			)
		);

		// POST /auth/logout.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /auth/me.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'me' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);
	}

	/**
	 * Login endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function login( WP_REST_Request $request ): WP_REST_Response {
		$username = $request->get_param( 'username' );
		$password = $request->get_param( 'password' );

		// Validate input.
		$validator = $this->validate( $request );
		$validator->required( 'username' )
				  ->required( 'password' );

		if ( $validator->fails() ) {
			return $this->validation_error( $validator->errors() );
		}

		// Authenticate user.
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return $this->error(
				'invalid_credentials',
				__( 'Invalid username or password.', 'wc-headless-api' ),
				401
			);
		}

		// Generate tokens.
		$tokens = $this->generate_tokens( $user );

		/**
		 * Action fired after successful authentication.
		 *
		 * @param int    $user_id      User ID.
		 * @param string $access_token Access token.
		 */
		do_action( 'wcha_auth_success', $user->ID, $tokens['access_token'] );

		return $this->success( $tokens );
	}

	/**
	 * Refresh token endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function refresh( WP_REST_Request $request ): WP_REST_Response {
		$refresh_token = $request->get_param( 'refresh_token' );

		if ( empty( $refresh_token ) ) {
			return $this->error(
				'missing_token',
				__( 'Refresh token is required.', 'wc-headless-api' ),
				400
			);
		}

		// Validate refresh token.
		$result = $this->jwt_handler->validate_token(
			$refresh_token,
			JWTHandler::TOKEN_TYPE_REFRESH
		);

		if ( ! $result['valid'] ) {
			$status = isset( $result['expired'] ) && $result['expired'] ? 401 : 403;

			return $this->error(
				'invalid_refresh_token',
				$result['error'] ?? __( 'Invalid refresh token.', 'wc-headless-api' ),
				$status
			);
		}

		// Get user.
		$user = get_user_by( 'ID', $result['user_id'] );

		if ( ! $user ) {
			return $this->error(
				'user_not_found',
				__( 'User not found.', 'wc-headless-api' ),
				404
			);
		}

		// Generate new tokens.
		$tokens = $this->generate_tokens( $user );

		return $this->success( $tokens );
	}

	/**
	 * Logout endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function logout( WP_REST_Request $request ): WP_REST_Response {
		// JWT tokens are stateless, so we just return success.
		// In a more complex system, you might want to blacklist the token.

		return $this->success(
			array(
				'message' => __( 'Successfully logged out.', 'wc-headless-api' ),
			)
		);
	}

	/**
	 * Get current user endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function me( WP_REST_Request $request ): WP_REST_Response {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return $this->error(
				'not_authenticated',
				__( 'Not authenticated.', 'wc-headless-api' ),
				401
			);
		}

		return $this->success( $this->format_user( $user ) );
	}

	/**
	 * Check authentication for protected routes.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_auth( WP_REST_Request $request ): bool|\WP_Error {
		$token = $this->jwt_handler->get_token_from_header();

		if ( empty( $token ) ) {
			return new \WP_Error(
				'rest_not_logged_in',
				__( 'Authorization header missing.', 'wc-headless-api' ),
				array( 'status' => 401 )
			);
		}

		$result = $this->jwt_handler->validate_token( $token );

		if ( ! $result['valid'] ) {
			return new \WP_Error(
				'rest_forbidden',
				$result['error'] ?? __( 'Invalid token.', 'wc-headless-api' ),
				array( 'status' => 401 )
			);
		}

		wp_set_current_user( $result['user_id'] );

		return true;
	}

	/**
	 * Generate tokens for a user.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	private function generate_tokens( WP_User $user ): array {
		return array(
			'access_token'  => $this->jwt_handler->generate_access_token( $user->ID ),
			'refresh_token' => $this->jwt_handler->generate_refresh_token( $user->ID ),
			'token_type'    => 'Bearer',
			'expires_in'    => $this->jwt_handler->get_access_expiration(),
			'user'          => $this->format_user( $user ),
		);
	}

	/**
	 * Format user data for response.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	private function format_user( WP_User $user ): array {
		return array(
			'id'           => $user->ID,
			'email'        => $user->user_email,
			'username'     => $user->user_login,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'avatar_url'   => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
			'roles'        => $user->roles,
		);
	}

	/**
	 * Get login endpoint arguments.
	 *
	 * @return array
	 */
	private function get_login_args(): array {
		return array(
			'username' => array(
				'description'       => __( 'Username or email.', 'wc-headless-api' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'password' => array(
				'description'       => __( 'User password.', 'wc-headless-api' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => function ( $value ) {
					// Don't sanitize password, just return as-is.
					return $value;
				},
			),
		);
	}

	/**
	 * Get refresh endpoint arguments.
	 *
	 * @return array
	 */
	private function get_refresh_args(): array {
		return array(
			'refresh_token' => array(
				'description'       => __( 'Refresh token.', 'wc-headless-api' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
