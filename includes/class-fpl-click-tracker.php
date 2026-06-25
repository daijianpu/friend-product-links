<?php
/**
 * Local click redirect and tracking.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Click_Tracker {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_handle_click' ), 1 );
	}

	/**
	 * Create signed local click URL.
	 *
	 * @param int    $friend_id Friend feed ID.
	 * @param string $product_hash Product hash.
	 * @return string
	 */
	public static function click_url( $friend_id, $product_hash ) {
		$friend_id    = absint( $friend_id );
		$product_hash = sanitize_text_field( $product_hash );
		$sig          = FPL_Security::sign_value( $friend_id . '|' . $product_hash );

		return add_query_arg(
			array(
				'fpl_click' => '1',
				'friend_id' => $friend_id,
				'product'   => $product_hash,
				'sig'       => $sig,
			),
			home_url( '/' )
		);
	}

	/**
	 * Handle local signed click redirect.
	 *
	 * @return void
	 */
	public function maybe_handle_click() {
		if ( empty( $_GET['fpl_click'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$count_click  = isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
		$friend_id    = isset( $_GET['friend_id'] ) ? absint( $_GET['friend_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_hash = isset( $_GET['product'] ) ? sanitize_text_field( wp_unslash( $_GET['product'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sig          = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $friend_id || ! FPL_Security::is_product_hash( $product_hash ) || ! FPL_Security::verify_value_signature( $friend_id . '|' . $product_hash, $sig ) ) {
			$this->not_found();
		}

		$post = get_post( $friend_id );
		if ( ! $post || FPL_CPT::FRIEND_FEED !== $post->post_type || '1' !== get_post_meta( $friend_id, FPL_Repository::META_ENABLED, true ) ) {
			$this->not_found();
		}

		if ( ! FPL_Repository::friend_has_valid_cache( $friend_id ) ) {
			$this->not_found();
		}

		$product_url = self::find_cached_product_url( $friend_id, $product_hash );
		if ( ! $product_url ) {
			$this->not_found();
		}

		// 5-second transient throttle: same friend+product gets only one click counted.
		$lock_key = 'fpl_click_lock_' . md5( $friend_id . '|' . $product_hash );

		if ( $count_click && 'yes' === get_option( FPL_Repository::OPTION_ENABLE_STATS, 'yes' ) && ! get_transient( $lock_key ) ) {
			set_transient( $lock_key, 1, 5 );
			FPL_Stats_Repository::record_click( $friend_id, $product_hash, $product_url );
		}

		self::safe_friend_redirect( $product_url );
		exit;
	}

	/**
	 * Find cached product URL for a friend hash.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $product_hash Product hash.
	 * @return string
	 */
	private static function find_cached_product_url( $friend_id, $product_hash ) {
		$products        = FPL_Repository::get_cached_products( $friend_id );
		$friend_site_url = FPL_Repository::get_friend_site_url( $friend_id );

		foreach ( $products as $product ) {
			$hash = ! empty( $product['product_hash'] ) ? sanitize_text_field( $product['product_hash'] ) : FPL_Security::product_hash( $product['product_url'] ?? '' );
			if ( ! FPL_Security::is_product_hash( $hash ) ) {
				$hash = FPL_Security::product_hash( $product['product_url'] ?? '' );
			}

			if ( hash_equals( $hash, $product_hash ) && ! empty( $product['product_url'] ) ) {
				$product_url = FPL_URL_Helper::normalize_url( $product['product_url'] );
				if ( is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) || ( $friend_site_url && ! FPL_URL_Helper::same_host( $friend_site_url, $product_url ) ) ) {
					return '';
				}

				return $product_url;
			}
		}

		return '';
	}

	/**
	 * Redirect to a validated friend product URL.
	 *
	 * @param string $product_url Product URL.
	 * @return void
	 */
	private static function safe_friend_redirect( $product_url ) {
		if ( is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		$host = wp_parse_url( $product_url, PHP_URL_HOST );
		if ( $host ) {
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $host ) {
					$hosts[] = $host;
					return array_values( array_unique( $hosts ) );
				}
			);
		}

		if ( ! wp_safe_redirect( $product_url, 302 ) ) {
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Return 404 and stop.
	 *
	 * @return void
	 */
	private function not_found() {
		status_header( 404 );
		nocache_headers();
		exit;
	}
}
