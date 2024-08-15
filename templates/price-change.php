<?php
# Prevent direct file access
defined( 'ABSPATH' ) || exit;

/**
 * @var string $categories
 * @var WP_Term[] $tags
 * @var array $attributes
 * @var int $block_size
 * @var int $preview_count
 * @var int $time_limit
 */
?>
<div class="wrap wh-dashboard">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-sm-4 pe-0 wh-nav-pills">
                <div class="wh-settings-sidebar">
                    <div class="wh-logo">
                        <img src="<?php echo esc_url( WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/img/webhead_logo.svg' ); ?>"
                             alt="Logo" class="img-fluid">
                    </div> <!-- @.wh-logo -->

                    <div class="wh-settings-tab d-flex align-items-start">
                        <div class="nav flex-column nav-pills w-100" id="v-pills-tab" role="tablist"
                             aria-orientation="vertical">
                            <button class="nav-link active" id="v-pills-update-price-tab" data-bs-toggle="pill"
                                    data-bs-target="#v-pills-update-price" type="button" role="tab"
                                    aria-controls="v-pills-update-price" aria-selected="true">
                                <span class="wh-nav-active-icon"></span>
                                <span class="d-flex flex-column align-items-start">
                                    <span class="wh-nav-title">
                                        <?php esc_html_e( 'Update prices', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </span>
                                    <span class="wh-nav-subtitle">
                                        <?php esc_html_e( 'Bulk update product prices', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </span>
                                </span>
                                <span class="wh-nav-icon">
                                    <i class="fa-solid fa-hand-holding-dollar"></i>
                                </span>
                            </button>
                            <button class="nav-link" id="v-pills-settings-tab" data-bs-toggle="pill"
                                    data-bs-target="#v-pills-settings" type="button" role="tab"
                                    aria-controls="v-pills-settings" aria-selected="false">
                                <span class="wh-nav-active-icon"></span>
                                <span class="d-flex flex-column align-items-start">
                                    <span class="wh-nav-title">
                                        <?php esc_html_e( 'Settings', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </span>
                                    <span class="wh-nav-subtitle">
                                        <?php esc_html_e( 'All general settings', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </span>
                                </span>
                                <span class="wh-nav-icon">
                                    <i class="fa-solid fa-gear"></i>
                                </span>
                            </button>
                            <button class="nav-link" id="v-pills-about-tab" data-bs-toggle="pill"
                                    data-bs-target="#v-pills-about" type="button" role="tab"
                                    aria-controls="v-pills-about" aria-selected="false">
                                <span class="wh-nav-active-icon"></span>
                                <span class="d-flex flex-column align-items-start">
                                    <span class="wh-nav-title">
                                        <?php esc_html_e( 'About us', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </span>
                                    <span class="wh-nav-subtitle">
                                        <?php esc_html_e( 'Our story and other plugins', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </span>
                                </span>
                                <span class="wh-nav-icon">
                                    <i class="fa-regular fa-thumbs-up"></i>
                                </span>
                            </button>
                        </div> <!-- @.nav -->
                    </div> <!-- @.wh-settings-tab -->
                </div> <!-- @.wh-fixed-top -->
            </div> <!-- @.col-lg-3 -->
            <div class="col-lg-9 col-sm-8 ps-0 wh-nav-contents">
                <div class="card w-100">
                    <div class="card-body tab-content" id="v-pills-tabContent">
                        <div class="tab-pane fade show active" id="v-pills-update-price" role="tabpanel"
                             aria-labelledby="v-pills-update-price-tab" tabindex="0">
                            <div class="wh-setting-header">
                                <h3 class="m-0">
                                    <span class="wh-setting-header-icon me-3">
                                        <i class="fa-solid fa-hand-holding-dollar"></i>
                                    </span>
                                    <?php esc_html_e( 'Update prices', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                </h3>
                            </div> <!-- @.wh-settings-header -->

                            <div class="mb-3">
                                <label for="price_type" class="form-label d-block">
                                    <?php esc_html_e( 'Price type to modify', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                </label>
                                <select class="form-select" name="price_type" id="price_type"
                                        aria-label="<?php esc_attr_e( 'Price type to modify', 'wh-bulk-price-update-for-woocommerce' ); ?>">
                                    <option value="both" selected>
                                        <?php esc_html_e( 'Regular and sale prices', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="regular_price">
                                        <?php esc_html_e( 'Regular price only', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="sale_price">
                                        <?php esc_html_e( 'Sale price only', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                </select>
                            </div> <!-- @.mb-3 price_type -->

                            <label class="form-label d-block">
                                <?php esc_html_e( 'Price action', 'wh-bulk-price-update-for-woocommerce' ); ?>
                            </label>
                            <div class="input-group wh-price-input-group mb-3">
                                <select class="form-select" name="action_type" id="action_type"
                                        aria-label="<?php esc_attr_e( 'Action type to modify', 'wh-bulk-price-update-for-woocommerce' ); ?>">
                                    <option value="increase" selected>
                                        <?php esc_html_e( 'Increase (+)', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="decrease">
                                        <?php esc_html_e( 'Decrease (-)', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="multiply">
                                        <?php esc_html_e( 'Multiply (*)', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="divide">
                                        <?php esc_html_e( 'Divide  (/)', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="fixed">
                                        <?php esc_html_e( 'Direct price', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                </select>

                                <input type="number" class="form-control" min="0" id="price_value" name="price_value"
                                       value="0" aria-label="Price value" aria-describedby="price-action">

                                <select class="form-select" name="change_type" id="change_type"
                                        aria-label="<?php esc_attr_e( 'Change type to modify', 'wh-bulk-price-update-for-woocommerce' ); ?>">
                                    <option value="percentage" selected>
                                        <?php esc_html_e( 'Percentage (%)', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                    <option value="fixed">
                                        <?php esc_html_e( 'Fixed', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </option>
                                </select>
                            </div> <!-- @.input-group.wh-price-input-group -->
                            <div class="form-text d-none" id="fixed-price-help">
                                <?php esc_html_e( 'Select this if you want to set all products prices to same value.', 'wh-bulk-price-update-for-woocommerce' ); ?>
                            </div>
                            <div class="form-text" id="price-example">
                                <?php esc_html_e( 'Example', 'wh-bulk-price-update-for-woocommerce' ); ?>:
                                <abbr id="ex_current_price"
                                      title="<?php esc_html_e( 'Original Price', 'wh-bulk-price-update-for-woocommerce' ); ?>"
                                      data-value="150">
                                    <?php echo wp_kses( wc_price( 150 ), ['span' => ['class'], 'bdi'] ); ?>
                                </abbr>
                                <span id="ex_action_type"><code>+</code></span>
                                <span id="ex_price_value">0</span>
                                <span id="ex_change_type"><code>%</code></span>
                                <span id="ex_equal">=</span>
                                <abbr id="ex_result"
                                      title="<?php esc_html_e( 'Modified Price', 'wh-bulk-price-update-for-woocommerce' ); ?>">
                                    <?php echo wp_kses( wc_price( 150 ), ['span' => ['class'], 'bdi'] ); ?>
                                </abbr>
                            </div> <!-- @#price-example.form-text -->

                            <hr class="my-2"/>

                            <div class="row">
                                <div class="col-lg-6 col-sm-12">
                                    <div class="mb-3">
                                        <label for="apply_to" class="form-label d-block">
                                            <?php esc_html_e( 'Apply to', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        </label>
                                        <select class="form-select" name="apply_to" id="apply_to"
                                                aria-label="<?php esc_attr_e( 'Apply to', 'wh-bulk-price-update-for-woocommerce' ); ?>">
                                            <option value="all" selected>
                                                <?php esc_html_e( 'All products', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                            </option>
                                            <option value="specific">
                                                <?php esc_html_e( 'Specific products and categories', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                            </option>
                                        </select>
                                    </div> <!-- @.mb-3 apply_to -->

                                    <div class="specific_products_wrapper d-none">

                                        <div class="mb-3">
                                            <label for="include_products" class="form-label d-block">
                                                <?php esc_html_e( 'Products to include', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                            </label>
                                            <select class="wc-product-search" id="include_products"
                                                    name="include_products[]"
                                                    multiple="multiple"
                                                    data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wh-bulk-price-update-for-woocommerce' ); ?>"></select>
                                        </div> <!-- @.mb-3 -->

                                        <div class="mb-3">
                                            <label for="categories" class="form-label d-block">
                                                <?php esc_html_e( 'Categories to include', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                            </label>
                                            <?php echo wp_kses(
                                                $categories,
                                                [
                                                    'select' => [
                                                        'name'        => [],
                                                        'id'          => [],
                                                        'class'       => [],
                                                        'multiple'    => [],
                                                        'tabindex'    => [],
                                                        'aria-hidden' => [],
                                                    ],
                                                    'option' => [
                                                        'class' => [],
                                                        'value' => [],
                                                    ],
                                                ]
                                            ); ?>
                                        </div> <!-- @.mb-3 -->

                                        <div class="mb-3">
                                            <label for="tags" class="form-label d-block">
                                                <?php esc_html_e( 'Tags to include', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                            </label>
                                            <select class="wh-select2" name="tags[]" id="tags" multiple="multiple"
                                                    aria-label="<?php esc_attr_e( 'Search for tags', 'wh-bulk-price-update-for-woocommerce' ); ?>">
                                                <?php foreach($tags as $tag): ?>
                                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>">
                                                        <?php echo esc_html( $tag->name ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div> <!-- @.mb-3 -->
                                    </div> <!-- @.specific_products_wrapper -->

                                    <div class="form-check form-switch d-flex align-items-center mb-3 mt-4">
                                        <input type="hidden" name="has_exclude_products" value="0">
                                        <input class="form-check-input d-block" type="checkbox" role="switch"
                                               id="has_exclude_products" name="has_exclude_products" value="1">
                                        <label class="form-check-label d-block" for="has_exclude_products">
                                            <?php esc_html_e( 'Exclude products', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        </label>
                                    </div> <!-- @.form-switch has_exclude_products -->

                                    <div id="exclude_products_wrapper" class="d-none">
                                        <div class="mb-3">
                                            <label for="exclude_products" class="form-label d-block">
                                                <?php esc_html_e( 'Products to exclude', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                            </label>
                                            <select class="wc-product-search" id="exclude_products"
                                                    name="exclude_products[]" multiple="multiple"
                                                    data-placeholder="<?php esc_attr_e( 'Search for a product&hellip;', 'wh-bulk-price-update-for-woocommerce' ); ?>"></select>
                                        </div> <!-- @.mb-3 -->
                                    </div> <!-- @#exclude_products_wrapper -->

                                </div> <!-- @.col-lg-6.col-sm-12 -->
                                <div class="col-lg-6 col-sm-12">
                                    <div class="specific_products_wrapper d-none">
                                        <?php foreach($attributes as $attr): ?>
                                            <div class="mb-3">
                                                <label class="form-label d-block"
                                                       for="<?php echo esc_attr( $attr->attribute_name ); ?>">
                                                    <?php echo sprintf(
                                                    /* translators: %s: Attribute label. */
                                                        wp_kses( __( 'Products in <b>%s</b> attribute', 'wh-bulk-price-update-for-woocommerce' ), ['b' => []] ),
                                                        esc_html( $attr->attribute_label ),
                                                    ); ?>
                                                </label>
                                                <select class="form-select wh-select2" multiple
                                                        id="<?php echo esc_attr( $attr->attribute_name ); ?>"
                                                        name="<?php echo esc_attr( wc_attribute_taxonomy_name( $attr->attribute_name ) ); ?>">
                                                    <?php
                                                    $attr_terms = get_terms( wc_attribute_taxonomy_name( $attr->attribute_name ) );
                                                    foreach($attr_terms as $attr_term): ?>
                                                        <option value="<?php echo esc_attr( $attr_term->slug ); ?>">
                                                            <?php echo esc_html( $attr_term->name ); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div> <!-- @.mb-3 -->
                                        <?php endforeach; ?>
                                    </div> <!-- @.specific_products_wrapper -->
                                </div> <!-- @.col-lg-6.col-sm-12 -->
                            </div> <!-- @.row -->

                            <button type="button" class="btn btn-primary" id="preview-prices">
                                <?php esc_html_e( 'Preview prices', 'wh-bulk-price-update-for-woocommerce' ); ?>
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                    data-bs-target="#wh-confirm-update-price">
                                <?php esc_html_e( 'Change prices', 'wh-bulk-price-update-for-woocommerce' ); ?>
                            </button>
                            <span id="pp_spinner" class="spinner" style="float:none"></span>

                            <div id="preview-products-result" class="mt-5 d-none"></div>

                        </div> <!-- @.tab-pane update-price -->
                        <div class="tab-pane fade" id="v-pills-settings" role="tabpanel"
                             aria-labelledby="v-pills-settings-tab" tabindex="0">
                            <form method="post" id="wh-settings-form">
                                <div class="wh-setting-header">
                                    <h3 class="m-0">
                                        <span class="wh-setting-header-icon me-3">
                                            <i class="fa-solid fa-gear"></i>
                                        </span>
                                        <?php esc_html_e( 'Settings', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </h3>
                                    <button type="submit" class="btn btn-success rounded-pill text-uppercase"
                                            style="--bs-btn-padding-y: 10px; --bs-btn-padding-x: 23px; --bs-btn-font-size: 14px; --bs-btn-line-height: 16px; --bs-btn-font-weight: 700">
                                        <i class="btn-icon fa-regular fa-floppy-disk me-1"></i>
                                        <span class="btn-text">
                                            <?php esc_html_e( 'Save Changes', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        </span>
                                        <span class="btn-spinner spinner-border spinner-border-sm me-1 d-none"
                                              aria-hidden="true"></span>
                                        <span role="status" class="btn-status d-none">
                                            <?php esc_html_e( 'Saving...', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        </span>
                                    </button>
                                </div> <!-- @.wh-settings-header -->

                                <div class="mb-3">
                                    <label for="block_size" class="form-label">
                                        <?php esc_html_e( 'Block size', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        <span class="wh-label-help" data-bs-toggle="tooltip"
                                              data-bs-title="<?php esc_attr_e( 'Number of products processed in a single products query.', 'wh-bulk-price-update-for-woocommerce' ); ?>">?</span>
                                    </label>
                                    <input type="number" min="1" name="block_size" id="block_size" class="form-control"
                                           value="<?php echo esc_attr( $block_size ); ?>">
                                </div> <!-- @.mb-3 block_size -->

                                <div class="mb-3">
                                    <label for="preview_count" class="form-label">
                                        <?php esc_html_e( 'Preview count', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        <span class="wh-label-help" data-bs-toggle="tooltip"
                                              data-bs-title="<?php esc_attr_e( 'Number of products for preview.', 'wh-bulk-price-update-for-woocommerce' ); ?>">?</span>
                                    </label>
                                    <input type="number" min="1" name="preview_block_size" id="preview_count"
                                           class="form-control" value="<?php echo esc_attr( $preview_count ); ?>">
                                </div> <!-- @.mb-3 preview_count -->

                                <div class="mb-3">
                                    <label for="time_limit" class="form-label">
                                        <?php esc_html_e( 'Time limit', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                        <span class="wh-label-help" data-bs-toggle="tooltip"
                                              data-bs-title="<?php esc_attr_e( 'The maximum execution time for a a single products query, in seconds. If set to zero, no time limit is imposed. If set to -1, default server time limit is used.', 'wh-bulk-price-update-for-woocommerce' ); ?>">?</span>
                                    </label>
                                    <input type="number" min="-1" name="time_limit" id="time_limit" class="form-control"
                                           value="<?php echo esc_html( $time_limit ); ?>">
                                </div> <!-- @.mb-3 time_limit -->

                            </form>
                        </div> <!-- @.tab-pane settings -->
                        <div class="tab-pane fade" id="v-pills-about" role="tabpanel"
                             aria-labelledby="v-pills-about-tab" tabindex="0">
                            <div class="wh-setting-header">
                                <h3 class="m-0">
                                    <span class="wh-setting-header-icon me-3">
                                        <i class="fa-regular fa-thumbs-up"></i>
                                    </span>
                                    <?php esc_html_e( 'About us', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                </h3>
                            </div> <!-- @.wh-settings-header -->

                            <div class="mb-3">
                                <img src="<?php echo esc_url( WEBHEAD_BULK_PRICE_UPDATE_PLUGIN_URL . 'assets/img/webhead_cover.jpg' ); ?>"
                                     alt="Banner" class="img-fluid rounded"/>
                            </div>

                            <div class="mb-3 fs-6 lh-base">
                                <?php esc_html_e( 'webhead is a professional, dynamic, and experienced web design, WordPress & SEO agency based in Vienna. Our agency consists of a talented, competent, and young team of experts who are passionate about each project and always strive to achieve the best possible results for our clients through the latest technologies & trends.', 'wh-bulk-price-update-for-woocommerce' ); ?>
                            </div>

                            <div class="mb-5 mt-4 text-center">
                                <h3>
                                    <?php esc_html_e( 'Top-notch & Friendly Support', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                </h3>
                                <p>
                                    <?php esc_html_e( 'Stuck somewhere? Feel free to open a ticket for getting Pro support.', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                </p>
                                <a href="https://crm.webhead.eu/forms/ticket" target="_blank" rel="nofollow"
                                   class="btn btn-success">
                                    <i class="fa-regular fa-circle-question"></i>
                                    <?php esc_html_e( 'Support', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                </a>
                            </div>

                            <div class="mb-3">
                                <nav class="nav nav-pills flex-column flex-sm-row" id="wh-about-tab" role="tablist">
                                    <a class="flex-sm-fill text-sm-center nav-link active" aria-current="page" href="#"
                                       data-bs-toggle="pill" data-bs-target="#wh-pills-posts" type="button" role="tab"
                                       aria-controls="wh-pills-posts" aria-selected="true">
                                        <?php esc_html_e( 'Latest posts', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </a>
                                    <a class="flex-sm-fill text-sm-center nav-link" href="#" data-bs-toggle="pill"
                                       data-bs-target="#wh-pills-plugins" type="button" role="tab"
                                       aria-controls="wh-pills-plugins" aria-selected="true">
                                        <?php esc_html_e( 'Other plugins', 'wh-bulk-price-update-for-woocommerce' ); ?>
                                    </a>
                                </nav>

                                <div class="tab-content" id="wh-pills-aboutTabContent">
                                    <div class="tab-pane fade show active" id="wh-pills-posts" role="tabpanel"
                                         aria-labelledby="wh-pills-posts" tabindex="0">
                                        <div id="wh-blog-posts-wrapper">
                                            <span class="spinner" style="float:none"></span>
                                            <div id="wh-blog-posts"></div>
                                        </div>
                                    </div> <!-- @.tab-pane blog_posts -->
                                    <div class="tab-pane fade" id="wh-pills-plugins" role="tabpanel"
                                         aria-labelledby="wh-pills-plugins" tabindex="0">
                                        <div id="wh-plugins-wrapper">
                                            <span class="spinner" style="float:none"></span>
                                            <div id="wh-plugins"></div>
                                        </div>
                                    </div> <!-- @.tab-pane plugins -->
                                </div> <!-- @#wh-pills-aboutTabContent.tab-content -->
                            </div> <!-- @.mb-3 -->
                        </div> <!-- @#v-pills-about.tab-pane -->
                    </div> <!-- @.card-body -->
                </div> <!-- @.card -->
            </div> <!-- @.col-lg-9 -->
        </div> <!-- @.row -->
    </div> <!-- @.container-fluid -->
</div> <!-- @.wrap.wh-dashboard -->

<div class="modal fade" id="wh-confirm-update-price" tabindex="-1" aria-labelledby="confirmUpdate" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="h1 modal-title fs-5" id="confirmUpdate">
                    <?php esc_html_e( 'Confirm', 'wh-bulk-price-update-for-woocommerce' ); ?>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div> <!-- @.modal-header -->
            <div class="modal-body">
                <?php esc_html_e( 'There is no undo for this action. Are you sure?', 'wh-bulk-price-update-for-woocommerce' ); ?>
            </div> <!-- @.modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php esc_html_e( 'Cancel', 'wh-bulk-price-update-for-woocommerce' ); ?>
                </button>
                <button type="button" class="btn btn-primary" id="wh-do-update-price">
                    <?php esc_html_e( 'Yes, I\'m sure', 'wh-bulk-price-update-for-woocommerce' ); ?>
                </button>
            </div> <!-- @.modal-footer -->
        </div> <!-- @.modal-content -->
    </div> <!-- @.modal-dialog -->
</div> <!-- @.modal -->

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="wh-toast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive"
         aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <?php esc_html_e( 'Settings successfully saved.', 'wh-bulk-price-update-for-woocommerce' ); ?>
            </div> <!-- @.toast-body -->
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                    aria-label="Close"></button>
        </div> <!-- @.d-flex -->
    </div> <!-- @.toast -->
</div> <!-- @.toast-container -->