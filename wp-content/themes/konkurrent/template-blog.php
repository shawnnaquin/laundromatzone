<?php
/**
 * Template Name: Blog Template
 */

get_template_part('templates/page', 'header');

$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
$wp_query = new WP_Query( array('paged' => $paged, 'post_type' => 'post') );
if ( $wp_query->have_posts() ) { ?>
    <div class="container" id="page-container">
        <div class="row">
            <div class="col-xs-12">
            <?php
                while ( $wp_query->have_posts() ) {
                    $wp_query->the_post();
                    get_template_part('templates/content', 'post');
                }

                get_template_part('templates/pagination');
                wp_reset_postdata();
            ?>
            </div>
        </div>
    </div>
<?php } else {
    echo __('No post found', 'konkurrent');
}