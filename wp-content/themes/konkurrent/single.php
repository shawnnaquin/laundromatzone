<?php use Konkurrent\Site\Titles; ?> 
<?php while (have_posts()) : the_post(); ?>
    <div class="container-fluid" id="page-header">
            <div class="row">
                <?php
                $header_image = get_header_image();
                if($header_image != '') {
                    $style = "background-image:url('".esc_url($header_image)."')";
                    $img_class = '';
                } else {
                    $style = '';
                    $img_class = 'canvas-exists';
                }
                ?>
                <div class="image-container <?php echo esc_attr($img_class); ?>" style="<?php echo esc_attr($style); ?>">
                    <h1><?php echo Titles\title(); ?></h1>
                    <div class="entry-meta">
                    <span class="inner-container">
                        <span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?php _e('By', 'konkurrent'); ?> <?php the_author_posts_link(); ?>
                    </span>
                    <span class="inner-container">
                      <span class="glyphicon glyphicon-time" aria-hidden="true"></span><?php the_date(); ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php get_template_part('templates/content-single', get_post_type()); ?>
<?php endwhile; ?>