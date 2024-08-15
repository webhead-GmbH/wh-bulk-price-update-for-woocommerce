<?php
/**
 * Bulk Price Update Ajax - AJAX Event Handlers.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2024 Webhead
 */

# Prevent direct file access
defined( 'ABSPATH' ) || exit;

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
        ];

        foreach($ajax_events as $ajax_event) {
            add_action( "wp_ajax_webhead_bulk_price_update_{$ajax_event}", [__CLASS__, $ajax_event] );
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
        check_ajax_referer( 'update-product-price', 'security' );

        if( empty( $_POST['price_value'] ) )
            wp_die();

        $is_preview = (bool)$_POST['is_preview'];
        $result = [];
        $offset = 0;
        $updated_count = 0;
        $table_caption = '';

        // Get block sizes (normal and preview) from settings
        $block_size = get_option( 'wh_bulk_price_update_block_size', 1024 );
        $preview_block_size = get_option( 'wh_bulk_price_update_preview_block_size', 20 );

        // Get time limit from settings (defaults to disabled)
        $time_limit = get_option( 'wh_bulk_price_update_time_limit', -1 );

        // Sanitize and format user input
        $price_value = floatval( sanitize_text_field( $_POST['price_value'] ) );
        $action_type = sanitize_text_field( $_POST['action_type'] );
        $change_type = sanitize_text_field( $_POST['change_type'] );
        $price_type = sanitize_text_field( $_POST['price_type'] );
        $apply_to = sanitize_text_field( $_POST['apply_to'] );
        $include_products = array_map( 'intval', (array)$_POST['include_products'] );
        $exclude_products = array_map( 'intval', (array)$_POST['exclude_products'] );
        $categories = array_map( 'intval', (array)$_POST['categories'] );
        $tags = array_map( 'intval', (array)$_POST['tags'] );

        // Set preview caption if in preview mode
        if( $is_preview )
            $table_caption = sprintf(
            /* translators: %d: Preview block size. */
                esc_html__( 'A maximum of %d products are shown in the preview mode', 'wh-bulk-price-update-for-woocommerce' ),
                $preview_block_size
            );

        do_action( 'before_wh_bulk_price_update_product_price' );

        // Loop through products in batches until there are no more or time limit is reached
        while(true) {
            if( -1 != $time_limit )
                set_time_limit( $time_limit );

            // Build the WP_Query arguments to retrieve products
            $args = [
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => $is_preview ? $preview_block_size : $block_size,
                'offset'         => $offset,
                'fields'         => 'ids',
            ];

            // Apply product filtering based on user selection
            if( $apply_to !== 'all' ) {
                if( !empty( $include_products ) )
                    $args['post__in'] = $include_products;

                if( !empty( $categories ) ) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'product_cat',
                        'field'    => 'id',
                        'terms'    => $categories,
                        'operator' => 'IN',
                    ];
                }

                if( !empty( $tags ) ) {
                    $args['tax_query'][] = [
                        'taxonomy' => 'product_tag',
                        'field'    => 'id',
                        'terms'    => $tags,
                        'operator' => 'IN',
                    ];
                }

                foreach(wc_get_attribute_taxonomies() as $attr) {
                    $attr_key = wc_attribute_taxonomy_name( $attr->attribute_name );
                    if( isset( $_POST[$attr_key] ) ) {
                        $attributes = array_map( 'sanitize_text_field', (array)$_POST[$attr_key] );
                        if( !empty( $attributes ) ) {
                            $args['tax_query'][] = [
                                'taxonomy' => wc_attribute_taxonomy_name( $attr->attribute_name ),
                                'field'    => 'slug',
                                'terms'    => array_map( 'sanitize_text_field', $attributes ),
                                'operator' => 'IN',
                            ];
                        }
                    }
                }
            }

            $args = apply_filters( 'wh_bulk_price_update_product_price_query_args', $args );

            // Execute the WP_Query to retrieve products
            $loop = new WP_Query( $args );

            // Check if there are any products to process
            if( !$loop->have_posts() )
                break;

            // Process each product in the current batch
            foreach($loop->posts as $product_id) {
                // Apply product exclusion if selected
                if( $_POST['has_exclude_products'] == 1 && !empty( $exclude_products ) && in_array( $product_id, $exclude_products ) )
                    continue;

                // Getting all product IDs (including variations for variable products)
                $product_ids = [$product_id];
                $product = wc_get_product( $product_id );

                if( $product->is_type( 'variable' ) )
                    $product_ids = array_merge( $product_ids, $product->get_children() );

                $product_ids = apply_filters( 'wh_bulk_price_update_product_price_processed_products', $product_ids, $product );

                // Process each product ID (including variations)
                foreach($product_ids as $_product_id) {
                    $_product = wc_get_product( $_product_id );
                    $_product_attrs = $_product->get_attributes();

                    // Check for attribute-based product exclusion
                    foreach(wc_get_attribute_taxonomies() as $attr) {
                        if( $_product->is_type( 'variation' ) && !empty( $attributes = $_POST[wc_attribute_taxonomy_name( $attr->attribute_name )] ) ) {
                            if(
                                !isset( $_product_attrs[wc_attribute_taxonomy_name( $attr->attribute_name )] )
                                || ( !empty( $_product_attrs[wc_attribute_taxonomy_name( $attr->attribute_name )] ) && !in_array( $_product_attrs[wc_attribute_taxonomy_name( $attr->attribute_name )], $attributes ) )
                            ) {
                                $_product_id = 0;
                                break;
                            }
                        }
                    }

                    // Skip if product ID is invalid or excluded
                    if( $_product_id === 0 )
                        continue;

                    // Prepare product data for the response
                    $result[$_product_id] = [
                        'name'            => "(#{$_product_id}) {$_product->get_name()}",
                        'permalink'       => $_product->get_permalink(),
                        'categories'      => wp_strip_all_tags( wc_get_product_category_list( $product_id ) ),
                        'tags'            => wp_strip_all_tags( wc_get_product_tag_list( $product_id ) ),
                        'original_prices' => [
                            'price' => wc_price( $_product->get_price() ),
                        ],
                        'change_prices'   => [],
                    ];

                    // Calculate and format modified prices based on user input
                    switch($price_type) {
                        case 'both':
                            $result[$_product_id]['original_prices']['regular_price'] = wc_price( $_product->get_regular_price() );
                            $result[$_product_id]['original_prices']['sale_price'] = wc_price( $_product->get_sale_price() );

                            $result[$_product_id]['change_prices']['price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_price(), $price_value, $action_type, $change_type ) );
                            $result[$_product_id]['change_prices']['regular_price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_regular_price(), $price_value, $action_type, $change_type ) );
                            $result[$_product_id]['change_prices']['sale_price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_sale_price(), $price_value, $action_type, $change_type ) );
                            break;
                        case 'regular_price':
                            $result[$_product_id]['original_prices']['regular_price'] = wc_price( $_product->get_regular_price() );

                            $result[$_product_id]['change_prices']['price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_price(), $price_value, $action_type, $change_type ) );
                            $result[$_product_id]['change_prices']['regular_price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_regular_price(), $price_value, $action_type, $change_type ) );
                            break;
                        case 'sale_price':
                            $result[$_product_id]['original_prices']['sale_price'] = wc_price( $_product->get_sale_price() );

                            $result[$_product_id]['change_prices']['price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_price(), $price_value, $action_type, $change_type ) );
                            $result[$_product_id]['change_prices']['sale_price'] = wc_price( webhead_bulk_price_update_calculate_modified_price( $_product->get_sale_price(), $price_value, $action_type, $change_type ) );
                            break;
                    }

                    // Apply price changes to products (if not a preview)
                    if( !$is_preview ) {
                        foreach($result[$_product_id]['change_prices'] as $key => $value)
                            update_post_meta( $_product_id, "_{$key}", $value );

                        $updated_count += 1;
                    }
                }
            }

            // Increment offset for the next batch
            $offset += $block_size;

            // Stop processing if preview mode and preview limit is reached
            if( $is_preview && $offset >= $preview_block_size )
                break;
        }

        // Reset WordPress post data
        wp_reset_postdata();

        // Set success message for non-preview mode
        if( !$is_preview )
            $table_caption = sprintf(
            /* translators: %d: Updated products count. */
                esc_html__( 'The price of %d products has been successfully updated', 'wh-bulk-price-update-for-woocommerce' ),
                $updated_count
            );

        do_action( 'after_wh_bulk_price_update_product_price', $result, $updated_count );

        // Load the products table template and return the response
        webhead_bulk_price_update_load_template( 'products-table', ['products' => $result, 'caption' => $table_caption] );
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
        check_ajax_referer( 'save-settings', 'security' );

        $available_options = [
            'block_size'         => isset( $_POST['block_size'] ) ? intval( $_POST['block_size'] ) : 1020,
            'preview_block_size' => isset( $_POST['preview_block_size'] ) ? intval( $_POST['preview_block_size'] ) : 20,
            'time_limit'         => isset( $_POST['time_limit'] ) ? intval( $_POST['time_limit'] ) : -1,
        ];

        foreach($available_options as $key => $value) {
            update_option( "wh_bulk_price_update_{$key}", $value );
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
        check_ajax_referer( 'get-blog-posts', 'security' );

        // Sanitize input
        $lang = sanitize_text_field( $_POST['lang'] ?? 'en' );
        $lang = explode( '-', $lang );
        $lang = $lang[0];
        $lang = explode( '_', $lang );
        $lang = $lang[0];

        webhead_bulk_price_update_load_template( 'posts-loop', ['posts' => webhead_bulk_price_update_get_blog_posts( $lang )] );
        wp_die();
    }

    public static function get_plugins()
    {
        check_ajax_referer( 'get-plugins', 'security' );

        webhead_bulk_price_update_load_template( 'plugins-loop', ['plugins' => webhead_bulk_price_update_get_plugins()] );
        wp_die();
    }
}

// Initialize the class and its functionalities
WH_Bulk_Price_Update_Ajax::init();