<?php
/**
 * Admin hooks, menu, assets, and page routing.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Admin {
	/**
	 * Dashboard page.
	 *
	 * @var FPL_Dashboard_Page
	 */
	private $dashboard_page;

	/**
	 * Admin actions.
	 *
	 * @var FPL_Admin_Actions
	 */
	private $actions;

	/**
	 * Admin pages.
	 *
	 * @var FPL_Admin_Pages
	 */
	private $pages;

	/**
	 * Exchange admin actions.
	 *
	 * @var FPL_Exchange_Admin_Actions
	 */
	private $exchange_actions;

	/**
	 * Exchange admin pages.
	 *
	 * @var FPL_Exchange_Admin_Pages
	 */
	private $exchange_pages;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->dashboard_page  = new FPL_Dashboard_Page();
		$this->actions         = new FPL_Admin_Actions();
		$this->pages           = new FPL_Admin_Pages();
		$this->exchange_actions = new FPL_Exchange_Admin_Actions();
		$this->exchange_pages  = new FPL_Exchange_Admin_Pages();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this->actions, 'handle_actions' ) );
		add_action( 'admin_init', array( $this->exchange_actions, 'handle_actions' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = FPL_CPT::manage_capability();

		add_menu_page(
			__( 'Friend Product Links', 'friend-product-links' ),
			__( 'Friend Product Links', 'friend-product-links' ),
			$cap,
			'fpl-dashboard',
			array( $this->dashboard_page, 'render_dashboard_page' ),
			'dashicons-admin-links',
			56
		);

		add_submenu_page( 'fpl-dashboard', __( 'Dashboard', 'friend-product-links' ), __( 'Dashboard', 'friend-product-links' ), $cap, 'fpl-dashboard', array( $this->dashboard_page, 'render_dashboard_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Core Product Links', 'friend-product-links' ), __( 'Core Product Links', 'friend-product-links' ), $cap, 'fpl-product-links', array( $this->pages, 'render_share_links_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Core Friends', 'friend-product-links' ), __( 'Core Friends', 'friend-product-links' ), $cap, 'fpl-friends', array( $this->pages, 'render_friends_page' ) );

		add_submenu_page( 'fpl-dashboard', __( 'Exchange My Offer', 'friend-product-links' ), __( 'Exchange My Offer', 'friend-product-links' ), $cap, 'fpl-exchange-offer', array( $this->exchange_pages, 'render_my_offer_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Start Exchange', 'friend-product-links' ), __( 'Start Exchange', 'friend-product-links' ), $cap, 'fpl-start-exchange', array( $this->exchange_pages, 'render_start_exchange_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Exchange Requests', 'friend-product-links' ), __( 'Exchange Requests', 'friend-product-links' ), $cap, 'fpl-exchange-requests', array( $this->exchange_pages, 'render_exchange_requests_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Exchange Partners', 'friend-product-links' ), __( 'Exchange Partners', 'friend-product-links' ), $cap, 'fpl-exchange-partners', array( $this->exchange_pages, 'render_exchange_partners_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Exchange Catalog', 'friend-product-links' ), __( 'Exchange Catalog', 'friend-product-links' ), $cap, 'fpl-exchange-catalog', array( $this->exchange_pages, 'render_exchange_catalog_page' ) );

		add_submenu_page( 'fpl-dashboard', __( 'Performance', 'friend-product-links' ), __( 'Performance', 'friend-product-links' ), $cap, 'fpl-performance', array( $this->pages, 'render_performance_page' ) );
		add_submenu_page( 'fpl-dashboard', __( 'Settings', 'friend-product-links' ), __( 'Settings', 'friend-product-links' ), $cap, 'fpl-settings', array( $this->pages, 'render_settings_page' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'fpl-' ) ) {
			return;
		}

		wp_enqueue_style( 'fpl-admin', FPL_URL . 'assets/admin.css', array(), FPL_VERSION );
		wp_enqueue_script( 'fpl-admin', FPL_URL . 'assets/admin.js', array(), FPL_VERSION, true );

		if ( FPL_Plugin::is_woocommerce_active() ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}
}
