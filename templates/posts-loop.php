<?php
# Prevent direct file access
defined( 'ABSPATH' ) || exit;

/**
 * @var array $posts
 */
?>
<div class="row row-cols-1 row-cols-md-3 g-4 wh-posts-loop">
    <?php foreach($posts as $post): ?>
        <div class="col wh-post-loop-item">
            <div class="card p-0 h-100" style="--bs-card-border-width:1px;--bs-card-border-radius:10px">
                <a href="<?php echo esc_url( $post['link'] ); ?>" target="_blank" rel="nofollow">
                    <img src="<?php echo esc_url( $post['_embedded']['wp:featuredmedia'][0]['source_url'] ); ?>"
                         alt="<?php echo esc_attr( $post['_embedded']['wp:featuredmedia'][0]['alt_text'] ); ?>"
                         class="card-img-top object-fit-cover" style="max-height:254px">
                </a>
                <div class="card-body">
                    <h4 class="card-title">
                        <a href="<?php echo esc_url( $post['link'] ); ?>" target="_blank" rel="nofollow"
                           class="text-decoration-none" style="color:#319B80">
                            <?php echo esc_html( $post['title']['rendered'] ); ?>
                        </a>
                    </h4>
                    <div class="card-text">
                        <?php echo wp_kses( $post['excerpt']['rendered'], ['p' => []] ); ?>
                    </div>
                    <a href="<?php echo esc_url( $post['link'] ); ?>" target="_blank" rel="nofollow"
                       class="btn btn-outline-success">
                        <?php esc_html_e( 'Read more', 'wh-bulk-price-update-for-woocommerce' ); ?>
                    </a>
                </div> <!-- @.card-body -->
            </div> <!-- @.card -->
        </div> <!-- @.col -->
    <?php endforeach; ?>
</div> <!-- @.row -->
