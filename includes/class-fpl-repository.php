<?php
/**
 * Centralized data access for plugin storage.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Repository {
	const POST_TYPE_SHARE_LINK  = 'fpl_share_link';
	const POST_TYPE_FRIEND_FEED = 'fpl_friend_feed';

	const META_ENABLED             = '_fpl_enabled';
	const META_TOKEN               = '_fpl_token';
	const META_PRODUCTS            = '_fpl_selected_product_ids';
	const META_FRIEND_NAME         = '_fpl_friend_name';
	const META_FRIEND_SITE_URL     = '_fpl_friend_site_url';
	const META_FEED_URL            = '_fpl_feed_url';
	const META_CACHED_PRODUCTS     = '_fpl_cached_products';
	const META_LAST_SYNCED_AT      = '_fpl_last_sync_time';
	const META_SYNC_STATUS         = '_fpl_last_sync_status';
	const META_SYNC_ERROR          = '_fpl_last_error_message';
	const META_SORT_ORDER          = '_fpl_sort_order';
	const META_NOTE                = '_fpl_note';
	const META_STATS_ENDPOINT      = '_fpl_stats_endpoint';
	const META_STATS_KEY           = '_fpl_stats_key';
	const META_SHARE_STATS_KEY     = '_fpl_stats_key';
	const META_FRIEND_STATS_KEY    = '_fpl_stats_key';
	const META_LOCAL_PRODUCT_STATS = '_fpl_local_product_stats';
	const META_CREATED_AT          = '_fpl_created_at';
	const META_UPDATED_AT          = '_fpl_updated_at';
	const META_REMOTE_DISPLAY_COUNT = '_fpl_remote_display_count';
	const META_REMOTE_CLICK_COUNT  = '_fpl_remote_click_count';
	const META_REMOTE_PRODUCT_STATS = '_fpl_remote_product_stats';
	const META_REMOTE_LAST_RECEIVED_TIME = '_fpl_remote_last_received_time';
	const META_REMOTE_LAST_SENDER_SITE = '_fpl_remote_last_sender_site';
	const META_LOCAL_DISPLAY_COUNT = '_fpl_local_display_count';
	const META_LOCAL_CLICK_COUNT   = '_fpl_local_click_count';
	const META_LOCAL_LAST_CLICK_TIME = '_fpl_local_last_click_time';
	const META_LAST_STATS_SENT_TIME = '_fpl_last_stats_sent_time';
	const META_LAST_STATS_SENT_STATUS = '_fpl_last_stats_sent_status';
	const META_LAST_STATS_SENT_ERROR = '_fpl_last_stats_sent_error';
	const META_FAIL_COUNT          = '_fpl_fail_count';
	const META_HEALTH_WARNING      = '_fpl_health_warning';
	const META_SITE_MISMATCH_WARNING = '_fpl_site_mismatch_warning';
	const META_CACHE_SOURCE_HOST   = '_fpl_cache_source_host';
	const META_CACHE_FEED_URL_HASH = '_fpl_cache_feed_url_hash';
	const META_CACHE_SITE_URL_HASH = '_fpl_cache_site_url_hash';
	const META_CACHE_CACHED_AT     = '_fpl_cache_cached_at';

	const OPTION_AUTO_DISPLAY        = 'fpl_auto_display';
	const OPTION_FRONTEND_TITLE      = 'fpl_frontend_title';
	const OPTION_BUTTON_TEXT         = 'fpl_button_text';
	const OPTION_NEW_WINDOW          = 'fpl_new_window';
	const OPTION_SHOW_SOURCE         = 'fpl_show_source';
	const OPTION_SHOW_REMOTE_IMAGES  = 'fpl_show_remote_images';
	const OPTION_ENABLE_STATS        = 'fpl_enable_stats';
	const OPTION_SHARE_STATS         = 'fpl_share_stats';
	const OPTION_RECEIVE_STATS       = 'fpl_receive_stats';
	const OPTION_CLICK_TABLE_VERSION = 'fpl_click_table_version';

	/**
	 * Get a share link post.
	 *
	 * @param int $id Post ID.
	 * @return WP_Post|null
	 */
	public static function get_share_link( $id ) {
		$post = get_post( absint( $id ) );

		return $post && self::POST_TYPE_SHARE_LINK === $post->post_type ? $post : null;
	}

	/**
	 * Get share link token.
	 *
	 * @param int $id Share link ID.
	 * @return string
	 */
	public static function get_share_link_token( $id ) {
		return sanitize_text_field( get_post_meta( absint( $id ), self::META_TOKEN, true ) );
	}

	/**
	 * Get selected product IDs.
	 *
	 * @param int $id Share link ID.
	 * @return int[]
	 */
	public static function get_share_link_products( $id ) {
		$ids = get_post_meta( absint( $id ), self::META_PRODUCTS, true );

		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Save selected product IDs.
	 *
	 * @param int   $id Share link ID.
	 * @param array $product_ids Product IDs.
	 * @return void
	 */
	public static function save_share_link_products( $id, array $product_ids ) {
		update_post_meta( absint( $id ), self::META_PRODUCTS, array_values( array_filter( array_map( 'absint', $product_ids ) ) ) );
	}

	/**
	 * Get friend feed URL.
	 *
	 * @param int $friend_id Friend ID.
	 * @return string
	 */
	public static function get_friend_feed_url( $friend_id ) {
		return esc_url_raw( get_post_meta( absint( $friend_id ), self::META_FEED_URL, true ) );
	}

	/**
	 * Get friend site URL.
	 *
	 * @param int $friend_id Friend ID.
	 * @return string
	 */
	public static function get_friend_site_url( $friend_id ) {
		return esc_url_raw( get_post_meta( absint( $friend_id ), self::META_FRIEND_SITE_URL, true ) );
	}

	/**
	 * Get cached products.
	 *
	 * @param int $friend_id Friend ID.
	 * @return array
	 */
	public static function get_cached_products( $friend_id ) {
		$products = get_post_meta( absint( $friend_id ), self::META_CACHED_PRODUCTS, true );

		return is_array( $products ) ? $products : array();
	}

	/**
	 * Save cached products.
	 *
	 * @param int   $friend_id Friend ID.
	 * @param array $products Products.
	 * @return void
	 */
	public static function save_cached_products( $friend_id, array $products ) {
		update_post_meta( absint( $friend_id ), self::META_CACHED_PRODUCTS, $products );
	}

	/**
	 * Check whether a friend has cache matching the current URLs.
	 *
	 * @param int $friend_id Friend ID.
	 * @return bool
	 */
	public static function friend_has_valid_cache( $friend_id ) {
		$friend_id = absint( $friend_id );
		$products  = self::get_cached_products( $friend_id );
		if ( empty( $products ) ) {
			return false;
		}

		$feed_url        = self::get_friend_feed_url( $friend_id );
		$friend_site_url = self::get_friend_site_url( $friend_id );
		$boundary        = FPL_URL_Helper::validate_friend_feed_boundary( $friend_site_url, $feed_url );
		if ( is_wp_error( $boundary ) ) {
			return false;
		}

		$identity = self::get_cache_identity( $friend_id );
		if ( empty( $identity['source_host'] ) || empty( $identity['feed_url_hash'] ) ) {
			return false;
		}

		if ( $identity['source_host'] !== FPL_URL_Helper::get_host( $feed_url ) ) {
			return false;
		}

		if ( $identity['feed_url_hash'] !== self::hash_url_for_cache_identity( $feed_url ) ) {
			return false;
		}

		if ( ! empty( $identity['site_url_hash'] ) && $identity['site_url_hash'] !== self::hash_url_for_cache_identity( $friend_site_url ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Remove cached friend products and cache identity.
	 *
	 * @param int $friend_id Friend ID.
	 * @return void
	 */
	public static function invalidate_friend_cache( $friend_id ) {
		$friend_id = absint( $friend_id );

		delete_post_meta( $friend_id, self::META_CACHED_PRODUCTS );
		delete_post_meta( $friend_id, self::META_CACHE_SOURCE_HOST );
		delete_post_meta( $friend_id, self::META_CACHE_FEED_URL_HASH );
		delete_post_meta( $friend_id, self::META_CACHE_SITE_URL_HASH );
		delete_post_meta( $friend_id, self::META_CACHE_CACHED_AT );
		delete_post_meta( $friend_id, self::META_STATS_ENDPOINT );
		delete_post_meta( $friend_id, self::META_FRIEND_STATS_KEY );
		delete_post_meta( $friend_id, self::META_SITE_MISMATCH_WARNING );
		update_post_meta( $friend_id, self::META_ENABLED, '0' );
	}

	/**
	 * Save cache source identity for a successful feed sync.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $friend_site_url Friend site URL.
	 * @param string $feed_url Feed URL.
	 * @return void
	 */
	public static function save_cache_identity( $friend_id, $friend_site_url, $feed_url ) {
		$friend_id = absint( $friend_id );

		update_post_meta( $friend_id, self::META_CACHE_SOURCE_HOST, FPL_URL_Helper::get_host( $feed_url ) );
		update_post_meta( $friend_id, self::META_CACHE_FEED_URL_HASH, self::hash_url_for_cache_identity( $feed_url ) );
		update_post_meta( $friend_id, self::META_CACHE_SITE_URL_HASH, self::hash_url_for_cache_identity( $friend_site_url ) );
		update_post_meta( $friend_id, self::META_CACHE_CACHED_AT, current_time( 'mysql' ) );
	}

	/**
	 * Get saved cache identity.
	 *
	 * @param int $friend_id Friend ID.
	 * @return array
	 */
	public static function get_cache_identity( $friend_id ) {
		$friend_id = absint( $friend_id );

		return array(
			'source_host'   => sanitize_text_field( get_post_meta( $friend_id, self::META_CACHE_SOURCE_HOST, true ) ),
			'feed_url_hash' => sanitize_text_field( get_post_meta( $friend_id, self::META_CACHE_FEED_URL_HASH, true ) ),
			'site_url_hash' => sanitize_text_field( get_post_meta( $friend_id, self::META_CACHE_SITE_URL_HASH, true ) ),
			'cached_at'     => sanitize_text_field( get_post_meta( $friend_id, self::META_CACHE_CACHED_AT, true ) ),
		);
	}

	/**
	 * Hash URL for cache identity comparisons.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function hash_url_for_cache_identity( $url ) {
		return hash( 'sha256', FPL_URL_Helper::normalize_url( $url ) );
	}

	/**
	 * Record sync error.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $message Error message.
	 * @return void
	 */
	public static function update_sync_error( $friend_id, $message ) {
		update_post_meta( absint( $friend_id ), self::META_SYNC_STATUS, 'failed' );
		update_post_meta( absint( $friend_id ), self::META_SYNC_ERROR, sanitize_text_field( $message ) );
	}

	/**
	 * Clear sync error.
	 *
	 * @param int $friend_id Friend ID.
	 * @return void
	 */
	public static function clear_sync_error( $friend_id ) {
		update_post_meta( absint( $friend_id ), self::META_SYNC_ERROR, '' );
	}
}
