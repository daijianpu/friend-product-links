<?php
/**
 * Exchange REST endpoints.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_REST {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'friend-product-links/v1',
			'/exchange/offer/(?P<token>[A-Za-z0-9_-]{32,128})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_offer' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'friend-product-links/v1',
			'/exchange/request',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'friend-product-links/v1',
			'/exchange/callback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive_callback' ),
				'permission_callback' => '__return_true',
			)
		);

		// /exchange/stats disabled in v1.1.1 — no auth mechanism yet.
	}

	/**
	 * Return the public exchange offer.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_offer( WP_REST_Request $request ) {
		$token = sanitize_text_field( $request['token'] );

		if ( $token !== FPL_Exchange_Repository::get_offer_token() || ! FPL_Exchange_Repository::is_offer_enabled() ) {
			return new WP_Error(
				'fpl_offer_not_found',
				__( 'Exchange offer not found.', 'friend-product-links' ),
				array( 'status' => 404 )
			);
		}

		// Catalog must be ready to serve offers.
		$catalog = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		if ( is_wp_error( $catalog ) ) {
			return new WP_Error(
				'fpl_catalog_not_ready',
				__( 'Exchange Catalog page is not ready.', 'friend-product-links' ),
				array( 'status' => 409 )
			);
		}

		$catalog_url = FPL_Exchange_Catalog_Page_Manager::get_catalog_page_url();
		if ( ! $catalog_url ) {
			return new WP_Error(
				'fpl_catalog_not_ready',
				__( 'Exchange Catalog page is not ready.', 'friend-product-links' ),
				array( 'status' => 409 )
			);
		}

		$response = FPL_Exchange_Feed_Builder::build_offer();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response['exchange_mode']    = true;
		$response['catalog_status']   = 'ready';
		$response['catalog_url']      = $catalog_url;

		return rest_ensure_response( $response );
	}

	/**
	 * Receive an exchange request from a remote site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive_request( WP_REST_Request $request ) {
		// Catalog must be ready to receive requests.
		$catalog = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		if ( is_wp_error( $catalog ) ) {
			return new WP_Error(
				'fpl_catalog_not_ready',
				__( 'This store has not enabled Exchange Catalog Mode.', 'friend-product-links' ),
				array( 'status' => 403 )
			);
		}

		// Local offer must be enabled and valid to receive requests.
		$local = FPL_Exchange_Repository::validate_local_offer();
		if ( is_wp_error( $local ) ) {
			return new WP_Error(
				'fpl_local_offer_unavailable',
				__( 'This site is not accepting exchange requests at this time.', 'friend-product-links' ),
				array( 'status' => 403 )
			);
		}

		$raw_body = $request->get_body();
		if ( strlen( $raw_body ) > 32768 ) {
			return new WP_Error(
				'fpl_payload_too_large',
				__( 'Exchange payload is too large.', 'friend-product-links' ),
				array( 'status' => 413 )
			);
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'fpl_bad_request', __( 'Invalid request body.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		$request_id    = ! empty( $body['request_id'] ) ? sanitize_text_field( $body['request_id'] ) : '';
		$site_name     = ! empty( $body['from_site_name'] ) ? sanitize_text_field( $body['from_site_name'] ) : '';
		$site_url      = ! empty( $body['from_site_url'] ) ? esc_url_raw( $body['from_site_url'] ) : '';
		$offer_url     = ! empty( $body['from_offer_url'] ) ? esc_url_raw( $body['from_offer_url'] ) : '';
		$callback_url  = ! empty( $body['from_callback_url'] ) ? esc_url_raw( $body['from_callback_url'] ) : '';
		$callback_token = ! empty( $body['callback_token'] ) ? sanitize_text_field( $body['callback_token'] ) : '';
		$product_count = ! empty( $body['product_count'] )
			? min( absint( $body['product_count'] ), FPL_EXCHANGE_MAX_PRODUCTS )
			: 0;

		if ( ! $request_id || ! $site_name || ! $site_url || ! $offer_url || ! $callback_url || ! $callback_token ) {
			return new WP_Error( 'fpl_missing_fields', __( 'Missing required fields.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		if ( ! FPL_Security::is_token( $request_id ) || ! FPL_Security::is_token( $callback_token ) ) {
			return new WP_Error(
				'fpl_invalid_token',
				__( 'Invalid request token.', 'friend-product-links' ),
				array( 'status' => 400 )
			);
		}

		// Validate URL formats.
		$site_valid = FPL_URL_Helper::validate_identity_http_url( $site_url );
		if ( is_wp_error( $site_valid ) ) {
			return new WP_Error( 'fpl_invalid_site_url', __( 'Invalid site URL.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		// Offer and callback URLs will be fetched later — strict validation.
		$offer_valid = FPL_URL_Helper::is_public_http_url( $offer_url );
		if ( is_wp_error( $offer_valid ) ) {
			return new WP_Error( 'fpl_invalid_offer_url', __( 'Invalid offer URL.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		$callback_valid = FPL_URL_Helper::is_public_http_url( $callback_url );
		if ( is_wp_error( $callback_valid ) ) {
			return new WP_Error( 'fpl_invalid_callback_url', __( 'Invalid callback URL.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		// Same host check.
		$hosts = array_unique(
			array_filter(
				array(
					FPL_URL_Helper::get_host( $offer_url ),
					FPL_URL_Helper::get_host( $site_url ),
					FPL_URL_Helper::get_host( $callback_url ),
				)
			)
		);
		if ( count( $hosts ) > 1 ) {
			return new WP_Error( 'fpl_host_mismatch', __( 'All URLs must be on the same host.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		if (
			FPL_URL_Helper::same_host( home_url( '/' ), $site_url )
			|| FPL_URL_Helper::same_host( home_url( '/' ), $offer_url )
			|| FPL_URL_Helper::same_host( home_url( '/' ), $callback_url )
		) {
			return new WP_Error(
				'fpl_self_request',
				__( 'You cannot exchange with your own store.', 'friend-product-links' ),
				array( 'status' => 409 )
			);
		}

		// Deduplicate: same request_id or same offer_url with pending/active status.
		if ( FPL_Exchange_Repository::has_active_or_pending_request( $offer_url ) ) {
			return new WP_Error( 'fpl_duplicate_request', __( 'An exchange request for this offer already exists.', 'friend-product-links' ), array( 'status' => 409 ) );
		}

		if ( FPL_Exchange_Repository::get_partner_by_offer_url( $offer_url ) ) {
			return new WP_Error( 'fpl_duplicate_partner', __( 'This site is already an exchange partner.', 'friend-product-links' ), array( 'status' => 409 ) );
		}

		$existing_by_id = FPL_CPT::get_exchange_request_by_request_id( $request_id );
		if ( $existing_by_id ) {
			return new WP_Error( 'fpl_duplicate_request_id', __( 'This request ID has already been received.', 'friend-product-links' ), array( 'status' => 409 ) );
		}

		// Fetch remote offer for preview validation.
		$remote_offer = FPL_Exchange_Fetcher::fetch_remote_offer( $offer_url );

		// Use canonical remote offer_url for duplicate detection and storage.
		$canonical_offer_url = $offer_url;
		if ( ! is_wp_error( $remote_offer ) && ! empty( $remote_offer['offer_url'] ) ) {
			$canonical_offer_url = $remote_offer['offer_url'];

			if ( $canonical_offer_url !== $offer_url ) {
				if ( FPL_Exchange_Repository::has_active_or_pending_request( $canonical_offer_url ) ) {
					return new WP_Error( 'fpl_duplicate_request', __( 'An exchange request for this offer already exists.', 'friend-product-links' ), array( 'status' => 409 ) );
				}

				if ( FPL_Exchange_Repository::get_partner_by_offer_url( $canonical_offer_url ) ) {
					return new WP_Error( 'fpl_duplicate_partner', __( 'This site is already an exchange partner.', 'friend-product-links' ), array( 'status' => 409 ) );
				}
			}
		}

		$status = 'pending';
		$error  = '';
		if ( is_wp_error( $remote_offer ) ) {
			$status = 'failed';
			$error  = $remote_offer->get_error_message();
		}

		$result = FPL_Exchange_Repository::create_exchange_request(
			array(
				'direction'      => 'incoming',
				'request_id'     => $request_id,
				'status'         => $status,
				'remote_site_name' => $site_name,
				'remote_site_url'  => $site_url,
				'remote_offer_url' => $canonical_offer_url,
				'remote_callback_url' => $callback_url,
				'callback_token' => $callback_token,
				'remote_product_count' => is_wp_error( $remote_offer ) ? $product_count : absint( $remote_offer['product_count'] ?? $product_count ),
				'last_error'     => $error,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response_data = array(
			'success'    => true,
			'status'     => $status,
			'request_id' => $request_id,
		);

		if ( $error ) {
			$response_data['preview_error'] = $error;
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * Receive an exchange decision callback from a remote site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive_callback( WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		if ( strlen( $raw_body ) > 32768 ) {
			return new WP_Error(
				'fpl_payload_too_large',
				__( 'Exchange payload is too large.', 'friend-product-links' ),
				array( 'status' => 413 )
			);
		}

		$body = json_decode( $raw_body, true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'fpl_bad_request', __( 'Invalid request body.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		$request_id = ! empty( $body['request_id'] ) ? sanitize_text_field( $body['request_id'] ) : '';
		$decision   = ! empty( $body['decision'] ) ? sanitize_text_field( $body['decision'] ) : '';
		$site_name  = ! empty( $body['from_site_name'] ) ? sanitize_text_field( $body['from_site_name'] ) : '';
		$site_url   = ! empty( $body['from_site_url'] ) ? esc_url_raw( $body['from_site_url'] ) : '';
		$offer_url  = ! empty( $body['from_offer_url'] ) ? esc_url_raw( $body['from_offer_url'] ) : '';

		if ( ! $request_id || ! $decision || ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'fpl_missing_fields', __( 'Missing or invalid fields.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		if ( ! FPL_Security::is_token( $request_id ) ) {
			return new WP_Error(
				'fpl_invalid_token',
				__( 'Invalid request token.', 'friend-product-links' ),
				array( 'status' => 400 )
			);
		}

		// Find the outgoing request.
		$outgoing = FPL_CPT::get_exchange_request_by_request_id( $request_id );
		if ( ! $outgoing || FPL_CPT::EXCHANGE_REQUEST !== $outgoing->post_type ) {
			return new WP_Error( 'fpl_request_not_found', __( 'Exchange request not found.', 'friend-product-links' ), array( 'status' => 404 ) );
		}

		$direction = get_post_meta( $outgoing->ID, FPL_Exchange_Repository::META_DIRECTION, true );
		if ( 'outgoing' !== $direction ) {
			return new WP_Error( 'fpl_wrong_direction', __( 'Invalid request direction.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		// Verify HMAC signature.
		// $raw_body was already read for size check above.
		$signature  = sanitize_text_field( $request->get_header( 'x-fpl-exchange-signature' ) );
		$stored_token = FPL_Exchange_Repository::get_request_callback_token( $outgoing->ID );

		if ( ! FPL_Security::is_token( $stored_token ) ) {
			return new WP_Error(
				'fpl_invalid_callback_token',
				__( 'Invalid stored callback token.', 'friend-product-links' ),
				array( 'status' => 403 )
			);
		}

		if ( ! $signature || 1 !== preg_match( '/^[a-f0-9]{64}$/i', $signature ) || ! hash_equals( hash_hmac( 'sha256', $raw_body, $stored_token ), $signature ) ) {
			return new WP_Error( 'fpl_bad_signature', __( 'Invalid callback signature.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		$current_status = FPL_Exchange_Repository::get_request_status( $outgoing->ID );

		if ( in_array( $current_status, array( 'approved', 'rejected' ), true ) ) {
			if ( $decision === $current_status ) {
				return rest_ensure_response(
					array(
						'success'           => true,
						'status'            => $current_status,
						'already_finalized' => true,
					)
				);
			}

			return new WP_Error(
				'fpl_request_finalized',
				__( 'This exchange request has already been finalized.', 'friend-product-links' ),
				array( 'status' => 409 )
			);
		}

		if ( ! in_array( $current_status, array( 'pending', 'failed' ), true ) ) {
			return new WP_Error(
				'fpl_request_not_processable',
				__( 'This exchange request is not in a processable state.', 'friend-product-links' ),
				array( 'status' => 409 )
			);
		}

		if ( 'rejected' === $decision ) {
			FPL_Exchange_Repository::update_exchange_request(
				$outgoing->ID,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'rejected',
					FPL_Exchange_Repository::META_LAST_ERROR => '',
				)
			);

			return rest_ensure_response( array( 'success' => true, 'status' => 'rejected' ) );
		}

		// Approved: catalog must be ready, then local offer, then verify and fetch.
		$catalog = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		if ( is_wp_error( $catalog ) ) {
			$this->fail_outgoing_request( $outgoing->ID, __( 'Exchange Catalog page is not ready.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_catalog_not_ready', __( 'Exchange Catalog page is not ready.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		$local = FPL_Exchange_Repository::validate_local_offer();
		if ( is_wp_error( $local ) ) {
			$this->fail_outgoing_request( $outgoing->ID, __( 'This site is not accepting exchange approvals at this time.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_local_offer_unavailable', __( 'This site is not accepting exchange approvals at this time.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		if ( ! $site_name || ! $site_url || ! $offer_url ) {
			$this->fail_outgoing_request( $outgoing->ID, __( 'Missing partner info for approval.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_missing_partner_info', __( 'Missing partner info for approval.', 'friend-product-links' ), array( 'status' => 400 ) );
		}

		// Verify the callback offer URL matches the original outgoing request.
		$expected_offer_url = FPL_Exchange_Repository::get_request_remote_offer_url( $outgoing->ID );
		$received_offer_url = FPL_Exchange_Repository::normalize_offer_url( $offer_url );

		if ( ! $expected_offer_url || ! $received_offer_url || ! FPL_URL_Helper::same_host( $expected_offer_url, $received_offer_url ) ) {
			$this->fail_outgoing_request( $outgoing->ID, __( 'Offer URL mismatch.', 'friend-product-links' ) );
			return new WP_Error( 'fpl_offer_url_mismatch', __( 'Offer URL mismatch.', 'friend-product-links' ), array( 'status' => 403 ) );
		}

		$remote = FPL_Exchange_Fetcher::fetch_remote_offer( $expected_offer_url );

		if ( is_wp_error( $remote ) ) {
			$fetch_error = sprintf(
				/* translators: %s: remote fetch error */
				__( 'Could not fetch partner offer after approval: %s', 'friend-product-links' ),
				$remote->get_error_message()
			);
			$this->fail_outgoing_request( $outgoing->ID, $fetch_error );
			return new WP_Error( 'fpl_fetch_failed', $fetch_error, array( 'status' => 502 ) );
		}

		// Also allow canonical remote offer_url after successful fetch.
		if ( $received_offer_url !== $expected_offer_url ) {
			$canonical_cb_url = ! empty( $remote['offer_url'] ) ? FPL_Exchange_Repository::normalize_offer_url( $remote['offer_url'] ) : '';
			if ( ! $canonical_cb_url || $received_offer_url !== $canonical_cb_url ) {
				$this->fail_outgoing_request( $outgoing->ID, __( 'Offer URL mismatch.', 'friend-product-links' ) );
				return new WP_Error( 'fpl_offer_url_mismatch', __( 'Offer URL mismatch.', 'friend-product-links' ), array( 'status' => 403 ) );
			}
		}

		// Lookup existing partner by canonical URL, with fallback to expected URL.
		$canonical_offer_url = ! empty( $remote['offer_url'] ) ? $remote['offer_url'] : $expected_offer_url;
		$existing_partner    = FPL_Exchange_Repository::get_partner_by_offer_url( $canonical_offer_url );
		if ( ! $existing_partner && $expected_offer_url !== $canonical_offer_url ) {
			$existing_partner = FPL_Exchange_Repository::get_partner_by_offer_url( $expected_offer_url );
		}
		if ( $existing_partner ) {
			$status = FPL_Exchange_Repository::get_partner_status( $existing_partner->ID );
			if ( 'paused_safety' === $status ) {
				$this->fail_outgoing_request( $outgoing->ID, __( 'This partner is paused for safety reasons.', 'friend-product-links' ) );
				return new WP_Error( 'fpl_partner_paused', __( 'This partner is paused for safety reasons.', 'friend-product-links' ), array( 'status' => 409 ) );
			}

			FPL_Exchange_Repository::update_partner(
				$existing_partner->ID,
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
				$outgoing->ID,
				array(
					FPL_Exchange_Repository::META_STATUS     => 'approved',
					FPL_Exchange_Repository::META_LAST_ERROR => '',
				)
			);

			return rest_ensure_response(
				array(
					'success'    => true,
					'status'     => 'approved',
					'partner_id' => $existing_partner->ID,
				)
			);
		}

		$partner_id = FPL_Exchange_Repository::create_exchange_partner(
			array(
				'site_name'       => $remote['site_name'],
				'site_url'        => $remote['site_url'],
				'offer_url'       => $remote['offer_url'],
				'status'          => 'active',
				'cached_products' => $remote['products'],
				'last_error'      => '',
			)
		);

		if ( is_wp_error( $partner_id ) ) {
			$this->fail_outgoing_request( $outgoing->ID, $partner_id->get_error_message() );

			$status = 500;
			if ( 'fpl_duplicate_partner' === $partner_id->get_error_code() ) {
				$status = 409;
			} elseif ( 'fpl_invalid_partner_offer_url' === $partner_id->get_error_code() ) {
				$status = 400;
			}

			return new WP_Error(
				$partner_id->get_error_code(),
				$partner_id->get_error_message(),
				array( 'status' => $status )
			);
		}

		FPL_Exchange_Repository::update_exchange_request(
			$outgoing->ID,
			array(
				FPL_Exchange_Repository::META_STATUS     => 'approved',
				FPL_Exchange_Repository::META_LAST_ERROR => '',
			)
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'status'     => 'approved',
				'partner_id' => $partner_id,
			)
		);

	}

	/**
	 * Mark an outgoing exchange request as failed with a message.
	 *
	 * @param int    $outgoing_id Outgoing exchange request post ID.
	 * @param string $message     Error message.
	 * @return void
	 */
	private function fail_outgoing_request( $outgoing_id, $message ) {
		FPL_Exchange_Repository::update_exchange_request(
			$outgoing_id,
			array(
				FPL_Exchange_Repository::META_STATUS     => 'failed',
				FPL_Exchange_Repository::META_LAST_ERROR => sanitize_text_field( $message ),
			)
		);
	}
}