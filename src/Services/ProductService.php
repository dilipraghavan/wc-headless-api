<?php
/**
 * Product Service class.
 *
 * @package WCHeadlessAPI
 */

declare(strict_types=1);

namespace WCHeadlessAPI\Services;

use WC_Product;
use WC_Product_Variable;

/**
 * Handles product data retrieval and formatting.
 */
class ProductService {

	/**
	 * Get products with filters and pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array{products: array, total: int}
	 */
	public function get_products( array $args = array() ): array {
		$defaults = array(
			'page'     => 1,
			'per_page' => 12,
			'category' => '',
			'search'   => '',
			'orderby'  => 'date',
			'order'    => 'DESC',
			'min_price' => '',
			'max_price' => '',
			'featured' => null,
			'on_sale'  => null,
			'status'   => 'publish',
		);

		$args = wp_parse_args( $args, $defaults );

		$query_args = array(
			'status'   => $args['status'],
			'limit'    => $args['per_page'],
			'page'     => $args['page'],
			'orderby'  => $args['orderby'],
			'order'    => $args['order'],
			'paginate' => true,
		);

		// Category filter.
		if ( ! empty( $args['category'] ) ) {
			$query_args['category'] = array( $args['category'] );
		}

		// Search filter.
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = $args['search'];
		}

		// Price filters.
		if ( '' !== $args['min_price'] ) {
			$query_args['min_price'] = (float) $args['min_price'];
		}

		if ( '' !== $args['max_price'] ) {
			$query_args['max_price'] = (float) $args['max_price'];
		}

		// Featured filter.
		if ( null !== $args['featured'] ) {
			$query_args['featured'] = (bool) $args['featured'];
		}

		// On sale filter.
		if ( null !== $args['on_sale'] ) {
			$query_args['on_sale'] = (bool) $args['on_sale'];
		}

		/**
		 * Filter product query args.
		 *
		 * @param array $query_args WC query arguments.
		 * @param array $args       Original request arguments.
		 */
		$query_args = apply_filters( 'wcha_products_query_args', $query_args, $args );

		$results = wc_get_products( $query_args );

		$products = array();
		foreach ( $results->products as $product ) {
			$products[] = $this->format_product( $product );
		}

		return array(
			'products' => $products,
			'total'    => $results->total,
		);
	}

	/**
	 * Get single product by ID.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null
	 */
	public function get_product( int $product_id ): ?array {
		$product = wc_get_product( $product_id );

		if ( ! $product || 'publish' !== $product->get_status() ) {
			return null;
		}

		return $this->format_product( $product, true );
	}

	/**
	 * Get product by slug.
	 *
	 * @param string $slug Product slug.
	 * @return array|null
	 */
	public function get_product_by_slug( string $slug ): ?array {
		$product = get_page_by_path( $slug, OBJECT, 'product' );

		if ( ! $product ) {
			return null;
		}

		return $this->get_product( $product->ID );
	}

	/**
	 * Search products.
	 *
	 * @param string $query   Search query.
	 * @param int    $page    Page number.
	 * @param int    $per_page Items per page.
	 * @return array{products: array, total: int}
	 */
	public function search_products( string $query, int $page = 1, int $per_page = 12 ): array {
		return $this->get_products(
			array(
				'search'   => $query,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * Get related products.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Number of products.
	 * @return array
	 */
	public function get_related_products( int $product_id, int $limit = 4 ): array {
		$related_ids = wc_get_related_products( $product_id, $limit );
		$products    = array();

		foreach ( $related_ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$products[] = $this->format_product( $product );
			}
		}

		return $products;
	}

	/**
	 * Format product data for API response.
	 *
	 * @param WC_Product $product  Product object.
	 * @param bool       $detailed Include detailed info.
	 * @return array
	 */
	public function format_product( WC_Product $product, bool $detailed = false ): array {
		$data = array(
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'slug'           => $product->get_slug(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'permalink'      => $product->get_permalink(),
			'sku'            => $product->get_sku(),
			'price'          => $product->get_price(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'price_html'     => $product->get_price_html(),
			'on_sale'        => $product->is_on_sale(),
			'featured'       => $product->is_featured(),
			'stock_status'   => $product->get_stock_status(),
			'stock_quantity' => $product->get_stock_quantity(),
			'in_stock'       => $product->is_in_stock(),
			'short_description' => $product->get_short_description(),
			'categories'     => $this->get_product_categories( $product ),
			'tags'           => $this->get_product_tags( $product ),
			'images'         => $this->get_product_images( $product ),
			'average_rating' => $product->get_average_rating(),
			'rating_count'   => $product->get_rating_count(),
			'date_created'   => $product->get_date_created()?->format( 'c' ),
		);

		// Add detailed info for single product view.
		if ( $detailed ) {
			$data['description']    = $product->get_description();
			$data['attributes']     = $this->get_product_attributes( $product );
			$data['default_attributes'] = $product->get_default_attributes();
			$data['gallery_images'] = $this->get_gallery_images( $product );
			$data['related_ids']    = wc_get_related_products( $product->get_id(), 4 );

			// Add variations for variable products.
			if ( $product instanceof WC_Product_Variable ) {
				$data['variations']           = $this->get_product_variations( $product );
				$data['variation_attributes'] = $product->get_variation_attributes();
			}
		}

		/**
		 * Filter formatted product data.
		 *
		 * @param array      $data     Formatted product data.
		 * @param WC_Product $product  Product object.
		 * @param bool       $detailed Whether detailed view.
		 */
		return apply_filters( 'wcha_product_data', $data, $product, $detailed );
	}

	/**
	 * Get product categories.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_product_categories( WC_Product $product ): array {
		$categories = array();
		$term_ids   = $product->get_category_ids();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$categories[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		return $categories;
	}

	/**
	 * Get product tags.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_product_tags( WC_Product $product ): array {
		$tags     = array();
		$term_ids = $product->get_tag_ids();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'product_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				$tags[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		return $tags;
	}

	/**
	 * Get product images.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_product_images( WC_Product $product ): array {
		$images = array();

		// Main image.
		$main_image_id = $product->get_image_id();
		if ( $main_image_id ) {
			$images[] = $this->format_image( $main_image_id, true );
		}

		// Gallery images.
		$gallery_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_ids as $image_id ) {
			$images[] = $this->format_image( $image_id );
		}

		return $images;
	}

	/**
	 * Get gallery images only.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_gallery_images( WC_Product $product ): array {
		$images      = array();
		$gallery_ids = $product->get_gallery_image_ids();

		foreach ( $gallery_ids as $image_id ) {
			$images[] = $this->format_image( $image_id );
		}

		return $images;
	}

	/**
	 * Format image data.
	 *
	 * @param int|string  $image_id Image attachment ID.
	 * @param bool $is_main  Whether this is the main image.
	 * @return array
	 */
	private function format_image( int|string $image_id, bool $is_main = false ): array {
	    $image_id = (int) $image_id;
		$full      = wp_get_attachment_image_src( $image_id, 'full' );
		$thumbnail = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );
		$medium    = wp_get_attachment_image_src( $image_id, 'medium' );

		return array(
			'id'        => $image_id,
			'src'       => $full ? $full[0] : '',
			'thumbnail' => $thumbnail ? $thumbnail[0] : '',
			'medium'    => $medium ? $medium[0] : '',
			'alt'       => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			'is_main'   => $is_main,
		);
	}

	/**
	 * Get product attributes.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function get_product_attributes( WC_Product $product ): array {
		$attributes = array();

		foreach ( $product->get_attributes() as $attribute ) {
			$attr_data = array(
				'id'        => $attribute->get_id(),
				'name'      => $attribute->get_name(),
				'position'  => $attribute->get_position(),
				'visible'   => $attribute->get_visible(),
				'variation' => $attribute->get_variation(),
				'options'   => array(),
			);

			if ( $attribute->is_taxonomy() ) {
				$terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) );
				foreach ( $terms as $term ) {
					$attr_data['options'][] = array(
						'id'   => $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					);
				}
			} else {
				$attr_data['options'] = $attribute->get_options();
			}

			$attributes[] = $attr_data;
		}

		return $attributes;
	}

	/**
	 * Get product variations.
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return array
	 */
	private function get_product_variations( WC_Product_Variable $product ): array {
		$variations = array();

		foreach ( $product->get_available_variations() as $variation ) {
			$variation_obj = wc_get_product( $variation['variation_id'] );

			if ( ! $variation_obj ) {
				continue;
			}

			$variations[] = array(
				'id'             => $variation['variation_id'],
				'sku'            => $variation_obj->get_sku(),
				'price'          => $variation_obj->get_price(),
				'regular_price'  => $variation_obj->get_regular_price(),
				'sale_price'     => $variation_obj->get_sale_price(),
				'in_stock'       => $variation_obj->is_in_stock(),
				'stock_quantity' => $variation_obj->get_stock_quantity(),
				'attributes'     => $variation['attributes'],
				'image'          => $variation['image'],
			);
		}

		return $variations;
	}
}
