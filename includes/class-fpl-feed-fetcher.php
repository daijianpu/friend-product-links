<?php
/**
 * Remote feed fetcher and local cache writer.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Feed_Fetcher {
	const MAX_BODY_BYTES = 524288;

	/**
	 * Sync one friend feed.
	 *
	 * @param int $friend_id Friend feed post ID.
	 * @return true|WP_Error
	 */
	public static function sync_friend_feed( $friend_id ) {
		$friend_id = absint( $friend_id );
		$post      = get_post( $friend_id );

		if ( ! $post || FPL_CPT::FRIEND_FEED !== $post->post_type ) {
			return new WP_Error( 'fpl_missing_friend', __( 'Friend feed not found.', 'friend-product-links' ) );
		}

		$feed_url        = FPL_Repository::get_friend_feed_url( $friend_id );
		$friend_site_url = FPL_Repository::get_friend_site_url( $friend_id );
		$boundary        = FPL_URL_Helper::validate_friend_feed_boundary( $friend_site_url, $feed_url );
		if ( is_wp_error( $boundary ) ) {
			self::record_failure( $friend_id, $boundary->get_error_message() );
			return $boundary;
		}

		$response = wp_safe_remote_get(
			$feed_url,
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
			self::record_failure( $friend_id, $response->get_error_message() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $code || 410 === $code ) {
			FPL_Repository::invalidate_friend_cache( $friend_id );
			self::record_failure( $friend_id, __( 'Feed was disabled or not found. The friend display was paused.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_feed_disabled', __( 'Feed disabled or not found.', 'friend-product-links' ) );
		}

		if ( $code < 200 || $code >= 300 ) {
			self::record_failure( $friend_id, sprintf( 'HTTP %d', $code ) );
			return new WP_Error( 'fpl_http_error', sprintf( 'HTTP %d', $code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			self::record_failure( $friend_id, __( 'Feed response was too large.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_too_large', __( 'Feed response was too large.', 'friend-product-links' ) );
		}

		$content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( $content_type && false === strpos( $content_type, 'application/json' ) && false === strpos( $content_type, '+json' ) ) {
			self::record_failure( $friend_id, __( 'Feed response was not JSON.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_bad_content_type', __( 'Feed response was not JSON.', 'friend-product-links' ) );
		}

		$data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			self::record_failure( $friend_id, json_last_error_msg() );
			return new WP_Error( 'fpl_bad_json', __( 'Invalid friend product feed JSON.', 'friend-product-links' ) );
		}

		$boundary = FPL_URL_Helper::validate_friend_feed_boundary( $friend_site_url, $feed_url, $data );
		if ( is_wp_error( $boundary ) ) {
			self::record_failure( $friend_id, $boundary->get_error_message() );
			return $boundary;
		}

		$feed_host      = FPL_URL_Helper::get_host( $feed_url );
		$warning_parts  = array();
		$raw_count      = is_array( $data['products'] ) ? count( $data['products'] ) : 0;
		$feed_site_url  = ! empty( $data['site_url'] ) ? FPL_URL_Helper::normalize_url( $data['site_url'] ) : '';
		$stored_site_url = FPL_URL_Helper::normalize_url( $friend_site_url );

		if ( $feed_site_url && $stored_site_url && $feed_site_url !== $stored_site_url ) {
			$warning_parts[] = __( 'The friend feed site URL differs from the saved friend website URL, but the host boundary is valid.', 'friend-product-links' );
		}

		$products = array();
		foreach ( $data['products'] as $product ) {
			$clean = FPL_Security::sanitize_remote_product( $product, $feed_host );
			if ( $clean ) {
				$clean['source_name'] = sanitize_text_field( get_post_meta( $friend_id, FPL_Repository::META_FRIEND_NAME, true ) );
				$clean['source_url']  = $friend_site_url;
				$clean['cached_at']   = current_time( 'mysql' );
				$products[]           = $clean;
			}

			if ( count( $products ) >= FPL_CORE_PRODUCT_MAX ) {
				break;
			}
		}
		$dropped_count = max( 0, $raw_count - count( $products ) );

		if ( empty( $products ) ) {
			self::record_failure( $friend_id, __( 'No usable products were found in the feed.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_no_products', __( 'No usable products were found in the feed.', 'friend-product-links' ) );
		}

		if ( $dropped_count > 0 ) {
			$warning_parts[] = sprintf(
				/* translators: %1$d: number of ignored remote products, %2$d: max products. */
				_n( '%1$d feed product was ignored because it was invalid or above the %2$d product limit.', '%1$d feed products were ignored because they were invalid or above the %2$d product limit.', $dropped_count, 'friend-product-links' ),
				$dropped_count,
				FPL_CORE_PRODUCT_MAX
			);
		}

		FPL_Repository::save_cached_products( $friend_id, $products );
		FPL_Repository::save_cache_identity( $friend_id, $friend_site_url, $feed_url );
		update_post_meta( $friend_id, FPL_Repository::META_LAST_SYNCED_AT, current_time( 'mysql' ) );
		update_post_meta( $friend_id, FPL_Repository::META_SYNC_STATUS, 'success' );
		FPL_Repository::clear_sync_error( $friend_id );
		delete_post_meta( $friend_id, FPL_Repository::META_HEALTH_WARNING );
		update_post_meta( $friend_id, FPL_Repository::META_SITE_MISMATCH_WARNING, implode( ' ', $warning_parts ) );
		$stats_endpoint = '';
		$stats_key      = ! empty( $data['stats_key'] ) ? sanitize_text_field( $data['stats_key'] ) : '';
		if ( ! FPL_Security::is_token( $stats_key ) ) {
			$stats_key = '';
		}

		if ( ! empty( $data['stats_endpoint'] ) && $stats_key ) {
			$maybe_endpoint = FPL_URL_Helper::normalize_url( $data['stats_endpoint'] );
			if ( ! is_wp_error( FPL_URL_Helper::is_public_http_url( $maybe_endpoint ) ) && FPL_URL_Helper::same_host( $feed_url, $maybe_endpoint ) ) {
				$stats_endpoint = $maybe_endpoint;
			}
		}

		update_post_meta( $friend_id, FPL_Repository::META_STATS_ENDPOINT, $stats_endpoint );
		update_post_meta( $friend_id, FPL_Repository::META_FRIEND_STATS_KEY, $stats_endpoint ? $stats_key : '' );
		update_post_meta( $friend_id, FPL_Repository::META_FAIL_COUNT, 0 );

		return true;
	}

	/**
	 * Record sync failure.
	 *
	 * @param int    $friend_id Friend post ID.
	 * @param string $message Error message.
	 * @return void
	 */
	private static function record_failure( $friend_id, $message ) {
		$fail_count = absint( get_post_meta( $friend_id, FPL_Repository::META_FAIL_COUNT, true ) ) + 1;

		update_post_meta( $friend_id, FPL_Repository::META_LAST_SYNCED_AT, current_time( 'mysql' ) );
		FPL_Repository::update_sync_error( $friend_id, $message );
		update_post_meta( $friend_id, FPL_Repository::META_FAIL_COUNT, $fail_count );

		if ( $fail_count >= 5 ) {
			update_post_meta( $friend_id, FPL_Repository::META_HEALTH_WARNING, '1' );
		}
	}
}
