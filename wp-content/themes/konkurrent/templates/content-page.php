<?php use Konkurrent\Site\Wrapper; ?>
<div class="container" id="page-container">
    <div class="row">

        <div class="col-md-9 col-xs-12">
            <div class="row inner-post-container">
                <div class="col-xs-12">
                    <div class="post-content-container">
                        <?php
                        the_content();
                        wp_link_pages( array(
                                'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'konkurrent' ),
                                'after'	 => '</div>',
                        ) );
                        ?>
                        
                    </div>
                </div>
            </div>
            <?php comments_template('/templates/comments.php'); ?>
        </div>

        <div class="col-md-3 col-xs-12">
            <aside class="sidebar">
                <?php include Wrapper\konkurrent_sidebar_path(); ?>
            </aside><!-- /.sidebar -->
        </div>

    </div>
</div>
