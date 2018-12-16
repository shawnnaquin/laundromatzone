<?php
 /*
Plugin Name: Laundromatzone Custom CSS 2019
Plugin URI: wordpress codex
Description: widget
Author: wordpress codex
Version: 1
Author URI: https://wordpress.com
*/

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'WPFA-Plugin-Style', plugin_dir_url( __FILE__ ) . 'custom-css.css' );
}, 1000 );

?>
