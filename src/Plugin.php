<?php
/**
 * Main Plugin class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI;

use WCHeadlessAPI\Auth\JWTHandler;
use WCHeadlessAPI\Auth\AuthMiddleware;
use WCHeadlessAPI\API\AuthController;
use WCHeadlessAPI\API\ProductController;
use WCHeadlessAPI\Helpers\ResponseFormatter;

/**
 * Plugin bootstrap class.
 */
class Plugin {

	/**
	 * JWT Handler instance.
	 *
	 * @var JWTHandler
	 */
	private JWTHandler $jwt_handler;

	/**
	 * Auth Middleware instance.
	 *
	 * @var AuthMiddleware
	 */
	private AuthMiddleware $auth_middleware;

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @return void
	 */
	private function init_services(): void {
		$this->jwt_handler     = new JWTHandler();
		$this->auth_middleware = new AuthMiddleware( $this->jwt_handler );
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Register REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Add CORS headers.
		add_action( 'rest_api_init', array( $this, 'add_cors_headers' ) );

		// Handle preflight requests.
		add_action( 'init', array( $this, 'handle_preflight' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Auth controller.
		$auth_controller = new AuthController( $this->jwt_handler );
		$auth_controller->register_routes();

		// Product controller.
		$product_controller = new ProductController();
		$product_controller->register_routes();
	}

	/**
	 * Add CORS headers for REST API.
	 *
	 * @return void
	 */
	public function add_cors_headers(): void {
		// Remove default WordPress CORS.
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

		// Add custom CORS headers.
		add_filter( 'rest_pre_serve_request', array( $this, 'send_cors_headers' ), 10, 1 );
	}

	/**
	 * Send CORS headers.
	 *
	 * @param mixed $value Pre-serve request value.
	 * @return mixed
	 */
	public function send_cors_headers( mixed $value ): mixed {
		$origin = $this->get_request_origin();

		if ( $this->is_origin_allowed( $origin ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Access-Control-Allow-Credentials: true' );
		}

		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With' );
		header( 'Access-Control-Max-Age: 86400' );

		return $value;
	}

	/**
	 * Handle preflight OPTIONS requests.
	 *
	 * @return void
	 */
	public function handle_preflight(): void {
		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			$origin = $this->get_request_origin();

			if ( $this->is_origin_allowed( $origin ) ) {
				header( 'Access-Control-Allow-Origin: ' . $origin );
				header( 'Access-Control-Allow-Credentials: true' );
			}

			header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With' );
			header( 'Access-Control-Max-Age: 86400' );
			header( 'Content-Length: 0' );
			header( 'Content-Type: text/plain' );
			exit;
		}
	}

	/**
	 * Get request origin.
	 *
	 * @return string
	 */
	private function get_request_origin(): string {
		return isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
	}

	/**
	 * Check if origin is allowed.
	 *
	 * @param string $origin Origin URL.
	 * @return bool
	 */
	private function is_origin_allowed( string $origin ): bool {
		if ( empty( $origin ) ) {
			return false;
		}

		/**
		 * Filter allowed origins.
		 *
		 * @param array $origins Allowed origins.
		 */
		$allowed_origins = apply_filters(
			'wcha_allowed_origins',
			array(
				'http://localhost:3000',
				'https://localhost:3000',
			)
		);

		// Add origins from settings.
		$settings = get_option( 'wcha_settings', array() );
		if ( ! empty( $settings['allowed_origins'] ) ) {
			$allowed_origins = array_merge( $allowed_origins, (array) $settings['allowed_origins'] );
		}

		return in_array( $origin, $allowed_origins, true );
	}

	/**
	 * Get JWT Handler instance.
	 *
	 * @return JWTHandler
	 */
	public function get_jwt_handler(): JWTHandler {
		return $this->jwt_handler;
	}

	/**
	 * Get Auth Middleware instance.
	 *
	 * @return AuthMiddleware
	 */
	public function get_auth_middleware(): AuthMiddleware {
		return $this->auth_middleware;
	}
}
