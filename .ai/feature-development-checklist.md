# Feature development checklist

Follow this sequence for every feature or refactor.

## 1. Preparation

- Read:
  - `.ai/overview.md`
  - `.ai/php-architecture.md`
  - `.ai/naming-and-formatting.md`
  - `.ai/frontend-guidelines.md` (if UI/js is involved)
  - `readme.txt` (public behavior promises)
- Identify impacted domains:
  - manual bulk update
  - scheduled rules engine
  - settings and runtime limits
  - about tab remote feeds

## 2. Data contract and persistence

- If settings change:
  - update defaults/reads in:
    - `includes/class-wh-bulk-price-update.php`
    - `includes/class-wh-bulk-price-update-ajax.php`
  - ensure uninstall cleanup is still correct.
- If rule payload shape changes:
  - update form serialization in `assets/js/price-rules.js`
  - update decode/usage in executor/scheduler classes
  - validate backward compatibility for existing JSON rows.
- If rule filter semantics change:
  - verify canonical + legacy keys remain compatible in `WH_Price_Rule_Executor::build_query_args()`
  - verify include/exclude normalization when both lists are populated
  - verify attribute prefill path still works via `search_attributes` with `ids[]`.
- If schema changes:
  - update table DDL in DB classes
  - bump `WEBHEAD_BULK_PRICE_UPDATE_DB_VERSION`
  - ensure activation/version-check path can migrate safely.

## 3. PHP changes

- Add or update hooks in `WH_Bulk_Price_Update::add_hooks()`.
- For new ajax endpoint:
  - add nonce in `admin_scripts()` localization
  - add JS request payload with that nonce
  - add handler in `WH_Bulk_Price_Update_Ajax`
  - enforce capability for mutating operations
  - return consistent response shape.
- For rule execution changes:
  - keep `WH_Price_Rule_Executor::execute()` deterministic
  - ensure `_price` sync logic remains valid for sale/regular updates
  - keep logging behavior in `WH_Price_Rule_Log_DB`.

## 4. Frontend changes

- Update relevant template(s) in `templates/`.
- Update behavior in:
  - `assets/js/script.js`
  - `assets/js/price-rules.js`
- Keep `wh_script_params` contract in sync with JS usage.
- Verify Select2/bootstrap modal interactions still work after changes.

## 5. Scheduler and cron integrity

- If schedule options are added:
  - update `get_schedule_types()`
  - update `get_wp_recurrence()`
  - update `add_cron_intervals()` if non-core interval is required.
- Verify:
  - activation reschedules active rules
  - deactivation unschedules all rules
  - custom schedules requeue after execution.

## 6. Performance and safety

- Preserve block processing (`block_size`, `preview_block_size`) to avoid timeouts.
- Avoid loading full objects when IDs are sufficient.
- Keep sanitization/escaping at request and render boundaries.
- Keep third-party requests cached via transients.

## 7. Manual verification

Run a complete smoke test:

1. Manual preview with all-products mode.
2. Manual apply with include/exclude filters.
3. Attribute-filtered update.
4. Save settings and reload page.
5. Scheduled rules add flow: verify step validation and button states.
6. Scheduled rules edit flow: verify step validation and data prefill.
7. Scheduled preview with mixed filters (categories/tags/brands/attributes/include/exclude).
8. Run rule now and check log output.
9. Toggle rule status and confirm scheduling behavior.
10. Delete rule and verify logs are removed.
11. Confirm About tab loads posts/plugins.

## 8. Final checks

- Confirm no unrelated files were changed.
- Confirm text domain consistency for new strings.
- Update `readme.txt` changelog when behavior changes.
- If needed, verify release packaging compatibility with `.distignore` and `.github/workflows/deploy.yml`.
