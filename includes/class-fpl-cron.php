<?php
/**
 * Cron synchronization.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Cron {
	const HOOK = 'fpl_sync_friend_feeds';
	const STATS_HOOK = 'fpl_sync_friend_stats';
	const EXCHANGE_HOOK = 'fpl_sync_exchange_partners';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) );
		add_action( self::HOOK, array( $this, 'sync_all' ) );
		add_action( self::STATS_HOOK, array( $this, 'sync_stats_all' ) );
		add_action( self::EXCHANGE_HOOK, array( $this, 'sync_exchange_all' ) );
	}

	/**
	 * Add 12 hour interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public function add_schedule( $schedules ) {
		$schedules['fpl_twelve_hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 hours', 'friend-product-links' ),
		);

		return $schedules;
	}

	/**
	 * Schedule event.
	 *
	 * @return void
	 */
	public static function schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_static_schedule' ) );

		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::STATS_HOOK );
		wp_clear_scheduled_hook( self::EXCHANGE_HOOK );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'fpl_twelve_hours', self::HOOK );
		wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'fpl_twelve_hours', self::STATS_HOOK );
		wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'fpl_twelve_hours', self::EXCHANGE_HOOK );
	}

	/**
	 * Ensure scheduled events exist after imports, migrations, or missed activation.
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_static_schedule' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'fpl_twelve_hours', self::HOOK );
		}

		if ( ! wp_next_scheduled( self::STATS_HOOK ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'fpl_twelve_hours', self::STATS_HOOK );
		}

		if ( ! wp_next_scheduled( self::EXCHANGE_HOOK ) ) {
			wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), 'fpl_twelve_hours', self::EXCHANGE_HOOK );
		}
	}

	/**
	 * Static schedule filter for activation.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function add_static_schedule( $schedules ) {
		$schedules['fpl_twelve_hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => __( 'Every 12 hours', 'friend-product-links' ),
		);

		return $schedules;
	}

	/**
	 * Clear event.
	 *
	 * @return void
	 */
	public static function clear() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::STATS_HOOK );
		wp_clear_scheduled_hook( self::EXCHANGE_HOOK );
	}

	/**
	 * Sync all enabled friends.
	 *
	 * @return void
	 */
	public function sync_all() {
		$friends = array_slice( FPL_CPT::get_friend_feeds( true ), 0, FPL_CORE_FRIEND_LIMIT );
		foreach ( $friends as $friend ) {
			FPL_Feed_Fetcher::sync_friend_feed( $friend->ID );
		}
	}

	/**
	 * Sync aggregate stats to enabled friends.
	 *
	 * @return void
	 */
	public function sync_stats_all() {
		$friends = array_slice( FPL_CPT::get_friend_feeds( true ), 0, FPL_CORE_FRIEND_LIMIT );
		foreach ( $friends as $friend ) {
			FPL_Performance::send_friend_stats( $friend->ID );
		}
	}

	/**
	 * Sync all active exchange partners.
	 *
	 * @return void
	 */
	public function sync_exchange_all() {
		FPL_Exchange_Fetcher::sync_all_partners();
	}
}
