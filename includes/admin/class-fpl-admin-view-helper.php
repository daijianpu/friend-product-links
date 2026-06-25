<?php
/**
 * Admin view helper methods.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Admin_View_Helper {
	/**
	 * Render action form.
	 *
	 * @param string $action Action key.
	 * @param int    $post_id Post ID.
	 * @param string $label Button label.
	 * @param string $class Extra class.
	 * @param string $confirm Confirm message.
	 * @return void
	 */
	protected function render_post_action_form( $action, $post_id, $label, $class = '', $confirm = '' ) {
		?>
		<form method="post" class="fpl-inline-form fpl-action-form" <?php echo $confirm ? 'data-fpl-confirm="' . esc_attr( $confirm ) . '"' : ''; ?>>
			<?php wp_nonce_field( 'fpl_' . $action ); ?>
			<input type="hidden" name="fpl_action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<button class="button <?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Render enable/disable form.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $enabled Current state.
	 * @return void
	 */
	protected function render_toggle_form( $post_id, $enabled ) {
		?>
		<form method="post" class="fpl-inline-form fpl-action-form">
			<?php wp_nonce_field( 'fpl_toggle_status' ); ?>
			<input type="hidden" name="fpl_action" value="toggle_status">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<input type="hidden" name="desired_status" value="<?php echo esc_attr( $enabled ? '0' : '1' ); ?>">
			<button class="button"><?php echo esc_html( $enabled ? __( 'Disable', 'friend-product-links' ) : __( 'Enable', 'friend-product-links' ) ); ?></button>
		</form>
		<?php
	}

	/**
	 * Check friend cache.
	 *
	 * @param int $post_id Friend post ID.
	 * @return bool
	 */
	protected function friend_has_valid_cache( $post_id ) {
		return FPL_Repository::friend_has_valid_cache( $post_id );
	}

	/**
	 * Get standardized empty value markup for admin tables.
	 *
	 * @return string
	 */
	protected function get_empty_value_html() {
		return '<span class="fpl-muted" aria-label="' . esc_attr__( 'No value', 'friend-product-links' ) . '">-</span>';
	}

	/**
	 * Render standardized empty value markup.
	 *
	 * @return void
	 */
	protected function render_empty_value() {
		echo $this->get_empty_value_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render escaped text or a standardized empty value.
	 *
	 * Important: numeric zero is a valid value and must not be treated as empty.
	 *
	 * @param mixed $value Value.
	 * @return void
	 */
	protected function render_value_or_empty( $value ) {
		if ( null === $value || '' === trim( (string) $value ) ) {
			$this->render_empty_value();
			return;
		}

		echo esc_html( $value );
	}

	/**
	 * Render an external link or a standardized empty value.
	 *
	 * @param string $url Link URL.
	 * @param string $label Optional label.
	 * @param string $class Optional CSS class.
	 * @return void
	 */
	protected function render_external_link_or_empty( $url, $label = '', $class = '' ) {
		$raw_url = trim( (string) $url );

		if ( '' === $raw_url ) {
			$this->render_empty_value();
			return;
		}

		$valid = FPL_URL_Helper::validate_identity_http_url( $raw_url );
		if ( is_wp_error( $valid ) ) {
			$this->render_empty_value();
			return;
		}

		$label = '' !== trim( (string) $label ) ? $label : $raw_url;

		printf(
			'<a%1$s href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
			$class ? ' class="' . esc_attr( $class ) . '"' : '',
			esc_url( $raw_url ),
			esc_html( $label )
		);
	}

	/**
	 * Format CTR.
	 *
	 * @param int $clicks Click count.
	 * @param int $display Display count.
	 * @return string
	 */
	protected function format_ctr( $clicks, $display ) {
		$display = absint( $display );
		if ( 0 === $display ) {
			return '';
		}

		return number_format_i18n( ( absint( $clicks ) / $display ) * 100, 1 ) . '%';
	}

	/**
	 * Format last stats sent state.
	 *
	 * @param int $friend_id Friend ID.
	 * @return string
	 */
	protected function format_stats_sent( $friend_id ) {
		$status = get_post_meta( $friend_id, FPL_Repository::META_LAST_STATS_SENT_STATUS, true );
		$time = get_post_meta( $friend_id, FPL_Repository::META_LAST_STATS_SENT_TIME, true );
		$error = get_post_meta( $friend_id, FPL_Repository::META_LAST_STATS_SENT_ERROR, true );

		if ( ! $status ) {
			return '';
		}

		$text = ucfirst( sanitize_text_field( $status ) );
		if ( $time ) {
			$text .= ', ' . $time;
		}
		if ( $error ) {
			$text .= ' - ' . $error;
		}

		return $text;
	}

	/**
	 * Render text field.
	 *
	 * @param string $name Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function field_text( $name, $label, $value = '' ) {
		printf(
			'<p><label for="fpl-%1$s">%2$s</label><br><input id="fpl-%1$s" name="%1$s" type="text" class="regular-text" value="%3$s"></p>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $value )
		);
	}

	/**
	 * Render URL field.
	 *
	 * @param string $name Name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	protected function field_url( $name, $label, $value = '' ) {
		printf(
			'<p><label for="fpl-%1$s">%2$s</label><br><input id="fpl-%1$s" name="%1$s" type="url" class="regular-text code" value="%3$s"></p>',
			esc_attr( $name ),
			esc_html( $label ),
			esc_attr( $value )
		);
	}

	/**
	 * Render number field.
	 *
	 * @param string $name Name.
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @return void
	 */
	protected function field_number( $name, $label, $value = 0 ) {
		printf(
			'<p><label for="fpl-%1$s">%2$s</label><br><input id="fpl-%1$s" name="%1$s" type="number" min="0" class="small-text" value="%3$d"></p>',
			esc_attr( $name ),
			esc_html( $label ),
			absint( $value )
		);
	}

	/**
	 * Render WooCommerce product selector.
	 *
	 * @param int[]  $selected  Selected product IDs.
	 * @param string $field_name Form field name.
	 * @param string $select_id  Select element ID.
	 * @param int    $min        Minimum selection.
	 * @param int    $max        Maximum selection.
	 * @param string $description Description text.
	 * @return void
	 */
	protected function render_product_picker( $selected = array(), $field_name = 'product_ids[]', $select_id = 'fpl-product-ids', $min = 3, $max = 9, $description = '' ) {
		if ( ! $description ) {
			/* translators: %d: minimum, %d: maximum */
			$description = sprintf( __( 'Search and select %1$d to %2$d public WooCommerce products.', 'friend-product-links' ), $min, $max );
		}
		?>
		<p>
			<label for="<?php echo esc_attr( $select_id ); ?>"><?php esc_html_e( 'Selected Products', 'friend-product-links' ); ?></label><br>
			<select
				id="<?php echo esc_attr( $select_id ); ?>"
				name="<?php echo esc_attr( $field_name ); ?>"
				class="wc-product-search fpl-product-select"
				multiple="multiple"
				data-placeholder="<?php esc_attr_e( 'Search for products', 'friend-product-links' ); ?>"
				data-action="woocommerce_json_search_products"
				data-allow_clear="true"
				data-min="<?php echo esc_attr( $min ); ?>"
				data-max="<?php echo esc_attr( $max ); ?>"
			>
				<?php foreach ( FPL_Security::filter_valid_product_ids( $selected, $max ) as $product_id ) : ?>
				<option value="<?php echo esc_attr( $product_id ); ?>" selected><?php echo esc_html( get_the_title( $product_id ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php echo esc_html( $description ); ?></span>
		</p>
		<?php
	}

	/**
	 * Render action notice.
	 *
	 * @return void
	 */
	protected function render_notice() {
		if ( empty( $_GET['fpl_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$message = sanitize_key( wp_unslash( $_GET['fpl_message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$texts = array(
			'saved'                => __( 'Saved.', 'friend-product-links' ),
			'deleted'              => __( 'Deleted.', 'friend-product-links' ),
			'token_reset'          => __( 'Token reset. The old friend product link is no longer valid.', 'friend-product-links' ),
			'synced'                => __( 'Friend feed synced and cached.', 'friend-product-links' ),
			'synced_but_disabled'   => __( 'Sync succeeded, but this core friend is still disabled. Enable it to display products on single product pages.', 'friend-product-links' ),
			'sync_failed'           => __( 'Sync failed. Existing valid cache and enabled status were preserved unless the feed was removed or URLs changed.', 'friend-product-links' ),
			'friend_limit'         => sprintf(
				/* translators: %d: maximum number of core friends */
				__( 'You can add up to %d core friends in this version.', 'friend-product-links' ),
				FPL_CORE_FRIEND_LIMIT
			),
			'bad_url'              => __( 'The friend product link failed safety validation.', 'friend-product-links' ),
			'bad_friend_site'      => __( 'Please enter a valid http(s) friend website URL.', 'friend-product-links' ),
			'bad_boundary'         => __( 'Friend website and product feed URLs must be public http(s) URLs on the same host.', 'friend-product-links' ),
			'share_needs_products' => sprintf(
				/* translators: %d: minimum number of public WooCommerce products */
				__( 'Please select at least %d public WooCommerce products.', 'friend-product-links' ),
				FPL_CORE_PRODUCT_MIN
			),
			'share_invalid_products' => __( 'The selected items were filtered out. Please choose published, visible WooCommerce products only. Variations, drafts, private, hidden, and deleted products cannot be used.', 'friend-product-links' ),
			'share_too_many_products' => sprintf(
				/* translators: %d: maximum number of public WooCommerce products */
				__( 'Please select no more than %d public WooCommerce products.', 'friend-product-links' ),
				FPL_CORE_PRODUCT_MAX
			),
			'status_updated'       => __( 'Status updated.', 'friend-product-links' ),
			'friend_needs_cache'   => __( 'This friend cannot be enabled until it has a valid cached product preview.', 'friend-product-links' ),
			'friend_needs_resync'   => __( 'Friend URLs changed. The old cache was disabled; fetch and preview again to enable this friend.', 'friend-product-links' ),
			'friend_saved_needs_sync' => __( 'Friend saved. Fetch and preview it before enabling display.', 'friend-product-links' ),
			'save_failed'           => __( 'Save failed. Please try again.', 'friend-product-links' ),
			'exchange_has_active_partners' => __( 'Exchange Catalog Mode cannot be disabled while you have active exchange partners. Pause or remove active partners first.', 'friend-product-links' ),
			'exchange_offer_disabled' => __( 'Exchange Catalog Mode is disabled or the Exchange Catalog page is not ready.', 'friend-product-links' ),
			'exchange_catalog_repaired' => __( 'Exchange Catalog page repaired.', 'friend-product-links' ),
		);

		if ( isset( $texts[ $message ] ) ) {
			$error_messages = array(
				'bad_url',
				'bad_friend_site',
				'bad_boundary',
				'share_needs_products',
				'share_invalid_products',
				'share_too_many_products',
				'friend_needs_cache',
				'friend_needs_resync',
				'save_failed',
				'sync_failed',
				'exchange_has_active_partners',
				'exchange_offer_disabled',
			);
			$class = in_array( $message, $error_messages, true ) ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $texts[ $message ] ) );
		}
	}

	/**
	 * Redirect after action.
	 *
	 * @param string $page Page slug.
	 * @param string $message Message key.
	 * @param array  $extra Extra query args.
	 * @return void
	 */
	protected function redirect_with_message( $page, $message, $extra = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'fpl_message' => $message ), $extra ), admin_url( 'admin.php' ) ) );
		exit;
	}

}
