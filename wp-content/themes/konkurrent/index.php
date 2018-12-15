<?php get_template_part('templates/page', 'header'); ?>

<?php if (!have_posts()) : ?>
  <div class="alert alert-warning">
    <?php _e('Sorry, no results were found.', 'konkurrent'); ?>
  </div>
  <?php get_search_form(); ?>
<?php endif; ?>
<div class="container" id="page-container">
    <div class="row">
        <div class="col-xs-12">
            <?php
                while (have_posts()) : the_post();
                    get_template_part('templates/content', 'post');
                endwhile;
            get_template_part('templates/pagination');
            ?>
        </div>
    </div>
</div>