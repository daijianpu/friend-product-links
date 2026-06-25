<?php
/**
 * Uninstall cleanup.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'FPL_REMOVE_DATA_ON_UNINSTALL' ) || ! FPL_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

$post_types = array( 'fpl_share_link', 'fpl_friend_feed', 'fpl_exchange_request', 'fpl_exchange_partner' );
foreach ( $post_types as $post_type ) {
	$ids = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
}

delete_option( 'fpl_auto_display' );
delete_option( 'fpl_frontend_title' );
delete_option( 'fpl_button_text' );
delete_option( 'fpl_new_window' );
delete_option( 'fpl_show_source' );
delete_option( 'fpl_show_remote_images' );
delete_option( 'fpl_enable_stats' );
delete_option( 'fpl_share_stats' );
delete_option( 'fpl_receive_stats' );
delete_option( 'fpl_click_table_version' );
delete_option( 'fpl_exchange_offer_enabled' );
delete_option( 'fpl_exchange_offer_token' );
delete_option( 'fpl_exchange_offer_product_ids' );
delete_option( 'fpl_exchange_offer_updated_at' );
delete_option( 'fpl_exchange_mode_enabled' );
delete_option( 'fpl_exchange_catalog_page_id' );

global $wpdb;
$table_name = $wpdb->prefix . 'fpl_click_stats';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Explicit uninstall cleanup for the plugin's custom table.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );
