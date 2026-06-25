<?php
/**
 * Main plugin loader.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var FPL_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Get singleton instance.
	 *
	 * @return FPL_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::load_core_files();

		FPL_CPT::register();
		FPL_Performance::maybe_create_click_table();
		FPL_Cron::schedule();

		flush_rewrite_rules();
	}

	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::load_core_files();

		FPL_Cron::clear();
		flush_rewrite_rules();
	}

	/**
	 * Check whether WooCommerce APIs needed by the plugin are available.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		self::load_core_files();

		add_action( 'init', array( 'FPL_CPT', 'register' ) );
		add_action( 'init', array( 'FPL_Cron', 'maybe_schedule' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_wc_notice' ) );
		add_filter( 'plugin_action_links_' . FPL_BASENAME, array( $this, 'plugin_action_links' ) );

		$this->modules = array(
			new FPL_REST_Feed(),
			new FPL_Exchange_REST(),
			new FPL_Renderer(),
			new FPL_Shortcode(),
			new FPL_Exchange_Renderer(),
			new FPL_Exchange_Click_Tracker(),
			new FPL_Cron(),
			new FPL_Performance(),
		);

		if ( is_admin() ) {
			array_unshift( $this->modules, new FPL_Admin() );
		}

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'hooks' ) ) {
				$module->hooks();
			}
		}
	}

	/**
	 * Load required class files.
	 *
	 * @return void
	 */
	private static function load_core_files() {
		$files = array(
			'class-fpl-cpt.php',
			'class-fpl-security.php',
			'class-fpl-url-helper.php',
			'class-fpl-repository.php',
			'class-fpl-stats-repository.php',
			'class-fpl-exchange-repository.php',
			'class-fpl-feed-builder.php',
			'class-fpl-feed-fetcher.php',
			'class-fpl-rest-feed.php',
			'class-fpl-exchange-rest.php',
			'class-fpl-exchange-feed-builder.php',
			'class-fpl-exchange-fetcher.php',
			'class-fpl-exchange-renderer.php',
			'class-fpl-exchange-click-tracker.php',
			'class-fpl-exchange-catalog-page-manager.php',
			'class-fpl-renderer.php',
			'class-fpl-shortcode.php',
			'class-fpl-cron.php',
			'class-fpl-click-tracker.php',
			'class-fpl-display-tracker.php',
			'class-fpl-stats-exchange.php',
			'class-fpl-performance-report.php',
			'class-fpl-performance.php',
		);

		foreach ( $files as $file ) {
			require_once FPL_PATH . 'includes/' . $file;
		}

		if ( is_admin() ) {
			require_once FPL_PATH . 'includes/admin/class-fpl-admin-view-helper.php';
			require_once FPL_PATH . 'includes/admin/class-fpl-dashboard-page.php';
			require_once FPL_PATH . 'includes/admin/class-fpl-admin-actions.php';
			require_once FPL_PATH . 'includes/admin/class-fpl-admin-pages.php';
			require_once FPL_PATH . 'includes/admin/class-fpl-exchange-admin-actions.php';
			require_once FPL_PATH . 'includes/admin/class-fpl-exchange-admin-pages.php';
			require_once FPL_PATH . 'includes/admin/class-fpl-admin.php';
		}
	}

	/**
	 * Show WooCommerce dependency notice without causing fatal errors.
	 *
	 * @return void
	 */
	public function maybe_show_wc_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || self::is_woocommerce_active() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Friend Product Links works best with WooCommerce active. Product feed generation and frontend display are paused until WooCommerce is available.', 'friend-product-links' );
		echo '</p></div>';
	}

	/**
	 * Add Settings shortcut.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=fpl-settings' ) ),
			esc_html__( 'Settings', 'friend-product-links' )
		);

		array_unshift( $links, $settings );

		return $links;
	}
}
