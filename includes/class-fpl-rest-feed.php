<?php
/**
 * Public product feed REST endpoint.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_REST_Feed {
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
			'/feed/(?P<token>[A-Za-z0-9_-]{32,128})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_feed' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Return a public feed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_feed( WP_REST_Request $request ) {
		if ( ! FPL_Plugin::is_woocommerce_active() ) {
			return new WP_Error( 'fpl_woocommerce_required', __( 'WooCommerce is required.', 'friend-product-links' ), array( 'status' => 503 ) );
		}

		$response = FPL_Feed_Builder::build_by_token( sanitize_text_field( $request['token'] ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
	}
}
