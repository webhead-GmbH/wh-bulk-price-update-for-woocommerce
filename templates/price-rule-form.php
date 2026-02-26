<?php
# Prevent direct file access
defined('ABSPATH') || exit;

/**
 * @var array $rule_types
 * @var array $schedule_types
 * @var array $margin_tiers
 * @var string $categories
 * @var WP_Term[] $tags
 * @var array $attributes
 */
?>

<div class="modal fade" id="wh-rule-form-modal" tabindex="-1" aria-labelledby="whRuleFormModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5" id="whRuleFormModalLabel">
                    <i class="fa-solid fa-plus-circle me-2"></i>
                    <span id="wh-rule-form-title"><?php esc_html_e('Add New Rule', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="wh-rule-form">
                    <input type="hidden" name="rule_id" id="wh-rule-id" value="0">

                    <div class="wh-step-navigation" role="tablist">
                        <button type="button" class="wh-step-indicator active" data-step="1">
                            <span>1</span>
                            <strong><?php esc_html_e('Basic Information', 'wh-bulk-price-update-for-woocommerce'); ?></strong>
                        </button>
                        <button type="button" class="wh-step-indicator" data-step="2">
                            <span>2</span>
                            <strong><?php esc_html_e('Schedule', 'wh-bulk-price-update-for-woocommerce'); ?></strong>
                        </button>
                        <button type="button" class="wh-step-indicator" data-step="3">
                            <span>3</span>
                            <strong><?php esc_html_e('Product Filters', 'wh-bulk-price-update-for-woocommerce'); ?></strong>
                        </button>
                        <button type="button" class="wh-step-indicator" data-step="4">
                            <span>4</span>
                            <strong><?php esc_html_e('Rule Settings', 'wh-bulk-price-update-for-woocommerce'); ?></strong>
                        </button>
                    </div>

                    <div class="wh-rule-steps">
                    <!-- Step 1: Basic Info -->
                    <div class="wh-rule-step" id="wh-rule-step-1" data-step="1">
                        <div class="wh-step-header mb-3">
                            <span class="wh-step-badge">1</span>
                            <span class="wh-step-title"><?php esc_html_e('Basic Information', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                        </div>

                        <div class="mb-3">
                            <label for="wh-rule-name" class="form-label">
                                <?php esc_html_e('Rule Name', 'wh-bulk-price-update-for-woocommerce'); ?>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="wh-rule-name" name="name"
                                placeholder="<?php esc_attr_e('e.g. Margin Protection Rule', 'wh-bulk-price-update-for-woocommerce'); ?>"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="wh-rule-type" class="form-label">
                                <?php esc_html_e('Rule Type', 'wh-bulk-price-update-for-woocommerce'); ?>
                                <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="wh-rule-type" name="rule_type">
                                <?php foreach ($rule_types as $type_key => $type_label): ?>
                                    <option value="<?php echo esc_attr($type_key); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text wh-rule-type-help">
                                <span data-type="margin_check">
                                    <?php esc_html_e('Ensures the sale price maintains a minimum margin above the Cost of Goods (COG).', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </span>
                                <span data-type="max_discount_pct" class="d-none">
                                    <?php esc_html_e('Limits the maximum discount percentage compared to the regular price.', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </span>
                                <span data-type="price_adjustment" class="d-none">
                                    <?php esc_html_e('Applies a fixed or percentage price change to matching products.', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="wh-rule-status" class="form-label">
                                <?php esc_html_e('Status', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </label>
                            <select class="form-select" id="wh-rule-status" name="status">
                                <option value="active"><?php esc_html_e('Active', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                <option value="paused"><?php esc_html_e('Paused', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                            </select>
                        </div>
                    </div> <!-- @#wh-rule-step-1 -->

                    <!-- Step 2: Schedule -->
                    <div class="wh-rule-step" id="wh-rule-step-2" data-step="2">
                        <div class="wh-step-header mb-3">
                            <span class="wh-step-badge">2</span>
                            <span class="wh-step-title"><?php esc_html_e('Schedule', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                        </div>

                        <div class="mb-3">
                            <label for="wh-schedule-type" class="form-label">
                                <?php esc_html_e('Frequency', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </label>
                            <select class="form-select" id="wh-schedule-type" name="schedule_type">
                                <?php foreach ($schedule_types as $sched_key => $sched_label): ?>
                                    <option value="<?php echo esc_attr($sched_key); ?>"
                                        <?php selected($sched_key, 'daily'); ?>>
                                        <?php echo esc_html($sched_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="wh-schedule-hours-wrapper">
                            <label class="form-label mb-1" id="wh-schedule-hours-label">
                                <?php esc_html_e('Start Time', 'wh-bulk-price-update-for-woocommerce'); ?>
                                <span class="wh-label-help" data-bs-toggle="tooltip"
                                    data-bs-title="<?php esc_attr_e('Specify the time at which this rule should run.', 'wh-bulk-price-update-for-woocommerce'); ?>">?</span>
                            </label>

                            <div class="form-text mb-2 text-muted" id="wh-schedule-helper-text-interval">
                                <?php esc_html_e('The rule will first run at this Start Time and then repeat according to the Frequency interval.', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </div>
                            <div class="form-text mb-2 text-muted d-none" id="wh-schedule-helper-text-custom">
                                <?php esc_html_e('The rule will execute exactly at these specific times every day.', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </div>

                            <div class="form-text mt-0 mb-3 text-info">
                                <i class="fa-solid fa-globe me-1"></i>
                                <?php printf(
                                /* translators: %s: Timezone */
                                        esc_html__('Server Timezone: %s', 'wh-bulk-price-update-for-woocommerce'),
                                        '<strong>' . esc_html(wp_timezone_string()) . '</strong>'
                                ); ?>
                            </div>
                            <div id="wh-schedule-hours-list">
                                <div class="input-group mb-2 wh-schedule-hour-row">
                                    <input type="time" class="form-control wh-schedule-hour-input" value="08:00">
                                    <button type="button" class="btn btn-outline-danger wh-remove-hour-btn" title="<?php esc_attr_e('Remove', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success" id="wh-add-hour-btn">
                                <i class="fa-solid fa-plus me-1"></i>
                                <?php esc_html_e('Add Time', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </button>
                        </div>
                    </div> <!-- @#wh-rule-step-2 -->

                    <!-- Step 3: Product Filters -->
                    <div class="wh-rule-step" id="wh-rule-step-3" data-step="3">
                        <div class="wh-step-header mb-3">
                            <span class="wh-step-badge">3</span>
                            <span class="wh-step-title"><?php esc_html_e('Product Filters', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                        </div>

                        <div class="alert alert-light border small mb-4">
                            <i class="fa-solid fa-circle-info text-info me-2"></i>
                            <strong><?php esc_html_e('Tip:', 'wh-bulk-price-update-for-woocommerce'); ?></strong>
                            <?php esc_html_e('Leave all filters empty to apply this rule to all products.', 'wh-bulk-price-update-for-woocommerce'); ?>
                        </div>

                        <!-- Group 1: Taxonomy Filters -->
                        <div class="card mb-3 shadow-sm border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title fw-bold mb-3"><i class="fa-solid fa-tags text-muted me-2"></i><?php esc_html_e('Filter by Taxonomy', 'wh-bulk-price-update-for-woocommerce'); ?></h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="wh-rule-categories" class="form-label text-muted small fw-bold text-uppercase">
                                                <?php esc_html_e('Categories', 'wh-bulk-price-update-for-woocommerce'); ?>
                                            </label>
                                            <?php
                                            echo wp_dropdown_categories([
                                                'taxonomy'      => 'product_cat',
                                                'orderby'       => 'name',
                                                'hide_empty'    => 0,
                                                'hide_if_empty' => false,
                                                'echo'          => false,
                                                'hierarchical'  => true,
                                                'show_count'    => true,
                                                'name'          => 'rule_categories[]',
                                                'id'            => 'wh-rule-categories',
                                                'class'         => 'wh-select2 multiple',
                                                'selected'      => -1,
                                            ]);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="wh-rule-tags" class="form-label text-muted small fw-bold text-uppercase">
                                                <?php esc_html_e('Tags', 'wh-bulk-price-update-for-woocommerce'); ?>
                                            </label>
                                            <select class="wh-select2" name="rule_tags[]" id="wh-rule-tags" multiple="multiple"
                                                aria-label="<?php esc_attr_e('Search for tags', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                                <?php foreach ($tags as $tag): ?>
                                                    <option value="<?php echo esc_attr($tag->term_id); ?>">
                                                        <?php echo esc_html($tag->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 mb-md-0">
                                            <label for="wh-rule-brands" class="form-label text-muted small fw-bold text-uppercase">
                                                <?php esc_html_e('Brands', 'wh-bulk-price-update-for-woocommerce'); ?>
                                            </label>
                                            <?php if (taxonomy_exists('product_brand')): ?>
                                                <?php
                                                echo wp_dropdown_categories([
                                                    'taxonomy'      => 'product_brand',
                                                    'orderby'       => 'name',
                                                    'hide_empty'    => 0,
                                                    'hide_if_empty' => false,
                                                    'echo'          => false,
                                                    'hierarchical'  => true,
                                                    'show_count'    => true,
                                                    'name'          => 'rule_brands[]',
                                                    'id'            => 'wh-rule-brands',
                                                    'class'         => 'wh-select2 multiple',
                                                    'selected'      => -1,
                                                ]);
                                                ?>
                                            <?php else: ?>
                                                <p class="text-muted small mb-0">
                                                    <?php esc_html_e('No brands found or taxonomy missing.', 'wh-bulk-price-update-for-woocommerce'); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-0">
                                            <label for="wh-rule-attributes" class="form-label text-muted small fw-bold text-uppercase">
                                                <?php esc_html_e('Attributes', 'wh-bulk-price-update-for-woocommerce'); ?>
                                            </label>
                                            <select class="wh-ajax-select2 form-control" name="rule_attributes[]" id="wh-rule-attributes" multiple="multiple"
                                                aria-label="<?php esc_attr_e('Search for attributes', 'wh-bulk-price-update-for-woocommerce'); ?>"
                                                data-placeholder="<?php esc_attr_e('Search attributes...', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                                <!-- Options are loaded via AJAX -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Specific Products -->
                        <div class="card shadow-sm border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title fw-bold mb-3"><i class="fa-solid fa-box-open text-muted me-2"></i><?php esc_html_e('Filter by Specific Products', 'wh-bulk-price-update-for-woocommerce'); ?></h6>
                                <div class="mb-3">
                                    <label for="wh-rule-include-products" class="form-label text-muted small fw-bold text-uppercase">
                                        <?php esc_html_e('Include Products', 'wh-bulk-price-update-for-woocommerce'); ?>
                                    </label>
                                    <select class="wc-product-search" id="wh-rule-include-products"
                                        name="rule_include_products[]" multiple="multiple"
                                        data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'wh-bulk-price-update-for-woocommerce'); ?>"></select>
                                </div>

                                <div class="mb-0">
                                    <label for="wh-rule-exclude-products" class="form-label text-muted small fw-bold text-uppercase">
                                        <?php esc_html_e('Exclude Products', 'wh-bulk-price-update-for-woocommerce'); ?>
                                    </label>
                                    <select class="wc-product-search" id="wh-rule-exclude-products"
                                        name="rule_exclude_products[]" multiple="multiple"
                                        data-placeholder="<?php esc_attr_e('Search for a product&hellip;', 'wh-bulk-price-update-for-woocommerce'); ?>"></select>
                                </div>
                            </div>
                        </div>
                    </div> <!-- @#wh-rule-step-3 -->

                    <!-- Step 4: Rule Settings -->
                    <div class="wh-rule-step" id="wh-rule-step-4" data-step="4">
                        <div class="wh-step-header mb-3">
                            <span class="wh-step-badge">4</span>
                            <span class="wh-step-title"><?php esc_html_e('Rule Settings', 'wh-bulk-price-update-for-woocommerce'); ?></span>
                        </div>

                        <!-- Margin Check Settings -->
                        <div class="wh-rule-settings-panel" id="wh-margin-check-settings">
                            <div class="alert alert-info small mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <?php esc_html_e('Define margin tiers based on Cost of Goods (COG). If the sale price falls below COG + margin, it will be adjusted upward.', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </div>

                            <?php if (wc_prices_include_tax()): ?>
                                <div class="alert alert-warning small mb-3">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                    <strong><?php esc_html_e('Tax Notice:', 'wh-bulk-price-update-for-woocommerce'); ?></strong>
                                    <?php esc_html_e('Your store is set to enter prices inclusive of tax. Taxes will be deducted internally before margins are calculated against the Cost of Goods (which is net), and then re-applied to the final sale price.', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </div>
                            <?php endif; ?>

                            <label class="form-label fw-bold">
                                <?php esc_html_e('Margin Tiers', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </label>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle" id="wh-margin-tiers-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40px" class="text-center" title="<?php esc_attr_e('Drag to sort', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                                <i class="fa-solid fa-arrows-up-down text-muted"></i>
                                            </th>
                                            <th><?php esc_html_e('COG From', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                                            <th><?php esc_html_e('COG To', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                                            <th><?php esc_html_e('Margin %', 'wh-bulk-price-update-for-woocommerce'); ?></th>
                                            <th style="width: 50px"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="wh-margin-tiers-body">
                                        <?php foreach ($margin_tiers as $i => $tier): ?>
                                            <tr class="wh-margin-tier-row">
                                                <td style="cursor: move;" class="text-center align-middle" title="<?php esc_attr_e('Drag to sort', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                                    <i class="fa-solid fa-grip-lines text-muted"></i>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" class="form-control wh-tier-min-cog" step="0.01" min="0"
                                                            value="<?php echo esc_attr($tier['min_cog']); ?>">
                                                        <span class="input-group-text"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" class="form-control wh-tier-max-cog" step="0.01" min="0"
                                                            value="<?php echo esc_attr($tier['max_cog']); ?>"
                                                            placeholder="<?php esc_attr_e('0 = unlimited', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                                        <span class="input-group-text"><?php echo esc_html(get_woocommerce_currency_symbol()); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" class="form-control wh-tier-margin" step="0.1" min="0"
                                                            value="<?php echo esc_attr($tier['margin_pct']); ?>">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger wh-remove-tier-btn"
                                                        title="<?php esc_attr_e('Remove', 'wh-bulk-price-update-for-woocommerce'); ?>">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-success" id="wh-add-tier-btn">
                                <i class="fa-solid fa-plus me-1"></i>
                                <?php esc_html_e('Add Tier', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </button>
                            <div id="wh-margin-tiers-error" class="text-danger mt-2 fw-bold d-none"></div>
                        </div> <!-- @#wh-margin-check-settings -->

                        <!-- Max Discount Settings -->
                        <div class="wh-rule-settings-panel d-none" id="wh-max-discount-settings">
                            <div class="alert alert-info small mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <?php esc_html_e('If a product\'s discount percentage exceeds this threshold, the sale price will be adjusted upward to meet the limit.', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </div>

                            <div class="mb-3">
                                <label for="wh-max-discount-pct" class="form-label fw-bold">
                                    <?php esc_html_e('Maximum Discount Percentage', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </label>
                                <div class="input-group" style="max-width: 200px">
                                    <input type="number" class="form-control" id="wh-max-discount-pct"
                                        step="0.1" min="0" max="100" value="20">
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">
                                    <?php esc_html_e('Example: If set to 20%, a product with regular price 100€ cannot have a sale price below 80€.', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </div>
                            </div>
                        </div> <!-- @#wh-max-discount-settings -->

                        <!-- Price Adjustment Settings -->
                        <div class="wh-rule-settings-panel d-none" id="wh-price-adjustment-settings">
                            <div class="alert alert-info small mb-3">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <?php esc_html_e('Apply a price modification to matching products on each scheduled run.', 'wh-bulk-price-update-for-woocommerce'); ?>
                            </div>

                            <div class="mb-3">
                                <label for="wh-adj-price-type" class="form-label fw-bold">
                                    <?php esc_html_e('Price Type', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </label>
                                <select class="form-select" id="wh-adj-price-type" style="max-width: 250px">
                                    <option value="sale_price"><?php esc_html_e('Sale Price', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                    <option value="regular_price"><?php esc_html_e('Regular Price', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <?php esc_html_e('Price Action', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </label>
                                <div class="input-group" style="max-width: 400px">
                                    <select class="form-select" id="wh-adj-action-type">
                                        <option value="increase"><?php esc_html_e('Increase (+)', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                        <option value="decrease"><?php esc_html_e('Decrease (-)', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                        <option value="multiply"><?php esc_html_e('Multiply (*)', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                        <option value="divide"><?php esc_html_e('Divide (/)', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                        <option value="fixed"><?php esc_html_e('Direct price', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                    </select>
                                    <input type="number" class="form-control" id="wh-adj-price-value" step="0.01" min="0" value="0">
                                    <select class="form-select" id="wh-adj-change-type">
                                        <option value="percentage"><?php esc_html_e('Percentage (%)', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                        <option value="fixed"><?php esc_html_e('Fixed', 'wh-bulk-price-update-for-woocommerce'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div> <!-- @#wh-price-adjustment-settings -->

                        <div id="wh-preview-rule-section" class="wh-preview-section mt-4 d-none">
                            <div class="wh-preview-section-header">
                                <h6 class="wh-preview-section-title m-0">
                                    <i class="fa-solid fa-eye me-1"></i>
                                    <?php esc_html_e('Preview Results', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </h6>
                                <p class="wh-preview-section-subtitle mb-0">
                                    <?php esc_html_e('Review estimated price changes before saving this rule.', 'wh-bulk-price-update-for-woocommerce'); ?>
                                </p>
                            </div>
                            <div id="wh-preview-rule-result" class="wh-preview-section-body">
                                <!-- Preview results loaded here via AJAX -->
                            </div>
                        </div>
                    </div> <!-- @#wh-rule-step-4 -->
                    </div> <!-- @.wh-rule-steps -->
                </form>
            </div> <!-- @.modal-body -->
            <div class="modal-footer wh-step-footer">
                <div class="wh-step-actions me-auto">
                    <button type="button" class="btn btn-outline-secondary wh-step-prev" disabled>
                        <?php esc_html_e('Previous', 'wh-bulk-price-update-for-woocommerce'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary wh-step-next">
                        <?php esc_html_e('Next', 'wh-bulk-price-update-for-woocommerce'); ?>
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php esc_html_e('Cancel', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
                <button type="button" class="btn btn-info text-white d-none" id="wh-preview-rule-btn">
                    <i class="fa-solid fa-eye me-1"></i> <?php esc_html_e('Preview prices', 'wh-bulk-price-update-for-woocommerce'); ?>
                </button>
                <button type="button" class="btn btn-success" id="wh-save-rule-btn">
                    <i class="btn-icon fa-regular fa-floppy-disk me-1"></i>
                    <span class="btn-text">
                        <?php esc_html_e('Save Rule', 'wh-bulk-price-update-for-woocommerce'); ?>
                    </span>
                    <span class="btn-spinner spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>
                    <span role="status" class="btn-status d-none">
                        <?php esc_html_e('Saving...', 'wh-bulk-price-update-for-woocommerce'); ?>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
