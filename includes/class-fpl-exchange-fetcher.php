<?php
/**
 * Fetch and validate remote exchange offers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Fetcher {
	const MAX_BODY_BYTES = 524288;

	/**
	 * Fetch and validate a remote exchange offer.
	 *
	 * @param string $offer_url Remote exchange offer URL.
	 * @return array|WP_Error Decoded and validated payload.
	 */
	public static function fetch_remote_offer( $offer_url ) {
		$url_valid = FPL_URL_Helper::is_public_http_url( $offer_url );
		if ( is_wp_error( $url_valid ) ) {
			return $url_valid;
		}

		$response = wp_safe_remote_get(
			$offer_url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_BODY_BYTES,
				'reject_unsafe_urls'  => true,
				'user-agent'          => 'Friend Product Links/' . FPL_VERSION . '; ' . home_url( '/' ),
				'headers'             => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'fpl_http_error', sprintf( 'HTTP %d', $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return new WP_Error( 'fpl_too_large', __( 'Response was too large.', 'friend-product-links' ) );
		}

		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'fpl_bad_json', __( 'Invalid JSON response.', 'friend-product-links' ) );
		}

		return self::validate_remote_offer_structure( $data, $offer_url );
	}

	/**
	 * Validate the structure of a remote exchange offer payload.
	 *
	 * @param array  $data      Decoded payload.
	 * @param string $offer_url The URL used to fetch.
	 * @return array|WP_Error
	 */
	public static function validate_remote_offer_structure( $data, $offer_url ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'fpl_bad_payload', __( 'Exchange offer payload is invalid.', 'friend-product-links' ) );
		}

		if ( empty( $data['type'] ) || 'exchange_offer' !== $data['type'] ) {
			return new WP_Error( 'fpl_wrong_type', __( 'Not a valid exchange offer.', 'friend-product-links' ) );
		}

		// exchange_mode must be truthy.
		if ( empty( $data['exchange_mode'] ) ) {
			return new WP_Error( 'fpl_exchange_mode_disabled', __( 'Remote exchange has not enabled exchange mode.', 'friend-product-links' ) );
		}

		// catalog_status must be 'ready'.
		if ( empty( $data['catalog_status'] ) || 'ready' !== $data['catalog_status'] ) {
			return new WP_Error( 'fpl_catalog_not_ready', __( 'Remote exchange catalog is not ready.', 'friend-product-links' ) );
		}

		// catalog_url must be present and valid.
		$catalog_url = ! empty( $data['catalog_url'] ) ? esc_url_raw( $data['catalog_url'] ) : '';
		if ( ! $catalog_url || is_wp_error( FPL_URL_Helper::is_public_http_url( $catalog_url ) ) ) {
			return new WP_Error( 'fpl_invalid_catalog_url', __( 'Remote exchange catalog URL is invalid.', 'friend-product-links' ) );
		}

		$site_name        = ! empty( $data['site_name'] ) ? sanitize_text_field( $data['site_name'] ) : '';
		$site_url         = ! empty( $data['site_url'] ) ? esc_url_raw( $data['site_url'] ) : '';
		$payload_offer_url = ! empty( $data['offer_url'] ) ? esc_url_raw( $data['offer_url'] ) : '';
		$remote_offer_url  = $payload_offer_url ? $payload_offer_url : $offer_url;

		$offer_url_valid = FPL_URL_Helper::is_public_http_url( $remote_offer_url );
		if ( is_wp_error( $offer_url_valid ) ) {
			return new WP_Error( 'fpl_invalid_offer_url', __( 'Remote exchange offer URL is invalid.', 'friend-product-links' ) );
		}

		if ( ! $site_name || ! $site_url ) {
			return new WP_Error( 'fpl_missing_site_info', __( 'Exchange offer missing site name or URL.', 'friend-product-links' ) );
		}

		if ( is_wp_error( FPL_URL_Helper::is_public_http_url( $site_url ) ) ) {
			return new WP_Error( 'fpl_invalid_site_url', __( 'Exchange offer site URL is invalid.', 'friend-product-links' ) );
		}

		$request_endpoint  = ! empty( $data['request_endpoint'] ) ? esc_url_raw( $data['request_endpoint'] ) : '';
		$callback_endpoint = ! empty( $data['callback_endpoint'] ) ? esc_url_raw( $data['callback_endpoint'] ) : '';

		if ( ! $request_endpoint || ! $callback_endpoint ) {
			return new WP_Error( 'fpl_missing_endpoints', __( 'Exchange offer missing request or callback endpoint.', 'friend-product-links' ) );
		}

		foreach ( array( $request_endpoint, $callback_endpoint ) as $endpoint ) {
			$valid = FPL_URL_Helper::is_public_http_url( $endpoint );
			if ( is_wp_error( $valid ) ) {
				return new WP_Error( 'fpl_invalid_endpoint', __( 'Exchange offer endpoint failed URL validation.', 'friend-product-links' ) );
			}
		}

		// All URLs must share the same host.
		$hosts = array(
			FPL_URL_Helper::get_host( $offer_url ),
			FPL_URL_Helper::get_host( $remote_offer_url ),
			FPL_URL_Helper::get_host( $site_url ),
			FPL_URL_Helper::get_host( $catalog_url ),
		);
		foreach ( array( $request_endpoint, $callback_endpoint ) as $endpoint ) {
			$hosts[] = FPL_URL_Helper::get_host( $endpoint );
		}
		$hosts = array_unique( array_filter( $hosts ) );
		if ( count( $hosts ) > 1 ) {
			return new WP_Error( 'fpl_host_mismatch', __( 'Exchange offer URLs must all be on the same host.', 'friend-product-links' ) );
		}

		$products = isset( $data['products'] ) && is_array( $data['products'] ) ? $data['products'] : array();
		if ( count( $products ) < FPL_EXCHANGE_MIN_PRODUCTS ) {
			return new WP_Error(
				'fpl_insufficient_products',
				sprintf(
					/* translators: %d: minimum product count */
					__( 'Remote exchange offer has fewer than %d products.', 'friend-product-links' ),
					FPL_EXCHANGE_MIN_PRODUCTS
				)
			);
		}

		if ( count( $products ) > FPL_EXCHANGE_MAX_PRODUCTS ) {
			return new WP_Error(
				'fpl_too_many_products',
				sprintf(
					/* translators: %d: maximum product count */
					__( 'Remote exchange offer has more than %d products.', 'friend-product-links' ),
					FPL_EXCHANGE_MAX_PRODUCTS
				)
			);
		}

		$clean_products = array();
		$feed_host      = reset( $hosts ) ?: '';
		foreach ( array_slice( $products, 0, FPL_EXCHANGE_MAX_PRODUCTS ) as $product ) {
			$clean = FPL_Security::sanitize_remote_product( $product, $feed_host );
			if ( $clean ) {
				$clean_products[] = $clean;
			}
		}

		if ( count( $clean_products ) < FPL_EXCHANGE_MIN_PRODUCTS ) {
			return new WP_Error( 'fpl_too_few_clean_products', __( 'Remote exchange offer has too few valid products.', 'friend-product-links' ) );
		}

		return array(
			'site_name'         => $site_name,
			'site_url'          => $site_url,
			'offer_url'         => FPL_Exchange_Repository::normalize_offer_url( $remote_offer_url ),
			'request_endpoint'  => $request_endpoint,
			'callback_endpoint' => $callback_endpoint,
			'product_count'     => count( $clean_products ),
			'display_count'     => FPL_EXCHANGE_DISPLAY_PRODUCTS,
			'catalog_url'       => $catalog_url,
			'catalog_status'    => 'ready',
			'exchange_mode'     => true,
			'products'          => $clean_products,
		);
	}

	/**
	 * Sync an exchange partner's cached products from their remote offer.
	 *
	 * @param int $partner_id Partner post ID.
	 * @return true|WP_Error
	 */
	public static function sync_partner( $partner_id ) {
		$partner_id = absint( $partner_id );
		$status     = get_post_meta( $partner_id, FPL_Exchange_Repository::META_PARTNER_STATUS, true );

		// paused_safety must never auto-recover.
		if ( 'paused_safety' === $status ) {
			return true;
		}

		// Only sync active or degraded_missing_catalog partners.
		if ( ! in_array( $status, array( 'active', 'degraded_missing_catalog', 'degraded_remote_offer' ), true ) ) {
			return true;
		}

		// Check catalog readiness.
		if ( ! FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled() || ! FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready() ) {
			FPL_Exchange_Repository::update_partner(
				$partner_id,
				array(
					FPL_Exchange_Repository::META_PARTNER_STATUS       => 'degraded_missing_catalog',
					FPL_Exchange_Repository::META_PARTNER_LAST_ERROR   => __( 'Local Exchange Catalog page is missing or invalid.', 'friend-product-links' ),
					FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT => current_time( 'mysql' ),
				)
			);
			return true;
		}

		$offer_url = get_post_meta( $partner_id, FPL_Exchange_Repository::META_PARTNER_OFFER_URL, true );
		if ( ! $offer_url ) {
			FPL_Exchange_Repository::update_partner(
				$partner_id,
				array(
					FPL_Exchange_Repository::META_PARTNER_STATUS       => 'degraded_remote_offer',
					FPL_Exchange_Repository::META_PARTNER_LAST_ERROR   => __( 'Partner has no offer URL.', 'friend-product-links' ),
					FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT => current_time( 'mysql' ),
				)
			);

			return true;
		}

		$remote = self::fetch_remote_offer( $offer_url );
		if ( is_wp_error( $remote ) ) {
			// fetch_remote_offer() also returns WP_Error for remote offers with
			// too few valid products, host mismatch, invalid JSON, etc.
			// Mark the partner degraded so stale cached products stop rendering
			// on the public Exchange Catalog.
			FPL_Exchange_Repository::update_partner(
				$partner_id,
				array(
					FPL_Exchange_Repository::META_PARTNER_STATUS       => 'degraded_remote_offer',
					FPL_Exchange_Repository::META_PARTNER_LAST_ERROR   => $remote->get_error_message(),
					FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT => current_time( 'mysql' ),
				)
			);

			return true;
		}

		// Keep old cache if new products are too few; just mark degraded.
		// fetch_remote_offer() also returns WP_Error for remote offers with too
		// few valid products (the structural validation check path), so this
		// branch is a defensive fallback for edge cases or future changes.
		if ( count( $remote['products'] ) < FPL_EXCHANGE_MIN_PRODUCTS ) {
			FPL_Exchange_Repository::update_partner(
				$partner_id,
				array(
					FPL_Exchange_Repository::META_PARTNER_STATUS       => 'degraded_remote_offer',
					FPL_Exchange_Repository::META_PARTNER_LAST_ERROR   =>
						sprintf(
							/* translators: %d: minimum product count */
							__( 'Remote offer has fewer than %d valid products.', 'friend-product-links' ),
							FPL_EXCHANGE_MIN_PRODUCTS
						),
					FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT => current_time( 'mysql' ),
				)
			);
			return true;
		}

		FPL_Exchange_Repository::update_partner(
			$partner_id,
			array(
				FPL_Exchange_Repository::META_PARTNER_SITE_NAME       => $remote['site_name'],
				FPL_Exchange_Repository::META_PARTNER_SITE_URL        => $remote['site_url'],
				FPL_Exchange_Repository::META_PARTNER_OFFER_URL       => $remote['offer_url'],
				FPL_Exchange_Repository::META_PARTNER_STATUS          => 'active',
				FPL_Exchange_Repository::META_PARTNER_CACHED_PRODUCTS => $remote['products'],
				FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT    => current_time( 'mysql' ),
				FPL_Exchange_Repository::META_PARTNER_LAST_SUCCESS_AT => current_time( 'mysql' ),
				FPL_Exchange_Repository::META_PARTNER_LAST_ERROR      => '',
			)
		);

		return true;
	}

	/**
	 * Sync all active exchange partners.
	 *
	 * @return void
	 */
	public static function sync_all_partners() {
		$partners = FPL_CPT::get_exchange_partners( false, -1 );
		foreach ( $partners as $partner ) {
			self::sync_partner( $partner->ID );
		}
	}
}
