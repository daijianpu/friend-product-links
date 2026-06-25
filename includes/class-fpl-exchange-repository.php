<?php
/**
 * Exchange data access (option-based offer storage + request/partner CPT access).
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Repository {
	const OPTION_ENABLED       = 'fpl_exchange_offer_enabled';
	const OPTION_TOKEN         = 'fpl_exchange_offer_token';
	const OPTION_PRODUCT_IDS   = 'fpl_exchange_offer_product_ids';
	const OPTION_UPDATED_AT    = 'fpl_exchange_offer_updated_at';

	const META_DIRECTION            = '_fpl_exchange_direction';
	const META_REQUEST_ID           = '_fpl_exchange_request_id';
	const META_STATUS               = '_fpl_exchange_status';
	const META_REMOTE_SITE_NAME     = '_fpl_exchange_remote_site_name';
	const META_REMOTE_SITE_URL      = '_fpl_exchange_remote_site_url';
	const META_REMOTE_OFFER_URL     = '_fpl_exchange_remote_offer_url';
	const META_REMOTE_CALLBACK_URL  = '_fpl_exchange_remote_callback_url';
	const META_REMOTE_REQUEST_ENDPOINT = '_fpl_exchange_remote_request_endpoint';
	const META_CALLBACK_TOKEN       = '_fpl_exchange_callback_token';
	const META_CREATED_AT           = '_fpl_exchange_created_at';
	const META_UPDATED_AT           = '_fpl_exchange_updated_at';
	const META_LAST_ERROR           = '_fpl_exchange_last_error';
	const META_REMOTE_PRODUCT_COUNT = '_fpl_exchange_remote_product_count';

	/**
	 * Normalize remote offer URLs before storage or exact comparisons.
	 *
	 * @param string $offer_url Offer URL.
	 * @return string
	 */
	public static function normalize_offer_url( $offer_url ) {
		return FPL_URL_Helper::normalize_url( $offer_url );
	}

	// --- Offer options ---

	/**
	 * Whether the local exchange offer is enabled.
	 *
	 * @return bool
	 */
	public static function is_offer_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Enable/disable the local exchange offer.
	 *
	 * @param bool $enabled Whether enabled.
	 * @return void
	 */
	public static function set_offer_enabled( $enabled ) {
		update_option( self::OPTION_ENABLED, $enabled ? 'yes' : 'no', false );
	}

	/**
	 * Get the exchange offer token.
	 *
	 * @return string
	 */
	public static function get_offer_token() {
		$token = sanitize_text_field( get_option( self::OPTION_TOKEN, '' ) );
		if ( ! FPL_Security::is_token( $token ) ) {
			$token = FPL_Security::generate_token();
			update_option( self::OPTION_TOKEN, $token, false );
		}

		return $token;
	}

	/**
	 * Get the selected exchange offer product IDs.
	 *
	 * @return int[]
	 */
	public static function get_offer_product_ids() {
		$ids = get_option( self::OPTION_PRODUCT_IDS, array() );

		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Save the selected exchange offer product IDs.
	 *
	 * @param int[] $ids Product IDs.
	 * @return void
	 */
	public static function save_offer_product_ids( array $ids ) {
		update_option( self::OPTION_PRODUCT_IDS, array_values( array_filter( array_map( 'absint', $ids ) ) ), false );
	}

	/**
	 * Get the exchange offer updated timestamp.
	 *
	 * @return string
	 */
	public static function get_offer_updated_at() {
		return sanitize_text_field( get_option( self::OPTION_UPDATED_AT, '' ) );
	}

	/**
	 * Touch the offer updated timestamp.
	 *
	 * @return void
	 */
	public static function touch_offer_updated_at() {
		update_option( self::OPTION_UPDATED_AT, current_time( 'mysql' ), false );
	}

	/**
	 * Build the exchange offer URL for this site.
	 *
	 * @return string
	 */
	public static function get_offer_url() {
		return rest_url( 'friend-product-links/v1/exchange/offer/' . rawurlencode( self::get_offer_token() ) );
	}

	/**
	 * Validate that the local offer has enough valid products.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_local_offer() {
		if ( ! self::is_offer_enabled() ) {
			return new WP_Error( 'fpl_offer_disabled', __( 'Your exchange offer is not enabled.', 'friend-product-links' ) );
		}

		if ( ! FPL_Plugin::is_woocommerce_active() ) {
			return new WP_Error( 'fpl_woocommerce_required', __( 'WooCommerce is required.', 'friend-product-links' ) );
		}

		$valid = FPL_Security::filter_valid_product_ids( self::get_offer_product_ids(), 0 );
		$count = count( $valid );

		if ( $count < FPL_EXCHANGE_MIN_PRODUCTS ) {
			return new WP_Error(
				'fpl_offer_insufficient_products',
				sprintf(
					/* translators: %d: minimum product count */
					__( 'Your exchange offer needs at least %d public WooCommerce products.', 'friend-product-links' ),
					FPL_EXCHANGE_MIN_PRODUCTS
				)
			);
		}

		if ( $count > FPL_EXCHANGE_MAX_PRODUCTS ) {
			return new WP_Error( 'fpl_offer_too_many_products', __( 'Your exchange offer has too many products.', 'friend-product-links' ) );
		}

		return true;
	}

	// --- Exchange Request CRUD ---

	/**
	 * Create an exchange request post.
	 *
	 * @param array $args {
	 *     @type string $direction      incoming|outgoing.
	 *     @type string $request_id     Unique request ID.
	 *     @type string $status         pending|approved|rejected|active|failed.
	 *     @type string $remote_site_name  Remote site name.
	 *     @type string $remote_site_url   Remote site URL.
	 *     @type string $remote_offer_url  Remote offer URL.
	 *     @type string $remote_request_endpoint Remote request endpoint.
	 *     @type string $remote_callback_url   Remote callback URL.
	 *     @type string $callback_token      Token for callback verification.
	 *     @type int    $remote_product_count Remote product count.
	 * }
	 * @return int|WP_Error
	 */
	public static function create_exchange_request( array $args ) {
		$title = sanitize_text_field( $args['request_id'] ?? uniqid( 'req_', true ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => FPL_CPT::EXCHANGE_REQUEST,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error( 'fpl_request_create_failed', __( 'Failed to create exchange request.', 'friend-product-links' ) );
		}

		$meta = array(
			self::META_DIRECTION            => sanitize_text_field( $args['direction'] ?? 'outgoing' ),
			self::META_REQUEST_ID           => sanitize_text_field( $args['request_id'] ?? $title ),
			self::META_STATUS               => sanitize_text_field( $args['status'] ?? 'pending' ),
			self::META_REMOTE_SITE_NAME     => sanitize_text_field( $args['remote_site_name'] ?? '' ),
			self::META_REMOTE_SITE_URL      => esc_url_raw( $args['remote_site_url'] ?? '' ),
			self::META_REMOTE_OFFER_URL     => self::normalize_offer_url( $args['remote_offer_url'] ?? '' ),
			self::META_REMOTE_REQUEST_ENDPOINT => esc_url_raw( $args['remote_request_endpoint'] ?? '' ),
			self::META_REMOTE_CALLBACK_URL  => esc_url_raw( $args['remote_callback_url'] ?? '' ),
			self::META_CALLBACK_TOKEN       => sanitize_text_field( $args['callback_token'] ?? '' ),
			self::META_REMOTE_PRODUCT_COUNT => absint( $args['remote_product_count'] ?? 0 ),
			self::META_CREATED_AT           => current_time( 'mysql' ),
			self::META_UPDATED_AT           => current_time( 'mysql' ),
			self::META_LAST_ERROR           => sanitize_text_field( $args['last_error'] ?? '' ),
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Update exchange request meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $meta    Key-value meta pairs.
	 * @return void
	 */
	public static function update_exchange_request( $post_id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		update_post_meta( $post_id, self::META_UPDATED_AT, current_time( 'mysql' ) );
	}

	/**
	 * Get the status of an exchange request.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_request_status( $post_id ) {
		return sanitize_text_field( get_post_meta( $post_id, self::META_STATUS, true ) );
	}

	/**
	 * Get the callback token for an outgoing request.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_request_callback_token( $post_id ) {
		return sanitize_text_field( get_post_meta( $post_id, self::META_CALLBACK_TOKEN, true ) );
	}

	/**
	 * Get the remote callback URL for an exchange request.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_request_callback_url( $post_id ) {
		return esc_url_raw( get_post_meta( $post_id, self::META_REMOTE_CALLBACK_URL, true ) );
	}

	/**
	 * Get the remote offer URL for an exchange request.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_request_remote_offer_url( $post_id ) {
		return self::normalize_offer_url( get_post_meta( $post_id, self::META_REMOTE_OFFER_URL, true ) );
	}

	/**
	 * Get the remote request endpoint for an exchange request.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_request_remote_request_endpoint( $post_id ) {
		return esc_url_raw( get_post_meta( $post_id, self::META_REMOTE_REQUEST_ENDPOINT, true ) );
	}

	/**
	 * Check whether an active or pending exchange request exists for an offer URL.
	 *
	 * @param string $offer_url Remote offer URL.
	 * @return bool
	 */
	public static function has_active_or_pending_request( $offer_url ) {
		$existing = FPL_CPT::get_exchange_requests_by_offer_url( $offer_url );
		foreach ( $existing as $post ) {
			$status = self::get_request_status( $post->ID );
			if ( in_array( $status, array( 'pending', 'approved', 'active' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	// --- Exchange Partner CRUD ---

	const META_PARTNER_SITE_NAME       = '_fpl_exchange_partner_site_name';
	const META_PARTNER_SITE_URL        = '_fpl_exchange_partner_site_url';
	const META_PARTNER_OFFER_URL       = '_fpl_exchange_partner_offer_url';
	const META_PARTNER_STATUS          = '_fpl_exchange_partner_status';
	const META_PARTNER_CACHED_PRODUCTS = '_fpl_exchange_cached_products';
	const META_PARTNER_LAST_SYNC_AT    = '_fpl_exchange_last_sync_at';
	const META_PARTNER_LAST_SUCCESS_AT = '_fpl_exchange_last_success_at';
	const META_PARTNER_LAST_ERROR      = '_fpl_exchange_last_error';
	const META_PARTNER_CLICKS_SENT     = '_fpl_exchange_clicks_sent';
	const META_PARTNER_DISPLAYS_SENT   = '_fpl_exchange_displays_sent';
	const META_PARTNER_CREATED_AT      = '_fpl_exchange_partner_created_at';
	const META_PARTNER_PAUSE_REASON    = '_fpl_exchange_pause_reason';
	const META_PARTNER_PAUSED_AT       = '_fpl_exchange_paused_at';

	/**
	 * Create an exchange partner post.
	 *
	 * @param array $args Partner data.
	 * @return int|WP_Error
	 */
	public static function create_exchange_partner( array $args ) {
		$normalized_offer_url = self::normalize_offer_url( $args['offer_url'] ?? '' );

		$offer_url_valid = FPL_URL_Helper::is_public_http_url( $normalized_offer_url );
		if ( ! $normalized_offer_url || is_wp_error( $offer_url_valid ) ) {
			return new WP_Error(
				'fpl_invalid_partner_offer_url',
				__( 'Partner offer URL is missing or invalid.', 'friend-product-links' )
			);
		}

		$existing = self::get_partner_by_offer_url( $normalized_offer_url );
		if ( $existing ) {
			return new WP_Error(
				'fpl_duplicate_partner',
				__( 'This site is already an exchange partner.', 'friend-product-links' )
			);
		}

		$title = sanitize_text_field( $args['site_name'] ?? '' );
		if ( ! $title ) {
			$title = __( 'Exchange Partner', 'friend-product-links' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => FPL_CPT::EXCHANGE_PARTNER,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error( 'fpl_partner_create_failed', __( 'Failed to create exchange partner.', 'friend-product-links' ) );
		}

		$meta = array(
			self::META_PARTNER_SITE_NAME       => sanitize_text_field( $args['site_name'] ?? '' ),
			self::META_PARTNER_SITE_URL        => esc_url_raw( $args['site_url'] ?? '' ),
			self::META_PARTNER_OFFER_URL       => $normalized_offer_url,
			self::META_PARTNER_STATUS          => sanitize_text_field( $args['status'] ?? 'active' ),
			self::META_PARTNER_CACHED_PRODUCTS => is_array( $args['cached_products'] ?? null ) ? $args['cached_products'] : array(),
			self::META_PARTNER_LAST_SYNC_AT    => ! empty( $args['cached_products'] ) ? current_time( 'mysql' ) : '',
			self::META_PARTNER_LAST_SUCCESS_AT => ( 'active' === sanitize_text_field( $args['status'] ?? 'active' ) && ! empty( $args['cached_products'] ) ) ? current_time( 'mysql' ) : '',
			self::META_PARTNER_CREATED_AT      => current_time( 'mysql' ),
			self::META_PARTNER_LAST_ERROR      => sanitize_text_field( $args['last_error'] ?? '' ),
			self::META_PARTNER_CLICKS_SENT     => 0,
			self::META_PARTNER_DISPLAYS_SENT   => 0,
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}

	/**
	 * Update partner meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $meta    Key-value meta pairs.
	 * @return void
	 */
	public static function update_partner( $post_id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Get partner status.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_partner_status( $post_id ) {
		return sanitize_text_field( get_post_meta( $post_id, self::META_PARTNER_STATUS, true ) );
	}

	/**
	 * Get a partner by remote offer URL.
	 *
	 * @param string $offer_url Remote offer URL.
	 * @return WP_Post|null
	 */
	public static function get_partner_by_offer_url( $offer_url ) {
		$posts = get_posts(
			array(
				'post_type'      => FPL_CPT::EXCHANGE_PARTNER,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The normalized offer URL is plugin-owned metadata used to identify a partner.
				'meta_query'     => array(
					array(
						'key'     => self::META_PARTNER_OFFER_URL,
						'value'   => self::normalize_offer_url( $offer_url ),
						'compare' => '=',
					),
				),
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Get cached products for a partner.
	 *
	 * @param int $partner_id Partner post ID.
	 * @return array
	 */
	public static function get_partner_cached_products( $partner_id ) {
		$products = get_post_meta( $partner_id, self::META_PARTNER_CACHED_PRODUCTS, true );

		return is_array( $products ) ? $products : array();
	}
}
