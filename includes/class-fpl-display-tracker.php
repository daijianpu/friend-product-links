<?php
/**
 * Server-rendered display tracking.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Display_Tracker {
	/**
	 * Record displayed products from PHP rendering.
	 *
	 * @param array $products Rendered product rows.
	 * @return void
	 */
	public static function record_displayed_products( $products ) {
		static $counted = array();

		if ( 'yes' !== get_option( FPL_Repository::OPTION_ENABLE_STATS, 'yes' ) || empty( $products ) || ! is_array( $products ) ) {
			return;
		}

		$grouped = array();
		foreach ( $products as $product ) {
			$friend_id    = isset( $product['friend_id'] ) ? absint( $product['friend_id'] ) : 0;
			$product_hash = isset( $product['product_hash'] ) ? sanitize_text_field( $product['product_hash'] ) : '';
			$product_url  = ! empty( $product['product_url'] ) ? FPL_URL_Helper::normalize_url( $product['product_url'] ) : '';

			if ( ! $friend_id || ! FPL_Security::is_product_hash( $product_hash ) || '' === $product_url ) {
				continue;
			}

			$key = $friend_id . '|' . $product_hash;
			if ( isset( $counted[ $key ] ) ) {
				continue;
			}

			$counted[ $key ] = true;

			if ( ! isset( $grouped[ $friend_id ] ) ) {
				$grouped[ $friend_id ] = array();
			}

			$grouped[ $friend_id ][] = array(
				'product_hash' => $product_hash,
				'product_url'  => $product_url,
			);
		}

		if ( ! empty( $grouped ) ) {
			FPL_Stats_Repository::record_displays_batch( $grouped );
		}
	}
}
