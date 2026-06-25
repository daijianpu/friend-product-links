<?php
/**
 * Exchange Catalog Mode and Catalog page manager.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Catalog_Page_Manager {
	/**
	 * Whether Exchange Catalog Mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_exchange_mode_enabled() {
		return 'yes' === get_option( FPL_EXCHANGE_MODE_OPTION, 'no' );
	}

	/**
	 * Enable Exchange Catalog Mode.
	 *
	 * Creates or repairs the catalog page before enabling.
	 *
	 * @return true|WP_Error
	 */
	public static function enable_exchange_mode() {
		$page_id = self::create_catalog_page();
		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		update_option( FPL_EXCHANGE_MODE_OPTION, 'yes', false );

		return true;
	}

	/**
	 * Disable Exchange Catalog Mode.
	 *
	 * Does NOT delete the page or partners. Only blocks action entry points.
	 *
	 * @return void
	 */
	public static function disable_exchange_mode() {
		update_option( FPL_EXCHANGE_MODE_OPTION, 'no', false );
	}

	/**
	 * Get the stored catalog page ID.
	 *
	 * @return int
	 */
	public static function get_catalog_page_id() {
		return absint( get_option( FPL_EXCHANGE_CATALOG_PAGE_OPTION, 0 ) );
	}

	/**
	 * Get the catalog page URL.
	 *
	 * @return string
	 */
	public static function get_catalog_page_url() {
		$page_id = self::get_catalog_page_id();
		if ( ! $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}

	/**
	 * Whether the catalog page is fully ready.
	 *
	 * @return bool
	 */
	public static function is_catalog_page_ready() {
		$page_id = self::get_catalog_page_id();
		if ( ! $page_id ) {
			return false;
		}

		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		if ( false === strpos( $post->post_content, FPL_EXCHANGE_CATALOG_SHORTCODE ) ) {
			return false;
		}

		if ( ! get_permalink( $page_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get a human-readable catalog page status.
	 *
	 * @return string
	 */
	public static function get_catalog_page_status_text() {
		$page_id = self::get_catalog_page_id();
		if ( ! $page_id ) {
			return __( 'Missing', 'friend-product-links' );
		}

		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return __( 'Invalid (deleted or wrong type)', 'friend-product-links' );
		}

		if ( 'trash' === $post->post_status ) {
			return __( 'In Trash', 'friend-product-links' );
		}

		if ( 'publish' !== $post->post_status ) {
			return __( 'Not published', 'friend-product-links' );
		}

		if ( false === strpos( $post->post_content, FPL_EXCHANGE_CATALOG_SHORTCODE ) ) {
			return __( 'Missing shortcode', 'friend-product-links' );
		}

		return __( 'Ready', 'friend-product-links' );
	}

	/**
	 * Create or reuse the Exchange Catalog page.
	 *
	 * @return int|WP_Error Page ID.
	 */
	public static function create_catalog_page() {
		$page_id = self::get_catalog_page_id();

		// If stored ID points to a valid page, repair it.
		if ( $page_id ) {
			$result = self::repair_catalog_page();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return self::get_catalog_page_id();
		}

		// Check if a page with the slug already exists.
		$existing = get_page_by_path( 'exchange-catalog', OBJECT, 'page' );
		if ( $existing ) {
			update_option( FPL_EXCHANGE_CATALOG_PAGE_OPTION, $existing->ID, false );
			$result = self::repair_catalog_page();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $existing->ID;
		}

		// Create new page.
		$new_id = wp_insert_post(
			array(
				'post_title'   => __( 'Exchange Catalog', 'friend-product-links' ),
				'post_name'    => 'exchange-catalog',
				'post_content' => FPL_EXCHANGE_CATALOG_SHORTCODE,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return new WP_Error( 'fpl_catalog_page_create_failed', __( 'Failed to create Exchange Catalog page.', 'friend-product-links' ) );
		}

		update_option( FPL_EXCHANGE_CATALOG_PAGE_OPTION, $new_id, false );

		return $new_id;
	}

	/**
	 * Repair the Exchange Catalog page.
	 *
	 * @return true|WP_Error
	 */
	public static function repair_catalog_page() {
		$page_id = self::get_catalog_page_id();
		if ( ! $page_id ) {
			$new_id = self::create_catalog_page();
			if ( is_wp_error( $new_id ) ) {
				return $new_id;
			}
			return true;
		}

		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			// Stored ID is invalid; reset and create fresh.
			delete_option( FPL_EXCHANGE_CATALOG_PAGE_OPTION );
			$new_id = self::create_catalog_page();
			if ( is_wp_error( $new_id ) ) {
				return $new_id;
			}
			return true;
		}

		$updates = array( 'ID' => $page_id );

		// Restore from trash.
		if ( 'trash' === $post->post_status ) {
			$updates['post_status'] = 'publish';
		} elseif ( 'publish' !== $post->post_status ) {
			$updates['post_status'] = 'publish';
		}

		// Ensure shortcode is present.
		if ( false === strpos( $post->post_content, FPL_EXCHANGE_CATALOG_SHORTCODE ) ) {
			$updates['post_content'] = $post->post_content . "\n\n" . FPL_EXCHANGE_CATALOG_SHORTCODE;
		}

		if ( count( $updates ) > 1 ) {
			$result = wp_update_post( $updates, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Require catalog ready; return error if not.
	 *
	 * @return true|WP_Error
	 */
	public static function require_catalog_ready_or_error() {
		if ( ! self::is_exchange_mode_enabled() ) {
			return new WP_Error(
				'fpl_exchange_mode_disabled',
				__( 'Exchange Catalog Mode is not enabled.', 'friend-product-links' )
			);
		}

		if ( ! self::is_catalog_page_ready() ) {
			return new WP_Error(
				'fpl_catalog_not_ready',
				__( 'Exchange Catalog page is missing or invalid. Please repair it before using Exchange Catalog.', 'friend-product-links' )
			);
		}

		return true;
	}
}