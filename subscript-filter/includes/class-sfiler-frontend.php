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

		$product_id = $product->get_id();
		$discount   = Sfiler_Product::get_discount_percent( $product_id );
		$frequencies = Sfiler_Product::get_frequencies( $product_id );
		$regular_price = (float) $product->get_price();

		include SFILER_PLUGIN_DIR . 'templates/purchase-options.php';
	}
}
