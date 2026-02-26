<?php
/**
 * Bulk Price Update Core Functions
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2024 Webhead
 */

# Prevent direct file access
defined( 'ABSPATH' ) || exit;

if( !function_exists( 'webhead_bulk_price_update_get_template' ) ) {
    /**
     * This function retrieves the path to a template file within the plugin directory.
     *
     * @param string $template_name The name of the template file (without extension).
     * @param string $ext           The extension of the template file (default: 'php').
     *
     * @return string The full path to the template file.
     */
    function webhead_bulk_price_update_get_template(string $template_name, string $ext = 'php'): string
    {
        $template_name = sanitize_file_name( $template_name );
        $ext = sanitize_text_field( strtolower( $ext ) );
        return WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . "templates/{$template_name}.{$ext}";
    }
}

if( !function_exists( 'webhead_bulk_price_update_load_template' ) ) {
    /**
     * This function loads a template file and extracts variables from an array.
     *
     * @param string $template_name The name of the template file (without extension).
     * @param array $params         An array of variables to be extracted and made available within the template (default: []).
     *
     * @return void
     */
    function webhead_bulk_price_update_load_template(string $template_name, array $params = []): void
    {
        extract( $params );
        require webhead_bulk_price_update_get_template( $template_name );
    }
}

if( !function_exists( 'webhead_bulk_price_update_parse_price_input' ) ) {
    /**
     * Parse a price input string into float.
     *
     * @param string|float|int $price_value Raw user input.
     *
     * @return float
     */
    function webhead_bulk_price_update_parse_price_input($price_value): float
    {
        $price_value = sanitize_text_field( (string) $price_value );

        if( function_exists( 'wc_format_decimal' ) ) {
            return floatval( wc_format_decimal( $price_value, false ) );
        }

        $price_value = str_replace( ',', '.', $price_value );
        $price_value = preg_replace( '/[^0-9.\-]/', '', $price_value );

        if( empty( $price_value ) )
            return 0.0;

        return floatval( $price_value );
    }
}

if( !function_exists( 'webhead_bulk_price_update_get_product_cost_value' ) ) {
    /**
     * Resolve product cost value using configured and fallback COG meta keys.
     *
     * @param WC_Product $product Product instance.
     *
     * @return float
     */
    function webhead_bulk_price_update_get_product_cost_value(WC_Product $product): float
    {
        $product_ids = [$product->get_id()];

        if( $product->is_type( 'variation' ) ) {
            $parent_id = $product->get_parent_id();
            if( $parent_id > 0 ) {
                $product_ids[] = $parent_id;
            }
        }

        $meta_keys = ['_cogs_total_value', '_cog_cost', '_wc_cog_cost', '_alg_wc_cog_cost'];
        if( class_exists( 'WH_Price_Rule_Executor' ) ) {
            $configured_key = get_option( 'wh_bulk_price_update_cog_meta_key', WH_Price_Rule_Executor::get_default_cog_meta_key() );
            $configured_key = WH_Price_Rule_Executor::sanitize_cog_meta_key( $configured_key );
            if( $configured_key !== '' ) {
                array_unshift( $meta_keys, $configured_key );
            }
        }

        $meta_keys = array_values( array_unique( array_filter( $meta_keys ) ) );

        foreach( $product_ids as $product_id ) {
            foreach( $meta_keys as $meta_key ) {
                $cog_value = get_post_meta( $product_id, $meta_key, true );
                if( $cog_value !== '' && $cog_value !== false ) {
                    return webhead_bulk_price_update_parse_price_input( $cog_value );
                }
            }
        }

        return 0.0;
    }
}

if( !function_exists( 'webhead_bulk_price_update_get_custom_expression_placeholders' ) ) {
    /**
     * Get custom expression placeholders metadata.
     *
     * Developers can extend placeholders via:
     * `wh_bulk_price_update_custom_expression_placeholders`
     *
     * @return array
     */
    function webhead_bulk_price_update_get_custom_expression_placeholders(): array
    {
        $placeholders = [
            'regular_price' => [
                'label'        => __( 'Regular Price', 'wh-bulk-price-update-for-woocommerce' ),
                'description'  => __( 'Current regular price of the product.', 'wh-bulk-price-update-for-woocommerce' ),
                'sample_value' => 150,
            ],
            'sale_price'    => [
                'label'        => __( 'Sale Price', 'wh-bulk-price-update-for-woocommerce' ),
                'description'  => __( 'Current sale price of the product.', 'wh-bulk-price-update-for-woocommerce' ),
                'sample_value' => 120,
            ],
            'cost'          => [
                'label'        => __( 'Cost', 'wh-bulk-price-update-for-woocommerce' ),
                'description'  => __( 'Cost of goods value (configured COG meta key and fallbacks).', 'wh-bulk-price-update-for-woocommerce' ),
                'sample_value' => 95,
            ],
        ];

        $placeholders = apply_filters( 'wh_bulk_price_update_custom_expression_placeholders', $placeholders );

        $normalized = [];
        foreach( (array) $placeholders as $key => $placeholder ) {
            if( !is_array( $placeholder ) ) {
                continue;
            }

            $token = is_string( $key ) ? $key : (string) ( $placeholder['token'] ?? '' );
            $token = strtolower( sanitize_key( $token ) );
            if( $token === '' ) {
                continue;
            }

            $normalized[ $token ] = [
                'label'        => sanitize_text_field( $placeholder['label'] ?? $token ),
                'description'  => sanitize_text_field( $placeholder['description'] ?? '' ),
                'sample_value' => floatval( $placeholder['sample_value'] ?? 0 ),
            ];
        }

        return $normalized;
    }
}

if( !function_exists( 'webhead_bulk_price_update_get_custom_expression_placeholder_values' ) ) {
    /**
     * Resolve placeholder values for a given product.
     *
     * Developers can extend runtime values via:
     * `wh_bulk_price_update_custom_expression_placeholder_values`
     *
     * @param WC_Product $product Product instance.
     *
     * @return array<string,float>
     */
    function webhead_bulk_price_update_get_custom_expression_placeholder_values(WC_Product $product): array
    {
        $regular_price = webhead_bulk_price_update_parse_price_input( $product->get_regular_price() );
        if( $regular_price <= 0 ) {
            $regular_price = webhead_bulk_price_update_parse_price_input( $product->get_price() );
        }

        $placeholders = webhead_bulk_price_update_get_custom_expression_placeholders();

        $values = [
            'regular_price' => $regular_price,
            'sale_price'    => webhead_bulk_price_update_parse_price_input( $product->get_sale_price() ),
            'cost'          => webhead_bulk_price_update_get_product_cost_value( $product ),
        ];

        $values = apply_filters(
            'wh_bulk_price_update_custom_expression_placeholder_values',
            $values,
            $product,
            $placeholders
        );

        $normalized = [];
        foreach( (array) $values as $token => $value ) {
            $token = strtolower( sanitize_key( (string) $token ) );
            if( $token === '' ) {
                continue;
            }

            $normalized[ $token ] = floatval( $value );
        }

        foreach( array_keys( $placeholders ) as $token ) {
            if( !isset( $normalized[ $token ] ) ) {
                $normalized[ $token ] = 0.0;
            }
        }

        return $normalized;
    }
}

if( !function_exists( 'webhead_bulk_price_update_evaluate_math_expression' ) ) {
    /**
     * Evaluate a basic math expression safely using tokenization and RPN.
     *
     * Supported operators: +, -, *, / and parentheses.
     *
     * @param string $expression Math expression without placeholders.
     *
     * @return float|null
     */
    function webhead_bulk_price_update_evaluate_math_expression(string $expression): ?float
    {
        $expression = str_replace( ',', '.', sanitize_text_field( $expression ) );
        $expression = preg_replace( '/\s+/', '', $expression );

        if( $expression === '' || !preg_match( '/^[0-9+\-*\/().]+$/', $expression ) ) {
            return null;
        }

        preg_match_all( '/\d*\.\d+|\d+|[+\-*\/()]/', $expression, $matches );
        $tokens = $matches[0] ?? [];

        if( implode( '', $tokens ) !== $expression ) {
            return null;
        }

        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];
        $output     = [];
        $operators  = [];
        $prev_type  = 'start';

        foreach( $tokens as $token ) {
            if( is_numeric( $token ) ) {
                $output[]  = $token;
                $prev_type = 'number';
                continue;
            }

            if( $token === '(' ) {
                $operators[] = $token;
                $prev_type   = '(';
                continue;
            }

            if( $token === ')' ) {
                while( !empty( $operators ) && end( $operators ) !== '(' ) {
                    $output[] = array_pop( $operators );
                }

                if( empty( $operators ) ) {
                    return null;
                }

                array_pop( $operators );
                $prev_type = ')';
                continue;
            }

            if( !isset( $precedence[ $token ] ) ) {
                return null;
            }

            // Unary minus: convert "-X" to "0 - X".
            if( $token === '-' && in_array( $prev_type, ['start', 'op', '('], true ) ) {
                $output[] = '0';
            }

            while(
                !empty( $operators ) &&
                isset( $precedence[ end( $operators ) ] ) &&
                $precedence[ end( $operators ) ] >= $precedence[ $token ]
            ) {
                $output[] = array_pop( $operators );
            }

            $operators[] = $token;
            $prev_type   = 'op';
        }

        while( !empty( $operators ) ) {
            $operator = array_pop( $operators );
            if( $operator === '(' || $operator === ')' ) {
                return null;
            }
            $output[] = $operator;
        }

        $stack = [];
        foreach( $output as $token ) {
            if( is_numeric( $token ) ) {
                $stack[] = floatval( $token );
                continue;
            }

            if( count( $stack ) < 2 ) {
                return null;
            }

            $b = array_pop( $stack );
            $a = array_pop( $stack );

            switch( $token ) {
                case '+':
                    $result = $a + $b;
                    break;
                case '-':
                    $result = $a - $b;
                    break;
                case '*':
                    $result = $a * $b;
                    break;
                case '/':
                    if( $b == 0.0 ) {
                        return null;
                    }
                    $result = $a / $b;
                    break;
                default:
                    return null;
            }

            if( !is_finite( $result ) ) {
                return null;
            }

            $stack[] = $result;
        }

        if( count( $stack ) !== 1 ) {
            return null;
        }

        return floatval( $stack[0] );
    }
}

if( !function_exists( 'webhead_bulk_price_update_validate_custom_expression' ) ) {
    /**
     * Validate custom expression placeholders and syntax.
     *
     * @param string $expression         Expression string.
     * @param array  $allowed_tokens_map Allowed placeholder token map.
     *
     * @return bool
     */
    function webhead_bulk_price_update_validate_custom_expression(string $expression, array $allowed_tokens_map = []): bool
    {
        $expression = trim( sanitize_text_field( $expression ) );
        if( $expression === '' ) {
            return false;
        }

        if( empty( $allowed_tokens_map ) ) {
            $allowed_tokens_map = array_fill_keys( array_keys( webhead_bulk_price_update_get_custom_expression_placeholders() ), true );
        }

        $placeholder_pattern = '/\{([a-zA-Z0-9_]+)\}/';
        if( preg_match_all( $placeholder_pattern, $expression, $matches ) ) {
            foreach( (array) ( $matches[1] ?? [] ) as $token ) {
                $token = strtolower( sanitize_key( $token ) );
                if( $token === '' || !isset( $allowed_tokens_map[ $token ] ) ) {
                    return false;
                }
            }
        }

        $test_expression = preg_replace( $placeholder_pattern, '1', $expression );

        return webhead_bulk_price_update_evaluate_math_expression( (string) $test_expression ) !== null;
    }
}

if( !function_exists( 'webhead_bulk_price_update_calculate_custom_expression_price' ) ) {
    /**
     * Calculate custom expression result for a specific placeholder value map.
     *
     * @param string $expression        Expression string.
     * @param array  $placeholder_values Placeholder values.
     *
     * @return float|null
     */
    function webhead_bulk_price_update_calculate_custom_expression_price(string $expression, array $placeholder_values): ?float
    {
        $allowed_tokens_map = [];
        foreach( array_keys( $placeholder_values ) as $token ) {
            $token = strtolower( sanitize_key( (string) $token ) );
            if( $token !== '' ) {
                $allowed_tokens_map[ $token ] = true;
            }
        }

        if( !webhead_bulk_price_update_validate_custom_expression( $expression, $allowed_tokens_map ) ) {
            return null;
        }

        $placeholder_pattern = '/\{([a-zA-Z0-9_]+)\}/';
        $expression_with_values = preg_replace_callback(
            $placeholder_pattern,
            static function(array $matches) use ($placeholder_values): string {
                $token = strtolower( sanitize_key( (string) ( $matches[1] ?? '' ) ) );
                $value = floatval( $placeholder_values[ $token ] ?? 0 );
                return (string) $value;
            },
            $expression
        );

        return webhead_bulk_price_update_evaluate_math_expression( (string) $expression_with_values );
    }
}

if( !function_exists( 'webhead_bulk_price_update_calculate_modified_price' ) ) {
    /**
     * This function calculates a modified price based on user input.
     *
     * @param float|string $current_price The original price of the product.
     * @param float|string $price_value   The user-specified value for price change.
     * @param string $action_type         The type of action to perform (increase, decrease, multiply, divide).
     * @param string $change_type         The type of change to apply (fixed amount or percentage).
     *
     * @return float The calculated modified price.
     */
    function webhead_bulk_price_update_calculate_modified_price($current_price, $price_value, string $action_type, string $change_type): float
    {
        $current_price = floatval( $current_price );
        $price_value = floatval( $price_value );

        if( empty( $price_value ) )
            return 0;

        if( $action_type === 'fixed' )
            return $price_value;

        if( $change_type === 'fixed' ) {
            if( $action_type === 'increase' )
                return $current_price + $price_value;
            elseif( $action_type === 'decrease' )
                return $current_price - $price_value;
            elseif( $action_type === 'multiply' )
                return $current_price * $price_value;
            elseif( $action_type === 'divide' && $price_value > 0 )
                return $current_price / $price_value;

        } elseif( $change_type === 'percentage' ) {
            if( $action_type === 'increase' )
                return $current_price + ( ( $current_price * $price_value ) / 100 );
            elseif( $action_type === 'decrease' )
                return $current_price - ( ( $current_price * $price_value ) / 100 );
            elseif( $action_type === 'multiply' && $price_value > 0 )
                return $current_price * ( $price_value / 100 );
            elseif( $action_type === 'divide' && $price_value > 0 )
                return $current_price / ( $price_value / 100 );
        }

        // Default return if no valid conditions met
        return 0;
    }
}

if( !function_exists( 'webhead_bulk_price_update_get_language_code' ) ) {
    function webhead_bulk_price_update_get_language_code(string $lang): string
    {
        // Normalize language code
        $lang = explode( '-', sanitize_text_field( $lang ) )[0];
        $lang = explode( '_', $lang )[0];

        // Validate language code
        $available_languages = ['en', 'de'];
        return $available_languages[$lang] ?? 'en';
    }
}

if( !function_exists( 'webhead_bulk_price_update_get_blog_posts' ) ) {
    /**
     * This function retrieves recent blog posts from a remote server in a specified language.
     *
     * @param string $lang The language code for the desired blog posts (default: 'en').
     * @param int $count   The number of blog posts to retrieve (default: 3).
     *
     * @return array An array containing the retrieved blog posts or an empty array if unsuccessful.
     * @link   https://developer.wordpress.org/rest-api/reference/posts/#list-posts
     */
    function webhead_bulk_price_update_get_blog_posts(string $lang = 'en', int $count = 3): array
    {
        $lang = webhead_bulk_price_update_get_language_code( $lang );

        // Check if cached blog posts are available
        if( false === ( $posts = get_transient( WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . "_{$lang}" ) ) ) {

            $site_lang = $lang;
            if( $site_lang !== 'de' )
                $site_lang = "/{$site_lang}";

            // Construct API URL
            $url = "https://webhead.at{$site_lang}/wp-json/wp/v2/posts?per_page={$count}&context=embed&_embed";

            // Perform remote GET request
            $response = wp_remote_get( esc_url_raw( $url ) );

            // Retry request with SSL verification disabled if initial attempt fails
            if( is_wp_error( $response ) )
                $response = wp_remote_get( esc_url_raw( $url ), ['sslverify' => false] );

            if( is_wp_error( $response ) )
                return [];

            $posts = json_decode( wp_remote_retrieve_body( $response ), true );

            // Cache blog posts for 24 hours
            set_transient( WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . "_{$lang}", $posts, DAY_IN_SECONDS );
        }

        return $posts;
    }
}

if( !function_exists( 'webhead_bulk_price_update_get_plugins' ) ) {
    /**
     * This function retrieves a list of other plugins from a remote server.
     *
     * @return array An array containing the retrieved plugins or an empty array if unsuccessful.
     */
    function webhead_bulk_price_update_get_plugins(): array
    {
        // Check if cached blog posts are available
        if( false === ( $plugins = get_transient( WEBHEAD_BULK_PRICE_UPDATE_PLUGINS_CACHE_KEY ) ) ) {
            // Construct API URL
            $url = "https://plugins.webhead.at/apps/v1/plugins.json";

            // Perform remote GET request
            $response = wp_remote_get( esc_url_raw( $url ) );

            // Retry request with SSL verification disabled if initial attempt fails
            if( is_wp_error( $response ) )
                $response = wp_remote_get( esc_url_raw( $url ), ['sslverify' => false] );

            if( is_wp_error( $response ) )
                return [];

            $plugins = json_decode( wp_remote_retrieve_body( $response ), true );

            // Cache plugins list for 24 hours
            set_transient( WEBHEAD_BULK_PRICE_UPDATE_PLUGINS_CACHE_KEY, $plugins, DAY_IN_SECONDS );
        }

        return $plugins;
    }
}
