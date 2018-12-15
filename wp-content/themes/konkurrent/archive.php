<div class="container-fluid" id="page-header">
    <div class="row">
        <?php
            $header_image = get_header_image();
            if($header_image != '') {
                $style = "background-image:url('".esc_url($header_image)."')";
                $img_class = '';
            } else {
                $img_class = 'canvas-exists';
            }
        ?>
        <div class="image-container <?php echo esc_attr($img_class); ?>" style="<?php echo esc_attr($style); ?>">
            <h1><?php the_archive_title(); ?></h1>
        </div>
    </div>
</div>
<div class="container" id="page-container">
    <div class="row">

        <div class="col-xs-12">
            <?php
            if ( have_posts() ) :
                while ( have_posts() ) : the_post();
                    get_template_part('templates/content', 'post');
                endwhile;

                get_template_part('templates/pagination');

                wp_reset_postdata();
            else :
            ?>
                <p><?php _e( 'Sorry, no posts matched your criteria.', 'konkurrent' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>