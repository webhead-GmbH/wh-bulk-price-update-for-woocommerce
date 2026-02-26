<?php
# Prevent direct file access
defined('ABSPATH') || exit;

/**
 * @var string $preview_state
 * @var array  $preview_results
 */

$preview_state = sanitize_key((string) ($preview_state ?? 'results'));
$preview_results = is_array($preview_results ?? null) ? $preview_results : [];
?>

<?php if ('no_products' === $preview_state): ?>
    <div class="alert alert-info">
        <?php esc_html_e('No products found matching these conditions.', 'wh-bulk-price-update-for-woocommerce'); ?>
    </div>
<?php elseif ('no_adjustments' === $preview_state): ?>
    <div class="alert alert-info">
        <?php esc_html_e('Products found, but none require price adjustment based on current settings (e.g. margins already optimal).', 'wh-bulk-price-update-for-woocommerce'); ?>
    </div>
<?php else: ?>
    <table class="table table-sm table-bordered wh-preview-table mt-3">
        <thead class="table-light">
            <tr>
                <th><?php esc_html_e('Product', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Categories', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Modified Details', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                <th><?php esc_html_e('Reason', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                <th class="wh-preview-actions-col"><?php esc_html_e('Actions', 'wh-bulk-price-update-for-woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($preview_results as $res): ?>
                <?php
                $product_id = isset($res['product_id']) ? (int) $res['product_id'] : 0;
                $product_name = (string) ($res['name'] ?? '');
                $edit_link = (string) ($res['edit_link'] ?? '');
                $categories = (string) ($res['categories'] ?? '');
                $field = (string) ($res['field'] ?? '');
                $field_label = (string) ($res['field_label'] ?? '');
                $old_price = isset($res['old_price']) ? (float) $res['old_price'] : 0.0;
                $new_price = isset($res['new_price']) ? (float) $res['new_price'] : 0.0;
                $is_sale_removed = ! empty($res['is_sale_removed']);
                $sale_will_be_removed = ! empty($res['sale_will_be_removed']);
                $old_sale_price = isset($res['old_sale_price']) ? (float) $res['old_sale_price'] : 0.0;
                $short_reason = (string) ($res['short_reason'] ?? '');
                $show_linked_sale_removal = $sale_will_be_removed && '_regular_price' === $field;
                ?>
                <tr>
                    <td>
                        <a class="wh-preview-product-link" href="<?php echo esc_url($edit_link); ?>" target="_blank" rel="noopener">
                            <?php
                            /* translators: 1: Product ID, 2: Product name */
                            echo esc_html(sprintf(__('(#%1$d) %2$s', 'wh-bulk-price-update-for-woocommerce'), $product_id, $product_name));
                            ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($categories); ?></td>
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
                                        <td><code class="wh-preview-price-field"><?php echo esc_html($field_label); ?></code></td>
                                        <td><code><?php echo wp_kses_post(wc_price($old_price)); ?></code></td>
                                        <td>
                                            <?php if ($is_sale_removed): ?>
                                                <span class="text-muted"><?php esc_html_e('Removed', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                                            <?php else: ?>
                                                <span class="text-success"><?php echo wp_kses_post(wc_price($new_price)); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($show_linked_sale_removal): ?>
                                        <tr>
                                            <td><code class="wh-preview-price-field"><?php esc_html_e('Sale Price', 'wh-bulk-price-update-for-woocommerce'); ?></code></td>
                                            <td><code><?php echo wp_kses_post(wc_price($old_sale_price)); ?></code></td>
                                            <td><span class="text-muted"><?php esc_html_e('Removed', 'wh-bulk-price-update-for-woocommerce'); ?></span></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                    <td class="text-muted small">
                        <span><?php echo esc_html($short_reason); ?></span>
                    </td>
                    <td class="text-nowrap text-center wh-preview-actions-cell">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger wh-preview-add-exclude-btn wh-preview-icon-btn"
                            data-product-id="<?php echo esc_attr((string) $product_id); ?>"
                            data-product-name="<?php echo esc_attr($product_name); ?>"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-title="<?php esc_attr_e('Add to Exclude', 'wh-bulk-price-update-for-woocommerce'); ?>"
                            title="<?php esc_attr_e('Add to Exclude', 'wh-bulk-price-update-for-woocommerce'); ?>"
                            aria-label="<?php esc_attr_e('Add to Exclude', 'wh-bulk-price-update-for-woocommerce'); ?>">
                            <i class="fa-solid fa-ban" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
