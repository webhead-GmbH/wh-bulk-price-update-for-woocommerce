<?php
/**
 * Price Rule Scheduler.
 *
 * Manages WP-Cron events for scheduled price rules.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

class WH_Price_Rule_Scheduler
{
    /** @var string Cron hook prefix */
    const CRON_HOOK_PREFIX = 'wh_price_rule_execute_';

    /**
     * Initialize the scheduler hooks.
     *
     * @return void
     */
    public static function init(): void
    {
        // Register custom cron intervals
        add_filter('cron_schedules', [__CLASS__, 'add_cron_intervals']);

        // Register execution hooks for all active rules
        self::register_execution_hooks();
    }

    /**
     * Add custom cron intervals.
     *
     * @param array $schedules Existing cron schedules.
     *
     * @return array Modified cron schedules.
     */
    public static function add_cron_intervals(array $schedules): array
    {
        if (! isset($schedules['every_two_hours'])) {
            $schedules['every_two_hours'] = [
                'interval' => 2 * HOUR_IN_SECONDS,
                'display'  => __('Every 2 Hours', 'wh-bulk-price-update-for-woocommerce'),
            ];
        }

        if (! isset($schedules['every_four_hours'])) {
            $schedules['every_four_hours'] = [
                'interval' => 4 * HOUR_IN_SECONDS,
                'display'  => __('Every 4 Hours', 'wh-bulk-price-update-for-woocommerce'),
            ];
        }

        if (! isset($schedules['every_six_hours'])) {
            $schedules['every_six_hours'] = [
                'interval' => 6 * HOUR_IN_SECONDS,
                'display'  => __('Every 6 Hours', 'wh-bulk-price-update-for-woocommerce'),
            ];
        }

        return $schedules;
    }

    /**
     * Register execution hooks for all active rules.
     *
     * @return void
     */
    public static function register_execution_hooks(): void
    {
        if (! WH_Price_Rule_DB::table_exists()) {
            return;
        }

        $rules = WH_Price_Rule_DB::get_active();

        foreach ($rules as $rule) {
            $hook = self::CRON_HOOK_PREFIX . $rule->id;
            add_action($hook, ['WH_Price_Rule_Executor', 'execute'], 10, 1);
        }

        // Also register a generic hook for any rule
        add_action('wh_price_rule_execute', ['WH_Price_Rule_Executor', 'execute'], 10, 1);
    }

    /**
     * Schedule a rule's cron event.
     *
     * @param int $rule_id Rule ID.
     *
     * @return bool Whether scheduling succeeded.
     */
    public static function schedule_rule(int $rule_id): bool
    {
        $rule = WH_Price_Rule_DB::get($rule_id);

        if (! $rule || $rule->status !== 'active') {
            return false;
        }

        // First, clear any existing schedule
        self::unschedule_rule($rule_id);

        $hook = self::CRON_HOOK_PREFIX . $rule_id;

        // Register the action hook
        if (! has_action($hook)) {
            add_action($hook, ['WH_Price_Rule_Executor', 'execute'], 10, 1);
        }

        $recurrence = self::get_wp_recurrence($rule->schedule_type);
        $schedule_hours = json_decode($rule->schedule_hours, true) ?: [];

        if ($rule->schedule_type === 'custom' && ! empty($schedule_hours)) {
            // For custom schedules, find the next occurrence from the specified hours
            $next_run = self::get_next_custom_run($schedule_hours);
            wp_schedule_single_event($next_run, $hook, [$rule_id]);
        } else {
            // For standard schedules, use WP Cron recurring
            $next_run = self::get_next_run_time($rule->schedule_type, $schedule_hours);
            wp_schedule_event($next_run, $recurrence, $hook, [$rule_id]);
        }

        return true;
    }

    /**
     * Unschedule a rule's cron event.
     *
     * @param int $rule_id Rule ID.
     *
     * @return void
     */
    public static function unschedule_rule(int $rule_id): void
    {
        $hook = self::CRON_HOOK_PREFIX . $rule_id;

        // Clear recurring event
        $timestamp = wp_next_scheduled($hook, [$rule_id]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook, [$rule_id]);
        }

        // Clear all events for this hook
        wp_clear_scheduled_hook($hook, [$rule_id]);
    }

    /**
     * Reschedule all active rules.
     * Called on plugin activation.
     *
     * @return void
     */
    public static function reschedule_all(): void
    {
        if (! WH_Price_Rule_DB::table_exists()) {
            return;
        }

        $rules = WH_Price_Rule_DB::get_active();

        foreach ($rules as $rule) {
            self::schedule_rule($rule->id);
        }
    }

    /**
     * Unschedule all rules.
     * Called on plugin deactivation.
     *
     * @return void
     */
    public static function unschedule_all(): void
    {
        if (! WH_Price_Rule_DB::table_exists()) {
            return;
        }

        $rules = WH_Price_Rule_DB::get_all();

        foreach ($rules as $rule) {
            self::unschedule_rule($rule->id);
        }
    }

    /**
     * Reschedule a custom rule after execution (for single-event custom schedules).
     *
     * @param int $rule_id Rule ID.
     *
     * @return void
     */
    public static function reschedule_custom_rule(int $rule_id): void
    {
        $rule = WH_Price_Rule_DB::get($rule_id);

        if (! $rule || $rule->status !== 'active' || $rule->schedule_type !== 'custom') {
            return;
        }

        $schedule_hours = json_decode($rule->schedule_hours, true) ?: [];

        if (empty($schedule_hours)) {
            return;
        }

        $hook     = self::CRON_HOOK_PREFIX . $rule_id;
        $next_run = self::get_next_custom_run($schedule_hours);

        wp_schedule_single_event($next_run, $hook, [$rule_id]);
    }

    /**
     * Map schedule type to WP Cron recurrence.
     *
     * @param string $schedule_type Schedule type.
     *
     * @return string WP Cron recurrence string.
     */
    private static function get_wp_recurrence(string $schedule_type): string
    {
        $map = [
            'hourly'           => 'hourly',
            'every_two_hours'  => 'every_two_hours',
            'every_four_hours' => 'every_four_hours',
            'every_six_hours'  => 'every_six_hours',
            'twicedaily'       => 'twicedaily',
            'daily'            => 'daily',
            'weekly'           => 'weekly',
        ];

        return $map[$schedule_type] ?? 'daily';
    }

    /**
     * Get the next run time based on schedule type and hours.
     *
     * @param string $schedule_type Schedule type.
     * @param array  $hours         Specific hours (for twicedaily etc.).
     *
     * @return int Unix timestamp.
     */
    private static function get_next_run_time(string $schedule_type, array $hours = []): int
    {
        $now = current_time('timestamp');

        if (! empty($hours) && ! empty($hours[0])) {
            // Use the first specified hour
            $today_at_hour = strtotime('today ' . $hours[0], $now);

            if ($today_at_hour > $now) {
                return $today_at_hour;
            }

            // Already passed today, schedule for tomorrow
            return strtotime('tomorrow ' . $hours[0], $now);
        }

        // Default: 1 minute from now
        return $now + 60;
    }

    /**
     * Get the next run time for a custom schedule.
     *
     * @param array $hours Array of time strings (e.g., ["08:00", "20:00"]).
     *
     * @return int Unix timestamp.
     */
    private static function get_next_custom_run(array $hours): int
    {
        $now = current_time('timestamp');

        sort($hours);

        // Find the next occurrence today
        foreach ($hours as $hour) {
            $target = strtotime('today ' . $hour, $now);
            if ($target > $now) {
                return $target;
            }
        }

        // All times passed today — use the first time tomorrow
        return strtotime('tomorrow ' . $hours[0], $now);
    }

    /**
     * Get available schedule types.
     *
     * @return array
     */
    public static function get_schedule_types(): array
    {
        return [
            'hourly'           => __('Every Hour', 'wh-bulk-price-update-for-woocommerce'),
            'every_two_hours'  => __('Every 2 Hours', 'wh-bulk-price-update-for-woocommerce'),
            'every_four_hours' => __('Every 4 Hours', 'wh-bulk-price-update-for-woocommerce'),
            'every_six_hours'  => __('Every 6 Hours', 'wh-bulk-price-update-for-woocommerce'),
            'twicedaily'       => __('Twice Daily', 'wh-bulk-price-update-for-woocommerce'),
            'daily'            => __('Once Daily', 'wh-bulk-price-update-for-woocommerce'),
            'weekly'           => __('Once Weekly', 'wh-bulk-price-update-for-woocommerce'),
            'custom'           => __('Custom Times', 'wh-bulk-price-update-for-woocommerce'),
        ];
    }
}
