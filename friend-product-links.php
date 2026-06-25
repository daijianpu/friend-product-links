<?php
/**
 * Plugin Name: Friend Product Links
 * Description: A free P2P WooCommerce friend product link plugin with local feed caching.
 * Version: 1.1.42
 * Author: Friend Product Links
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: friend-product-links
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FPL_VERSION', '1.1.42' );
define( 'FPL_FILE', __FILE__ );
define( 'FPL_PATH', plugin_dir_path( __FILE__ ) );
define( 'FPL_URL', plugin_dir_url( __FILE__ ) );
define( 'FPL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Core Friends limits (write once, not configurable).
 */
define( 'FPL_CORE_FRIEND_LIMIT', 3 );
define( 'FPL_CORE_PRODUCT_MIN', 4 );
define( 'FPL_CORE_PRODUCT_MAX', 9 );
define( 'FPL_CORE_DISPLAY_PRODUCTS', 4 );

/**
 * Exchange Catalog limits (write once, not configurable).
 */
define( 'FPL_EXCHANGE_MIN_PRODUCTS', 4 );
define( 'FPL_EXCHANGE_MAX_PRODUCTS', 9 );
define( 'FPL_EXCHANGE_DISPLAY_PRODUCTS', 4 );

/**
 * Exchange Catalog Mode (write once, not configurable).
 */
define( 'FPL_EXCHANGE_MODE_OPTION', 'fpl_exchange_mode_enabled' );
define( 'FPL_EXCHANGE_CATALOG_PAGE_OPTION', 'fpl_exchange_catalog_page_id' );
define( 'FPL_EXCHANGE_CATALOG_SHORTCODE', '[fpl_exchange_catalog]' );


/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 *
 * Friend Product Links does not read or write WooCommerce order data, so it is
 * compatible with the custom order tables feature.
 *
 * @return void
 */
function fpl_declare_woocommerce_feature_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'fpl_declare_woocommerce_feature_compatibility' );

require_once FPL_PATH . 'includes/class-fpl-plugin.php';

register_activation_hook( __FILE__, array( 'FPL_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FPL_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'FPL_Plugin', 'instance' ) );
