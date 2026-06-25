=== Friend Product Links ===
Contributors: eezz.net
Tags: woocommerce, product feed, product links, friends
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.42
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A free P2P WooCommerce plugin for exchanging selected friend product links with local caching and aggregated performance stats.

== Description ==

Friend Product Links lets WooCommerce store owners share selected products with trusted friend stores.

Each store can generate a private Friend Product Link, send it to a friend, add up to 3 friend links, cache up to 9 products per friend, display up to 4 products from each core friend on WooCommerce product pages, and exchange products on a dedicated Exchange Catalog page.

Aggregated local click stats help evaluate friend product placements and sponsored partner slots. The stats stay privacy-friendly: no cookies, no visitor profiles, no IP logging, and no browser fingerprinting. Server-side display counts may be affected by full-page cache; click counts are usually more reliable.

The plugin keeps the first version simple:

* No account system.
* No connection to the plugin author's server.
* No JSON copy-paste for normal users.
* Store-to-store sharing through the Friend Product Link.
* Frontend product pages never request friend websites in real time.
* Aggregated stats only.
* No visitor tracking.
* No data sent to the plugin author.

Friend product images are loaded from the connected friend store URLs when displayed. As with any externally hosted image, the friend store may receive normal web request information such as the visitor IP address, browser user agent, and referrer. The plugin itself does not store or transmit visitor identifiers as analytics data. You can disable remote friend product images in the plugin settings.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/friend-product-links`.
2. Activate the plugin through the WordPress Plugins screen.
3. Make sure WooCommerce is active.
4. Open Friend Product Links in the WordPress admin.

== Basic Workflow ==

1. Create a product link under My Product Links.
2. Select 4 to 9 public WooCommerce products.
3. Send the generated Friend Product Link to your friend.
4. Add your friend's link under My Friends.
5. Fetch and preview the friend products.
6. Enable the friend after a successful sync.
7. View aggregate server-rendered displays, clicks, and CTR values in the admin.

== Frequently Asked Questions ==

= Does the plugin contact the plugin author server? =

No. Friend product links are shared directly between stores.

= Does the frontend request friend feeds? =

No. Friend feeds are cached locally and refreshed by WP-Cron or manually by an administrator.

= What performance stats are stored? =

The plugin stores aggregated server-rendered display and click counts locally on your WordPress site. It does not track individual visitors and does not use cookies. Full-page cache may undercount server-rendered displays; click counts are usually more reliable.

= Are stats sent anywhere? =

If stats sharing is enabled, the plugin can send aggregated display and click counts to the connected friend site through the Friend Product Link. It does not send statistics to the plugin author or any central server.

= Why are links marked nofollow sponsored noopener noreferrer? =

Friend links are external links to another store. The plugin uses nofollow sponsored noopener noreferrer by default to keep the first version conservative for search engines and referrer privacy.

= How can I customize the price and button colors? =

This is optional. If you do not add custom CSS, Friend Product Links uses its default frontend colors.

To customize colors, add CSS variables to your theme's Additional CSS:

body .fpl-module {
    --fpl-price-color: #f00000;
    --fpl-button-color: #7c00ff;
    --fpl-button-hover-color: #5f00cc;
    --fpl-button-text-color: #ffffff;
}

Change the hex colors to match your theme. These variables affect only Friend Product Links modules. Leave a variable out to use the plugin default.

== Privacy ==

This plugin stores aggregated display and click counts locally on your WordPress site. If stats sharing is enabled, the plugin can send aggregated display and click counts to the connected friend site through the Friend Product Link. It does not track individual visitors, does not use cookies, and does not send statistics to the plugin author or any central server.

The plugin never sends visitor IP addresses, user agents, user accounts, order data, cookies, or personal data as part of stats sharing.

Friend product images are loaded from the connected friend store URLs when displayed. As with any externally hosted image, the friend store may receive normal web request information such as the visitor IP address, browser user agent, and referrer. The plugin itself does not store or transmit visitor identifiers as analytics data.

== Upgrade Notice ==

= 1.1.42 =
Fixed REST callback canonical-offer failure handling and hardened exchange partner duplicate/error handling.

= 1.1.41 =
Improved exchange offer URL canonical handling, duplicate request protection, invalid URL feedback, and partner repository URL validation.

= 1.1.40 =
Improved exchange notice severity, invalid URL feedback, duplicate partner protection, and cached preview URL safety.

= 1.1.39 =
Improved admin external-link validation, catalog action guarding, partner identity refresh on sync, and settings save consistency.

= 1.1.38 =
Completed backend empty-value helper rollout across Performance, Core Friends, Exchange Requests, and Dashboard rows.

= 1.1.37 =
Admin empty values now use a unified muted dash helper across backend pages.

= 1.1.36 =
Improved exchange resume safety, approval callback consistency, callback token validation, and removed BOM from source files.

= 1.1.35 =
Improved paused partner resume safety, approval callback consistency, callback token validation, and sync degradation for incomplete partner records.

= 1.1.34 =
Improved paused partner resume safety, callback token validation, and admin muted display styling.

= 1.1.33 =
Improved exchange partner degradation behavior, pending partner timestamps, callback token validation, and empty admin URL display.

= 1.1.32 =
Improved exchange request safety, callback idempotency, self-exchange prevention, and admin link output.

= 1.1.31 =
Improved Exchange admin flow safety, request failure visibility, and backend action layout consistency.

= 1.1.28 =
Settings now make it clearer that frontend color customization is optional, and Exchange Catalog Mode is locked on while active partners exist.

= 1.1.27 =
Frontend price and button colors can now be customized with CSS variables in your theme Additional CSS.

= 1.1.26 =
Dashboard onboarding, partner warnings, and Exchange Catalog quick links are now more consistent.

= 1.1.25 =
Fixed Dashboard status edge cases and reduced noisy onboarding messages on empty sites.

= 1.1.24 =
Dashboard warnings are now less noisy for Exchange-only sites, and the Dashboard no longer performs unlimited reads for summary cards.

= 1.1.23 =
Dashboard health warnings are more accurate, especially when Core Friends or Core Product Links are disabled.

= 1.1.22 =
The Dashboard now reports setup health more accurately and remains read-only.

= 1.1.21 =
Friend Product Links now opens to a read-only Dashboard that summarizes setup status, exchange health, and click performance.

= 1.1.20 =
Review Exchange Partners after updating. Paused/degraded partners now have clearer status and recovery actions.

= 1.1.19 =
Purge caches after updating. This release refines frontend price sizing and responsive grid CSS.

= 1.1.18 =
Purge caches after updating. Frontend price styling and responsive product grids now rely on the updated frontend stylesheet.

= 1.1.17 =
Core Friends now render later on single product pages with a higher priority, so store related products can appear first on more themes. Purge caches after updating.

= 1.1.16 =
Core Friends are now displayed later on single product pages, after the store's own related-product area where supported by the active theme. Purge caches after updating.

= 1.1.15 =
Core Product Links now require at least 4 products. Please update existing Core Product Links and re-sync Core Friends after updating.

= 1.1.14 =
After updating, purge caches and re-sync Exchange Partners. Exchange Offers now require at least 4 products.

= 1.1.13 =
Exchange Offers now require at least 4 products. Please update your Exchange Offer products and re-sync Exchange Partners after updating.

= 1.1.12 =
After updating, sync your Core Friends and Exchange Partners again to refresh cached product images and prices.

= 1.1.11 =
After updating, sync your Core Friends and Exchange Partners again to refresh cached product images and prices.

== Changelog ==

= 1.1.42 =
* Fixed a REST callback edge case where canonical offer URL validation could read a WP_Error as an array after a failed remote fetch.
* Matched existing partners by both canonical and legacy expected offer URLs during callback approval.
* Checked both submitted and canonical offer URLs when detecting duplicate outgoing exchange requests.
* Hardened repository-level exchange partner offer URL validation to require public http(s) URLs.
* Surfaced duplicate/invalid partner creation errors more accurately in admin approval flows.
* Returned explicit REST status codes for duplicate or invalid partner creation errors during approval callbacks.
* Kept Empty Value Helper rollout, frontend CSS variables, Dashboard read-only behavior, and Exchange state-machine fixes intact.

= 1.1.41 =
* Marked Exchange Catalog Mode disabled create/repair notices as errors instead of success notices.
* Validated Start Exchange partner offer URLs before remote fetch attempts.
* Validated and used remote offer_url from exchange offer payloads as the canonical partner offer URL.
* Rechecked incoming exchange request duplicates after fetching canonical remote offer URLs.
* Rejected empty or invalid partner offer URLs at the repository layer.
* Allowed approval callbacks to match either the stored expected offer URL or the verified canonical remote offer URL.
* Cleaned high-risk admin table action markup formatting.
* Kept Empty Value Helper rollout, frontend CSS variables, Dashboard read-only behavior, and Exchange state-machine fixes intact.

= 1.1.40 =
* Marked Exchange Catalog Mode disabled create/repair notices as errors instead of success notices.
* Validated Start Exchange partner offer URLs before remote fetch attempts.
* Used canonical remote offer URLs when detecting existing exchange partners during approval.
* Added a repository-level duplicate partner guard by normalized offer URL.
* Hardened cached preview product links against invalid or cross-host historical cached URLs.
* Cleaned high-risk admin table action markup formatting.
* Cleaned duplicated catalog manager docblock.
* Kept Empty Value Helper rollout, frontend CSS variables, Dashboard read-only behavior, and Exchange state-machine fixes intact.

= 1.1.39 =
* Hardened admin external link rendering to reject non-http(s) or relative URLs.
* Displayed a muted placeholder instead of a broken Copy Link when a Core Product Link token is missing or invalid.
* Added a server-side guard to prevent Exchange Catalog create/repair actions while Exchange Catalog Mode is disabled.
* Refreshed exchange partner site name, site URL, and offer URL after successful partner sync.
* Refreshed exchange partner offer URL after approval callback success paths.
* Prevented partial Settings saves when Exchange Catalog Mode cannot be disabled due to active partners.
* Cleaned high-risk admin table action markup formatting.
* Kept Empty Value Helper rollout, frontend CSS variables, Dashboard read-only behavior, and Exchange state-machine fixes intact.

= 1.1.38 =
* Replaced the remaining plain dash in outgoing exchange request actions with the shared empty value helper.
* Applied the shared empty value helper to Performance table names and last-click columns.
* Applied the shared empty value helper to Core Friends last-click and name fields.
* Prevented empty product hashes from rendering as ellipses in Dashboard and Performance tables.
* Cleaned duplicated helper docblocks.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.37 =
* Added a shared admin Empty Value Helper for consistent muted dash output.
* Replaced scattered plain dash placeholders across Core, Exchange, Dashboard, and Performance admin pages.
* Standardized empty external links through a shared admin link-or-empty helper.
* Preserved numeric zero values while rendering only real empty values as muted dashes.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.36 =
* Removed UTF-8 BOM from plugin source files to prevent unexpected output before redirects or REST responses.
* Re-synced paused exchange partners before making them active again.
* Prevented existing partners from receiving success timestamps before approval callbacks succeed.
* Added site name and site URL updates only after approval callbacks succeed.
* Validated stored callback tokens before processing exchange callbacks.
* Validated request IDs and callback tokens before sending approval/rejection callbacks.
* Blocked self-exchange URLs before remote fetch attempts.
* Marked active partners with missing offer URLs as degraded during sync.
* Standardized muted dash display for empty backend values.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.35 =
* Re-synced paused exchange partners before making them active again.
* Prevented existing partners from receiving success timestamps before approval callbacks succeed.
* Added site name and site URL updates only after approval callbacks succeed.
* Validated stored callback tokens before processing exchange callbacks.
* Validated request IDs and callback tokens before sending approval/rejection callbacks.
* Blocked self-exchange URLs before remote fetch attempts.
* Marked active partners with missing offer URLs as degraded during sync.
* Standardized muted dash display for empty backend values.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.34 =
* Fixed malformed admin CSS for muted empty-value indicators.
* Made Resume re-sync paused exchange partners before making them public again.
* Prevented existing partners from receiving success timestamps before approval callbacks succeed.
* Validated stored callback tokens before processing exchange callbacks.
* Validated callback request IDs and tokens before sending approval/rejection callbacks.
* Blocked self-exchange URLs before remote fetch attempts.
* Standardized muted dash display for empty admin action cells.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.33 =
* Marked exchange partners as degraded when remote offer sync fails so stale cached products stop rendering publicly.
* Avoided writing last-success timestamps for pending activation partners.
* Clarified degraded remote offer handling for too-few-product sync failures.
* Validated callback request ID token shape before processing exchange callbacks.
* Displayed muted dashes for empty admin URL cells instead of leaving table cells blank.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.32 =
* Prevented failed approval callbacks from downgrading existing active exchange partners.
* Cleared stale request errors after successful approve/reject decisions.
* Added finalized-state guards for exchange approval callbacks.
* Stored the real remote offer fetch error when callback processing fails.
* Blocked attempts to exchange with the same store.
* Validated exchange request IDs and callback tokens before storing incoming requests.
* Improved the new Core Friend Save Without Sync notice.
* Hardened backend external links and avoided empty clickable links.

= 1.1.31 =
* Added confirmation to the Settings-page Exchange Catalog repair action.
* Prevented empty View Offer buttons when offer URLs are missing.
* Changed partner sync feedback so degraded sync results are shown as warnings instead of success.
* Marked outgoing exchange requests as failed when approved callbacks cannot be processed locally.
* Added confirmation before sending new exchange requests.
* Improved backend action button layout consistency across Core and Exchange admin pages.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.30 =
* Allowed disabled Exchange Offers to be saved without requiring the minimum product count in frontend validation.
* Hid misleading Sync Now actions for partners that are not in a syncable state.
* Added confirmation prompts for high-impact Exchange Request and Exchange Catalog actions.
* Added View Offer action for outgoing exchange requests.
* Improved backend action button layout with wrapping row action containers.
* Kept frontend color CSS variable support and Dashboard read-only behavior intact.

= 1.1.29 =
* Added Resume and Delete buttons to the Exchange Partners admin page.
* Added success notices for resumed and deleted exchange partners.
* Improved Exchange Partners diagnostics by showing last error messages and cached product count health.
* Kept frontend color CSS variable support and default color fallbacks intact.
* Kept Dashboard read-only behavior intact.

= 1.1.28 =
* Locked the Exchange Catalog Mode switch on the Settings page while active exchange partners exist.
* Clarified that frontend price and button color customization with CSS variables is optional.
* Kept default frontend price and button color fallbacks.
* Kept Dashboard fixes and read-only behavior intact.

= 1.1.27 =
* Added CSS variable support for frontend price and button colors.
* Added a Settings page example for customizing Friend Product Links colors in theme Additional CSS.
* Removed the unused css.txt test file from the plugin package.
* Kept Dashboard fixes and read-only behavior intact.

= 1.1.26 =
* Removed the duplicate empty-site Exchange Mode info message from the Dashboard.
* Counted active partners with too few cached products in the Partners / Requests dashboard card.
* Renamed the Exchange Catalog quick link to Manage Exchange Catalog when the catalog page is not ready.
* Made Core Summary sync health focus on enabled friend issues.
* Kept the Dashboard read-only.

= 1.1.25 =
* Fixed an undefined Dashboard status state for healthy Partners / Requests cards.
* Replaced multiple empty-site setup warnings with one onboarding message.
* Made Core Friends status metadata use enabled friend issues only.
* Cleaned duplicate Dashboard changelog entries.
* Kept the Dashboard read-only.

= 1.1.24 =
* Kept Dashboard summary reads bounded to avoid encouraging excessive exchange networks.
* Removed outdated readme wording about 100+ record Dashboard totals.
* Added request failure/error checks to the Partners / Requests Dashboard status.
* Reduced Core setup warnings for stores using Exchange Catalog only.
* Kept the Dashboard read-only.

= 1.1.23 =
* Reduced false Dashboard warnings from disabled Core Friends and disabled Core Product Links.
* Improved empty-state handling for the Partners / Requests dashboard card.
* Made the Exchange Catalog quick link point to the admin page when the catalog is not ready.
* Optimized read-only friend stats checks in the Dashboard.
* Kept the Dashboard read-only.

= 1.1.22 =
* Improved Dashboard status accuracy for Core Friends and Exchange Catalog readiness.
* Counted valid public WooCommerce products for Core Product Link and Exchange Offer dashboard health.
* Added CTR columns to Dashboard performance top tables.
* Wrapped Dashboard tables to prevent narrow admin layouts from overflowing.
* Reduced unnecessary Exchange setup info messages for Core-only users.
* Kept the Dashboard read-only.

= 1.1.21 =
* Added a read-only Dashboard for Friend Product Links.
* Added quick status cards for WooCommerce, Core Friends, Exchange Catalog, and partners.
* Added Action Required checks for setup, sync, catalog, request, partner, cron, and stats issues.
* Added Core Friends, Exchange, Performance, and System Health summaries.
* Moved the main admin menu landing page to the new Dashboard.

= 1.1.20 =
* Improved admin notices for Exchange Catalog Mode and catalog repair actions.
* Locked Exchange Catalog Mode ON in Settings while active exchange partners exist.
* Added Resume and Delete actions for Exchange Partners.
* Added degraded_remote_offer status when partner sync returns too few valid products.
* Improved Exchange Partners health, cached product count, and error visibility.
* Added horizontal table wrappers for wide admin tables.
* Added confirmations for exchange approve, reject, pause, and delete actions.
* Added lightweight Exchange readiness status strips across Exchange admin pages.
* Limited saved disabled Exchange Offer product IDs to the configured maximum.

= 1.1.19 =
* Refined frontend price styling so price labels and values inherit the corrected FPL price size without double scaling.
* Scoped container-query product grid rules to Friend Product Links modules.
* Kept responsive container-based product grids and theme-resistant price styling intact.

= 1.1.18 =
* Fixed frontend price styling being overridden by theme price span rules.
* Applied Friend Product Links price color, size, and weight directly to price labels and values.
* Added container-query based responsive grids so Core Friends and Exchange Catalog respond to their actual module width.
* Kept viewport media queries as fallback for older browsers.

= 1.1.17 =
* Moved automatic Core Friends rendering later in the WooCommerce single product page flow.
* Centered the Core Friends frontend module title.
* Unified Friend Product Links button and price colors using theme/WooCommerce color variables with a red fallback.
* Kept buttons and prices consistent between Core Friends and Exchange Catalog.
* Replaced remaining hard-coded product-count messages with constants.

= 1.1.16 =
* Moved automatic Core Friends output later in the WooCommerce single product page flow so store related products appear first.
* Updated frontend price color to use theme/WooCommerce color variables with a red-orange fallback.
* Kept button styling controlled by the active theme.
* Replaced remaining hard-coded Core and Exchange product-count messages with constants.

= 1.1.15 =
* Updated Core Friends frontend rendering to display up to 4 products per core friend.
* Updated Core Product Links minimum product requirement from 3 to 4 products.
* Replaced remaining Core Product Link product-count hardcoding with constants.
* Improved frontend price styling with larger, bold red prices.
* Preserved Exchange Catalog 4-product display and responsive 4/3/2/1 frontend grids.

= 1.1.14 =
* Fixed Exchange Catalog grid displacement caused by WooCommerce products clearfix and product layout styles.
* Switched plugin frontend grids to plugin-controlled layout classes while preserving theme-friendly title, price, and button classes.
* Strengthened frontend image sizing and title clamping so product cards render consistently in responsive 4/3/2/1 grids.
* Updated remaining Exchange admin copy to reflect the 4-to-9 product offer requirement and 4-product catalog display.

= 1.1.13 =
* Changed Exchange Catalog partner display count from 3 to 4 products to better match the responsive 4-column frontend grid.
* Updated Exchange Offer minimum product requirement to 4 products.
* Improved frontend image sizing so remote product images fill the product card area consistently.
* Strengthened title clamping so long product titles stay within two lines.
* Improved grid item width resets to prevent themes from compressing plugin product cards.
* Preserved Core Friends friend limit and existing Core Friends business rules.

= 1.1.12 =
* Fixed WooCommerce products clearfix pseudo-elements from interfering with plugin CSS Grid layouts.
* Added local WooCommerce wrapper classes so Exchange Catalog and Core Friends inherit theme product styles more reliably.
* Improved remote cached text normalization for product titles and price text.
* Kept responsive 4/3/2/1 frontend grids and theme-friendly product card rendering intact.

= 1.1.11 =
* Switched frontend product grids to WooCommerce-compatible ul.products / li.product markup.
* Strengthened responsive grid resets to prevent theme float and width rules from breaking the 4/3/2/1 layout.
* Reduced hard-coded image and module title styling so frontend output follows the active theme more closely.
* Improved frontend stylesheet loading reliability for shortcode and template usage.
* Added a standard upgrade notice reminding users to re-sync cached remote products after updating.

= 1.1.10 =
* Ensured frontend styles load early on product and Exchange Catalog pages.
* Added WooCommerce-compatible product grid classes for Core Friends and Exchange Catalog.
* Improved responsive 4/3/2/1 product grid stability across themes.
* Reduced hard-coded frontend card styling so product cards follow the active theme more closely.
* Kept clean price rendering, remote image handling, and Core Friends fallback rendering intact.

= 1.1.9 =
* Unified frontend product grids for Core Friends and Exchange Catalog with responsive 4/3/2/1 columns.
* Improved WooCommerce theme compatibility for frontend product cards.
* Kept frontend product data sanitization and clean price rendering intact.
* Preserved Core Friends display logic while updating its responsive layout.

= 1.1.8 =
* Fixed frontend product card rendering for Core Friends and Exchange Catalog.
* Reworked clean price text generation to avoid WooCommerce screen-reader price text and HTML entities.
* Allowed safe public CDN/external image URLs for remote product images.
* Removed empty image placeholders when remote products have no image.
* Improved Core Friends frontend rendering with safer filtering and a fallback WooCommerce hook.
* Improved admin hints for disabled Core Friends with cached products.

After updating, sync your Core Friends and Exchange Partners again to refresh cached product images and prices.

= 1.1.7 =
* Fixed Exchange partner sync recovery for degraded_missing_catalog partners.
* Hardened Exchange request approve/reject actions against stale request status submissions.

= 1.1.6 =
* Moved Core Friends Mode to the top of the Settings page as an always-on mode card.
* Added a locked green ON switch for Core Friends Mode.
* Reorganized Settings page sections to make plugin modes clearer.

= 1.1.5 =
* Improved Exchange Catalog Mode settings UI with a clear ON/OFF switch.
* Added clearer mode status messaging for Exchange Catalog setup.
* Kept Exchange Catalog Mode save and page creation logic unchanged.

= 1.1.4 =
* Fixed Settings page repair form markup by removing nested form output.
* Reworked Exchange Catalog admin page to use the bound catalog page manager status instead of the legacy manual shortcode flow.
* Hardened exchange approval callback offer URL comparison by normalizing the received and expected offer URLs.
* Added async image decoding for Exchange Catalog frontend images.

= 1.1.3 =
* Fixed Exchange Catalog readiness checks after automatic catalog page creation.
* Enforced remote offer catalog readiness validation (exchange_mode, catalog_status, catalog_url).
* Fixed Settings repair form markup (no nested forms).
* Fixed Exchange Catalog frontend rendering when the catalog page is missing or invalid.
* Fixed partner degraded_missing_catalog sync state handling.
* Hardened exchange approval callback offer URL matching.

= 1.1.1 =
* Fix Exchange Catalog runtime loader for frontend renderer and click tracker classes.
* Harden exchange partner/request matching with exact normalized offer URL comparisons.
* Improve exchange approval handshake to avoid one-sided activation when local partner creation fails.
* Render the first 3 valid exchange products from each partner cache.
* Show Exchange Partner clicks sent in the partner management table.


= 1.1.0 =
* Added Exchange Catalog module (phase 1): plugin constants, generic product picker, admin JS improvements.
* Core Friends menu labels updated to distinguish from Exchange Catalog.
* Copy Link button now falls back to execCommand when clipboard API fails.

= 1.0.3 =
* Fixed friend website URL validation when generating My Product Links. The generation step now validates URL format only and no longer blocks offline or DNS-restricted friend sites.
* Kept strict public URL validation for remote feed fetching, remote product URLs, image URLs, redirects, and stats endpoints.

= 1.0.2 =
* Fix admin share-link form submission hardening for WooCommerce Select2 product IDs.
* Add clearer validation messages when selected products are filtered out because they are not public visible products.
* Declare WooCommerce HPOS custom order table compatibility.

= 1.0.1 =
* Tightened product validation so only public visible WooCommerce products are saved.
* Fixed aggregate performance table uniqueness so different friend feeds do not merge stats.
* Hardened stats signature/key validation and click redirect hash handling.
* Added safer save failure handling for admin forms.

= 1.0.0 =
* Initial MVP.
