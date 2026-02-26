# Bulk Price Update for WooCommerce - Feature Baseline

This file defines the current functional behavior of the plugin.  
Use it as the regression baseline before/after any change.

## 1) Scope

The plugin provides four admin-facing feature groups:

1. Manual bulk price updates for WooCommerce products.
2. Scheduled rule-based automatic price adjustments.
3. Runtime/performance settings for bulk operations.
4. About tab content loaded from external webhead endpoints.

Admin menu location: `Products -> Bulk Price Update`.

Main dashboard tabs (`Update prices`, `Scheduled Rules`, `Settings`, `About us`) are URL-synced via `tab={pane_id}` and the same parameter is honored on page load.

## 2) Preconditions and boot behavior

1. WooCommerce dependency:
   - If `woocommerce/woocommerce.php` is inactive, plugin runtime is not initialized.
   - Admin notice is shown requesting WooCommerce.
2. On plugin activation:
   - Creates price rule tables.
   - Reschedules active rules.
   - Stores DB schema version option.
3. On deactivation:
   - Unschedules all rule events.
4. On uninstall:
   - Deletes plugin options/transients.
   - Drops plugin tables.
   - Attempts to clear cron hooks with `wh_price_rule_execute*` prefix.

## 3) Manual Bulk Price Update

### 3.1 Inputs

Manual update supports:

1. Price target:
   - `both`
   - `regular_price`
   - `sale_price`
2. Action type:
   - `increase`
   - `decrease`
   - `multiply`
   - `divide`
   - `fixed` (direct set)
   - `custom` (formula-based)
3. Change type:
   - `percentage`
   - `fixed`
4. Value:
   - `price_value`:
     - numeric value (required, > 0) for non-custom actions
     - expression string for `action_type = custom`
       - supports placeholders: `{regular_price}`, `{sale_price}`, `{cost}`
       - examples: `{regular_price} * 0.8`, `{regular_price} - 10`, `{cost} + {regular_price} - 10`
5. Targeting:
   - all products
   - filtered products
6. Filters:
   - include products
   - exclude products (optional toggle)
   - categories
   - tags
   - product attributes

### 3.2 Processing model

1. Runs in batches with option-driven sizes:
   - `wh_bulk_price_update_block_size` (default `1024`)
   - `wh_bulk_price_update_preview_block_size` (default `20`)
2. Uses `WP_Query` with product IDs and incremental `offset`.
3. For variable products:
   - parent ID is included
   - all variation child IDs are also processed
4. Attribute filtering behavior:
   - query-level filtering applies taxonomy slugs
   - variation-level check validates selected attribute values on each variation
5. Optional runtime limit:
   - `wh_bulk_price_update_time_limit` (default `-1`)
   - when not `-1`, `set_time_limit()` is called per loop iteration.

### 3.3 Preview mode

1. No product meta is changed.
2. Returns rendered table (`templates/products-table.php`) with:
   - product name/link
   - categories/tags
   - original prices
   - calculated changed prices
3. Caption indicates preview max count.

### 3.4 Apply mode

1. Updates selected price fields in post meta:
   - `_price`
   - `_regular_price`
   - `_sale_price`
2. Sale price is set to empty string when computed value is non-positive.
3. Returns rendered result table plus success caption with updated count.

## 4) Price Calculation Semantics

Shared helper: `webhead_bulk_price_update_calculate_modified_price()`.

Rules:

1. `action_type = fixed`:
   - numeric input: result = `price_value`
2. `change_type = fixed`:
   - increase: `current + value`
   - decrease: `current - value`
   - multiply: `current * value`
   - divide: `current / value` (only if value > 0)
3. `change_type = percentage`:
   - increase: `current + current*value/100`
   - decrease: `current - current*value/100`
   - multiply: `current * (value/100)` (if value > 0)
   - divide: `current / (value/100)` (if value > 0)
4. Invalid/empty conditions currently fall back to `0`.
5. `action_type = custom`:
   - expression is validated with safe tokenization and math parsing.
   - placeholders are resolved per-product, then expression is evaluated.
   - if expression is invalid (unknown placeholder or invalid syntax), request is rejected.

### 4.1 Custom expression placeholder extension hooks

1. `wh_bulk_price_update_custom_expression_placeholders`
   - Extends placeholder metadata used in UI/help and validation.
2. `wh_bulk_price_update_custom_expression_placeholder_values`
   - Provides per-product runtime numeric values for placeholders during evaluation.

## 5) Scheduled Rules

### 5.1 Rule model

Rule record includes:

1. `name`
2. `status` (`active` or `paused`)
3. `schedule_type`
4. `schedule_hours` (JSON array of `HH:MM`)
5. `rule_type`
6. `conditions` (JSON object)
7. `actions` (JSON object)
8. timestamps (`created_at`, `updated_at`, `last_run_at`)

Current scheduled rule filter keys in `conditions`:

1. `categories`
2. `tags`
3. `rule_brands`
4. `rule_attributes`
5. `include_products`
6. `exclude_products`

Rule executor also accepts legacy aliases for backward compatibility.

### 5.2 Rule management operations

Admin can:

1. create rule
2. edit rule
3. toggle active/paused
4. run now
5. open execution logs
6. delete rule (also deletes associated logs)

All rule-management ajax endpoints require:

1. valid nonce
2. `manage_options` capability

### 5.3 Rule form wizard behavior

Create and edit use the same modal wizard behavior:

1. 4 steps:
   - Basic Information
   - Schedule
   - Product Filters
   - Rule Settings
2. Forward navigation is blocked if current step is invalid.
3. `Save Rule` stays disabled until all required steps/fields are valid.
4. `Preview prices` is shown only on the final step.
5. Preview result panel is only visible in final step context.
6. Attribute/product Select2 values are rehydrated in edit mode.
7. Preview results include per-row `Add to Exclude` action to push products into `Exclude Products` without leaving step 4.
8. Preview table columns are optimized for operator review:
   - `Product` links to the WordPress product edit screen (variation rows point to parent product edit when needed)
   - `Categories` shows resolved product categories (with variation fallback to parent terms)
   - `Modified Details` embeds a compact detail table (`Price Type`, `Original Price`, `Modified Price`) similar to `Update prices` preview
   - `Reason` uses concise operator-facing summaries and includes applied margin in `margin_check`
   - if a sale price would be removed, reason explicitly states removal.
   - when a regular-price increase makes an existing sale invalid, preview also shows an extra `Sale Price -> Removed` row in `Modified Details`.

### 5.4 Rule types and behavior

#### A) `margin_check`

Goal: keep prices at or above minimum margin over COG.

Behavior:

1. COG meta lookup priority:
   - configured setting `wh_bulk_price_update_cog_meta_key` (default `_cogs_total_value`)
   - `_cog_cost`
   - `_wc_cog_cost`
   - `_alg_wc_cog_cost`
2. If no COG or COG <= 0: skip product.
3. Margin tiers source:
   - `conditions.margin_tiers`
   - fallback default tiers:
     - `0..200 => 60%`
     - `201..1500 => 50%`
     - `1501+ => 45%`
4. If store prices include tax:
   - regular price is converted to ex-tax for margin math
   - resulting min price is converted back to stored format
5. Minimum required price:
   - `min_net = COG / (1 - margin_pct/100)`
   - margin clamped to `< 100` (99.99 cap)
   - rounded by WooCommerce price decimals
6. Adjustment order:
   - first check regular price; if below min, adjust regular
   - otherwise check sale price
7. Sale price handling:
   - if below min, raise it
   - if minimum reaches/exceeds regular, remove sale price

#### B) `max_discount_pct`

Goal: cap discount amount.

Behavior:

1. Requires valid regular + sale price where sale < regular.
2. Computes current discount:
   - `(regular - sale) / regular * 100`
3. If current discount > configured threshold:
   - sale price adjusted upward to exact max discount boundary.

#### C) `price_adjustment`

Goal: periodic increase/decrease/multiply/divide/direct-set updates.

Behavior:

1. Uses action fields:
   - `price_type`
   - `action_type`
   - `change_type`
   - `price_value`
2. Reuses same calculation helper as manual bulk update.
3. Skips when:
   - source price not set/<=0
   - computed value effectively unchanged
4. Prevents negative final price by clamping to `0`.

## 6) Rule Execution and Logging

### 6.1 Execution loop

1. Scheduler triggers `WH_Price_Rule_Executor::execute(rule_id)`.
2. Querying is block-based with same options:
   - block size
   - time limit
3. Variable products expand to include children.
4. Each changed product writes a log row.
5. `last_run_at` is updated after execution.

### 6.2 Price write-back rules

1. If `_sale_price` updated:
   - `_price` synced to sale price
   - if sale removed/invalid, `_price` reverts to regular price
2. If `_regular_price` updated:
   - `_price` updated
   - conflicting lower sale price may be removed
3. WooCommerce transients are cleared per updated product.

### 6.3 Logs

Log table captures:

1. rule ID
2. product ID and name
3. old/new values
4. affected field
5. textual reason
6. execution datetime

UI currently loads and renders up to 100 most recent logs per rule.

### 6.4 Filter and query semantics (scheduled rules)

1. `categories`, `tags`, `rule_brands`, `rule_attributes` are combined as taxonomy filters with `AND`.
2. `rule_attributes` receives attribute term IDs and maps them to their real attribute taxonomies.
3. If both include and exclude lists are set:
   - excluded IDs are removed from included IDs before query.
   - if include becomes empty after normalization, the query returns no rows.
4. This normalization avoids ambiguous behavior when `post__in` and `post__not_in` are both provided.

## 7) Scheduling Semantics

Supported schedule labels:

1. hourly
2. every 2 hours
3. every 4 hours
4. every 6 hours
5. twice daily
6. once daily
7. once weekly
8. custom times

Behavior:

1. Non-custom schedules use recurring WP-Cron events.
2. Custom schedules use single events and are requeued after each run.
3. Existing rule schedule is cleared before re-scheduling.
4. Schedule start time for interval-based types uses the first configured time.

## 8) Settings Feature

Current settings saved via ajax:

1. block size
2. preview count
3. time limit
4. cost of goods custom field key (`wh_bulk_price_update_cog_meta_key`, default `_cogs_total_value`)

Settings are stored as standalone options (not one serialized settings array).

## 9) About Tab External Data

### 9.1 Latest posts

1. Source:
   - `https://webhead.at/{lang}/wp-json/wp/v2/posts?...`
2. Language normalized from HTML lang.
3. Cached in transient for 24h per language key.

### 9.2 Other plugins

1. Source:
   - `https://plugins.webhead.at/apps/v1/plugins.json`
2. Cached in transient for 24h.

Both requests are lazy-triggered when About tab is opened.

## 10) Hooks and extensibility surface

Actions:

1. `before_wh_bulk_price_update_run`
2. `after_wh_bulk_price_update_run`
3. `before_wh_bulk_price_update_product_price`
4. `after_wh_bulk_price_update_product_price`
5. `before_wh_price_rule_execute`
6. `after_wh_price_rule_execute`

Filters:

1. `wh_bulk_price_update_product_price_query_args`
2. `wh_bulk_price_update_product_price_processed_products`
3. `wh_price_rule_query_args`
4. `wh_price_rule_evaluate_custom`

## 11) Known current caveats (baseline realities)

These are current behaviors and should be considered during refactors:

1. Ajax actions are currently registered in two places.
2. `weekly` is listed but corresponding custom cron interval is not explicitly registered.
3. Language helper fallback implementation currently always resolves to `'en'` due to indexing style.
4. Uninstall file references constants that are not guaranteed in uninstall context.
5. No automated test suite is present in this package snapshot.

## 12) Regression checklist

Before and after a change, verify:

1. Manual preview works and shows expected calculations.
2. Manual apply updates intended products only.
3. Include/exclude and taxonomy filters behave correctly.
4. Variable product children are handled correctly.
5. Settings save and persist after reload.
6. Scheduled rule wizard validation blocks invalid step transitions.
7. `Preview prices` appears only in final step and preview panel visibility is scoped to that step.
8. Edit mode correctly pre-fills categories/tags/brands/attributes/include/exclude.
9. Include+exclude combination excludes overlapping IDs deterministically.
10. Each rule type can be created, edited, previewed, and executed.
11. Status toggle affects scheduling.
12. Logs are written and displayed.
13. Rule delete removes row and logs.
14. About tab loads remote cards without errors.
15. Changing main dashboard tabs updates `tab` query arg, and opening the same URL restores the matching default tab.
16. In rule preview, `Add to Exclude` appends selected product to `Exclude Products` with readable product label (not raw ID).
17. In rule preview, `Product` links and `Categories`/`Modified Details`/`Reason` cells render readable operational context for quick review.
