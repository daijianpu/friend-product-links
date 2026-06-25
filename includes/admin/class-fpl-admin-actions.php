<?php
/**
 * Admin form action handlers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Admin_Actions {
	/**
	 * Handle form actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The action name is required to select the nonce action.
		if ( ! FPL_CPT::can_manage() || empty( $_POST['fpl_action'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified immediately after reading its action name.
		$action = sanitize_key( wp_unslash( $_POST['fpl_action'] ) );
		check_admin_referer( 'fpl_' . $action );

		switch ( $action ) {
			case 'save_share_link':
				$this->save_share_link();
				break;
			case 'delete_share_link':
				$this->delete_post_action( FPL_CPT::SHARE_LINK, 'fpl-product-links' );
				break;
			case 'reset_share_token':
				$this->reset_share_token();
				break;
			case 'toggle_status':
				$this->toggle_status();
				break;
			case 'save_friend':
				$this->save_friend();
				break;
			case 'sync_friend':
				$this->sync_friend();
				break;
			case 'delete_friend':
				$this->delete_post_action( FPL_CPT::FRIEND_FEED, 'fpl-friends' );
				break;
			case 'save_settings':
				$this->save_settings();
				break;
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- All handlers are dispatched only after check_admin_referer() succeeds above.

	/**
	 * Save share link.
	 *
	 * @return void
	 */
	private function save_share_link() {
		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$link_name   = isset( $_POST['link_name'] ) ? sanitize_text_field( wp_unslash( $_POST['link_name'] ) ) : '';
		$friend_name = isset( $_POST['friend_name'] ) ? sanitize_text_field( wp_unslash( $_POST['friend_name'] ) ) : '';
		$friend_url  = isset( $_POST['friend_site_url'] ) ? esc_url_raw( wp_unslash( $_POST['friend_site_url'] ) ) : '';
		$friend_url  = FPL_URL_Helper::normalize_url( $friend_url );
		$enabled     = isset( $_POST['enabled'] ) ? '1' : '0';
		$posted_product_ids = isset( $_POST['product_ids'] ) ? map_deep( wp_unslash( $_POST['product_ids'] ), 'sanitize_text_field' ) : array();
		$raw_product_ids    = FPL_Security::normalize_product_id_input( $posted_product_ids );
		$product_ids        = FPL_Security::filter_valid_product_ids( $raw_product_ids, FPL_CORE_PRODUCT_MAX + 1 );

		if ( count( $product_ids ) > FPL_CORE_PRODUCT_MAX ) {
			$this->redirect_with_message( 'fpl-product-links', 'share_too_many_products', $post_id ? array( 'edit_share_id' => $post_id ) : array() );
		}

		if ( count( $product_ids ) < FPL_CORE_PRODUCT_MIN ) {
			$message = count( $raw_product_ids ) >= FPL_CORE_PRODUCT_MIN ? 'share_invalid_products' : 'share_needs_products';
			$this->redirect_with_message( 'fpl-product-links', $message, $post_id ? array( 'edit_share_id' => $post_id ) : array() );
		}

		$site_valid = FPL_URL_Helper::validate_identity_http_url( $friend_url );
		if ( is_wp_error( $site_valid ) ) {
			$this->redirect_with_message( 'fpl-product-links', 'bad_friend_site', $post_id ? array( 'edit_share_id' => $post_id ) : array() );
		}

		$title = $link_name ? $link_name : $friend_name;
		if ( ! $title ) {
			$title = __( 'Friend Product Link', 'friend-product-links' );
		}

		$data = array(
			'post_type'   => FPL_CPT::SHARE_LINK,
			'post_status' => 'publish',
			'post_title'  => $title,
		);

		if ( $post_id && FPL_CPT::SHARE_LINK === get_post_type( $post_id ) ) {
			$data['ID'] = $post_id;
			$post_id    = wp_update_post( $data, true );
		} else {
			$post_id = wp_insert_post( $data, true );
			if ( ! is_wp_error( $post_id ) && $post_id ) {
				update_post_meta( $post_id, FPL_Repository::META_TOKEN, FPL_Security::generate_token() );
				update_post_meta( $post_id, FPL_Repository::META_CREATED_AT, current_time( 'mysql' ) );
			}
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->redirect_with_message( 'fpl-product-links', 'save_failed' );
		}

		update_post_meta( $post_id, FPL_Repository::META_ENABLED, $enabled );
		update_post_meta( $post_id, FPL_Repository::META_FRIEND_NAME, $friend_name );
		update_post_meta( $post_id, FPL_Repository::META_FRIEND_SITE_URL, $friend_url );
		FPL_Repository::save_share_link_products( $post_id, $product_ids );
		update_post_meta( $post_id, FPL_Repository::META_UPDATED_AT, current_time( 'mysql' ) );

		$this->redirect_with_message( 'fpl-product-links', 'saved' );
	}

	/**
	 * Save friend feed.
	 *
	 * @return void
	 */
	private function save_friend() {
		$post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$is_new_friend = ! $post_id;
		$friend_name = isset( $_POST['friend_name'] ) ? sanitize_text_field( wp_unslash( $_POST['friend_name'] ) ) : '';
		$friend_url  = isset( $_POST['friend_site_url'] ) ? esc_url_raw( wp_unslash( $_POST['friend_site_url'] ) ) : '';
		$feed_url    = isset( $_POST['feed_url'] ) ? esc_url_raw( wp_unslash( $_POST['feed_url'] ) ) : '';
		$wants_enable = isset( $_POST['enabled'] );
		$sort_order  = isset( $_POST['sort_order'] ) ? absint( $_POST['sort_order'] ) : 10;
		$note        = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$old_friend_url = $post_id ? FPL_Repository::get_friend_site_url( $post_id ) : '';
		$old_feed_url = $post_id ? FPL_Repository::get_friend_feed_url( $post_id ) : '';
		$url_changed = ! $post_id || FPL_URL_Helper::normalize_url( $old_friend_url ) !== FPL_URL_Helper::normalize_url( $friend_url ) || FPL_URL_Helper::normalize_url( $old_feed_url ) !== FPL_URL_Helper::normalize_url( $feed_url );

		if ( ! $post_id && FPL_CPT::count_friend_feeds() >= 3 ) {
			$this->redirect_with_message( 'fpl-friends', 'friend_limit' );
		}

		$boundary = FPL_URL_Helper::validate_friend_feed_boundary( $friend_url, $feed_url );
		if ( is_wp_error( $boundary ) ) {
			$this->redirect_with_message( 'fpl-friends', 'bad_boundary', $post_id ? array( 'edit_friend_id' => $post_id ) : array() );
		}

		$data = array(
			'post_type'   => FPL_CPT::FRIEND_FEED,
			'post_status' => 'publish',
			'post_title'  => $friend_name ? $friend_name : __( 'Friend Feed', 'friend-product-links' ),
		);

		if ( $post_id && FPL_CPT::FRIEND_FEED === get_post_type( $post_id ) ) {
			$data['ID'] = $post_id;
			$post_id    = wp_update_post( $data, true );
		} else {
			$post_id = wp_insert_post( $data, true );
			if ( ! is_wp_error( $post_id ) && $post_id ) {
				update_post_meta( $post_id, FPL_Repository::META_CREATED_AT, current_time( 'mysql' ) );
			}
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->redirect_with_message( 'fpl-friends', 'save_failed' );
		}

		update_post_meta( $post_id, FPL_Repository::META_FRIEND_NAME, $friend_name );
		update_post_meta( $post_id, FPL_Repository::META_FRIEND_SITE_URL, $friend_url );
		update_post_meta( $post_id, FPL_Repository::META_FEED_URL, $feed_url );
		update_post_meta( $post_id, FPL_Repository::META_SORT_ORDER, $sort_order );
		update_post_meta( $post_id, FPL_Repository::META_NOTE, $note );
		update_post_meta( $post_id, FPL_Repository::META_UPDATED_AT, current_time( 'mysql' ) );

		$message = 'saved';
		$synced = false;
		$sync_attempted = isset( $_POST['sync_now'] );
		if ( isset( $_POST['sync_now'] ) ) {
			$result = FPL_Feed_Fetcher::sync_friend_feed( $post_id );
			$synced = ! is_wp_error( $result );
			$message = $synced ? 'synced' : 'sync_failed';
		}

		if ( $url_changed && ! $synced ) {
			FPL_Repository::invalidate_friend_cache( $post_id );
			$enabled = '0';
			if ( $sync_attempted ) {
				$message = 'sync_failed';
			} elseif ( $is_new_friend ) {
				$message = 'friend_saved_needs_sync';
			} else {
				$message = 'friend_needs_resync';
			}
		} elseif ( $synced ) {
			$enabled = ( $wants_enable && FPL_Repository::friend_has_valid_cache( $post_id ) ) ? '1' : '0';
		} else {
			$enabled = ( $wants_enable && FPL_Repository::friend_has_valid_cache( $post_id ) ) ? '1' : '0';
		}
		update_post_meta( $post_id, FPL_Repository::META_ENABLED, $enabled );

		$this->redirect_with_message( 'fpl-friends', $message );
	}

	/**
	 * Reset share token.
	 *
	 * @return void
	 */
	private function reset_share_token() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id && FPL_CPT::SHARE_LINK === get_post_type( $post_id ) ) {
			update_post_meta( $post_id, FPL_Repository::META_TOKEN, FPL_Security::generate_token() );
			update_post_meta( $post_id, FPL_Repository::META_SHARE_STATS_KEY, FPL_Security::generate_token() );
			update_post_meta( $post_id, FPL_Repository::META_UPDATED_AT, current_time( 'mysql' ) );
		}

		$this->redirect_with_message( 'fpl-product-links', 'token_reset' );
	}

	/**
	 * Sync a friend feed.
	 *
	 * @return void
	 */
	private function sync_friend() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id && FPL_CPT::FRIEND_FEED === get_post_type( $post_id ) ) {
			$result = FPL_Feed_Fetcher::sync_friend_feed( $post_id );
			if ( is_wp_error( $result ) ) {
				$this->redirect_with_message( 'fpl-friends', 'sync_failed' );
			}

			$enabled = '1' === get_post_meta( $post_id, FPL_Repository::META_ENABLED, true );
			$this->redirect_with_message( 'fpl-friends', $enabled ? 'synced' : 'synced_but_disabled' );
		}

		$this->redirect_with_message( 'fpl-friends', 'sync_failed' );
	}

	/**
	 * Toggle share link or friend feed status.
	 *
	 * @return void
	 */
	private function toggle_status() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$status = isset( $_POST['desired_status'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['desired_status'] ) ) ? '1' : '0';
		$post_type = $post_id ? get_post_type( $post_id ) : '';

		if ( FPL_CPT::SHARE_LINK === $post_type ) {
			update_post_meta( $post_id, FPL_Repository::META_ENABLED, $status );
			$this->redirect_with_message( 'fpl-product-links', 'status_updated' );
		}

		if ( FPL_CPT::FRIEND_FEED === $post_type ) {
			if ( '1' === $status && ! $this->friend_has_valid_cache( $post_id ) ) {
				$this->redirect_with_message( 'fpl-friends', 'friend_needs_cache' );
			}
			update_post_meta( $post_id, FPL_Repository::META_ENABLED, $status );
			$this->redirect_with_message( 'fpl-friends', 'status_updated' );
		}
	}

	/**
	 * Delete storage post.
	 *
	 * @param string $post_type Post type.
	 * @param string $page Page slug.
	 * @return void
	 */
	private function delete_post_action( $post_type, $page ) {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id && $post_type === get_post_type( $post_id ) ) {
			if ( FPL_CPT::FRIEND_FEED === $post_type ) {
				FPL_Performance::delete_friend_stats( $post_id );
			}
			wp_delete_post( $post_id, true );
		}

		$this->redirect_with_message( $page, 'deleted' );
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	private function save_settings() {
		// Exchange Catalog Mode transition guard must run before saving ordinary options.
		// If the user tries to disable Exchange Mode with active partners, reject
		// the entire save to avoid partial save confusion.
		$wants_exchange   = isset( $_POST['exchange_mode_enabled'] );
		$current_exchange = FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled();

		if ( ! $wants_exchange && $current_exchange ) {
			$active_partners = FPL_CPT::get_exchange_partners( true );
			if ( ! empty( $active_partners ) ) {
				$this->redirect_with_message( 'fpl-settings', 'exchange_has_active_partners' );
			}
		}

		update_option( FPL_Repository::OPTION_AUTO_DISPLAY, isset( $_POST['auto_display'] ) ? 'yes' : 'no' );
		update_option( FPL_Repository::OPTION_FRONTEND_TITLE, isset( $_POST['frontend_title'] ) ? sanitize_text_field( wp_unslash( $_POST['frontend_title'] ) ) : '' );
		update_option( FPL_Repository::OPTION_BUTTON_TEXT, isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '' );
		update_option( FPL_Repository::OPTION_NEW_WINDOW, isset( $_POST['new_window'] ) ? 'yes' : 'no' );
		update_option( FPL_Repository::OPTION_SHOW_SOURCE, isset( $_POST['show_source'] ) ? 'yes' : 'no' );
		update_option( FPL_Repository::OPTION_SHOW_REMOTE_IMAGES, isset( $_POST['show_remote_images'] ) ? 'yes' : 'no' );
		update_option( FPL_Repository::OPTION_ENABLE_STATS, isset( $_POST['enable_stats'] ) ? 'yes' : 'no' );
		update_option( FPL_Repository::OPTION_SHARE_STATS, isset( $_POST['share_stats'] ) ? 'yes' : 'no' );
		update_option( FPL_Repository::OPTION_RECEIVE_STATS, isset( $_POST['receive_stats'] ) ? 'yes' : 'no' );

		// Exchange Catalog Mode transition.
		if ( $wants_exchange && ! $current_exchange ) {
			$result = FPL_Exchange_Catalog_Page_Manager::enable_exchange_mode();
			if ( is_wp_error( $result ) ) {
				$this->redirect_with_message( 'fpl-settings', 'save_failed' );
			}
		} elseif ( ! $wants_exchange && $current_exchange ) {
			FPL_Exchange_Catalog_Page_Manager::disable_exchange_mode();
		}

		$this->redirect_with_message( 'fpl-settings', 'saved' );
	}

	/**
	 * Check friend cache.
	 *
	 * @param int $post_id Friend post ID.
	 * @return bool
	 */
	private function friend_has_valid_cache( $post_id ) {
		return FPL_Repository::friend_has_valid_cache( $post_id );
	}

	/**
	 * Redirect after action.
	 *
	 * @param string $page Page slug.
	 * @param string $message Message key.
	 * @param array  $extra Extra query args.
	 * @return void
	 */
	private function redirect_with_message( $page, $message, $extra = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'fpl_message' => $message ), $extra ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
