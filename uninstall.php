<?php
/**
 * Uninstall plugin.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2024 Webhead
 */

# Prevent direct file access
defined( 'ABSPATH' ) || exit;

// Clean up before uninstalling this plugin
delete_option( 'wh_bulk_price_update_block_size' );
delete_option( 'wh_bulk_price_update_preview_block_size' );
delete_option( 'wh_bulk_price_update_time_limit' );
delete_transient( WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . '_en' );
delete_transient( WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . '_de' );
delete_transient( WEBHEAD_BULK_PRICE_UPDATE_PLUGINS_CACHE_KEY );
