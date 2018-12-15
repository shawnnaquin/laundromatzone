<?php
$date = get_the_date();
$formatted_date = strtotime($date);
$post_day = date('d',$formatted_date);
$post_month = date('M',$formatted_date);
?>
<div class="row inner-posts-container">
    
    <div class="entry-info">
        <div class="post-icon"><i class="glyphicon glyphicon-align-left"></i></div>
        <?php if ( 'post' == get_post_type() ) : ?>
            <div class="date" title="<?php echo esc_attr($date); ?>">
                <div class="day"><?php echo esc_html($post_day); ?></div>
                <div class="month"><?php echo esc_html($post_month); ?></div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-xs-12 content-wrapper">
        <?php
        if(has_post_thumbnail()) {
            $content_class = '';
        ?>
            <div class="image-container">
                <a href="<?php echo esc_url(get_the_permalink()); ?>" class="overlay"></a>
                <?php the_post_thumbnail(); ?>
            </div>
        <?php } else {
            $content_class = 'full-width';
        } ?>

        <div class="post-content-container <?php echo esc_attr($content_class); ?>">

            <h4>
                <a href="<?php echo esc_url(get_the_permalink()); ?>" title="<?php the_title(); ?>">
                    <?php the_title(); ?>
                </a>
            </h4>

            <?php the_excerpt(); ?>

            <a href="<?php echo esc_url(get_the_permalink()); ?>" title="<?php the_title(); ?>">
                <?php _e('Read More', 'konkurrent'); ?>
            </a>

        </div>

    </div>
</div>