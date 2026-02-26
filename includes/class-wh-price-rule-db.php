<?php
/**
 * Price Rule Database Handler.
 *
 * Manages the custom database table for scheduled price rules.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

class WH_Price_Rule_DB
{
    /** @var string Table name (without prefix) */
    const TABLE_NAME = 'wh_price_rules';

    /**
     * Get the full table name with prefix.
     *
     * @return string
     */
    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Check whether current WordPress supports identifier placeholders (%i).
     *
     * @return bool
     */
    protected static function has_identifier_placeholders(): bool
    {
        global $wpdb;

        return method_exists($wpdb, 'has_cap') && $wpdb->has_cap('identifier_placeholders');
    }

    /**
     * Sanitize table name for fallback queries when %i is not available.
     *
     * @param string $table_name Raw table name.
     *
     * @return string
     */
    protected static function sanitize_table_name(string $table_name): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);

        if (! is_string($sanitized) || $sanitized === '') {
            return self::TABLE_NAME;
        }

        return $sanitized;
    }

    /**
     * Normalize order-by column against a strict allow-list.
     *
     * @param string $order_by Requested order-by value.
     *
     * @return string
     */
    protected static function normalize_order_by(string $order_by): string
    {
        $allowed_columns = [
            'id',
            'name',
            'status',
            'schedule_type',
            'rule_type',
            'created_at',
            'updated_at',
            'last_run_at',
        ];

        $normalized = sanitize_key($order_by);

        if (! in_array($normalized, $allowed_columns, true)) {
            return 'created_at';
        }

        return $normalized;
    }

    /**
     * Normalize sort direction to ASC|DESC only.
     *
     * @param string $order Requested sort direction.
     *
     * @return string
     */
    protected static function normalize_order_direction(string $order): string
    {
        return strtoupper(sanitize_text_field($order)) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * Create the price rules table.
     *
     * @return void
     */
    public static function create_table(): void
    {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            schedule_type VARCHAR(50) NOT NULL DEFAULT 'daily',
            schedule_hours LONGTEXT DEFAULT NULL,
            rule_type VARCHAR(50) NOT NULL DEFAULT 'margin_check',
            conditions LONGTEXT DEFAULT NULL,
            actions LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_run_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY rule_type (rule_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop the price rules table.
     *
     * @return void
     */
    public static function drop_table(): void
    {
        global $wpdb;

        if (self::has_identifier_placeholders()) {
            $prepared_sql = $wpdb->prepare('DROP TABLE IF EXISTS %i', self::table_name());
        } else {
            $safe_table   = self::sanitize_table_name(self::table_name());
            $prepared_sql = sprintf('DROP TABLE IF EXISTS `%s`', $safe_table);
        }

        $wpdb->query($prepared_sql);
    }

    /**
     * Insert a new price rule.
     *
     * @param array $data Rule data.
     *
     * @return int|false Inserted row ID or false on failure.
     */
    public static function insert(array $data)
    {
        global $wpdb;

        $defaults = [
            'name'           => '',
            'status'         => 'active',
            'schedule_type'  => 'daily',
            'schedule_hours' => '[]',
            'rule_type'      => 'margin_check',
            'conditions'     => '{}',
            'actions'        => '{}',
            'created_at'     => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            self::table_name(),
            [
                'name'           => sanitize_text_field($data['name']),
                'status'         => sanitize_text_field($data['status']),
                'schedule_type'  => sanitize_text_field($data['schedule_type']),
                'schedule_hours' => wp_json_encode(json_decode($data['schedule_hours'], true) ?: []),
                'rule_type'      => sanitize_text_field($data['rule_type']),
                'conditions'     => wp_json_encode(json_decode($data['conditions'], true) ?: []),
                'actions'        => wp_json_encode(json_decode($data['actions'], true) ?: []),
                'created_at'     => $data['created_at'],
                'updated_at'     => $data['updated_at'],
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing price rule.
     *
     * @param int   $id   Rule ID.
     * @param array $data Data to update.
     *
     * @return bool Whether the update succeeded.
     */
    public static function update(int $id, array $data): bool
    {
        global $wpdb;

        $update_data   = [];
        $update_format = [];

        $allowed_fields = [
            'name'           => '%s',
            'status'         => '%s',
            'schedule_type'  => '%s',
            'schedule_hours' => '%s',
            'rule_type'      => '%s',
            'conditions'     => '%s',
            'actions'        => '%s',
            'last_run_at'    => '%s',
        ];

        foreach ($allowed_fields as $field => $format) {
            if (isset($data[$field])) {
                if (in_array($field, ['name', 'status', 'schedule_type', 'rule_type'], true)) {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                } elseif (in_array($field, ['schedule_hours', 'conditions', 'actions'], true)) {
                    $update_data[$field] = wp_json_encode(json_decode($data[$field], true) ?: []);
                } else {
                    $update_data[$field] = $data[$field];
                }
                $update_format[] = $format;
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $update_data['updated_at'] = current_time('mysql');
        $update_format[]           = '%s';

        $result = $wpdb->update(
            self::table_name(),
            $update_data,
            ['id' => $id],
            $update_format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete a price rule.
     *
     * @param int $id Rule ID.
     *
     * @return bool Whether the deletion succeeded.
     */
    public static function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            self::table_name(),
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get a single price rule by ID.
     *
     * @param int $id Rule ID.
     *
     * @return object|null Rule object or null.
     */
    public static function get(int $id): ?object
    {
        global $wpdb;

        if (self::has_identifier_placeholders()) {
            $prepared_sql = $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                self::table_name(),
                $id
            );
        } else {
            $safe_table   = self::sanitize_table_name(self::table_name());
            $sql          = sprintf('SELECT * FROM `%s` WHERE id = %%d', $safe_table);
            $prepared_sql = $wpdb->prepare($sql, $id);
        }

        return $wpdb->get_row($prepared_sql);
    }

    /**
     * Get all price rules.
     *
     * @param string $order_by Column to order by.
     * @param string $order    Order direction (ASC or DESC).
     *
     * @return array Array of rule objects.
     */
    public static function get_all(string $order_by = 'created_at', string $order = 'DESC'): array
    {
        global $wpdb;

        $safe_order_by = self::normalize_order_by($order_by);
        $safe_order    = self::normalize_order_direction($order);

        if (self::has_identifier_placeholders()) {
            $sql = sprintf('SELECT * FROM %%i ORDER BY %s %s', $safe_order_by, $safe_order);
            $prepared_sql = $wpdb->prepare($sql, self::table_name());

            return $wpdb->get_results($prepared_sql);
        }

        $safe_table = self::sanitize_table_name(self::table_name());
        $sql        = sprintf('SELECT * FROM `%s` ORDER BY %s %s', $safe_table, $safe_order_by, $safe_order);

        return $wpdb->get_results($sql);
    }

    /**
     * Get all active price rules.
     *
     * @return array Array of active rule objects.
     */
    public static function get_active(): array
    {
        global $wpdb;

        if (self::has_identifier_placeholders()) {
            $prepared_sql = $wpdb->prepare(
                'SELECT * FROM %i WHERE status = %s ORDER BY created_at ASC',
                self::table_name(),
                'active'
            );
        } else {
            $safe_table   = self::sanitize_table_name(self::table_name());
            $sql          = sprintf('SELECT * FROM `%s` WHERE status = %%s ORDER BY created_at ASC', $safe_table);
            $prepared_sql = $wpdb->prepare($sql, 'active');
        }

        return $wpdb->get_results($prepared_sql);
    }

    /**
     * Check if the table exists.
     *
     * @return bool
     */
    public static function table_exists(): bool
    {
        global $wpdb;

        $table = self::table_name();

        $prepared_sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
        return $wpdb->get_var($prepared_sql) === $table;
    }
}
