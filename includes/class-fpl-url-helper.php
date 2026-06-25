<?php
/**
 * URL normalization and boundary validation helpers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_URL_Helper {
	/**
	 * Normalize a URL before storage or comparison.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		return esc_url_raw( trim( (string) $url ) );
	}

	/**
	 * Return normalized host without www prefix.
	 *
	 * @param string $url URL or host.
	 * @return string
	 */
	public static function get_host( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );
		if ( ! $host ) {
			$host = (string) $url;
		}

		$host = strtolower( trim( $host, "[] \t\n\r\0\x0B." ) );
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		return $host;
	}

	/**
	 * Compare URL hosts.
	 *
	 * @param string $left First URL.
	 * @param string $right Second URL.
	 * @return bool
	 */
	public static function same_host( $left, $right ) {
		$left_host  = self::get_host( $left );
		$right_host = self::get_host( $right );

		return '' !== $left_host && '' !== $right_host && $left_host === $right_host;
	}

	/**
	 * Validate a public http(s) URL.
	 *
	 * @param string $url URL.
	 * @return true|WP_Error
	 */
	public static function is_public_http_url( $url ) {
		$url = self::normalize_url( $url );

		if ( '' === $url ) {
			return new WP_Error( 'fpl_empty_url', __( 'URL is empty.', 'friend-product-links' ) );
		}

		if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'fpl_invalid_url', __( 'URL failed WordPress HTTP validation.', 'friend-product-links' ) );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'fpl_invalid_url', __( 'URL must include a scheme and host.', 'friend-product-links' ) );
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'fpl_bad_scheme', __( 'Only http and https URLs are allowed.', 'friend-product-links' ) );
		}

		if ( self::is_private_or_local_host( $parts['host'] ) ) {
			return new WP_Error( 'fpl_private_host', __( 'Local, private, and reserved hosts are not allowed.', 'friend-product-links' ) );
		}

		return true;
	}

	/**
	 * Detect localhost, private IP, reserved IP, or unresolvable hosts.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function is_private_or_local_host( $host ) {
		static $host_cache = array();

		$host = strtolower( trim( (string) $host, "[] \t\n\r\0\x0B." ) );
		if ( isset( $host_cache[ $host ] ) ) {
			return $host_cache[ $host ];
		}

		if ( '' === $host || in_array( $host, array( 'localhost', 'localhost.localdomain' ), true ) ) {
			$host_cache[ $host ] = true;
			return true;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$host_cache[ $host ] = ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
			return $host_cache[ $host ];
		}

		$ips = FPL_Security::resolve_host_ips( $host );
		if ( empty( $ips ) ) {
			$host_cache[ $host ] = true;
			return true;
		}

		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				$host_cache[ $host ] = true;
				return true;
			}
		}

		$host_cache[ $host ] = false;
		return false;
	}

	/**
	 * Validate friend site, feed URL, and optional feed payload same-host boundary.
	 *
	 * @param string $friend_site_url Entered friend site URL.
	 * @param string $feed_url Feed URL.
	 * @param array  $feed_payload Optional decoded feed payload.
	 * @return true|WP_Error
	 */
	public static function validate_friend_feed_boundary( $friend_site_url, $feed_url, $feed_payload = null ) {
		$site_valid = self::validate_identity_http_url( $friend_site_url );
		if ( is_wp_error( $site_valid ) ) {
			return $site_valid;
		}

		$feed_valid = self::is_public_http_url( $feed_url );
		if ( is_wp_error( $feed_valid ) ) {
			return $feed_valid;
		}

		if ( ! self::same_host( $friend_site_url, $feed_url ) ) {
			return new WP_Error( 'fpl_feed_host_mismatch', __( 'Friend website URL and feed URL must use the same host.', 'friend-product-links' ) );
		}

		if ( null === $feed_payload ) {
			return true;
		}

		if ( ! is_array( $feed_payload ) ) {
			return new WP_Error( 'fpl_bad_payload', __( 'Feed payload is invalid.', 'friend-product-links' ) );
		}

		if ( empty( $feed_payload['site_url'] ) || ! self::same_host( $feed_url, $feed_payload['site_url'] ) ) {
			return new WP_Error( 'fpl_site_url_mismatch', __( 'Feed site_url must use the same host as the feed URL.', 'friend-product-links' ) );
		}

		if ( empty( $feed_payload['products'] ) || ! is_array( $feed_payload['products'] ) ) {
			return new WP_Error( 'fpl_bad_products', __( 'Feed JSON did not contain a valid products array.', 'friend-product-links' ) );
		}

		return true;
	}

	/**
	 * Lightweight URL format validation for identity/reference URLs.
	 *
	 * Checks scheme (http/https only) and host presence. Does NOT perform
	 * DNS resolution, reachability checks, or private/reserved IP detection.
	 * Use for URLs stored as references (e.g. friend website URL on a share link)
	 * where the server will NOT make outbound requests to that URL.
	 *
	 * @param string $url URL to validate.
	 * @return true|WP_Error
	 */
	public static function validate_identity_http_url( $url ) {
		$url = self::normalize_url( $url );

		if ( '' === $url ) {
			return new WP_Error( 'fpl_empty_url', __( 'URL is empty.', 'friend-product-links' ) );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new WP_Error( 'fpl_invalid_url', __( 'URL must include a scheme and host.', 'friend-product-links' ) );
		}

		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'fpl_bad_scheme', __( 'Only http and https URLs are allowed.', 'friend-product-links' ) );
		}

		$host = self::get_host( $url );
		if ( '' === $host ) {
			return new WP_Error( 'fpl_invalid_host', __( 'URL must include a valid host.', 'friend-product-links' ) );
		}

		return true;
	}
}
