<?php
# Prevent direct file access
defined('ABSPATH') || exit;

/**
 * @var array $price_rules
 * @var array $rule_types
 * @var array $schedule_types
 * @var array $margin_tiers
 * @var string $categories
 * @var string $brands
 * @var WP_Term[] $tags
 * @var array $attributes
 */
?>
<div class="wh-setting-header">
    <h3 class="m-0">
        <span class="wh-setting-header-icon me-3">
            <i class="fa-solid fa-clock"></i>
        </span>
        <?php esc_html_e('Scheduled Rules', 'wh-bulk-price-update-for-woocommerce'); ?>
    </h3>
    <button type="button" class="btn btn-success rounded-pill text-uppercase" id="wh-add-rule-btn"
        style="--bs-btn-padding-y: 10px; --bs-btn-padding-x: 23px; --bs-btn-font-size: 14px; --bs-btn-line-height: 16px; --bs-btn-font-weight: 700">
        <i class="fa-solid fa-plus me-1"></i>
        <?php esc_html_e('Add New Rule', 'wh-bulk-price-update-for-woocommerce'); ?>
    </button>
</div> <!-- @.wh-setting-header -->

<?php if (empty($price_rules)): ?>
    <div class="wh-empty-state text-center py-5">
        <div class="wh-empty-state-icon mb-3">
            <i class="fa-solid fa-clock fa-3x text-muted"></i>
        </div>
        <h4 class="text-muted"><?php esc_html_e('No Scheduled Rules Yet', 'wh-bulk-price-update-for-woocommerce'); ?></h4>
        <p class="text-muted mb-4">
            <?php esc_html_e('Create your first scheduled price rule to automatically adjust product prices based on conditions like margins, discounts, and more.', 'wh-bulk-price-update-for-woocommerce'); ?>
        </p>
        <button type="button" class="btn btn-success wh-add-rule-trigger">
            <i class="fa-solid fa-plus me-1"></i>
            <?php esc_html_e('Create Your First Rule', 'wh-bulk-price-update-for-woocommerce'); ?>
        </button>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle wh-rules-table">
            <thead class="table-light">
                <tr>
                    <th scope="col" style="width: 30%"><?php esc_html_e('Rule Name', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                    <th scope="col" style="width: 15%"><?php esc_html_e('Type', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                    <th scope="col" style="width: 15%"><?php esc_html_e('Schedule', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                    <th scope="col" style="width: 10%"><?php esc_html_e('Status', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                    <th scope="col" style="width: 15%"><?php esc_html_e('Last Run', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                    <th scope="col" style="width: 15%"><?php esc_html_e('Actions', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($price_rules as $rule):
                    $conditions = json_decode($rule->conditions, true) ?: [];
                    $actions_data = json_decode($rule->actions, true) ?: [];
                ?>
                    <tr id="wh-rule-row-<?php echo esc_attr($rule->id); ?>">
                        <td>
                            <strong><?php echo esc_html($rule->name); ?></strong>
                            <?php if (! empty($conditions['categories'])): ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fa-solid fa-folder-open me-1"></i>
                                    <?php
                                    $cat_names = [];
                                    foreach ((array) $conditions['categories'] as $cat_id) {
                                        $term = get_term($cat_id, 'product_cat');
                                        if ($term && ! is_wp_error($term)) {
                                            $cat_names[] = $term->name;
                                        }
                                    }
                                    echo esc_html(implode(', ', $cat_names));
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $type_badges = [
                                'margin_check'     => 'bg-primary',
                                'max_discount_pct' => 'bg-warning text-dark',
                                'price_adjustment' => 'bg-info text-dark',
                            ];
                            $badge_class = $type_badges[$rule->rule_type] ?? 'bg-secondary';
                            $type_label  = $rule_types[$rule->rule_type] ?? $rule->rule_type;
                            ?>
                            <span class="badge <?php echo esc_attr($badge_class); ?> wh-rule-type-badge">
                                <?php echo esc_html($type_label); ?>
                            </span>
                        </td>
                        <td>
                            <span class="wh-schedule-label">
                                <i class="fa-regular fa-clock me-1"></i>
                                <?php echo esc_html($schedule_types[$rule->schedule_type] ?? $rule->schedule_type); ?>
                            </span>
                            <?php
                            $hours = json_decode($rule->schedule_hours, true) ?: [];
                            if (! empty($hours)):
                            ?>
                                <br>
                                <small class="text-muted"><?php echo esc_html(implode(', ', $hours)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="form-check form-switch wh-rule-status-toggle">
                                <input class="form-check-input wh-toggle-rule-status" type="checkbox"
                                    role="switch"
                                    data-rule-id="<?php echo esc_attr($rule->id); ?>"
                                    <?php checked($rule->status, 'active'); ?>>
                            </div>
                        </td>
                        <td>
                            <?php if ($rule->last_run_at): ?>
                                <small title="<?php echo esc_attr($rule->last_run_at); ?>">
                                    <?php echo esc_html(human_time_diff(strtotime($rule->last_run_at), current_time('timestamp'))); ?>
                                    <?php esc_html_e('ago', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted"><?php esc_html_e('Never', 'wh-bulk-price-update-for-woocommerce'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary wh-edit-rule"
                                    data-rule-id="<?php echo esc_attr($rule->id); ?>"
                                    data-rule='<?php echo esc_attr(wp_json_encode([
                                                    'id'             => $rule->id,
                                                    'name'           => $rule->name,
                                                    'status'         => $rule->status,
                                                    'schedule_type'  => $rule->schedule_type,
                                                    'schedule_hours' => $hours,
                                                    'rule_type'      => $rule->rule_type,
                                                    'conditions'     => $conditions,
                                                    'actions'        => $actions_data,
                                                ])); ?>'
                                    title="<?php esc_attr_e('Edit', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-outline-success wh-run-rule"
                                    data-rule-id="<?php echo esc_attr($rule->id); ?>"
                                    title="<?php esc_attr_e('Run Now', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary wh-view-logs"
                                    data-rule-id="<?php echo esc_attr($rule->id); ?>"
                                    title="<?php esc_attr_e('View Logs', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                    <i class="fa-solid fa-list-check"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger wh-delete-rule"
                                    data-rule-id="<?php echo esc_attr($rule->id); ?>"
                                    title="<?php esc_attr_e('Delete', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Rule Form Modal -->
<?php webhead_bulk_price_update_load_template('price-rule-form', [
    'rule_types'     => $rule_types,
    'schedule_types' => $schedule_types,
    'margin_tiers'   => $margin_tiers,
    'categories'     => $categories,
    'brands'         => $brands,
    'tags'           => $tags,
    'attributes'     => $attributes,
]); ?>

<!-- Rule Logs Modal -->
<div class="modal fade" id="wh-rule-logs-modal" tabindex="-1" aria-labelledby="whRuleLogsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5" id="whRuleLogsModalLabel">
                    <i class="fa-solid fa-list-check me-2"></i>
                    <?php esc_html_e('Execution Logs', 'wh-bulk-price-update-for-woocommerce'); ?>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="wh-rule-logs-content">
                <div class="text-center p-4">
                    <span class="spinner-border text-success" role="status"></span>
                    <p class="mt-2 text-muted"><?php esc_html_e('Loading logs...', 'wh-bulk-price-update-for-woocommerce'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="wh-confirm-delete-rule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5 m-0">
                    <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>
                    <?php esc_html_e('Delete Rule', 'wh-bulk-price-update-for-woocommerce'); ?>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php esc_html_e('Are you sure you want to delete this rule? All associated logs will also be deleted. This action cannot be undone.', 'wh-bulk-price-update-for-woocommerce'); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php esc_html_e('Cancel', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
                <button type="button" class="btn btn-danger" id="wh-confirm-delete-rule-btn">
                    <i class="fa-solid fa-trash-can me-1"></i>
                    <?php esc_html_e('Delete', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Run Result Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
    <div id="wh-rule-toast" class="toast align-items-center border-0" role="alert" aria-live="assertive"
        aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="wh-rule-toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Close"></button>
        </div>
    </div>
</div>
