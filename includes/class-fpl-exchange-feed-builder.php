<?php
/**
 * Build public exchange offer JSON payloads.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Feed_Builder {
	const NO_PRODUCTS_MESSAGE = 'No public products are available in this exchange offer.';

	/**
	 * Build the full exchange offer payload.
	 *
	 * @return array|WP_Error
	 */
	public static function build_offer() {
		if ( ! FPL_Plugin::is_woocommerce_active() ) {
			return new WP_Error( 'fpl_woocommerce_required', __( 'WooCommerce is required.', 'friend-product-links' ), array( 'status' => 503 ) );
		}

		$validate = FPL_Exchange_Repository::validate_local_offer();
		if ( is_wp_error( $validate ) ) {
			return new WP_Error(
				'fpl_offer_unavailable',
				__( 'No public products are available in this exchange offer.', 'friend-product-links' ),
				array( 'status' => 404 )
			);
		}

		$products = self::build_products();
		if ( empty( $products ) ) {
			return new WP_Error(
				'fpl_no_public_products',
				__( 'No public products are available in this exchange offer.', 'friend-product-links' ),
				array( 'status' => 404 )
			);
		}

		$token = FPL_Exchange_Repository::get_offer_token();

		return array(
			'plugin'            => 'friend-product-links',
			'type'              => 'exchange_offer',
			'version'           => FPL_VERSION,
			'site_name'         => get_bloginfo( 'name' ),
			'site_url'          => home_url( '/' ),
			'offer_url'         => FPL_Exchange_Repository::get_offer_url(),
			'request_endpoint'  => rest_url( 'friend-product-links/v1/exchange/request' ),
			'callback_endpoint' => rest_url( 'friend-product-links/v1/exchange/callback' ),
			'product_count'     => count( $products ),
			'display_count'     => FPL_EXCHANGE_DISPLAY_PRODUCTS,
			'updated_at'        => FPL_Exchange_Repository::get_offer_updated_at(),
			'products'          => $products,
		);
	}

	/**
	 * Build public product rows for the exchange offer.
	 *
	 * @return array
	 */
	private static function build_products() {
		$products = array();
		$ids      = FPL_Security::filter_valid_product_ids( FPL_Exchange_Repository::get_offer_product_ids(), FPL_EXCHANGE_MAX_PRODUCTS );

		foreach ( $ids as $product_id ) {
			if ( 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id ) ) {
				continue;
			}

			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( ! $product || ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) ) {
				continue;
			}

			$title       = wp_strip_all_tags( $product->get_name() );
			$product_url = get_permalink( $product_id );
			if ( '' === $title || ! $product_url ) {
				continue;
			}

			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

			$products[] = array(
				'title'        => $title,
				'image_url'    => $image_url ? esc_url_raw( $image_url ) : '',
				'price_text'   => FPL_Feed_Builder::get_clean_price_text( $product ),
				'product_url'  => esc_url_raw( $product_url ),
				'product_hash' => FPL_Security::product_hash( $product_url ),
			);
		}

		return $products;
	}
}