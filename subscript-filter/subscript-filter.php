<?php
/**
 * Plugin Name: Subscript Filter
 * Description: Adds recurring subscription purchasing to WooCommerce simple and variable products, with Stripe (card and Apple Pay) off-session renewal billing.
 * Version: 1.2.0
 * Author: Design Filters
 * Text Domain: subscript-filter
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SFILER_VERSION', '1.2.0' );
define( 'SFILER_PLUGIN_FILE', __FILE__ );
define( 'SFILER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFILER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, 'sfiler_activate' );
register_deactivation_hook( __FILE__, 'sfiler_deactivate' );

function sfiler_activate() {
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-install.php';
	Sfiler_Install::install();
}

function sfiler_deactivate() {
	wp_clear_scheduled_hook( 'sfiler_process_renewals' );
}

add_action( 'plugins_loaded', 'sfiler_init' );
function sfiler_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'sfiler_missing_wc_notice' );
		return;
	}

	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-helpers.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-subscription.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-product.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-frontend.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-cart.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-order.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-stripe.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-cron.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-emails.php';
	require_once SFILER_PLUGIN_DIR . 'includes/class-sfiler-my-account.php';

	if ( is_admin() ) {
		require_once SFILER_PLUGIN_DIR . 'includes/admin/class-sfiler-admin-list-table.php';
		require_once SFILER_PLUGIN_DIR . 'includes/admin/class-sfiler-admin-export.php';
		require_once SFILER_PLUGIN_DIR . 'includes/admin/class-sfiler-admin.php';
		Sfiler_Admin::init();
		Sfiler_Admin_Export::init();
	}

	Sfiler_Product::init();
	Sfiler_Frontend::init();
	Sfiler_Cart::init();
	Sfiler_Order::init();
	Sfiler_Cron::init();
	Sfiler_Emails::init();
	Sfiler_My_Account::init();
}

function sfiler_missing_wc_notice() {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Subscript Filter requires WooCommerce to be installed and active.', 'subscript-filter' ) . '</p></div>';
}
