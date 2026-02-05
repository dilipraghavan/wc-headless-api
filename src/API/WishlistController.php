<?php
/**
 * Wishlist Controller class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WCHeadlessAPI\Auth\JWTHandler;
use WCHeadlessAPI\Services\WishlistService;

/**
 * Handles wishlist API endpoints.
 */
class WishlistController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'wishlist';

	/**
	 * Wishlist service instance.
	 *
	 * @var WishlistService
	 */
	private WishlistService $wishlist_service;

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
		$this->jwt_handler      = $jwt_handler;
		$this->wishlist_service = new WishlistService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /wishlist - Get user's wishlist.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wishlist' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// POST /wishlist - Add to wishlist.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_to_wishlist' ),
					'permission_callback' => array( $this, 'check_auth' ),
					'args'                => array(
						'product_id' => array(
							'description'       => __( 'Product ID to add.', 'wc-headless-api' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $value ) {
								return is_numeric( $value ) && $value > 0;
							},
						),
					),
				),
			)
		);

		// DELETE /wishlist/{product_id} - Remove from wishlist.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<product_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_from_wishlist' ),
					'permission_callback' => array( $this, 'check_auth' ),
					'args'                => array(
						'product_id' => array(
							'description'       => __( 'Product ID to remove.', 'wc-headless-api' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $value ) {
								return is_numeric( $value ) && $value > 0;
							},
						),
					),
				),
			)
		);

		// DELETE /wishlist - Clear entire wishlist.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clear',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_wishlist' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// GET /wishlist/check/{product_id} - Check if product in wishlist.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/check/(?P<product_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'check_in_wishlist' ),
					'permission_callback' => array( $this, 'check_auth' ),
					'args'                => array(
						'product_id' => array(
							'description'       => __( 'Product ID to check.', 'wc-headless-api' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /wishlist/ids - Get wishlist IDs only (lightweight).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/ids',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_wishlist_ids' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);
	}

	/**
	 * Get user's wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_wishlist( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = get_current_user_id();
		$wishlist = $this->wishlist_service->get_wishlist( $user_id );

		return $this->success( $wishlist );
	}

	/**
	 * Get wishlist IDs only.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_wishlist_ids( WP_REST_Request $request ): WP_REST_Response {
		$user_id  = get_current_user_id();
		$wishlist = $this->wishlist_service->get_wishlist( $user_id, false );

		return $this->success( $wishlist );
	}

	/**
	 * Add product to wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function add_to_wishlist( WP_REST_Request $request ): WP_REST_Response {
		$user_id    = get_current_user_id();
		$product_id = (int) $request->get_param( 'product_id' );

		$result = $this->wishlist_service->add_to_wishlist( $user_id, $product_id );

		if ( ! $result['success'] ) {
			return $this->error(
				'wishlist_error',
				$result['message'],
				400
			);
		}

		return $this->created( $result );
	}

	/**
	 * Remove product from wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function remove_from_wishlist( WP_REST_Request $request ): WP_REST_Response {
		$user_id    = get_current_user_id();
		$product_id = (int) $request->get_param( 'product_id' );

		$result = $this->wishlist_service->remove_from_wishlist( $user_id, $product_id );

		if ( ! $result['success'] ) {
			return $this->error(
				'wishlist_error',
				$result['message'],
				400
			);
		}

		return $this->success( $result );
	}

	/**
	 * Clear entire wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function clear_wishlist( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$result  = $this->wishlist_service->clear_wishlist( $user_id );

		return $this->success( $result );
	}

	/**
	 * Check if product is in wishlist.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function check_in_wishlist( WP_REST_Request $request ): WP_REST_Response {
		$user_id    = get_current_user_id();
		$product_id = (int) $request->get_param( 'product_id' );

		$in_wishlist = $this->wishlist_service->is_in_wishlist( $user_id, $product_id );

		return $this->success(
			array(
				'product_id'  => $product_id,
				'in_wishlist' => $in_wishlist,
			)
		);
	}

	/**
	 * Check authentication.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_auth( WP_REST_Request $request ): bool|WP_Error {
		$token = $this->jwt_handler->get_token_from_header();

		if ( empty( $token ) ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'Authentication required.', 'wc-headless-api' ),
				array( 'status' => 401 )
			);
		}

		$result = $this->jwt_handler->validate_token( $token );

		if ( ! $result['valid'] ) {
			return new WP_Error(
				'rest_forbidden',
				$result['error'] ?? __( 'Invalid token.', 'wc-headless-api' ),
				array( 'status' => 401 )
			);
		}

		wp_set_current_user( $result['user_id'] );

		return true;
	}
}
