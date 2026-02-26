<?php
/**
 * Bulk Price Update Ajax - AJAX Event Handlers.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

class WH_Bulk_Price_Update_Ajax
{
    public static function init()
    {
        self::add_ajax_events();
    }

    /** Hook in methods - uses WordPress ajax handlers (admin-ajax) */
    public static function add_ajax_events()
    {
        $ajax_events = [
            'update_product_price',
            'save_settings',
            'get_blog_posts',
            'get_plugins',
            'save_price_rule',
            'delete_price_rule',
            'toggle_price_rule',
            'run_price_rule',
            'get_price_rule_logs',
            'clear_price_rule_logs',
            'preview_price_rule',
            'search_attributes',
            'search_products',
        ];

        foreach ($ajax_events as $ajax_event) {
            add_action("wp_ajax_webhead_bulk_price_update_{$ajax_event}", [__CLASS__, $ajax_event]);
        }
    }

    /**
     * Handles the AJAX request for updating product prices in bulk.
     * This method performs the following actions:
     *  - Checks the security nonce using `check_ajax_referer`.
     *  - Sanitizes and retrieves the user input from the POST request.
     *  - Calls helper functions to:
     *    - Calculate the modified prices based on user input.
     *    - Get product data (including variations).
     *  - Applies price changes to products (if not a preview).
     *  - Prepares and returns the response data.
     *
     * @return void
     */
    public static function update_product_price(): void
    {
        check_ajax_referer('update-product-price', 'security');

        $price_value_raw = sanitize_text_field(wp_unslash($_POST['price_value'] ?? ''));
        if ($price_value_raw === '')
            wp_die();

        $is_preview = (bool)$_POST['is_preview'];
        $result = [];
        $offset = 0;
        $updated_count = 0;
        $table_caption = '';

        // Get block sizes (normal and preview) from settings
        $block_size = get_option('wh_bulk_price_update_block_size', 1024);
        $preview_block_size = get_option('wh_bulk_price_update_preview_block_size', 20);

        // Get time limit from settings (defaults to disabled)
        $time_limit = get_option('wh_bulk_price_update_time_limit', -1);

        // Sanitize and format user input
        $action_type = sanitize_text_field(wp_unslash($_POST['action_type'] ?? ''));
        $change_type = sanitize_text_field(wp_unslash($_POST['change_type'] ?? ''));
        $price_type = sanitize_text_field(wp_unslash($_POST['price_type'] ?? ''));
        $apply_to = sanitize_text_field(wp_unslash($_POST['apply_to'] ?? ''));
        $price_value = webhead_bulk_price_update_parse_price_input($price_value_raw);
        $custom_placeholders = webhead_bulk_price_update_get_custom_expression_placeholders();
        $custom_placeholders_map = array_fill_keys(array_keys($custom_placeholders), true);

        if ($action_type === 'custom') {
            if (!webhead_bulk_price_update_validate_custom_expression($price_value_raw, $custom_placeholders_map)) {
                wp_die();
            }
        } elseif ($price_value <= 0) {
            wp_die();
        }

        $include_products = array_map('intval', (array)$_POST['include_products']);
        $exclude_products = array_map('intval', (array)$_POST['exclude_products']);
        $categories = array_map('intval', (array)$_POST['categories']);
        $tags = array_map('intval', (array)$_POST['tags']);

        // Set preview caption if in preview mode
        if ($is_preview)
            $table_caption = sprintf(
                /* translators: %d: Preview block size. */
                esc_html__('A maximum of %d products are shown in the preview mode', 'wh-bulk-price-update-for-woocommerce'),
                $preview_block_size
            );

        do_action('before_wh_bulk_price_update_product_price');

        // Loop through products in batches until there are no more or time limit is reached
        while (true) {
            if (-1 != $time_limit)
                set_time_limit($time_limit);

            // Build the WP_Query arguments to retrieve products
            $args = [
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => $is_preview ? $preview_block_size : $block_size,
                'offset'         => $offset,
                'fields'         => 'ids',
            ];

            // Apply product filtering based on user selection
            if ($apply_to !== 'all') {
                if (!empty($include_products))
                    $args['post__in'] = $include_products;

                if (!empty($categories)) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'product_cat',
                        'field'    => 'id',
                        'terms'    => $categories,
                        'operator' => 'IN',
                    ];
                }

                if (!empty($tags)) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'product_tag',
                        'field'    => 'id',
                        'terms'    => $tags,
                        'operator' => 'IN',
                    ];
                }

                foreach (wc_get_attribute_taxonomies() as $attr) {
                    $attr_key = wc_attribute_taxonomy_name($attr->attribute_name);
                    if (isset($_POST[$attr_key])) {
                        $attributes = array_map('sanitize_text_field', (array)$_POST[$attr_key]);
                        if (!empty($attributes)) {
                            $args['tax_query'][] = [
                                'taxonomy' => wc_attribute_taxonomy_name($attr->attribute_name),
                                'field'    => 'slug',
                                'terms'    => array_map('sanitize_text_field', $attributes),
                                'operator' => 'IN',
                            ];
                        }
                    }
                }
            }

            $args = apply_filters('wh_bulk_price_update_product_price_query_args', $args);

            // Execute the WP_Query to retrieve products
            $loop = new WP_Query($args);

            // Check if there are any products to process
            if (!$loop->have_posts())
                break;

            // Process each product in the current batch
            foreach ($loop->posts as $product_id) {
                // Apply product exclusion if selected
                if ($_POST['has_exclude_products'] == 1 && !empty($exclude_products) && in_array($product_id, $exclude_products))
                    continue;

                // Getting all product IDs (including variations for variable products)
                $product_ids = [$product_id];
                $product = wc_get_product($product_id);

                if ($product->is_type('variable'))
                    $product_ids = array_merge($product_ids, $product->get_children());

                $product_ids = apply_filters('wh_bulk_price_update_product_price_processed_products', $product_ids, $product);

                // Process each product ID (including variations)
                foreach ($product_ids as $_product_id) {
                    $_product = wc_get_product($_product_id);
                    $_product_attrs = $_product->get_attributes();

                    // Check for attribute-based product exclusion
                    foreach (wc_get_attribute_taxonomies() as $attr) {
                        $attr_key = wc_attribute_taxonomy_name($attr->attribute_name);
                        if (isset($_POST[$attr_key])) {
                            $attributes = array_map('sanitize_text_field', (array)$_POST[$attr_key]);
                            if ($_product->is_type('variation') && !empty($attributes)) {
                                if (
                                    !isset($_product_attrs[wc_attribute_taxonomy_name($attr->attribute_name)])
                                    || (!empty($_product_attrs[wc_attribute_taxonomy_name($attr->attribute_name)]) && !in_array($_product_attrs[wc_attribute_taxonomy_name($attr->attribute_name)], $attributes))
                                ) {
                                    $_product_id = 0;
                                    break;
                                }
                            }
                        }
                    }

                    // Skip if product ID is invalid or excluded
                    if ($_product_id === 0)
                        continue;

                    // Prepare product data for the response
                    $result[$_product_id] = [
                        'name'            => "(#{$_product_id}) {$_product->get_name()}",
                        'permalink'       => $_product->get_permalink(),
                        'categories'      => wp_strip_all_tags(wc_get_product_category_list($product_id)),
                        'tags'            => wp_strip_all_tags(wc_get_product_tag_list($product_id)),
                        'original_prices' => [
                            'price' => wc_price($_product->get_price()),
                        ],
                        'change_prices'   => [],
                    ];

                    $resolved_price_value = $price_value;
                    $resolved_action_type = $action_type;

                    if ($action_type === 'custom') {
                        $placeholder_values = webhead_bulk_price_update_get_custom_expression_placeholder_values($_product);
                        $resolved_price_value = webhead_bulk_price_update_calculate_custom_expression_price($price_value_raw, $placeholder_values);

                        if ($resolved_price_value === null) {
                            continue;
                        }

                        $resolved_action_type = 'fixed';
                    }

                    // Calculate and format modified prices based on user input
                    switch ($price_type) {
                        case 'both':
                            $result[$_product_id]['original_prices']['regular_price'] = wc_price($_product->get_regular_price());
                            $result[$_product_id]['original_prices']['sale_price'] = wc_price($_product->get_sale_price());

                            $result[$_product_id]['change_prices']['price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            $result[$_product_id]['change_prices']['regular_price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_regular_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            $result[$_product_id]['change_prices']['sale_price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_sale_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            break;
                        case 'regular_price':
                            $result[$_product_id]['original_prices']['regular_price'] = wc_price($_product->get_regular_price());

                            $result[$_product_id]['change_prices']['price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            $result[$_product_id]['change_prices']['regular_price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_regular_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            break;
                        case 'sale_price':
                            $result[$_product_id]['original_prices']['sale_price'] = wc_price($_product->get_sale_price());

                            $result[$_product_id]['change_prices']['price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            $result[$_product_id]['change_prices']['sale_price'] = wc_price(webhead_bulk_price_update_calculate_modified_price($_product->get_sale_price(), $resolved_price_value, $resolved_action_type, $change_type));
                            break;
                    }

                    // Apply price changes to products (if not a preview)
                    if (!$is_preview) {
                        foreach ($result[$_product_id]['change_prices'] as $key => $value) {
                            $price = static::extract_price_from_html($value);
                            if ($price <= 0 && $key === 'sale_price') {
                                $price = '';
                            }

                            update_post_meta($_product_id, "_{$key}", $price);
                        }

                        $updated_count += 1;
                    }
                }
            }

            // Increment offset for the next batch
            $offset += $block_size;

            // Stop processing if preview mode and preview limit is reached
            if ($is_preview && $offset >= $preview_block_size)
                break;
        }

        // Reset WordPress post data
        wp_reset_postdata();

        // Set success message for non-preview mode
        if (!$is_preview)
            $table_caption = sprintf(
                /* translators: %d: Updated products count. */
                esc_html__('The price of %d products has been successfully updated', 'wh-bulk-price-update-for-woocommerce'),
                $updated_count
            );

        do_action('after_wh_bulk_price_update_product_price', $result, $updated_count);

        // Load the products table template and return the response
        webhead_bulk_price_update_load_template('products-table', ['products' => $result, 'caption' => $table_caption]);
        wp_die();
    }

    /**
     * Handles the AJAX request for saving bulk price update settings.
     * This method performs the following actions:
     *  - Checks the security nonce using `check_ajax_referer`.
     *  - Sanitizes and retrieves the user input from the POST request.
     *  - Updates the relevant settings in the WordPress options table.
     *  - Sends a JSON success response.
     *
     * @return void
     */
    public static function save_settings()
    {
        check_ajax_referer('save-settings', 'security');

        $cog_meta_key = WH_Price_Rule_Executor::sanitize_cog_meta_key(wp_unslash($_POST['cog_meta_key'] ?? ''));
        if ($cog_meta_key === '') {
            $cog_meta_key = WH_Price_Rule_Executor::get_default_cog_meta_key();
        }

        $available_options = [
            'block_size'         => isset($_POST['block_size']) ? intval($_POST['block_size']) : 1020,
            'preview_block_size' => isset($_POST['preview_block_size']) ? intval($_POST['preview_block_size']) : 20,
            'time_limit'         => isset($_POST['time_limit']) ? intval($_POST['time_limit']) : -1,
            'cog_meta_key'       => $cog_meta_key,
        ];

        foreach ($available_options as $key => $value) {
            update_option("wh_bulk_price_update_{$key}", $value);
        }

        wp_send_json_success();
    }

    /**
     * Handles the AJAX request for retrieving blog posts.
     * This method performs the following actions:
     *  - Checks the security nonce using `check_ajax_referer`.
     *  - Sanitizes and retrieves the language code from the POST request.
     *  - Calls a helper function to get blog posts in the specified language.
     *  - Loads the posts loop template and returns the response.
     *
     * @return void
     */
    public static function get_blog_posts()
    {
        check_ajax_referer('get-blog-posts', 'security');

        // Sanitize input
        $lang = sanitize_text_field($_POST['lang'] ?? 'en');
        $lang = explode('-', $lang);
        $lang = $lang[0];
        $lang = explode('_', $lang);
        $lang = $lang[0];

        webhead_bulk_price_update_load_template('posts-loop', ['posts' => webhead_bulk_price_update_get_blog_posts($lang)]);
        wp_die();
    }

    public static function get_plugins()
    {
        check_ajax_referer('get-plugins', 'security');

        webhead_bulk_price_update_load_template('plugins-loop', ['plugins' => webhead_bulk_price_update_get_plugins()]);
        wp_die();
    }

    /**
     * Save (create or update) a price rule.
     *
     * @return void
     */
    public static function save_price_rule(): void
    {
        check_ajax_referer('save-price-rule', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;

        $data = [
            'name'           => sanitize_text_field($_POST['name'] ?? ''),
            'status'         => sanitize_text_field($_POST['status'] ?? 'active'),
            'schedule_type'  => sanitize_text_field($_POST['schedule_type'] ?? 'daily'),
            'schedule_hours' => wp_unslash($_POST['schedule_hours'] ?? '[]'),
            'rule_type'      => sanitize_text_field($_POST['rule_type'] ?? 'margin_check'),
            'conditions'     => wp_unslash($_POST['conditions'] ?? '{}'),
            'actions'        => wp_unslash($_POST['actions'] ?? '{}'),
        ];

        if (empty($data['name'])) {
            wp_send_json_error(['message' => __('Rule name is required.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $schedule_hours = json_decode($data['schedule_hours'], true);
        $conditions = json_decode($data['conditions'], true);
        $actions = json_decode($data['actions'], true);

        if (! is_array($schedule_hours)) {
            wp_send_json_error(['message' => __('Invalid schedule data.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        if (! is_array($conditions)) {
            $conditions = [];
        }

        if (! is_array($actions)) {
            $actions = [];
        }

        if ($data['rule_type'] === 'margin_check') {
            $margin_tiers_source = [];

            if (! empty($actions['margin_tiers']) && is_array($actions['margin_tiers'])) {
                $margin_tiers_source = $actions['margin_tiers'];
            } elseif (! empty($conditions['margin_tiers']) && is_array($conditions['margin_tiers'])) {
                $margin_tiers_source = $conditions['margin_tiers'];
            }

            $validated_margin_tiers = self::validate_and_normalize_margin_tiers($margin_tiers_source);
            if (is_wp_error($validated_margin_tiers)) {
                wp_send_json_error(['message' => $validated_margin_tiers->get_error_message()]);
            }

            $actions['margin_tiers'] = $validated_margin_tiers;
            $conditions['margin_tiers'] = $validated_margin_tiers;
        }

        $data['schedule_hours'] = wp_json_encode(array_values($schedule_hours));
        $data['conditions'] = wp_json_encode($conditions);
        $data['actions'] = wp_json_encode($actions);

        if ($rule_id > 0) {
            // Update existing rule
            $result = WH_Price_Rule_DB::update($rule_id, $data);

            if ($result) {
                // Reschedule the cron event
                if ($data['status'] === 'active') {
                    WH_Price_Rule_Scheduler::schedule_rule($rule_id);
                } else {
                    WH_Price_Rule_Scheduler::unschedule_rule($rule_id);
                }

                wp_send_json_success([
                    'message' => __('Rule updated successfully.', 'wh-bulk-price-update-for-woocommerce'),
                    'rule_id' => $rule_id,
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to update rule.', 'wh-bulk-price-update-for-woocommerce')]);
            }
        } else {
            // Create new rule
            $new_id = WH_Price_Rule_DB::insert($data);

            if ($new_id) {
                // Schedule the cron event
                if ($data['status'] === 'active') {
                    WH_Price_Rule_Scheduler::schedule_rule($new_id);
                }

                wp_send_json_success([
                    'message' => __('Rule created successfully.', 'wh-bulk-price-update-for-woocommerce'),
                    'rule_id' => $new_id,
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to create rule.', 'wh-bulk-price-update-for-woocommerce')]);
            }
        }
    }

    /**
     * Delete a price rule.
     *
     * @return void
     */
    public static function delete_price_rule(): void
    {
        check_ajax_referer('delete-price-rule', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_id = intval($_POST['rule_id'] ?? 0);

        if ($rule_id <= 0) {
            wp_send_json_error(['message' => __('Invalid rule ID.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        // Unschedule cron event first
        WH_Price_Rule_Scheduler::unschedule_rule($rule_id);

        // Delete associated logs
        WH_Price_Rule_Log_DB::delete_by_rule($rule_id);

        // Delete the rule
        $result = WH_Price_Rule_DB::delete($rule_id);

        if ($result) {
            wp_send_json_success(['message' => __('Rule deleted successfully.', 'wh-bulk-price-update-for-woocommerce')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete rule.', 'wh-bulk-price-update-for-woocommerce')]);
        }
    }

    /**
     * Toggle a price rule's active/paused status.
     *
     * @return void
     */
    public static function toggle_price_rule(): void
    {
        check_ajax_referer('toggle-price-rule', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $rule    = WH_Price_Rule_DB::get($rule_id);

        if (! $rule) {
            wp_send_json_error(['message' => __('Rule not found.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $new_status = $rule->status === 'active' ? 'paused' : 'active';
        $result     = WH_Price_Rule_DB::update($rule_id, ['status' => $new_status]);

        if ($result) {
            if ($new_status === 'active') {
                WH_Price_Rule_Scheduler::schedule_rule($rule_id);
            } else {
                WH_Price_Rule_Scheduler::unschedule_rule($rule_id);
            }

            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %s: New status */
                    __('Rule status changed to %s.', 'wh-bulk-price-update-for-woocommerce'),
                    $new_status
                ),
                'status' => $new_status,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to toggle rule.', 'wh-bulk-price-update-for-woocommerce')]);
        }
    }

    /**
     * Run a price rule immediately.
     *
     * @return void
     */
    public static function run_price_rule(): void
    {
        check_ajax_referer('run-price-rule', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $rule    = WH_Price_Rule_DB::get($rule_id);

        if (! $rule) {
            wp_send_json_error(['message' => __('Rule not found.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $results = WH_Price_Rule_Executor::execute($rule_id);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: 1: Processed count, 2: Adjusted count */
                __('Rule executed. %1$d products processed, %2$d adjusted.', 'wh-bulk-price-update-for-woocommerce'),
                $results['processed'],
                $results['adjusted']
            ),
            'results' => $results,
        ]);
    }

    /**
     * Get logs for a price rule.
     *
     * @return void
     */
    public static function get_price_rule_logs(): void
    {
        check_ajax_referer('get-price-rule-logs', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_id = intval($_POST['rule_id'] ?? 0);

        if ($rule_id <= 0) {
            wp_send_json_error(['message' => __('Invalid rule ID.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule = WH_Price_Rule_DB::get($rule_id);
        if (! $rule) {
            wp_send_json_error(['message' => __('Rule not found.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $page     = max(1, absint($_POST['page'] ?? 1));
        $per_page = max(1, min(100, absint($_POST['per_page'] ?? 20)));
        $filters  = self::get_logs_filters_from_request();

        $log_count = WH_Price_Rule_Log_DB::count_by_rule_filtered($rule_id, $filters);
        $total_pages = max(1, (int) ceil($log_count / $per_page));
        $page = min($page, $total_pages);
        $offset = ($page - 1) * $per_page;

        $logs = WH_Price_Rule_Log_DB::get_by_rule_filtered($rule_id, $filters, $per_page, $offset);
        $logs = self::prepare_logs_for_render($logs);

        $selected_attributes = self::get_selected_attribute_terms_for_logs($filters['attributes']);

        webhead_bulk_price_update_load_template('price-rule-logs', [
            'logs'                => $logs,
            'rule'                => $rule,
            'log_count'           => $log_count,
            'page'                => $page,
            'per_page'            => $per_page,
            'total_pages'         => $total_pages,
            'filters'             => $filters,
            'category_terms'      => self::get_logs_filter_terms('product_cat'),
            'tag_terms'           => self::get_logs_filter_terms('product_tag'),
            'brand_terms'         => self::get_logs_filter_terms('product_brand'),
            'selected_attributes' => $selected_attributes,
        ]);
        wp_die();
    }

    /**
     * Clear all execution logs for a price rule.
     *
     * @return void
     */
    public static function clear_price_rule_logs(): void
    {
        check_ajax_referer('clear-price-rule-logs', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_id = intval($_POST['rule_id'] ?? 0);
        if ($rule_id <= 0) {
            wp_send_json_error(['message' => __('Invalid rule ID.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule = WH_Price_Rule_DB::get($rule_id);
        if (! $rule) {
            wp_send_json_error(['message' => __('Rule not found.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $deleted = WH_Price_Rule_Log_DB::delete_by_rule($rule_id);
        if (! $deleted) {
            wp_send_json_error(['message' => __('Failed to clear logs.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        wp_send_json_success(['message' => __('Execution logs cleared.', 'wh-bulk-price-update-for-woocommerce')]);
    }

    public static function preview_price_rule(): void
    {
        check_ajax_referer('preview-price-rule', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $rule_type  = sanitize_text_field(wp_unslash($_POST['rule_type'] ?? ''));
        $conditions = isset($_POST['conditions']) ? json_decode(wp_unslash($_POST['conditions']), true) : [];
        $actions    = isset($_POST['actions']) ? json_decode(wp_unslash($_POST['actions']), true) : [];

        if (! is_array($conditions)) {
            $conditions = [];
        }

        if (! is_array($actions)) {
            $actions = [];
        }

        if ($rule_type === 'margin_check') {
            $margin_tiers_source = [];

            if (! empty($actions['margin_tiers']) && is_array($actions['margin_tiers'])) {
                $margin_tiers_source = $actions['margin_tiers'];
            } elseif (! empty($conditions['margin_tiers']) && is_array($conditions['margin_tiers'])) {
                $margin_tiers_source = $conditions['margin_tiers'];
            }

            $validated_margin_tiers = self::validate_and_normalize_margin_tiers($margin_tiers_source);
            if (is_wp_error($validated_margin_tiers)) {
                wp_send_json_error(['message' => $validated_margin_tiers->get_error_message()]);
            }

            $actions['margin_tiers'] = $validated_margin_tiers;
            $conditions['margin_tiers'] = $validated_margin_tiers;
        }

        // Build query to get preview products iteratively
        $preview_count = get_option('wh_bulk_price_update_preview_block_size', 20);
        $block_size    = 100;
        $offset        = 0;
        $max_loops     = 5; // Max 500 products checked for preview to avoid timeout
        $loops         = 0;
        $preview_results = [];
        $has_any_products = false;

        while ($loops < $max_loops && count($preview_results) < $preview_count) {
            $args = WH_Price_Rule_Executor::build_query_args($conditions, $block_size, $offset);
            $loop = new WP_Query($args);

            if (! $loop->have_posts()) {
                break;
            }

            $has_any_products = true;

            foreach ($loop->posts as $product_id) {
                $product_ids = [$product_id];
                $product     = wc_get_product($product_id);

                if (! $product) {
                    continue;
                }

                if ($product->is_type('variable')) {
                    $product_ids = array_merge($product_ids, $product->get_children());
                }

                foreach ($product_ids as $_product_id) {
                    $_product = wc_get_product($_product_id);

                    if (! $_product) {
                        continue;
                    }

                    $adjustment = WH_Price_Rule_Executor::evaluate_rule($rule_type, $_product, $conditions, $actions);

                    if ($adjustment !== null) {
                        $field = sanitize_text_field($adjustment['price_field'] ?? '');

                        $preview_results[] = [
                            'product_id'         => (int) $_product_id,
                            'name'               => $_product->get_name(),
                            'edit_link'          => self::get_preview_edit_link($_product_id),
                            'categories'         => self::get_preview_product_categories($_product),
                            'field'              => $field,
                            'field_label'        => self::get_preview_price_field_label($field),
                            'old_price'          => isset($adjustment['old_price']) ? floatval($adjustment['old_price']) : 0.0,
                            'new_price'          => $adjustment['new_price'] ?? '',
                            'is_sale_removed'    => self::is_preview_sale_removed($adjustment),
                            'sale_will_be_removed' => !empty($adjustment['sale_will_be_removed']),
                            'old_sale_price'     => isset($adjustment['old_sale_price']) ? floatval($adjustment['old_sale_price']) : 0.0,
                            'short_reason'       => self::get_preview_short_reason($rule_type, $adjustment, $actions),
                        ];
                    }

                    if (count($preview_results) >= $preview_count) {
                        break 3;
                    }
                }
            }

            wp_reset_postdata();
            $offset += $block_size;
            $loops++;
        }

        $preview_state = 'results';
        if (! $has_any_products) {
            $preview_state = 'no_products';
        } elseif (empty($preview_results)) {
            $preview_state = 'no_adjustments';
        }

        webhead_bulk_price_update_load_template('price-rule-preview', [
            'preview_state'   => $preview_state,
            'preview_results' => $preview_results,
        ]);
        wp_die();
    }

    /**
     * Build product edit link used in Scheduled Rules preview rows.
     *
     * @param int $product_id Product ID.
     *
     * @return string
     */
    protected static function get_preview_edit_link(int $product_id): string
    {
        $edit_post_id = $product_id;
        $product = wc_get_product($product_id);

        if ($product && $product->is_type('variation') && $product->get_parent_id() > 0) {
            $edit_post_id = (int) $product->get_parent_id();
        }

        $link = get_edit_post_link($edit_post_id, '');

        if (empty($link) || false === strpos((string) $link, 'post.php')) {
            $link = admin_url('post.php?post=' . $edit_post_id . '&action=edit');
        }

        return (string) $link;
    }

    /**
     * Get comma-separated category list for the preview row.
     *
     * @param WC_Product $product Product instance.
     *
     * @return string
     */
    protected static function get_preview_product_categories(WC_Product $product): string
    {
        $product_id = $product->get_id();
        $parent_id = (int) $product->get_parent_id();

        // Variations should inherit category context from parent product.
        if ($product->is_type('variation') && $parent_id > 0) {
            $categories = wc_get_product_terms($parent_id, 'product_cat', ['fields' => 'names']);
        } else {
            $categories = wc_get_product_terms($product_id, 'product_cat', ['fields' => 'names']);

            if (empty($categories) && $parent_id > 0) {
                $categories = wc_get_product_terms($parent_id, 'product_cat', ['fields' => 'names']);
            }
        }

        if (empty($categories)) {
            return __('Uncategorized', 'wh-bulk-price-update-for-woocommerce');
        }

        return implode(', ', array_map('sanitize_text_field', $categories));
    }

    /**
     * Return readable label for changed price field.
     *
     * @param string $field Meta key.
     *
     * @return string
     */
    protected static function get_preview_price_field_label(string $field): string
    {
        $labels = [
            '_regular_price' => __('Regular Price', 'wh-bulk-price-update-for-woocommerce'),
            '_sale_price'    => __('Sale Price', 'wh-bulk-price-update-for-woocommerce'),
            '_price'         => __('Active Price', 'wh-bulk-price-update-for-woocommerce'),
        ];

        if (isset($labels[$field])) {
            return $labels[$field];
        }

        $fallback = trim(str_replace('_', ' ', ltrim($field, '_')));
        $fallback = ucwords($fallback);

        return $fallback !== '' ? $fallback : __('Price', 'wh-bulk-price-update-for-woocommerce');
    }

    /**
     * Determine if the adjustment removes sale price.
     *
     * @param array $adjustment Adjustment payload.
     *
     * @return bool
     */
    protected static function is_preview_sale_removed(array $adjustment): bool
    {
        if (($adjustment['price_field'] ?? '') !== '_sale_price') {
            return false;
        }

        if (!isset($adjustment['new_price']) || $adjustment['new_price'] === '' || $adjustment['new_price'] === null) {
            return true;
        }

        return is_numeric($adjustment['new_price']) && floatval($adjustment['new_price']) <= 0;
    }

    /**
     * Build a concise reason text for preview rows.
     *
     * @param string $rule_type  Rule type.
     * @param array  $adjustment Evaluated adjustment payload.
     * @param array  $actions    Rule actions.
     *
     * @return string
     */
    protected static function get_preview_short_reason(string $rule_type, array $adjustment, array $actions): string
    {
        $is_sale_removed = self::is_preview_sale_removed($adjustment);
        $sale_will_be_removed = !empty($adjustment['sale_will_be_removed']) && !empty($adjustment['old_sale_price']);
        $price_field = sanitize_text_field($adjustment['price_field'] ?? '');
        $margin_pct = isset($adjustment['margin_pct']) ? floatval($adjustment['margin_pct']) : 0.0;

        switch ($rule_type) {
            case 'margin_check':
                $margin_label = wc_format_localized_decimal($margin_pct);

                if ($is_sale_removed) {
                    return sprintf(
                        /* translators: %s: margin percent */
                        __('Sale removed; required margin is %s%%.', 'wh-bulk-price-update-for-woocommerce'),
                        $margin_label
                    );
                }

                if ($price_field === '_sale_price') {
                    return sprintf(
                        /* translators: %s: margin percent */
                        __('Sale raised for %s%% margin.', 'wh-bulk-price-update-for-woocommerce'),
                        $margin_label
                    );
                }

                if ($price_field === '_regular_price' && $sale_will_be_removed) {
                    return sprintf(
                        /* translators: %s: margin percent */
                        __('Regular raised for %s%% margin; current sale will be removed.', 'wh-bulk-price-update-for-woocommerce'),
                        $margin_label
                    );
                }

                return sprintf(
                    /* translators: %s: margin percent */
                    __('Regular raised for %s%% margin.', 'wh-bulk-price-update-for-woocommerce'),
                    $margin_label
                );

            case 'max_discount_pct':
                $max_discount = isset($actions['max_discount_pct']) ? floatval($actions['max_discount_pct']) : 0;

                if ($max_discount > 0) {
                    return sprintf(
                        /* translators: %s: max discount percent */
                        __('Discount capped at %s%%.', 'wh-bulk-price-update-for-woocommerce'),
                        wc_format_localized_decimal($max_discount)
                    );
                }

                return __('Discount exceeds rule cap.', 'wh-bulk-price-update-for-woocommerce');

            case 'price_adjustment':
                $action_type = sanitize_text_field($actions['action_type'] ?? '');
                $change_type = sanitize_text_field($actions['change_type'] ?? '');
                $price_value = isset($actions['price_value']) ? floatval($actions['price_value']) : 0;

                if ($is_sale_removed) {
                    return __('Sale removed by rule action.', 'wh-bulk-price-update-for-woocommerce');
                }

                if ($sale_will_be_removed) {
                    return __('Regular change will remove current sale.', 'wh-bulk-price-update-for-woocommerce');
                }

                if ($price_value > 0 && $action_type !== '') {
                    $action_label = self::get_preview_action_label($action_type);
                    $value_label  = $change_type === 'percentage'
                        ? wc_format_localized_decimal($price_value) . '%'
                        : wp_strip_all_tags(wc_price($price_value));

                    return sprintf(
                        /* translators: 1: action label, 2: value */
                        __('%1$s by %2$s.', 'wh-bulk-price-update-for-woocommerce'),
                        $action_label,
                        $value_label
                    );
                }

                return __('Rule adjustment applied.', 'wh-bulk-price-update-for-woocommerce');
        }

        if ($is_sale_removed) {
            return __('Sale removed by rule.', 'wh-bulk-price-update-for-woocommerce');
        }

        if ($sale_will_be_removed) {
            return __('Current sale will be removed.', 'wh-bulk-price-update-for-woocommerce');
        }

        return __('Price will be updated by this rule.', 'wh-bulk-price-update-for-woocommerce');
    }

    /**
     * Get a readable label for adjustment action types.
     *
     * @param string $action_type Action type key.
     *
     * @return string
     */
    protected static function get_preview_action_label(string $action_type): string
    {
        $labels = [
            'increase' => __('Increase', 'wh-bulk-price-update-for-woocommerce'),
            'decrease' => __('Decrease', 'wh-bulk-price-update-for-woocommerce'),
            'multiply' => __('Multiply', 'wh-bulk-price-update-for-woocommerce'),
            'divide'   => __('Divide', 'wh-bulk-price-update-for-woocommerce'),
            'fixed'    => __('Set', 'wh-bulk-price-update-for-woocommerce'),
        ];

        return $labels[$action_type] ?? __('Adjust', 'wh-bulk-price-update-for-woocommerce');
    }

    /**
     * Sanitize log filter values from request.
     *
     * @return array
     */
    protected static function get_logs_filters_from_request(): array
    {
        $date_from = sanitize_text_field(wp_unslash($_POST['date_from'] ?? ''));
        $date_to   = sanitize_text_field(wp_unslash($_POST['date_to'] ?? ''));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $date_from = '';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $date_to = '';
        }

        return [
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'categories' => self::sanitize_id_array_from_request($_POST['categories'] ?? []),
            'tags'       => self::sanitize_id_array_from_request($_POST['tags'] ?? []),
            'brands'     => self::sanitize_id_array_from_request($_POST['brands'] ?? []),
            'attributes' => self::sanitize_id_array_from_request($_POST['attributes'] ?? []),
        ];
    }

    /**
     * Normalize request values into unique positive IDs.
     *
     * @param mixed $raw Request value.
     *
     * @return int[]
     */
    protected static function sanitize_id_array_from_request($raw): array
    {
        $values = wp_unslash((array) $raw);
        $values = array_map('intval', $values);
        $values = array_filter($values, static function ($id) {
            return $id > 0;
        });

        return array_values(array_unique($values));
    }

    /**
     * Validate and normalize margin tiers payload.
     *
     * @param mixed $raw_tiers Raw tiers payload.
     *
     * @return array|WP_Error
     */
    protected static function validate_and_normalize_margin_tiers($raw_tiers)
    {
        if (! is_array($raw_tiers) || empty($raw_tiers)) {
            return new WP_Error(
                'invalid_margin_tiers',
                __('Please add at least one margin tier.', 'wh-bulk-price-update-for-woocommerce')
            );
        }

        $normalized_tiers = [];

        foreach ($raw_tiers as $index => $tier) {
            $row_number = intval($index) + 1;

            if (! is_array($tier)) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: %d: row number */
                        __('Invalid margin tier at row %d.', 'wh-bulk-price-update-for-woocommerce'),
                        $row_number
                    )
                );
            }

            $min_cog = self::normalize_numeric_tier_value($tier['min_cog'] ?? null);
            $max_cog = self::normalize_numeric_tier_value($tier['max_cog'] ?? 0);
            $margin_pct = self::normalize_numeric_tier_value($tier['margin_pct'] ?? null);

            if ($min_cog === null || $min_cog < 0) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: %d: row number */
                        __('Margin tier row %d: "COG From" must be 0 or greater.', 'wh-bulk-price-update-for-woocommerce'),
                        $row_number
                    )
                );
            }

            if ($max_cog === null || $max_cog < 0) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: %d: row number */
                        __('Margin tier row %d: "COG To" must be 0 or greater.', 'wh-bulk-price-update-for-woocommerce'),
                        $row_number
                    )
                );
            }

            if ($margin_pct === null || $margin_pct < 0) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: %d: row number */
                        __('Margin tier row %d: "Margin %%" must be 0 or greater.', 'wh-bulk-price-update-for-woocommerce'),
                        $row_number
                    )
                );
            }

            $is_unlimited = $max_cog <= 0;

            if (! $is_unlimited && $max_cog <= $min_cog) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: %d: row number */
                        __('Margin tier row %d: "COG To" must be greater than "COG From".', 'wh-bulk-price-update-for-woocommerce'),
                        $row_number
                    )
                );
            }

            if ($min_cog == 0.0 && $is_unlimited && $margin_pct == 0.0) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: %d: row number */
                        __('Margin tier row %d: 0 / 0 (unlimited) / 0 is not allowed.', 'wh-bulk-price-update-for-woocommerce'),
                        $row_number
                    )
                );
            }

            $normalized_tiers[] = [
                'min_cog' => $min_cog,
                'max_cog' => $is_unlimited ? 0.0 : $max_cog,
                'margin_pct' => $margin_pct,
            ];
        }

        usort($normalized_tiers, static function (array $a, array $b): int {
            if ($a['min_cog'] === $b['min_cog']) {
                return 0;
            }

            return $a['min_cog'] < $b['min_cog'] ? -1 : 1;
        });

        for ($i = 0; $i < count($normalized_tiers) - 1; $i++) {
            $current_tier = $normalized_tiers[$i];
            $next_tier = $normalized_tiers[$i + 1];

            if ($current_tier['max_cog'] <= 0) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: 1: current tier min COG, 2: next tier min COG */
                        __('Overlapping tiers: a tier starting at %1$s has no maximum, but another tier starts at %2$s.', 'wh-bulk-price-update-for-woocommerce'),
                        wc_format_localized_decimal($current_tier['min_cog']),
                        wc_format_localized_decimal($next_tier['min_cog'])
                    )
                );
            }

            if ($current_tier['max_cog'] > $next_tier['min_cog']) {
                return new WP_Error(
                    'invalid_margin_tiers',
                    sprintf(
                        /* translators: 1: current tier max COG, 2: next tier min COG */
                        __('Overlapping tiers: the tier ending at %1$s overlaps with the tier starting at %2$s.', 'wh-bulk-price-update-for-woocommerce'),
                        wc_format_localized_decimal($current_tier['max_cog']),
                        wc_format_localized_decimal($next_tier['min_cog'])
                    )
                );
            }
        }

        return $normalized_tiers;
    }

    /**
     * Normalize numeric margin tier values.
     *
     * @param mixed $raw_value Raw input value.
     *
     * @return float|null
     */
    protected static function normalize_numeric_tier_value($raw_value): ?float
    {
        if (is_string($raw_value)) {
            $raw_value = trim($raw_value);
        }

        if ($raw_value === '' || $raw_value === null) {
            return null;
        }

        if (! is_numeric($raw_value)) {
            return null;
        }

        return (float) $raw_value;
    }

    /**
     * Load taxonomy options for logs filters.
     *
     * @param string $taxonomy Taxonomy slug.
     *
     * @return WP_Term[]
     */
    protected static function get_logs_filter_terms(string $taxonomy): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'orderby'    => 'name',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        return $terms;
    }

    /**
     * Build selected attribute terms for logs filters select2 prefill.
     *
     * @param int[] $attribute_ids Selected attribute term IDs.
     *
     * @return array<int,array{id:int,text:string}>
     */
    protected static function get_selected_attribute_terms_for_logs(array $attribute_ids): array
    {
        if (empty($attribute_ids)) {
            return [];
        }

        $taxonomies = self::get_public_attribute_taxonomies();
        if (empty($taxonomies)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'include'    => array_values(array_unique(array_map('intval', $attribute_ids))),
            'orderby'    => 'include',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $results = [];
        foreach ($terms as $term) {
            $tax_obj = get_taxonomy($term->taxonomy);
            $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $term->taxonomy;
            $results[] = [
                'id'   => (int) $term->term_id,
                'text' => sprintf('%s: %s', $tax_label, $term->name),
            ];
        }

        return $results;
    }

    /**
     * Return public WooCommerce attribute taxonomies (pa_*).
     *
     * @return array
     */
    protected static function get_public_attribute_taxonomies(): array
    {
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if (empty($attribute_taxonomies)) {
            return [];
        }

        $taxonomies = [];
        foreach ($attribute_taxonomies as $attribute_taxonomy) {
            $taxonomy = wc_attribute_taxonomy_name($attribute_taxonomy->attribute_name);
            if (taxonomy_exists($taxonomy)) {
                $taxonomies[] = $taxonomy;
            }
        }

        return $taxonomies;
    }

    /**
     * Enrich log rows for rendering in the logs table.
     *
     * @param array $logs Raw DB rows.
     *
     * @return array
     */
    protected static function prepare_logs_for_render(array $logs): array
    {
        if (empty($logs)) {
            return [];
        }

        foreach ($logs as &$log) {
            $product_id = isset($log->product_id) ? (int) $log->product_id : 0;
            $product = $product_id > 0 ? wc_get_product($product_id) : false;

            $log->edit_link = $product_id > 0 ? self::get_preview_edit_link($product_id) : '';
            $log->categories = $product instanceof WC_Product
                ? self::get_preview_product_categories($product)
                : __('Uncategorized', 'wh-bulk-price-update-for-woocommerce');

            $price_field = sanitize_text_field((string) ($log->price_field ?? ''));
            $log->field_label = self::get_preview_price_field_label($price_field);

            $new_price = isset($log->new_price) ? floatval($log->new_price) : 0.0;
            $log->is_sale_removed = $price_field === '_sale_price' && $new_price <= 0;
            $log->display_date = isset($log->executed_at)
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $log->executed_at))
                : '';
        }
        unset($log);

        return $logs;
    }

    /**
     * Extract numeric price from WooCommerce formatted price HTML string
     *
     * @param string $priceHtml WooCommerce formatted price string
     *
     * @return float Extracted numeric price
     * @sicne 1.0.7
     */
    protected static function extract_price_from_html(string $priceHtml): float
    {
        // Strip HTML tags
        $text = wp_strip_all_tags($priceHtml);

        // Decode HTML entities (e.g. &#36; → $)
        $charset = get_option('blog_charset', 'UTF-8');
        $text = html_entity_decode($text, ENT_QUOTES, $charset);

        // Get locale-specific separators
        $decimalSeparator = wc_get_price_decimal_separator();
        $thousandSeparator = wc_get_price_thousand_separator();

        // Remove a thousand separator if defined
        if ($thousandSeparator !== '') {
            $text = str_replace($thousandSeparator, '', $text);
        }

        // Normalize decimal separator to dot
        if ($decimalSeparator !== '.') {
            $text = str_replace($decimalSeparator, '.', $text);
        }

        // Remove any non-numeric characters except dot and minus
        $text = preg_replace('/[^0-9.-]/', '', $text);

        return (float) $text;
    }

    /**
     * Search global attributes for Select2 field
     * 
     * @since 1.0.8
     */
    public static function search_attributes(): void
    {
        check_ajax_referer('search-attributes', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $search_term = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
        $ids_raw = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
        $include_ids = array_values(array_filter(array_map('intval', $ids_raw)));

        // We search inside all public taxonomies starting with pa_
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if (empty($attribute_taxonomies)) {
            wp_send_json_success(['results' => []]);
        }

        $taxonomies = [];
        foreach ($attribute_taxonomies as $tax) {
            $taxonomies[] = wc_attribute_taxonomy_name($tax->attribute_name);
        }

        $args = [
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'number'     => 50,
        ];

        if (!empty($include_ids)) {
            $args['include'] = $include_ids;
            $args['orderby'] = 'include';
        } else {
            $args['name__like'] = $search_term;
        }

        $terms = get_terms($args);
        $results = [];

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                // Determine attribute name for better grouping/display (e.g., Color: Red)
                $tax_obj = get_taxonomy($term->taxonomy);
                $tax_label = $tax_obj ? $tax_obj->labels->singular_name : $term->taxonomy;

                $results[] = [
                    'id'   => $term->term_id,
                    'text' => sprintf('%s: %s', $tax_label, $term->name),
                ];
            }
        }

        wp_send_json_success(['results' => $results]);
    }

    /**
     * Fetch product labels by IDs for Select2 prefill.
     *
     * @since 1.0.8
     */
    public static function search_products(): void
    {
        check_ajax_referer('search-products', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'wh-bulk-price-update-for-woocommerce')]);
        }

        $ids_raw = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
        $include_ids = array_values(array_unique(array_filter(array_map('intval', $ids_raw))));

        if (empty($include_ids)) {
            wp_send_json_success(['results' => []]);
        }

        $product_ids = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'post_status'    => 'any',
            'posts_per_page' => count($include_ids),
            'post__in'       => $include_ids,
            'orderby'        => 'post__in',
            'fields'         => 'ids',
        ]);

        $results = [];
        foreach ($product_ids as $product_id) {
            $product = wc_get_product((int) $product_id);
            if (! $product) {
                continue;
            }

            $label = $product->is_type('variation') ? $product->get_formatted_name() : $product->get_name();
            $results[] = [
                'id'   => (int) $product_id,
                'text' => wp_strip_all_tags((string) $label),
            ];
        }

        wp_send_json_success(['results' => $results]);
    }
}

// Initialize the class and its functionalities
WH_Bulk_Price_Update_Ajax::init();
