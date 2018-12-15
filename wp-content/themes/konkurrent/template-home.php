<?php
/**
 * Template Name: Homepage Template
 */

while (have_posts()) : the_post();
?>
    <div class="container-fluid" id="home-container">

        <!-- BANNER CONTAINER -->
        <div class="row">
            <?php if(has_post_thumbnail()) {
                $bg_image = get_the_post_thumbnail_url();
            } else {
                $bg_image = get_header_image();
            }
            $content = get_the_content();
            if($content) {
                $content = substr($content, 0, 200);
                $content = apply_filters('the_content', $content);
                $content = preg_replace('/\<[\/]{0,1}div[^\>]*\>/i', '', $content);
            }
            ?>
            <div class="col-xs-12" id="homepage-wrapper" style="background-image: url(<?php echo esc_url($bg_image); ?>)">
                <div class="overlay">
                    <div class="inner-container">
                        <?php echo $content; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- END OF BANNER CONTAINER -->

        <!-- TOP 3 FEATURES CONTAINER -->
        <?php
        $top_features = array();
        for($l=1;$l<=3;$l++) {
            $feature_id = get_theme_mod('kk_top_feature_'.$l);
            if($feature_id)
            $top_features[] = get_theme_mod('kk_top_feature_'.$l);
        }
        if(!empty($top_features)) {
        ?>
            <div class="row section-row">
                <div class="container" id="top-features-wrapper">
                    <div class="row">
                        <?php foreach($top_features as $top_feature) { ?>
                            <div class="col-xs-12 col-md-4 inner-container">
                                <h4 class="section-title"><?php echo esc_html(get_the_title($top_feature)); ?></h4>
                                <?php echo konkurrent_content($top_feature); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        <!-- END OF TOP 3 FEATURES CONTAINER -->

        <div id="features-wrapper">
        <?php
            for($k=1;$k<=3;$k++) {
                $feature = get_theme_mod('kk_other_feature_'.$k);
                if($feature) {
                    $align_feature = get_theme_mod('kk_other_feature_align'.$k);

                    if($align_feature === 'left') {
                        $img_class = 'col-xs-12 col-md-6 col-md-pull-6 image-container';
                        $content_class = 'col-xs-12 col-md-6 col-md-push-6 content-container';
                    } else if($align_feature === 'right') {
                        $img_class = 'col-xs-12 col-md-6 image-container';
                        $content_class = 'col-xs-12 col-md-6  content-container';
                    } else {
                        $img_class = 'col-xs-12 image-container';
                        $content_class = 'col-xs-12 content-container';
                    }

                    if($k%2 === 0) {
                        $row_class = '';
                    } else {
                        $row_class = 'grey';
                    }
                ?>
                    <div class="row align-<?php echo esc_attr($align_feature); ?> <?php echo esc_attr($row_class); ?>">
                        <div class="<?php echo esc_attr($content_class); ?>">
                            <h4 class="section-title has-border"><?php echo esc_html(get_the_title($feature)); ?></h4>
                             <?php echo konkurrent_content($feature); ?>
                        </div>
                        <div class="<?php echo esc_attr($img_class); ?>">
                            <?php echo get_the_post_thumbnail($feature); ?>
                        </div>
                    </div>
            <?php
                }
            }
        ?>
        </div>

        <!-- RECENT POSTS SECTION -->

        <div class="row section-row" id="recent-posts">
            <div class="container">
                <div class="row">
                    <?php
                    $recent_posts = wp_get_recent_posts(array('numberposts' => 3));
                    if(!empty($recent_posts)) {
                        $blog_title = get_theme_mod('konkurrent_blog_title');
                        $blog_description = get_theme_mod('konkurrent_blog_description');
                    ?>
                        <div class="section-header">
                            <?php if($blog_title) { ?>
                                <h4 class="section-title has-border"><?php echo esc_html($blog_title); ?></h4>
                            <?php } ?>
                            <?php if($blog_description) { ?>
                                <p class="description"><?php echo esc_html($blog_description); ?></p>
                            <?php } ?>
                        </div>
                        <?php foreach( $recent_posts as $recent ){
                            $recent_post_id = $recent['ID'];
                        ?>
                            <div class="col-xs-12 col-sm-6 col-md-4 inner-container">
                                <div class="image-container">
                                    <a class="overlay" href="<?php echo esc_url(get_the_permalink($recent_post_id)); ?>" title="<?php the_title(); ?>"></a>
                                    <?php echo get_the_post_thumbnail($recent_post_id); ?>
                                </div>
                                <div class="content-container">
                                    <h4>
                                        <a href="<?php echo esc_url(get_the_permalink($recent_post_id)); ?>" title="<?php the_title(); ?>">
                                            <?php echo esc_html($recent['post_title']); ?>
                                        </a>
                                    </h4>
                                    <p><?php echo konkurrent_content($recent_post_id); ?></p>
                                </div>
                            </div>
                    <?php
                        }
                        wp_reset_postdata();
                    } ?>
                </div>
            </div>
        </div>

        <!-- END OF RECENT POSTS SECTION -->
    </div>
<?php
endwhile;