<?php
# Prevent direct file access
defined('ABSPATH') || exit;

/**
 * @var array       $logs
 * @var object|null $rule
 * @var int         $log_count
 * @var int         $page
 * @var int         $per_page
 * @var int         $total_pages
 * @var array       $filters
 * @var array       $category_terms
 * @var array       $tag_terms
 * @var array       $brand_terms
 * @var array       $selected_attributes
 */

$rule_id = isset($rule->id) ? (int) $rule->id : 0;

$filters = is_array($filters ?? null) ? $filters : [];
$selected_categories = array_map('intval', $filters['categories'] ?? []);
$selected_tags = array_map('intval', $filters['tags'] ?? []);
$selected_brands = array_map('intval', $filters['brands'] ?? []);
$selected_attributes_ids = array_map('intval', $filters['attributes'] ?? []);

$page = max(1, (int) ($page ?? 1));
$per_page = max(1, (int) ($per_page ?? 20));
$total_pages = max(1, (int) ($total_pages ?? 1));
$log_count = max(0, (int) ($log_count ?? 0));

$from_index = $log_count > 0 ? (($page - 1) * $per_page) + 1 : 0;
$to_index = $log_count > 0 ? min($log_count, $page * $per_page) : 0;
?>

<div class="wh-logs-wrapper" data-rule-id="<?php echo esc_attr((string) $rule_id); ?>">
    <form id="wh-rule-logs-filter-form" class="wh-logs-filter-form mb-3" data-rule-id="<?php echo esc_attr((string) $rule_id); ?>">
        <input type="hidden" name="per_page" value="<?php echo esc_attr((string) $per_page); ?>">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1"><?php esc_html_e('Date From', 'wh-bulk-price-update-for-woocommerce'); ?></label>
                <input
                    type="date"
                    name="date_from"
                    class="form-control form-control-sm"
                    value="<?php echo esc_attr((string) ($filters['date_from'] ?? '')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php esc_html_e('Date To', 'wh-bulk-price-update-for-woocommerce'); ?></label>
                <input
                    type="date"
                    name="date_to"
                    class="form-control form-control-sm"
                    value="<?php echo esc_attr((string) ($filters['date_to'] ?? '')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php esc_html_e('Categories', 'wh-bulk-price-update-for-woocommerce'); ?></label>
                <select name="categories[]" class="form-select form-select-sm wh-logs-filter-select" multiple>
                    <?php foreach ($category_terms as $term): ?>
                        <option
                            value="<?php echo esc_attr((string) $term->term_id); ?>"
                            <?php selected(in_array((int) $term->term_id, $selected_categories, true), true); ?>>
                            <?php echo esc_html(sprintf('%s (%d)', $term->name, $term->count)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php esc_html_e('Tags', 'wh-bulk-price-update-for-woocommerce'); ?></label>
                <select name="tags[]" class="form-select form-select-sm wh-logs-filter-select" multiple>
                    <?php foreach ($tag_terms as $term): ?>
                        <option
                            value="<?php echo esc_attr((string) $term->term_id); ?>"
                            <?php selected(in_array((int) $term->term_id, $selected_tags, true), true); ?>>
                            <?php echo esc_html($term->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php esc_html_e('Brands', 'wh-bulk-price-update-for-woocommerce'); ?></label>
                <select name="brands[]" class="form-select form-select-sm wh-logs-filter-select" multiple>
                    <?php foreach ($brand_terms as $term): ?>
                        <option
                            value="<?php echo esc_attr((string) $term->term_id); ?>"
                            <?php selected(in_array((int) $term->term_id, $selected_brands, true), true); ?>>
                            <?php echo esc_html(sprintf('%s (%d)', $term->name, $term->count)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1"><?php esc_html_e('Attributes', 'wh-bulk-price-update-for-woocommerce'); ?></label>
                <select name="attributes[]" class="form-select form-select-sm wh-logs-attributes-select" multiple>
                    <?php foreach ($selected_attributes as $attribute_option): ?>
                        <?php
                        $attribute_id = isset($attribute_option['id']) ? (int) $attribute_option['id'] : 0;
                        $attribute_text = isset($attribute_option['text']) ? (string) $attribute_option['text'] : '';
                        ?>
                        <?php if ($attribute_id > 0 && $attribute_text !== ''): ?>
                            <option
                                value="<?php echo esc_attr((string) $attribute_id); ?>"
                                <?php selected(in_array($attribute_id, $selected_attributes_ids, true), true); ?>>
                                <?php echo esc_html($attribute_text); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-2 gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-sm btn-success">
                    <i class="fa-solid fa-filter me-1"></i>
                    <?php esc_html_e('Apply Filters', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary wh-reset-logs-filters">
                    <i class="fa-solid fa-rotate-left me-1"></i>
                    <?php esc_html_e('Reset', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger wh-clear-rule-logs"
                    data-rule-id="<?php echo esc_attr((string) $rule_id); ?>"
                    <?php disabled($log_count <= 0); ?>>
                    <i class="fa-solid fa-trash-can me-1"></i>
                    <?php esc_html_e('Clear Logs', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
            </div>

            <small class="text-muted">
                <?php if ($log_count > 0): ?>
                    <?php
                    printf(
                        /* translators: 1: start row, 2: end row, 3: total rows */
                        esc_html__('Showing %1$d-%2$d of %3$d entries', 'wh-bulk-price-update-for-woocommerce'),
                        $from_index,
                        $to_index,
                        $log_count
                    );
                    ?>
                <?php else: ?>
                    <?php esc_html_e('No logs found.', 'wh-bulk-price-update-for-woocommerce'); ?>
                <?php endif; ?>
            </small>
        </div>
    </form>

    <?php if (empty($logs)): ?>
        <div class="alert alert-info mb-0">
            <?php esc_html_e('No execution logs found for the selected filters.', 'wh-bulk-price-update-for-woocommerce'); ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered wh-preview-table wh-rule-logs-table">
                <thead class="table-light">
                    <tr>
                        <th><?php esc_html_e('Product', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('Categories', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('Modified Details', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('Reason', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                        <th><?php esc_html_e('Date', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <a class="wh-preview-product-link" href="<?php echo esc_url((string) ($log->edit_link ?? '')); ?>" target="_blank" rel="noopener">
                                    <?php
                                    /* translators: 1: Product ID, 2: Product name */
                                    echo esc_html(sprintf(__('(#%1$d) %2$s', 'wh-bulk-price-update-for-woocommerce'), (int) $log->product_id, (string) $log->product_name));
                                    ?>
                                </a>
                            </td>
                            <td><?php echo esc_html((string) ($log->categories ?? __('Uncategorized', 'wh-bulk-price-update-for-woocommerce'))); ?></td>
                            <td class="wh-preview-modified-details">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle wh-price-details-table m-0">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Price Type', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                                                <th><?php esc_html_e('Original Price', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                                                <th><?php esc_html_e('Modified Price', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code class="wh-preview-price-field"><?php echo esc_html((string) ($log->field_label ?? '')); ?></code></td>
                                                <td><code><?php echo wp_kses_post(wc_price((float) $log->old_price)); ?></code></td>
                                                <td>
                                                    <?php if (! empty($log->is_sale_removed)): ?>
                                                        <span class="text-muted"><?php esc_html_e('Removed', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-success"><?php echo wp_kses_post(wc_price((float) $log->new_price)); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                            <td class="text-muted small">
                                <span><?php echo esc_html((string) $log->reason); ?></span>
                            </td>
                            <td class="text-nowrap">
                                <small title="<?php echo esc_attr((string) $log->executed_at); ?>">
                                    <?php echo esc_html((string) ($log->display_date ?? '')); ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <?php
            $page_window = 2;
            $start_page = max(1, $page - $page_window);
            $end_page = min($total_pages, $page + $page_window);

            if (($end_page - $start_page) < ($page_window * 2)) {
                $start_page = max(1, $end_page - ($page_window * 2));
                $end_page = min($total_pages, $start_page + ($page_window * 2));
            }
            ?>
            <nav class="mt-3" aria-label="<?php esc_attr_e('Execution logs pagination', 'wh-bulk-price-update-for-woocommerce'); ?>">
                <ul class="pagination pagination-sm justify-content-end mb-0 wh-rule-logs-pagination" data-rule-id="<?php echo esc_attr((string) $rule_id); ?>">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link wh-rule-logs-page-link" href="#" data-page="<?php echo esc_attr((string) max(1, $page - 1)); ?>">
                            <?php esc_html_e('Previous', 'wh-bulk-price-update-for-woocommerce'); ?>
                        </a>
                    </li>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link wh-rule-logs-page-link" href="#" data-page="<?php echo esc_attr((string) $i); ?>">
                                <?php echo esc_html((string) $i); ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link wh-rule-logs-page-link" href="#" data-page="<?php echo esc_attr((string) min($total_pages, $page + 1)); ?>">
                            <?php esc_html_e('Next', 'wh-bulk-price-update-for-woocommerce'); ?>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
