<?php use Konkurrent\Site\Titles; ?>
<div class="container-fluid" id="page-header">
    <div class="row">
        <?php
        global $post;
        if ( is_home() ) {
            $post_id = get_option( 'page_for_posts' );
        } else {
            $post_id = $post->ID;
        }
        if(has_post_thumbnail($post_id)) {
            $style = "background-image:url('".esc_url(get_the_post_thumbnail_url($post_id))."')";
            $img_class = '';
        } else {
            $header_image = get_header_image();
            if($header_image != '') {
                $style = "background-image:url('".esc_url($header_image)."')";
                $img_class = '';
            } else {
                $style = '';
                $img_class = 'canvas-exists';
            }
        }
        ?>
        <div class="image-container <?php echo esc_attr($img_class); ?>" style="<?php echo esc_attr($style); ?>">
            <h1><?php echo Titles\title(); ?></h1>
        </div>
    </div>
</div>