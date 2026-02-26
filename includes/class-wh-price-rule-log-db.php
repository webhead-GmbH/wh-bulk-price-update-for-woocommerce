<?php
/**
 * Price Rule Log Database Handler.
 *
 * Manages the custom database table for price rule execution logs.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

class WH_Price_Rule_Log_DB
{
    /** @var string Table name (without prefix) */
    const TABLE_NAME = 'wh_price_rule_logs';

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
     * Build placeholders list for IN(...) clauses.
     *
     * @param int $count Number of placeholders.
     *
     * @return string
     */
    protected static function in_placeholders(int $count): string
    {
        return implode(',', array_fill(0, max(1, $count), '%d'));
    }

    /**
     * Create the price rule logs table.
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
            rule_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL DEFAULT '',
            old_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            new_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            price_field VARCHAR(50) NOT NULL DEFAULT '_sale_price',
            reason TEXT DEFAULT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rule_id (rule_id),
            KEY product_id (product_id),
            KEY executed_at (executed_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Drop the price rule logs table.
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
     * Insert a new log entry.
     *
     * @param array $data Log data.
     *
     * @return int|false Inserted row ID or false on failure.
     */
    public static function insert(array $data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            self::table_name(),
            [
                'rule_id'      => absint($data['rule_id']),
                'product_id'   => absint($data['product_id']),
                'product_name' => sanitize_text_field($data['product_name'] ?? ''),
                'old_price'    => floatval($data['old_price'] ?? 0),
                'new_price'    => floatval($data['new_price'] ?? 0),
                'price_field'  => sanitize_text_field($data['price_field'] ?? '_sale_price'),
                'reason'       => sanitize_textarea_field($data['reason'] ?? ''),
                'executed_at'  => $data['executed_at'] ?? current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get logs for a specific rule.
     *
     * @param int $rule_id Rule ID.
     * @param int $limit   Maximum number of results.
     * @param int $offset  Offset for pagination.
     *
     * @return array Array of log objects.
     */
    public static function get_by_rule(int $rule_id, int $limit = 50, int $offset = 0): array
    {
        return self::get_by_rule_filtered($rule_id, [], $limit, $offset);
    }

    /**
     * Get logs for a specific rule with optional filters.
     *
     * Supported filters:
     * - date_from (Y-m-d)
     * - date_to (Y-m-d)
     * - categories (term IDs from product_cat)
     * - tags (term IDs from product_tag)
     * - brands (term IDs from product_brand)
     * - attributes (term IDs from any pa_* taxonomy)
     *
     * @param int   $rule_id Rule ID.
     * @param array $filters Optional filter payload.
     * @param int   $limit   Maximum number of results.
     * @param int   $offset  Offset for pagination.
     *
     * @return array Array of log objects.
     */
    public static function get_by_rule_filtered(int $rule_id, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $limit  = max(1, absint($limit));
        $offset = max(0, absint($offset));

        $params = [];
        $from_sql = self::build_rule_filter_sql($rule_id, $filters, $params);
        $sql = "SELECT l.* {$from_sql} ORDER BY l.executed_at DESC, l.id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $prepared_sql = $wpdb->prepare($sql, $params);

        return $wpdb->get_results(
            $prepared_sql
        );
    }

    /**
     * Get the count of logs for a specific rule.
     *
     * @param int $rule_id Rule ID.
     *
     * @return int Log count.
     */
    public static function count_by_rule(int $rule_id): int
    {
        return self::count_by_rule_filtered($rule_id, []);
    }

    /**
     * Get the count of logs for a specific rule with optional filters.
     *
     * @param int   $rule_id Rule ID.
     * @param array $filters Optional filter payload.
     *
     * @return int Log count.
     */
    public static function count_by_rule_filtered(int $rule_id, array $filters = []): int
    {
        global $wpdb;

        $params = [];
        $from_sql = self::build_rule_filter_sql($rule_id, $filters, $params);
        $sql = "SELECT COUNT(*) {$from_sql}";

        $prepared_sql = $wpdb->prepare($sql, $params);

        return (int) $wpdb->get_var(
            $prepared_sql
        );
    }

    /**
     * Build SQL fragment for filtered rule logs.
     *
     * @param int   $rule_id Rule ID.
     * @param array $filters Optional filter payload.
     * @param array $params  Output SQL parameters.
     *
     * @return string
     */
    protected static function build_rule_filter_sql(int $rule_id, array $filters, array &$params): string
    {
        global $wpdb;

        $supports_identifiers = self::has_identifier_placeholders();

        $table_name       = self::table_name();
        $posts_table      = $wpdb->posts;
        $term_rel_tbl     = $wpdb->term_relationships;
        $term_tax_tbl     = $wpdb->term_taxonomy;
        $safe_table_name  = self::sanitize_table_name($table_name);
        $safe_posts_table = self::sanitize_table_name($posts_table);
        $safe_term_rel    = self::sanitize_table_name($term_rel_tbl);
        $safe_term_tax    = self::sanitize_table_name($term_tax_tbl);

        if ($supports_identifiers) {
            $from_sql = 'FROM %i l LEFT JOIN %i p ON p.ID = l.product_id';
            $params = [$table_name, $posts_table, absint($rule_id)];
        } else {
            $from_sql = sprintf('FROM `%s` l LEFT JOIN `%s` p ON p.ID = l.product_id', $safe_table_name, $safe_posts_table);
            $params = [absint($rule_id)];
        }

        $where_parts = ['l.rule_id = %d'];
        $target_object_id_sql = 'IF(COALESCE(p.post_parent, 0) > 0, p.post_parent, l.product_id)';

        $date_from = sanitize_text_field((string) ($filters['date_from'] ?? ''));
        $date_to   = sanitize_text_field((string) ($filters['date_to'] ?? ''));

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where_parts[] = 'l.executed_at >= %s';
            $params[]      = $date_from . ' 00:00:00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where_parts[] = 'l.executed_at <= %s';
            $params[]      = $date_to . ' 23:59:59';
        }

        $taxonomy_filters = [
            'categories' => 'product_cat',
            'tags'       => 'product_tag',
            'brands'     => 'product_brand',
        ];

        foreach ($taxonomy_filters as $filter_key => $taxonomy) {
            $term_ids = self::normalize_filter_ids($filters[$filter_key] ?? []);
            if (empty($term_ids)) {
                continue;
            }

            $placeholders = self::in_placeholders(count($term_ids));

            if ($supports_identifiers) {
                $where_parts[] = "EXISTS (
                    SELECT 1
                    FROM %i tr
                    INNER JOIN %i tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    WHERE tr.object_id = {$target_object_id_sql}
                      AND tt.taxonomy = %s
                      AND tt.term_id IN ({$placeholders})
                )";
                $params[] = $term_rel_tbl;
                $params[] = $term_tax_tbl;
            } else {
                $where_parts[] = "EXISTS (
                    SELECT 1
                    FROM `{$safe_term_rel}` tr
                    INNER JOIN `{$safe_term_tax}` tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    WHERE tr.object_id = {$target_object_id_sql}
                      AND tt.taxonomy = %s
                      AND tt.term_id IN ({$placeholders})
                )";
            }

            $params[] = $taxonomy;
            foreach ($term_ids as $term_id) {
                $params[] = $term_id;
            }
        }

        $attribute_term_ids = self::normalize_filter_ids($filters['attributes'] ?? []);
        if (! empty($attribute_term_ids)) {
            $attr_placeholders = self::in_placeholders(count($attribute_term_ids));

            if ($supports_identifiers) {
                $where_parts[] = "EXISTS (
                    SELECT 1
                    FROM %i tr
                    INNER JOIN %i tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    WHERE tr.object_id = {$target_object_id_sql}
                      AND tt.taxonomy LIKE %s
                      AND tt.term_id IN ({$attr_placeholders})
                )";
                $params[] = $term_rel_tbl;
                $params[] = $term_tax_tbl;
            } else {
                $where_parts[] = "EXISTS (
                    SELECT 1
                    FROM `{$safe_term_rel}` tr
                    INNER JOIN `{$safe_term_tax}` tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                    WHERE tr.object_id = {$target_object_id_sql}
                      AND tt.taxonomy LIKE %s
                      AND tt.term_id IN ({$attr_placeholders})
                )";
            }

            $params[] = $wpdb->esc_like('pa_') . '%';
            foreach ($attribute_term_ids as $term_id) {
                $params[] = $term_id;
            }
        }

        return $from_sql . ' WHERE ' . implode(' AND ', $where_parts);
    }

    /**
     * Normalize and sanitize term ID filters.
     *
     * @param mixed $values Candidate ID list.
     *
     * @return int[]
     */
    protected static function normalize_filter_ids($values): array
    {
        $values = is_array($values) ? $values : [];
        $values = array_map('absint', $values);
        $values = array_filter($values, static function ($value) {
            return $value > 0;
        });

        return array_values(array_unique($values));
    }

    /**
     * Get recent logs across all rules.
     *
     * @param int $limit Maximum number of results.
     *
     * @return array Array of log objects.
     */
    public static function get_recent(int $limit = 50): array
    {
        global $wpdb;

        $table      = self::table_name();
        $rule_table = WH_Price_Rule_DB::table_name();
        $limit      = max(1, absint($limit));

        if (self::has_identifier_placeholders()) {
            $prepared_sql = $wpdb->prepare(
                'SELECT l.*, r.name AS rule_name
                 FROM %i l
                 LEFT JOIN %i r ON l.rule_id = r.id
                 ORDER BY l.executed_at DESC
                 LIMIT %d',
                $table,
                $rule_table,
                $limit
            );
        } else {
            $safe_table      = self::sanitize_table_name($table);
            $safe_rule_table = self::sanitize_table_name($rule_table);
            $sql             = sprintf(
                'SELECT l.*, r.name AS rule_name
                 FROM `%s` l
                 LEFT JOIN `%s` r ON l.rule_id = r.id
                 ORDER BY l.executed_at DESC
                 LIMIT %%d',
                $safe_table,
                $safe_rule_table
            );
            $prepared_sql = $wpdb->prepare($sql, $limit);
        }

        return $wpdb->get_results($prepared_sql);
    }

    /**
     * Delete all logs for a specific rule.
     *
     * @param int $rule_id Rule ID.
     *
     * @return bool Whether the deletion succeeded.
     */
    public static function delete_by_rule(int $rule_id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            self::table_name(),
            ['rule_id' => $rule_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete logs older than a specified number of days.
     *
     * @param int $days Number of days to keep.
     *
     * @return int Number of rows deleted.
     */
    public static function cleanup_old(int $days = 30): int
    {
        global $wpdb;

        $table = self::table_name();
        $days  = max(1, absint($days));

        if (self::has_identifier_placeholders()) {
            $prepared_sql = $wpdb->prepare(
                'DELETE FROM %i WHERE executed_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
                $table,
                $days
            );
        } else {
            $safe_table   = self::sanitize_table_name($table);
            $sql          = sprintf('DELETE FROM `%s` WHERE executed_at < DATE_SUB(NOW(), INTERVAL %%d DAY)', $safe_table);
            $prepared_sql = $wpdb->prepare($sql, $days);
        }

        return (int) $wpdb->query($prepared_sql);
    }
}
