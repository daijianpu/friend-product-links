<?php
/**
 * Frontend friend product renderer.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Renderer {
	/**
	 * Whether core friends have been rendered on this page load.
	 *
	 * @var bool
	 */
	private $core_friends_rendered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 20 );

		$hook     = apply_filters( 'fpl_core_friends_auto_display_hook', 'woocommerce_after_single_product' );
		$priority = (int) apply_filters( 'fpl_core_friends_auto_display_priority', 9999 );

		add_action( $hook, array( $this, 'maybe_auto_render' ), $priority );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'fpl-frontend', FPL_URL . 'assets/frontend.css', array(), FPL_VERSION );
	}

	/**
	 * Render automatically on single product pages.
	 *
	 * @return void
	 */
	public function maybe_auto_render() {
		if ( $this->core_friends_rendered ) {
			return;
		}

		if ( ! FPL_Plugin::is_woocommerce_active() || ! function_exists( 'is_product' ) ) {
			return;
		}

		if ( 'yes' !== get_option( FPL_Repository::OPTION_AUTO_DISPLAY, 'yes' ) || ! is_product() ) {
			return;
		}

		$html = $this->render();
		if ( '' === trim( (string) $html ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<!-- Friend Product Links: no core friend products rendered. Check: (1) at least one Core Friend is enabled, (2) friend has valid cache, (3) Auto Display setting is ON, (4) current page is a WooCommerce product page. -->';
			}
			return;
		}

		$this->core_friends_rendered = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render module HTML.
	 *
	 * @return string
	 */
	public function render() {
		$products = $this->get_display_products();
		if ( empty( $products ) ) {
			return '';
		}

		FPL_Performance::record_displayed_products( $products );

		wp_enqueue_style( 'fpl-frontend' );
		$title = get_option( FPL_Repository::OPTION_FRONTEND_TITLE, __( 'You may also like from our friends', 'friend-product-links' ) );
		if ( '' === trim( (string) $title ) ) {
			$title = __( 'You may also like from our friends', 'friend-product-links' );
		}
		$button = get_option( FPL_Repository::OPTION_BUTTON_TEXT, __( 'View Product', 'friend-product-links' ) );
		$new_window = 'yes' === get_option( FPL_Repository::OPTION_NEW_WINDOW, 'yes' );
		$show_source = 'yes' === get_option( FPL_Repository::OPTION_SHOW_SOURCE, 'yes' );
		$show_remote_images = 'yes' === get_option( FPL_Repository::OPTION_SHOW_REMOTE_IMAGES, 'yes' );

		ob_start();
		?>
		<section class="woocommerce fpl-module fpl-core-friends-module" aria-label="<?php echo esc_attr( $title ); ?>">
			<h2 class="fpl-module-title"><?php echo esc_html( $title ); ?></h2>
			<ul class="fpl-products-grid fpl-core-products-grid">
				<?php foreach ( $products as $product ) : ?>
					<?php
					$target = $new_window ? '_blank' : '_self';
					$rel    = 'nofollow sponsored noopener noreferrer';
					$link_url = 'yes' === get_option( FPL_Repository::OPTION_ENABLE_STATS, 'yes' ) ? FPL_Performance::click_url( $product['friend_id'], $product['product_hash'] ) : $product['product_url'];
					?>
					<li class="fpl-card fpl-core-product-card">
						<?php if ( $show_remote_images && ! empty( $product['image_url'] ) ) : ?>
						<a class="woocommerce-LoopProduct-link woocommerce-loop-product__link fpl-image-link" href="<?php echo esc_url( $link_url ); ?>" target="<?php echo esc_attr( $target ); ?>" rel="<?php echo esc_attr( $rel ); ?>">
							<img class="fpl-image" src="<?php echo esc_url( $product['image_url'] ); ?>" alt="<?php echo esc_attr( $product['title'] ); ?>" loading="lazy" decoding="async">
						</a>
						<?php endif; ?>
						<h3 class="woocommerce-loop-product__title fpl-product-title" title="<?php echo esc_attr( $product['title'] ); ?>">
							<a href="<?php echo esc_url( $link_url ); ?>" target="<?php echo esc_attr( $target ); ?>" rel="<?php echo esc_attr( $rel ); ?>">
								<?php echo esc_html( $product['title'] ); ?>
							</a>
						</h3>
						<?php if ( ! empty( $product['price_text'] ) ) : ?>
							<p class="price fpl-product-price"><span class="fpl-price-label"><?php esc_html_e( 'Price:', 'friend-product-links' ); ?></span> <span class="fpl-price-value"><?php echo esc_html( $product['price_text'] ); ?></span></p>
						<?php endif; ?>
						<?php if ( $show_source && ! empty( $product['source_name'] ) ) : ?>
							<p class="fpl-product-source"><?php
							echo esc_html(
								sprintf(
									/* translators: %s: source store name */
									__( 'From: %s', 'friend-product-links' ),
									$product['source_name']
								)
							);
							?></p>
						<?php endif; ?>
						<a class="button product_type_external fpl-product-button" href="<?php echo esc_url( $link_url ); ?>" target="<?php echo esc_attr( $target ); ?>" rel="<?php echo esc_attr( $rel ); ?>">
							<?php echo esc_html( $button ); ?>
						</a>
						</li>
			<?php endforeach; ?>
		</ul>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build randomized display list.
	 *
	 * @return array
	 */
	public function get_display_products() {
		$friends = array_slice( FPL_CPT::get_friend_feeds( true ), 0, FPL_CORE_FRIEND_LIMIT );
		$display = array();

		foreach ( $friends as $friend ) {
			if ( ! FPL_Repository::friend_has_valid_cache( $friend->ID ) ) {
				continue;
			}

			$products = FPL_Repository::get_cached_products( $friend->ID );
			if ( ! is_array( $products ) || empty( $products ) ) {
				continue;
			}

			$source_url = FPL_Repository::get_friend_site_url( $friend->ID );
			shuffle( $products );
			$added = 0;

			foreach ( $products as $product ) {
				if ( $added >= FPL_CORE_DISPLAY_PRODUCTS ) {
					break;
				}

				if ( empty( $product['product_url'] ) || empty( $product['title'] ) ) {
					continue;
				}

				$product_url = FPL_URL_Helper::normalize_url( $product['product_url'] );
				if ( is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) || ( $source_url && ! FPL_URL_Helper::same_host( $source_url, $product_url ) ) ) {
					continue;
				}

				$image_url = ! empty( $product['image_url'] ) ? FPL_URL_Helper::normalize_url( $product['image_url'] ) : '';
				if ( $image_url && is_wp_error( FPL_URL_Helper::is_public_http_url( $image_url ) ) ) {
					$image_url = '';
				}

				$product_hash = ! empty( $product['product_hash'] ) ? sanitize_text_field( $product['product_hash'] ) : FPL_Security::product_hash( $product_url );
				if ( ! FPL_Security::is_product_hash( $product_hash ) ) {
					$product_hash = FPL_Security::product_hash( $product_url );
				}

				$display[] = array(
					'friend_id'    => $friend->ID,
					'title'        => sanitize_text_field( $product['title'] ),
					'image_url'    => $image_url,
					'price_text'   => ! empty( $product['price_text'] ) ? sanitize_text_field( $product['price_text'] ) : '',
					'product_url'  => $product_url,
					'product_hash' => $product_hash,
					'source_name'  => get_post_meta( $friend->ID, FPL_Repository::META_FRIEND_NAME, true ),
					'source_url'   => $source_url,
				);

				++$added;
			}
		}

		shuffle( $display );

		return $display;
	}
}
