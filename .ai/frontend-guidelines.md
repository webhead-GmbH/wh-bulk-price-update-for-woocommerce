# Frontend guidelines

These rules apply to templates and admin scripts in this plugin.

## Stack and entry points

- Rendering:
  - PHP templates in `templates/`
- JS:
  - `assets/js/script.js` (manual update, settings, about tab)
  - `assets/js/price-rules.js` (scheduled rules UI and modal logic)
- CSS:
  - `assets/css/style.css`
  - `assets/css/price-rules.css`
- UI dependencies:
  - Bootstrap 5
  - jQuery
  - Select2 / wc-enhanced-select
  - accounting.js
  - Font Awesome

## Main template topology

`templates/price-change.php` hosts 4 tabs:

1. Update prices
2. Scheduled rules
3. Settings
4. About us

Scheduled rules tab includes:
- `templates/price-rules.php`
- `templates/price-rule-form.php`
- `templates/price-rule-logs.php` (loaded dynamically)

Preview/update results:
- `templates/products-table.php`

About tab ajax content:
- `templates/posts-loop.php`
- `templates/plugins-loop.php`

## Localized JS contract

Server injects `window.wh_script_params` from `WH_Bulk_Price_Update::admin_scripts()`.

Expected keys:
- `ajax_url`
- all nonces used by js actions
- currency formatting values:
  - `currency_format_num_decimals`
  - `currency_format_symbol`
  - `currency_format_decimal_sep`
  - `currency_format_thousand_sep`
  - `currency_format`
- UI strings:
  - `i18n_select_an_option`
  - `i18n_confirm_delete`
  - `i18n_running`
  - `i18n_saving`

When adding an ajax feature, update both:
- JS action request payload
- localized nonce in PHP

## UI behavior conventions

- Update prices panel:
  - `price_value` accepts numeric input.
  - `action_type = custom` enables formula mode for `price_value`.
  - formula mode supports placeholders such as `{regular_price}`, `{sale_price}`, `{cost}`.
  - placeholder chips are clickable and insert token text into the input.
  - formula tip/help section is visible only when custom mode is selected.
- Select2:
  - standard selects use `.wh-select2`
  - product searches use WooCommerce `wc-product-search`
  - rule attributes use ajax Select2 endpoint (`search_attributes`)
  - rule attributes edit/prefill path hydrates labels by selected `ids[]`
  - include/exclude edit prefill hydrates product labels by selected `ids[]` (avoid raw `#ID` tags)
- Rule form:
  - 4-step modal wizard for both add and edit
  - dynamic settings panels by rule type
  - margin tiers support drag-sort and overlap validation
  - schedule times switch behavior by schedule type
  - next-step navigation is blocked when current step is invalid
  - save button is enabled only when all steps pass validation
  - preview button and preview panel are shown only in step 4
  - preview rows expose `Add to Exclude` action to append products into `#wh-rule-exclude-products`
  - preview table should use readable operator-facing content:
    - product name links to edit screen
    - include categories column
    - render change data in a compact `Modified Details` table (`Price Type`, `Original Price`, `Modified Price`)
    - keep reason concise; include applied margin for margin rules and mention sale-price removal when applicable
    - if regular price change invalidates an existing sale, show an additional `Sale Price` row with `Removed`
- UX feedback:
  - Bootstrap toasts for success states
  - inline spinner/disabled state while requests are pending
  - modals for destructive confirmations
- Main dashboard tab state:
  - keep primary tab state synced in URL query (`tab={pane_id}`)
  - respect incoming `tab` query on initial load so refresh/deep links keep context

## Styling conventions

- Prefix classes with `wh-` for plugin scope.
- Keep Bootstrap utility usage; avoid rewriting layout primitives.
- Sidebar/header behavior is JS-assisted (`sticky` + width sync).
- Keep mobile behavior in mind (special rules at `max-width: 767px`).

## Editing boundaries

- This repository snapshot contains compiled assets directly.
- There is no `src/` JS source in this package; edit `assets/js/*.js` directly unless source files are added.
- Keep third-party files untouched unless explicitly upgrading:
  - `assets/js/bootstrap.min.js`, `assets/js/popper.min.js`
  - `assets/css/bootstrap.min.css`, `assets/css/all.min.css`
  - `assets/webfonts/*`

## Validation checklist after frontend changes

1. Open `Products -> Bulk Price Update` and ensure all 4 tabs render.
2. Verify Select2 controls initialize without console errors.
3. Run manual preview and confirm table output renders.
4. Execute a real price change and verify spinner + result caption.
5. Save settings and confirm toast appears.
6. Create/edit/run/delete a scheduled rule.
7. Open logs modal and confirm rows render.
8. Open About tab and verify posts/plugins are loaded via ajax.
9. Verify step validation blocks navigation when required fields are invalid.
10. Verify Preview is visible only on step 4 and hides when navigating to earlier steps.
11. Verify include+exclude filters keep excluded IDs out of effective selection.
12. Verify edit mode rehydrates attributes/include/exclude selections correctly.
13. Verify `Add to Exclude` in preview updates Exclude Products with product label and disables that row action.
