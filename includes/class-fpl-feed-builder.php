<?php
/**
 * Build public feed payloads for share links.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Feed_Builder {
	const NO_PRODUCTS_MESSAGE = 'No public products are available in this friend product link.';

	/**
	 * Build feed by public token.
	 *
	 * @param string $token Feed token.
	 * @return array|WP_Error
	 */
	public static function build_by_token( $token ) {
		$post = FPL_CPT::get_share_link_by_token( sanitize_text_field( $token ) );

		if ( ! $post || '1' !== get_post_meta( $post->ID, FPL_Repository::META_ENABLED, true ) ) {
			return new WP_Error( 'fpl_not_found', __( 'Feed not found.', 'friend-product-links' ), array( 'status' => 404 ) );
		}

		return self::build_by_share_link_id( $post->ID );
	}

	/**
	 * Build feed by share link ID.
	 *
	 * @param int $share_link_id Share link ID.
	 * @return array|WP_Error
	 */
	public static function build_by_share_link_id( $share_link_id ) {
		if ( ! FPL_Plugin::is_woocommerce_active() ) {
			return new WP_Error( 'fpl_woocommerce_required', __( 'WooCommerce is required.', 'friend-product-links' ), array( 'status' => 503 ) );
		}

		$post = FPL_Repository::get_share_link( $share_link_id );
		if ( ! $post ) {
			return new WP_Error( 'fpl_not_found', __( 'Feed not found.', 'friend-product-links' ), array( 'status' => 404 ) );
		}

		$products = self::build_products( FPL_Repository::get_share_link_products( $post->ID ) );
		if ( empty( $products ) ) {
			return new WP_Error( 'fpl_no_public_products', __( 'No public products are available in this friend product link.', 'friend-product-links' ), array( 'status' => 404 ) );
		}

		$payload = array(
			'plugin'     => 'friend-product-links',
			'version'    => FPL_VERSION,
			'site_name'  => get_bloginfo( 'name' ),
			'site_url'   => home_url( '/' ),
			'feed_name'  => get_the_title( $post ),
			'updated_at' => current_time( 'c' ),
			'products'   => $products,
		);

		if ( 'yes' === get_option( FPL_Repository::OPTION_RECEIVE_STATS, 'yes' ) ) {
			$token = FPL_Repository::get_share_link_token( $post->ID );
			$payload['stats_endpoint'] = rest_url( 'friend-product-links/v1/stats/' . rawurlencode( $token ) );
			$payload['stats_key']      = FPL_Performance::ensure_share_stats_key( $post->ID );
		} else {
			$payload['stats_endpoint'] = null;
			$payload['stats_key']      = null;
		}

		return $payload;
	}

	/**
	 * Build public product rows.
	 *
	 * @param int[] $product_ids Product IDs.
	 * @return array
	 */
	private static function build_products( array $product_ids ) {
		$products = array();

		foreach ( FPL_Security::filter_valid_product_ids( $product_ids, FPL_CORE_PRODUCT_MAX ) as $product_id ) {
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
				'price_text'   => self::get_clean_price_text( $product ),
				'product_url'  => esc_url_raw( $product_url ),
				'product_hash' => FPL_Security::product_hash( $product_url ),
			);
		}

		return $products;
	}

	/**
	 * Get clean price text without screen-reader HTML or HTML entities.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public static function get_clean_price_text( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}

		$is_variable = $product->is_type( 'variable' );

		if ( $is_variable ) {
			$price = $product->get_variation_price( 'min', true );
		} else {
			$price = $product->get_price();
		}

		if ( '' === $price || null === $price ) {
			return '';
		}

		$price_text = self::format_plain_price_text( $price );

		if ( '' === $price_text ) {
			return '';
		}

		return $is_variable ? $price_text . '+' : $price_text;
	}

	/**
	 * Strip HTML, decode entities, and normalize whitespace from a wc_price() string.
	 *
	 * @param float $price Raw price value.
	 * @return string
	 */
	private static function format_plain_price_text( $price ) {
		$html = wc_price( $price );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return sanitize_text_field( trim( $text ) );
	}
}
