<?php
/**
 * Bulk Price Update - Core Class
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

class WH_Bulk_Price_Update
{
    public function init(): void
    {
        $this->includes();
        $this->add_hooks();
        WH_Price_Rule_Scheduler::init();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('wh-bulk-price-update-for-woocommerce', false, WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_SLUG . '/languages/');
    }

    public function add_price_change_page()
    {
        add_submenu_page(
            'edit.php?post_type=product',
            __('Bulk Price Update', 'wh-bulk-price-update-for-woocommerce'),
            __('Bulk Price Update', 'wh-bulk-price-update-for-woocommerce'),
            'manage_options',
            'wh-bulk-price-update-for-woocommerce',
            [$this, 'render_price_change_page']
        );
    }

    public function render_price_change_page()
    {
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_style('wh-bootstrap-css');
        wp_enqueue_style('wh-fontawesome-css');
        wp_enqueue_style('wh-bulk-price-update-for-woocommerce');
        wp_enqueue_style('wh-bulk-price-update-for-woocommerce-rules');

        wp_enqueue_script('wh-popper');
        wp_enqueue_script('wh-bootstrap');
        wp_enqueue_script('select2');
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_script('accounting');
        wp_enqueue_script('wh-bulk-price-update-for-woocommerce');
        wp_enqueue_script('wh-bulk-price-update-for-woocommerce-rules');

        $data['categories'] = wp_dropdown_categories([
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
        ]);

        $data['brands'] = wp_dropdown_categories([
            'taxonomy'      => 'product_brand',
            'orderby'       => 'name',
            'hide_empty'    => 0,
            'hide_if_empty' => false,
            'echo'          => false,
            'hierarchical'  => true,
            'show_count'    => true,
            'name'          => 'brands[]',
            'id'            => 'brands',
            'class'         => 'wh-select2 multiple',
            'selected'      => -1,
        ]);

        $data['tags'] = get_terms([
            'taxonomy'   => 'product_tag',
            'orderby'    => 'name',
            'hide_empty' => 0,
        ]);

        $data['attributes'] = wc_get_attribute_taxonomies();
        $data['block_size'] = get_option('wh_bulk_price_update_block_size', 1024);
        $data['preview_count'] = get_option('wh_bulk_price_update_preview_block_size', 20);
        $data['time_limit'] = get_option('wh_bulk_price_update_time_limit', -1);
        $data['cog_meta_key'] = WH_Price_Rule_Executor::sanitize_cog_meta_key(
            get_option('wh_bulk_price_update_cog_meta_key', WH_Price_Rule_Executor::get_default_cog_meta_key())
        );
        $data['custom_expression_placeholders'] = webhead_bulk_price_update_get_custom_expression_placeholders();

        if ($data['cog_meta_key'] === '') {
            $data['cog_meta_key'] = WH_Price_Rule_Executor::get_default_cog_meta_key();
        }

        // Price rules data
        $data['price_rules']    = WH_Price_Rule_DB::table_exists() ? WH_Price_Rule_DB::get_all() : [];
        $data['rule_types']     = WH_Price_Rule_Executor::get_rule_types();
        $data['schedule_types'] = WH_Price_Rule_Scheduler::get_schedule_types();
        $data['margin_tiers']   = WH_Price_Rule_Executor::get_default_margin_tiers();

        webhead_bulk_price_update_load_template('price-change', $data);
    }

    public function woo_compatibility()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_FILE,
                true
            );
        }
    }

    public function admin_styles()
    {
        $style_path = WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . 'assets/css/style.css';
        $rules_style_path = WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . 'assets/css/price-rules.css';
        $style_version = WEBHEAD_BULK_PRICE_UPDATE_VERSION . '.' . (file_exists($style_path) ? filemtime($style_path) : WEBHEAD_BULK_PRICE_UPDATE_VERSION);
        $rules_style_version = WEBHEAD_BULK_PRICE_UPDATE_VERSION . '.' . (file_exists($rules_style_path) ? filemtime($rules_style_path) : WEBHEAD_BULK_PRICE_UPDATE_VERSION);

        wp_register_style('wh-bootstrap-css', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/bootstrap.min.css', [], '5.3.3');
        wp_register_style('wh-fontawesome-css', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/all.min.css', [], '6.5.2');
        wp_register_style('wh-bulk-price-update-for-woocommerce', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/style.css', ['wh-bootstrap-css', 'woocommerce_admin_styles'], $style_version);
        wp_register_style('wh-bulk-price-update-for-woocommerce-rules', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/css/price-rules.css', ['wh-bulk-price-update-for-woocommerce'], $rules_style_version);
    }

    public function admin_scripts()
    {
        $script_path = WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . 'assets/js/script.js';
        $rules_script_path = WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . 'assets/js/price-rules.js';
        $script_version = WEBHEAD_BULK_PRICE_UPDATE_VERSION . '.' . (file_exists($script_path) ? filemtime($script_path) : WEBHEAD_BULK_PRICE_UPDATE_VERSION);
        $rules_script_version = WEBHEAD_BULK_PRICE_UPDATE_VERSION . '.' . (file_exists($rules_script_path) ? filemtime($rules_script_path) : WEBHEAD_BULK_PRICE_UPDATE_VERSION);
        $custom_expression_placeholders = webhead_bulk_price_update_get_custom_expression_placeholders();

        wp_register_script('wh-popper', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/popper.min.js', [], '2.11.8', true);
        wp_register_script('wh-bootstrap', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/bootstrap.min.js', ['wh-popper', 'jquery'], '5.3.3', true);
        wp_register_script('wh-bulk-price-update-for-woocommerce', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/script.js', ['jquery', 'select2', 'wh-bootstrap', 'accounting'], $script_version, true);

        // Register jQuery UI Sortable for Margin Tiers
        wp_enqueue_script('jquery-ui-sortable');

        wp_register_script('wh-bulk-price-update-for-woocommerce-rules', WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/js/price-rules.js', ['wh-bulk-price-update-for-woocommerce', 'jquery-ui-sortable'], $rules_script_version, true);

        wp_localize_script(
            'wh-bulk-price-update-for-woocommerce',
            'wh_script_params',
            [
                'i18n_price_value_placeholder_numeric' => __('e.g. 10', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_price_value_placeholder_custom'  => __('e.g. {regular_price} * 0.8', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_select_an_option'        => _x('Select an option', 'enhanced select', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_confirm_delete'          => __('Are you sure you want to delete this rule?', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_confirm_clear_logs'      => __('Are you sure you want to clear all execution logs for this rule?', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_running'                 => __('Running...', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_saving'                  => __('Saving...', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_clearing'                => __('Clearing...', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_loading_logs'            => __('Loading logs...', 'wh-bulk-price-update-for-woocommerce'),
                'i18n_excluded'                => __('Excluded', 'wh-bulk-price-update-for-woocommerce'),
                'ajax_url'                     => admin_url('admin-ajax.php'),
                'update_product_price_nonce'   => wp_create_nonce('update-product-price'),
                'save_settings_nonce'          => wp_create_nonce('save-settings'),
                'get_blog_posts_nonce'         => wp_create_nonce('get-blog-posts'),
                'get_plugins_nonce'            => wp_create_nonce('get-plugins'),
                'save_price_rule_nonce'        => wp_create_nonce('save-price-rule'),
                'delete_price_rule_nonce'      => wp_create_nonce('delete-price-rule'),
                'toggle_price_rule_nonce'      => wp_create_nonce('toggle-price-rule'),
                'run_price_rule_nonce'         => wp_create_nonce('run-price-rule'),
                'get_price_rule_logs_nonce'    => wp_create_nonce('get-price-rule-logs'),
                'clear_price_rule_logs_nonce'  => wp_create_nonce('clear-price-rule-logs'),
                'preview_price_rule_nonce'     => wp_create_nonce('preview-price-rule'),
                'search_attributes_nonce'      => wp_create_nonce('search-attributes'),
                'search_products_nonce'        => wp_create_nonce('search-products'),
                'currency_format_num_decimals' => 2,
                'currency_format_symbol'       => get_woocommerce_currency_symbol(),
                'currency_format_decimal_sep'  => esc_attr(wc_get_price_decimal_separator()),
                'currency_format_thousand_sep' => esc_attr(wc_get_price_thousand_separator()),
                'currency_format'              => esc_attr(str_replace(['%1$s', '%2$s'], ['%s', '%v'], get_woocommerce_price_format())),
                'custom_expression_placeholders' => array_map(
                    static function(string $token, array $placeholder): array {
                        return [
                            'token'       => $token,
                            'label'       => sanitize_text_field($placeholder['label'] ?? $token),
                            'description' => sanitize_text_field($placeholder['description'] ?? ''),
                            'sample_value' => floatval($placeholder['sample_value'] ?? 0),
                        ];
                    },
                    array_keys($custom_expression_placeholders),
                    $custom_expression_placeholders
                ),
            ]
        );
    }

    public function includes()
    {
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/wh-bulk-price-update-core-functions.php';
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/class-wh-price-rule-db.php';
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/class-wh-price-rule-log-db.php';
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/class-wh-price-rule-executor.php';
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/class-wh-price-rule-scheduler.php';
        require_once WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . '/includes/class-wh-bulk-price-update-ajax.php';
    }

    private function add_hooks(): void
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_price_change_page']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('before_woocommerce_init', [$this, 'woo_compatibility']);

        // AJAX Actions
        add_action('wp_ajax_webhead_bulk_price_update_update_product_price', ['WH_Bulk_Price_Update_Ajax', 'update_product_price']);
        add_action('wp_ajax_webhead_bulk_price_update_save_settings', ['WH_Bulk_Price_Update_Ajax', 'save_settings']);
        add_action('wp_ajax_webhead_bulk_price_update_get_blog_posts', ['WH_Bulk_Price_Update_Ajax', 'get_blog_posts']);
        add_action('wp_ajax_webhead_bulk_price_update_get_plugins', ['WH_Bulk_Price_Update_Ajax', 'get_plugins']);

        // Scheduled Price Rules AJAX Actions
        add_action('wp_ajax_webhead_bulk_price_update_save_price_rule', ['WH_Bulk_Price_Update_Ajax', 'save_price_rule']);
        add_action('wp_ajax_webhead_bulk_price_update_delete_price_rule', ['WH_Bulk_Price_Update_Ajax', 'delete_price_rule']);
        add_action('wp_ajax_webhead_bulk_price_update_toggle_price_rule', ['WH_Bulk_Price_Update_Ajax', 'toggle_price_rule']);
        add_action('wp_ajax_webhead_bulk_price_update_run_price_rule', ['WH_Bulk_Price_Update_Ajax', 'run_price_rule']);
        add_action('wp_ajax_webhead_bulk_price_update_get_price_rule_logs', ['WH_Bulk_Price_Update_Ajax', 'get_price_rule_logs']);
        add_action('wp_ajax_webhead_bulk_price_update_clear_price_rule_logs', ['WH_Bulk_Price_Update_Ajax', 'clear_price_rule_logs']);
        add_action('wp_ajax_webhead_bulk_price_update_preview_price_rule', ['WH_Bulk_Price_Update_Ajax', 'preview_price_rule']);
        add_action('wp_ajax_webhead_bulk_price_update_search_attributes', ['WH_Bulk_Price_Update_Ajax', 'search_attributes']);
        add_action('wp_ajax_webhead_bulk_price_update_search_products', ['WH_Bulk_Price_Update_Ajax', 'search_products']);
    }
}
