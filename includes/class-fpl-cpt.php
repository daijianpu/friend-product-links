<?php
/**
 * Custom post types and meta helpers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_CPT {
	const SHARE_LINK        = 'fpl_share_link';
	const FRIEND_FEED       = 'fpl_friend_feed';
	const EXCHANGE_REQUEST  = 'fpl_exchange_request';
	const EXCHANGE_PARTNER  = 'fpl_exchange_partner';

	/**
	 * Register storage CPTs.
	 *
	 * @return void
	 */
	public static function register() {
		$args = array(
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'supports'            => array( 'title' ),
		);

		register_post_type(
			self::SHARE_LINK,
			array_merge(
				$args,
				array(
					'label'  => __( 'Friend Product Share Links', 'friend-product-links' ),
					'labels' => array(
						'name'          => __( 'Friend Product Share Links', 'friend-product-links' ),
						'singular_name' => __( 'Friend Product Share Link', 'friend-product-links' ),
					),
				)
			)
		);

		register_post_type(
			self::FRIEND_FEED,
			array_merge(
				$args,
				array(
					'label'  => __( 'Friend Feeds', 'friend-product-links' ),
					'labels' => array(
						'name'          => __( 'Friend Feeds', 'friend-product-links' ),
						'singular_name' => __( 'Friend Feed', 'friend-product-links' ),
					),
				)
			)
		);

		register_post_type(
			self::EXCHANGE_REQUEST,
			array_merge(
				$args,
				array(
					'label'  => __( 'Exchange Requests', 'friend-product-links' ),
					'labels' => array(
						'name'          => __( 'Exchange Requests', 'friend-product-links' ),
						'singular_name' => __( 'Exchange Request', 'friend-product-links' ),
					),
				)
			)
		);

		register_post_type(
			self::EXCHANGE_PARTNER,
			array_merge(
				$args,
				array(
					'label'  => __( 'Exchange Partners', 'friend-product-links' ),
					'labels' => array(
						'name'          => __( 'Exchange Partners', 'friend-product-links' ),
						'singular_name' => __( 'Exchange Partner', 'friend-product-links' ),
					),
				)
			)
		);
	}

	/**
	 * Check admin capability.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::manage_capability() ) || current_user_can( 'manage_options' );
	}

	/**
	 * Get the preferred admin capability for menu access.
	 *
	 * @return string
	 */
	public static function manage_capability() {
		return FPL_Plugin::is_woocommerce_active() ? 'manage_woocommerce' : 'manage_options';
	}

	/**
	 * Get active share links.
	 *
	 * @return WP_Post[]
	 */
	public static function get_share_links( $limit = 100 ) {
		return get_posts(
			array(
				'post_type'      => self::SHARE_LINK,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => (int) $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Get friend feeds.
	 *
	 * @param bool $enabled_only Only enabled feeds.
	 * @return WP_Post[]
	 */
	public static function get_friend_feeds( $enabled_only = false, $limit = 100 ) {
		$args = array(
			'post_type'      => self::FRIEND_FEED,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => (int) $limit,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sort order is plugin-owned post meta and is required for deterministic friend ordering.
			'meta_key'       => FPL_Repository::META_SORT_ORDER,
			'orderby'        => 'meta_value_num date',
			'order'          => 'ASC',
		);

		if ( $enabled_only ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering the plugin's bounded friend-feed post type by its enabled flag is required.
			$args['meta_query'] = array(
				array(
					'key'   => FPL_Repository::META_ENABLED,
					'value' => '1',
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Find a share link by token.
	 *
	 * @param string $token Token.
	 * @return WP_Post|null
	 */
	public static function get_share_link_by_token( $token ) {
		$posts = get_posts(
			array(
				'post_type'      => self::SHARE_LINK,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The random share token is stored as plugin post meta and uniquely identifies the link.
				'meta_query'     => array(
					array(
						'key'   => FPL_Repository::META_TOKEN,
						'value' => sanitize_text_field( $token ),
					),
				),
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Count friend feeds.
	 *
	 * @return int
	 */
	public static function count_friend_feeds() {
		$counts = wp_count_posts( self::FRIEND_FEED );

		return (int) $counts->publish + (int) $counts->draft;
	}

	/**
	 * Get exchange requests by direction.
	 *
	 * @param string $direction incoming|outgoing.
	 * @return WP_Post[]
	 */
	public static function get_exchange_requests( $direction = '', $limit = 100 ) {
		$args = array(
			'post_type'      => self::EXCHANGE_REQUEST,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $direction ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Direction is plugin-owned request metadata used to split the bounded admin lists.
			$args['meta_query'] = array(
				array(
					'key'   => FPL_Exchange_Repository::META_DIRECTION,
					'value' => sanitize_text_field( $direction ),
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Find an exchange request by its unique request ID.
	 *
	 * @param string $request_id Request ID.
	 * @return WP_Post|null
	 */
	public static function get_exchange_request_by_request_id( $request_id ) {
		$posts = get_posts(
			array(
				'post_type'      => self::EXCHANGE_REQUEST,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The random request ID is stored as plugin post meta and uniquely identifies the request.
				'meta_query'     => array(
					array(
						'key'   => FPL_Exchange_Repository::META_REQUEST_ID,
						'value' => sanitize_text_field( $request_id ),
					),
				),
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Find exchange requests by remote offer URL.
	 *
	 * @param string $offer_url Remote offer URL.
	 * @return WP_Post[]
	 */
	public static function get_exchange_requests_by_offer_url( $offer_url ) {
		return get_posts(
			array(
				'post_type'      => self::EXCHANGE_REQUEST,
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The normalized remote offer URL is plugin-owned request metadata.
				'meta_query'     => array(
					array(
						'key'     => FPL_Exchange_Repository::META_REMOTE_OFFER_URL,
						'value'   => FPL_Exchange_Repository::normalize_offer_url( $offer_url ),
						'compare' => '=',
					),
				),
			)
		);
	}

	/**
	 * Get exchange partners.
	 *
	 * @param bool $active_only Only active partners.
	 * @return WP_Post[]
	 */
	public static function get_exchange_partners( $active_only = false, $limit = 100 ) {
		$args = array(
			'post_type'      => self::EXCHANGE_PARTNER,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $active_only ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Partner status is plugin-owned metadata required to return active partners only.
			$args['meta_query'] = array(
				array(
					'key'   => FPL_Exchange_Repository::META_PARTNER_STATUS,
					'value' => 'active',
				),
			);
		}

		return get_posts( $args );
	}
}
