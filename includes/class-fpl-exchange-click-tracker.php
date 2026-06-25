<?php
/**
 * Exchange catalog click redirect and tracking.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Click_Tracker {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_handle_click' ), 1 );
	}

	/**
	 * Create signed local click URL for exchange catalog products.
	 *
	 * @param int    $partner_id  Exchange partner ID.
	 * @param string $product_hash Product hash.
	 * @return string
	 */
	public static function click_url( $partner_id, $product_hash ) {
		$partner_id   = absint( $partner_id );
		$product_hash = sanitize_text_field( $product_hash );
		$sig          = FPL_Security::sign_value( 'exchange_' . $partner_id . '|' . $product_hash );

		return add_query_arg(
			array(
				'fpl_exchange_click' => '1',
				'partner_id'         => $partner_id,
				'product'            => $product_hash,
				'sig'                => $sig,
			),
			home_url( '/' )
		);
	}

	/**
	 * Handle signed exchange click redirect.
	 *
	 * @return void
	 */
	public function maybe_handle_click() {
		if ( empty( $_GET['fpl_exchange_click'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled() || ! FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready() ) {
			$this->not_found();
		}

		$partner_id   = isset( $_GET['partner_id'] ) ? absint( $_GET['partner_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_hash = isset( $_GET['product'] ) ? sanitize_text_field( wp_unslash( $_GET['product'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sig          = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $partner_id || ! FPL_Security::is_product_hash( $product_hash ) || ! FPL_Security::verify_value_signature( 'exchange_' . $partner_id . '|' . $product_hash, $sig ) ) {
			$this->not_found();
		}

		$post = get_post( $partner_id );
		if ( ! $post || FPL_CPT::EXCHANGE_PARTNER !== $post->post_type ) {
			$this->not_found();
		}

		$status = get_post_meta( $partner_id, FPL_Exchange_Repository::META_PARTNER_STATUS, true );
		if ( 'active' !== $status ) {
			$this->not_found();
		}

		$product_url = self::find_cached_product_url( $partner_id, $product_hash );
		if ( ! $product_url ) {
			$this->not_found();
		}

		// 5-second transient throttle: same partner+product gets only one click counted.
		$lock_key = 'fpl_ex_click_lock_' . md5( $partner_id . '|' . $product_hash );

		$count_click = isset( $_SERVER['REQUEST_METHOD'] )
			&& 'GET' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		if ( $count_click && 'yes' === get_option( FPL_Repository::OPTION_ENABLE_STATS, 'yes' ) && ! get_transient( $lock_key ) ) {
			set_transient( $lock_key, 1, 5 );

			// Record click on partner meta.
			$clicks = absint( get_post_meta( $partner_id, FPL_Exchange_Repository::META_PARTNER_CLICKS_SENT, true ) );
			update_post_meta( $partner_id, FPL_Exchange_Repository::META_PARTNER_CLICKS_SENT, $clicks + 1 );
		}

		self::safe_redirect( $product_url );
		exit;
	}

	/**
	 * Find cached product URL for a partner hash.
	 *
	 * @param int    $partner_id   Partner ID.
	 * @param string $product_hash Product hash.
	 * @return string
	 */
	private static function find_cached_product_url( $partner_id, $product_hash ) {
		$products        = FPL_Exchange_Repository::get_partner_cached_products( $partner_id );
		$partner_site_url = get_post_meta( $partner_id, FPL_Exchange_Repository::META_PARTNER_SITE_URL, true );

		foreach ( $products as $product ) {
			$hash = ! empty( $product['product_hash'] ) ? sanitize_text_field( $product['product_hash'] ) : '';
			if ( ! $hash ) {
				$hash = FPL_Security::product_hash( $product['product_url'] ?? '' );
			}

			if ( hash_equals( $hash, $product_hash ) && ! empty( $product['product_url'] ) ) {
				$product_url = FPL_URL_Helper::normalize_url( $product['product_url'] );
				if ( is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) || ( $partner_site_url && ! FPL_URL_Helper::same_host( $partner_site_url, $product_url ) ) ) {
					return '';
				}

				return $product_url;
			}
		}

		return '';
	}

	/**
	 * Safe redirect to a validated partner product URL.
	 *
	 * @param string $product_url Product URL.
	 * @return void
	 */
	private static function safe_redirect( $product_url ) {
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