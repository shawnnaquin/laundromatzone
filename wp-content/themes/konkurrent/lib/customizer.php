<?php

namespace Konkurrent\Site\Customizer;

use Konkurrent\Site\Assets;

/**
 * Add postMessage support
 */
function konkurrent_customize_register($wp_customize) {

    $wp_customize->add_panel( 'homepage_panel', array(
        'priority' => 10,
        'capability' => 'edit_theme_options',
        'theme_supports' => '',
        'title' => __( 'Homepage', 'konkurrent' ),
        'description' => __( 'Manage homepage content.', 'konkurrent' ),
    ) );

    // TOP 3 FEATURES SECTION

    $wp_customize->add_section( 'top_three_features', array(
        'priority' => 10,
        'capability' => 'edit_theme_options',
        'theme_supports' => '',
        'title' => __( 'Top 3 Features', 'konkurrent' ),
        'description' => '',
        'panel' => 'homepage_panel',
    ) );


    // TOP 3 FEATURES CONTROLLERS

    for($l=1;$l<=3;$l++) {
        $wp_customize->add_setting( 'kk_top_feature_'.$l, array(
            'default' => '',
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'transport' => '',
            'sanitize_callback' => 'absint'
        ) );
        $wp_customize->add_control( 'kk_top_feature_'.$l, array(
                'label'          => sprintf( __( 'Feature %d', 'konkurrent' ), $l ),
                'section'        => 'top_three_features',
                'settings' => 'kk_top_feature_'.$l,
                'type'           => 'dropdown-pages',
                'allow_addition' => true,
                'active_callback' => '',
        ) );
    }

    // LATEST BLOG SETTINGS

    $wp_customize->add_section( 'konkurrent_blog_settings', array(
        'priority' => 10,
        'capability' => 'edit_theme_options',
        'theme_supports' => '',
        'title' => __( 'Blog Settings', 'konkurrent' ),
        'description' => '',
        'panel' => 'homepage_panel',
    ) );

    $wp_customize->add_setting( 'konkurrent_blog_title', array(
        'default' => '',
        'type' => 'theme_mod',
        'capability' => 'edit_theme_options',
        'transport' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'konkurrent_blog_title', array(
        'type' => 'text',
        'priority' => 10,
        'section' => 'konkurrent_blog_settings',
        'label' => __( 'Blog Title', 'konkurrent' ),
        'description' => '',
    ) );

    $wp_customize->add_setting( 'konkurrent_blog_description', array(
        'default' => '',
        'type' => 'theme_mod',
        'capability' => 'edit_theme_options',
        'transport' => '',
        'sanitize_callback' => 'esc_textarea',
    ) );
    $wp_customize->add_control( 'konkurrent_blog_description', array(
        'type' => 'textarea',
        'priority' => 10,
        'section' => 'konkurrent_blog_settings',
        'label' => __( 'Blog Description', 'konkurrent' ),
        'description' => '',
    ) );

    // THEME FEATURES SECTION

    $wp_customize->add_panel( 'kk_theme_features', array(
        'priority' => 10,
        'capability' => 'edit_theme_options',
        'theme_supports' => '',
        'title' => __( 'Features', 'konkurrent' ),
        'description' => __( 'Manage homepage content.', 'konkurrent' ),
    ) );

    // THEME FEATURES CONTROLLERS

    for($k=1;$k<=3;$k++) {
        $wp_customize->add_section( 'kk_theme_features'.$k, array(
            'priority' => 10,
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'title' => __( 'Feature ', 'konkurrent' ).$k,
            'panel' => 'kk_theme_features',
        ) );

        $wp_customize->add_setting( 'kk_other_feature_'.$k, array(
            'default' => '',
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'transport' => '',
            'sanitize_callback' => 'absint'
        ) );
        
        $wp_customize->add_control( 'kk_other_feature_'.$k, array(
                'label'          => sprintf( __( 'Feature %d', 'konkurrent' ), $k ),
                'section' => 'kk_theme_features'.$k,
                'settings' => 'kk_other_feature_'.$k,
                'type'           => 'dropdown-pages',
                'allow_addition' => true,
                'active_callback' => '',
        ) );

        $wp_customize->add_setting( 'kk_other_feature_align'.$k, array(
            'default' => '',
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'transport' => '',
            'sanitize_callback' => 'konkurrent_sanitize_select'
        ) );
        
        $wp_customize->add_control( 'kk_other_feature_align'.$k, array(
                'label' => __( 'Align feature', 'konkurrent' ),
                'section' => 'kk_theme_features'.$k,
                'settings' => 'kk_other_feature_align'.$k,
                'type'           => 'select',
                'choices'  => array(
                    'left' => __('Left', 'konkurrent'),
                    'right' => __('Right', 'konkurrent'),
                    'center' => __('Center', 'konkurrent')
                )
        ) );
    }


    //THEME OPTIONS SECTION
    
    $wp_customize->add_panel(
        'konkurrent_theme_options', 
            array(
            'priority' => 10,
            'capability' => 'edit_theme_options',
            'theme_supports' => '',
            'title' => __( 'Theme Settings', 'konkurrent' ),
            'description' => __( 'Description of what this panel does.', 'konkurrent' ),
        ) 
    );

    $wp_customize->add_section( 'konkurrent_theme_options', array(
        'title'    => __( 'Theme Options', 'konkurrent' ),
        'priority' => 130,
        'panel'     => 'konkurrent_theme_options'
     ) );
    
    $wp_customize->add_section(
        'header_image',
        array(
            'title' => __( 'Header Image', 'konkurrent' ),
            'description' => __( 'This is a section for the image to be displayed on single and archive page header.', 'konkurrent' ),
            'priority' => 10,
            'panel' => 'konkurrent_theme_options',
        )
    );

    $wp_customize->add_control(
        'single_page_image',
        array(
            'label' => __( 'Header Image', 'konkurrent' ),
            'section' => 'header_image',
            'type' => 'text',
            'panel' => 'konkurrent_theme_options'
        )
    );

    $social_icons = array( 'facebook', 'twitter', 'linkedin', 'pinterest');

    foreach($social_icons as $social_icon) {
        $wp_customize->add_setting( $social_icon.'_link', array(
            'default' => '',
            'type' => 'theme_mod',
            'capability' => 'edit_theme_options',
            'transport' => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( $social_icon.'_link', array(
            'type' => 'text',
            'priority' => 10,
            'section' => 'konkurrent_theme_options',
            'label' => __( 'Link to ', 'konkurrent' ). $social_icon,
            'description' => '',
        ) );

        $wp_customize->add_setting( 'footer_text', array(
            'type'              => 'theme_mod',
            'default'           => '',
            'capability' => 'edit_theme_options',
            'transport'         => '',
            'sanitize_callback' => 'konkurrent_sanitize_powered_by',
        ) );

        $wp_customize->add_control( 'footer_text', array(
            'label'       => __( 'Footer Text', 'konkurrent' ),
            'section'     => 'konkurrent_theme_options',
            'type'        => 'textarea',
            'description' => '',
        ) );

    }
    
    $wp_customize->get_setting('blogname')->transport = 'postMessage';
    $wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
    $wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';
}
add_action('customize_register', __NAMESPACE__ . '\\konkurrent_customize_register');

/**
 * Customizer JS
 */
function konkurrent_customize_preview_js() {
  wp_enqueue_script('konkurrent-customizer', Assets\konkurrent_asset_path('scripts/customizer.js'), ['customize-preview'], null, true);
}
add_action('customize_preview_init', __NAMESPACE__ . '\\konkurrent_customize_preview_js');