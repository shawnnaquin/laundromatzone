<div id="error-page-container">
    <div class="inner-container">
        <h2><?php _e('404', 'konkurrent'); ?></h2>
        <p>
            <?php _e('The page you are looking for no longer exists.', 'konkurrent'); ?><br />
            <?php _e('Perhaps you can return back to the site\'s', 'konkurrent'); ?>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php _e('Homepage', 'konkurrent'); ?></a>
            <?php _e('  and see if you can find what you are looking for.', 'konkurrent'); ?><br />
        </p>
    </div>
</div>