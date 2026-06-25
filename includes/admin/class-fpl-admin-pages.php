<?php
/**
 * Admin page renderers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Admin_Pages extends FPL_Admin_View_Helper {
	/**
	 * Render share links page.
	 *
	 * @return void
	 */
	public function render_share_links_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$links = FPL_CPT::get_share_links();
		$edit_id = isset( $_GET['edit_share_id'] ) ? absint( $_GET['edit_share_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit = $edit_id && FPL_CPT::SHARE_LINK === get_post_type( $edit_id ) ? get_post( $edit_id ) : null;
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Core Product Links', 'friend-product-links' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p><?php esc_html_e( 'Create private product links to share selected WooCommerce products with core friends.', 'friend-product-links' ); ?></p>

			<table class="widefat striped fpl-table">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Friend', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Products', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Status', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Friend-reported displays', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Clicks from friend sites', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Last stats received', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Friend Product Link', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'friend-product-links' ); ?></th>
				</tr></thead>
				<tbody>
					<?php if ( empty( $links ) ) : ?>
						<tr><td colspan="10"><?php esc_html_e( 'No product links yet.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $links as $link ) : ?>
						<?php
						$enabled = '1' === get_post_meta( $link->ID, FPL_Repository::META_ENABLED, true );
						$token = get_post_meta( $link->ID, FPL_Repository::META_TOKEN, true );
						$url   = FPL_Security::is_token( $token ) ? rest_url( 'friend-product-links/v1/feed/' . rawurlencode( $token ) ) : '';
						$product_ids = get_post_meta( $link->ID, FPL_Repository::META_PRODUCTS, true );
						$remote_display = absint( get_post_meta( $link->ID, FPL_Repository::META_REMOTE_DISPLAY_COUNT, true ) );
						$remote_clicks = absint( get_post_meta( $link->ID, FPL_Repository::META_REMOTE_CLICK_COUNT, true ) );
						?>
						<tr>
							<td><?php $this->render_value_or_empty( get_the_title( $link ) ); ?></td>
							<td><?php $this->render_value_or_empty( get_post_meta( $link->ID, FPL_Repository::META_FRIEND_NAME, true ) ); ?></td>
							<td><?php echo esc_html( is_array( $product_ids ) ? count( $product_ids ) : 0 ); ?></td>
							<td><?php echo $enabled ? esc_html__( 'Enabled', 'friend-product-links' ) : esc_html__( 'Disabled', 'friend-product-links' ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $remote_display ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $remote_clicks ) ); ?></td>
							<td><?php $this->render_value_or_empty( $this->format_ctr( $remote_clicks, $remote_display ) ); ?></td>
							<td><?php $this->render_value_or_empty( get_post_meta( $link->ID, FPL_Repository::META_REMOTE_LAST_RECEIVED_TIME, true ) ); ?></td>
							<td>
								<?php if ( $url ) : ?>
								<input class="large-text code fpl-copy-field" readonly value="<?php echo esc_url( $url ); ?>">
								<button type="button" class="button fpl-copy-button"><?php esc_html_e( 'Copy Link', 'friend-product-links' ); ?></button>
								<?php else : ?>
									<?php $this->render_empty_value(); ?>
									<br><span class="description"><?php esc_html_e( 'Token missing or invalid. Use Reset Token.', 'friend-product-links' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<div class="fpl-row-actions">
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'fpl-product-links', 'edit_share_id' => $link->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'friend-product-links' ); ?></a>
								<?php $this->render_toggle_form( $link->ID, $enabled ); ?>
								<?php $this->render_post_action_form( 'reset_share_token', $link->ID, __( 'Reset Token', 'friend-product-links' ), '', __( 'Resetting the token will invalidate the old Friend Product Link. Continue?', 'friend-product-links' ) ); ?>
								<?php $this->render_post_action_form( 'delete_share_link', $link->ID, __( 'Delete', 'friend-product-links' ), 'button-link-delete', __( 'Are you sure you want to delete this item?', 'friend-product-links' ) ); ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php $this->render_share_link_form( $edit ); ?>
		</div>
		<?php
	}

	/**
	 * Render friends page.
	 *
	 * @return void
	 */
	public function render_friends_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$friends = FPL_CPT::get_friend_feeds();
		$edit_id = isset( $_GET['edit_friend_id'] ) ? absint( $_GET['edit_friend_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit = $edit_id && FPL_CPT::FRIEND_FEED === get_post_type( $edit_id ) ? get_post( $edit_id ) : null;
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Core Friends', 'friend-product-links' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p><?php printf(
					/* translators: %d: core friend limit */
					esc_html__( 'Add up to %d core friends. Product pages only use cached friend product data.', 'friend-product-links' ),
					esc_html( FPL_CORE_FRIEND_LIMIT )
				); ?></p>

			<table class="widefat striped fpl-table">
				<thead><tr>
					<th><?php esc_html_e( 'Order', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Friend', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Cached Products', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Sync Status', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Last Sync', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Status', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Server-rendered displays', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Clicks from my site', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Last click', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Stats sent', 'friend-product-links' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'friend-product-links' ); ?></th>
				</tr></thead>
				<tbody>
					<?php if ( empty( $friends ) ) : ?>
						<tr><td colspan="12"><?php esc_html_e( 'No friends yet.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $friends as $friend ) : ?>
						<?php
						$products = FPL_Repository::friend_has_valid_cache( $friend->ID ) ? FPL_Repository::get_cached_products( $friend->ID ) : array();
						$enabled = '1' === get_post_meta( $friend->ID, FPL_Repository::META_ENABLED, true );
						$local_totals = FPL_Stats_Repository::get_friend_totals( $friend->ID );
						$local_display = absint( $local_totals['displays'] );
						$local_clicks = absint( $local_totals['clicks'] );
						?>
						<tr>
							<td><?php $this->render_value_or_empty( get_post_meta( $friend->ID, FPL_Repository::META_SORT_ORDER, true ) ); ?></td>
							<td>
								<?php $fn = get_post_meta( $friend->ID, FPL_Repository::META_FRIEND_NAME, true ); ?>
								<?php if ( '' !== trim( (string) $fn ) ) : ?>
									<strong><?php echo esc_html( $fn ); ?></strong>
								<?php else : ?>
									<?php $this->render_empty_value(); ?>
								<?php endif; ?><br>
								<?php $friend_site_url = get_post_meta( $friend->ID, FPL_Repository::META_FRIEND_SITE_URL, true ); ?>
								<?php $this->render_external_link_or_empty( $friend_site_url ); ?>
							</td>
							<td><?php echo esc_html( is_array( $products ) ? count( $products ) : 0 ); ?></td>
							<td>
								<?php echo esc_html( get_post_meta( $friend->ID, FPL_Repository::META_SYNC_STATUS, true ) ? get_post_meta( $friend->ID, FPL_Repository::META_SYNC_STATUS, true ) : __( 'Never synced', 'friend-product-links' ) ); ?>
								<?php if ( get_post_meta( $friend->ID, FPL_Repository::META_SYNC_ERROR, true ) ) : ?>
									<br><span class="fpl-error"><?php echo esc_html( get_post_meta( $friend->ID, FPL_Repository::META_SYNC_ERROR, true ) ); ?></span>
								<?php endif; ?>
								<?php if ( get_post_meta( $friend->ID, FPL_Repository::META_SITE_MISMATCH_WARNING, true ) ) : ?>
									<br><span class="fpl-warning"><?php echo esc_html( get_post_meta( $friend->ID, FPL_Repository::META_SITE_MISMATCH_WARNING, true ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php $this->render_value_or_empty( get_post_meta( $friend->ID, FPL_Repository::META_LAST_SYNCED_AT, true ) ); ?></td>
							<td><?php echo $enabled ? esc_html__( 'Enabled', 'friend-product-links' ) : esc_html__( 'Disabled', 'friend-product-links' ); ?>
							<?php if ( ! $enabled && is_array( $products ) && count( $products ) > 0 ) : ?>
								<br><span class="fpl-warning"><?php esc_html_e( 'Has cached products but disabled. Enable to display on product pages.', 'friend-product-links' ); ?></span>
							<?php endif; ?></td>
							<td><?php echo esc_html( number_format_i18n( $local_display ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $local_clicks ) ); ?></td>
							<td><?php $this->render_value_or_empty( $this->format_ctr( $local_clicks, $local_display ) ); ?></td>
							<td><?php $this->render_value_or_empty( $local_totals['last_clicked_at'] ?? '' ); ?></td>
							<td><?php $this->render_value_or_empty( $this->format_stats_sent( $friend->ID ) ); ?></td>
							<td>
								<div class="fpl-row-actions">
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'fpl-friends', 'edit_friend_id' => $friend->ID ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'friend-product-links' ); ?></a>
								<?php $this->render_toggle_form( $friend->ID, $enabled ); ?>
								<?php $this->render_post_action_form( 'sync_friend', $friend->ID, __( 'Sync Now', 'friend-product-links' ) ); ?>
								<?php $this->render_post_action_form( 'delete_friend', $friend->ID, __( 'Delete', 'friend-product-links' ), 'button-link-delete', __( 'Are you sure you want to delete this item?', 'friend-product-links' ) ); ?>
								</div>
							</td>
						</tr>
						<?php if ( is_array( $products ) && ! empty( $products ) ) : ?>
							<tr class="fpl-preview-row"><td colspan="12"><?php $this->render_cached_preview( $products ); ?></td></tr>
						<?php endif; ?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! $edit && count( $friends ) >= FPL_CORE_FRIEND_LIMIT ) : ?>
				<div class="notice notice-info inline"><p><?php printf(
						/* translators: %d: core friend limit */
						esc_html__( 'You can add up to %d core friends in this version.', 'friend-product-links' ),
						esc_html( FPL_CORE_FRIEND_LIMIT )
					); ?></p></div>
			<?php else : ?>
				<?php $this->render_friend_form( $edit ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$exchange_enabled = false;
		$catalog_ready    = false;
		$catalog_status   = __( 'Not active', 'friend-product-links' );
		$catalog_url      = '';
		$catalog_page_id  = 0;

		if ( class_exists( 'FPL_Exchange_Catalog_Page_Manager' ) ) {
			$exchange_enabled = FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled();
			$catalog_ready    = FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready();
			$catalog_page_id  = absint( FPL_Exchange_Catalog_Page_Manager::get_catalog_page_id() );
			$catalog_url      = FPL_Exchange_Catalog_Page_Manager::get_catalog_page_url();

			if ( ! $exchange_enabled ) {
				$catalog_status = __( 'Not active', 'friend-product-links' );
			} elseif ( $catalog_ready ) {
				$catalog_status = __( 'Ready', 'friend-product-links' );
			} elseif ( ! $catalog_page_id ) {
				$catalog_status = __( 'Missing', 'friend-product-links' );
			} else {
				$catalog_post = get_post( $catalog_page_id );
				if ( ! $catalog_post ) {
					$catalog_status = __( 'Missing', 'friend-product-links' );
				} elseif ( 'trash' === $catalog_post->post_status ) {
					$catalog_status = __( 'Trashed', 'friend-product-links' );
				} elseif ( 'publish' !== $catalog_post->post_status ) {
					$catalog_status = __( 'Not published', 'friend-product-links' );
				} elseif ( false === strpos( (string) $catalog_post->post_content, FPL_EXCHANGE_CATALOG_SHORTCODE ) ) {
					$catalog_status = __( 'Invalid shortcode', 'friend-product-links' );
				} else {
					$catalog_status = __( 'Invalid', 'friend-product-links' );
				}
			}
		}
		$active_exchange_partner_count = 0;
		$exchange_mode_locked_on = false;

		if ( class_exists( 'FPL_CPT' ) ) {
			$active_exchange_partners = FPL_CPT::get_exchange_partners( true );
			$active_exchange_partner_count = is_array( $active_exchange_partners ) ? count( $active_exchange_partners ) : 0;
		}

		$exchange_mode_locked_on = $exchange_enabled && $active_exchange_partner_count > 0;
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Settings', 'friend-product-links' ); ?></h1>
			<?php $this->render_notice(); ?>
			<form method="post" class="fpl-panel">
				<?php wp_nonce_field( 'fpl_save_settings' ); ?>
				<input type="hidden" name="fpl_action" value="save_settings">

				<div class="fpl-mode-card fpl-core-mode-card">
					<div class="fpl-mode-card-header">
						<div>
							<h2><?php esc_html_e( 'Core Friends Mode', 'friend-product-links' ); ?></h2>
							<p><?php esc_html_e( "Show trusted core friends' products under WooCommerce product pages.", 'friend-product-links' ); ?></p>
						</div>
						<div class="fpl-mode-switch fpl-mode-switch-locked is-on" aria-label="<?php esc_attr_e( 'Core Friends Mode is always enabled', 'friend-product-links' ); ?>">
							<span class="fpl-mode-switch-ui" aria-hidden="true">
								<span class="fpl-mode-switch-knob"></span>
								<span class="fpl-mode-switch-text fpl-mode-switch-text-on"><?php esc_html_e( 'ON', 'friend-product-links' ); ?></span>
							</span>
						</div>
					</div>
					<div class="fpl-mode-card-body">
						<p><strong><?php esc_html_e( 'Status:', 'friend-product-links' ); ?></strong> <span class="fpl-status-badge is-ready"><?php esc_html_e( 'Enabled by default', 'friend-product-links' ); ?></span></p>
						<p><?php esc_html_e( 'Core Friends Mode is the basic friend product placement feature. It is always enabled and cannot be turned off.', 'friend-product-links' ); ?></p>
						<p class="description"><?php printf(
							/* translators: %d: core friend limit */
							esc_html__( 'Use Core Product Links and Core Friends to display products from up to %d trusted core friends on single product pages.', 'friend-product-links' ),
							esc_html( FPL_CORE_FRIEND_LIMIT )
						); ?></p>
					</div>
				</div>

				<div class="fpl-mode-card fpl-exchange-mode-card">
					<div class="fpl-mode-card-header">
						<div>
							<h2><?php esc_html_e( 'Exchange Catalog Mode', 'friend-product-links' ); ?></h2>
							<p><?php esc_html_e( 'Exchange products with other WooCommerce store owners on a dedicated catalog page.', 'friend-product-links' ); ?></p>
						</div>
						<?php if ( $exchange_mode_locked_on ) : ?>
						<input type="hidden" name="exchange_mode_enabled" value="yes">
						<div class="fpl-mode-switch fpl-mode-switch-locked is-on" aria-label="<?php esc_attr_e( 'Exchange Catalog Mode is locked on while active partners exist', 'friend-product-links' ); ?>">
							<span class="fpl-mode-switch-ui" aria-hidden="true">
								<span class="fpl-mode-switch-knob"></span>
								<span class="fpl-mode-switch-text fpl-mode-switch-text-on"><?php esc_html_e( 'ON', 'friend-product-links' ); ?></span>
							</span>
						</div>
					<?php else : ?>
						<label class="fpl-mode-switch">
							<input type="checkbox" name="exchange_mode_enabled" value="yes" <?php checked( $exchange_enabled ); ?>>
							<span class="fpl-mode-switch-ui" aria-hidden="true">
								<span class="fpl-mode-switch-knob"></span>
								<span class="fpl-mode-switch-text fpl-mode-switch-text-off"><?php esc_html_e( 'OFF', 'friend-product-links' ); ?></span>
								<span class="fpl-mode-switch-text fpl-mode-switch-text-on"><?php esc_html_e( 'ON', 'friend-product-links' ); ?></span>
							</span>
						</label>
					<?php endif; ?>
					</div>
					<div class="fpl-mode-card-body">
						<p><strong><?php esc_html_e( 'Status:', 'friend-product-links' ); ?></strong>
							<span class="fpl-status-badge <?php echo $exchange_enabled ? 'is-ready' : 'is-disabled'; ?>">
								<?php echo $exchange_enabled ? esc_html__( 'Enabled', 'friend-product-links' ) : esc_html__( 'Disabled', 'friend-product-links' ); ?>
							</span>
						</p>

						<?php if ( $exchange_enabled ) : ?>
							<?php if ( $exchange_mode_locked_on ) : ?>
								<p class="fpl-warning"><?php
								printf(
									/* translators: %d: number of active exchange partners */
									esc_html__( 'Exchange Catalog Mode is locked ON because you have %d active exchange partner(s). Pause or delete active partners before turning this mode off.', 'friend-product-links' ),
									absint( $active_exchange_partner_count )
								);
								?></p>
							<?php endif; ?>
							<p><?php esc_html_e( 'When this mode is ON, the plugin automatically creates and maintains your Exchange Catalog page.', 'friend-product-links' ); ?></p>
							<p><strong><?php esc_html_e( 'Catalog Page Status:', 'friend-product-links' ); ?></strong>
								<span class="fpl-status-badge <?php echo $catalog_ready ? 'is-ready' : 'is-warning'; ?>"><?php echo esc_html( $catalog_status ); ?></span>
								<?php if ( $catalog_url ) : ?>
									| <a href="<?php echo esc_url( $catalog_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Page', 'friend-product-links' ); ?></a>
									<?php if ( $catalog_page_id ) : ?>
										| <a href="<?php echo esc_url( get_edit_post_link( $catalog_page_id ) ); ?>"><?php esc_html_e( 'Edit Page', 'friend-product-links' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
							</p>
						<?php else : ?>
							<p><?php esc_html_e( 'When this mode is OFF, exchange offers, exchange requests, and exchange approvals are disabled.', 'friend-product-links' ); ?></p>
							<p class="description"><?php esc_html_e( 'Enable this mode to automatically create an Exchange Catalog page and exchange products with approved WooCommerce store owners.', 'friend-product-links' ); ?></p>
							<p><strong><?php esc_html_e( 'Catalog Page Status:', 'friend-product-links' ); ?></strong> <?php esc_html_e( 'Not active', 'friend-product-links' ); ?></p>
						<?php endif; ?>

						<p class="description"><?php esc_html_e( 'Changes to this mode are applied after you click Save Settings.', 'friend-product-links' ); ?></p>
					</div>
				</div>

				<hr>

				<h2><?php esc_html_e( 'Core Friends Display Settings', 'friend-product-links' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These settings control how core friend products appear on single WooCommerce product pages.', 'friend-product-links' ); ?></p>
				<p><label><input type="checkbox" name="auto_display" <?php checked( get_option( FPL_Repository::OPTION_AUTO_DISPLAY, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Auto display on single product pages', 'friend-product-links' ); ?></label></p>
				<?php $this->field_text( 'frontend_title', __( 'Frontend Title', 'friend-product-links' ), get_option( FPL_Repository::OPTION_FRONTEND_TITLE, __( 'You may also like from our friends', 'friend-product-links' ) ) ); ?>
				<?php $this->field_text( 'button_text', __( 'Button Text', 'friend-product-links' ), get_option( FPL_Repository::OPTION_BUTTON_TEXT, __( 'View Product', 'friend-product-links' ) ) ); ?>
				<p><label><input type="checkbox" name="new_window" <?php checked( get_option( FPL_Repository::OPTION_NEW_WINDOW, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Open friend products in a new window', 'friend-product-links' ); ?></label></p>
				<p><label><input type="checkbox" name="show_source" <?php checked( get_option( FPL_Repository::OPTION_SHOW_SOURCE, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Show source store name', 'friend-product-links' ); ?></label></p>
				<p><label><input type="checkbox" name="show_remote_images" <?php checked( get_option( FPL_Repository::OPTION_SHOW_REMOTE_IMAGES, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Show remote friend product images', 'friend-product-links' ); ?></label></p>
				<p class="description"><?php esc_html_e( "Product images are loaded from friend store image URLs. Your visitors' browsers may request those remote images directly. Disable image display if you do not want remote image requests.", 'friend-product-links' ); ?></p>

				<h3><?php esc_html_e( 'Frontend Color Customization', 'friend-product-links' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Frontend color customization is optional. If you do not add any custom CSS, Friend Product Links will use its default frontend colors. To match your theme, add CSS variables to your theme Additional CSS.', 'friend-product-links' ); ?></p>
				<?php
				$color_css = "body .fpl-module {\n    --fpl-price-color: #f00000;\n    --fpl-button-color: #7c00ff;\n    --fpl-button-hover-color: #5f00cc;\n    --fpl-button-text-color: #ffffff;\n}";
				?>
				<pre class="fpl-code-example"><code><?php echo esc_html( $color_css ); ?></code></pre>
				<p class="description"><?php esc_html_e( 'You can change the hex colors to match your theme. Leave a variable out to use the plugin default.', 'friend-product-links' ); ?></p>

				<hr>

				<h2><?php esc_html_e( 'Performance Settings', 'friend-product-links' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These settings control local aggregate statistics and partner stats sharing.', 'friend-product-links' ); ?></p>
				<p><label><input type="checkbox" name="enable_stats" <?php checked( get_option( FPL_Repository::OPTION_ENABLE_STATS, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Enable local performance stats', 'friend-product-links' ); ?></label></p>
				<p><label><input type="checkbox" name="share_stats" <?php checked( get_option( FPL_Repository::OPTION_SHARE_STATS, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Share aggregated stats with friend sites', 'friend-product-links' ); ?></label></p>
				<p><label><input type="checkbox" name="receive_stats" <?php checked( get_option( FPL_Repository::OPTION_RECEIVE_STATS, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Receive aggregated stats from friend sites', 'friend-product-links' ); ?></label></p>
				<p class="description"><?php esc_html_e( 'Only aggregated display and click counts are shared with connected friend sites. No visitor IP addresses, cookies, user accounts, order data, user agents, or personal data are shared. No statistics are sent to the plugin author or any central server.', 'friend-product-links' ); ?></p>

				<p><button class="button button-primary"><?php esc_html_e( 'Save Settings', 'friend-product-links' ); ?></button></p>
			</form>

			<?php if ( $exchange_enabled && ! $catalog_ready ) : ?>
			<form method="post" class="fpl-panel" data-fpl-confirm="<?php esc_attr_e( 'Repair the Exchange Catalog page now?', 'friend-product-links' ); ?>">
				<?php wp_nonce_field( 'fpl_repair_exchange_catalog_page' ); ?>
				<input type="hidden" name="fpl_action" value="repair_exchange_catalog_page">
				<h2><?php esc_html_e( 'Repair Exchange Catalog Page', 'friend-product-links' ); ?></h2>
				<p><?php esc_html_e( 'Exchange Catalog page is missing or invalid. Repair it before using Exchange Catalog exchanges.', 'friend-product-links' ); ?></p>
				<p><button type="submit" class="button"><?php esc_html_e( 'Repair Exchange Catalog Page', 'friend-product-links' ); ?></button></p>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render performance page.
	 *
	 * @return void
	 */
	public function render_performance_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$report = FPL_Performance::get_click_report();
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Performance', 'friend-product-links' ); ?></h1>
			<?php $this->render_notice(); ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Friend Product Links records local aggregate click counts for your shared product placements. It does not store visitor IP addresses, browser fingerprints, or personal profiles, and it does not send analytics data to third-party services.', 'friend-product-links' ); ?></p></div>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Display counts are server-rendered counts. Sites using full-page cache may undercount displays; click counts are usually more reliable.', 'friend-product-links' ); ?></p></div>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Remote stats are reported by the connected friend site and are intended for partner reference, not third-party audited billing.', 'friend-product-links' ); ?></p></div>
			<div class="fpl-panel">
				<h2><?php esc_html_e( 'Local Aggregate Clicks', 'friend-product-links' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'Today:', 'friend-product-links' ); ?></strong> <?php echo esc_html( number_format_i18n( $report['today'] ) ); ?>
					&nbsp; <strong><?php esc_html_e( 'Last 7 days:', 'friend-product-links' ); ?></strong> <?php echo esc_html( number_format_i18n( $report['seven'] ) ); ?>
					&nbsp; <strong><?php esc_html_e( 'Last 30 days:', 'friend-product-links' ); ?></strong> <?php echo esc_html( number_format_i18n( $report['thirty'] ) ); ?>
				</p>
			</div>
			<h2><?php esc_html_e( 'Top Friend Hosts', 'friend-product-links' ); ?></h2>
			<table class="widefat striped fpl-table">
				<thead><tr><th><?php esc_html_e( 'Friend Host', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Displays', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Clicks', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Last Click', 'friend-product-links' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $report['friends'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No clicks recorded yet.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $report['friends'] as $row ) : ?>
						<tr><td><?php $this->render_value_or_empty( $row['friend_host'] ?? '' ); ?></td><td><?php echo esc_html( number_format_i18n( $row['displays'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td><td><?php $this->render_value_or_empty( $row['last_clicked_at'] ?? '' ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Top Products', 'friend-product-links' ); ?></h2>
			<table class="widefat striped fpl-table">
				<thead><tr><th><?php esc_html_e( 'Product Hash', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Displays', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Clicks', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Last Click', 'friend-product-links' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $report['products'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No product clicks recorded yet.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $report['products'] as $row ) : ?>
						<tr><td><?php
						$ph = sanitize_text_field( $row['product_hash'] ?? '' );
						$ph_label = $ph ? substr( $ph, 0, 16 ) . '...' : '';
						if ( $ph_label ) : ?>
							<code><?php echo esc_html( $ph_label ); ?></code>
						<?php else : ?>
							<?php $this->render_empty_value(); ?>
						<?php endif; ?>
					</td><td><?php echo esc_html( number_format_i18n( $row['displays'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td><td><?php $this->render_value_or_empty( $row['last_clicked_at'] ?? '' ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Share Link Rankings', 'friend-product-links' ); ?></h2>
			<table class="widefat striped fpl-table">
				<thead><tr><th><?php esc_html_e( 'Share Link', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Remote Clicks', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Friend-reported displays', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'CTR', 'friend-product-links' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( FPL_CPT::get_share_links() as $share_link ) : ?>
						<?php
						$remote_clicks  = absint( get_post_meta( $share_link->ID, FPL_Repository::META_REMOTE_CLICK_COUNT, true ) );
						$remote_display = absint( get_post_meta( $share_link->ID, FPL_Repository::META_REMOTE_DISPLAY_COUNT, true ) );
						?>
						<tr><td><?php $this->render_value_or_empty( get_the_title( $share_link ) ); ?></td><td><?php echo esc_html( number_format_i18n( $remote_clicks ) ); ?></td><td><?php echo esc_html( number_format_i18n( $remote_display ) ); ?></td><td><?php $this->render_value_or_empty( $this->format_ctr( $remote_clicks, $remote_display ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Friend Feed Rankings', 'friend-product-links' ); ?></h2>
			<table class="widefat striped fpl-table">
				<thead><tr><th><?php esc_html_e( 'Friend', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Local Clicks', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'Server-rendered displays', 'friend-product-links' ); ?></th><th><?php esc_html_e( 'CTR', 'friend-product-links' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( FPL_CPT::get_friend_feeds() as $friend ) : ?>
						<?php
						$local_totals  = FPL_Stats_Repository::get_friend_totals( $friend->ID );
						$local_clicks  = absint( $local_totals['clicks'] );
						$local_display = absint( $local_totals['displays'] );
						?>
						<tr><td><?php $this->render_value_or_empty( get_post_meta( $friend->ID, FPL_Repository::META_FRIEND_NAME, true ) ); ?></td><td><?php echo esc_html( number_format_i18n( $local_clicks ) ); ?></td><td><?php echo esc_html( number_format_i18n( $local_display ) ); ?></td><td><?php $this->render_value_or_empty( $this->format_ctr( $local_clicks, $local_display ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render share link form.
	 *
	 * @param WP_Post|null $edit Edit post.
	 * @return void
	 */
	private function render_share_link_form( $edit = null ) {
		$post_id = $edit ? $edit->ID : 0;
		$selected = $post_id ? get_post_meta( $post_id, FPL_Repository::META_PRODUCTS, true ) : array();
		if ( ! is_array( $selected ) ) {
			$selected = array();
		}

		if ( ! FPL_Plugin::is_woocommerce_active() ) {
			?>
			<h2><?php echo esc_html( $edit ? __( 'Edit Core Product Link', 'friend-product-links' ) : __( 'Create Core Product Link', 'friend-product-links' ) ); ?></h2>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is required to create Core Product Links.', 'friend-product-links' ); ?></p></div>
			<?php
			return;
		}
		?>
		<h2><?php echo esc_html( $edit ? __( 'Edit Core Product Link', 'friend-product-links' ) : __( 'Create Core Product Link', 'friend-product-links' ) ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=fpl-product-links' ) ); ?>" class="fpl-panel" data-fpl-product-form="1">
			<?php wp_nonce_field( 'fpl_save_share_link' ); ?>
			<input type="hidden" name="fpl_action" value="save_share_link">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php $this->field_text( 'link_name', __( 'Link Name', 'friend-product-links' ), $edit ? get_the_title( $edit ) : '' ); ?>
			<?php $this->field_text( 'friend_name', __( 'Friend Name', 'friend-product-links' ), $post_id ? get_post_meta( $post_id, FPL_Repository::META_FRIEND_NAME, true ) : '' ); ?>
			<?php $this->field_url( 'friend_site_url', __( 'Friend Website URL', 'friend-product-links' ), $post_id ? get_post_meta( $post_id, FPL_Repository::META_FRIEND_SITE_URL, true ) : '' ); ?>
			<?php
					$this->render_product_picker(
						$selected,
						'product_ids[]',
						'fpl-product-ids',
						FPL_CORE_PRODUCT_MIN,
						FPL_CORE_PRODUCT_MAX
					);
					?>
			<label><input type="checkbox" name="enabled" <?php checked( $post_id ? get_post_meta( $post_id, FPL_Repository::META_ENABLED, true ) : '1', '1' ); ?>> <?php esc_html_e( 'Enabled', 'friend-product-links' ); ?></label>
			<p><button type="submit" class="button button-primary"><?php echo esc_html( $edit ? __( 'Update Link', 'friend-product-links' ) : __( 'Generate Link', 'friend-product-links' ) ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Render friend form.
	 *
	 * @param WP_Post|null $edit Edit post.
	 * @return void
	 */
	private function render_friend_form( $edit = null ) {
		$post_id = $edit ? $edit->ID : 0;
		$has_cache = $post_id ? $this->friend_has_valid_cache( $post_id ) : false;
		?>
		<h2><?php echo esc_html( $edit ? __( 'Edit Core Friend', 'friend-product-links' ) : __( 'Add Core Friend', 'friend-product-links' ) ); ?></h2>
		<form method="post" class="fpl-panel">
			<?php wp_nonce_field( 'fpl_save_friend' ); ?>
			<input type="hidden" name="fpl_action" value="save_friend">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php $this->field_text( 'friend_name', __( 'Friend Name', 'friend-product-links' ), $post_id ? get_post_meta( $post_id, FPL_Repository::META_FRIEND_NAME, true ) : '' ); ?>
			<?php $this->field_url( 'friend_site_url', __( 'Friend Website URL', 'friend-product-links' ), $post_id ? get_post_meta( $post_id, FPL_Repository::META_FRIEND_SITE_URL, true ) : '' ); ?>
			<?php $this->field_url( 'feed_url', __( 'Friend Product Link', 'friend-product-links' ), $post_id ? get_post_meta( $post_id, FPL_Repository::META_FEED_URL, true ) : '' ); ?>
			<?php $this->field_number( 'sort_order', __( 'Sort Order', 'friend-product-links' ), $post_id ? get_post_meta( $post_id, FPL_Repository::META_SORT_ORDER, true ) : 10 ); ?>
			<p><label for="fpl-note"><?php esc_html_e( 'Note', 'friend-product-links' ); ?></label><br><textarea id="fpl-note" name="note" rows="3" class="large-text"><?php echo esc_textarea( $post_id ? get_post_meta( $post_id, FPL_Repository::META_NOTE, true ) : '' ); ?></textarea></p>
			<label><input type="checkbox" name="enabled" <?php checked( $post_id ? get_post_meta( $post_id, FPL_Repository::META_ENABLED, true ) : '1', '1' ); ?>> <?php esc_html_e( 'Enable when valid cache exists', 'friend-product-links' ); ?></label>
			<?php if ( ! $has_cache ) : ?>
				<p class="description"><?php esc_html_e( 'This friend cannot be enabled until Fetch & Preview succeeds.', 'friend-product-links' ); ?></p>
			<?php endif; ?>
			<p>
				<button class="button button-primary" name="sync_now" value="1"><?php esc_html_e( 'Fetch & Preview', 'friend-product-links' ); ?></button>
				<button class="button"><?php esc_html_e( 'Save Without Sync', 'friend-product-links' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render cached product preview.
	 *
	 * @param array $products Cached products.
	 * @return void
	 */
	private function render_cached_preview( $products ) {
		?>
		<div class="fpl-preview">
			<?php foreach ( array_slice( $products, 0, FPL_CORE_PRODUCT_MAX ) as $product ) : ?>
				<div class="fpl-preview-card">
					<?php if ( ! empty( $product['image_url'] ) ) : ?>
						<img src="<?php echo esc_url( $product['image_url'] ); ?>" alt="">
					<?php endif; ?>
					<strong><?php echo esc_html( $product['title'] ?? '' ); ?></strong>
					<span><?php echo esc_html( $product['price_text'] ?? '' ); ?></span>
					<?php
					$p_url   = ! empty( $product['product_url'] ) ? FPL_URL_Helper::normalize_url( $product['product_url'] ) : '';
					$s_url   = ! empty( $product['source_url'] ) ? FPL_URL_Helper::normalize_url( $product['source_url'] ) : '';
					$can_view = $p_url && ! is_wp_error( FPL_URL_Helper::is_public_http_url( $p_url ) );
					if ( $can_view && $s_url && ! FPL_URL_Helper::same_host( $s_url, $p_url ) ) {
						$can_view = false;
					}
					?>
					<?php if ( $can_view ) : ?>
					<a href="<?php echo esc_url( $p_url ); ?>" target="_blank" rel="nofollow sponsored noopener noreferrer"><?php esc_html_e( 'View', 'friend-product-links' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

}
