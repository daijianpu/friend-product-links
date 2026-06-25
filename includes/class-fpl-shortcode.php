<?php
/**
 * Shortcode integration.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Shortcode {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_shortcode( 'friend_product_links', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @return string
	 */
	public function render() {
		$renderer = new FPL_Renderer();

		return $renderer->render();
	}
}
