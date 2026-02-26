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
delete_option('wh_bulk_price_update_block_size');
delete_option('wh_bulk_price_update_preview_block_size');
delete_option('wh_bulk_price_update_time_limit');
delete_option('wh_bulk_price_update_cog_meta_key');
delete_transient(WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . '_en');
delete_transient(WEBHEAD_BULK_PRICE_UPDATE_BLOG_POST_CACHE_KEY . '_de');
delete_transient(WEBHEAD_BULK_PRICE_UPDATE_PLUGINS_CACHE_KEY);
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
