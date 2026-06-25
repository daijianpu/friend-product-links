<?php
/**
 * Read-only admin dashboard.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Dashboard_Page extends FPL_Admin_View_Helper {
	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$data  = $this->get_dashboard_data();
		$items = $this->get_action_required_items( $data );
		?>
		<div class="wrap fpl-admin fpl-dashboard">
			<h1><?php esc_html_e( 'Friend Product Links Dashboard', 'friend-product-links' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p class="description"><?php esc_html_e( 'Quick overview of your core friends, exchange catalog, partners, and click performance.', 'friend-product-links' ); ?></p>

			<?php $this->render_status_cards( $data ); ?>
			<?php $this->render_action_required( $items ); ?>

			<div class="fpl-dashboard-two-col">
				<?php $this->render_core_summary( $data['core'] ); ?>
				<?php $this->render_exchange_summary( $data['exchange'] ); ?>
			</div>

			<?php $this->render_performance_summary( $data['performance'] ); ?>
			<?php $this->render_system_health( $data['system'] ); ?>
			<?php $this->render_quick_links( $data['exchange'] ); ?>
		</div>
		<?php
	}

	/**
	 * Collect dashboard data.
	 *
	 * @return array
	 */
	private function get_dashboard_data() {
		$data = array(
			'core'        => $this->get_core_summary(),
			'exchange'    => $this->get_exchange_summary(),
			'performance' => $this->get_performance_summary(),
			'system'      => $this->get_system_summary(),
		);

		return $data;
	}

	/**
	 * Get core link and friend summary.
	 *
	 * @return array
	 */
	private function get_core_summary() {
		$share_links     = FPL_CPT::get_share_links();
		$friends         = FPL_CPT::get_friend_feeds();
		$enabled_friends = FPL_CPT::get_friend_feeds( true );

		$enabled_share_links            = 0;
		$under_min_share_links          = 0;
		$total_selected_products        = 0;
		$remote_display_count_total     = 0;
		$remote_click_count_total       = 0;
		$last_remote_stats_received_at  = '';
		$total_valid_selected_products  = 0;
		$enabled_under_min_share_links  = 0;

		foreach ( $share_links as $link ) {
			$is_enabled = '1' === get_post_meta( $link->ID, FPL_Repository::META_ENABLED, true );
			$enabled_share_links += $is_enabled ? 1 : 0;

			$product_ids = get_post_meta( $link->ID, FPL_Repository::META_PRODUCTS, true );
			$product_ids = is_array( $product_ids ) ? array_values( array_filter( array_map( 'absint', $product_ids ) ) ) : array();
			$valid_product_ids = array();
			if ( FPL_Plugin::is_woocommerce_active() ) {
				$valid_product_ids = FPL_Security::filter_valid_product_ids( $product_ids, FPL_CORE_PRODUCT_MAX + 1 );
			}

			$total_selected_products += count( $product_ids );
			$total_valid_selected_products += count( $valid_product_ids );
			if ( count( $valid_product_ids ) < FPL_CORE_PRODUCT_MIN ) {
				$under_min_share_links++;
				if ( $is_enabled ) {
					$enabled_under_min_share_links++;
				}
			}

			$remote_display_count_total += absint( get_post_meta( $link->ID, FPL_Repository::META_REMOTE_DISPLAY_COUNT, true ) );
			$remote_click_count_total   += absint( get_post_meta( $link->ID, FPL_Repository::META_REMOTE_CLICK_COUNT, true ) );
			$last_remote_stats_received_at = $this->max_time( $last_remote_stats_received_at, get_post_meta( $link->ID, FPL_Repository::META_REMOTE_LAST_RECEIVED_TIME, true ) );
		}

		$cached_friends             = 0;
		$total_cached_products      = 0;
		$sync_success_count         = 0;
		$sync_error_count           = 0;
		$fail_count_total           = 0;
		$site_mismatch_count        = 0;
		$health_warning_count       = 0;
		$local_display_count_total  = 0;
		$local_click_count_total    = 0;
		$last_sync_time             = '';
		$last_stats_sent_time       = '';
		$stats_send_error_count     = 0;
		$enabled_cached_friends     = 0;
		$enabled_friends_without_cache = 0;
		$enabled_sync_error_count   = 0;
		$enabled_health_warning_count = 0;
		$enabled_site_mismatch_count = 0;
		$enabled_stats_send_error_count = 0;
		$stats_table_exists         = FPL_Stats_Repository::table_exists();

		foreach ( $friends as $friend ) {
			$products = FPL_Repository::get_cached_products( $friend->ID );
			$is_enabled = '1' === get_post_meta( $friend->ID, FPL_Repository::META_ENABLED, true );
			$has_valid_cache = FPL_Repository::friend_has_valid_cache( $friend->ID );
			if ( $has_valid_cache ) {
				$cached_friends++;
			}
			$total_cached_products += is_array( $products ) ? count( $products ) : 0;

			$status = get_post_meta( $friend->ID, FPL_Repository::META_SYNC_STATUS, true );
			if ( 'success' === $status ) {
				$sync_success_count++;
			}
			if ( get_post_meta( $friend->ID, FPL_Repository::META_SYNC_ERROR, true ) ) {
				$sync_error_count++;
			}

			$fail_count_total += absint( get_post_meta( $friend->ID, FPL_Repository::META_FAIL_COUNT, true ) );
			$site_mismatch_count += get_post_meta( $friend->ID, FPL_Repository::META_SITE_MISMATCH_WARNING, true ) ? 1 : 0;
			$health_warning_count += get_post_meta( $friend->ID, FPL_Repository::META_HEALTH_WARNING, true ) ? 1 : 0;

			if ( $is_enabled ) {
				$enabled_cached_friends += $has_valid_cache ? 1 : 0;
				$enabled_friends_without_cache += $has_valid_cache ? 0 : 1;
				$enabled_sync_error_count += get_post_meta( $friend->ID, FPL_Repository::META_SYNC_ERROR, true ) ? 1 : 0;
				$enabled_health_warning_count += get_post_meta( $friend->ID, FPL_Repository::META_HEALTH_WARNING, true ) ? 1 : 0;
				$enabled_site_mismatch_count += get_post_meta( $friend->ID, FPL_Repository::META_SITE_MISMATCH_WARNING, true ) ? 1 : 0;
				$enabled_stats_send_error_count += get_post_meta( $friend->ID, FPL_Repository::META_LAST_STATS_SENT_ERROR, true ) ? 1 : 0;
			}

			$friend_stats = $this->get_friend_stats_readonly( $friend->ID, $stats_table_exists );
			$local_display_count_total += absint( $friend_stats['displays'] );
			$local_click_count_total   += absint( $friend_stats['clicks'] );

			$last_sync_time       = $this->max_time( $last_sync_time, get_post_meta( $friend->ID, FPL_Repository::META_LAST_SYNCED_AT, true ) );
			$last_stats_sent_time = $this->max_time( $last_stats_sent_time, get_post_meta( $friend->ID, FPL_Repository::META_LAST_STATS_SENT_TIME, true ) );
			$stats_send_error_count += get_post_meta( $friend->ID, FPL_Repository::META_LAST_STATS_SENT_ERROR, true ) ? 1 : 0;
		}

		$total_share_links = count( $share_links );
		$total_friends     = count( $friends );

		return array(
			'total_share_links'           => $total_share_links,
			'enabled_share_links'         => $enabled_share_links,
			'disabled_share_links'        => max( 0, $total_share_links - $enabled_share_links ),
			'under_min_share_links'       => $under_min_share_links,
			'enabled_under_min_share_links' => $enabled_under_min_share_links,
			'total_selected_products'     => $total_selected_products,
			'total_valid_selected_products' => $total_valid_selected_products,
			'average_selected_products'   => $total_share_links ? $total_selected_products / $total_share_links : 0,
			'average_valid_selected_products' => $total_share_links ? $total_valid_selected_products / $total_share_links : 0,
			'remote_display_count_total'  => $remote_display_count_total,
			'remote_click_count_total'    => $remote_click_count_total,
			'remote_ctr'                  => $this->format_dashboard_ctr( $remote_click_count_total, $remote_display_count_total ),
			'last_remote_stats_received_time' => $last_remote_stats_received_at,
			'total_friends'               => $total_friends,
			'enabled_friends'             => count( $enabled_friends ),
			'disabled_friends'            => max( 0, $total_friends - count( $enabled_friends ) ),
			'cached_friends'              => $cached_friends,
			'enabled_cached_friends'      => $enabled_cached_friends,
			'friends_without_cache'       => max( 0, $total_friends - $cached_friends ),
			'enabled_friends_without_cache' => $enabled_friends_without_cache,
			'total_cached_products'       => $total_cached_products,
			'average_cached_products'     => $total_friends ? $total_cached_products / $total_friends : 0,
			'sync_success_count'          => $sync_success_count,
			'sync_error_count'            => $sync_error_count,
			'enabled_sync_error_count'    => $enabled_sync_error_count,
			'fail_count_total'            => $fail_count_total,
			'site_mismatch_count'         => $site_mismatch_count,
			'enabled_site_mismatch_count' => $enabled_site_mismatch_count,
			'health_warning_count'        => $health_warning_count,
			'enabled_health_warning_count' => $enabled_health_warning_count,
			'local_display_count_total'   => $local_display_count_total,
			'local_click_count_total'     => $local_click_count_total,
			'local_ctr'                   => $this->format_dashboard_ctr( $local_click_count_total, $local_display_count_total ),
			'last_sync_time'              => $last_sync_time,
			'last_stats_sent_time'        => $last_stats_sent_time,
			'stats_send_error_count'      => $stats_send_error_count,
			'enabled_stats_send_error_count' => $enabled_stats_send_error_count,
		);
	}

	/**
	 * Get exchange summary.
	 *
	 * @return array
	 */
	private function get_exchange_summary() {
		$offer_product_ids = FPL_Exchange_Repository::get_offer_product_ids();
		$valid_offer_product_ids = array();
		if ( FPL_Plugin::is_woocommerce_active() ) {
			$valid_offer_product_ids = FPL_Security::filter_valid_product_ids( $offer_product_ids, FPL_EXCHANGE_MAX_PRODUCTS + 1 );
		}

		$offer_enabled     = FPL_Exchange_Repository::is_offer_enabled();
		$offer_product_count = count( $offer_product_ids );
		$valid_offer_product_count = count( $valid_offer_product_ids );
		// Do not call FPL_Exchange_Repository::get_offer_url() here; it may generate and persist a token. Dashboard must remain read-only.
		$offer_token       = sanitize_text_field( get_option( FPL_Exchange_Repository::OPTION_TOKEN, '' ) );
		$offer_token_valid = FPL_Security::is_token( $offer_token );
		$offer_url         = $offer_token_valid ? rest_url( 'friend-product-links/v1/exchange/offer/' . rawurlencode( $offer_token ) ) : '';
		$offer_ready       = $offer_enabled && $offer_token_valid && $valid_offer_product_count >= FPL_EXCHANGE_MIN_PRODUCTS && $valid_offer_product_count <= FPL_EXCHANGE_MAX_PRODUCTS;

		$incoming = FPL_CPT::get_exchange_requests( 'incoming' );
		$outgoing = FPL_CPT::get_exchange_requests( 'outgoing' );
		$partners = FPL_CPT::get_exchange_partners( false );
		$active_partners = FPL_CPT::get_exchange_partners( true );

		$requests = array(
			'incoming_total'            => count( $incoming ),
			'outgoing_total'            => count( $outgoing ),
			'incoming_pending'          => 0,
			'outgoing_pending'          => 0,
			'approved_count'            => 0,
			'rejected_count'            => 0,
			'failed_count'              => 0,
			'requests_with_error_count' => 0,
		);

		$request_problem_count = 0;
		foreach ( array_merge( $incoming, $outgoing ) as $request ) {
			$status = FPL_Exchange_Repository::get_request_status( $request->ID );
			if ( 'pending' === $status ) {
				if ( 'incoming' === get_post_meta( $request->ID, FPL_Exchange_Repository::META_DIRECTION, true ) ) {
					$requests['incoming_pending']++;
				} else {
					$requests['outgoing_pending']++;
				}
			}
			if ( 'approved' === $status ) {
				$requests['approved_count']++;
			}
			if ( 'rejected' === $status ) {
				$requests['rejected_count']++;
			}
			if ( 'failed' === $status ) {
				$requests['failed_count']++;
			}
			if ( get_post_meta( $request->ID, FPL_Exchange_Repository::META_LAST_ERROR, true ) ) {
				$requests['requests_with_error_count']++;
			}
			if ( 'failed' === $status || get_post_meta( $request->ID, FPL_Exchange_Repository::META_LAST_ERROR, true ) ) {
				$request_problem_count++;
			}
		}

		$paused_partners                    = 0;
		$degraded_partners                  = 0;
		$pending_partners                   = 0;
		$partners_with_error                = 0;
		$partners_below_display_requirement = 0;
		$total_cached_products              = 0;
		$exchange_displays_sent_total       = 0;
		$exchange_clicks_sent_total         = 0;
		$last_partner_sync_at               = '';
		$last_partner_success_at            = '';

		foreach ( $partners as $partner ) {
			$status       = FPL_Exchange_Repository::get_partner_status( $partner->ID );
			$last_error   = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_LAST_ERROR, true );
			$products     = FPL_Exchange_Repository::get_partner_cached_products( $partner->ID );
			$product_count = is_array( $products ) ? count( $products ) : 0;

			$total_cached_products += $product_count;
			$paused_partners += 'paused_safety' === $status ? 1 : 0;
			$degraded_partners += 0 === strpos( $status, 'degraded_' ) ? 1 : 0;
			$pending_partners += false !== strpos( $status, 'pending' ) ? 1 : 0;
			$partners_with_error += ( 'active' !== $status || $last_error ) ? 1 : 0;
			$partners_below_display_requirement += ( 'active' === $status && $product_count < FPL_EXCHANGE_DISPLAY_PRODUCTS ) ? 1 : 0;

			$exchange_displays_sent_total += absint( get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_DISPLAYS_SENT, true ) );
			$exchange_clicks_sent_total   += absint( get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_CLICKS_SENT, true ) );
			$last_partner_sync_at          = $this->max_time( $last_partner_sync_at, get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT, true ) );
			$last_partner_success_at       = $this->max_time( $last_partner_success_at, get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_LAST_SUCCESS_AT, true ) );
		}

		return array_merge(
			array(
				'exchange_mode_enabled'              => FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled(),
				'catalog_ready'                     => FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready(),
				'catalog_page_id'                   => FPL_Exchange_Catalog_Page_Manager::get_catalog_page_id(),
				'catalog_page_url'                  => FPL_Exchange_Catalog_Page_Manager::get_catalog_page_url(),
				'offer_enabled'                     => $offer_enabled,
				'offer_product_count'               => $offer_product_count,
				'valid_offer_product_count'         => $valid_offer_product_count,
				'offer_token_valid'                 => $offer_token_valid,
				'offer_ready'                       => $offer_ready,
				'offer_url'                         => $offer_url,
				'total_partners'                    => count( $partners ),
				'active_partners'                   => count( $active_partners ),
				'paused_partners'                   => $paused_partners,
				'degraded_partners'                 => $degraded_partners,
				'pending_partners'                  => $pending_partners,
				'partners_with_error'               => $partners_with_error,
				'partners_below_display_requirement' => $partners_below_display_requirement,
				'total_cached_products'             => $total_cached_products,
				'exchange_displays_sent_total'      => $exchange_displays_sent_total,
				'exchange_clicks_sent_total'        => $exchange_clicks_sent_total,
				'exchange_ctr'                      => $this->format_dashboard_ctr( $exchange_clicks_sent_total, $exchange_displays_sent_total ),
				'last_partner_sync_at'              => $last_partner_sync_at,
				'last_partner_success_at'           => $last_partner_success_at,
				'request_problem_count'             => $request_problem_count,
			),
			$requests
		);
	}

	/**
	 * Get performance summary.
	 *
	 * @return array
	 */
	private function get_performance_summary() {
		return FPL_Performance_Report::get_report_readonly();
	}

	/**
	 * Get system summary.
	 *
	 * @return array
	 */
	private function get_system_summary() {
		$wc_version = '';
		if ( FPL_Plugin::is_woocommerce_active() && function_exists( 'WC' ) && WC() ) {
			$wc_version = WC()->version;
		}

		return array(
			'plugin_version'          => FPL_VERSION,
			'wordpress_version'       => get_bloginfo( 'version' ),
			'php_version'             => PHP_VERSION,
			'woocommerce_active'      => FPL_Plugin::is_woocommerce_active(),
			'woocommerce_version'     => $wc_version,
			'stats_table_ready'       => FPL_Stats_Repository::table_exists(),
			'click_table_version'     => get_option( FPL_Repository::OPTION_CLICK_TABLE_VERSION, '' ),
			'next_friend_feed_sync'   => wp_next_scheduled( FPL_Cron::HOOK ),
			'next_friend_stats_sync'  => wp_next_scheduled( FPL_Cron::STATS_HOOK ),
			'next_exchange_sync'      => wp_next_scheduled( FPL_Cron::EXCHANGE_HOOK ),
		);
	}

	/**
	 * Build action list from collected data.
	 *
	 * @param array $data Dashboard data.
	 * @return array
	 */
	private function get_action_required_items( $data ) {
		$items    = array();
		$core     = $data['core'];
		$exchange = $data['exchange'];
		$system   = $data['system'];

		if ( ! $system['woocommerce_active'] ) {
			$items[] = $this->action_item( 'error', __( 'WooCommerce is missing', 'friend-product-links' ), __( 'Product selection and rendering are paused until WooCommerce is active.', 'friend-product-links' ), admin_url( 'plugins.php' ), __( 'Go to Plugins', 'friend-product-links' ) );
		}
		if ( ! $system['stats_table_ready'] ) {
			$items[] = $this->action_item( 'error', __( 'Stats table missing', 'friend-product-links' ), __( 'Performance details are unavailable until the stats table exists.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-performance' ), __( 'View Performance', 'friend-product-links' ) );
		}
		if ( $exchange['exchange_mode_enabled'] && ! $exchange['catalog_ready'] ) {
			$items[] = $this->action_item( 'error', __( 'Exchange Catalog is not ready', 'friend-product-links' ), __( 'Exchange Catalog Mode is on, but the bound catalog page is not ready.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-catalog' ), __( 'Open Exchange Catalog', 'friend-product-links' ) );
		}
		if ( $exchange['offer_enabled'] && ! $exchange['offer_token_valid'] ) {
			$items[] = $this->action_item( 'error', __( 'Exchange Offer token is missing', 'friend-product-links' ), __( 'Your Exchange Offer is enabled, but its token is missing or invalid. Open Exchange My Offer and save it again.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-offer' ), __( 'Open Exchange Offer', 'friend-product-links' ) );
		}
		if ( $system['woocommerce_active'] && $exchange['offer_enabled'] && $exchange['valid_offer_product_count'] < FPL_EXCHANGE_MIN_PRODUCTS ) {
			$items[] = $this->action_item( 'error', __( 'Exchange Offer needs valid products', 'friend-product-links' ), __( 'Your enabled offer has fewer valid public WooCommerce products than the exchange minimum.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-offer' ), __( 'Set Up Offer', 'friend-product-links' ) );
		}
		if ( $system['woocommerce_active'] && $exchange['offer_enabled'] && $exchange['valid_offer_product_count'] > FPL_EXCHANGE_MAX_PRODUCTS ) {
			$items[] = $this->action_item( 'error', __( 'Exchange Offer has too many products', 'friend-product-links' ), __( 'Your enabled offer has more valid products than the exchange maximum.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-offer' ), __( 'Review Offer', 'friend-product-links' ) );
		}
		$has_exchange_setup_or_activity = (
			$exchange['exchange_mode_enabled']
			|| $exchange['offer_enabled']
			|| $exchange['total_partners'] > 0
			|| $exchange['incoming_total'] > 0
			|| $exchange['outgoing_total'] > 0
		);
		$has_core_setup_or_activity = ( $core['total_share_links'] > 0 || $core['total_friends'] > 0 );

		if ( ! $has_core_setup_or_activity && ! $has_exchange_setup_or_activity ) {
			$items[] = $this->action_item(
				'info',
				__( 'Choose a setup path', 'friend-product-links' ),
				__( 'Start with Core Product Links for trusted friends, or enable Exchange Catalog when you are ready to exchange with other stores.', 'friend-product-links' ),
				admin_url( 'admin.php?page=fpl-dashboard' ),
				__( 'Review Dashboard', 'friend-product-links' )
			);
		} else {
			if ( $has_core_setup_or_activity && 0 === $core['total_share_links'] ) {
				$items[] = $this->action_item( 'warning', __( 'No Core Product Links', 'friend-product-links' ), __( 'Create a core product link before sharing products with friends.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-product-links' ), __( 'Open Core Product Links', 'friend-product-links' ) );
			}

			if ( $has_core_setup_or_activity && 0 === $core['total_friends'] ) {
				$items[] = $this->action_item( 'warning', __( 'No Core Friends', 'friend-product-links' ), __( 'Add a friend feed to display friend products.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-friends' ), __( 'Manage Core Friends', 'friend-product-links' ) );
			}
		}
		if ( $core['enabled_under_min_share_links'] > 0 ) {
			$items[] = $this->action_item( 'warning', __( 'Enabled Core Product Links need valid products', 'friend-product-links' ), __( 'One or more enabled Core Product Links have fewer valid public WooCommerce products than the minimum.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-product-links' ), __( 'Review Core Product Links', 'friend-product-links' ) );
		}
		if ( $core['enabled_sync_error_count'] > 0 ) {
			$items[] = $this->action_item( 'warning', __( 'Core Friend sync errors', 'friend-product-links' ), __( 'One or more core friends have sync errors.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-friends' ), __( 'Review Friends', 'friend-product-links' ) );
		}
		if ( $core['enabled_friends_without_cache'] > 0 ) {
			$items[] = $this->action_item( 'warning', __( 'Core Friends without cache', 'friend-product-links' ), __( 'Some friends do not have a valid cached product preview.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-friends' ), __( 'Review Friends', 'friend-product-links' ) );
		}
		if ( $exchange['incoming_pending'] > 0 ) {
			$items[] = $this->action_item( 'warning', __( 'Pending incoming requests', 'friend-product-links' ), __( 'Incoming exchange requests are waiting for review.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-requests' ), __( 'Review Requests', 'friend-product-links' ) );
		}
		if ( $exchange['request_problem_count'] > 0 ) {
			$items[] = $this->action_item(
				'warning',
				__( 'Exchange requests need attention', 'friend-product-links' ),
				__( 'One or more exchange requests failed or have an error message.', 'friend-product-links' ),
				admin_url( 'admin.php?page=fpl-exchange-requests' ),
				__( 'Review Requests', 'friend-product-links' )
			);
		}
		if ( ( $exchange['degraded_partners'] + $exchange['paused_partners'] ) > 0 ) {
			$items[] = $this->action_item( 'warning', __( 'Partner health issues', 'friend-product-links' ), __( 'Some exchange partners are paused or degraded.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-partners' ), __( 'Review Partners', 'friend-product-links' ) );
		}
		if ( $exchange['partners_below_display_requirement'] > 0 ) {
			$items[] = $this->action_item( 'warning', __( 'Partner product count is low', 'friend-product-links' ), __( 'Some active partners have fewer cached products than the display count.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-partners' ), __( 'Review Partners', 'friend-product-links' ) );
		}
		if ( ! $system['next_friend_feed_sync'] || ! $system['next_friend_stats_sync'] || ! $system['next_exchange_sync'] ) {
			$items[] = $this->action_item( 'warning', __( 'Cron schedule missing', 'friend-product-links' ), __( 'One or more background schedules are missing.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-settings' ), __( 'Open Settings', 'friend-product-links' ) );
		}
		if ( $has_exchange_setup_or_activity && ! $exchange['exchange_mode_enabled'] ) {
			$items[] = $this->action_item( 'info', __( 'Exchange Mode is off', 'friend-product-links' ), __( 'Exchange Catalog is disabled, so exchange partners will not be displayed on the catalog page.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-settings' ), __( 'Open Settings', 'friend-product-links' ) );
		}
		if ( $exchange['exchange_mode_enabled'] && ! $exchange['offer_enabled'] ) {
			$items[] = $this->action_item( 'info', __( 'Exchange Offer is not enabled', 'friend-product-links' ), __( 'Your local exchange offer is not currently enabled.', 'friend-product-links' ), admin_url( 'admin.php?page=fpl-exchange-offer' ), __( 'Set Up Offer', 'friend-product-links' ) );
		}

		return $items;
	}

	/**
	 * Render quick status cards.
	 *
	 * @param array $data Dashboard data.
	 * @return void
	 */
	private function render_status_cards( $data ) {
		$core     = $data['core'];
		$exchange = $data['exchange'];
		$system   = $data['system'];

		$core_state = 'ready';
		$enabled_core_issue_count = $core['enabled_sync_error_count'] + $core['enabled_friends_without_cache'];
		$core_meta  = sprintf(
			/* translators: 1: enabled core friends, 2: total core friends, 3: enabled core friends with issues */
			__( 'Enabled %1$d / %2$d, enabled issues %3$d', 'friend-product-links' ),
			$core['enabled_friends'],
			$core['total_friends'],
			$enabled_core_issue_count
		);
		if ( 0 === $core['total_friends'] ) {
			$core_state = 'muted';
			$core_meta  = __( 'No core friends yet', 'friend-product-links' );
		} elseif ( 0 === $core['enabled_friends'] ) {
			$core_state = 'warning';
			$core_meta  = __( 'No enabled core friends', 'friend-product-links' );
		} elseif ( $core['enabled_sync_error_count'] > 0 || $core['enabled_friends_without_cache'] > 0 ) {
			$core_state = 'warning';
			$core_meta  = sprintf(
				/* translators: 1: enabled core friends with sync errors, 2: enabled core friends without valid cache */
				__( 'Enabled sync errors %1$d, enabled without cache %2$d', 'friend-product-links' ),
				$core['enabled_sync_error_count'],
				$core['enabled_friends_without_cache']
			);
		}

		$exchange_state = 'ready';
		$exchange_meta  = __( 'Mode, catalog, and offer are ready', 'friend-product-links' );
		if ( ! $exchange['exchange_mode_enabled'] ) {
			$exchange_state = 'muted';
			$exchange_meta  = __( 'Exchange Mode is off', 'friend-product-links' );
		} elseif ( ! $exchange['catalog_ready'] ) {
			$exchange_state = 'error';
			$exchange_meta  = __( 'Catalog page is not ready', 'friend-product-links' );
		} elseif ( ! $exchange['offer_ready'] ) {
			$exchange_state = 'warning';
			$exchange_meta  = __( 'Exchange offer is not ready', 'friend-product-links' );
		}

		$network_problem_count = $exchange['partners_with_error'] + $exchange['request_problem_count'] + $exchange['partners_below_display_requirement'];
		$network_state = 'ready';
		$network_meta  = sprintf(
			/* translators: 1: pending incoming exchange requests, 2: exchange network problems */
			__( 'Pending requests %1$d, problems %2$d', 'friend-product-links' ),
			$exchange['incoming_pending'],
			$network_problem_count
		);
		if ( 0 === $exchange['total_partners'] && 0 === $exchange['incoming_total'] && 0 === $exchange['outgoing_total'] ) {
			$network_state = 'muted';
			$network_meta  = __( 'No exchange activity yet', 'friend-product-links' );
		} elseif (
			$exchange['incoming_pending'] > 0
			|| $exchange['request_problem_count'] > 0
			|| $exchange['partners_with_error'] > 0
			|| $exchange['paused_partners'] > 0
			|| $exchange['degraded_partners'] > 0
	|| $exchange['partners_below_display_requirement'] > 0
		) {
			$network_state = 'warning';
		}
		?>
		<div class="fpl-dashboard-grid">
			<?php $this->render_status_card( __( 'WooCommerce', 'friend-product-links' ), $system['woocommerce_active'] ? __( 'Ready', 'friend-product-links' ) : __( 'Missing', 'friend-product-links' ), $system['woocommerce_active'] ? 'ready' : 'error', $system['woocommerce_version'] ? $system['woocommerce_version'] : __( 'Required for product workflows', 'friend-product-links' ) ); ?>
			<?php
			$core_metric = sprintf(
				/* translators: 1: enabled core friends, 2: total core friends */
				__( 'Enabled %1$d / %2$d', 'friend-product-links' ),
				$core['enabled_friends'],
				$core['total_friends']
			);
			$this->render_status_card( __( 'Core Friends', 'friend-product-links' ), $core_metric, $core_state, $core_meta );
			?>
			<?php $this->render_status_card( __( 'Exchange Catalog', 'friend-product-links' ), $exchange['exchange_mode_enabled'] ? __( 'Mode On', 'friend-product-links' ) : __( 'Mode Off', 'friend-product-links' ), $exchange_state, $exchange_meta ); ?>
			<?php
			$network_metric = sprintf(
				/* translators: %d: active exchange partners */
				__( 'Active %d', 'friend-product-links' ),
				$exchange['active_partners']
			);
			$this->render_status_card( __( 'Partners / Requests', 'friend-product-links' ), $network_metric, $network_state, $network_meta );
			?>
		</div>
		<?php
	}

	/**
	 * Render one card.
	 *
	 * @param string $title Card title.
	 * @param string $metric Main metric.
	 * @param string $state State key.
	 * @param string $meta Supporting text.
	 * @return void
	 */
	private function render_status_card( $title, $metric, $state, $meta ) {
		?>
		<div class="fpl-dashboard-card">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div class="fpl-dashboard-metric"><?php echo esc_html( $metric ); ?></div>
			<span class="fpl-status-pill is-<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $meta ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render action list.
	 *
	 * @param array $items Items.
	 * @return void
	 */
	private function render_action_required( $items ) {
		?>
		<div class="fpl-dashboard-section">
			<h2><?php esc_html_e( 'Action Required', 'friend-product-links' ); ?></h2>
			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'All clear. No immediate action required.', 'friend-product-links' ); ?></p>
			<?php else : ?>
				<ul class="fpl-action-list">
					<?php foreach ( $items as $item ) : ?>
						<li>
							<span class="fpl-status-pill is-<?php echo esc_attr( $item['severity'] ); ?>"><?php echo esc_html( ucfirst( $item['severity'] ) ); ?></span>
							<div>
								<strong><?php echo esc_html( $item['title'] ); ?></strong>
								<p class="fpl-dashboard-muted"><?php echo esc_html( $item['message'] ); ?></p>
								<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['action'] ); ?></a>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render core summary.
	 *
	 * @param array $core Core data.
	 * @return void
	 */
	private function render_core_summary( $core ) {
		?>
		<div class="fpl-dashboard-section">
			<h2><?php esc_html_e( 'Core Summary', 'friend-product-links' ); ?></h2>
			<?php $this->render_metric_list( array(
				__( 'Product links', 'friend-product-links' ) => sprintf( '%1$d total, %2$d enabled, %3$d enabled under minimum, %4$d total under minimum', $core['total_share_links'], $core['enabled_share_links'], $core['enabled_under_min_share_links'], $core['under_min_share_links'] ),
				__( 'Selected products', 'friend-product-links' ) => sprintf( '%1$d selected, %2$d valid, %3$s average selected', $core['total_selected_products'], $core['total_valid_selected_products'], number_format_i18n( $core['average_selected_products'], 1 ) ),
				__( 'Friend feeds', 'friend-product-links' ) => sprintf( '%1$d total, %2$d enabled, %3$d cached', $core['total_friends'], $core['enabled_friends'], $core['cached_friends'] ),
				__( 'Friend cache', 'friend-product-links' ) => sprintf( '%1$d products, %2$d without cache, %3$d enabled without cache', $core['total_cached_products'], $core['friends_without_cache'], $core['enabled_friends_without_cache'] ),
				__( 'Enabled friend health', 'friend-product-links' ) => sprintf( '%1$d enabled errors, %2$d enabled without cache, %3$d enabled warnings', $core['enabled_sync_error_count'], $core['enabled_friends_without_cache'], $core['enabled_health_warning_count'] ),
				__( 'Local performance', 'friend-product-links' ) => sprintf( '%1$d displays, %2$d clicks, %3$s CTR', $core['local_display_count_total'], $core['local_click_count_total'], $core['local_ctr'] ),
				__( 'Remote reported', 'friend-product-links' ) => sprintf( '%1$d displays, %2$d clicks, %3$s CTR', $core['remote_display_count_total'], $core['remote_click_count_total'], $core['remote_ctr'] ),
				__( 'Last sync', 'friend-product-links' ) => $this->format_time( $core['last_sync_time'] ),
				__( 'Last stats sent', 'friend-product-links' ) => $this->format_time( $core['last_stats_sent_time'] ),
				__( 'Last remote stats', 'friend-product-links' ) => $this->format_time( $core['last_remote_stats_received_time'] ),
			) ); ?>
		</div>
		<?php
	}

	/**
	 * Render exchange summary.
	 *
	 * @param array $exchange Exchange data.
	 * @return void
	 */
	private function render_exchange_summary( $exchange ) {
		?>
		<div class="fpl-dashboard-section">
			<h2><?php esc_html_e( 'Exchange Summary', 'friend-product-links' ); ?></h2>
			<?php $this->render_metric_list( array(
				__( 'Catalog mode', 'friend-product-links' ) => $exchange['exchange_mode_enabled'] ? __( 'On', 'friend-product-links' ) : __( 'Off', 'friend-product-links' ),
				__( 'Catalog page', 'friend-product-links' ) => $exchange['catalog_ready'] ? __( 'Ready', 'friend-product-links' ) : __( 'Not Ready', 'friend-product-links' ),
				__( 'Offer', 'friend-product-links' ) => sprintf( '%1$s, %2$d selected, %3$d valid', $exchange['offer_enabled'] ? __( 'Enabled', 'friend-product-links' ) : __( 'Disabled', 'friend-product-links' ), $exchange['offer_product_count'], $exchange['valid_offer_product_count'] ),
				__( 'Requests', 'friend-product-links' ) => sprintf( '%1$d incoming, %2$d outgoing, %3$d pending incoming', $exchange['incoming_total'], $exchange['outgoing_total'], $exchange['incoming_pending'] ),
				__( 'Request results', 'friend-product-links' ) => sprintf( '%1$d approved, %2$d rejected, %3$d failed', $exchange['approved_count'], $exchange['rejected_count'], $exchange['failed_count'] ),
				__( 'Partners', 'friend-product-links' ) => sprintf( '%1$d total, %2$d active, %3$d paused, %4$d degraded', $exchange['total_partners'], $exchange['active_partners'], $exchange['paused_partners'], $exchange['degraded_partners'] ),
				__( 'Partner cache', 'friend-product-links' ) => sprintf( '%1$d products, %2$d below display count', $exchange['total_cached_products'], $exchange['partners_below_display_requirement'] ),
				__( 'Exchange performance', 'friend-product-links' ) => sprintf( '%1$d displays sent, %2$d clicks sent, %3$s CTR', $exchange['exchange_displays_sent_total'], $exchange['exchange_clicks_sent_total'], $exchange['exchange_ctr'] ),
				__( 'Last partner sync', 'friend-product-links' ) => $this->format_time( $exchange['last_partner_sync_at'] ),
				__( 'Last partner success', 'friend-product-links' ) => $this->format_time( $exchange['last_partner_success_at'] ),
			) ); ?>
		</div>
		<?php
	}

	/**
	 * Render performance summary.
	 *
	 * @param array $performance Performance data.
	 * @return void
	 */
	private function render_performance_summary( $performance ) {
		?>
		<div class="fpl-dashboard-section">
			<h2><?php esc_html_e( 'Performance Summary', 'friend-product-links' ); ?></h2>
			<?php if ( empty( $performance['table_ready'] ) ) : ?>
				<p class="fpl-dashboard-muted"><?php esc_html_e( 'Stats table missing. Performance data is not available yet.', 'friend-product-links' ); ?></p>
			<?php else : ?>
				<div class="fpl-dashboard-grid">
					<?php $this->render_status_card( __( 'Today', 'friend-product-links' ), number_format_i18n( $performance['today_clicks'] ), 'ready', __( 'Clicks', 'friend-product-links' ) ); ?>
					<?php $this->render_status_card( __( 'Last 7 Days', 'friend-product-links' ), number_format_i18n( $performance['seven_clicks'] ), 'ready', __( 'Clicks', 'friend-product-links' ) ); ?>
					<?php $this->render_status_card( __( 'Last 30 Days', 'friend-product-links' ), number_format_i18n( $performance['thirty_clicks'] ), 'ready', __( 'Clicks', 'friend-product-links' ) ); ?>
					<?php $this->render_status_card( __( 'Stats Table', 'friend-product-links' ), __( 'Ready', 'friend-product-links' ), 'ready', __( 'Read-only report', 'friend-product-links' ) ); ?>
				</div>
				<div class="fpl-dashboard-two-col">
					<?php $this->render_top_rows( __( 'Top Friend Hosts', 'friend-product-links' ), $performance['top_friends'], 'friend_host' ); ?>
					<?php $this->render_top_rows( __( 'Top Products', 'friend-product-links' ), $performance['top_products'], 'product_hash' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render system health.
	 *
	 * @param array $system System data.
	 * @return void
	 */
	private function render_system_health( $system ) {
		?>
		<div class="fpl-dashboard-section">
			<h2><?php esc_html_e( 'System Health', 'friend-product-links' ); ?></h2>
			<?php $this->render_metric_list( array(
				__( 'Plugin version', 'friend-product-links' ) => $system['plugin_version'],
				__( 'WordPress version', 'friend-product-links' ) => $system['wordpress_version'],
				__( 'WooCommerce version', 'friend-product-links' ) => $system['woocommerce_active'] ? $system['woocommerce_version'] : __( 'Missing', 'friend-product-links' ),
				__( 'PHP version', 'friend-product-links' ) => $system['php_version'],
				__( 'Stats table', 'friend-product-links' ) => $system['stats_table_ready'] ? __( 'Ready', 'friend-product-links' ) : __( 'Missing', 'friend-product-links' ),
				__( 'Click table version', 'friend-product-links' ) => $system['click_table_version'] ? $system['click_table_version'] : '',
				__( 'Next friend feed sync', 'friend-product-links' ) => $this->format_time( $system['next_friend_feed_sync'] ),
				__( 'Next friend stats sync', 'friend-product-links' ) => $this->format_time( $system['next_friend_stats_sync'] ),
				__( 'Next exchange partner sync', 'friend-product-links' ) => $this->format_time( $system['next_exchange_sync'] ),
			) ); ?>
		</div>
		<?php
	}

	/**
	 * Render quick links.
	 *
	 * @param array $exchange Exchange data.
	 * @return void
	 */
	private function render_quick_links( $exchange ) {
		$catalog_url = ( ! empty( $exchange['catalog_ready'] ) && ! empty( $exchange['catalog_page_url'] ) ) ? $exchange['catalog_page_url'] : admin_url( 'admin.php?page=fpl-exchange-catalog' );
		$catalog_label = ( ! empty( $exchange['catalog_ready'] ) && ! empty( $exchange['catalog_page_url'] ) )
			? __( 'View Exchange Catalog', 'friend-product-links' )
			: __( 'Manage Exchange Catalog', 'friend-product-links' );
		$links = array(
			__( 'Dashboard', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-dashboard' ),
			__( 'Create Core Product Link', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-product-links' ),
			__( 'Manage Core Friends', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-friends' ),
			__( 'Set up Exchange Offer', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-exchange-offer' ),
			__( 'Start Exchange', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-start-exchange' ),
			__( 'Review Exchange Requests', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-exchange-requests' ),
			__( 'Manage Exchange Partners', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-exchange-partners' ),
			$catalog_label => $catalog_url,
			__( 'Performance', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-performance' ),
			__( 'Settings', 'friend-product-links' ) => admin_url( 'admin.php?page=fpl-settings' ),
		);
		?>
		<div class="fpl-dashboard-section">
			<h2><?php esc_html_e( 'Quick Links', 'friend-product-links' ); ?></h2>
			<p>
				<?php foreach ( $links as $label => $url ) : ?>
					<a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render a key/value list.
	 *
	 * @param array $rows Rows.
	 * @return void
	 */
	private function render_metric_list( $rows ) {
		?>
		<div class="fpl-table-wrap">
			<table class="widefat striped fpl-table">
				<tbody>
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td><?php $this->render_value_or_empty( $value ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render top rows.
	 *
	 * @param string $title Title.
	 * @param array  $rows Rows.
	 * @param string $name_key Name field.
	 * @return void
	 */
	private function render_top_rows( $title, $rows, $name_key ) {
		?>
		<div>
			<h3><?php echo esc_html( $title ); ?></h3>
			<div class="fpl-table-wrap">
				<table class="widefat striped fpl-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'friend-product-links' ); ?></th>
							<th><?php esc_html_e( 'Displays', 'friend-product-links' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'friend-product-links' ); ?></th>
							<th><?php esc_html_e( 'CTR', 'friend-product-links' ); ?></th>
							<th><?php esc_html_e( 'Last Click', 'friend-product-links' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No data yet.', 'friend-product-links' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$raw_name = sanitize_text_field( $row[ $name_key ] ?? '' );
			$name     = 'product_hash' === $name_key ? ( $raw_name ? substr( $raw_name, 0, 16 ) . '...' : '' ) : $raw_name;
							$displays = absint( $row['displays'] ?? 0 );
							$clicks   = absint( $row['clicks'] ?? 0 );
							?>
							<tr>
								<td><?php $this->render_value_or_empty( $name ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $displays ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $clicks ) ); ?></td>
								<td><?php echo esc_html( $this->format_dashboard_ctr( $clicks, $displays ) ); ?></td>
								<td><?php $this->render_value_or_empty( $this->format_time( $row['last_clicked_at'] ?? '' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Get one friend's aggregate stats without storage maintenance.
	 *
	 * @param int $friend_id Friend ID.
	 * @return array
	 */
	private function get_friend_stats_readonly( $friend_id, $stats_table_exists = null ) {
		global $wpdb;

		$friend_id = absint( $friend_id );
		$displays  = 0;
		$clicks    = 0;

		if ( null === $stats_table_exists ) {
			$stats_table_exists = FPL_Stats_Repository::table_exists();
		}

		if ( $stats_table_exists ) {
			$table_name = FPL_Stats_Repository::table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard diagnostics must reflect live aggregate stats.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COALESCE(SUM(displays), 0) AS displays, COALESCE(SUM(clicks), 0) AS clicks FROM %i WHERE friend_id = %d',
					$table_name,
					$friend_id
				),
				ARRAY_A
			);

			$displays = isset( $row['displays'] ) ? absint( $row['displays'] ) : 0;
			$clicks   = isset( $row['clicks'] ) ? absint( $row['clicks'] ) : 0;
		}

		if ( 0 === $displays ) {
			$displays = absint( get_post_meta( $friend_id, FPL_Repository::META_LOCAL_DISPLAY_COUNT, true ) );
		}
		if ( 0 === $clicks ) {
			$clicks = absint( get_post_meta( $friend_id, FPL_Repository::META_LOCAL_CLICK_COUNT, true ) );
		}

		return array(
			'displays' => $displays,
			'clicks'   => $clicks,
		);
	}

	/**
	 * Build one action item.
	 *
	 * @param string $severity Severity.
	 * @param string $title Title.
	 * @param string $message Message.
	 * @param string $url URL.
	 * @param string $action Action label.
	 * @return array
	 */
	private function action_item( $severity, $title, $message, $url, $action ) {
		return array(
			'severity' => $severity,
			'title'    => $title,
			'message'  => $message,
			'url'      => $url,
			'action'   => $action,
		);
	}

	/**
	 * Format CTR.
	 *
	 * @param int $clicks Clicks.
	 * @param int $displays Displays.
	 * @return string
	 */
	private function format_dashboard_ctr( $clicks, $displays ) {
		$displays = absint( $displays );
		if ( $displays <= 0 ) {
			return '0%';
		}

		return number_format_i18n( ( absint( $clicks ) / $displays ) * 100, 2 ) . '%';
	}

	/**
	 * Format timestamp or stored datetime.
	 *
	 * @param int|string $value Time value.
	 * @return string
	 */
	private function format_time( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( is_numeric( $value ) ) {
			return date_i18n( 'Y-m-d H:i:s', absint( $value ) );
		}

		$timestamp = strtotime( (string) $value );
		if ( $timestamp ) {
			return date_i18n( 'Y-m-d H:i:s', $timestamp );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Return the later stored datetime.
	 *
	 * @param string $current Current value.
	 * @param string $candidate Candidate value.
	 * @return string
	 */
	private function max_time( $current, $candidate ) {
		if ( ! $candidate ) {
			return $current;
		}
		if ( ! $current ) {
			return sanitize_text_field( $candidate );
		}

		return strtotime( $candidate ) > strtotime( $current ) ? sanitize_text_field( $candidate ) : sanitize_text_field( $current );
	}
}
