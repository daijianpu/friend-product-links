<?php
/**
 * P2P aggregate stats exchange.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Stats_Exchange {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register aggregate stats receive endpoint.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'friend-product-links/v1',
			'/stats/(?P<token>[A-Za-z0-9_-]{32,128})',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive_stats' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Receive aggregate stats from a friend site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive_stats( WP_REST_Request $request ) {
		if ( 'yes' !== get_option( FPL_Repository::OPTION_RECEIVE_STATS, 'yes' ) ) {
			return new WP_Error( 'fpl_stats_disabled', __( 'Stats receiving is disabled.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		$token = sanitize_text_field( $request['token'] );
		$share = FPL_CPT::get_share_link_by_token( $token );
		if ( ! $share || '1' !== get_post_meta( $share->ID, FPL_Repository::META_ENABLED, true ) ) {
			return new WP_Error( 'fpl_not_found', __( 'Friend product link not found.', 'friend-product-links' ), array( 'status' => 404 ) );
		}

		$raw = $request->get_body();
		if ( strlen( $raw ) > 32768 ) {
			return new WP_Error( 'fpl_payload_too_large', __( 'Stats payload is too large.', 'friend-product-links' ), array( 'status' => 413 ) );
		}

		$stats_key = sanitize_text_field( get_post_meta( $share->ID, FPL_Repository::META_SHARE_STATS_KEY, true ) );

		if ( ! FPL_Security::is_token( $stats_key ) ) {
			return new WP_Error(
				'fpl_stats_key_missing',
				__( 'Stats key is missing or invalid.', 'friend-product-links' ),
				array( 'status' => 403 )
			);
		}
		$signature = sanitize_text_field( $request->get_header( 'x-fpl-signature' ) );
		if ( ! $signature || 1 !== preg_match( '/^[a-f0-9]{64}$/i', $signature ) || ! hash_equals( hash_hmac( 'sha256', $raw, $stats_key ), $signature ) ) {
			return new WP_Error( 'fpl_bad_signature', __( 'Invalid stats signature.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'fpl_bad_payload', __( 'Invalid stats payload.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		$sender_site_url   = ! empty( $data['site_url'] ) ? FPL_URL_Helper::normalize_url( $data['site_url'] ) : '';
		$expected_site_url = esc_url_raw( get_post_meta( $share->ID, FPL_Repository::META_FRIEND_SITE_URL, true ) );
		$sender_valid   = FPL_URL_Helper::validate_identity_http_url( $sender_site_url );
		$expected_valid = FPL_URL_Helper::validate_identity_http_url( $expected_site_url );
		if ( ! $sender_site_url || ! $expected_site_url || is_wp_error( $sender_valid ) || is_wp_error( $expected_valid ) || ! FPL_URL_Helper::same_host( $expected_site_url, $sender_site_url ) ) {
			return new WP_Error( 'fpl_bad_sender_site', __( 'Stats sender site failed validation.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		$product_stats       = isset( $data['product_stats'] ) && is_array( $data['product_stats'] ) ? $data['product_stats'] : array();
		$allowed_hashes      = self::share_product_hashes( $share->ID );
		$clean_product_stats = array();

		// Clean incoming product_stats: only allowed hashes, clamp values.
		foreach ( $product_stats as $hash => $row ) {
			if ( count( $clean_product_stats ) >= FPL_CORE_PRODUCT_MAX ) {
				break;
			}

			$hash = sanitize_text_field( $hash );
			if ( ! in_array( $hash, $allowed_hashes, true ) || ! is_array( $row ) ) {
				continue;
			}

			$clean_product_stats[ $hash ] = array(
				'display' => self::clamp_stat_count( $row['display'] ?? 0 ),
				'click'   => self::clamp_stat_count( $row['click'] ?? 0 ),
			);
		}

		// Merge with existing stored product stats: only allowed hashes, clamp, use max.
		$stored_product_stats = get_post_meta( $share->ID, FPL_Repository::META_REMOTE_PRODUCT_STATS, true );
		if ( is_array( $stored_product_stats ) ) {
			foreach ( $stored_product_stats as $hash => $stored_row ) {
				$hash = sanitize_text_field( $hash );
				if ( ! in_array( $hash, $allowed_hashes, true ) || ! is_array( $stored_row ) ) {
					continue;
				}

				$stored_display = self::clamp_stat_count( $stored_row['display'] ?? 0 );
				$stored_click   = self::clamp_stat_count( $stored_row['click'] ?? 0 );

				if ( ! isset( $clean_product_stats[ $hash ] ) ) {
					$clean_product_stats[ $hash ] = array(
						'display' => $stored_display,
						'click'   => $stored_click,
					);
				} else {
					$clean_product_stats[ $hash ]['display'] = self::clamp_stat_count( max( $stored_display, $clean_product_stats[ $hash ]['display'] ) );
					$clean_product_stats[ $hash ]['click']   = self::clamp_stat_count( max( $stored_click, $clean_product_stats[ $hash ]['click'] ) );
				}
			}
		}

		$current_display = absint( get_post_meta( $share->ID, FPL_Repository::META_REMOTE_DISPLAY_COUNT, true ) );
		$current_click   = absint( get_post_meta( $share->ID, FPL_Repository::META_REMOTE_CLICK_COUNT, true ) );
		$new_display     = max( $current_display, isset( $data['display_count'] ) ? absint( $data['display_count'] ) : 0 );
		$new_click       = max( $current_click, isset( $data['click_count'] ) ? absint( $data['click_count'] ) : 0 );

		$new_display = self::clamp_stat_count( $new_display );
		$new_click   = self::clamp_stat_count( $new_click );

		update_post_meta( $share->ID, FPL_Repository::META_REMOTE_DISPLAY_COUNT, $new_display );
		update_post_meta( $share->ID, FPL_Repository::META_REMOTE_CLICK_COUNT, $new_click );

		update_post_meta( $share->ID, FPL_Repository::META_REMOTE_PRODUCT_STATS, $clean_product_stats );
		update_post_meta( $share->ID, FPL_Repository::META_REMOTE_LAST_RECEIVED_TIME, current_time( 'mysql' ) );
		update_post_meta( $share->ID, FPL_Repository::META_REMOTE_LAST_SENDER_SITE, $sender_site_url );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Send aggregate stats to one friend site.
	 *
	 * @param int $friend_id Friend feed ID.
	 * @return true|WP_Error
	 */
	public static function send_friend_stats( $friend_id ) {
		if ( 'yes' !== get_option( FPL_Repository::OPTION_SHARE_STATS, 'yes' ) ) {
			return true;
		}

		$friend_id = absint( $friend_id );

		// Self-check: friend must exist, be enabled, and have valid config.
		$friend_post = get_post( $friend_id );
		if ( ! $friend_post || FPL_CPT::FRIEND_FEED !== $friend_post->post_type || 'publish' !== $friend_post->post_status ) {
			return new WP_Error( 'fpl_invalid_friend', __( 'Friend feed post is invalid.', 'friend-product-links' ) );
		}

		if ( '1' !== get_post_meta( $friend_id, FPL_Repository::META_ENABLED, true ) ) {
			return true;
		}

		$endpoint  = FPL_URL_Helper::normalize_url( get_post_meta( $friend_id, FPL_Repository::META_STATS_ENDPOINT, true ) );
		$key       = sanitize_text_field( get_post_meta( $friend_id, FPL_Repository::META_FRIEND_STATS_KEY, true ) );

		// No endpoint or key configured: skip silently (not an error).
		if ( ! $endpoint && ! $key ) {
			return true;
		}

		// Endpoint must be a valid public http(s) URL.
		$endpoint_url_valid = FPL_URL_Helper::is_public_http_url( $endpoint );
		if ( is_wp_error( $endpoint_url_valid ) ) {
			self::record_stats_send_result( $friend_id, 'failed', __( 'Stats endpoint is not a valid public URL.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_bad_stats_endpoint', __( 'Stats endpoint is not a valid public URL.', 'friend-product-links' ) );
		}

		if ( ! FPL_Security::is_token( $key ) ) {
			self::record_stats_send_result( $friend_id, 'failed', __( 'Stats key is invalid.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_bad_stats_key', __( 'Stats key is invalid.', 'friend-product-links' ) );
		}

		// Endpoint must share host with friend site URL.
		$friend_site_url = FPL_Repository::get_friend_site_url( $friend_id );
		if ( $friend_site_url && ! FPL_URL_Helper::same_host( $friend_site_url, $endpoint ) ) {
			self::record_stats_send_result( $friend_id, 'failed', __( 'Stats endpoint failed same-host validation.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_bad_stats_endpoint', __( 'Stats endpoint failed same-host validation.', 'friend-product-links' ) );
		}

		$totals  = FPL_Stats_Repository::get_friend_totals( $friend_id );
		$payload = array(
			'site_url'      => home_url( '/' ),
			'sent_at'       => current_time( 'c' ),
			'display_count' => absint( $totals['displays'] ),
			'click_count'   => absint( $totals['clicks'] ),
			'product_stats' => FPL_Stats_Repository::get_friend_product_stats( $friend_id ),
		);

		$body     = wp_json_encode( $payload );
		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => 32768,
				'reject_unsafe_urls'  => true,
				'user-agent'          => 'Friend Product Links/' . FPL_VERSION . '; ' . home_url( '/' ),
				'headers'             => array(
					'Content-Type'    => 'application/json',
					'X-FPL-Signature' => hash_hmac( 'sha256', $body, $key ),
				),
				'body'                => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record_stats_send_result( $friend_id, 'failed', $response->get_error_message() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$error = sprintf( 'HTTP %d', $code );
			self::record_stats_send_result( $friend_id, 'failed', $error );
			return new WP_Error( 'fpl_stats_sync_failed', $error );
		}

		self::record_stats_send_result( $friend_id, 'success', '' );

		return true;
	}

	/**
	 * Ensure a share link has a stats key.
	 *
	 * @param int $share_id Share link ID.
	 * @return string
	 */
	public static function ensure_share_stats_key( $share_id ) {
		$key = sanitize_text_field( get_post_meta( $share_id, FPL_Repository::META_SHARE_STATS_KEY, true ) );
		if ( ! FPL_Security::is_token( $key ) ) {
			$key = FPL_Security::generate_token();
			update_post_meta( $share_id, FPL_Repository::META_SHARE_STATS_KEY, $key );
		}

		return $key;
	}

	/**
	 * Get allowed product hashes for a share link.
	 *
	 * @param int $share_id Share link ID.
	 * @return string[]
	 */
	private static function share_product_hashes( $share_id ) {
		$hashes = array();
		foreach ( FPL_Security::filter_valid_product_ids( FPL_Repository::get_share_link_products( $share_id ), FPL_CORE_PRODUCT_MAX ) as $product_id ) {
			$url = get_permalink( $product_id );
			if ( $url ) {
				$hashes[] = FPL_Security::product_hash( $url );
			}
		}

		return $hashes;
	}

	/**
	 * Clamp a stat count to a safe maximum.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private static function clamp_stat_count( $value ) {
		return min( absint( $value ), 999999999 );
	}

	/**
	 * Record stats send result.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $status Status.
	 * @param string $error Error text.
	 * @return void
	 */
	private static function record_stats_send_result( $friend_id, $status, $error ) {
		update_post_meta( $friend_id, FPL_Repository::META_LAST_STATS_SENT_TIME, current_time( 'mysql' ) );
		update_post_meta( $friend_id, FPL_Repository::META_LAST_STATS_SENT_STATUS, sanitize_text_field( $status ) );
		update_post_meta( $friend_id, FPL_Repository::META_LAST_STATS_SENT_ERROR, sanitize_text_field( $error ) );
	}
}
