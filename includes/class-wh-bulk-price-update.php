<?php
/**
 * Bulk Price Update - Core Class
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2024 Webhead
 */

# Prevent direct file access
defined( 'ABSPATH' ) || exit;

class WH_Bulk_Price_Update
{
    public function init(): void
    {
        $this->includes();
        $this->add_hooks();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain( 'wh-bulk-price-update-for-woocommerce', false, WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_SLUG . '/languages/' );
    }

    public function add_price_change_page()
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Bulk Price Update', 'wh-bulk-price-update-for-woocommerce' ),
            __( 'Bulk Price Update', 'wh-bulk-price-update-for-woocommerce' ),
            'manage_options',
            'wh-bulk-price-update-for-woocommerce',
            [$this, 'render_price_change_page']
        );
    }

    public function render_price_change_page()
    {
        wp_enqueue_style( 'woocommerce_admin_styles' );
        wp_enqueue_style( 'wh-bootstrap-css' );
        wp_enqueue_style( 'wh-fontawesome-css' );
        wp_enqueue_style( 'wh-bulk-price-update-for-woocommerce' );

        wp_enqueue_script( 'wh-popper' );
        wp_enqueue_script( 'wh-bootstrap' );
        wp_enqueue_script( 'select2' );
        wp_enqueue_script( 'wc-enhanced-select' );
        wp_enqueue_script( 'accounting' );
        wp_enqueue_script( 'wh-bulk-price-update-for-woocommerce' );

        $data['categories'] = wp_dropdown_categories( [
            'taxonomy'      => 'product_cat',
            'orderby'       => 'name',
            'hide_empty'    => 0,
            'hide_if_empty' => false,
            'echo'          => false,
            'hierarchical'  => true,
            'show_count'    => true,
            'name'          => 'categories[]',
            'id'            => 'categories',
            'class'         => 'wh-select2 multiple',
            'selected'      => -1,
        ] );

        $data['tags'] = get_terms( [
            'taxonomy'   => 'product_tag',
            'orderby'    => 'name',
            'hide_empty' => 0,
        ] );

        $data['attributes'] = wc_get_attribute_taxonomies();
        $data['block_size'] = get_option( 'wh_bulk_price_update_block_size', 1024 );
        $data['preview_count'] = get_option( 'wh_bulk_price_update_preview_block_size', 20 );
        $data['time_limit'] = get_option( 'wh_bulk_price_update_time_limit', -1 );

        webhead_bulk_price_update_load_template( 'price-change', $data );
    }

    public function woo_compatibility()
    {
        if( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_FILE,
                true
            );
        }
    }

    public function admin_styles()
    {
        wp_register_style( 'wh-bootstrap-css', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/bootstrap.min.css', [], '5.3.3' );
        wp_register_style( 'wh-fontawesome-css', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/all.min.css', [], '6.5.2' );
        wp_register_style( 'wh-bulk-price-update-for-woocommerce', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/style.css', ['wh-bootstrap-css', 'woocommerce_admin_styles'], WEBHEAD_BULK_PRICE_UPDATE_VERSION );
    }

    public function admin_scripts()
    {
        wp_register_script( 'wh-popper', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/popper.min.js', [], '2.11.8', true );
        wp_register_script( 'wh-bootstrap', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/bootstrap.min.js', ['wh-popper', 'jquery'], '5.3.3', true );
        wp_register_script( 'wh-bulk-price-update-for-woocommerce', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/script.js', ['jquery', 'select2', 'wh-bootstrap', 'accounting'], WEBHEAD_BULK_PRICE_UPDATE_VERSION, true );
        wp_localize_script(
            'wh-bulk-price-update-for-woocommerce',
            'wh_script_params',
            [
                'i18n_select_an_option'        => _x( 'Select an option', 'enhanced select', 'wh-bulk-price-update-for-woocommerce' ),
                'ajax_url'                     => admin_url( 'admin-ajax.php' ),
                'update_product_price_nonce'   => wp_create_nonce( 'update-product-price' ),
                'save_settings_nonce'          => wp_create_nonce( 'save-settings' ),
                'get_blog_posts_nonce'         => wp_create_nonce( 'get-blog-posts' ),
                'get_plugins_nonce'            => wp_create_nonce( 'get-plugins' ),
                'currency_format_num_decimals' => 2,
                'currency_format_symbol'       => get_woocommerce_currency_symbol(),
                'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
                'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
                'currency_format'              => esc_attr( str_replace( ['%1$s', '%2$s'], ['%s', '%v'], get_woocommerce_price_format() ) ),
            ]
        );
    }

    public function includes()
    {
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/wh-bulk-price-update-core-functions.php';
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/class-wh-bulk-price-update-ajax.php';
    }

    private function add_hooks(): void
    {
        add_action( 'init', [$this, 'load_textdomain'] );
        add_action( 'admin_menu', [$this, 'add_price_change_page'] );
        add_action( 'admin_enqueue_scripts', [$this, 'admin_styles'] );
        add_action( 'admin_enqueue_scripts', [$this, 'admin_scripts'] );
        add_action( 'before_woocommerce_init', [$this, 'woo_compatibility'] );
    }
}