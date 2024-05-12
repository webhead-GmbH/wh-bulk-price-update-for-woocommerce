<?php
# Prevent direct file access
defined( 'ABSPATH' ) || exit;

/**
 * @var array $plugins
 */
?>

<div class="row row-cols-1 row-cols-md-3 g-4 wh-plugins-loop">
    <?php foreach($plugins as $plugin): ?>
        <div class="col wh-plugin-loop-item">
            <div class="card p-0 h-100" style="--bs-card-border-width:1px;--bs-card-border-radius:10px">
                <div class="card-header">
                    <img src="<?php echo esc_url( $plugin['icon'] ); ?>"
                         alt="<?php echo esc_attr( $plugin['name'] ); ?>"
                         class="object-fit-cover" width="60" height="60">
                    <?php if( !empty( $plugin['badge'] ) ): ?>
                        <div class="badge text-bg-success">
                            <?php echo esc_html( $plugin['badge'] ); ?>
                        </div>
                    <?php endif; ?>
                </div> <!-- @card-header -->
                <div class="card-body">
                    <h3 class="card-title">
                        <?php echo esc_html( $plugin['name'] ); ?>
                    </h3>
                    <div class="card-text">
                        <?php echo wp_kses( $plugin['description'], ['strong' => []] ); ?>
                    </div>
                </div> <!-- @.card-body -->
                <div class="card-footer">
                    <?php if( !empty( $plugin['learn_more_url'] ) ): ?>
                        <a href="<?php echo esc_url( $plugin['learn_more_url'] ); ?>" target="_blank" rel="nofollow"
                           class="text-decoration-none">
                            <?php esc_html_e( 'Learn More', 'wh-bulk-price-update' ); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $plugin['action_url'] ); ?>" class="btn btn-outline-success"
                       target="<?php echo esc_attr( $plugin['target'] ) ?>" rel="nofollow">
                        <?php echo esc_html( $plugin['action_label'] ); ?>
                    </a>
                </div> <!-- @card-footer -->
            </div> <!-- @.card -->
        </div> <!-- @.col -->
    <?php endforeach; ?>
</div> <!-- @.row -->
