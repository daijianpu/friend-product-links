<?php
/**
 * Aggregate stats table access.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Stats_Repository {
	const TABLE_VERSION = '3';

	/**
	 * Ensure aggregate stats table exists.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		static $checked = false;

		if ( $checked ) {
			return;
		}

		global $wpdb;

		if ( self::TABLE_VERSION === get_option( FPL_Repository::OPTION_CLICK_TABLE_VERSION ) && self::table_exists() ) {
			$checked = true;
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		self::drop_legacy_unique_key();

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			stat_date date NOT NULL,
			share_link_id bigint unsigned NOT NULL DEFAULT 0,
			friend_id bigint unsigned NOT NULL DEFAULT 0,
			friend_host varchar(191) NOT NULL,
			product_hash char(64) NOT NULL,
			product_url_hash char(64) NOT NULL,
			displays bigint unsigned NOT NULL DEFAULT 0,
			clicks bigint unsigned NOT NULL DEFAULT 0,
			last_displayed_at datetime DEFAULT NULL,
			last_clicked_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_daily_friend_product (stat_date, friend_id, product_hash, product_url_hash),
			KEY friend_id (friend_id),
			KEY product_hash (product_hash),
			KEY stat_date (stat_date)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( FPL_Repository::OPTION_CLICK_TABLE_VERSION, self::TABLE_VERSION, false );

		$checked = true;
	}

	/**
	 * Return aggregate table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'fpl_click_stats';
	}

	/**
	 * Check whether the aggregate stats table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A live schema check cannot use the object cache.
		return $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
	}

	/**
	 * Remove the older unique key that merged different friend feeds on the same host.
	 *
	 * @return void
	 */
	private static function drop_legacy_unique_key() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return;
		}

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This one-time migration must inspect the live table schema.
		$index_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW INDEX FROM %i WHERE Key_name = %s',
				$table_name,
				'uniq_daily_product'
			)
		);
		if ( $index_name ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required one-time migration from the legacy index.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required one-time migration from the legacy index.
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i DROP INDEX %i',
					$table_name,
					'uniq_daily_product'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}

	/**
	 * Record one server-rendered display.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $product_hash Product hash.
	 * @param string $product_url Product URL.
	 * @return void
	 */
	public static function record_display( $friend_id, $product_hash, $product_url ) {
		self::record_event( $friend_id, $product_hash, $product_url, 'display' );
	}

	/**
	 * Batch record displays grouped by friend_id.
	 * Runs maybe_create_table once, then inserts per product.
	 *
	 * @param array $grouped Array keyed by friend_id, each value is array of (product_hash, product_url).
	 * @return void
	 */
	public static function record_displays_batch( array $grouped ) {
		global $wpdb;

		self::maybe_create_table();

		$table_name = self::table_name();
		$today      = current_time( 'Y-m-d' );
		$now        = current_time( 'mysql' );

		foreach ( $grouped as $friend_id => $items ) {
			$friend_id    = absint( $friend_id );
			$friend_host  = FPL_URL_Helper::get_host( FPL_Repository::get_friend_site_url( $friend_id ) );

			if ( '' === $friend_host ) {
				$friend_host = '-';
			}

			foreach ( array_slice( $items, 0, FPL_CORE_DISPLAY_PRODUCTS ) as $item ) {
				$product_hash = sanitize_text_field( $item['product_hash'] );
				$product_url  = FPL_URL_Helper::normalize_url( $item['product_url'] );

				if ( ! FPL_Security::is_product_hash( $product_hash ) || is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic aggregate counter updates must be written directly.
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO %i (stat_date, share_link_id, friend_id, friend_host, product_hash, product_url_hash, displays, clicks, last_displayed_at)
						VALUES (%s, %d, %d, %s, %s, %s, 1, 0, %s)
						ON DUPLICATE KEY UPDATE displays = displays + 1, last_displayed_at = VALUES(last_displayed_at)",
						$table_name,
						$today,
						0,
						$friend_id,
						sanitize_text_field( $friend_host ),
						$product_hash,
						hash( 'sha256', $product_url ),
						$now
					)
				);
			}
		}
	}

	/**
	 * Record one local click.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $product_hash Product hash.
	 * @param string $product_url Product URL.
	 * @return void
	 */
	public static function record_click( $friend_id, $product_hash, $product_url ) {
		self::record_event( $friend_id, $product_hash, $product_url, 'click' );
	}

	/**
	 * Get totals for one friend with old meta fallback.
	 *
	 * @param int $friend_id Friend ID.
	 * @return array
	 */
	public static function get_friend_totals( $friend_id ) {
		global $wpdb;

		self::maybe_create_table();

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate stats are volatile and read from the custom table as the source of truth.
		$row        = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(displays), 0) AS displays, COALESCE(SUM(clicks), 0) AS clicks, MAX(last_clicked_at) AS last_clicked_at FROM %i WHERE friend_id = %d',
				$table_name,
				absint( $friend_id )
			),
			ARRAY_A
		);

		$displays = isset( $row['displays'] ) ? absint( $row['displays'] ) : 0;
		$clicks   = isset( $row['clicks'] ) ? absint( $row['clicks'] ) : 0;

		return array(
			'displays'        => $displays ? $displays : absint( get_post_meta( $friend_id, FPL_Repository::META_LOCAL_DISPLAY_COUNT, true ) ),
			'clicks'          => $clicks ? $clicks : absint( get_post_meta( $friend_id, FPL_Repository::META_LOCAL_CLICK_COUNT, true ) ),
			'last_clicked_at' => ! empty( $row['last_clicked_at'] ) ? $row['last_clicked_at'] : get_post_meta( $friend_id, FPL_Repository::META_LOCAL_LAST_CLICK_TIME, true ),
		);
	}

	/**
	 * Get per-product stats for stats sharing.
	 *
	 * @param int $friend_id Friend ID.
	 * @return array
	 */
	public static function get_friend_product_stats( $friend_id ) {
		global $wpdb;

		self::maybe_create_table();

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate stats are volatile and read from the custom table as the source of truth.
		$rows       = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_hash, SUM(displays) AS displays, SUM(clicks) AS clicks FROM %i WHERE friend_id = %d GROUP BY product_hash LIMIT %d',
				$table_name,
				absint( $friend_id ),
				absint( FPL_CORE_PRODUCT_MAX )
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return self::clean_legacy_product_stats( get_post_meta( $friend_id, FPL_Repository::META_LOCAL_PRODUCT_STATS, true ) );
		}

		$stats = array();
		foreach ( $rows as $row ) {
			$hash = sanitize_text_field( $row['product_hash'] );
			if ( FPL_Security::is_product_hash( $hash ) ) {
				$stats[ $hash ] = array(
					'display' => absint( $row['displays'] ),
					'click'   => absint( $row['clicks'] ),
				);
			}
		}

		return $stats;
	}

	/**
	 * Get global report data.
	 *
	 * @return array
	 */
	public static function get_global_report() {
		global $wpdb;

		self::maybe_create_table();

		$table_name = self::table_name();
		$timestamp  = current_time( 'timestamp' );
		$today      = current_time( 'Y-m-d' );
		$seven_from = function_exists( 'wp_date' )
			? wp_date( 'Y-m-d', $timestamp - ( 6 * DAY_IN_SECONDS ) )
			: date_i18n( 'Y-m-d', $timestamp - ( 6 * DAY_IN_SECONDS ) );
		$thirty_from = function_exists( 'wp_date' )
			? wp_date( 'Y-m-d', $timestamp - ( 29 * DAY_IN_SECONDS ) )
			: date_i18n( 'Y-m-d', $timestamp - ( 29 * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard stats are volatile and read directly from the aggregate table.
		$today_clicks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks), 0) FROM %i WHERE stat_date = %s', $table_name, $today ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard stats are volatile and read directly from the aggregate table.
		$seven_clicks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks), 0) FROM %i WHERE stat_date >= %s', $table_name, $seven_from ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard stats are volatile and read directly from the aggregate table.
		$thirty_clicks = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks), 0) FROM %i WHERE stat_date >= %s', $table_name, $thirty_from ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard stats are volatile and read directly from the aggregate table.
		$friends = $wpdb->get_results( $wpdb->prepare( 'SELECT friend_host, SUM(displays) AS displays, SUM(clicks) AS clicks, MAX(last_clicked_at) AS last_clicked_at FROM %i GROUP BY friend_host ORDER BY clicks DESC LIMIT 10', $table_name ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard stats are volatile and read directly from the aggregate table.
		$products = $wpdb->get_results( $wpdb->prepare( 'SELECT product_hash, product_url_hash, SUM(displays) AS displays, SUM(clicks) AS clicks, MAX(last_clicked_at) AS last_clicked_at FROM %i GROUP BY product_hash, product_url_hash ORDER BY clicks DESC LIMIT 10', $table_name ), ARRAY_A );

		return array(
			'today'    => $today_clicks,
			'seven'    => $seven_clicks,
			'thirty'   => $thirty_clicks,
			'friends'  => $friends,
			'products' => $products,
		);
	}

	/**
	 * Delete stats for one friend.
	 *
	 * @param int $friend_id Friend ID.
	 * @return void
	 */
	public static function delete_friend_stats( $friend_id ) {
		global $wpdb;

		self::maybe_create_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting a friend's aggregate rows must update the custom table immediately.
		$wpdb->delete( self::table_name(), array( 'friend_id' => absint( $friend_id ) ), array( '%d' ) );
	}

	/**
	 * Record one aggregate event.
	 *
	 * @param int    $friend_id Friend ID.
	 * @param string $product_hash Product hash.
	 * @param string $product_url Product URL.
	 * @param string $metric display|click.
	 * @return void
	 */
	private static function record_event( $friend_id, $product_hash, $product_url, $metric ) {
		global $wpdb;

		$friend_id    = absint( $friend_id );
		$product_hash = sanitize_text_field( $product_hash );
		$product_url  = FPL_URL_Helper::normalize_url( $product_url );

		if ( ! $friend_id || ! FPL_Security::is_product_hash( $product_hash ) || is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) ) {
			return;
		}

		self::maybe_create_table();

		$friend_host = FPL_URL_Helper::get_host( FPL_Repository::get_friend_site_url( $friend_id ) );
		if ( '' === $friend_host ) {
			$friend_host = FPL_URL_Helper::get_host( $product_url );
		}

		$table_name = self::table_name();
		$now        = current_time( 'mysql' );

		if ( 'display' === $metric ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic aggregate counter updates must be written directly.
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO %i (stat_date, share_link_id, friend_id, friend_host, product_hash, product_url_hash, displays, clicks, last_displayed_at)
					VALUES (%s, %d, %d, %s, %s, %s, 1, 0, %s)
					ON DUPLICATE KEY UPDATE displays = displays + 1, last_displayed_at = VALUES(last_displayed_at)",
					$table_name,
					current_time( 'Y-m-d' ),
					0,
					$friend_id,
					sanitize_text_field( $friend_host ),
					$product_hash,
					hash( 'sha256', $product_url ),
					$now
				)
			);
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic aggregate counter updates must be written directly.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (stat_date, share_link_id, friend_id, friend_host, product_hash, product_url_hash, displays, clicks, last_clicked_at)
				VALUES (%s, %d, %d, %s, %s, %s, 0, 1, %s)
				ON DUPLICATE KEY UPDATE clicks = clicks + 1, last_clicked_at = VALUES(last_clicked_at)",
				$table_name,
				current_time( 'Y-m-d' ),
				0,
				$friend_id,
				sanitize_text_field( $friend_host ),
				$product_hash,
				hash( 'sha256', $product_url ),
				$now
			)
		);
	}

	/**
	 * Clean legacy serialized product stats.
	 *
	 * @param mixed $stats Raw stats.
	 * @return array
	 */
	private static function clean_legacy_product_stats( $stats ) {
		if ( ! is_array( $stats ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $stats, 0, FPL_CORE_PRODUCT_MAX, true ) as $hash => $row ) {
			$hash = sanitize_text_field( $hash );
			if ( FPL_Security::is_product_hash( $hash ) && is_array( $row ) ) {
				$clean[ $hash ] = array(
					'display' => isset( $row['display'] ) ? absint( $row['display'] ) : 0,
					'click'   => isset( $row['click'] ) ? absint( $row['click'] ) : 0,
				);
			}
		}

		return $clean;
	}
}
