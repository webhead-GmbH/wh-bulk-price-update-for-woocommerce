# Bulk Price Update for WooCommerce - system overview

This document is the top-level map of the plugin. Read it before editing code.

## Architectural pillars

- WordPress plugin with procedural bootstrap plus class-based services.
- Admin-first product workflow:
  - manual bulk price update
  - scheduled rule-based price adjustments
- No dependency injection container, no namespaces, no build source in this package.
- Server rendering uses PHP templates in `templates/`.
- Admin interactions use jQuery + `admin-ajax.php` endpoints.

## Key entry points

| Component | Role |
|---|---|
| `wh-bulk-price-update.php` | Plugin bootstrap, constants, activation/deactivation hooks, WooCommerce dependency check. |
| `includes/class-wh-bulk-price-update.php` | Core orchestrator: menu page, assets, template data, ajax hook registration. |
| `includes/class-wh-bulk-price-update-ajax.php` | All ajax handlers for manual updates, settings, rules, logs, preview, and attribute search. |
| `includes/class-wh-price-rule-db.php` | CRUD for `wh_price_rules` table. |
| `includes/class-wh-price-rule-log-db.php` | CRUD for `wh_price_rule_logs` table. |
| `includes/class-wh-price-rule-executor.php` | Rule execution engine (margin, max discount, direct adjustment). |
| `includes/class-wh-price-rule-scheduler.php` | WP-Cron scheduling/unscheduling of per-rule hooks. |
| `includes/wh-bulk-price-update-core-functions.php` | Shared helpers: template loading, price calculation, remote content fetch. |
| `templates/price-change.php` | Main admin dashboard with tabs (Update prices, Scheduled rules, Settings, About us). |
| `assets/js/script.js` | Manual update/settings/about tab interactions. |
| `assets/js/price-rules.js` | Scheduled rules modal/list interactions. |

## Runtime flow

1. WordPress loads `wh-bulk-price-update.php`.
2. If WooCommerce is inactive, an admin notice is shown and runtime hooks are skipped.
3. On `plugins_loaded`:
   - DB version check and table creation/migration (`webhead_bulk_price_update_check_version`)
   - plugin init (`webhead_bulk_price_update_init_plugin`)
4. `WH_Bulk_Price_Update::init()` loads includes, registers hooks, and initializes scheduler.
5. Admin page is mounted at `Products -> Bulk Price Update`.
6. User actions call ajax endpoints and update product price meta / rule tables.
7. Scheduler triggers rule hooks (`wh_price_rule_execute_{rule_id}`), executor updates products, and logs changes.

## Feature map

- Manual bulk update:
  - action types: increase, decrease, multiply, divide, fixed/direct, custom formula
  - change types: percentage, fixed
  - price targets: regular, sale, both
  - custom formula supports placeholders like `{regular_price}`, `{sale_price}`, `{cost}`
  - filters: products, categories, tags, brands, attributes, include/exclude lists
  - preview mode before applying updates
- Scheduled rules:
  - rule types:
    - margin check (COG + margin tiers, using configurable COG meta key with safe fallbacks)
    - maximum discount percentage
    - direct price adjustment
  - modal workflow:
    - 4-step wizard (basic info, schedule, product filters, rule settings)
    - add and edit both use the same step flow
    - preview action is available only in the last step
    - preview table shows product edit links, categories, a compact `Modified Details` table, and concise reasons
    - sale-price removal is explicitly explained in preview reason text
    - preview rows provide an inline action to add products to `Exclude Products`
    - save action stays disabled until all steps are valid
  - schedules:
    - hourly / every 2h / every 4h / every 6h / twicedaily / daily / weekly / custom times
  - operations:
    - create/update
    - toggle active/paused
    - run now
    - view logs
    - delete
  - condition keys:
    - canonical keys: `categories`, `tags`, `rule_brands`, `rule_attributes`, `include_products`, `exclude_products`
    - executor accepts legacy aliases for backward compatibility
- Settings:
  - block size
  - preview count
  - time limit
  - cost of goods custom field key (default `_cogs_total_value`)
- About tab:
  - lazy-load latest blog posts from `webhead.at`
  - lazy-load plugin list from `plugins.webhead.at`

## Data and storage map

- Options:
  - `wh_bulk_price_update_block_size`
  - `wh_bulk_price_update_preview_block_size`
  - `wh_bulk_price_update_time_limit`
  - `wh_bulk_price_update_cog_meta_key`
  - `wh_bulk_price_update_db_version`
- Transients:
  - `wh_blog_posts_en`, `wh_blog_posts_de`
  - `wh_plugins`
- Custom tables:
  - `{prefix}wh_price_rules`
  - `{prefix}wh_price_rule_logs`
- Product meta touched by this plugin:
  - `_regular_price`
  - `_sale_price`
  - `_price`

## Folder snapshot

```text
wh-bulk-price-update-for-woocommerce/
|- .ai/                              <- AI contributor docs
|- wh-bulk-price-update.php          <- bootstrap
|- uninstall.php                     <- uninstall cleanup
|- includes/                         <- PHP runtime logic
|- templates/                        <- admin page templates
|- assets/js/                        <- jQuery behavior
|- assets/css/                       <- admin styles
|- assets/img/                       <- logo/banner assets
|- .wordpress-org/                   <- wp.org banners/screenshots
|- readme.txt                        <- wp.org readme/changelog
`- .github/workflows/deploy.yml      <- release deploy automation
```

## Read order

1. `.ai/overview.md`
2. `.ai/php-architecture.md`
3. `.ai/naming-and-formatting.md`
4. `.ai/frontend-guidelines.md` (if touching templates or JS/CSS)
5. `.ai/feature-development-checklist.md`
6. `readme.txt` and `README.md` for public behavior claims
