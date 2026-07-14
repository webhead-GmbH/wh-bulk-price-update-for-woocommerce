<?php
/**
 * Uninstall plugin.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

// Clean up before uninstalling this plugin.
// Note: the plugin's main file is not loaded during uninstall, so the
// WEBHEAD_BULK_PRICE_UPDATE_* constants are unavailable here. We use the literal
// cache-key values (mirrors the constants defined in wh-bulk-price-update.php) to
// avoid a fatal "undefined constant" error on PHP 8+.
delete_option('wh_bulk_price_update_block_size');
delete_option('wh_bulk_price_update_preview_block_size');
delete_option('wh_bulk_price_update_time_limit');
delete_option('wh_bulk_price_update_cog_meta_key');
delete_transient('wh_blog_posts_en');
delete_transient('wh_blog_posts_de');
delete_transient('wh_plugins');
delete_option('wh_bulk_price_update_db_version');

// Drop price rule tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wh_price_rule_logs"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wh_price_rules"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clear all scheduled price rule cron events
$crons = _get_cron_array();
if (is_array($crons)) {
    foreach ($crons as $timestamp => $cron) {
        foreach ($cron as $hook => $data) {
            if (strpos($hook, 'wh_price_rule_execute') === 0) {
                wp_unschedule_event($timestamp, $hook, array_keys($data));
            }
        }
    }
}
