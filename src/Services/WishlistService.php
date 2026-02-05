<?php
/**
 * Wishlist Service class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\Services;

/**
 * Handles wishlist data storage and retrieval.
 */
class WishlistService {

	/**
	 * Meta key for storing wishlist.
	 *
	 * @var string
	 */
	private const META_KEY = '_wcha_wishlist';

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
	 * Get user's wishlist.
	 *
	 * @param int  $user_id       User ID.
	 * @param bool $with_products Include full product data.
	 * @return array
	 */
	public function get_wishlist( int $user_id, bool $with_products = true ): array {
		$product_ids = $this->get_wishlist_ids( $user_id );

		if ( ! $with_products ) {
			return array(
				'product_ids' => $product_ids,
				'count'       => count( $product_ids ),
			);
		}

		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product = $this->product_service->get_product( (int) $product_id );
			if ( $product ) {
				$products[] = $product;
			}
		}

		return array(
			'products' => $products,
			'count'    => count( $products ),
		);
	}

	/**
	 * Get wishlist product IDs only.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_wishlist_ids( int $user_id ): array {
		$wishlist = get_user_meta( $user_id, self::META_KEY, true );

		if ( empty( $wishlist ) || ! is_array( $wishlist ) ) {
			return array();
		}

		return array_map( 'intval', $wishlist );
	}

	/**
	 * Add product to wishlist.
	 *
	 * @param int $user_id    User ID.
	 * @param int $product_id Product ID.
	 * @return array{success: bool, message: string, wishlist?: array}
	 */
	public function add_to_wishlist( int $user_id, int $product_id ): array {
		// Verify product exists.
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return array(
				'success' => false,
				'message' => __( 'Product not found.', 'wc-headless-api' ),
			);
		}

		$wishlist = $this->get_wishlist_ids( $user_id );

		// Check if already in wishlist.
		if ( in_array( $product_id, $wishlist, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Product already in wishlist.', 'wc-headless-api' ),
			);
		}

		// Add to wishlist.
		$wishlist[] = $product_id;
		update_user_meta( $user_id, self::META_KEY, $wishlist );

		/**
		 * Action fired after product added to wishlist.
		 *
		 * @param int $user_id    User ID.
		 * @param int $product_id Product ID.
		 */
		do_action( 'wcha_wishlist_added', $user_id, $product_id );

		return array(
			'success'  => true,
			'message'  => __( 'Product added to wishlist.', 'wc-headless-api' ),
			'wishlist' => $this->get_wishlist( $user_id ),
		);
	}

	/**
	 * Remove product from wishlist.
	 *
	 * @param int $user_id    User ID.
	 * @param int $product_id Product ID.
	 * @return array{success: bool, message: string, wishlist?: array}
	 */
	public function remove_from_wishlist( int $user_id, int $product_id ): array {
		$wishlist = $this->get_wishlist_ids( $user_id );

		// Check if in wishlist.
		$key = array_search( $product_id, $wishlist, true );
		if ( false === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Product not in wishlist.', 'wc-headless-api' ),
			);
		}

		// Remove from wishlist.
		unset( $wishlist[ $key ] );
		$wishlist = array_values( $wishlist ); // Re-index array.
		update_user_meta( $user_id, self::META_KEY, $wishlist );

		/**
		 * Action fired after product removed from wishlist.
		 *
		 * @param int $user_id    User ID.
		 * @param int $product_id Product ID.
		 */
		do_action( 'wcha_wishlist_removed', $user_id, $product_id );

		return array(
			'success'  => true,
			'message'  => __( 'Product removed from wishlist.', 'wc-headless-api' ),
			'wishlist' => $this->get_wishlist( $user_id ),
		);
	}

	/**
	 * Clear entire wishlist.
	 *
	 * @param int $user_id User ID.
	 * @return array{success: bool, message: string}
	 */
	public function clear_wishlist( int $user_id ): array {
		delete_user_meta( $user_id, self::META_KEY );

		/**
		 * Action fired after wishlist cleared.
		 *
		 * @param int $user_id User ID.
		 */
		do_action( 'wcha_wishlist_cleared', $user_id );

		return array(
			'success' => true,
			'message' => __( 'Wishlist cleared.', 'wc-headless-api' ),
		);
	}

	/**
	 * Check if product is in wishlist.
	 *
	 * @param int $user_id    User ID.
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function is_in_wishlist( int $user_id, int $product_id ): bool {
		$wishlist = $this->get_wishlist_ids( $user_id );

		return in_array( $product_id, $wishlist, true );
	}

	/**
	 * Get wishlist count.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_wishlist_count( int $user_id ): int {
		return count( $this->get_wishlist_ids( $user_id ) );
	}
}
