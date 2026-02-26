# PHP architecture and patterns

This guide defines how server-side code should be extended in this plugin.

## Bootstrap lifecycle

- Entry file: `wh-bulk-price-update.php`
- Constant setup: `webhead_bulk_price_update_setup_constants()`
- Plugin init:
  - `webhead_bulk_price_update_check_version()` on `plugins_loaded`
  - `webhead_bulk_price_update_init_plugin()` on `plugins_loaded`
- Activation:
  - creates custom tables
  - reschedules active rules
  - stores DB version option
- Deactivation:
  - unschedules all rule cron hooks

## Class responsibilities

- `WH_Bulk_Price_Update`
  - registers admin submenu page
  - registers styles/scripts + localized runtime data
  - prepares template data (taxonomies, attributes, settings, rules)
  - wires core ajax hooks
- `WH_Bulk_Price_Update_Ajax`
  - request boundary for all ajax endpoints
  - nonce checks, basic sanitization, response rendering/json
- `WH_Price_Rule_DB`
  - schema + CRUD for rules table
- `WH_Price_Rule_Log_DB`
  - schema + CRUD for logs table
- `WH_Price_Rule_Executor`
  - product query building
  - rule evaluation per product
  - application of adjusted prices + logging
- `WH_Price_Rule_Scheduler`
  - schedule type mapping
  - cron event registration and rescheduling

## Database schema

`{prefix}wh_price_rules`:
- `id`
- `name`
- `status` (`active`/`paused`)
- `schedule_type`
- `schedule_hours` (JSON text)
- `rule_type` (`margin_check`, `max_discount_pct`, `price_adjustment`)
- `conditions` (JSON text)
- `actions` (JSON text)
- `created_at`, `updated_at`, `last_run_at`

`{prefix}wh_price_rule_logs`:
- `id`
- `rule_id`
- `product_id`
- `product_name`
- `old_price`
- `new_price`
- `price_field`
- `reason`
- `executed_at`

## Rule engine behavior

`WH_Price_Rule_Executor::execute($rule_id)`:

1. Loads rule and decodes `conditions/actions`.
2. Iterates products in blocks (`wh_bulk_price_update_block_size`).
   - `build_query_args()` accepts both canonical and legacy condition keys.
   - Include/exclude are normalized before query execution:
     - excluded IDs are removed from included IDs.
     - if all included IDs are excluded, query is forced to return no rows.
3. Expands variable products to include child variations.
4. Evaluates one of three rule types:
   - `margin_check`
   - `max_discount_pct`
   - `price_adjustment`
   - `margin_check` reads COG using `wh_bulk_price_update_cog_meta_key` first (default `_cogs_total_value`) and then falls back to known compatibility keys (`_cog_cost`, `_wc_cog_cost`, `_alg_wc_cog_cost`).
5. Applies change to product meta and inserts log entry.
6. Updates `last_run_at`.
7. For `custom` schedules, schedules next single event.

Price application rules:
- Sale price updates keep `_price` in sync.
- Removing sale price resets `_price` to regular price.
- Regular price increase can remove sale price if now invalid.
- Product transients are cleared after each update.

## Scheduler behavior

- Hook prefix: `wh_price_rule_execute_`
- Per-rule hooks are dynamically registered:
  - `wh_price_rule_execute_{rule_id}`
- For `custom` schedule:
  - schedules single event at next explicit clock time
  - after run, schedules the next one
- For interval schedules:
  - schedules recurring WP-Cron event

## Ajax contract

All endpoints are `wp_ajax_*` only (admin-authenticated), no public `nopriv` endpoints.

Actions and nonces:

- `webhead_bulk_price_update_update_product_price` -> nonce `update-product-price`
- `webhead_bulk_price_update_save_settings` -> nonce `save-settings`
- `webhead_bulk_price_update_get_blog_posts` -> nonce `get-blog-posts`
- `webhead_bulk_price_update_get_plugins` -> nonce `get-plugins`
- `webhead_bulk_price_update_save_price_rule` -> nonce `save-price-rule`
- `webhead_bulk_price_update_delete_price_rule` -> nonce `delete-price-rule`
- `webhead_bulk_price_update_toggle_price_rule` -> nonce `toggle-price-rule`
- `webhead_bulk_price_update_run_price_rule` -> nonce `run-price-rule`
- `webhead_bulk_price_update_get_price_rule_logs` -> nonce `get-price-rule-logs`
- `webhead_bulk_price_update_preview_price_rule` -> nonce `preview-price-rule`
- `webhead_bulk_price_update_search_attributes` -> nonce `search-attributes`
- `webhead_bulk_price_update_search_products` -> nonce `search-products`

`webhead_bulk_price_update_search_attributes` request modes:
- `q` for free-text term search.
- `ids[]` for preloading known term IDs in edit/prefill flows.

`webhead_bulk_price_update_search_products` request mode:
- `ids[]` for preloading known product IDs in edit/prefill flows.

Capability checks:
- Rule management endpoints enforce `current_user_can('manage_options')`.
- `update_product_price`, `save_settings`, `get_blog_posts`, `get_plugins` currently rely on nonce/auth only.

## Extension points

Available hooks:

- `before_wh_bulk_price_update_run`
- `after_wh_bulk_price_update_run`
- `before_wh_bulk_price_update_product_price`
- `after_wh_bulk_price_update_product_price`
- `wh_bulk_price_update_product_price_query_args` (filter)
- `wh_bulk_price_update_product_price_processed_products` (filter)
- `wh_bulk_price_update_custom_expression_placeholders` (filter)
- `wh_bulk_price_update_custom_expression_placeholder_values` (filter)
- `before_wh_price_rule_execute`
- `after_wh_price_rule_execute`
- `wh_price_rule_query_args` (filter)
- `wh_price_rule_evaluate_custom` (filter)

## Shared helpers

`includes/wh-bulk-price-update-core-functions.php` provides:
- template resolver/loader
- price calculation helper
- custom expression placeholder and evaluator helpers
- language normalization helper
- remote blog/plugin feed fetch with 24h transients

## Known implementation caveats (preserve unless intentionally fixing)

- Ajax hooks are registered twice:
  - once in `WH_Bulk_Price_Update::add_hooks()`
  - once in `WH_Bulk_Price_Update_Ajax::init()`
- `weekly` schedule is exposed but no `weekly` interval is added in `cron_schedules`.
- `webhead_bulk_price_update_get_language_code()` uses array indexing that effectively falls back to `en`.
- `uninstall.php` references plugin constants that are not defined inside uninstall context.
- This package has no PHPUnit/integration test suite; rely on manual regression checks.
