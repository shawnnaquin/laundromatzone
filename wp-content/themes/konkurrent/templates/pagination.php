<div id="pagination-container">
    <?php
    the_posts_pagination( array(
        'prev_text' => '<span class="glyphicon glyphicon-arrow-left"><span class="screen-reader-text">' . __( 'Previous page', 'konkurrent' ) . '</span>',
        'next_text' => '<span class="screen-reader-text">' . __( 'Next page', 'konkurrent' ) . '</span><span class="glyphicon glyphicon-arrow-right">',
        'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'konkurrent' ) . ' </span>',
    ) );
    ?>
</div>