<?php
/**
 * Frontend exchange catalog renderer.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Renderer {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_shortcode( 'fpl_exchange_catalog', array( $this, 'render_catalog' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 20 );
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
	 * Render the exchange catalog.
	 *
	 * @return string
	 */
	public function render_catalog() {
		if ( ! FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p>' . esc_html__( 'Exchange Catalog is not enabled.', 'friend-product-links' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=fpl-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'friend-product-links' ) . '</a></p>';
			}
			return '';
		}

		if ( ! FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p>' . esc_html__( 'Exchange Catalog page is missing or invalid. Please repair it in Settings.', 'friend-product-links' ) . '</p>';
			}
			return '';
		}

		$partners = FPL_CPT::get_exchange_partners( true );
		if ( empty( $partners ) ) {
			return '';
		}

		$stats_enabled  = 'yes' === get_option( FPL_Repository::OPTION_ENABLE_STATS, 'yes' );
		static $display_counted = array();
		$display_count  = array();
		$cards          = array();

		foreach ( $partners as $partner ) {
			$products   = FPL_Exchange_Repository::get_partner_cached_products( $partner->ID );
			$site_name  = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_SITE_NAME, true );
			$site_url   = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_SITE_URL, true );

			if ( empty( $products ) ) {
				continue;
			}

			$displayed = 0;
			foreach ( $products as $product ) {
				if ( empty( $product['product_url'] ) || empty( $product['title'] ) ) {
					continue;
				}

				$product_url = FPL_URL_Helper::normalize_url( $product['product_url'] );
				if ( is_wp_error( FPL_URL_Helper::is_public_http_url( $product_url ) ) || ( $site_url && ! FPL_URL_Helper::same_host( $site_url, $product_url ) ) ) {
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

				// Respect stats setting for link_url.
				$link_url = $stats_enabled
					? FPL_Exchange_Click_Tracker::click_url( $partner->ID, $product_hash )
					: $product_url;

				$cards[] = array(
					'title'        => sanitize_text_field( $product['title'] ),
					'image_url'    => $image_url,
					'price_text'   => ! empty( $product['price_text'] ) ? sanitize_text_field( $product['price_text'] ) : '',
					'product_url'  => $product_url,
					'link_url'     => $link_url,
					'product_hash' => $product_hash,
					'source_name'  => $site_name,
				);

				// Display count with per-product deduplication within same page load.
				// Must run before break so the 4th card is also counted.
				if ( $stats_enabled ) {
					$dedupe_key = $partner->ID . '|' . $product_hash;
					if ( ! isset( $display_counted[ $dedupe_key ] ) ) {
						$display_counted[ $dedupe_key ] = true;
						if ( ! isset( $display_count[ $partner->ID ] ) ) {
							$display_count[ $partner->ID ] = 0;
						}
						$display_count[ $partner->ID ]++;
					}
				}

				++$displayed;
				if ( $displayed >= FPL_EXCHANGE_DISPLAY_PRODUCTS ) {
					break;
				}
			}
		}

		if ( empty( $cards ) ) {
			return '';
		}

		// Batch update display counts after cards are confirmed.
		if ( $stats_enabled ) {
			foreach ( $display_count as $pid => $count ) {
				$current = absint( get_post_meta( $pid, FPL_Exchange_Repository::META_PARTNER_DISPLAYS_SENT, true ) );
				update_post_meta( $pid, FPL_Exchange_Repository::META_PARTNER_DISPLAYS_SENT, $current + absint( $count ) );
			}
		}

		wp_enqueue_style( 'fpl-frontend' );

		$button_text = get_option( FPL_Repository::OPTION_BUTTON_TEXT, __( 'View Product', 'friend-product-links' ) );
		$new_window  = 'yes' === get_option( FPL_Repository::OPTION_NEW_WINDOW, 'yes' );
		$target      = $new_window ? '_blank' : '_self';
		$rel         = 'nofollow sponsored noopener noreferrer';

		ob_start();
		?>
		<section class="woocommerce fpl-exchange-catalog fpl-module" aria-label="<?php esc_attr_e( 'Exchange Catalog', 'friend-product-links' ); ?>">
			<ul class="fpl-products-grid fpl-exchange-grid">
				<?php foreach ( $cards as $card ) : ?>
					<li class="fpl-card fpl-exchange-card">
						<?php if ( ! empty( $card['image_url'] ) ) : ?>
						<a class="woocommerce-LoopProduct-link woocommerce-loop-product__link fpl-image-link" href="<?php echo esc_url( $card['link_url'] ); ?>" target="<?php echo esc_attr( $target ); ?>" rel="<?php echo esc_attr( $rel ); ?>">
							<img class="fpl-image" src="<?php echo esc_url( $card['image_url'] ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" loading="lazy" decoding="async">
						</a>
						<?php endif; ?>
						<h3 class="woocommerce-loop-product__title fpl-exchange-product-title" title="<?php echo esc_attr( $card['title'] ); ?>">
							<a href="<?php echo esc_url( $card['link_url'] ); ?>" target="<?php echo esc_attr( $target ); ?>" rel="<?php echo esc_attr( $rel ); ?>">
								<?php echo esc_html( $card['title'] ); ?>
							</a>
						</h3>
						<?php if ( ! empty( $card['price_text'] ) ) : ?>
							<p class="price fpl-exchange-price"><span class="fpl-price-label"><?php esc_html_e( 'Price:', 'friend-product-links' ); ?></span> <span class="fpl-price-value"><?php echo esc_html( $card['price_text'] ); ?></span></p>
						<?php endif; ?>
						<?php if ( ! empty( $card['source_name'] ) ) : ?>
							<p class="fpl-exchange-source"><?php
							echo esc_html(
								sprintf(
									/* translators: %s: source store name */
									__( 'From: %s', 'friend-product-links' ),
									$card['source_name']
								)
							);
							?></p>
						<?php endif; ?>
						<a class="button product_type_external fpl-exchange-product-button" href="<?php echo esc_url( $card['link_url'] ); ?>" target="<?php echo esc_attr( $target ); ?>" rel="<?php echo esc_attr( $rel ); ?>">
							<?php echo esc_html( $button_text ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
		return ob_get_clean();
	}
}
