<?php
/**
 * Performance report facade.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Performance_Report {
	/**
	 * Get aggregate report rows.
	 *
	 * @return array
	 */
	public static function get_report() {
		return FPL_Stats_Repository::get_global_report();
	}

	/**
	 * Get aggregate report rows without creating or updating storage.
	 *
	 * @return array
	 */
	public static function get_report_readonly() {
		global $wpdb;

		if ( ! FPL_Stats_Repository::table_exists() ) {
			return array(
				'table_ready'   => false,
				'today_clicks'  => 0,
				'seven_clicks'  => 0,
				'thirty_clicks' => 0,
				'top_friends'   => array(),
				'top_products'  => array(),
			);
		}

		$table_name  = FPL_Stats_Repository::table_name();
		$today       = current_time( 'Y-m-d' );
		$seven_from  = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 6 * DAY_IN_SECONDS ) );
		$thirty_from = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 29 * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only diagnostics must reflect the live aggregate table.
		$today_clicks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks), 0) FROM %i WHERE stat_date = %s', $table_name, $today ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only diagnostics must reflect the live aggregate table.
		$seven_clicks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks), 0) FROM %i WHERE stat_date >= %s', $table_name, $seven_from ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only diagnostics must reflect the live aggregate table.
		$thirty_clicks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks), 0) FROM %i WHERE stat_date >= %s', $table_name, $thirty_from ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only diagnostics must reflect the live aggregate table.
		$top_friends = $wpdb->get_results( $wpdb->prepare( 'SELECT friend_host, SUM(displays) AS displays, SUM(clicks) AS clicks, MAX(last_clicked_at) AS last_clicked_at FROM %i GROUP BY friend_host ORDER BY clicks DESC LIMIT 5', $table_name ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only diagnostics must reflect the live aggregate table.
		$top_products = $wpdb->get_results( $wpdb->prepare( 'SELECT product_hash, product_url_hash, SUM(displays) AS displays, SUM(clicks) AS clicks, MAX(last_clicked_at) AS last_clicked_at FROM %i GROUP BY product_hash, product_url_hash ORDER BY clicks DESC LIMIT 5', $table_name ), ARRAY_A );

		return array(
			'table_ready'   => true,
			'today_clicks'  => $today_clicks,
			'seven_clicks'  => $seven_clicks,
			'thirty_clicks' => $thirty_clicks,
			'top_friends'   => $top_friends,
			'top_products'  => $top_products,
		);
	}
}
