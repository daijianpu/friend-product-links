<?php
/**
 * Backward-compatible performance facade.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Performance {
	/**
	 * Register performance-related hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		$click_tracker  = new FPL_Click_Tracker();
		$stats_exchange = new FPL_Stats_Exchange();

		$click_tracker->hooks();
		$stats_exchange->hooks();
	}

	/**
	 * Ensure aggregate table exists.
	 *
	 * @return void
	 */
	public static function maybe_create_click_table() {
		FPL_Stats_Repository::maybe_create_table();
	}

	/**
	 * Create signed local click URL.
	 *
	 * @param int    $friend_id Friend feed ID.
	 * @param string $product_hash Product hash.
	 * @return string
	 */
	public static function click_url( $friend_id, $product_hash ) {
		return FPL_Click_Tracker::click_url( $friend_id, $product_hash );
	}

	/**
	 * Record server-rendered displays.
	 *
	 * @param array $products Rendered product rows.
	 * @return void
	 */
	public static function record_displayed_products( $products ) {
		FPL_Display_Tracker::record_displayed_products( $products );
	}

	/**
	 * Send aggregate stats to one friend site.
	 *
	 * @param int $friend_id Friend feed ID.
	 * @return true|WP_Error
	 */
	public static function send_friend_stats( $friend_id ) {
		return FPL_Stats_Exchange::send_friend_stats( $friend_id );
	}

	/**
	 * Ensure a share link has a stats key.
	 *
	 * @param int $share_id Share link ID.
	 * @return string
	 */
	public static function ensure_share_stats_key( $share_id ) {
		return FPL_Stats_Exchange::ensure_share_stats_key( $share_id );
	}

	/**
	 * Get aggregate report.
	 *
	 * @return array
	 */
	public static function get_click_report() {
		return FPL_Performance_Report::get_report();
	}

	/**
	 * Delete local stats for a removed friend.
	 *
	 * @param int $friend_id Friend ID.
	 * @return void
	 */
	public static function delete_friend_stats( $friend_id ) {
		FPL_Stats_Repository::delete_friend_stats( $friend_id );
	}
}
