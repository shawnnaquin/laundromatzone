<?php
if (post_password_required()) {
  return;
}
?>

<section id="comments" class="comments">
    <?php if (have_comments()) : ?>
        <h2>
            <span class="glyphicon glyphicon-comment"></span>
            <?php echo esc_html(get_comments_number()). ' '. __('Comments', 'konkurrent'); ?>
        </h2>

        <ol class="comment-list">
            <?php wp_list_comments(['style' => 'ol', 'short_ping' => true]); ?>
        </ol>

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : ?>
            <nav>
                <ul class="pager">
                    <?php if (get_previous_comments_link()) : ?>
                        <li class="previous"><?php previous_comments_link(__('&larr; Older comments', 'konkurrent')); ?></li>
                    <?php endif; ?>
                    <?php if (get_next_comments_link()) : ?>
                        <li class="next"><?php next_comments_link(__('Newer comments &rarr;', 'konkurrent')); ?></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!comments_open() && get_comments_number() != '0' && post_type_supports(get_post_type(), 'comments')) : ?>
      <div class="alert alert-warning">
        <?php _e('Comments are closed.', 'konkurrent'); ?>
      </div>
    <?php endif; ?>

    <?php comment_form(); ?>
</section>