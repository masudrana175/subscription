<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures the customer's purchase-type choice into the cart, applies the
 * subscription price, and persists the choice onto the resulting order item.
 */
class Sfiler_Cart {

	const CART_ITEM_KEY = 'sfiler_subscription';

	public static function init() {
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'render_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_subscription_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_item_meta' ), 10, 4 );
	}

	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( ! Sfiler_Product::is_enabled( $product_id ) ) {
			return $cart_item_data;
		}

		$purchase_type = isset( $_POST['sfiler_purchase_type'] ) ? sanitize_text_field( wp_unslash( $_POST['sfiler_purchase_type'] ) ) : 'onetime';

		if ( 'subscription' !== $purchase_type ) {
			return $cart_item_data;
		}

		$frequencies     = Sfiler_Product::get_frequencies( $product_id );
		$frequency_index = isset( $_POST['sfiler_frequency'] ) ? absint( $_POST['sfiler_frequency'] ) : 0;
		$frequency       = isset( $frequencies[ $frequency_index ] ) ? $frequencies[ $frequency_index ] : $frequencies[0];

		$cart_item_data[ self::CART_ITEM_KEY ] = array(
			'is_subscription'  => true,
			'discount_percent' => Sfiler_Product::get_discount_percent( $product_id ),
			'interval_count'   => $frequency['count'],
			'interval_unit'    => $frequency['unit'],
		);

		return $cart_item_data;
	}

	public static function render_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_ITEM_KEY ]['is_subscription'] ) ) {
			return $item_data;
		}

		$sub   = $cart_item[ self::CART_ITEM_KEY ];
		$units = sfiler_get_interval_units();

		$item_data[] = array(
			'key'   => __( 'Purchase type', 'subscript-filter' ),
			'value' => sprintf(
				/* translators: 1: interval count, 2: interval unit label */
				__( 'Subscription &ndash; every %1$d %2$s', 'subscript-filter' ),
				$sub['interval_count'],
				strtolower( $units[ $sub['interval_unit'] ] )
			),
		);

		return $item_data;
	}

	public static function apply_subscription_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item[ self::CART_ITEM_KEY ]['is_subscription'] ) ) {
				continue;
			}

			$discount = (float) $cart_item[ self::CART_ITEM_KEY ]['discount_percent'];
			$product  = $cart_item['data'];

			// woocommerce_before_calculate_totals can fire more than once per
			// request (page load, then an AJAX cart update). Always discount
			// from the stable regular price, never from get_price(), which
			// this method may have already mutated on an earlier firing —
			// otherwise the discount compounds on every extra call.
			$price = (float) $product->get_regular_price();

			$product->set_price( round( $price * ( 1 - ( $discount / 100 ) ), wc_get_price_decimals() ) );
		}
	}

	public static function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values[ self::CART_ITEM_KEY ]['is_subscription'] ) ) {
			return;
		}

		$sub = $values[ self::CART_ITEM_KEY ];

		$item->add_meta_data( '_sfiler_subscription', 'yes', true );
		$item->add_meta_data( '_sfiler_interval_count', $sub['interval_count'], true );
		$item->add_meta_data( '_sfiler_interval_unit', $sub['interval_unit'], true );
		$item->add_meta_data( '_sfiler_discount_percent', $sub['discount_percent'], true );
	}
}
