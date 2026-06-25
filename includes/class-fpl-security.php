<?php
/**
 * Security and sanitization helpers.
 *
 * @package FriendProductLinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPL_Security {
	/**
	 * Generate URL-safe token.
	 *
	 * @return string
	 */
	public static function generate_token() {
		return bin2hex( random_bytes( 24 ) );
	}

	/**
	 * Validate plugin-generated URL-safe tokens and shared stats keys.
	 *
	 * @param string $token Token or key.
	 * @return bool
	 */
	public static function is_token( $token ) {
		return is_string( $token ) && 1 === preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $token );
	}

	/**
	 * Hash product URL for stable local identifiers.
	 *
	 * @param string $url Product URL.
	 * @return string
	 */
	public static function product_hash( $url ) {
		return hash( 'sha256', esc_url_raw( (string) $url ) );
	}

	/**
	 * Normalize product ID input from a normal multi-select array or a legacy
	 * Select2 comma-separated value.
	 *
	 * Some WooCommerce admin screens/plugins can submit AJAX product pickers as
	 * either product_ids[]=1&product_ids[]=2 or as a single comma-separated
	 * string. Treat both forms consistently before product validation.
	 *
	 * @param mixed $ids Posted product ID value.
	 * @return int[]
	 */
	public static function normalize_product_id_input( $ids ) {
		$flat = array();

		if ( is_string( $ids ) ) {
			$ids = preg_split( '/[\s,]+/', $ids );
		}

		if ( is_array( $ids ) ) {
			array_walk_recursive(
				$ids,
				function ( $value ) use ( &$flat ) {
					if ( is_string( $value ) && false !== strpos( $value, ',' ) ) {
						foreach ( preg_split( '/[\s,]+/', $value ) as $part ) {
							$flat[] = $part;
						}
					} else {
						$flat[] = $value;
					}
				}
			);
		} else {
			$flat[] = $ids;
		}

		$normalized = array();
		foreach ( $flat as $value ) {
			$id = absint( $value );
			if ( $id ) {
				$normalized[] = $id;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Filter IDs to public, visible WooCommerce product posts.
	 *
	 * @param array $ids Product IDs.
	 * @param int   $limit Max IDs to return.
	 * @return int[]
	 */
	public static function filter_valid_product_ids( $ids, $limit = 9 ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$limit = absint( $limit );
		$valid = array();
		foreach ( array_unique( array_map( 'absint', (array) $ids ) ) as $product_id ) {
			if ( ! $product_id || 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product || ( method_exists( $product, 'is_type' ) && $product->is_type( 'variation' ) ) || ( method_exists( $product, 'is_visible' ) && ! $product->is_visible() ) ) {
				continue;
			}

			if ( ! get_permalink( $product_id ) || '' === trim( get_the_title( $product_id ) ) ) {
				continue;
			}

			$valid[] = $product_id;
			if ( $limit > 0 && count( $valid ) >= $limit ) {
				break;
			}
		}

		return $valid;
	}

	/**
	 * Sign a local value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function sign_value( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}

	/**
	 * Verify a local value signature.
	 *
	 * @param string $value Value.
	 * @param string $signature Signature.
	 * @return bool
	 */
	public static function verify_value_signature( $value, $signature ) {
		return is_string( $signature ) && hash_equals( self::sign_value( $value ), $signature );
	}

	/**
	 * Validate product hash shape.
	 *
	 * @param string $hash Product hash.
	 * @return bool
	 */
	public static function is_product_hash( $hash ) {
		return is_string( $hash ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $hash );
	}

	/**
	 * Normalize a URL host for comparisons.
	 *
	 * @param string $url URL or host.
	 * @return string
	 */
	public static function normalize_host( $url ) {
		return class_exists( 'FPL_URL_Helper' ) ? FPL_URL_Helper::get_host( $url ) : strtolower( trim( (string) $url ) );
	}

	/**
	 * Validate a public HTTP(S) URL before remote requests.
	 *
	 * @param string $url URL.
	 * @return true|WP_Error
	 */
	public static function validate_public_url( $url ) {
		return class_exists( 'FPL_URL_Helper' ) ? FPL_URL_Helper::is_public_http_url( $url ) : true;
	}

	/**
	 * Validate public IP address.
	 *
	 * @param string $ip IP address.
	 * @return true|WP_Error
	 */
	public static function validate_public_ip( $ip ) {
		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, $flags ) ) {
			return new WP_Error( 'fpl_private_ip', __( 'Private, loopback, link-local, and reserved IP addresses are not allowed.', 'friend-product-links' ) );
		}

		return true;
	}

	/**
	 * Resolve host to IPv4 and IPv6 records.
	 *
	 * @param string $host Hostname.
	 * @return string[]
	 */
	public static function resolve_host_ips( $host ) {
		$ips = array();

		$v4 = gethostbynamel( $host );
		if ( is_array( $v4 ) ) {
			$ips = array_merge( $ips, $v4 );
		}

		if ( function_exists( 'dns_get_record' ) ) {
			$records = dns_get_record( $host, DNS_AAAA );
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = $record['ipv6'];
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $ips ) ) );
	}

	/**
	 * Normalize remote product payload.
	 *
	 * @param array  $product Raw product.
	 * @param string $allowed_host Required product URL host.
	 * @return array|null
	 */
	public static function sanitize_remote_product( $product, $allowed_host = '' ) {
		if ( ! is_array( $product ) ) {
			return null;
		}

		$title       = isset( $product['title'] ) ? self::normalize_remote_text( $product['title'] ) : '';
		$product_url = isset( $product['product_url'] ) ? esc_url_raw( $product['product_url'] ) : '';
		$image_url   = isset( $product['image_url'] ) ? esc_url_raw( $product['image_url'] ) : '';
		$price_text  = isset( $product['price_text'] ) ? self::normalize_remote_text( $product['price_text'] ) : '';

		if ( '' === $title || '' === $product_url ) {
			return null;
		}

		if ( is_wp_error( self::validate_public_url( $product_url ) ) ) {
			return null;
		}

		if ( $allowed_host && self::normalize_host( $product_url ) !== self::normalize_host( $allowed_host ) ) {
			return null;
		}

		if ( '' !== $image_url && is_wp_error( self::validate_public_url( $image_url ) ) ) {
			$image_url = '';
		}

		return array(
			'title'        => $title,
			'image_url'    => $image_url,
			'price_text'   => $price_text,
			'product_url'  => $product_url,
			'product_hash' => self::product_hash( $product_url ),
		);
	}

	/**
	 * Strip tags, decode HTML entities, compress whitespace for remote text.
	 *
	 * @param string $value Raw remote text.
	 * @return string
	 */
	private static function normalize_remote_text( $value ) {
		$text = wp_strip_all_tags( (string) $value );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return sanitize_text_field( trim( $text ) );
	}
}
