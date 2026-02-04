<?php
/**
 * Product Controller class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WCHeadlessAPI\Services\ProductService;

/**
 * Handles product API endpoints.
 */
class ProductController extends BaseController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'products';

	/**
	 * Product service instance.
	 *
	 * @var ProductService
	 */
	private ProductService $product_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->product_service = new ProductService();
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /products - List products.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_products' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_products_args(),
				),
			)
		);

		// GET /products/search - Search products.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_products' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_search_args(),
				),
			)
		);

		// GET /products/{id} - Single product by ID.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_id_param(),
				),
			)
		);

		// GET /products/slug/{slug} - Single product by slug.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/slug/(?P<slug>[a-zA-Z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_by_slug' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'slug' => array(
							'description'       => __( 'Product slug.', 'wc-headless-api' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_title',
						),
					),
				),
			)
		);

		// GET /products/{id}/related - Related products.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/related',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_related_products' ),
					'permission_callback' => '__return_true',
					'args'                => array_merge(
						$this->get_id_param(),
						array(
							'limit' => array(
								'description'       => __( 'Number of related products.', 'wc-headless-api' ),
								'type'              => 'integer',
								'default'           => 4,
								'minimum'           => 1,
								'maximum'           => 12,
								'sanitize_callback' => 'absint',
							),
						)
					),
				),
			)
		);
	}

	/**
	 * Get products list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_products( WP_REST_Request $request ): WP_REST_Response {
		$pagination = $this->get_pagination_params( $request );

		$args = array(
			'page'      => $pagination['page'],
			'per_page'  => $pagination['per_page'],
			'category'  => $request->get_param( 'category' ) ?? '',
			'orderby'   => $request->get_param( 'orderby' ) ?? 'date',
			'order'     => $request->get_param( 'order' ) ?? 'DESC',
			'min_price' => $request->get_param( 'min_price' ) ?? '',
			'max_price' => $request->get_param( 'max_price' ) ?? '',
			'featured'  => $request->get_param( 'featured' ),
			'on_sale'   => $request->get_param( 'on_sale' ),
		);

		$result = $this->product_service->get_products( $args );

		return $this->paginated(
			$result['products'],
			$result['total'],
			$pagination['page'],
			$pagination['per_page']
		);
	}

	/**
	 * Search products.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search_products( WP_REST_Request $request ): WP_REST_Response {
		$query = $request->get_param( 'q' );

		if ( empty( $query ) ) {
			return $this->error(
				'missing_query',
				__( 'Search query is required.', 'wc-headless-api' ),
				400
			);
		}

		$pagination = $this->get_pagination_params( $request );

		$result = $this->product_service->search_products(
			$query,
			$pagination['page'],
			$pagination['per_page']
		);

		return $this->paginated(
			$result['products'],
			$result['total'],
			$pagination['page'],
			$pagination['per_page']
		);
	}

	/**
	 * Get single product by ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_product( WP_REST_Request $request ): WP_REST_Response {
		$product_id = (int) $request->get_param( 'id' );

		$product = $this->product_service->get_product( $product_id );

		if ( ! $product ) {
			return $this->not_found( __( 'Product not found.', 'wc-headless-api' ) );
		}

		return $this->success( $product );
	}

	/**
	 * Get single product by slug.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_product_by_slug( WP_REST_Request $request ): WP_REST_Response {
		$slug = $request->get_param( 'slug' );

		$product = $this->product_service->get_product_by_slug( $slug );

		if ( ! $product ) {
			return $this->not_found( __( 'Product not found.', 'wc-headless-api' ) );
		}

		return $this->success( $product );
	}

	/**
	 * Get related products.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_related_products( WP_REST_Request $request ): WP_REST_Response {
		$product_id = (int) $request->get_param( 'id' );
		$limit      = (int) $request->get_param( 'limit' );

		// Verify product exists.
		$product = $this->product_service->get_product( $product_id );

		if ( ! $product ) {
			return $this->not_found( __( 'Product not found.', 'wc-headless-api' ) );
		}

		$related = $this->product_service->get_related_products( $product_id, $limit );

		return $this->success( $related );
	}

	/**
	 * Get products endpoint arguments.
	 *
	 * @return array
	 */
	private function get_products_args(): array {
		return array_merge(
			$this->get_collection_params(),
			array(
				'category'  => array(
					'description'       => __( 'Filter by category slug.', 'wc-headless-api' ),
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_title',
				),
				'orderby'   => array(
					'description'       => __( 'Sort by attribute.', 'wc-headless-api' ),
					'type'              => 'string',
					'default'           => 'date',
					'enum'              => array( 'date', 'price', 'popularity', 'rating', 'title', 'menu_order' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				'order'     => array(
					'description'       => __( 'Sort order.', 'wc-headless-api' ),
					'type'              => 'string',
					'default'           => 'DESC',
					'enum'              => array( 'ASC', 'DESC' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				'min_price' => array(
					'description'       => __( 'Minimum price filter.', 'wc-headless-api' ),
					'type'              => 'number',
					'sanitize_callback' => 'floatval',
				),
				'max_price' => array(
					'description'       => __( 'Maximum price filter.', 'wc-headless-api' ),
					'type'              => 'number',
					'sanitize_callback' => 'floatval',
				),
				'featured'  => array(
					'description' => __( 'Filter by featured status.', 'wc-headless-api' ),
					'type'        => 'boolean',
				),
				'on_sale'   => array(
					'description' => __( 'Filter by sale status.', 'wc-headless-api' ),
					'type'        => 'boolean',
				),
			)
		);
	}

	/**
	 * Get search endpoint arguments.
	 *
	 * @return array
	 */
	private function get_search_args(): array {
		return array_merge(
			$this->get_collection_params(),
			array(
				'q' => array(
					'description'       => __( 'Search query.', 'wc-headless-api' ),
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			)
		);
	}
}
