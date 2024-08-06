<?php
/**
 * Bulk Price Update Core Functions
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2024 Webhead
 */

# Prevent direct file access
defined( 'ABSPATH' ) || exit;

if( !function_exists( 'wh_get_template' ) ) {
    /**
     * This function retrieves the path to a template file within the plugin directory.
     *
     * @param string $template_name The name of the template file (without extension).
     * @param string $ext           The extension of the template file (default: 'php').
     *
     * @return string The full path to the template file.
     */
    function wh_get_template(string $template_name, string $ext = 'php'): string
    {
        return WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_DIR . "templates/{$template_name}.{$ext}";
    }
}

if( !function_exists( 'wh_load_template' ) ) {
    /**
     * This function loads a template file and extracts variables from an array.
     *
     * @param string $template_name The name of the template file (without extension).
     * @param array $params         An array of variables to be extracted and made available within the template (default: []).
     *
     * @return void
     */
    function wh_load_template(string $template_name, array $params = []): void
    {
        extract( $params );
        require wh_get_template( $template_name );
    }
}

if( !function_exists( 'wh_calculate_modified_price' ) ) {
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
    function wh_calculate_modified_price($current_price, $price_value, string $action_type, string $change_type): float
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

if( !function_exists( 'wh_get_language_code' ) ) {
    function wh_get_language_code(string $language): string
    {
        // Normalize language code
        $lang = explode( '-', sanitize_text_field( $lang ) )[0];
        $lang = explode( '_', $lang )[0];

        // Validate language code
        $available_languages = ['en', 'de'];
        $lang = $available_languages[$lang] ?? 'en';

        if( $lang !== 'de' )
            $lang = "/{$lang}";

        return $lang;
    }
}

if( !function_exists( 'wh_get_blog_posts' ) ) {
    /**
     * This function retrieves recent blog posts from a remote server in a specified language.
     *
     * @param string $lang The language code for the desired blog posts (default: 'en').
     * @param int $count   The number of blog posts to retrieve (default: 3).
     *
     * @return array An array containing the retrieved blog posts or an empty array if unsuccessful.
     * @link   https://developer.wordpress.org/rest-api/reference/posts/#list-posts
     */
    function wh_get_blog_posts(string $lang = 'en', int $count = 3): array
    {
        $lang = wh_get_language_code( $lang );

        // Check if cached blog posts are available
        if( false === ( $posts = get_transient( WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . "_{$lang}" ) ) ) {
            // Construct API URL
            $url = "https://webhead.at{$lang}/wp-json/wp/v2/posts?per_page={$count}&context=embed&_embed";

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

if( !function_exists( 'wh_get_plugins' ) ) {
    /**
     * This function retrieves a list of other plugins from a remote server.
     *
     * @return array An array containing the retrieved plugins or an empty array if unsuccessful.
     */
    function wh_get_plugins(): array
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