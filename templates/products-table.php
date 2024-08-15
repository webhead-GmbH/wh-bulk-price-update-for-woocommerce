<?php
# Prevent direct file access
defined( 'ABSPATH' ) || exit;

/**
 * @var array $products
 * @var string $caption
 */
?>
<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle caption-top wh-preview-price-table">
        <caption>
            <h2 class="m-0"><?php esc_html_e( 'Results', 'wh-bulk-price-update-for-woocommerce' ); ?></h2>
            <?php echo esc_html( $caption ); ?>
        </caption>
        <thead class="table-dark">
        <tr>
            <th scope="col"><?php esc_html_e( 'Product', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Categories', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Tags', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Modified Details', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
        </tr>
        </thead>
        <tbody class="table-group-divider">
        <?php foreach($products as $product): ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url( $product['permalink'] ); ?>" target="_blank">
                        <?php echo esc_html( $product['name'] ); ?>
                    </a>
                </td>
                <td><?php echo esc_html( $product['categories'] ); ?></td>
                <td><?php echo esc_html( $product['tags'] ); ?></td>
                <td>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle wh-price-details-table m-0">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'Price Type', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Original Price', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
                                <th><?php esc_html_e( 'Modified Price', 'wh-bulk-price-update-for-woocommerce' ); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($product['original_prices'] as $price_type => $original_price): ?>
                                <tr>
                                    <td>
                                        <code><?php echo esc_html( ucfirst( str_replace( '_', ' ', $price_type ) ) ); ?></code>
                                    </td>
                                    <td>
                                        <code><?php echo wp_kses( $original_price, ['span' => ['class'], 'bdi'] ); ?></code>
                                    </td>
                                    <td>
                                        <span class="text-success">
                                            <?php echo wp_kses( $product['change_prices'][$price_type], ['span' => ['class'], 'bdi'] ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div> <!-- @.table-responsive -->