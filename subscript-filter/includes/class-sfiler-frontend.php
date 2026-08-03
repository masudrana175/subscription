<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the "Choose how to buy" box on the single product page.
 */
class Sfiler_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_purchase_options' ), 25 );
	}

	public static function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product || ! Sfiler_Product::is_enabled( $product->get_id() ) ) {
			return;
		}

		wp_enqueue_style( 'sfiler-frontend', SFILER_PLUGIN_URL . 'assets/css/sfiler-frontend.css', array(), SFILER_VERSION );
		wp_enqueue_script( 'sfiler-frontend', SFILER_PLUGIN_URL . 'assets/js/sfiler-frontend.js', array( 'jquery' ), SFILER_VERSION, true );
	}

	public static function render_purchase_options() {
		global $product;

		if ( ! $product instanceof WC_Product || ! Sfiler_Product::is_enabled( $product->get_id() ) ) {
			return;
		}

		$product_id  = $product->get_id();
		$discount    = Sfiler_Product::get_discount_percent( $product_id );
		$frequencies = Sfiler_Product::get_frequencies( $product_id );

		// The subscription discount is always computed off the regular
		// (list) price, matching how the cart prices it — never off a
		// temporary sale price, which would make the subscribe price
		// silently fluctuate whenever the store runs a one-time sale, and
		// which would otherwise mismatch what checkout actually charges.
		// Variable products may not have a single regular price until a
		// variation is picked; fall back to get_price() for that initial
		// render, the JS variation handler corrects it once one is chosen.
		$regular_price = $product->get_regular_price();
		$regular_price = ( '' !== $regular_price ) ? (float) $regular_price : (float) $product->get_price();

		include SFILER_PLUGIN_DIR . 'templates/frontend/purchase-options.php';
	}
}
