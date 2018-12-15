<?php use Konkurrent\Site\Wrapper; ?>
 <div class="container" id="page-container">
    <div class="row">

        <div class="col-md-9 col-xs-12">

            <div class="row inner-post-container">
                <div class="col-xs-12">
                    <div class="image-container">
                        <?php the_post_thumbnail(); ?>
                    </div>
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
            
            <?php if(has_tag()) { ?>
                <!-- TAGS CONTAINER -->
                <div class="row" id="tags-container">
                    <div class="col-xs-12">
                        <span>
                            <span class="glyphicon glyphicon-folder-open"></span>
                            <?php
                                konkurrent_category_list(3); 
                            ?>
                        </span>
                        <span>
                            <span class="glyphicon glyphicon-tags"></span>
                            <?php konkurrent_tag_list(3); ?>
                        </span>
                    </div>
                </div>
                <!-- END OF TAGS CONTAINER -->
            <?php } ?>

            <?php comments_template('/templates/comments.php'); ?>
        </div>

        <div class="col-md-3 col-xs-12">
             <aside class="sidebar">
                <?php include Wrapper\konkurrent_sidebar_path(); ?>
            </aside><!-- /.sidebar -->
        </div>

    </div>
</div>
