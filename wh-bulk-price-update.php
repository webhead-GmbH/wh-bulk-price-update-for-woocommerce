<?php
/**
 * Plugin Name: Bulk Price Update for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/wh-bulk-price-update-for-woocommerce/
 * Description: Easily update WooCommerce product prices in bulk by percentage or fixed amounts based on categories, tags, and attributes.
 * Version: 1.0.1
 * Author: Webhead
 * Author URI: https://webhead.at
 * Text Domain: wh-bulk-price-update
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 7.1.0
 * WC tested up to: 8.8
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2024 Webhead
 */

# Prevent direct file access
defined( 'ABSPATH' ) || exit;

if( !function_exists( 'is_plugin_inactive' ) ) include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

function webhead_bulk_price_update_setup_constants()
{
    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_VERSION' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_VERSION', '1.0.1' );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_FILE' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_FILE', __FILE__ );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_BASE' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_BASE', plugin_basename( __FILE__ ) );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_SLUG' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_SLUG', basename( WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_BASE, '.php' ) );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY', 'wh_blog_posts' );

    if( !defined( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGINS_CACHE_KEY' ) )
        define( 'WEBHEAD_BULK_PRICE_UPDATE_PLUGINS_CACHE_KEY', 'wh_plugins' );
}

/** Initialize the plugin. */
function webhead_bulk_price_update_init_plugin(): void
{
    // Setup plugin constants
    webhead_bulk_price_update_setup_constants();

    require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . 'includes/class-wh-bulk-price-update.php';

    // Instantiate the `WH_Bulk_Price_Update` object
    $plugin = new WH_Bulk_Price_Update();

    // Initialize the plugin
    $plugin->init();
}

if( is_plugin_inactive( 'woocommerce/woocommerce.php' ) ) {
    add_action( is_network_admin() ? 'network_admin_notices' : 'admin_notices', function() {
        echo '<div class="error"><h3>Bulk Price Update for Woocommerce</h3><p>To install the plugin, <strong>woocommerce</strong> plugin required.</p></div>';
    } );
} else {
    do_action( 'before_wh_bulk_price_update_run' );
    add_action( 'plugins_loaded', 'webhead_bulk_price_update_init_plugin' );
    do_action( 'after_wh_bulk_price_update_run' );
}