<?php
/**
 * Price Rule Executor.
 *
 * Processes price rules by evaluating conditions and applying price adjustments.
 *
 * @author    Mir Mohammad Hosseini <mh@webhead.dev>
 * @copyright 2026 webhead GmbH
 */

# Prevent direct file access
defined('ABSPATH') || exit;

class WH_Price_Rule_Executor
{
    /** @var string Default WooCommerce Cost of Goods meta key. */
    const DEFAULT_COG_META_KEY = '_cogs_total_value';

    /** @var array Fallback COG meta keys to check (in order of priority). */
    const COG_META_KEYS = ['_cogs_total_value', '_cog_cost', '_wc_cog_cost', '_alg_wc_cog_cost'];

    /**
     * Execute a price rule.
     *
     * @param int $rule_id Rule ID.
     *
     * @return array Execution results summary.
     */
    public static function execute(int $rule_id): array
    {
        $rule = WH_Price_Rule_DB::get($rule_id);

        if (! $rule) {
            return ['success' => false, 'message' => 'Rule not found.'];
        }

        $conditions = json_decode($rule->conditions, true) ?: [];
        $actions    = json_decode($rule->actions, true) ?: [];
        $results    = [
            'success'       => true,
            'rule_id'       => $rule_id,
            'rule_name'     => $rule->name,
            'processed'     => 0,
            'adjusted'      => 0,
            'skipped'       => 0,
            'errors'        => 0,
        ];

        // Get block size from settings
        $block_size = get_option('wh_bulk_price_update_block_size', 1024);
        $time_limit = get_option('wh_bulk_price_update_time_limit', -1);
        $offset     = 0;

        do_action('before_wh_price_rule_execute', $rule_id, $rule);

        while (true) {
            if (-1 != $time_limit) {
                set_time_limit($time_limit);
            }

            // Build query args
            $args = self::build_query_args($conditions, $block_size, $offset);
            $args = apply_filters('wh_price_rule_query_args', $args, $rule_id);

            $loop = new WP_Query($args);

            if (! $loop->have_posts()) {
                break;
            }

            foreach ($loop->posts as $product_id) {
                $product_ids = [$product_id];
                $product     = wc_get_product($product_id);

                if (! $product) {
                    $results['errors']++;
                    continue;
                }

                if ($product->is_type('variable')) {
                    $product_ids = array_merge($product_ids, $product->get_children());
                }

                foreach ($product_ids as $_product_id) {
                    $_product = wc_get_product($_product_id);

                    if (! $_product) {
                        $results['errors']++;
                        continue;
                    }

                    $results['processed']++;

                    // Execute based on rule type
                    $adjustment = self::evaluate_rule($rule->rule_type, $_product, $conditions, $actions);

                    if ($adjustment === null) {
                        $results['skipped']++;
                        continue;
                    }

                    // Apply the price change
                    $applied = self::apply_adjustment($_product, $adjustment, $rule_id);

                    if ($applied) {
                        $results['adjusted']++;
                    } else {
                        $results['skipped']++;
                    }
                }
            }

            wp_reset_postdata();

            $offset += $block_size;
        }

        // Update last run timestamp
        WH_Price_Rule_DB::update($rule_id, ['last_run_at' => current_time('mysql')]);

        // For custom schedules, reschedule the next single event
        if ($rule->schedule_type === 'custom' && $rule->status === 'active') {
            WH_Price_Rule_Scheduler::reschedule_custom_rule($rule_id);
        }

        do_action('after_wh_price_rule_execute', $rule_id, $results);

        return $results;
    }

    /**
     * Build WP_Query arguments from conditions.
     *
     * @param array $conditions Rule conditions.
     * @param int   $block_size Number of products per batch.
     * @param int   $offset     Query offset.
     *
     * @return array WP_Query args.
     */
    public static function build_query_args(array $conditions, int $block_size, int $offset): array
    {
        $include_products = $conditions['include_products'] ?? $conditions['rule_include_products'] ?? [];
        $exclude_products = $conditions['exclude_products'] ?? $conditions['rule_exclude_products'] ?? [];
        $categories       = $conditions['categories'] ?? $conditions['rule_categories'] ?? [];
        $tags             = $conditions['tags'] ?? $conditions['rule_tags'] ?? [];
        $rule_brands      = $conditions['rule_brands'] ?? $conditions['brands'] ?? [];
        $rule_attributes  = $conditions['rule_attributes'] ?? $conditions['attributes'] ?? [];

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $block_size,
            'offset'         => $offset,
            'fields'         => 'ids',
        ];

        // Specific products included/excluded.
        // WordPress may ignore post__not_in when post__in is present, so we normalize first.
        $include_ids = array_values(array_filter(array_map('intval', (array) $include_products)));
        $exclude_ids = array_values(array_filter(array_map('intval', (array) $exclude_products)));

        if (!empty($include_ids)) {
            $filtered_include_ids = array_values(array_diff($include_ids, $exclude_ids));

            // Force no results if all included products are excluded.
            $args['post__in'] = !empty($filtered_include_ids) ? $filtered_include_ids : [0];
        } elseif (!empty($exclude_ids)) {
            $args['post__not_in'] = $exclude_ids;
        }

        // Taxonomies (Categories, Tags, Brands, Attributes)
        $tax_query = [];

        if (!empty($categories)) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $categories),
                'operator' => 'IN',
            ];
        }

        if (!empty($tags)) {
            $tax_query[] = [
                'taxonomy' => 'product_tag',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $tags),
                'operator' => 'IN',
            ];
        }

        if (!empty($rule_brands)) {
            $tax_query[] = [
                'taxonomy' => 'product_brand',
                'field'    => 'term_id',
                'terms'    => array_map('intval', $rule_brands),
                'operator' => 'IN',
            ];
        }

        if (!empty($rule_attributes)) {
            // Frontend sends an array of term IDs
            $attribute_terms = array_map('intval', $rule_attributes);
            foreach ($attribute_terms as $term_id) {
                $term = get_term($term_id);
                if ($term && !is_wp_error($term)) {
                    $tax_query[] = [
                        'taxonomy' => $term->taxonomy,
                        'field'    => 'term_id',
                        'terms'    => [$term_id],
                        'operator' => 'IN',
                    ];
                }
            }
        }

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        return $args;
    }

    /**
     * Evaluate a rule for a specific product.
     *
     * @param string     $rule_type  Rule type.
     * @param WC_Product $product    Product object.
     * @param array      $conditions Rule conditions.
     * @param array      $actions    Rule actions.
     *
     * @return array|null Adjustment data or null if no adjustment needed.
     */
    public static function evaluate_rule(string $rule_type, WC_Product $product, array $conditions, array $actions): ?array
    {
        switch ($rule_type) {
            case 'margin_check':
                return self::evaluate_margin_check($product, $conditions, $actions);

            case 'max_discount_pct':
                return self::evaluate_max_discount($product, $conditions, $actions);

            case 'price_adjustment':
                return self::evaluate_price_adjustment($product, $conditions, $actions);

            default:
                return apply_filters('wh_price_rule_evaluate_custom', null, $rule_type, $product, $conditions, $actions);
        }
    }

    public static function evaluate_margin_check(WC_Product $product, array $conditions, array $actions): ?array
    {
        $regular_price = (float) $product->get_regular_price();
        $sale_price    = (float) $product->get_sale_price();
        $has_sale      = !empty($sale_price) && $sale_price > 0 && $sale_price < $regular_price;

        // No prices set at all
        if (empty($regular_price) && empty($sale_price)) {
            return null;
        }

        // Handle taxes if prices include tax in WooCommerce settings
        $prices_include_tax = wc_prices_include_tax();
        if ($prices_include_tax) {
            $regular_price_ex_tax = (float) wc_get_price_excluding_tax($product, ['price' => $regular_price]);
            // Calculate the tax multiplier so we can convert the required net price back to a gross price
            $tax_multiplier = $regular_price_ex_tax > 0 ? ($regular_price / $regular_price_ex_tax) : 1;
        } else {
            $tax_multiplier = 1;
        }

        // Get COG (COG is inherently a net cost)
        $cog = self::get_product_cog($product);

        if ($cog === null || $cog <= 0) {
            return null; // No COG data, skip
        }

        // Get applicable margin percentage
        $margin_tiers = $conditions['margin_tiers'] ?? self::get_default_margin_tiers();
        $margin_pct   = self::get_margin_for_cog($cog, $margin_tiers);

        // Protect against 100% margin division by zero
        if ($margin_pct >= 100) {
            $margin_pct = 99.99;
        }

        // Target margin formula: Margin = (Net Price - COG) / Net Price
        // Thus, Net Price = COG / (1 - Margin)
        $min_net_price = $cog / (1 - ($margin_pct / 100));

        // Convert minimum net price back to the format stored in DB (gross if taxes are included)
        $min_price = $min_net_price * $tax_multiplier;
        $min_price = round($min_price, wc_get_price_decimals());

        // 1. Check if Regular Price needs adjustment
        if ($regular_price > 0 && $regular_price < $min_price) {
            $sale_will_be_removed = $has_sale && $sale_price < $min_price;

            return [
                'price_field'          => '_regular_price',
                'old_price'            => $regular_price,
                'new_price'            => $min_price,
                'margin_pct'           => $margin_pct,
                'min_price'            => $min_price,
                'sale_will_be_removed' => $sale_will_be_removed,
                'old_sale_price'       => $sale_will_be_removed ? $sale_price : null,
                'reason'      => sprintf(
                    /* translators: 1: COG, 2: Margin %, 3: Min price, 4: Old regular price */
                    __('Regular price adjusted. COG: %1$s, Margin: %2$s%%, Min: %3$s, was: %4$s', 'wh-bulk-price-update-for-woocommerce'),
                    wc_price($cog),
                    $margin_pct,
                    wc_price($min_price),
                    wc_price($regular_price)
                ),
            ];
        }

        // 2. If Regular Price is fine, check Sale Price
        if ($has_sale && $sale_price < $min_price) {
            $new_sale_price = min($min_price, $regular_price);

            // If the minimum price equals or exceeds regular price, remove sale price
            if ($new_sale_price >= $regular_price) {
                return [
                    'price_field' => '_sale_price',
                    'old_price'   => $sale_price,
                    'new_price'   => '',
                    'margin_pct'  => $margin_pct,
                    'min_price'   => $min_price,
                    'reason'      => sprintf(
                        /* translators: 1: COG, 2: Margin %, 3: Min price, 4: Current sale price */
                        __('Sale price removed. COG: %1$s, Margin: %2$s%%, Min: %3$s, was: %4$s (exceeds regular price)', 'wh-bulk-price-update-for-woocommerce'),
                        wc_price($cog),
                        $margin_pct,
                        wc_price($min_price),
                        wc_price($sale_price)
                    ),
                ];
            }

            return [
                'price_field' => '_sale_price',
                'old_price'   => $sale_price,
                'new_price'   => $new_sale_price,
                'margin_pct'  => $margin_pct,
                'min_price'   => $min_price,
                'reason'      => sprintf(
                    /* translators: 1: COG, 2: Margin %, 3: Min price, 4: Old sale price, 5: New sale price */
                    __('Sale price adjusted. COG: %1$s, Margin: %2$s%%, Min: %3$s, was: %4$s', 'wh-bulk-price-update-for-woocommerce'),
                    wc_price($cog),
                    $margin_pct,
                    wc_price($min_price),
                    wc_price($sale_price)
                ),
            ];
        }

        return null;
    }

    /**
     * Evaluate max discount percentage rule.
     *
     * Ensures discount % does not exceed the threshold.
     *
     * @param WC_Product $product    Product object.
     * @param array      $conditions Conditions including max_discount_pct.
     * @param array      $actions    Actions configuration.
     *
     * @return array|null Adjustment data or null.
     */
    private static function evaluate_max_discount(WC_Product $product, array $conditions, array $actions): ?array
    {
        $sale_price    = (float) $product->get_sale_price();
        $regular_price = (float) $product->get_regular_price();

        // No sale price or no regular price — skip
        if (empty($sale_price) || $sale_price <= 0 || empty($regular_price) || $regular_price <= 0) {
            return null;
        }

        // If sale price >= regular price, no sale
        if ($sale_price >= $regular_price) {
            return null;
        }

        $max_discount = floatval($actions['max_discount_pct'] ?? 20);

        // Calculate current discount percentage
        $current_discount = (($regular_price - $sale_price) / $regular_price) * 100;

        if ($current_discount <= $max_discount) {
            return null; // Discount within limits
        }

        // Adjust sale price to exactly meet the max discount
        $new_sale_price = $regular_price * (1 - $max_discount / 100);
        $new_sale_price = round($new_sale_price, wc_get_price_decimals());

        return [
            'price_field' => '_sale_price',
            'old_price'   => $sale_price,
            'new_price'   => $new_sale_price,
            'reason'      => sprintf(
                /* translators: 1: Current discount %, 2: Max discount %, 3: Old sale price, 4: New sale price */
                __('Discount reduced from %1$s%% to %2$s%%. Sale price: %3$s → %4$s', 'wh-bulk-price-update-for-woocommerce'),
                round($current_discount, 1),
                $max_discount,
                wc_price($sale_price),
                wc_price($new_sale_price)
            ),
        ];
    }

    /**
     * Evaluate standard price adjustment rule.
     *
     * Reuses the existing price calculation logic.
     *
     * @param WC_Product $product    Product object.
     * @param array      $conditions Conditions.
     * @param array      $actions    Actions (action_type, change_type, price_value, price_type).
     *
     * @return array|null Adjustment data or null.
     */
    private static function evaluate_price_adjustment(WC_Product $product, array $conditions, array $actions): ?array
    {
        $action_type = sanitize_text_field($actions['action_type'] ?? 'increase');
        $change_type = sanitize_text_field($actions['change_type'] ?? 'percentage');
        $price_value = floatval($actions['price_value'] ?? 0);
        $price_type  = sanitize_text_field($actions['price_type'] ?? 'sale_price');

        if (empty($price_value)) {
            return null;
        }

        // Determine which price field to modify
        $price_field = '_' . $price_type;

        if ($price_type === 'regular_price') {
            $current_price = (float) $product->get_regular_price();
        } else {
            $current_price = (float) $product->get_sale_price();
        }

        if ($current_price <= 0) {
            return null;
        }

        $new_price = webhead_bulk_price_update_calculate_modified_price($current_price, $price_value, $action_type, $change_type);
        $new_price = round($new_price, wc_get_price_decimals());

        // Don't apply if no change
        if (abs($new_price - $current_price) < 0.01) {
            return null;
        }

        // Don't allow negative prices
        if ($new_price < 0) {
            $new_price = 0;
        }

        $sale_will_be_removed = false;
        $old_sale_price = null;

        if ($price_field === '_regular_price') {
            $current_sale_price = (float) $product->get_sale_price();
            if (!empty($current_sale_price) && $current_sale_price < $new_price) {
                $sale_will_be_removed = true;
                $old_sale_price = $current_sale_price;
            }
        }

        return [
            'price_field'          => $price_field,
            'old_price'            => $current_price,
            'new_price'            => $new_price,
            'sale_will_be_removed' => $sale_will_be_removed,
            'old_sale_price'       => $old_sale_price,
            'reason'      => sprintf(
                /* translators: 1: Action type, 2: Price value, 3: Change type, 4: Old price, 5: New price */
                __('Price %1$s by %2$s (%3$s). %4$s → %5$s', 'wh-bulk-price-update-for-woocommerce'),
                $action_type,
                $price_value,
                $change_type,
                wc_price($current_price),
                wc_price($new_price)
            ),
        ];
    }

    /**
     * Apply an adjustment to a product and log the change.
     *
     * @param WC_Product $product    Product object.
     * @param array      $adjustment Adjustment data.
     * @param int        $rule_id    Rule ID.
     *
     * @return bool Whether the adjustment was applied.
     */
    private static function apply_adjustment(WC_Product $product, array $adjustment, int $rule_id): bool
    {
        $product_id  = $product->get_id();
        $price_field = $adjustment['price_field'];
        $new_price   = $adjustment['new_price'];

        // Update the post meta
        update_post_meta($product_id, $price_field, $new_price);

        // If we updated sale price, also update the active price
        if ($price_field === '_sale_price') {
            if ($new_price === '' || $new_price <= 0) {
                // Sale removed: price should revert to regular price
                $regular_price = $product->get_regular_price();
                update_post_meta($product_id, '_price', $regular_price);
                delete_post_meta($product_id, '_sale_price');
            } else {
                update_post_meta($product_id, '_price', $new_price);
            }
        } elseif ($price_field === '_regular_price') {
            // If the regular price was pushed up, any existing sale price is now invalid (brings price below margin).
            // We ensure it gets removed.
            $sale_price = (float) $product->get_sale_price();
            if (!empty($sale_price)) {
                if ($sale_price < (float) $new_price) {
                    delete_post_meta($product_id, '_sale_price');
                    update_post_meta($product_id, '_price', $new_price);
                }
            } else {
                update_post_meta($product_id, '_price', $new_price);
            }
        }

        // Clear WooCommerce product cache
        wc_delete_product_transients($product_id);

        // Log the change
        WH_Price_Rule_Log_DB::insert([
            'rule_id'      => $rule_id,
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            'old_price'    => $adjustment['old_price'],
            'new_price'    => is_numeric($new_price) ? $new_price : 0,
            'price_field'  => $price_field,
            'reason'       => $adjustment['reason'],
        ]);

        return true;
    }

    /**
     * Get the Cost of Goods for a product.
     *
     * @param WC_Product $product Product object.
     *
     * @return float|null COG value or null if not set.
     */
    private static function get_product_cog(WC_Product $product): ?float
    {
        $product_id = $product->get_id();

        foreach (self::get_cog_meta_keys_for_lookup() as $meta_key) {
            $cog = get_post_meta($product_id, $meta_key, true);
            if ($cog !== '' && $cog !== false) {
                return floatval($cog);
            }
        }

        return null;
    }

    /**
     * Return default Cost of Goods custom field key.
     *
     * @return string
     */
    public static function get_default_cog_meta_key(): string
    {
        return self::DEFAULT_COG_META_KEY;
    }

    /**
     * Sanitize Cost of Goods custom field key.
     *
     * @param mixed $meta_key Raw meta key.
     *
     * @return string
     */
    public static function sanitize_cog_meta_key($meta_key): string
    {
        $meta_key = sanitize_text_field((string) $meta_key);
        $meta_key = trim($meta_key);
        $meta_key = preg_replace('/[^A-Za-z0-9_-]/', '', $meta_key);

        if (! is_string($meta_key)) {
            return '';
        }

        return trim($meta_key);
    }

    /**
     * Build Cost of Goods meta key lookup order.
     *
     * @return string[]
     */
    private static function get_cog_meta_keys_for_lookup(): array
    {
        $configured_meta_key = get_option('wh_bulk_price_update_cog_meta_key', self::get_default_cog_meta_key());
        $configured_meta_key = self::sanitize_cog_meta_key($configured_meta_key);

        $lookup_keys = [];

        if ($configured_meta_key !== '') {
            $lookup_keys[] = $configured_meta_key;
        }

        foreach (self::COG_META_KEYS as $meta_key) {
            if (! in_array($meta_key, $lookup_keys, true)) {
                $lookup_keys[] = $meta_key;
            }
        }

        return $lookup_keys;
    }

    /**
     * Get the applicable margin percentage for a given COG value.
     *
     * @param float $cog          Cost of Goods value.
     * @param array $margin_tiers Array of margin tier definitions.
     *
     * @return float Margin percentage.
     */
    private static function get_margin_for_cog(float $cog, array $margin_tiers): float
    {
        // Sort tiers by min_cog ascending
        usort($margin_tiers, function ($a, $b) {
            return floatval($a['min_cog']) - floatval($b['min_cog']);
        });

        $margin = 0;

        foreach ($margin_tiers as $tier) {
            $min = floatval($tier['min_cog'] ?? 0);
            $max = floatval($tier['max_cog'] ?? PHP_FLOAT_MAX);

            if ($cog >= $min && ($max <= 0 || $cog <= $max)) {
                $margin = floatval($tier['margin_pct'] ?? 0);
                break;
            }
        }

        return $margin;
    }

    /**
     * Get default margin tiers.
     *
     * @return array
     */
    public static function get_default_margin_tiers(): array
    {
        return [
            ['min_cog' => 0,    'max_cog' => 200,  'margin_pct' => 60],
            ['min_cog' => 201,  'max_cog' => 1500, 'margin_pct' => 50],
            ['min_cog' => 1501, 'max_cog' => 0,    'margin_pct' => 45],
        ];
    }

    /**
     * Get available rule types.
     *
     * @return array
     */
    public static function get_rule_types(): array
    {
        return [
            'margin_check'     => __('Margin Check', 'wh-bulk-price-update-for-woocommerce'),
            'max_discount_pct' => __('Maximum Discount Percentage', 'wh-bulk-price-update-for-woocommerce'),
            'price_adjustment' => __('Price Adjustment', 'wh-bulk-price-update-for-woocommerce'),
        ];
    }
}
