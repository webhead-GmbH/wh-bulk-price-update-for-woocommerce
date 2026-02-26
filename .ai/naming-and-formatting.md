# Naming and formatting rules

Apply these rules whenever you touch code in this plugin.

## PHP naming

| Item | Convention | Example |
|---|---|---|
| Class names | `WH_` prefix + StudlyCase with underscores | `WH_Bulk_Price_Update_Ajax` |
| Methods | snake_case | `save_price_rule()` |
| Helper functions | `webhead_bulk_price_update_` prefix | `webhead_bulk_price_update_load_template()` |
| Constants | `WEBHEAD_BULK_PRICE_UPDATE_` prefix + UPPER_SNAKE_CASE | `WEBHEAD_BULK_PRICE_UPDATE_DB_VERSION` |
| Option keys | `wh_bulk_price_update_` prefix | `wh_bulk_price_update_block_size` |
| Cron hooks | `wh_price_rule_execute_` prefix | `wh_price_rule_execute_12` |

## PHP file structure

Preferred order in files:

1. `<?php`
2. direct access guard (`defined('ABSPATH') || exit;`)
3. file docblock
4. class/function definitions

This codebase currently does not enforce strict types or namespaces; keep consistency unless a planned refactor changes this globally.

## Input/output safety patterns

- Request boundary:
  - use `check_ajax_referer(...)`
  - sanitize values using `sanitize_text_field`, `intval`, `floatval`, `wp_unslash`
- Capability checks:
  - required for mutating admin operations (`manage_options`)
- Render boundary:
  - use `esc_html`, `esc_attr`, `esc_url`, `wp_kses` in templates

## Data encoding conventions

- JSON columns (`conditions`, `actions`, `schedule_hours`) are stored as JSON strings.
- Existing pattern in DB layer:
  - decode incoming JSON
  - normalize to array fallback
  - re-encode with `wp_json_encode`

## Template and asset naming

- Template files use kebab-case:
  - `price-rule-form.php`
  - `products-table.php`
- JS and CSS in `assets/` use kebab-case or plain names:
  - `script.js`
  - `price-rules.js`
  - `style.css`
  - `price-rules.css`
- CSS classes are scoped with `wh-` prefix.

## i18n conventions

- Use text domain: `wh-bulk-price-update-for-woocommerce`.
- Use translation wrappers (`__`, `esc_html__`, `esc_attr__`, etc.) for user-facing strings.
- Keep translators comments where format placeholders are used.

## Generated and vendor-like files

Treat these as external artifacts unless the change explicitly targets a library upgrade:

- `assets/js/bootstrap.min.js`
- `assets/js/popper.min.js`
- `assets/css/bootstrap.min.css`
- `assets/css/all.min.css`
- `assets/webfonts/*`

## Consistency reminders

- Do not mix alternative naming styles in the same domain.
- Keep new hooks/options aligned with existing prefixes.
- Keep docblocks concise and focused on intent/contract.
- Preserve admin-ajax action naming shape: `webhead_bulk_price_update_{action}`.
