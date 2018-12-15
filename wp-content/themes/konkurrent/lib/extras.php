<?php

namespace Konkurrent\Site\Extras;

use Konkurrent\Site\Setup;

/**
 * Add <body> classes
 */
function konkurrent_body_class($classes) {
    // Add page slug if it doesn't exist
    if (is_single() || is_page() && !is_front_page()) {
        if (!in_array(basename(esc_url(get_permalink())), $classes)) {
            $classes[] = basename(esc_url(get_permalink()));
        }
    }

    // Add class if sidebar is active
    if (Setup\konkurrent_display_sidebar()) {
        $classes[] = 'sidebar-primary';
    }

    return $classes;
}
add_filter('body_class', __NAMESPACE__ . '\\konkurrent_body_class');

/**
 * Clean up the_excerpt()
 */

add_filter( 'excerpt_more',  __NAMESPACE__ . '\\konkurrent_read_more_link' );
function konkurrent_read_more_link() {
    return '';
}