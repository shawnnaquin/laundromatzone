<?php

namespace Konkurrent\Site\Setup;

use Konkurrent\Site\Assets;

/**
 * Theme setup
 */
function konkurrent_setup() {

    add_theme_support( 'automatic-feed-links' );
    load_theme_textdomain('konkurrent', get_template_directory() . '/lang');
    add_theme_support('title-tag');
    register_nav_menus([
      'primary_navigation' => __('Primary Navigation', 'konkurrent')
    ]);
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['caption', 'comment-form', 'comment-list', 'gallery', 'search-form']);
    $header_default_image = register_default_headers( array(
    'default-image' => array(
        'url'           => get_stylesheet_directory_uri() . '/dist/images/default-header.jpg',
        'thumbnail_url' => get_stylesheet_directory_uri() . '/dist/images/default-header.jpg',
        'description'   => __( 'Default Header Image', 'konkurrent' )
    ),
) );
    add_theme_support( 'custom-header', array(
        $header_default_image,
        'width'             => 2000,
        'height'            => 1200,
        'flex-height'       => true,
        'video'             => false,
        'header-text'       => 'Inner page image',
        'title'             => 'Single Page image'
    ) );
    add_theme_support( 'custom-logo', array(
	'height'      => 250,
	'width'       => 250,
	'flex-height' => true,
	'flex-width'  => true,
	'header-text' => array( 'site-title', 'site-description' ),
    ) );
    if ( ! isset( $content_width ) ) $content_width = 1170;
  
    add_editor_style(Assets\konkurrent_asset_path('styles/main.css'));
}
add_action('after_setup_theme', __NAMESPACE__ . '\\konkurrent_setup');

/**
 * Register sidebars
 */
function konkurrent_widgets_init() {
  register_sidebar([
    'name'          => __('Primary', 'konkurrent'),
    'id'            => 'sidebar-primary',
    'before_widget' => '<section class="widget %1$s %2$s">',
    'after_widget'  => '</section>',
    'before_title'  => '<h3>',
    'after_title'   => '</h3>'
  ]);
}
add_action('widgets_init', __NAMESPACE__ . '\\konkurrent_widgets_init');

/**
 * Determine which pages should NOT display the sidebar
 */
function konkurrent_display_sidebar() {
    static $display;

    isset($display) || $display = !in_array(true, [
        is_404(),
        is_front_page(),
        is_page_template('template-blog.php'),
        is_page(),
        is_single(),
        is_search(),
        is_archive(),
        is_home()
    ]);

    return apply_filters('konkurrent/konkurrent_display_sidebar', $display);
}

/**
 * Theme assets
 */
function konkurrent_assets() {
    wp_enqueue_style('konkurrent-open-sans-font', 'https://fonts.googleapis.com/css?family=Open+Sans', false, null);
    wp_enqueue_style('konkurrent-css', Assets\konkurrent_asset_path('styles/main.css'), false, null);
    if (is_single() && comments_open() && get_option('thread_comments')) {
      wp_enqueue_script('comment-reply');
    }
    wp_enqueue_script('konkurrent-js', Assets\konkurrent_asset_path('scripts/main.js'), ['jquery'], null, true);
    $konkurrent_data = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
    );
    wp_localize_script( 'konkurrent-js', 'kk_script_vars', $konkurrent_data );
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\konkurrent_assets', 100);