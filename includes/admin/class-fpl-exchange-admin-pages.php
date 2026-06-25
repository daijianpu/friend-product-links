<?php
/**
 * Exchange admin page renderers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Exchange_Admin_Pages extends FPL_Admin_View_Helper {
	/**
	 * Render Exchange My Offer page.
	 *
	 * @return void
	 */
	public function render_my_offer_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$enabled   = FPL_Exchange_Repository::is_offer_enabled();
		$selected  = FPL_Exchange_Repository::get_offer_product_ids();
		$offer_url = FPL_Exchange_Repository::get_offer_url();
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Exchange My Offer', 'friend-product-links' ); ?></h1>
			<?php $this->render_exchange_notice(); ?>
			<p><?php printf(
					/* translators: %1$d: min products, %2$d: max products, %3$d: display products */
					esc_html__( 'Choose %1$d to %2$d products you are willing to exchange. Partner sites will display %3$d products from this offer.', 'friend-product-links' ),
					esc_html( FPL_EXCHANGE_MIN_PRODUCTS ),
					esc_html( FPL_EXCHANGE_MAX_PRODUCTS ),
					esc_html( FPL_EXCHANGE_DISPLAY_PRODUCTS )
				); ?></p>

			<?php if ( ! FPL_Plugin::is_woocommerce_active() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'WooCommerce is required to create an exchange offer.', 'friend-product-links' ); ?></p></div>
				<?php
				return;
			endif;
			?>

			<?php
			$exchange_mode = FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled();
			$catalog_ready = FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready();
			if ( ! $exchange_mode ) :
			?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Exchange Catalog Mode is not enabled. Enable it before creating an exchange offer.', 'friend-product-links' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fpl-settings' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'friend-product-links' ); ?></a></p></div>
			<?php elseif ( ! $catalog_ready ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Exchange Catalog page is missing or invalid. Please repair it before creating an exchange offer.', 'friend-product-links' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $exchange_mode && $catalog_ready ) : ?>
			<form method="post" class="fpl-panel" data-fpl-product-form="1" data-fpl-products-required-when-enabled="enabled">
				<?php wp_nonce_field( 'fpl_save_exchange_offer' ); ?>
				<input type="hidden" name="fpl_action" value="save_exchange_offer">

				<p>
					<label>
						<input type="checkbox" name="enabled" <?php checked( $enabled ); ?>>
						<?php esc_html_e( 'Enable Exchange Offer', 'friend-product-links' ); ?>
					</label>
				</p>

				<?php
				$this->render_product_picker(
					$selected,
					'exchange_product_ids[]',
					'fpl-exchange-product-ids',
					FPL_EXCHANGE_MIN_PRODUCTS,
					FPL_EXCHANGE_MAX_PRODUCTS
				);
				?>

				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Offer', 'friend-product-links' ); ?>
					</button>
				</p>
			</form>
			<?php endif; ?>

			<?php if ( $enabled && $exchange_mode && $catalog_ready ) : ?>
				<div class="fpl-panel">
					<h2><?php esc_html_e( 'Your Exchange Offer URL', 'friend-product-links' ); ?></h2>
					<p>
						<input class="large-text code fpl-copy-field" readonly value="<?php echo esc_url( $offer_url ); ?>">
						<button type="button" class="button fpl-copy-button"><?php esc_html_e( 'Copy Link', 'friend-product-links' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Share this URL with a friend store to start an exchange.', 'friend-product-links' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Start Exchange page.
	 *
	 * @return void
	 */
	public function render_start_exchange_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$catalog_ready_result = FPL_Exchange_Catalog_Page_Manager::require_catalog_ready_or_error();
		$offer_ready_result   = FPL_Exchange_Repository::validate_local_offer();
		$woo_active           = FPL_Plugin::is_woocommerce_active();
		$ready                = ! is_wp_error( $catalog_ready_result ) && ! is_wp_error( $offer_ready_result ) && $woo_active;
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Start Exchange', 'friend-product-links' ); ?></h1>
			<?php $this->render_exchange_notice(); ?>
			<p><?php esc_html_e( 'Paste the Exchange Offer URL shared by another store owner. Your own Exchange Catalog Mode and Exchange Offer must be ready before you can send a request.', 'friend-product-links' ); ?></p>

			<?php if ( ! $woo_active ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'WooCommerce is required to send exchange requests.', 'friend-product-links' ); ?></p></div>
			<?php elseif ( is_wp_error( $catalog_ready_result ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Exchange Catalog Mode is not enabled or catalog page is not ready.', 'friend-product-links' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fpl-settings' ) ); ?>"><?php esc_html_e( 'Go to Settings', 'friend-product-links' ); ?></a></p></div>
			<?php elseif ( is_wp_error( $offer_ready_result ) ) : ?>
				<div class="notice notice-error inline"><p><?php printf(
						/* translators: %1$d: min products, %2$d: max products */
						esc_html__( 'Your Exchange Offer is not ready. Please enable it and select %1$d to %2$d public WooCommerce products.', 'friend-product-links' ),
						esc_html( FPL_EXCHANGE_MIN_PRODUCTS ),
						esc_html( FPL_EXCHANGE_MAX_PRODUCTS )
					); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=fpl-exchange-offer' ) ); ?>"><?php esc_html_e( 'Set up offer', 'friend-product-links' ); ?></a></p></div>
			<?php endif; ?>

			<form method="post" class="fpl-panel" data-fpl-confirm="<?php esc_attr_e( 'Send this exchange request to the remote store?', 'friend-product-links' ); ?>">
				<?php wp_nonce_field( 'fpl_send_exchange_request' ); ?>
				<input type="hidden" name="fpl_action" value="send_exchange_request">

				<?php $this->field_url( 'partner_offer_url', __( 'Partner Exchange Offer URL', 'friend-product-links' ), '' ); ?>

				<p>
					<button type="submit" class="button button-primary" <?php disabled( ! $ready ); ?>>
						<?php esc_html_e( 'Send Exchange Request', 'friend-product-links' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Exchange Requests page.
	 *
	 * @return void
	 */
	public function render_exchange_requests_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$incoming = FPL_CPT::get_exchange_requests( 'incoming' );
		$outgoing = FPL_CPT::get_exchange_requests( 'outgoing' );
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Exchange Requests', 'friend-product-links' ); ?></h1>
			<?php $this->render_exchange_notice(); ?>

			<h2><?php esc_html_e( 'Incoming Requests', 'friend-product-links' ); ?></h2>
			<table class="widefat striped fpl-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Partner', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Website', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Products', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Status', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Last Error', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'friend-product-links' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $incoming ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No incoming requests.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $incoming as $req ) : ?>
						<?php
						$site_name   = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_SITE_NAME, true );
						$site_url    = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_SITE_URL, true );
						$offer_url   = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_OFFER_URL, true );
						$status      = get_post_meta( $req->ID, FPL_Exchange_Repository::META_STATUS, true );
						$error       = get_post_meta( $req->ID, FPL_Exchange_Repository::META_LAST_ERROR, true );
						$prod_count  = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_PRODUCT_COUNT, true );
						?>
						<tr>
							<td><?php $this->render_value_or_empty( $site_name ); ?></td>
							<td><?php $this->render_external_link_or_empty( $site_url ); ?></td>
							<td><?php $this->render_value_or_empty( '' === (string) $prod_count ? '' : absint( $prod_count ) ); ?></td>
							<td><?php $this->render_value_or_empty( $status ); ?></td>
							<td><?php $this->render_value_or_empty( $error ); ?></td>
							<td>
								<div class="fpl-row-actions">
								<?php $this->render_external_link_or_empty( $offer_url, __( 'View Offer', 'friend-product-links' ), 'button' ); ?>
								<?php if ( in_array( $status, array( 'pending', 'failed' ), true ) ) : ?>
									<form method="post" class="fpl-inline-form fpl-action-form" data-fpl-confirm="<?php esc_attr_e( 'Approve this exchange request and activate this partner?', 'friend-product-links' ); ?>">
										<?php wp_nonce_field( 'fpl_approve_exchange_request' ); ?>
										<input type="hidden" name="fpl_action" value="approve_exchange_request">
										<input type="hidden" name="post_id" value="<?php echo esc_attr( $req->ID ); ?>">
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'friend-product-links' ); ?></button>
									</form>
									<form method="post" class="fpl-inline-form fpl-action-form" data-fpl-confirm="<?php esc_attr_e( 'Reject this exchange request? The partner may be notified.', 'friend-product-links' ); ?>">
										<?php wp_nonce_field( 'fpl_reject_exchange_request' ); ?>
										<input type="hidden" name="fpl_action" value="reject_exchange_request">
										<input type="hidden" name="post_id" value="<?php echo esc_attr( $req->ID ); ?>">
										<button type="submit" class="button"><?php esc_html_e( 'Reject', 'friend-product-links' ); ?></button>
									</form>
								<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Outgoing Requests', 'friend-product-links' ); ?></h2>
			<table class="widefat striped fpl-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Partner', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Website', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Products', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Status', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Last Error', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'friend-product-links' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $outgoing ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No outgoing requests.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $outgoing as $req ) : ?>
						<?php
						$site_name  = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_SITE_NAME, true );
						$site_url   = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_SITE_URL, true );
						$status     = get_post_meta( $req->ID, FPL_Exchange_Repository::META_STATUS, true );
						$error      = get_post_meta( $req->ID, FPL_Exchange_Repository::META_LAST_ERROR, true );
						$prod_count = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_PRODUCT_COUNT, true );
						$offer_url  = get_post_meta( $req->ID, FPL_Exchange_Repository::META_REMOTE_OFFER_URL, true );
						?>
						<tr>
							<td><?php $this->render_value_or_empty( $site_name ); ?></td>
							<td><?php $this->render_external_link_or_empty( $site_url ); ?></td>
							<td><?php $this->render_value_or_empty( '' === (string) $prod_count ? '' : absint( $prod_count ) ); ?></td>
							<td><?php $this->render_value_or_empty( $status ); ?></td>
							<td><?php $this->render_value_or_empty( $error ); ?></td>
							<td>
								<div class="fpl-row-actions">
<?php $this->render_external_link_or_empty( $offer_url, __( 'View Offer', 'friend-product-links' ), 'button' ); ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Exchange Partners page.
	 *
	 * @return void
	 */
	public function render_exchange_partners_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$partners = FPL_CPT::get_exchange_partners();
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Exchange Partners', 'friend-product-links' ); ?></h1>
			<?php $this->render_exchange_notice(); ?>
			<p><?php printf(
					/* translators: %d: display products count */
					esc_html__( 'Active exchange partners. Each partner appears in your Exchange Catalog with up to %d products.', 'friend-product-links' ),
					esc_html( FPL_EXCHANGE_DISPLAY_PRODUCTS )
				); ?></p>

			<table class="widefat striped fpl-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Partner', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Website', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Status', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Products', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Last Sync', 'friend-product-links' ); ?></th>
							<th><?php esc_html_e( 'Clicks Sent', 'friend-product-links' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'friend-product-links' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $partners ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No exchange partners yet.', 'friend-product-links' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $partners as $partner ) : ?>
						<?php
						$site_name    = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_SITE_NAME, true );
						$site_url     = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_SITE_URL, true );
						$status       = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_STATUS, true );
						$products     = FPL_Exchange_Repository::get_partner_cached_products( $partner->ID );
						$last_sync    = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_LAST_SYNC_AT, true );
						$offer_url    = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_OFFER_URL, true );
						$clicks_sent  = absint( get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_CLICKS_SENT, true ) );
						$pause_reason = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_PAUSE_REASON, true );
						$paused_at    = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_PAUSED_AT, true );
						$is_active    = 'active' === $status;
						$is_paused    = 'paused_safety' === $status;
						$can_sync     = in_array( $status, array( 'active', 'degraded_missing_catalog', 'degraded_remote_offer' ), true );
						$last_error   = get_post_meta( $partner->ID, FPL_Exchange_Repository::META_PARTNER_LAST_ERROR, true );
						$product_count = is_array( $products ) ? count( $products ) : 0;
						?>
						<tr>
							<td><?php $this->render_value_or_empty( $site_name ); ?></td>
							<td><?php $this->render_external_link_or_empty( $site_url ); ?></td>
							<td>
								<?php $this->render_value_or_empty( $status ); ?>
								<?php if ( $pause_reason ) : ?>
									<br><span class="fpl-error"><?php echo esc_html( $pause_reason ); ?></span>
									<?php if ( $paused_at ) : ?>
										<br><small><?php echo esc_html( $paused_at ); ?></small>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $last_error ) : ?>
									<br><span class="fpl-error"><?php echo esc_html( $last_error ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( sprintf( '%1$d / %2$d', $product_count, FPL_EXCHANGE_DISPLAY_PRODUCTS ) ); ?>
								<?php if ( 'active' === $status && $product_count < FPL_EXCHANGE_DISPLAY_PRODUCTS ) : ?>
									<br><span class="fpl-warning"><?php esc_html_e( 'Below display count.', 'friend-product-links' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php $this->render_value_or_empty( $last_sync ); ?></td>
								<td><?php echo esc_html( $clicks_sent ); ?></td>
							<td>
								<div class="fpl-row-actions">
								<?php $this->render_external_link_or_empty( $offer_url, __( 'View Offer', 'friend-product-links' ), 'button' ); ?>
				<?php if ( $can_sync ) : ?>
								<form method="post" class="fpl-inline-form fpl-action-form">
									<?php wp_nonce_field( 'fpl_sync_exchange_partner' ); ?>
									<input type="hidden" name="fpl_action" value="sync_exchange_partner">
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $partner->ID ); ?>">
									<button type="submit" class="button"><?php esc_html_e( 'Sync Now', 'friend-product-links' ); ?></button>
								</form>
							<?php endif; ?>
								<?php if ( $is_active ) : ?>
								<form method="post" class="fpl-inline-form fpl-action-form" data-fpl-confirm="<?php esc_attr_e( 'This will pause the partner. Continue?', 'friend-product-links' ); ?>">
									<?php wp_nonce_field( 'fpl_emergency_pause_partner' ); ?>
									<input type="hidden" name="fpl_action" value="emergency_pause_partner">
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $partner->ID ); ?>">
									<input type="text" name="pause_reason" placeholder="<?php esc_attr_e( 'Reason', 'friend-product-links' ); ?>" required class="fpl-action-form-inline-field">
									<button type="submit" class="button"><?php esc_html_e( 'Pause', 'friend-product-links' ); ?></button>
								</form>
								<?php endif; ?>
								<?php if ( $is_paused ) : ?>
								<form method="post" class="fpl-inline-form fpl-action-form" data-fpl-confirm="<?php esc_attr_e( 'Resume this exchange partner?', 'friend-product-links' ); ?>">
									<?php wp_nonce_field( 'fpl_resume_exchange_partner' ); ?>
									<input type="hidden" name="fpl_action" value="resume_exchange_partner">
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $partner->ID ); ?>">
									<button type="submit" class="button"><?php esc_html_e( 'Resume', 'friend-product-links' ); ?></button>
								</form>
								<?php endif; ?>
								<form method="post" class="fpl-inline-form fpl-action-form" data-fpl-confirm="<?php esc_attr_e( 'Deleting this partner removes it from your Exchange Catalog. Continue?', 'friend-product-links' ); ?>">
									<?php wp_nonce_field( 'fpl_delete_exchange_partner' ); ?>
									<input type="hidden" name="fpl_action" value="delete_exchange_partner">
									<input type="hidden" name="post_id" value="<?php echo esc_attr( $partner->ID ); ?>">
									<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete', 'friend-product-links' ); ?></button>
								</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Exchange Catalog page.
	 *
	 * @return void
	 */
	public function render_exchange_catalog_page() {
		if ( ! FPL_CPT::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage Friend Product Links.', 'friend-product-links' ) );
		}

		$exchange_enabled = FPL_Exchange_Catalog_Page_Manager::is_exchange_mode_enabled();
		$catalog_ready    = FPL_Exchange_Catalog_Page_Manager::is_catalog_page_ready();
		$catalog_status   = FPL_Exchange_Catalog_Page_Manager::get_catalog_page_status_text();
		$page_id          = FPL_Exchange_Catalog_Page_Manager::get_catalog_page_id();
		$page_url         = FPL_Exchange_Catalog_Page_Manager::get_catalog_page_url();
		?>
		<div class="wrap fpl-admin">
			<h1><?php esc_html_e( 'Exchange Catalog', 'friend-product-links' ); ?></h1>
			<?php $this->render_exchange_notice(); ?>
			<p><?php esc_html_e( 'The Exchange Catalog displays products from approved exchange partners. The catalog page is created and repaired by Exchange Catalog Mode; do not use a separate manual page to bypass this flow.', 'friend-product-links' ); ?></p>

			<div class="fpl-panel">
				<h2><?php esc_html_e( 'Catalog Status', 'friend-product-links' ); ?></h2>
				<p>
					<strong><?php esc_html_e( 'Exchange Catalog Mode:', 'friend-product-links' ); ?></strong>
					<?php echo $exchange_enabled ? esc_html__( 'Enabled', 'friend-product-links' ) : esc_html__( 'Disabled', 'friend-product-links' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Catalog Page Status:', 'friend-product-links' ); ?></strong>
					<?php echo esc_html( $catalog_status ); ?>
				</p>
				<?php if ( $page_url ) : ?>
					<p>
						<strong><?php esc_html_e( 'Bound Catalog Page:', 'friend-product-links' ); ?></strong>
						<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $page_url ); ?></a>
						<?php if ( $page_id ) : ?>
							| <a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>"><?php esc_html_e( 'Edit Page', 'friend-product-links' ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'The bound page must be published and contain the [fpl_exchange_catalog] shortcode. Exchange offers, requests, approvals, sync, rendering, and clicks depend on this page being ready.', 'friend-product-links' ); ?></p>
			</div>

			<form method="post" class="fpl-panel" data-fpl-confirm="<?php echo $catalog_ready ? esc_attr__( 'Repair the Exchange Catalog page? This may overwrite existing content.', 'friend-product-links' ) : esc_attr__( 'Create the Exchange Catalog page now?', 'friend-product-links' ); ?>">
				<?php wp_nonce_field( 'fpl_create_catalog_page' ); ?>
				<input type="hidden" name="fpl_action" value="create_catalog_page">
				<h2><?php esc_html_e( 'Create / Repair Exchange Catalog Page', 'friend-product-links' ); ?></h2>
				<?php if ( ! $exchange_enabled ) : ?>
					<p><?php esc_html_e( 'Enable Exchange Catalog Mode in Settings before creating or repairing the bound catalog page.', 'friend-product-links' ); ?></p>
				<?php elseif ( $catalog_ready ) : ?>
					<p><?php esc_html_e( 'Your Exchange Catalog page is ready. Use this button only if you need to repair the bound page.', 'friend-product-links' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Your Exchange Catalog page is missing or invalid. Repair it before participating in exchanges.', 'friend-product-links' ); ?></p>
				<?php endif; ?>
				<p>
					<button type="submit" class="button button-primary" <?php disabled( ! $exchange_enabled ); ?>>
						<?php esc_html_e( 'Create / Repair Exchange Catalog Page', 'friend-product-links' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Exchange partners notice messages.
	 *
	 * Override the parent method to handle exchange-partner message keys
	 * when the page slug implies we are on an exchange partners view.
	 *
	 * @return void
	 */
	protected function render_exchange_notice() {
		if ( empty( $_GET['fpl_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$message = sanitize_key( wp_unslash( $_GET['fpl_message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$texts   = array(
			'saved'                     => __( 'Saved.', 'friend-product-links' ),
			'share_needs_products'      => sprintf(
				/* translators: %d: minimum number of public WooCommerce products */
				__( 'Please select at least %d public WooCommerce products.', 'friend-product-links' ),
				FPL_EXCHANGE_MIN_PRODUCTS
			),
			'share_invalid_products'    => __( 'The selected items were filtered out. Please choose published, visible WooCommerce products only. Variations, drafts, private, hidden, and deleted products cannot be used.', 'friend-product-links' ),
			'share_too_many_products'   => sprintf(
				/* translators: %d: maximum number of public WooCommerce products */
				__( 'Please select no more than %d public WooCommerce products.', 'friend-product-links' ),
				FPL_EXCHANGE_MAX_PRODUCTS
			),
			'save_failed'               => __( 'Save failed. Please try again.', 'friend-product-links' ),
			'bad_url'                   => __( 'The partner offer URL failed validation.', 'friend-product-links' ),
			'exchange_offer_disabled'   => __( 'Your exchange offer is not enabled. Please enable it before sending requests.', 'friend-product-links' ),
			'exchange_fetch_failed'     => __( 'Could not fetch or validate the partner exchange offer.', 'friend-product-links' ),
			'exchange_duplicate'        => __( 'An exchange request for this partner already exists.', 'friend-product-links' ),
			'exchange_send_failed'      => __( 'Failed to send the exchange request to the partner.', 'friend-product-links' ),
			'exchange_request_sent'     => __( 'Exchange request sent successfully.', 'friend-product-links' ),
			'exchange_partner_created'  => __( 'Exchange partner created. Products cached.', 'friend-product-links' ),
			'exchange_request_rejected' => __( 'Exchange request rejected.', 'friend-product-links' ),
			'exchange_partner_paused'   => __( 'Exchange partner paused.', 'friend-product-links' ),
			'pause_reason_required'     => __( 'Please enter a reason for pausing the partner.', 'friend-product-links' ),
			'exchange_callback_failed'  => __( 'Partner approval callback failed. The exchange was not activated.', 'friend-product-links' ),
			'synced'                    => __( 'Partner synced successfully.', 'friend-product-links' ),
			'exchange_partner_resumed'  => __( 'Exchange partner resumed.', 'friend-product-links' ),
			'exchange_partner_deleted'  => __( 'Exchange partner deleted.', 'friend-product-links' ),
			'exchange_partner_sync_skipped' => __( 'Partner sync was skipped because this partner is not in a syncable state.', 'friend-product-links' ),
			'exchange_partner_sync_degraded' => __( 'Partner synced but the sync result is degraded. Check for errors.', 'friend-product-links' ),
			'exchange_self_request' => __( 'You cannot exchange with your own store.', 'friend-product-links' ),
			'exchange_mode_disabled' => __( 'Exchange Catalog Mode must be enabled before creating or repairing the catalog page.', 'friend-product-links' ),
		);

		if ( isset( $texts[ $message ] ) ) {
			$error_messages = array(
				'share_needs_products',
				'share_invalid_products',
				'share_too_many_products',
				'save_failed',
				'bad_url',
				'exchange_offer_disabled',
				'exchange_fetch_failed',
				'exchange_duplicate',
				'exchange_send_failed',
				'exchange_callback_failed',
				'exchange_partner_sync_skipped',
				'exchange_self_request',
				'exchange_mode_disabled',
			);
			$warning_messages = array(
				'exchange_partner_sync_degraded',
			);
			if ( in_array( $message, $error_messages, true ) ) {
				$class = 'notice notice-error is-dismissible';
			} elseif ( in_array( $message, $warning_messages, true ) ) {
				$class = 'notice notice-warning is-dismissible';
			} else {
				$class = 'notice notice-success is-dismissible';
			}
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $texts[ $message ] ) );
		}
	}
}
