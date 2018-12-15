<?php
use Konkurrent\Site\Setup;
use Konkurrent\Site\Wrapper;
?>

<!doctype html>
<html <?php language_attributes(); ?>>
  <?php get_template_part('templates/head'); ?>
  <body <?php body_class(); ?>>
    <!--[if IE]>
      <div class="alert alert-warning">
        <?php _e('You are using an outdated browser. Please upgrade your browser to improve your experience.', 'konkurrent'); ?>
      </div>
    <![endif]-->
    <?php
      do_action('get_header');
      get_template_part('templates/header');
    ?>
    <div class="wrap container-fluid" role="document">
      <div class="content row">
        <main class="main">
            <div class="row">
                <?php include Wrapper\konkurrent_template_path(); ?>
            </div>
        </main><!-- /.main -->
        <?php if (Setup\konkurrent_display_sidebar()) : ?>
          <aside class="sidebar">
            <?php include Wrapper\konkurrent_sidebar_path(); ?>
          </aside><!-- /.sidebar -->
        <?php endif; ?>
      </div><!-- /.content -->
    </div><!-- /.wrap -->
    <?php
      do_action('get_footer');
      get_template_part('templates/footer');
      wp_footer();
    ?>
  </body>
</html>