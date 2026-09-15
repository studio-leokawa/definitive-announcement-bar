<?php
/**
 * Plugin Name:          Definitive Announcement Bar for Bricks and WooCommerce
 * Description:          A lightweight, rotating announcement bar for any theme. Works with Bricks Builder, block and classic themes, and WooCommerce.
 * Version:              1.0.0
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Author:               studio leokawa
 * Author URI:           https://leokawa.design
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          definitive-announcement-bar
 * WC requires at least: 7.0
 * WC tested up to:      11.1
 *
 * @package DefinitiveAnnouncementBar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DABAR_VERSION', '1.0.0' );
define( 'DABAR_FILE', __FILE__ );
define( 'DABAR_PATH', plugin_dir_path( __FILE__ ) );
define( 'DABAR_URL', plugin_dir_url( __FILE__ ) );

require_once DABAR_PATH . 'includes/class-dabar-settings.php';
require_once DABAR_PATH . 'includes/class-dabar-frontend.php';

new DABAR_Frontend();

if ( is_admin() ) {
	require_once DABAR_PATH . 'includes/class-dabar-admin.php';
	new DABAR_Admin();
}

add_action( 'add_option_' . DABAR_Settings::OPTION, array( 'DABAR_Settings', 'flush' ) );
add_action( 'update_option_' . DABAR_Settings::OPTION, array( 'DABAR_Settings', 'flush' ) );

/**
 * Declares compatibility with WooCommerce features (HPOS and block cart/checkout).
 *
 * The plugin never touches orders or the checkout flow, so both are safe.
 */
function dabar_declare_woocommerce_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DABAR_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DABAR_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'dabar_declare_woocommerce_compatibility' );
