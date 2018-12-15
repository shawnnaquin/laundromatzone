<time class="updated" datetime="<?php echo esc_attr(get_post_time('c', true)); ?>">
    <?php echo esc_html(get_the_date()); ?>
</time>
<p class="byline author vcard">
    <?php echo __('By', 'konkurrent'); ?>
    <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>" rel="author" class="fn">
        <?php echo get_the_author(); ?>
    </a>
</p>
