<?php
/**
 * Exchange admin action handlers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Admin_Actions {
	/**
	 * Handle exchange form actions.
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
			case 'save_exchange_offer':
				$this->save_exchange_offer();
				break;
			case 'send_exchange_request':
				$this->send_exchange_request();
				break;
			case 'approve_exchange_request':
				$this->approve_exchange_request();
				break;
			case 'reject_exchange_request':
				$this->reject_exchange_request();
				break;
			case 'create_catalog_page':
				$this->create_catalog_page();
				break;
			case 'emergency_pause_partner':
				$this->emergency_pause_partner();
				break;
			case 'sync_exchange_partner':
				$this->sync_exchange_partner();
				break;
			case 'repair_exchange_catalog_page':
				$this->repair_exchange_catalog_page();
				break;
			case 'resume_exchange_partner':
				$this->resume_exchange_partner();
				break;
			case 'delete_exchange_partner':
				$this->delete_exchange_partner();
				break;
		}
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- All handlers are dispatched only after check_admin_referer() succeeds above.

	/**
	 * Save the local exchange offer.
	 *
	 * @return void
	 */
	private function save_exchange_offer() {
		$enabled = isset( $_POST['enabled'] ) ? '1' : '0';

		if ( '1' === $enabled ) {
			// Only require catalog ready — NOT validate_local_offer() which reads old option.
			$catalog = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
			if ( is_wp_error( $catalog ) ) {
				$this->redirect_with_message( 'fpl-exchange-offer', 'exchange_offer_disabled' );
			}
		}

		$posted_ids  = isset( $_POST['exchange_product_ids'] ) ? map_deep( wp_unslash( $_POST['exchange_product_ids'] ), 'sanitize_text_field' ) : array();
		$raw_ids     = FPL_Security::normalize_product_id_input( $posted_ids );
		$product_ids = FPL_Security::filter_valid_product_ids( $raw_ids, FPL_EXCHANGE_MAX_PRODUCTS + 1 );

		if ( count( $product_ids ) > FPL_EXCHANGE_MAX_PRODUCTS ) {
			$this->redirect_with_message( 'fpl-exchange-offer', 'share_too_many_products' );
		}

		if ( $enabled && count( $product_ids ) < FPL_EXCHANGE_MIN_PRODUCTS ) {
			$message = count( $raw_ids ) >= FPL_EXCHANGE_MIN_PRODUCTS ? 'share_invalid_products' : 'share_needs_products';
			$this->redirect_with_message( 'fpl-exchange-offer', $message );
		}

		FPL_Exchange_Repository::set_offer_enabled( '1' === $enabled );
		FPL_Exchange_Repository::save_offer_product_ids( $product_ids );
		FPL_Exchange_Repository::touch_offer_updated_at();

		$this->redirect_with_message( 'fpl-exchange-offer', 'saved' );
	}

	/**
	 * Send an exchange request to a remote partner offer URL.
	 *
	 * @return void
	 */
	private function send_exchange_request() {
		// Require exchange mode, catalog, and local offer.
		$ready = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		if ( is_wp_error( $ready ) ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_offer_disabled' );
		}

		$validate = FPL_Exchange_Repository::validate_local_offer();
		if ( is_wp_error( $validate ) ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_offer_disabled' );
		}

		$partner_offer_url = isset( $_POST['partner_offer_url'] ) ? FPL_URL_Helper::normalize_url( esc_url_raw( wp_unslash( $_POST['partner_offer_url'] ) ) ) : '';
		if ( ! $partner_offer_url ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'bad_url' );
		}

		// Validate public http(s) before making any remote request.
		$offer_url_valid = FPL_URL_Helper::is_public_http_url( $partner_offer_url );
		if ( is_wp_error( $offer_url_valid ) ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'bad_url' );
		}

		// Block self-exchange.
		if ( FPL_URL_Helper::same_host( home_url( '/' ), $partner_offer_url ) ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_self_request' );
		}

		// Fetch remote offer.
		$remote = FPL_Exchange_Fetcher::fetch_remote_offer( $partner_offer_url );
		if ( is_wp_error( $remote ) ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_fetch_failed' );
		}

		if (
			FPL_URL_Helper::same_host( home_url( '/' ), $remote['site_url'] )
			|| FPL_URL_Helper::same_host( home_url( '/' ), $remote['offer_url'] )
		) {
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_self_request' );
		}

		// Check for duplicate request or existing partner.
		$duplicate = FPL_Exchange_Repository::has_active_or_pending_request( $remote['offer_url'] )
			|| FPL_Exchange_Repository::get_partner_by_offer_url( $remote['offer_url'] );

		if ( ! $duplicate && $partner_offer_url !== $remote['offer_url'] ) {
			$duplicate = FPL_Exchange_Repository::has_active_or_pending_request( $partner_offer_url )
				|| FPL_Exchange_Repository::get_partner_by_offer_url( $partner_offer_url );
		}

		if ( $duplicate ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_duplicate' );
		}

		// Generate outgoing request.
		$request_id    = FPL_Security::generate_token();
		$callback_token = FPL_Security::generate_token();

		$outgoing_id = FPL_Exchange_Repository::create_exchange_request(
			array(
				'direction'      => 'outgoing',
				'request_id'     => $request_id,
				'status'         => 'pending',
				'remote_site_name' => $remote['site_name'],
				'remote_site_url'  => $remote['site_url'],
				'remote_offer_url' => $remote['offer_url'],
				'remote_request_endpoint' => $remote['request_endpoint'],
				'remote_callback_url' => $remote['callback_endpoint'],
				'callback_token' => $callback_token,
				'remote_product_count' => $remote['product_count'],
			)
		);

		if ( is_wp_error( $outgoing_id ) ) {
			$this->redirect_with_message( 'fpl-start-exchange', 'save_failed' );
		}

		// POST to remote request endpoint.
		$payload = array(
			'request_id'       => $request_id,
			'from_site_name'   => get_bloginfo( 'name' ),
			'from_site_url'    => home_url( '/' ),
			'from_offer_url'   => FPL_Exchange_Repository::get_offer_url(),
			'from_callback_url' => rest_url( 'friend-product-links/v1/exchange/callback' ),
			'callback_token'   => $callback_token,
			'product_count'    => count( FPL_Security::filter_valid_product_ids( FPL_Exchange_Repository::get_offer_product_ids(), FPL_EXCHANGE_MAX_PRODUCTS ) ),
			'display_count'    => FPL_EXCHANGE_DISPLAY_PRODUCTS,
		);

		$response = wp_safe_remote_post(
			$remote['request_endpoint'],
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => 32768,
				'reject_unsafe_urls'  => true,
				'user-agent'          => 'Friend Product Links/' . FPL_VERSION . '; ' . home_url( '/' ),
				'headers'             => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'                => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			FPL_Exchange_Repository::update_exchange_request(
				$outgoing_id,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'failed',
					FPL_Exchange_Repository::META_LAST_ERROR => $response->get_error_message(),
				)
			);
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_send_failed' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['success'] ) || empty( $data['status'] ) || 'pending' !== $data['status'] ) {
			$error = sprintf( 'HTTP %d', $code );
			if ( is_array( $data ) && ! empty( $data['preview_error'] ) ) {
				$error = sanitize_text_field( $data['preview_error'] );
			} elseif ( is_array( $data ) && ! empty( $data['status'] ) ) {
				$error = sprintf( 'Remote status: %s', sanitize_text_field( $data['status'] ) );
			}

			FPL_Exchange_Repository::update_exchange_request(
				$outgoing_id,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'failed',
					FPL_Exchange_Repository::META_LAST_ERROR => $error,
				)
			);
			$this->redirect_with_message( 'fpl-start-exchange', 'exchange_send_failed' );
		}

		$this->redirect_with_message( 'fpl-start-exchange', 'exchange_request_sent' );
	}

	/**
	 * Approve an incoming exchange request with a two-step activation handshake.
	 *
	 * @return void
	 */
	private function approve_exchange_request() {
		// Check catalog and local offer are ready.
		$ready = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		if ( is_wp_error( $ready ) ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_offer_disabled' );
		}

		$local = FPL_Exchange_Repository::validate_local_offer();
		if ( is_wp_error( $local ) ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_offer_disabled' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$req     = get_post( $post_id );

		if ( ! $req || FPL_CPT::EXCHANGE_REQUEST !== $req->post_type ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
		}

		$direction = get_post_meta( $post_id, FPL_Exchange_Repository::META_DIRECTION, true );
		if ( 'incoming' !== $direction ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
		}

		$request_status = FPL_Exchange_Repository::get_request_status( $post_id );
		if ( ! in_array( $request_status, array( 'pending', 'failed' ), true ) ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
		}

		$offer_url      = FPL_Exchange_Repository::get_request_remote_offer_url( $post_id );
		$site_name      = get_post_meta( $post_id, FPL_Exchange_Repository::META_REMOTE_SITE_NAME, true );
		$site_url       = get_post_meta( $post_id, FPL_Exchange_Repository::META_REMOTE_SITE_URL, true );
		$callback_url   = FPL_Exchange_Repository::get_request_callback_url( $post_id );
		$callback_token = FPL_Exchange_Repository::get_request_callback_token( $post_id );
		$request_id     = get_post_meta( $post_id, FPL_Exchange_Repository::META_REQUEST_ID, true );

		// Fetch remote offer first, so local partner creation has clean products ready.
		$remote = FPL_Exchange_Fetcher::fetch_remote_offer( $offer_url );
		if ( is_wp_error( $remote ) ) {
			FPL_Exchange_Repository::update_exchange_request(
				$post_id,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'failed',
					FPL_Exchange_Repository::META_LAST_ERROR => $remote->get_error_message(),
				)
			);
			$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_fetch_failed' );
		}

		$partner_id = 0;
		$created_partner = false;
		$canonical_offer_url = ! empty( $remote['offer_url'] ) ? $remote['offer_url'] : $offer_url;
		$existing   = FPL_Exchange_Repository::get_partner_by_offer_url( $canonical_offer_url );
		if ( ! $existing && $offer_url !== $canonical_offer_url ) {
			$existing = FPL_Exchange_Repository::get_partner_by_offer_url( $offer_url );
		}
		if ( $existing ) {
			$partner_id = $existing->ID;
			$status     = FPL_Exchange_Repository::get_partner_status( $partner_id );

			if ( 'paused_safety' === $status ) {
				FPL_Exchange_Repository::update_exchange_request(
					$post_id,
					array(
						FPL_Exchange_Repository::META_STATUS     => 'failed',
						FPL_Exchange_Repository::META_LAST_ERROR => __( 'This partner is currently paused for safety reasons.', 'friend-product-links' ),
					)
				);
				$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_duplicate' );
			}

			// Do not update cached_products or success timestamps before callback.
			// If callback fails, existing partner should retain its previous state.
			// Successful callback handles the full partner update below.
		} else {
			// Create local partner before callback as pending. This avoids a one-sided
			// exchange if the remote callback succeeds but local partner creation fails.
			$partner_id = FPL_Exchange_Repository::create_exchange_partner(
				array(
					'site_name'       => $remote['site_name'] ?: $site_name,
					'site_url'        => $remote['site_url'] ?: $site_url,
					'offer_url'       => $remote['offer_url'],
					'status'          => 'pending_activation',
					'cached_products' => $remote['products'],
				)
			);

			if ( is_wp_error( $partner_id ) ) {
				FPL_Exchange_Repository::update_exchange_request(
					$post_id,
					array(
						FPL_Exchange_Repository::META_STATUS     => 'failed',
						FPL_Exchange_Repository::META_LAST_ERROR => $partner_id->get_error_message(),
					)
				);

				if ( 'fpl_duplicate_partner' === $partner_id->get_error_code() ) {
					$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_duplicate' );
				}

				if ( 'fpl_invalid_partner_offer_url' === $partner_id->get_error_code() ) {
					$this->redirect_with_message( 'fpl-exchange-requests', 'bad_url' );
				}

				$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
			}

			$created_partner = true;
		}

		// Defensive same-host check before sending callback.
		if ( $offer_url && ! FPL_URL_Helper::same_host( $offer_url, $callback_url ) ) {
			FPL_Exchange_Repository::update_exchange_request(
				$post_id,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'failed',
					FPL_Exchange_Repository::META_LAST_ERROR => __( 'Callback URL host mismatch with offer URL.', 'friend-product-links' ),
				)
			);
			$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_callback_failed' );
		}

		$callback_result = $this->send_exchange_decision_callback( $callback_url, $callback_token, $request_id, 'approved', $offer_url );
		if ( is_wp_error( $callback_result ) ) {
			FPL_Exchange_Repository::update_exchange_request(
				$post_id,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'failed',
					FPL_Exchange_Repository::META_LAST_ERROR => $callback_result->get_error_message(),
				)
			);

			$partner_update = array(
				FPL_Exchange_Repository::META_PARTNER_LAST_ERROR => $callback_result->get_error_message(),
			);
			if ( $created_partner ) {
				$partner_update[ FPL_Exchange_Repository::META_PARTNER_STATUS ] = 'pending_activation';
			}

			FPL_Exchange_Repository::update_partner( $partner_id, $partner_update );

			$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_callback_failed' );
		}

		FPL_Exchange_Repository::update_partner(
			$partner_id,
			array(
				FPL_Exchange_Repository::META_PARTNER_SITE_NAME       => $remote['site_name'],
				FPL_Exchange_Repository::META_PARTNER_SITE_URL        => $remote['site_url'],
				FPL_Exchange_Repository::META_PARTNER_OFFER_URL       => $remote['offer_url'],
				FPL_Exchange_Repository::META_PARTNER_STATUS          => 'active',
				FPL_Exchange_Repository::META_PARTNER_CACHED_PRODUCTS => $remote['products'],
				FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT    => current_time( 'mysql' ),
				FPL_Exchange_Repository::META_PARTNER_LAST_SUCCESS_AT => current_time( 'mysql' ),
				FPL_Exchange_Repository::META_PARTNER_LAST_ERROR      => '',
			)
		);

		FPL_Exchange_Repository::update_exchange_request(
			$post_id,
			array(
				FPL_Exchange_Repository::META_STATUS     => 'approved',
				FPL_Exchange_Repository::META_LAST_ERROR => '',
			)
		);

		$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_partner_created' );
	}

	/**
	 * Reject an incoming exchange request.
	 *
	 * @return void
	 */
	private function reject_exchange_request() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$req     = get_post( $post_id );

		if ( ! $req || FPL_CPT::EXCHANGE_REQUEST !== $req->post_type ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
		}

		$direction = get_post_meta( $post_id, FPL_Exchange_Repository::META_DIRECTION, true );
		if ( 'incoming' !== $direction ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
		}

		$request_status = FPL_Exchange_Repository::get_request_status( $post_id );
		if ( ! in_array( $request_status, array( 'pending', 'failed' ), true ) ) {
			$this->redirect_with_message( 'fpl-exchange-requests', 'save_failed' );
		}

		$callback_url   = FPL_Exchange_Repository::get_request_callback_url( $post_id );
		$callback_token = FPL_Exchange_Repository::get_request_callback_token( $post_id );
		$request_id     = get_post_meta( $post_id, FPL_Exchange_Repository::META_REQUEST_ID, true );

		FPL_Exchange_Repository::update_exchange_request(
			$post_id,
			array(
				FPL_Exchange_Repository::META_STATUS     => 'rejected',
				FPL_Exchange_Repository::META_LAST_ERROR => '',
			)
		);

		// Best-effort callback for rejection. Do not reactivate or create a partner.
		// Defensive same-host check before sending callback.
		$req_offer_url = FPL_Exchange_Repository::get_request_remote_offer_url( $post_id );
		if ( $req_offer_url && ! FPL_URL_Helper::same_host( $req_offer_url, $callback_url ) ) {
			// Host mismatch — skip remote callback entirely.
			$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_request_rejected' );
		}

		$this->send_exchange_decision_callback( $callback_url, $callback_token, $request_id, 'rejected', $req_offer_url );

		$this->redirect_with_message( 'fpl-exchange-requests', 'exchange_request_rejected' );
	}

	/**
	 * Send an approved/rejected callback to the requester.
	 *
	 * @param string $callback_url Callback URL.
	 * @param string $callback_token Shared callback token.
	 * @param string $request_id Request ID.
	 * @param string $decision approved|rejected.
	 * @return true|WP_Error
	 */
	private function send_exchange_decision_callback( $callback_url, $callback_token, $request_id, $decision, $expected_offer_url = '' ) {
		if ( ! $callback_url || ! $callback_token ) {
			return new WP_Error( 'fpl_missing_callback', __( 'Callback URL or token missing.', 'friend-product-links' ) );
		}

		if ( ! FPL_Security::is_token( $request_id ) || ! FPL_Security::is_token( $callback_token ) ) {
			return new WP_Error(
				'fpl_invalid_callback_token',
				__( 'Invalid callback token.', 'friend-product-links' )
			);
		}

		$callback_valid = FPL_URL_Helper::is_public_http_url( $callback_url );

		// Defensive same-host check inside the callback sender itself.
		if ( $expected_offer_url && ! FPL_URL_Helper::same_host( $expected_offer_url, $callback_url ) ) {
			return new WP_Error(
				'fpl_callback_host_mismatch',
				__( 'Callback URL host mismatch with offer URL.', 'friend-product-links' )
			);
		}
		if ( is_wp_error( $callback_valid ) ) {
			return $callback_valid;
		}

		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'fpl_bad_decision', __( 'Invalid callback decision.', 'friend-product-links' ) );
		}

		$payload = array(
			'request_id'     => sanitize_text_field( $request_id ),
			'decision'       => $decision,
			'from_site_name' => get_bloginfo( 'name' ),
			'from_site_url'  => home_url( '/' ),
			'from_offer_url' => FPL_Exchange_Repository::get_offer_url(),
			'message'        => '',
		);

		$body = wp_json_encode( $payload );
		$response = wp_safe_remote_post(
			$callback_url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'limit_response_size' => 32768,
				'reject_unsafe_urls'  => true,
				'user-agent'          => 'Friend Product Links/' . FPL_VERSION . '; ' . home_url( '/' ),
				'headers'             => array(
					'Content-Type'             => 'application/json',
					'Accept'                   => 'application/json',
					'X-FPL-Exchange-Signature' => hash_hmac( 'sha256', $body, $callback_token ),
				),
				'body'                => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'fpl_callback_http_error', sprintf( 'Callback HTTP %d', $code ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) || empty( $data['status'] ) || $decision !== $data['status'] ) {
			return new WP_Error( 'fpl_callback_unexpected', __( 'Callback returned unexpected response.', 'friend-product-links' ) );
		}

		return true;
	}

	/**
	 * Create or update the Exchange Catalog page.
	 *
	 * @return void
	 */
	private function create_catalog_page() {
		if ( ! FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled() ) {
			$this->redirect_with_message( 'fpl-exchange-catalog', 'exchange_mode_disabled' );
		}

		$result = FPL_Exchange_Catalog_Page_Manager::repair_catalog_page();
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'fpl-exchange-catalog', 'save_failed' );
		}

		$this->redirect_with_message( 'fpl-exchange-catalog', 'saved' );
	}

	/**
	 * Emergency pause an active exchange partner.
	 *
	 * @return void
	 */
	private function emergency_pause_partner() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$reason  = isset( $_POST['pause_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pause_reason'] ) ) : '';

		if ( ! $post_id || ! $reason ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'pause_reason_required' );
		}

		$post = get_post( $post_id );
		if ( ! $post || FPL_CPT::EXCHANGE_PARTNER !== $post->post_type ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'save_failed' );
		}

		FPL_Exchange_Repository::update_partner(
			$post_id,
			array(
				FPL_Exchange_Repository::META_PARTNER_STATUS       => 'paused_safety',
				FPL_Exchange_Repository::META_PARTNER_PAUSE_REASON => $reason,
				FPL_Exchange_Repository::META_PARTNER_PAUSED_AT    => current_time( 'mysql' ),
			)
		);

		$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_paused' );
	}

	/**
	 * Resume a paused exchange partner.
	 *
	 * @return void
	 */
	private function resume_exchange_partner() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post || FPL_CPT::EXCHANGE_PARTNER !== $post->post_type ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'save_failed' );
		}

		$status = FPL_Exchange_Repository::get_partner_status( $post_id );
		if ( 'paused_safety' !== $status ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'save_failed' );
		}

		// Set to degraded_remote_offer first, then re-sync to validate remote offer.
		FPL_Exchange_Repository::update_partner(
			$post_id,
			array(
				FPL_Exchange_Repository::META_PARTNER_STATUS       => 'degraded_remote_offer',
				FPL_Exchange_Repository::META_PARTNER_PAUSE_REASON => '',
				FPL_Exchange_Repository::META_PARTNER_PAUSED_AT    => '',
				FPL_Exchange_Repository::META_PARTNER_LAST_ERROR   => '',
			)
		);

		$result = FPL_Exchange_Fetcher::sync_partner( $post_id );

		if ( is_wp_error( $result ) ) {
			FPL_Exchange_Repository::update_partner(
				$post_id,
				array(
					FPL_Exchange_Repository::META_PARTNER_STATUS       => 'degraded_remote_offer',
					FPL_Exchange_Repository::META_PARTNER_LAST_ERROR   => $result->get_error_message(),
					FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT => current_time( 'mysql' ),
				)
			);

			$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_sync_degraded' );
		}

		$after_status = FPL_Exchange_Repository::get_partner_status( $post_id );
		$last_error   = get_post_meta( $post_id, FPL_Exchange_Repository::META_PARTNER_LAST_ERROR, true );

		if ( 'active' !== $after_status || ! empty( $last_error ) ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_sync_degraded' );
		}

		$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_resumed' );
	}

	/**
	 * Delete an exchange partner.
	 *
	 * @return void
	 */
	private function delete_exchange_partner() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post || FPL_CPT::EXCHANGE_PARTNER !== $post->post_type ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'save_failed' );
		}

		wp_delete_post( $post_id, true );
		$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_deleted' );
	}

	/**
	 * Sync an exchange partner's cached products.
	 *
	 * @return void
	 */
	private function sync_exchange_partner() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post || FPL_CPT::EXCHANGE_PARTNER !== $post->post_type ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'save_failed' );
		}

		$status = FPL_Exchange_Repository::get_partner_status( $post_id );
		if ( ! in_array( $status, array( 'active', 'degraded_missing_catalog', 'degraded_remote_offer' ), true ) ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_sync_skipped' );
		}

		$result = FPL_Exchange_Fetcher::sync_partner( $post_id );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_fetch_failed' );
		}

		$after_status = FPL_Exchange_Repository::get_partner_status( $post_id );
		$last_error_check = get_post_meta( $post_id, FPL_Exchange_Repository::META_PARTNER_LAST_ERROR, true );

		if ( 'active' !== $after_status || ! empty( $last_error_check ) ) {
			$this->redirect_with_message( 'fpl-exchange-partners', 'exchange_partner_sync_degraded' );
		}

		$this->redirect_with_message( 'fpl-exchange-partners', 'synced' );
	}

	/**
	 * Repair the Exchange Catalog page.
	 *
	 * @return void
	 */
	private function repair_exchange_catalog_page() {
		if ( ! FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled() ) {
			$this->redirect_with_message( 'fpl-settings', 'exchange_offer_disabled' );
		}

		$result = FPL_Exchange_Catalog_Page_Manager::repair_catalog_page();
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_message( 'fpl-settings', 'save_failed' );
		}

		$this->redirect_with_message( 'fpl-settings', 'exchange_catalog_repaired' );
	}

	/**
	 * Require exchange catalog ready before proceeding.
	 *
	 * @return void
	 */
	private function require_exchange_ready() {
		$ready = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		if ( is_wp_error( $ready ) ) {
			$this->redirect_with_message( 'fpl-exchange-offer', 'exchange_offer_disabled' );
		}

		$offer = FPL_Exchange_Repository::validate_local_offer();
		if ( is_wp_error( $offer ) ) {
			$this->redirect_with_message( 'fpl-exchange-offer', 'exchange_offer_disabled' );
		}
	}

	/**
	 * Redirect after action.
	 *
	 * @param string $page    Page slug.
	 * @param string $message Message key.
	 * @param array  $extra   Extra query args.
	 * @return void
	 */
	private function redirect_with_message( $page, $message, $extra = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'fpl_message' => $message ), $extra ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
